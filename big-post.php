<?php
/* @wordpress-plugin
 * Plugin Name:       Big Post Shipping Plugin for WooCommerce
 * Plugin URI:        https://nicheextensions.com/extensions/woocommerce-bigpost
 * Description:       Big Post shipping calculator & lookup for WooCommerce
 * Version:           0.0.1
 * WC requires at least: 2.6
 * WC tested up to: 3.4
 * Author:            PB Web Development
 * Author URI:        https://nicheextensions.com
 * Text Domain:       big-post
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/pbwebdev/woocommerce-bigpost
 */

define('BIG_POST_URL', plugin_dir_url(__FILE__));

/**
 *
 * @since 0.0.1
 *
 */
class PBWEB_BIG_POST_Lite{

	public function __construct(){
		//info: if the Pro version active, deactivate it first.
		if ($this->is_plugin_active('woocommerce-bigpost-pro/class-bigpost.php')) {
				add_action('admin_init', array($this, 'deactivate_pro_version'));
		}
		//info: Only add the shipping method actions only if WooCommerce is activated.
		if($this->is_plugin_active('woocommerce/woocommerce.php')){
			add_filter('woocommerce_shipping_methods', array($this, 'add_big_post_method'));
			add_action('woocommerce_shipping_init', array($this, 'init_big_post'));
		}

		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links') );

	}

	// deactivate the pro version
	public function deactivate_pro_version() {
	  deactivate_plugins( 'woocommerce-bigpost-pro/class-big-post.php' );
	}

	public function add_big_post_method( $methods ){
		$methods['big_post'] = 'WC_Big_Post_Shipping_Method';
		return $methods;
	}

	public function init_big_post(){
		require 'class-big-post.php';
	}
	private function is_plugin_active($slug){
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		return in_array( $slug, $active_plugins ) || array_key_exists( $slug, $active_plugins );
	}

	public function plugin_action_links( $links ) {
	   $links[] = '<a href="https://nicheextensions.com/extensions/woocommerce-bigpost/" target="_blank">Get Paid Support</a>';
	   $links[] = '<a href="https://nicheextensions.com/extensions/woocommerce-bigpost/" target="_blank">Support</a>';
	   return $links;
	}

}
new PBWEB_BIG_POST_Lite();
