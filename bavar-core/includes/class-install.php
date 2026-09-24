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

	const DB_VERSION = '2';

	/**
	 * Run on activation.
	 */
	public static function activate() {
		self::upgrade();
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
	 * Keep the tables current after plugin updates.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bavar_db_version' ) !== self::DB_VERSION ) {
			self::upgrade();
		}
	}

	/**
	 * Create tables and move leads from the 1.0 table into the CRM.
	 */
	private static function upgrade() {
		global $wpdb;
		Bavar_CRM::create_tables();

		$old = $wpdb->prefix . 'bavar_leads';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) === $old ) {
			foreach ( $wpdb->get_results( "SELECT * FROM {$old} ORDER BY id ASC" ) as $r ) {
				$name = preg_split( '/\s+/u', trim( $r->full_name ), 2 );
				$id   = Bavar_CRM::upsert(
					[
						'phone'      => $r->phone,
						'first_name' => $name[0] ?? '',
						'last_name'  => $name[1] ?? '',
						'job'        => $r->job,
						'source'     => 'gate' === $r->source ? 'gate' : ( $r->path ? $r->path : 'order' ),
					]
				);
				if ( ! $id ) {
					continue;
				}
				$answers = json_decode( (string) $r->answers, true );
				$wpdb->insert(
					Bavar_CRM::events_table(),
					[
						'contact_id' => $id,
						'type'       => 'order' === $r->source ? ( 'paid' === $r->purchase_status ? 'purchase_completed' : 'purchase_pending' ) : ( $answers ? 'form_submitted' : 'phone_submitted' ),
						'path'       => $r->path ? $r->path : ( 'gate' === $r->source ? 'gate' : '' ),
						'product_id' => (int) $r->product_id,
						'order_id'   => (int) $r->order_id,
						'data'       => $answers ? wp_json_encode( [ 'answers' => $answers ], JSON_UNESCAPED_UNICODE ) : null,
						'created_at' => $r->created_at,
					]
				);
				if ( 'paid' === $r->purchase_status ) {
					Bavar_CRM::update( $id, [ 'status' => 'customer' ] );
				}
			}
			// Keep the original dates of migrated contacts.
			$contacts = Bavar_CRM::contacts_table();
			$events   = Bavar_CRM::events_table();
			$wpdb->query( "UPDATE {$contacts} SET created_at = (SELECT MIN(e.created_at) FROM {$events} e WHERE e.contact_id = {$contacts}.id), last_activity_at = (SELECT MAX(e.created_at) FROM {$events} e WHERE e.contact_id = {$contacts}.id), last_activity = (SELECT e.type FROM {$events} e WHERE e.contact_id = {$contacts}.id ORDER BY e.created_at DESC, e.id DESC LIMIT 1) WHERE EXISTS (SELECT 1 FROM {$events} e WHERE e.contact_id = {$contacts}.id)" );
			$wpdb->query( "DROP TABLE IF EXISTS {$old}" );
		}
		// phpcs:enable
		update_option( 'bavar_db_version', self::DB_VERSION );
	}

	/**
	 * Create the BAVAR pages once.
	 */
	private static function pages() {
		$settings = get_option( Bavar_Settings::OPTION, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$pages = [
			'page_library' => [
				'title'   => 'پک‌های کتاب گروه باور',
				'slug'    => 'library',
				'content' => "<!-- wp:heading -->\n<h2>خرید پک‌های برتر کتاب و آموزش گروه باور</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>خرید پک‌های کتاب به صورت دسته‌ای بر اساس نیاز شما. هر پک مجموعه‌ای از کتاب‌های منتخب است که همراه با معرفی، تصویر و اطلاعات هر کتاب و محتوای آموزشی تکمیلی یکجا خریداری می‌شود.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_library]\n<!-- /wp:shortcode -->",
			],
			'page_simorgh' => [
				'title'   => 'آشیانه سیمرغ',
				'slug'    => 'ashiane-simorgh',
				'content' => "<!-- wp:paragraph -->\n<p>دوره‌ی حضوری گروه باور. اطلاعات کامل دوره، سرفصل‌ها، زمان و مکان برگزاری را در این بخش وارد کنید.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_request path=\"simorgh\" fields=\"phone\" title=\"ثبت‌نام در آشیانه سیمرغ\" button=\"ثبت‌نام\"]\n<!-- /wp:shortcode -->",
			],
			'page_consult' => [
				'title'   => 'مشاوره',
				'slug'    => 'consulting',
				'content' => "<!-- wp:paragraph -->\n<p>یک ساعت مشاوره‌ی حضوری با مدیریت مجموعه.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>در این جلسه وضعیت کسب‌وکار شما بررسی می‌شود، مشکلات و فرصت‌های رشد شناسایی می‌شوند و مسیرهای عملی برای افزایش فروش و سود در اختیار شما قرار می‌گیرد.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>هدف ما این است که اجرای پیشنهادهای همین یک جلسه، در کسب‌وکار شما رشدی در حدود ۲۰ تا ۳۰ درصد در فروش و سود ایجاد کند؛ نتیجه‌ای که در بسیاری از کسب‌وکارها به دست آمده است، هرچند به شرایط هر کسب‌وکار و میزان اجرای پیشنهادها بستگی دارد. همین یک ساعت می‌تواند مسیر کسب‌وکار و حتی زندگی شما را تغییر دهد.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bavar_request path=\"consult\" title=\"درخواست مشاوره\" button=\"ثبت درخواست مشاوره\"]\n<!-- /wp:shortcode -->",
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
					'post_title' => 'حساب کاربری من',
				]
			);
		}
	}
}
