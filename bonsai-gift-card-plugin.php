<?php
/**
 * Plugin Name: Bonsai Gift Card Plugin
 * Plugin URI:  https://bonsaidigitalcollective.co.uk
 * Description: Simple WooCommerce gift card system — sell, email, redeem and check balance on gift cards. Built for WooCommerce Blocks Cart/Checkout with a client-configurable gift card image.
 * Version:     1.0.0
 * Author:      The Bonsai Digital Collective
 * Author URI:  https://bonsaidigitalcollective.co.uk
 * Text Domain: bgcp
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BGCP_VERSION', '1.0.0' );
define( 'BGCP_PLUGIN_FILE', __FILE__ );
define( 'BGCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BGCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function bgcp_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Bonsai Gift Card Plugin requires WooCommerce to be installed and active.', 'bgcp' ) .
		'</p></div>';
}

add_action( 'plugins_loaded', 'bgcp_init' );

function bgcp_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bgcp_missing_woocommerce_notice' );
		return;
	}

	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-db.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-settings.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-product.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-order.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-cart.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-shortcode.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-admin.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-rest.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-stripe.php';
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-report.php';

	BGCP_Settings::init();
	BGCP_Product::init();
	BGCP_Order::init();
	BGCP_Cart::instance();
	BGCP_Shortcode::init();
	BGCP_Admin::instance();
	BGCP_REST::init();
	BGCP_Stripe::init();
	BGCP_Report::init();

	// Self-heals the cron schedule if the stored event was ever cleared
	// (site migration, cron table wiped) without needing a settings re-save.
	add_action( 'admin_init', array( 'BGCP_Report', 'reschedule' ) );

	add_filter( 'woocommerce_email_classes', 'bgcp_register_email_class' );
}

function bgcp_register_email_class( $email_classes ) {
	// WC_Email is only guaranteed to exist once WC_Emails::init() has
	// started running (it includes its own base class right before firing
	// this filter), so this file is required here rather than eagerly in
	// bgcp_init() — WooCommerce's email system can load long after
	// 'plugins_loaded', e.g. on an admin page's asset check.
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-email.php';

	if ( class_exists( 'BGCP_Email_Gift_Card' ) ) {
		$email_classes['BGCP_Email_Gift_Card'] = new BGCP_Email_Gift_Card();
	}

	return $email_classes;
}

register_activation_hook( __FILE__, 'bgcp_activate' );

function bgcp_activate() {
	require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-db.php';
	BGCP_DB::create_table();
}

register_deactivation_hook( __FILE__, 'bgcp_deactivate' );

function bgcp_deactivate() {
	wp_clear_scheduled_hook( 'bgcp_send_sales_report_weekly' );
	wp_clear_scheduled_hook( 'bgcp_send_sales_report_monthly' );
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * The "Have a gift card?" field in the Blocks Cart/Checkout sidebar
 * requires registering against WooCommerce Blocks' own integration
 * registry — it's a separate hook from the general plugin bootstrap
 * above because Blocks may load after 'plugins_loaded'.
 */
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) || ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}

		require_once BGCP_PLUGIN_DIR . 'includes/class-bgcp-blocks-integration.php';

		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new BGCP_Blocks_Integration() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new BGCP_Blocks_Integration() );
			}
		);
	}
);
