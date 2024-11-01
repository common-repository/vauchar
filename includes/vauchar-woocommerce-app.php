<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class VaucharWoocommerce {

	public function __construct() {
		add_action( 'admin_init', array(
			$this,
			'woocommerce_vauchar_settings_info',
		) );
		add_action( 'admin_notices', array(
			$this,
			'woocommerce_vauchar_admin_notices',
		) );
		add_filter( "plugin_action_links_vauchar/vauchar_woocommerce.php", array(
			$this,
			'woocommerce_vauchar_settings_link',
		) );
		add_filter( "plugin_action_links_vauchar_woocommerce", array(
			$this,
			'woocommerce_vauchar_settings_link',
		) );
		add_filter( "woocommerce_get_shop_coupon_data", array(
			$this,
			'woocommerce_vauchar_shop_coupon_data',
		), 10, 2 );
		add_filter( "admin_menu", array(
			$this,
			'woocommerce_vauchar_setup_menu',
		) );
		add_filter( "woocommerce_coupon_code", array(
			$this,
			'woocommerce_vauchar_sanitize_coupon_code'
		), 9 );
		add_action( 'woocommerce_order_status_changed', array(
			$this,
			'woocommerce_vauchar_order_status_changed'
		), 10, 3 );
	}


	function woocommerce_vauchar_validate_api_key( $input ) {
		if ( $input ) {
			$merchant_id = get_option( 'woocommerce-vauchar-settings-merchant-id' );
			$vauchar_api = new VaucharWoocommerceApi();
			$valid       = $vauchar_api->validate_vauchar_api_credentials( $merchant_id, $input );
			if ( $valid ) {
				add_settings_error( 'woocommerce-vauchar-settings', 'woocommerce-vauchar-settings-api-key', 'Settings are valid and updated.', 'updated' );

				return $input;
			}
		}
		add_settings_error( 'woocommerce-vauchar-settings', 'woocommerce-vauchar-settings-api-key', 'The settings are not valid. Please check the entered details and try again.', 'error' );

		return FALSE;
	}


	function woocommerce_vauchar_admin_notices() {
		settings_errors();
	}


	public function woocommerce_vauchar_sanitize_coupon_code( $coupon_code ) {
		remove_filter( 'woocommerce_coupon_code', 'strtolower' );

		return $coupon_code;
	}

	// Add settings link on plugin page
	public function woocommerce_vauchar_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=vauchar-woocommerce">Settings</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}


	public function woocommerce_vauchar_order_status_changed( $order_id, $old_status, $new_status ) {
		if ( $old_status == 'pending' ) {
			$cart      = WC()->cart;
			$coupons   = $cart->get_applied_coupons();
			$couponids = WC()->session->get( 'vauchar_coupons' );
			if ( ! empty( $coupons ) && is_array( $coupons ) ) {
				foreach ( $coupons as $coupon ) {
					if ( isset( $couponids[ $coupon ] ) && $data = $couponids[ $coupon ] ) {
						$vauchar_api  = new VaucharWoocommerceApi();
						$current_user = wp_get_current_user();
						if ( $vauchar_api->redeem_coupon( $data['id'], $data['type'], $current_user, $order_id, $cart->discount_cart ) ) {
							unset( $couponids[ $coupon ] );
						}
					}
				}
				WC()->session->set( 'vauchar_coupons', $couponids );
			}
		}
	}


	public function woocommerce_vauchar_shop_coupon_data( $exists, $code ) {
		$vauchar_api = new VaucharWoocommerceApi();
		$user_id     = get_current_user_id();
		$data        = $vauchar_api->validate_coupon( $code, $user_id );

		return $data;
	}


	public function woocommerce_vauchar_settings_info() {
		register_setting( 'woocommerce-vauchar-settings', 'woocommerce-vauchar-settings-merchant-id' );
		register_setting( 'woocommerce-vauchar-settings', 'woocommerce-vauchar-settings-api-key', array(
			$this,
			'woocommerce_vauchar_validate_api_key',
		) );
	}

	public function woocommerce_vauchar_setup_menu() {
		add_menu_page( 'Vauchar API', 'Vauchar', 'manage_options', 'vauchar-woocommerce', array(
			$this,
			'woocommerce_vauchar_init'
		) );
	}


	public function woocommerce_vauchar_init() {
		?>
		<h1>Vauchar API Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'woocommerce-vauchar-settings' ); ?>
			<?php do_settings_sections( 'woocommerce-vauchar-settings' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Merchant ID:</th>
					<td><input size="50" type="text"
					           name="woocommerce-vauchar-settings-merchant-id"
					           value="<?php echo get_option( 'woocommerce-vauchar-settings-merchant-id' ); ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">API Key:</th>
					<td><input size="50" type="text"
					           name="woocommerce-vauchar-settings-api-key"
					           id="woocommerce-vauchar-settings-api-key"
					           value="<?php $client_secret = get_option( 'woocommerce-vauchar-settings-api-key' );
					           $masked_client_secret       = '';
					           if ( $client_secret ) {
						           $masked_client_secret = 'XXXXXXXXXXXXX' . substr( $client_secret, - 7 );
					           }
					           echo $masked_client_secret; ?>"/>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}
}

?>



















