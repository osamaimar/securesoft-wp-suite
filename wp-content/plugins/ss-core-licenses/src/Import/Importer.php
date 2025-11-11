<?php
/**
 * License importer class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Import;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;

/**
 * Importer class.
 */
class Importer {

	/**
	 * License service instance.
	 *
	 * @var Service
	 */
	private $license_service;

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Service $license_service License service.
	 * @param Logger  $audit_logger    Audit logger.
	 */
	public function __construct( Service $license_service, Logger $audit_logger ) {
		$this->license_service = $license_service;
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Import file.
	 *
	 * @param array  $file              Uploaded file array.
	 * @param int    $product_id        Product ID.
	 * @param string $provider_ref_column Provider reference column name.
	 * @return array Result with success status and count.
	 */
	public function import_file( $file, $product_id, $provider_ref_column = 'provider_ref' ) {
		$file_path = $file['tmp_name'];
		$file_name = $file['name'];
		$file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		// Parse file based on extension.
		if ( 'csv' === $file_ext ) {
			$rows = $this->parse_csv( $file_path );
		} elseif ( in_array( $file_ext, array( 'xlsx', 'xls' ), true ) ) {
			$rows = $this->parse_excel( $file_path );
		} else {
			return array(
				'success' => false,
				'message' => __( 'Unsupported file format. Please use CSV or Excel files.', 'ss-core-licenses' ),
			);
		}

		if ( empty( $rows ) ) {
			return array(
				'success' => false,
				'message' => __( 'No data found in file.', 'ss-core-licenses' ),
			);
		}

		// Import rows.
		$imported = 0;
		$failed = 0;
		$duplicates = array();
		$errors = array();
		$total_rows = count( $rows );

		foreach ( $rows as $row_index => $row ) {
			// Filter row data.
			$row = apply_filters( 'ss/licenses/import/row', $row );

			// Extract license code.
			$code = $this->extract_code( $row );

			if ( empty( $code ) ) {
				$failed++;
				$errors[] = array(
					'row' => $row_index + 1,
					'code' => '',
					'reason' => __( 'Empty license code', 'ss-core-licenses' ),
				);
				continue;
			}

			// Check for duplicates.
			$existing = $this->license_service->license_exists( $code, $product_id );
			if ( $existing ) {
				$duplicates[] = array(
					'row' => $row_index + 1,
					'code' => $code,
					'existing_id' => $existing['id'],
					'existing_status' => $existing['status'],
				);
				$failed++;
				continue;
			}

			// Extract provider reference.
			$provider_ref = null;
			if ( isset( $row[ $provider_ref_column ] ) && ! empty( trim( $row[ $provider_ref_column ] ) ) ) {
				$provider_ref = sanitize_text_field( trim( $row[ $provider_ref_column ] ) );
			}

			// Create license.
			$license_id = $this->license_service->create_license(
				$code,
				$product_id,
				array(
					'provider_ref' => $provider_ref,
					'imported_from' => $file_name,
					'imported_at' => current_time( 'mysql' ),
				)
			);

			if ( $license_id ) {
				$imported++;
			} else {
				$failed++;
				$errors[] = array(
					'row' => $row_index + 1,
					'code' => $code,
					'reason' => __( 'Failed to create license', 'ss-core-licenses' ),
				);
			}
		}

		// Log import event.
		$this->audit_logger->log(
			get_current_user_id(),
			'licenses_imported',
			'license',
			null,
			array(
				'product_id' => $product_id,
				'imported' => $imported,
				'failed' => $failed,
				'duplicates' => count( $duplicates ),
				'file' => $file_name,
			)
		);

		return array(
			'success' => true,
			'count' => $imported,
			'failed' => $failed,
			'duplicates' => $duplicates,
			'errors' => $errors,
			'total' => $total_rows,
			'message' => sprintf(
				// translators: %d: number of imported licenses.
				_n( '%d license imported successfully.', '%d licenses imported successfully.', $imported, 'ss-core-licenses' ),
				$imported
			),
		);
	}

	/**
	 * Parse CSV file.
	 *
	 * @param string $file_path File path.
	 * @return array Array of rows.
	 */
	private function parse_csv( $file_path ) {
		$rows = array();
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( false === $handle ) {
			return $rows;
		}

		// Read header.
		$header = fgetcsv( $handle );

		// Read rows.
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( $row ) ) {
				continue;
			}

			// Combine header with row.
			if ( $header ) {
				$row_data = array_combine( $header, $row );
			} else {
				// No header, use first column as code.
				$row_data = array( 'code' => $row[0] );
			}

			$rows[] = $row_data;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $rows;
	}

	/**
	 * Parse Excel file.
	 *
	 * @param string $file_path File path.
	 * @return array Array of rows.
	 */
	private function parse_excel( $file_path ) {
		// For Excel files, we'll use a simple approach.
		// In production, you might want to use PhpSpreadsheet library.
		// For now, we'll convert to CSV and parse.

		// Try to use PhpSpreadsheet if available.
		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return $this->parse_excel_with_phpspreadsheet( $file_path );
		}

		// Fallback: try to read as CSV if it's actually CSV with .xlsx extension.
		return $this->parse_csv( $file_path );
	}

	/**
	 * Parse Excel file with PhpSpreadsheet.
	 *
	 * @param string $file_path File path.
	 * @return array Array of rows.
	 */
	private function parse_excel_with_phpspreadsheet( $file_path ) {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file_path );
		$worksheet = $spreadsheet->getActiveSheet();
		$rows = array();

		// Get header.
		$header = array();
		$header_row = $worksheet->getRowIterator( 1, 1 )->current();
		foreach ( $header_row->getCellIterator() as $cell ) {
			$header[] = $cell->getValue();
		}

		// Get data rows.
		foreach ( $worksheet->getRowIterator( 2 ) as $row ) {
			$row_data = array();
			$cell_iterator = $row->getCellIterator();
			$cell_iterator->setIterateOnlyExistingCells( false );

			$index = 0;
			foreach ( $cell_iterator as $cell ) {
				$key = isset( $header[ $index ] ) ? $header[ $index ] : 'code';
				$row_data[ $key ] = $cell->getValue();
				$index++;
			}

			if ( ! empty( $row_data ) ) {
				$rows[] = $row_data;
			}
		}

		return $rows;
	}

	/**
	 * Extract license code from row.
	 *
	 * @param array $row Row data.
	 * @return string License code.
	 */
	private function extract_code( $row ) {
		// Try common column names.
		$code_keys = array( 'code', 'license', 'license_code', 'key', 'activation_key', 'product_key' );

		foreach ( $code_keys as $key ) {
			if ( isset( $row[ $key ] ) && ! empty( $row[ $key ] ) ) {
				return trim( sanitize_text_field( $row[ $key ] ) );
			}
		}

		// If no column match, use first value.
		if ( ! empty( $row ) ) {
			$first_value = reset( $row );
			if ( ! empty( $first_value ) ) {
				return trim( sanitize_text_field( $first_value ) );
			}
		}

		return '';
	}
}

