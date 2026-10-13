<?php
/* @wordpress-plugin
 * Plugin Name:          Big Post Shipping Plugin for WooCommerce
 * Plugin URI:           https://github.com/pbwebdev/woocommerce-big-post
 * Description:          Live Big Post freight quotes at the WooCommerce cart and checkout.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 * Author:               Peter Bui
 * Author URI:           https://github.com/pbwebdev
 * Text Domain:          big-post
 * License:              GPL-2.0+
 * License URI:          http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:          /languages
 * GitHub Plugin URI:    https://github.com/pbwebdev/woocommerce-big-post
 */

defined( 'ABSPATH' ) || exit;

define( 'BIG_POST_VERSION', '1.0.0' );
define( 'BIG_POST_PATH', plugin_dir_path( __FILE__ ) );
define( 'BIG_POST_URL', plugin_dir_url( __FILE__ ) );

/**
 *
 * @since 0.0.1
 *
 */
class PBWEB_BIG_POST {

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		//info: these only run when WooCommerce is active.
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_big_post_method' ) );
		add_action( 'woocommerce_shipping_init', array( $this, 'init_big_post' ) );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_company_to_packages' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'big-post', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	// the plugin stores nothing on orders, and its rates work in the cart and checkout blocks.
	public function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	public function add_big_post_method( $methods ) {
		$methods['big_post'] = 'WC_Big_Post_Shipping_Method';
		return $methods;
	}

	public function init_big_post() {
		require_once BIG_POST_PATH . 'includes/class-big-post-api.php';
		require_once BIG_POST_PATH . 'includes/class-wc-big-post-shipping-method.php';
	}

	// the company name decides business delivery, so rates must be recalculated when it changes.
	public function add_company_to_packages( $packages ) {
		$company = $this->get_checkout_company();

		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['destination']['company'] = $company;
		}

		return $packages;
	}

	private function get_checkout_company() {
		//info: the classic checkout posts its form on every update, before the customer is saved.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before it calculates shipping.
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
			parse_str( wp_unslash( $_POST['post_data'] ), $form );

			$field = empty( $form['ship_to_different_address'] ) ? 'billing_company' : 'shipping_company';
			if ( isset( $form[ $field ] ) && is_string( $form[ $field ] ) ) {
				return sanitize_text_field( $form[ $field ] );
			}
		}

		return WC()->customer ? (string) WC()->customer->get_shipping_company() : '';
	}

	public function plugin_action_links( $links ) {
		if ( class_exists( 'WooCommerce' ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=big_post' ) ) . '">' . esc_html__( 'Settings', 'big-post' ) . '</a>' );
		}
		return $links;
	}

}
new PBWEB_BIG_POST();
