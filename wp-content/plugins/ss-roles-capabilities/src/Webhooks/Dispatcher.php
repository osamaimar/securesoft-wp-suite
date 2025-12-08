<?php
/**
 * Outgoing webhook dispatcher.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Webhooks;

use SS_Roles_Capabilities\Admin\Screens\Webhooks as Webhooks_Screen;

/**
 * Sends webhook requests for SecureSoft role/user events.
 */
class Dispatcher {

	/**
	 * Dispatch a webhook event.
	 *
	 * @param string $event   Event key (e.g. user_registered, user_role_changed).
	 * @param array  $payload Payload data.
	 * @return void
	 */
	public function dispatch( $event, $payload ) {
		$webhooks = get_option( Webhooks_Screen::OPTION_NAME, array() );
		if ( ! is_array( $webhooks ) || empty( $webhooks ) ) {
			return;
		}

		/**
		 * Filter the outgoing webhook payload.
		 *
		 * @param array  $payload Payload.
		 * @param string $event   Event key.
		 */
		$filtered_payload = apply_filters( 'ss/roles/outgoing_webhook_payload', $payload, $event );

		$body = wp_json_encode( $filtered_payload );

		// Dispatch to all enabled webhooks for this event.
		foreach ( $webhooks as $webhook_id => $webhook ) {
			if ( empty( $webhook['enabled'] ) || $webhook['event'] !== $event ) {
				continue;
			}

			$url     = isset( $webhook['url'] ) ? $webhook['url'] : '';
			$secret  = isset( $webhook['secret'] ) ? $webhook['secret'] : '';
			$signature = '';

			if ( empty( $url ) ) {
				continue;
			}

			if ( ! empty( $secret ) ) {
				$signature = hash_hmac( 'sha256', $body, $secret );
			}

			$args = array(
				'method'      => 'POST',
				'body'        => $body,
				'headers'     => array(
					'Content-Type'      => 'application/json',
					'X-SS-Event'        => $event,
					'X-SS-Signature'    => $signature,
				),
				'data_format' => 'body',
				'timeout'     => 10,
			);

			$response = wp_remote_post( $url, $args );

			// Retry on failure if enabled.
			if ( ! empty( $webhook['retry'] ) && ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) ) {
				// Schedule retry (simplified - can be enhanced with cron).
				$this->schedule_retry( $webhook_id, $event, $filtered_payload );
			}
		}
	}

	/**
	 * Schedule webhook retry.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @param string $event     Event name.
	 * @param array  $payload   Payload data.
	 * @return void
	 */
	protected function schedule_retry( $webhook_id, $event, $payload ) {
		// Use WordPress cron to retry failed webhooks.
		wp_schedule_single_event( time() + 300, 'ss_webhook_retry', array( $webhook_id, $event, $payload ) );
	}
}








