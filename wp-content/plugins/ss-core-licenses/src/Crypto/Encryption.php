<?php
/**
 * Encryption class for AES-256-GCM encryption.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Crypto;

/**
 * Encryption class.
 */
class Encryption {

	/**
	 * Encryption algorithm.
	 *
	 * @var string
	 */
	private const ALGORITHM = 'aes-256-gcm';

	/**
	 * Key length in bytes.
	 *
	 * @var int
	 */
	private const KEY_LENGTH = 32;

	/**
	 * IV length in bytes.
	 *
	 * @var int
	 */
	private const IV_LENGTH = 12;

	/**
	 * Tag length in bytes.
	 *
	 * @var int
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Get encryption key.
	 *
	 * @param int $version Key version (null for active key).
	 * @return string|false Encryption key or false on failure.
	 */
	public function get_key( $version = null ) {
		$keys = $this->get_all_keys();

		if ( empty( $keys ) ) {
			// No keys found, try to initialize.
			$this->initialize_keys();
			$keys = $this->get_all_keys();
		}

		if ( null === $version ) {
			$version = $this->get_active_key_version();
		}

		if ( ! isset( $keys[ $version ] ) ) {
			return false;
		}

		$key = $keys[ $version ];

		if ( ! is_string( $key ) || empty( $key ) ) {
			error_log( 'SS Core: Encryption key is not a valid string. Version: ' . $version ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		// Keys stored in options are base64 encoded, so decode them.
		// Check if it's already raw binary (32 bytes).
		if ( strlen( $key ) === self::KEY_LENGTH ) {
			// Already raw binary, use as-is.
			return $key;
		}

		// Try to decode base64.
		$decoded = base64_decode( $key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( $decoded && strlen( $decoded ) === self::KEY_LENGTH ) {
			return $decoded;
		}

		// If decoding failed, log the error.
		error_log( 'SS Core: Invalid encryption key format. Key length: ' . strlen( $key ) . ', Expected after decode: ' . self::KEY_LENGTH . ', Version: ' . $version ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return false;
	}

	/**
	 * Get all encryption keys.
	 *
	 * @return array Array of keys indexed by version.
	 */
	public function get_all_keys() {
		$keys = get_option( 'ss_core_encryption_keys', array() );

		// If keys are stored in environment, use them.
		if ( defined( 'SS_CORE_ENCRYPTION_KEY' ) ) {
			$env_key = SS_CORE_ENCRYPTION_KEY;
			// Decode if it's base64.
			$decoded = base64_decode( $env_key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( $decoded && strlen( $decoded ) === self::KEY_LENGTH ) {
				$keys[1] = $decoded;
			} elseif ( strlen( $env_key ) === self::KEY_LENGTH ) {
				$keys[1] = $env_key;
			} else {
				$keys[1] = $env_key; // Use as-is, will be handled in get_key.
			}
		}

		// Return keys as-is (they're stored as base64 in options).
		// Decoding will happen in get_key() when needed.
		return $keys;
	}

	/**
	 * Get active key version.
	 *
	 * @return int Active key version.
	 */
	public function get_active_key_version() {
		return (int) get_option( 'ss_core_encryption_key_version', 1 );
	}

	/**
	 * Initialize encryption keys.
	 */
	public function initialize_keys() {
		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			error_log( 'SS Core: OpenSSL is not available on this server. Encryption will not work.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		$keys = $this->get_all_keys();

		if ( empty( $keys ) ) {
			// Generate new key.
			$key = $this->generate_key();
			if ( $key ) {
				// Store key as base64 for safe storage.
				$keys[1] = base64_encode( $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				update_option( 'ss_core_encryption_keys', $keys );
				update_option( 'ss_core_encryption_key_version', 1 );
				return true;
			} else {
				error_log( 'SS Core: Failed to generate encryption key.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate a new encryption key.
	 *
	 * @return string Random key.
	 */
	public function generate_key() {
		if ( function_exists( 'random_bytes' ) ) {
			return random_bytes( self::KEY_LENGTH );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return openssl_random_pseudo_bytes( self::KEY_LENGTH );
		} else {
			// Fallback (less secure).
			wp_die( esc_html__( 'Secure random number generation is not available on this server.', 'ss-core-licenses' ) );
		}
	}

	/**
	 * Encrypt data.
	 *
	 * @param string $plaintext Plaintext to encrypt.
	 * @param array  $context   Additional context (e.g., key version).
	 * @return string|false Encrypted data (base64 encoded with IV and tag) or false on failure.
	 */
	public function encrypt( $plaintext, $context = array() ) {
		if ( empty( $plaintext ) ) {
			return false;
		}

		// Allow filtering context.
		$context = apply_filters( 'ss/encrypt/context', $context );

		// Get key version from context or use active key.
		$key_version = isset( $context['key_version'] ) ? $context['key_version'] : null;
		$key = $this->get_key( $key_version );

		if ( ! $key ) {
			return false;
		}

		// Check if OpenSSL supports GCM mode.
		$ciphers = openssl_get_cipher_methods();
		$use_gcm = in_array( self::ALGORITHM, $ciphers, true );

		if ( ! $use_gcm ) {
			// Fallback to AES-256-CBC if GCM is not available.
			$iv = $this->generate_iv_cbc();
			$ciphertext = openssl_encrypt(
				$plaintext,
				'aes-256-cbc',
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);
			if ( false === $ciphertext ) {
				$error = openssl_error_string();
				error_log( 'SS Core: CBC Encryption failed. OpenSSL error: ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
			// For CBC, we don't have a tag, so we'll use HMAC for integrity.
			$hmac = hash_hmac( 'sha256', $ciphertext, $key, true );
			$encrypted = base64_encode( $iv . $hmac . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			
			// Prepend key version if not using active key.
			if ( $key_version && $key_version !== $this->get_active_key_version() ) {
				$encrypted = 'v' . $key_version . ':' . $encrypted;
			}
			
			return 'cbc:' . $encrypted;
		}

		// Encrypt with GCM mode (preferred).
		$iv = $this->generate_iv();
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::ALGORITHM,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext ) {
			$error = openssl_error_string();
			error_log( 'SS Core: GCM Encryption failed. OpenSSL error: ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		// Combine IV, tag, and ciphertext.
		$encrypted = base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Prepend key version if not using active key.
		if ( $key_version && $key_version !== $this->get_active_key_version() ) {
			$encrypted = 'v' . $key_version . ':' . $encrypted;
		}

		return $encrypted;
	}

	/**
	 * Decrypt data.
	 *
	 * @param string $ciphertext Encrypted data (base64 encoded with IV and tag).
	 * @param array  $context    Additional context.
	 * @return string|false Decrypted plaintext or false on failure.
	 */
	public function decrypt( $ciphertext, $context = array() ) {
		if ( empty( $ciphertext ) ) {
			return false;
		}

		// Extract key version if present.
		$key_version = null;
		$original_ciphertext = $ciphertext;
		if ( preg_match( '/^v(\d+):(.+)$/', $ciphertext, $matches ) ) {
			$key_version = (int) $matches[1];
			$ciphertext = $matches[2];
		}

		// Get all available keys.
		$all_keys = $this->get_all_keys();
		if ( empty( $all_keys ) ) {
			return false;
		}

		// Build list of keys to try.
		$keys_to_try = array();
		
		// If specific version requested, try it first.
		if ( $key_version !== null && isset( $all_keys[ $key_version ] ) ) {
			$keys_to_try[ $key_version ] = $all_keys[ $key_version ];
		}
		
		// Add all other keys (try newer keys first, then older).
		$sorted_versions = array_keys( $all_keys );
		rsort( $sorted_versions ); // Try newer keys first.
		foreach ( $sorted_versions as $version ) {
			if ( ! isset( $keys_to_try[ $version ] ) ) {
				$keys_to_try[ $version ] = $all_keys[ $version ];
			}
		}

		// Try each key until one works.
		foreach ( $keys_to_try as $version => $key_data ) {
			$key = $this->get_key( $version );
			if ( ! $key ) {
				continue;
		}

		// Check if this is CBC mode (fallback).
		if ( strpos( $ciphertext, 'cbc:' ) === 0 ) {
				$cbc_ciphertext = substr( $ciphertext, 4 );
				$data = base64_decode( $cbc_ciphertext, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $data ) {
					continue;
			}
			$iv = substr( $data, 0, 16 );
			$hmac = substr( $data, 16, 32 );
			$encrypted = substr( $data, 48 );
			// Verify HMAC.
			$calculated_hmac = hash_hmac( 'sha256', $encrypted, $key, true );
			if ( ! hash_equals( $hmac, $calculated_hmac ) ) {
					continue;
			}
			// Decrypt.
			$plaintext = openssl_decrypt(
				$encrypted,
				'aes-256-cbc',
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);
				if ( false !== $plaintext ) {
			return $plaintext;
				}
				continue;
		}

		// Decode base64.
		$data = base64_decode( $ciphertext, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $data ) {
				continue;
		}

		// Extract IV, tag, and ciphertext.
		$iv = substr( $data, 0, self::IV_LENGTH );
		$tag = substr( $data, self::IV_LENGTH, self::TAG_LENGTH );
		$encrypted = substr( $data, self::IV_LENGTH + self::TAG_LENGTH );

		// Decrypt.
		$plaintext = openssl_decrypt(
			$encrypted,
			self::ALGORITHM,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

			if ( false !== $plaintext ) {
				return $plaintext;
			}
		}

		// All keys failed.
		error_log( 'SS Core: Decryption failed with all available keys. Ciphertext length: ' . strlen( $original_ciphertext ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return false;
	}

	/**
	 * Generate random IV.
	 *
	 * @return string Random IV.
	 */
	private function generate_iv() {
		if ( function_exists( 'random_bytes' ) ) {
			return random_bytes( self::IV_LENGTH );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return openssl_random_pseudo_bytes( self::IV_LENGTH );
		} else {
			wp_die( esc_html__( 'Secure random number generation is not available on this server.', 'ss-core-licenses' ) );
		}
	}

	/**
	 * Generate random IV for CBC mode (16 bytes).
	 *
	 * @return string Random IV.
	 */
	private function generate_iv_cbc() {
		if ( function_exists( 'random_bytes' ) ) {
			return random_bytes( 16 );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return openssl_random_pseudo_bytes( 16 );
		} else {
			wp_die( esc_html__( 'Secure random number generation is not available on this server.', 'ss-core-licenses' ) );
		}
	}

	/**
	 * Rotate encryption keys.
	 *
	 * @param bool $dry_run Whether to perform a dry run.
	 * @return array Result with success status and message.
	 */
	public function rotate_keys( $dry_run = false ) {
		$keys = $this->get_all_keys();
		$current_version = $this->get_active_key_version();
		$new_version = $current_version + 1;

		// Generate new key.
		$new_key = $this->generate_key();
		$keys[ $new_version ] = base64_encode( $new_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( $dry_run ) {
			return array(
				'success' => true,
				'message' => __( 'Dry run completed. New key would be generated.', 'ss-core-licenses' ),
				'new_version' => $new_version,
			);
		}

		// Update keys.
		update_option( 'ss_core_encryption_keys', $keys );
		update_option( 'ss_core_encryption_key_version', $new_version );

		return array(
			'success' => true,
			'message' => __( 'Keys rotated successfully.', 'ss-core-licenses' ),
			'new_version' => $new_version,
		);
	}
}

