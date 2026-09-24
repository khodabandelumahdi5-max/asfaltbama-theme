<?php
/**
 * Lead storage (entry gate + three-question forms) and purchase status sync.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Leads {

	const COOKIE      = 'bavar_lead';
	const FLAG_COOKIE = 'bavar_ok';

	/**
	 * Hooks.
	 */
	public static function init() {
		foreach ( [ 'bavar_lead', 'bavar_path' ] as $action ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, 'ajax_' . substr( $action, 6 ) ] );
			add_action( 'wp_ajax_nopriv_' . $action, [ __CLASS__, 'ajax_' . substr( $action, 6 ) ] );
		}

		add_action( 'woocommerce_order_status_on-hold', [ __CLASS__, 'order_pending' ] );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'order_paid' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'order_paid' ] );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bavar_leads';
	}

	/**
	 * Create / update the table.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				full_name varchar(190) NOT NULL DEFAULT '',
				phone varchar(20) NOT NULL DEFAULT '',
				job varchar(190) NOT NULL DEFAULT '',
				path varchar(40) NOT NULL DEFAULT '',
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				answers longtext NULL,
				source varchar(40) NOT NULL DEFAULT '',
				page_url varchar(255) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				purchase_status varchar(20) NOT NULL DEFAULT 'lead',
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY phone (phone),
				KEY path (path),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * Insert a lead row.
	 *
	 * @param array $data Row data.
	 * @return int Insert ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row = wp_parse_args(
			$data,
			[
				'created_at'      => current_time( 'mysql' ),
				'full_name'       => '',
				'phone'           => '',
				'job'             => '',
				'path'            => '',
				'product_id'      => 0,
				'answers'         => null,
				'source'          => '',
				'page_url'        => '',
				'user_id'         => get_current_user_id(),
				'purchase_status' => 'lead',
				'order_id'        => 0,
			]
		);
		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Read + validate the common person fields from the request.
	 *
	 * @return array|WP_Error
	 */
	private static function person_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, cache-friendly lead forms; protected by honeypot + rate limit.
		if ( ! empty( $_POST['website'] ) ) {
			return new WP_Error( 'spam', 'درخواست نامعتبر است.' );
		}
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone = bavar_normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
		$job   = sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) );
		$page  = esc_url_raw( wp_unslash( $_POST['page'] ?? '' ) );
		// phpcs:enable

		if ( mb_strlen( $name ) < 3 ) {
			return new WP_Error( 'name', 'لطفاً نام و نام خانوادگی را کامل وارد کنید.' );
		}
		if ( ! $phone ) {
			return new WP_Error( 'phone', 'شماره موبایل معتبر نیست. نمونه: ۰۹۱۲۱۲۳۴۵۶۷' );
		}
		if ( mb_strlen( $job ) < 2 ) {
			return new WP_Error( 'job', 'لطفاً شغل یا حوزه‌ی فعالیت خود را وارد کنید.' );
		}
		if ( self::rate_limited() ) {
			return new WP_Error( 'rate', 'تعداد درخواست‌ها زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}

		return [
			'full_name' => mb_substr( $name, 0, 190 ),
			'phone'     => $phone,
			'job'       => mb_substr( $job, 0, 190 ),
			'page_url'  => mb_substr( $page, 0, 255 ),
		];
	}

	/**
	 * Allow at most 30 submissions per IP per hour.
	 *
	 * @return bool
	 */
	private static function rate_limited() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'bavar_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 30 ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Remember the visitor so they are not asked again.
	 *
	 * @param array $person Person fields.
	 */
	private static function remember( array $person ) {
		$payload = base64_encode( wp_json_encode( [ $person['full_name'], $person['phone'], $person['job'] ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$value   = $payload . '.' . wp_hash( $payload );
		$expire  = time() + YEAR_IN_SECONDS;
		$secure  = is_ssl();
		setcookie( self::COOKIE, $value, $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, true );
		setcookie( self::FLAG_COOKIE, '1', $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, false );
	}

	/**
	 * The remembered visitor (from the signed cookie), or null.
	 *
	 * @return array|null { full_name, phone, job }
	 */
	public static function current() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$parts = explode( '.', $raw, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( $parts[0] ), $parts[1] ) ) {
			return null;
		}
		$data = json_decode( base64_decode( $parts[0] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! is_array( $data ) || 3 !== count( $data ) ) {
			return null;
		}
		return [
			'full_name' => (string) $data[0],
			'phone'     => (string) $data[1],
			'job'       => (string) $data[2],
		];
	}

	/**
	 * Entry gate submission.
	 */
	public static function ajax_lead() {
		$person = self::person_from_request();
		if ( is_wp_error( $person ) ) {
			wp_send_json_error( [ 'message' => $person->get_error_message() ], 400 );
		}
		self::insert( $person + [ 'source' => 'gate' ] );
		self::remember( $person );
		wp_send_json_success( [ 'lead' => self::public_person( $person ) ] );
	}

	/**
	 * Three-question submission for one of the four paths.
	 */
	public static function ajax_path() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$key  = sanitize_key( wp_unslash( $_POST['path'] ?? '' ) );
		$path = Bavar_Settings::path( $key );
		if ( ! $path ) {
			wp_send_json_error( [ 'message' => 'مسیر انتخاب‌شده معتبر نیست.' ], 400 );
		}
		$person = self::person_from_request();
		if ( is_wp_error( $person ) ) {
			wp_send_json_error( [ 'message' => $person->get_error_message() ], 400 );
		}

		$answers = [];
		foreach ( [ 1, 2, 3 ] as $i ) {
			$answer = sanitize_textarea_field( wp_unslash( $_POST[ 'a' . $i ] ?? '' ) );
			if ( '' === trim( $answer ) ) {
				wp_send_json_error( [ 'message' => 'لطفاً به هر سه سؤال پاسخ دهید.' ], 400 );
			}
			$answers[] = [
				'q' => $path[ 'q' . $i ],
				'a' => mb_substr( $answer, 0, 2000 ),
			];
		}
		$product_id = absint( $_POST['product_id'] ?? 0 );
		// phpcs:enable

		self::insert(
			$person + [
				'path'       => $key,
				'product_id' => $product_id,
				'answers'    => wp_json_encode( $answers, JSON_UNESCAPED_UNICODE ),
				'source'     => 'path',
			]
		);
		self::remember( $person );

		$redirect = Bavar_Settings::path_target( $key );
		if ( in_array( $key, [ 'simorgh', 'consult' ], true ) ) {
			$redirect = add_query_arg( 'bavar_sent', '1', $redirect );
		}

		wp_send_json_success(
			[
				'lead'     => self::public_person( $person ),
				'redirect' => $redirect,
			]
		);
	}

	/**
	 * Fields safe to send back to the browser.
	 *
	 * @param array $person Person.
	 * @return array
	 */
	private static function public_person( array $person ) {
		return [
			'name'  => $person['full_name'],
			'phone' => $person['phone'],
			'job'   => $person['job'],
		];
	}

	/**
	 * Card-to-card order waiting for approval.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_pending( $order_id ) {
		self::sync_order( $order_id, 'pending' );
	}

	/**
	 * Paid order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_paid( $order_id ) {
		self::sync_order( $order_id, 'paid' );
	}

	/**
	 * Reflect an order's status on the buyer's lead rows, so the leads table
	 * doubles as a simple CRM (who asked, who bought).
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   pending|paid.
	 */
	private static function sync_order( $order_id, $status ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$phone = bavar_normalize_phone( $order->get_billing_phone() );
		if ( ! $phone ) {
			return;
		}
		$table = self::table();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$kind       = bavar_product_kind( $product_id );
			$path       = 'course' === $kind ? 'course' : ( 'pack' === $kind ? 'library' : '' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d AND product_id = %d LIMIT 1", $order_id, $product_id )
			);
			if ( $existing ) {
				$wpdb->update( $table, [ 'purchase_status' => $status ], [ 'id' => $existing ] );
				continue;
			}

			self::insert(
				[
					'full_name'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'phone'           => $phone,
					'job'             => (string) $order->get_meta( '_billing_job' ),
					'path'            => $path,
					'product_id'      => $product_id,
					'source'          => 'order',
					'user_id'         => $order->get_customer_id(),
					'purchase_status' => $status,
					'order_id'        => $order_id,
				]
			);

			if ( $path ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET purchase_status = %s WHERE phone = %s AND path = %s AND source = 'path' AND purchase_status <> 'paid'",
						$status,
						$phone,
						$path
					)
				);
			}
			// phpcs:enable
		}
	}
}
