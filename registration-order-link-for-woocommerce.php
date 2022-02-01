<?php
/**
 * Plugin Name: FreshySites - Link Previous Orders at Registration
 * Plugin URI: https://freshysites.com
 * Description: Automatically links previous WooCommerce orders to new customer accounts upon WooCommerce registration.
 * Author: FreshySites
 * Author URI: https://freshysits.com
 * Version: 1.0.0
 * Text Domain: link-wc-orders
 *
 *
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Registration-Order-Link
 * @author    FreshySites
 * @category  Admin
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

defined( 'ABSPATH' ) or exit;

// Check if WooCommerce is active
if ( ! WC_Registration_Order_Link::is_woocommerce_active() ) {
	return;
}


/**
 * When a customer registers at checkout or via the account, any past orders associated with the
 *  new customer's email are tied to this account automatically.
 *
 * New customers are also shown a message the first time they visit the account to inform them
 *  that old orders have been linked.
 */


// fire it up!
add_action( 'plugins_loaded', 'wc_registration_order_link', 11 );


/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class WC_Registration_Order_Link {

	const VERSION = '1.0.0';

	/** @var WC_Registration_Order_Link single instance of this plugin */
	protected static $instance;

	public function __construct() {

		// load translations
		add_action( 'init', array( $this, 'load_translation' ) );

		// link previous orders when registering
		add_action( 'woocommerce_created_customer', array( $this, 'link_orders_at_registration' ) );

		// woocommerce_account_dashboard action was added in v2.6
		if ( version_compare( get_option( 'woocommerce_db_version' ), '2.6.0', '<' ) ) {
			add_action( 'woocommerce_before_my_account', array( $this, 'maybe_show_linked_order_count' ), 1 );
		} else {
			add_action( 'woocommerce_account_dashboard', array( $this, 'maybe_show_linked_order_count' ), 1 );
		}

		if ( is_admin() && ! is_ajax() ) {

			// run every time
			$this->install();
		}

	}
	/** Helper methods ***************************************/
	/**
	 * Main WC_Registration_Order_Link Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wc_registration_order_link()
	 * @return WC_Registration_Order_Link
 	*/
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Load Translations
	 * TODO: needs a .pot file / folder structure if you want to translate it
	 *
	 * @since 1.0.0
	 */
	public function load_translation() {
		// localization
		load_plugin_textdomain( 'link-wc-orders', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}


	/**
	 * Checks if WooCommerce is active
	 *
	 * @since 1.0.0
	 * @return bool true if WooCommerce is active, false otherwise
	 */
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}


	/** Plugin methods ***************************************/


	/**
	 * Links previous orders to a new customer upon registration.
	 *
	 * @since 1.0.0
	 * @param int $user_id the ID for the new user
	 */
	public function link_orders_at_registration( $user_id ) {

		$count = wc_update_new_customer_past_orders( $user_id );
		update_user_meta( $user_id, '_wc_linked_order_count', $count );
	}

	/**
	 * Shows the "orders linked" notice upon first account visit if any were linked at registration.
	 *
	 * @since 1.0.0
	 */
	public function maybe_show_linked_order_count() {

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$count = get_user_meta( $user_id, '_wc_linked_order_count', true );

		if ( $count && $count > 0 ) {

			$fname = get_user_by( 'id', $user_id )->first_name;

			$message  = $fname ? sprintf( esc_html__( 'Welcome, %s!', 'link-wc-orders' ), $fname ) : esc_html__( 'Welcome!', 'link-wc-orders' );
			$message .= ' ' . esc_html__( sprintf( _n( 'Your previous order has been linked to this account.', 'Your previous %s orders have been linked to this account.', $count, 'link-wc-orders' ), $count ) );
			$message .= ' <a class="button" href="' . esc_url( wc_get_endpoint_url( 'orders' ) ) . '">' . esc_html__( 'View Orders', 'link-wc-orders' ) . '</a>';

			wc_print_notice( $message, 'notice' );
			delete_user_meta( $user_id, '_wc_linked_order_count' );
		}
	}
	/** Lifecycle methods ***************************************/
	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.0.0
	 */
	private function install() {

		// get current version to check for upgrade
		$installed_version = get_option( 'wc_registration_order_link_version' );

		// force upgrade to 1.0.0
		if ( ! $installed_version ) {
			update_option( 'wc_registration_order_link_version', '1.0.0' );
		}
	}


}


/**
 * Returns the One True Instance of WC_Registration_Order_Link
 *
 * @since 1.0.0
 * @return WC_Registration_Order_Link
 */
function wc_registration_order_link() {
    return WC_Registration_Order_Link::instance();
}
