<?php
/**
 * Key store manager class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\KeyStore;

/**
 * Key store manager class.
 */
class Manager {

	/**
	 * Backup keys to encrypted storage.
	 *
	 * @return string|false Backup data or false on failure.
	 */
	public function backup_keys() {
		$keys = get_option( 'ss_core_encryption_keys', array() );
		$version = get_option( 'ss_core_encryption_key_version', 1 );

		$backup_data = array(
			'keys' => $keys,
			'active_version' => $version,
			'backup_date' => current_time( 'mysql' ),
		);

		// Encrypt backup with a separate backup key if available.
		$backup_key = defined( 'SS_CORE_BACKUP_KEY' ) ? SS_CORE_BACKUP_KEY : get_option( 'ss_core_backup_key' );

		if ( $backup_key ) {
			// Simple encryption for backup (AES-256-CBC).
			$iv = openssl_random_pseudo_bytes( 16 );
			$encrypted = openssl_encrypt(
				wp_json_encode( $backup_data ),
				'aes-256-cbc',
				$backup_key,
				0,
				$iv
			);
			return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		// Return JSON if no backup key.
		return wp_json_encode( $backup_data, JSON_PRETTY_PRINT );
	}

	/**
	 * Restore keys from backup.
	 *
	 * @param string $backup_data Backup data.
	 * @return bool Success status.
	 */
	public function restore_keys( $backup_data ) {
		// Try to decrypt if backup key exists.
		$backup_key = defined( 'SS_CORE_BACKUP_KEY' ) ? SS_CORE_BACKUP_KEY : get_option( 'ss_core_backup_key' );

		if ( $backup_key ) {
			$data = base64_decode( $backup_data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( $data ) {
				$iv = substr( $data, 0, 16 );
				$encrypted = substr( $data, 16 );
				$decrypted = openssl_decrypt(
					$encrypted,
					'aes-256-cbc',
					$backup_key,
					0,
					$iv
				);
				if ( $decrypted ) {
					$backup_data = $decrypted;
				}
			}
		}

		$data = json_decode( $backup_data, true );
		if ( ! $data || ! isset( $data['keys'] ) ) {
			return false;
		}

		update_option( 'ss_core_encryption_keys', $data['keys'] );
		update_option( 'ss_core_encryption_key_version', $data['active_version'] );

		return true;
	}
}

