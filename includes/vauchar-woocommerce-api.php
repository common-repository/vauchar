<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class VaucharWoocommerceApi {

	protected function get_vauchar_api_credentials() {
		$merchant_id   = get_option( 'woocommerce-vauchar-settings-merchant-id' );
		$client_secret = get_option( 'woocommerce-vauchar-settings-api-key' );

		return array(
			'merchant_id'   => $merchant_id,
			'client_secret' => $client_secret,
		);
	}

	public function validate_vauchar_api_credentials( $merchant_id, $api_key ) {
		$response = $this->credentials_validate_api_request( $merchant_id, $api_key );

		return $response;
	}

	public function validate_coupon( $code, $user_id ) {
		if ( $code ) {
			$coupon = $this->coupon_validate_api_request( $code, $user_id );
			if ( isset( $coupon->validation_status->status ) ) {
				if ( $coupon->validation_status->status == 1000 ) {
					$data = $this->set_coupon_data( $coupon, $code );

					return $data;
				}
			}
		}

		if ( DOING_AJAX && isset( $_GET['wc-ajax'] ) && $_GET['wc-ajax'] == 'apply_coupon' ) {
			if ( isset( $coupon ) && isset( $coupon->validation_status->status ) && isset( $coupon->validation_status->message ) ) {
				$error_message = apply_filters( 'vauchar_woocommerce_coupon_error_message', $coupon->validation_status->message, $coupon->validation_status->status );
				wc_add_notice( $error_message, 'error' );
			} else {
				wc_add_notice( 'Coupon does not exists', 'error' );
			}

			wc_print_notices();
			die();
		}

		return FALSE;
	}


	public function redeem_coupon( $coupon_id, $type, $current_user, $order_id, $discount_amount = 0 ) {
		$redemption_data                   = array();
		$redemption_data['transaction_id'] = $order_id;
		if ( isset( $current_user->user_email ) ) {
			$redemption_data['user_email'] = $current_user->user_email;
		}
		if ( isset( $current_user->ID ) ) {
			$redemption_data['user_id'] = $current_user->ID;
		}
		if ( $discount_amount ) {
			$redemption_data['value_used'] = $discount_amount;
		}
		if ( $this->redeem_api_request( $coupon_id, $type, $redemption_data ) ) {
			return TRUE;
		}

		return FALSE;
	}


	public function set_coupon_data( $coupon, $code ) {
		$coupon_data   = (array) $coupon->data;
		$discount_type = 'fixed_cart';
		$discount      = array(
			$code => array(
				'id'   => $coupon_data['id'],
				'type' => $coupon_data['type'],
			)
		);
		WC()->session->set( 'vauchar_coupons', $discount );
		if ( $coupon_data['value_unit'] == 'percentage' ) {
			$discount_type = 'percent';
		}
		if ($coupon_data['type'] == 'gift-card') {
			$discount_value = $coupon_data['balance'];
		}
		else {
			$discount_value = $coupon_data['value'];
		}
		$defaults = array(
			'discount_type'              => $discount_type,
			'coupon_amount'              => $discount_value,
			'individual_use'             => 'yes',
			'product_ids'                => array(),
			'exclude_product_ids'        => array(),
			'usage_limit'                => '',
			'usage_limit_per_user'       => '',
			'limit_usage_to_x_items'     => '',
			'usage_count'                => '',
			'expiry_date'                => '',
			'free_shipping'              => 'no',
			'product_categories'         => array(),
			'exclude_product_categories' => array(),
			'exclude_sale_items'         => 'no',
			'minimum_amount'             => '',
			'maximum_amount'             => '',
			'customer_email'             => array()
		);

		return $defaults;
	}


	public function credentials_validate_api_request( $merchant_id, $api_key ) {
		$url = VAUCHAR_WOOCOMMERCE_API_ENDPOINT . 'verifycredentials';
		try {
			$curl = new VaucharWoocommerceCurlWrapper();
			$curl->setAuthType();
			$curl->setAuthCredentials( $merchant_id, $api_key );
			$curl->addHeader( 'Content-Type', 'application/json' );
			$response = $curl->get( $url );
			$httpCode = $curl->getTransferInfo( 'http_code' );
			if ( $httpCode == 200 ) {
				return TRUE;
			}
		}
		catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return FALSE;
	}

	public function coupon_validate_api_request( $code, $user_id ) {
		$creds   = $this->get_vauchar_api_credentials();
		$user_id = (int) $user_id;
		$url     = VAUCHAR_WOOCOMMERCE_API_ENDPOINT . "validate?code=$code&user_id=$user_id";
		try {
			$curl = new VaucharWoocommerceCurlWrapper();
			$curl->setAuthType();
			$curl->setAuthCredentials( $creds['merchant_id'], $creds['client_secret'] );
			$curl->addHeader( 'Content-Type', 'application/json' );
			$response = $curl->get( $url );
			$response = json_decode( $response );

			return $response;
		}
		catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return FALSE;
	}

	public function redeem_api_request( $id, $type, $data ) {
		$post_data = json_encode( $data );
		$creds     = $this->get_vauchar_api_credentials();
		$url       = VAUCHAR_WOOCOMMERCE_API_ENDPOINT;
		if ( $type == 'voucher' ) {
			$url .= "/vouchers/$id/redemptions";
		} elseif ( $type == 'gift-card' ) {
			$url .= "/gift-cards/$id/redemptions";
		} else {
			$url .= "/coupons/$id/redemptions";
		}
		try {
			$curl = new VaucharWoocommerceCurlWrapper();
			$curl->setAuthType();
			$curl->setAuthCredentials( $creds['merchant_id'], $creds['client_secret'] );
			$curl->addHeader( 'Content-Type', 'application/json' );
			$curl->rawPost( $url, $post_data );
			$httpCode = $curl->getTransferInfo( 'http_code' );
			if ( $httpCode == 201 ) {
				return TRUE;
			}
		}
		catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return FALSE;
	}

}