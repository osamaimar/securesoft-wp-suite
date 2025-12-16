<?php
/**
 * WooCommerce product meta integration.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Woo;

/**
 * Product meta class.
 */
class ProductMeta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_tab' ) );
	}

	/**
	 * Add product tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_product_tab( $tabs ) {
		$tabs['securesoft_licenses'] = array(
			'label' => __( 'Licenses & Delivery', 'ss-core-licenses' ),
			'target' => 'securesoft_licenses_data',
			'class' => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Render product tab.
	 */
	public function render_product_tab() {
		global $post;

		if ( ! function_exists( 'woocommerce_wp_select' ) ) {
			return;
		}

		$product_id = $post->ID;

		// Get current values.
		$delivery_mode = get_post_meta( $product_id, '_ss_delivery_mode', true );
		$license_source = get_post_meta( $product_id, '_ss_license_source', true );
		$license_pool_id = get_post_meta( $product_id, '_ss_license_pool_id', true );
		$license_status = get_post_meta( $product_id, '_ss_license_status', true );

		// Get pool data.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool = $pool_repo->get_by_product( $product_id );

		$available_count = $pool ? $pool['qty_cached'] : 0;

		// Get counts.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$reserved_count = $license_repo->count_by_status( $product_id, 'reserved' );
		$sold_count = $license_repo->count_by_status( $product_id, 'sold' );
		?>
		<div id="securesoft_licenses_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id' => '_ss_delivery_mode',
						'label' => __( 'Delivery Mode', 'ss-core-licenses' ),
						'options' => array(
							'internal' => __( 'Internal', 'ss-core-licenses' ),
							'external' => __( 'External', 'ss-core-licenses' ),
						),
						'value' => $delivery_mode,
						'desc_tip' => true,
						'description' => __( 'Whether license keys are stored locally (internal) or provided by supplier (external).', 'ss-core-licenses' ),
					)
				);

				woocommerce_wp_select(
					array(
						'id' => '_ss_license_source',
						'label' => __( 'License Source', 'ss-core-licenses' ),
						'options' => array(
							'pool' => __( 'Pool', 'ss-core-licenses' ),
							'provider' => __( 'Provider', 'ss-core-licenses' ),
						),
						'value' => $license_source,
						'desc_tip' => true,
						'description' => __( 'Source of license delivery.', 'ss-core-licenses' ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id' => '_ss_license_pool_id',
						'label' => __( 'License Pool ID', 'ss-core-licenses' ),
						'value' => $license_pool_id,
						'type' => 'number',
						'desc_tip' => true,
						'description' => __( 'Linked license pool ID (auto-created if not exists).', 'ss-core-licenses' ),
					)
				);
				?>

				<div class="options_group">
					<h3><?php esc_html_e( 'License Statistics', 'ss-core-licenses' ); ?></h3>
					<p class="form-field">
						<label><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></label>
						<span class="ss-license-count available"><?php echo esc_html( $available_count ); ?></span>
					</p>
					<p class="form-field">
						<label><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></label>
						<span class="ss-license-count reserved"><?php echo esc_html( $reserved_count ); ?></span>
					</p>
					<p class="form-field">
						<label><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></label>
						<span class="ss-license-count sold"><?php echo esc_html( $sold_count ); ?></span>
					</p>
				</div>

				<?php
				woocommerce_wp_textarea_input(
					array(
						'id' => '_ss_license_notes',
						'label' => __( 'Notes/Policy', 'ss-core-licenses' ),
						'value' => get_post_meta( $product_id, '_ss_license_notes', true ),
						'desc_tip' => true,
						'description' => __( 'Additional notes or delivery policy for this product.', 'ss-core-licenses' ),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add product fields to General tab.
	 */
	public function add_product_fields() {
		global $post;

		woocommerce_wp_text_input(
			array(
				'id' => '_ss_product_cost',
				'label' => __( 'Product Cost', 'ss-core-licenses' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'value' => get_post_meta( $post->ID, '_ss_product_cost', true ),
				'type' => 'text',
				'data_type' => 'price',
				'desc_tip' => true,
				'description' => __( 'The cost price of this product (for profit calculation).', 'ss-core-licenses' ),
			)
		);
	}

	/**
	 * Save product fields.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_fields( $post_id ) {
		// Save delivery mode.
		if ( isset( $_POST['_ss_delivery_mode'] ) ) {
			update_post_meta( $post_id, '_ss_delivery_mode', sanitize_text_field( wp_unslash( $_POST['_ss_delivery_mode'] ) ) );
		}

		// Save license source.
		if ( isset( $_POST['_ss_license_source'] ) ) {
			update_post_meta( $post_id, '_ss_license_source', sanitize_text_field( wp_unslash( $_POST['_ss_license_source'] ) ) );
		}

		// Save license pool ID.
		if ( isset( $_POST['_ss_license_pool_id'] ) ) {
			$pool_id = intval( $_POST['_ss_license_pool_id'] );
			update_post_meta( $post_id, '_ss_license_pool_id', $pool_id );
		} else {
			// Auto-create pool if it doesn't exist.
			$pool_repo = new \SS_Core_Licenses\Pools\Repository();
			$pool_repo->create_or_update( $post_id );
		}

		// Save license notes.
		if ( isset( $_POST['_ss_license_notes'] ) ) {
			update_post_meta( $post_id, '_ss_license_notes', sanitize_textarea_field( wp_unslash( $_POST['_ss_license_notes'] ) ) );
		}

		// Save product cost.
		if ( isset( $_POST['_ss_product_cost'] ) ) {
			update_post_meta( $post_id, '_ss_product_cost', wc_format_decimal( wp_unslash( $_POST['_ss_product_cost'] ) ) );
		}

		// Update license status based on available count.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool = $pool_repo->get_by_product( $post_id );
		$available_count = $pool ? $pool['qty_cached'] : 0;

		$status = $available_count > 0 ? 'in_stock' : 'out_of_stock';
		update_post_meta( $post_id, '_ss_license_status', $status );
	}
}

