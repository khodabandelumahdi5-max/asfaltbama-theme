<?php
/**
 * Plugin Name: BAVAR Core
 * Description: هسته‌ی سایت گروه باور — دریافت لید، فرم سه‌سؤالی، کتابخانه‌ی پک‌ها و کتاب‌ها، پرداخت کارت‌به‌کارت با ارسال فیش، لایسنس اسپات‌پلیر و «کتابخانه من».
 * Version: 1.0.0
 * Author: BAVAR GROUP
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: bavar-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BAVAR_CORE_VERSION', '1.0.0' );
define( 'BAVAR_CORE_FILE', __FILE__ );
define( 'BAVAR_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BAVAR_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BAVAR_CORE_DIR . 'includes/helpers.php';
require_once BAVAR_CORE_DIR . 'includes/class-settings.php';
require_once BAVAR_CORE_DIR . 'includes/class-leads.php';
require_once BAVAR_CORE_DIR . 'includes/class-frontend.php';
require_once BAVAR_CORE_DIR . 'includes/class-books.php';
require_once BAVAR_CORE_DIR . 'includes/class-products.php';
require_once BAVAR_CORE_DIR . 'includes/class-library.php';
require_once BAVAR_CORE_DIR . 'includes/class-checkout.php';
require_once BAVAR_CORE_DIR . 'includes/class-account.php';
require_once BAVAR_CORE_DIR . 'includes/class-receipts.php';
require_once BAVAR_CORE_DIR . 'includes/class-spotplayer.php';
require_once BAVAR_CORE_DIR . 'includes/class-admin.php';
require_once BAVAR_CORE_DIR . 'includes/class-install.php';

register_activation_hook( __FILE__, [ 'Bavar_Install', 'activate' ] );

add_action(
	'plugins_loaded',
	function () {
		Bavar_Leads::init();
		Bavar_Frontend::init();
		Bavar_Books::init();
		Bavar_Admin::init();
		Bavar_Install::maybe_upgrade();

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>افزونه‌ی BAVAR Core برای فروش دوره‌ها و پک‌ها به <strong>ووکامرس</strong> نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
				}
			);
			return;
		}

		Bavar_Products::init();
		Bavar_Library::init();
		Bavar_Checkout::init();
		Bavar_Account::init();
		Bavar_Receipts::init();
		Bavar_SpotPlayer::init();
	}
);
