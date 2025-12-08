<?php
/**
 * REST controller for incoming webhooks (optional).
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\REST\Controllers;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Webhooks REST controller.
 */
class Webhooks extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ss/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'roles/webhook';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_incoming_webhook' ),
					'permission_callback' => '__return_true', // Signature validation is handled in the callback.
				),
			)
		);
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_incoming_webhook( WP_REST_Request $request ) {
		// Example: validate HMAC signature from header X-SS-Signature with a shared secret.
		$signature = $request->get_header( 'X-SS-Signature' );
		$body      = $request->get_body();

		/**
		 * Filter the shared secret for incoming webhooks.
		 *
		 * In a real-world implementation you might store per-client secrets.
		 *
		 * @param string $secret Shared secret.
		 */
		$secret = apply_filters( 'ss/roles/incoming_webhook_secret', '' );

		if ( ! empty( $secret ) ) {
			$expected = hash_hmac( 'sha256', $body, $secret );
			if ( ! hash_equals( $expected, $signature ) ) {
				return new WP_Error( 'ss_webhook_invalid_signature', __( 'Invalid webhook signature.', 'ss-roles-capabilities' ), array( 'status' => 401 ) );
			}
		}

		// Trigger a generic action for listeners.
		do_action( 'ss/roles/incoming_webhook', json_decode( $body, true ), $request );

		return new WP_REST_Response( array( 'status' => 'ok' ) );
	}
}



