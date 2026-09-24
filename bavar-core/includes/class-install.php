<?php
/**
 * Activation: table, pages and WooCommerce options for digital sales.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Install {

	const DB_VERSION = '1';

	/**
	 * Run on activation.
	 */
	public static function activate() {
		Bavar_Leads::create_table();
		update_option( 'bavar_db_version', self::DB_VERSION );
		self::pages();
		self::woocommerce_options();
		Bavar_Books::register();
		if ( class_exists( 'Bavar_Account' ) ) {
			Bavar_Account::endpoint();
		}
		add_rewrite_endpoint( 'bavar-library', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Keep the table current after plugin updates.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bavar_db_version' ) !== self::DB_VERSION ) {
			Bavar_Leads::create_table();
			update_option( 'bavar_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create the BAVAR pages once.
	 */
	private static function pages() {
		$settings = get_option( Bavar_Settings::OPTION, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$pages = [
			'page_library' => [
				'title'   => 'BAVAR LIBRARY',
				'slug'    => 'library',
				'content' => "<!-- wp:paragraph -->\n<p>کتابخانه‌ی گروه باور؛ پک‌هایی از کتاب‌های منتخب جهان، همراه با خلاصه و توسعه‌ی محتوایی، چک‌لیست و تمرین‌های کاربردی.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_library]\n<!-- /wp:shortcode -->",
			],
			'page_simorgh' => [
				'title'   => 'آشیانه سیمرغ‌ها',
				'slug'    => 'ashiane-simorgh',
				'content' => "<!-- wp:paragraph -->\n<p>دوره‌ی حضوری گروه باور. توضیحات کامل دوره، زمان و مکان برگزاری را در این بخش بنویسید.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_request path=\"simorgh\"]\n<!-- /wp:shortcode -->",
			],
			'page_consult' => [
				'title'   => 'مشاوره',
				'slug'    => 'consulting',
				'content' => "<!-- wp:paragraph -->\n<p>مشاوره‌ی غیرحضوری با مدیریت مجموعه. توضیحات، مدت جلسه و نحوه‌ی برگزاری را در این بخش بنویسید.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_request path=\"consult\"]\n<!-- /wp:shortcode -->",
			],
		];

		foreach ( $pages as $key => $page ) {
			if ( ! empty( $settings[ $key ] ) && get_post( $settings[ $key ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $page['slug'] );
			$settings[ $key ] = $existing ? $existing->ID : wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => $page['content'],
				]
			);
		}
		update_option( Bavar_Settings::OPTION, $settings );
	}

	/**
	 * WooCommerce settings suited to selling digital products.
	 */
	private static function woocommerce_options() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		$options = [
			'woocommerce_enable_guest_checkout'              => 'no',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_myaccount_registration'      => 'yes',
			'woocommerce_registration_generate_username'     => 'yes',
			'woocommerce_registration_generate_password'     => 'no',
			'woocommerce_downloads_require_login'            => 'yes',
			'woocommerce_downloads_grant_access_after_payment' => 'yes',
			'woocommerce_file_download_method'               => 'force',
			'woocommerce_downloads_add_hash_to_filename'     => 'yes',
		];
		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
		// Switch a fresh store from the USD default to Toman; never override a chosen currency.
		if ( 'USD' === get_option( 'woocommerce_currency', 'USD' ) ) {
			update_option( 'woocommerce_currency', 'IRT' );
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'woocommerce_currency_pos', 'right_space' );
		}

		// The BAVAR checkout customisations use the classic checkout.
		foreach ( [ 'checkout' => '[woocommerce_checkout]', 'cart' => '[woocommerce_cart]' ] as $page => $shortcode ) {
			$id   = wc_get_page_id( $page );
			$post = $id > 0 ? get_post( $id ) : null;
			if ( $post && false !== strpos( $post->post_content, 'wp:woocommerce/' ) ) {
				wp_update_post(
					[
						'ID'           => $id,
						'post_content' => "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->",
					]
				);
			}
		}

		$account = wc_get_page_id( 'myaccount' );
		if ( $account > 0 ) {
			wp_update_post(
				[
					'ID'         => $account,
					'post_title' => 'MY BAVAR',
				]
			);
		}
	}
}
