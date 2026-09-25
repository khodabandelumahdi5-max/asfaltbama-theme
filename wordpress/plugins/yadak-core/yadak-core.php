<?php
/**
 * Plugin Name:       Yadak Core — هسته فروشگاه قطعات خودرو
 * Description:       فروشگاه قطعات خودرو روی ووکامرس: سازگاری با خودرو، جستجوی فارسی، قیمت همکار، اعتبار و حساب مشتری، پیش‌فاکتور، CRM، چند انبار، پیامک، زرین‌پال و کارت‌به‌کارت، فاکتور و خروجی مودیان، تاریخ شمسی، سئو و داشبورد فروش.
 * Version:           0.3.0
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

define( 'YADAK_CORE_VERSION', '0.3.0' );
define( 'YADAK_CORE_FILE', __FILE__ );
define( 'YADAK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'YADAK_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once YADAK_CORE_DIR . 'includes/helpers.php';
require_once YADAK_CORE_DIR . 'includes/class-install.php';
require_once YADAK_CORE_DIR . 'includes/class-settings.php';
require_once YADAK_CORE_DIR . 'includes/class-jalali.php';
require_once YADAK_CORE_DIR . 'includes/class-sms.php';
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
require_once YADAK_CORE_DIR . 'includes/class-print.php';
require_once YADAK_CORE_DIR . 'includes/class-orders.php';
require_once YADAK_CORE_DIR . 'includes/class-quotes.php';
require_once YADAK_CORE_DIR . 'includes/class-crm.php';
require_once YADAK_CORE_DIR . 'includes/class-warehouses.php';
require_once YADAK_CORE_DIR . 'includes/class-seo.php';
require_once YADAK_CORE_DIR . 'includes/class-dashboard.php';
require_once YADAK_CORE_DIR . 'includes/class-model3d.php';
require_once YADAK_CORE_DIR . 'includes/class-filter.php';
require_once YADAK_CORE_DIR . 'includes/class-bulk.php';
require_once YADAK_CORE_DIR . 'includes/class-schema.php';

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

		Yadak_Install::init();
		Yadak_Settings::init();
		Yadak_Jalali::init();
		Yadak_SMS::init();
		Yadak_Fitment::init();
		Yadak_Part_Data::init();
		Yadak_Search::init();
		Yadak_Customers::init();
		Yadak_Pricing::init();
		Yadak_Price_Log::init();
		Yadak_Garage::init();
		Yadak_Credit::init();
		Yadak_Checkout::init();
		add_action( 'init', array( 'Yadak_CSV', 'init' ) ); // Builds translated column labels.
		Yadak_Print::init();
		Yadak_Orders::init();
		Yadak_Quotes::init();
		Yadak_CRM::init();
		Yadak_Warehouses::init();
		Yadak_SEO::init();
		Yadak_Model3D::init();
		Yadak_Filter::init();
		Yadak_Bulk::init();
		Yadak_Schema::init();

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				require_once YADAK_CORE_DIR . 'includes/class-gateway-zarinpal.php';
				require_once YADAK_CORE_DIR . 'includes/class-gateway-card.php';
				$gateways[] = 'Yadak_Gateway_Zarinpal';
				$gateways[] = 'Yadak_Gateway_Card';
				return $gateways;
			}
		);
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		Yadak_Price_Log::install();
		Yadak_Credit::install();
		Yadak_Install::install();
		if ( function_exists( 'wc_get_page_id' ) ) {
			Yadak_Checkout::use_classic_pages();
		}
		Yadak_Fitment::register_taxonomy();
		Yadak_Garage::add_endpoints();
		Yadak_Credit::add_endpoints();
		Yadak_Quotes::add_endpoints();
		Yadak_SEO::rewrites();
		if ( function_exists( 'wc_get_orders' ) ) {
			Yadak_CRM::backfill_last_orders();
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		Yadak_Install::deactivate();
		flush_rewrite_rules();
	}
);
