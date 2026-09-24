<?php
/**
 * Plugin Name:       Yadak Core — هسته فروشگاه قطعات خودرو
 * Description:       سازگاری قطعه با خودرو، شماره فنی/OEM، جستجوی فارسی، گاراژ مشتری، قیمت همکار، خرید اعتباری و تاریخچه قیمت برای ووکامرس.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Yadak
 * Text Domain:       yadak-core
 * License:           GPL-2.0-or-later
 *
 * WC requires at least: 8.5
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YADAK_CORE_VERSION', '0.1.0' );
define( 'YADAK_CORE_FILE', __FILE__ );
define( 'YADAK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'YADAK_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once YADAK_CORE_DIR . 'includes/helpers.php';
require_once YADAK_CORE_DIR . 'includes/class-fitment.php';
require_once YADAK_CORE_DIR . 'includes/class-part-data.php';
require_once YADAK_CORE_DIR . 'includes/class-search.php';
require_once YADAK_CORE_DIR . 'includes/class-customers.php';
require_once YADAK_CORE_DIR . 'includes/class-pricing.php';
require_once YADAK_CORE_DIR . 'includes/class-price-log.php';
require_once YADAK_CORE_DIR . 'includes/class-garage.php';
require_once YADAK_CORE_DIR . 'includes/class-credit.php';
require_once YADAK_CORE_DIR . 'includes/class-checkout.php';
require_once YADAK_CORE_DIR . 'includes/class-csv.php';

// Declare compatibility with WooCommerce custom order tables (HPOS).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه Yadak Core برای کار به ووکامرس نیاز دارد.', 'yadak-core' ) . '</p></div>';
				}
			);
			return;
		}

		Yadak_Fitment::init();
		Yadak_Part_Data::init();
		Yadak_Search::init();
		Yadak_Customers::init();
		Yadak_Pricing::init();
		Yadak_Price_Log::init();
		Yadak_Garage::init();
		Yadak_Credit::init();
		Yadak_Checkout::init();
		Yadak_CSV::init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		Yadak_Price_Log::install();
		Yadak_Credit::install();
		if ( function_exists( 'wc_get_page_id' ) ) {
			Yadak_Checkout::use_classic_pages();
		}
		Yadak_Fitment::register_taxonomy();
		Yadak_Garage::add_endpoints();
		Yadak_Credit::add_endpoints();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
