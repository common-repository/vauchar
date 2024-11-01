<?php
/*
Plugin Name: Vauchar
Plugin URI: http://vauchar.com
Description: This is a Vauchar API Plugin for Woocommerce
Version: 1.0
Author: Vauchar Team
Author URI: http://vauchar.com
License: GPL2
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'VAUCHAR_WOOCOMMERCE_FILE', __FILE__ );
define( 'VAUCHAR_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'VAUCHAR_WOOCOMMERCE_API_ENDPOINT', 'https://api.vauchar.com/' );

$plugin = plugin_basename( __FILE__ );
require VAUCHAR_WOOCOMMERCE_PATH . 'includes/vauchar-woocommerce-app.php';
require VAUCHAR_WOOCOMMERCE_PATH . 'includes/vauchar-woocommerce-api.php';
require VAUCHAR_WOOCOMMERCE_PATH . 'includes/vauchar-woocommerce-curlwrapper.php';
$run = new VaucharWoocommerce();


?>
