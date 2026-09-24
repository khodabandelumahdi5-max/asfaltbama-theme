<?php
/**
 * Central CRM: one contact per phone number, plus an activity (event) log.
 *
 * Every form, purchase and content download is attached to the same contact,
 * so a visitor who fills several forms never becomes several leads.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_CRM {

	const COOKIE      = 'bavar_lead';
	const FLAG_COOKIE = 'bavar_ok';

	/**
	 * Hooks.
	 */
	public static function init() {
		foreach ( [ 'bavar_lead', 'bavar_path', 'bavar_track' ] as $action ) {
			$method = 'ajax_' . substr( $action, 6 );
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
			add_action( 'wp_ajax_nopriv_' . $action, [ __CLASS__, $method ] );
		}

		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'purchase_started' ], 5 );
		add_action( 'woocommerce_order_status_on-hold', [ __CLASS__, 'order_pending' ] );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'order_paid' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'order_paid' ] );
		add_action( 'woocommerce_download_product', [ __CLASS__, 'content_accessed' ], 10, 6 );
	}

	/* ---------------------------------------------------------------------
	 * Labels
	 * ------------------------------------------------------------------- */

	/**
	 * Event types → Persian labels.
	 *
	 * @return array
	 */
	public static function event_types() {
		return [
			'product_viewed'         => 'مشاهده‌ی محصول',
			'phone_submitted'        => 'ثبت شماره',
			'form_submitted'         => 'ثبت فرم',
			'registration_requested' => 'درخواست ثبت‌نام دوره‌ی حضوری',
			'consultation_requested' => 'درخواست مشاوره',
			'purchase_started'       => 'شروع خرید',
			'purchase_pending'       => 'در انتظار تأیید پرداخت',
			'purchase_completed'     => 'خرید موفق',
			'content_accessed'       => 'دسترسی به محتوا',
		];
	}

	/**
	 * Follow-up statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return [
			'new'       => 'جدید',
			'following' => 'در حال پیگیری',
			'contacted' => 'تماس گرفته شد',
			'customer'  => 'مشتری',
			'closed'    => 'بسته شد',
		];
	}

	/**
	 * Entry sources.
	 *
	 * @return array
	 */
	public static function sources() {
		return [
			'gate'    => 'ورود به سایت',
			'course'  => 'کتاب مقدس گروه باور',
			'library' => 'پک کتاب',
			'simorgh' => 'آشیانه سیمرغ',
			'consult' => 'مشاوره',
			'order'   => 'خرید',
		];
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Contacts table.
	 *
	 * @return string
	 */
	public static function contacts_table() {
		global $wpdb;
		return $wpdb->prefix . 'bavar_contacts';
	}

	/**
	 * Events table.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'bavar_events';
	}

	/**
	 * Create / update tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$contacts = self::contacts_table();
		$events   = self::events_table();
		dbDelta(
			"CREATE TABLE {$contacts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				phone varchar(20) NOT NULL DEFAULT '',
				first_name varchar(100) NOT NULL DEFAULT '',
				last_name varchar(100) NOT NULL DEFAULT '',
				job varchar(190) NOT NULL DEFAULT '',
				source varchar(40) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'new',
				notes longtext NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				last_activity varchar(40) NOT NULL DEFAULT '',
				last_activity_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY phone (phone),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset};
			CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(40) NOT NULL DEFAULT '',
				path varchar(40) NOT NULL DEFAULT '',
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				data longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY contact_id (contact_id),
				KEY type (type),
				KEY product_id (product_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * Find a contact by phone.
	 *
	 * @param string $phone Normalised phone.
	 * @return object|null
	 */
	public static function find_by_phone( $phone ) {
		global $wpdb;
		$table = self::contacts_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s", $phone ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get a contact by ID.
	 *
	 * @param int $id Contact ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::contacts_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create or update the contact for a phone number. Empty values never
	 * overwrite what is already known.
	 *
	 * @param array $data phone (required), first_name, last_name, job, source, user_id.
	 * @return int Contact ID (0 when the phone is invalid).
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		$phone = bavar_normalize_phone( $data['phone'] ?? '' );
		if ( ! $phone ) {
			return 0;
		}
		$now      = current_time( 'mysql' );
		$table    = self::contacts_table();
		$existing = self::find_by_phone( $phone );
		$fields   = [];
		foreach ( [ 'first_name', 'last_name', 'job' ] as $key ) {
			if ( isset( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
				$fields[ $key ] = mb_substr( trim( (string) $data[ $key ] ), 0, 'job' === $key ? 190 : 100 );
			}
		}
		if ( ! empty( $data['user_id'] ) ) {
			$fields['user_id'] = (int) $data['user_id'];
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $existing ) {
			if ( $fields ) {
				$wpdb->update( $table, $fields + [ 'updated_at' => $now ], [ 'id' => $existing->id ] );
			}
			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			$fields + [
				'phone'      => $phone,
				'source'     => sanitize_key( $data['source'] ?? '' ),
				'status'     => 'new',
				'created_at' => $now,
				'updated_at' => $now,
			]
		);
		// phpcs:enable
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update editable contact fields from the admin.
	 *
	 * @param int   $id   Contact ID.
	 * @param array $data Fields.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$allowed = array_intersect_key( $data, array_flip( [ 'first_name', 'last_name', 'job', 'status', 'notes' ] ) );
		if ( $allowed ) {
			$wpdb->update( self::contacts_table(), $allowed + [ 'updated_at' => current_time( 'mysql' ) ], [ 'id' => (int) $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Record an event.
	 *
	 * @param int    $contact_id Contact ID (0 for anonymous).
	 * @param string $type       Event type.
	 * @param array  $args       path, product_id, order_id, data.
	 */
	public static function log( $contact_id, $type, array $args = [] ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			self::events_table(),
			[
				'contact_id' => (int) $contact_id,
				'type'       => sanitize_key( $type ),
				'path'       => sanitize_key( $args['path'] ?? '' ),
				'product_id' => (int) ( $args['product_id'] ?? 0 ),
				'order_id'   => (int) ( $args['order_id'] ?? 0 ),
				'data'       => empty( $args['data'] ) ? null : wp_json_encode( $args['data'], JSON_UNESCAPED_UNICODE ),
				'created_at' => $now,
			]
		);
		if ( $contact_id ) {
			$wpdb->update(
				self::contacts_table(),
				[
					'last_activity'    => sanitize_key( $type ),
					'last_activity_at' => $now,
				],
				[ 'id' => (int) $contact_id ]
			);
		}
		// phpcs:enable

		/**
		 * Fires for every tracked event — hook SMS, email or analytics here.
		 *
		 * @param int    $contact_id Contact ID.
		 * @param string $type       Event type.
		 * @param array  $args       Event data.
		 */
		do_action( 'bavar_event', (int) $contact_id, $type, $args );
	}

	/**
	 * Promote a contact to "customer" unless the admin closed it.
	 *
	 * @param int $contact_id Contact ID.
	 */
	private static function mark_customer( $contact_id ) {
		global $wpdb;
		$table = self::contacts_table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'customer' WHERE id = %d AND status <> 'closed'", $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ---------------------------------------------------------------------
	 * Visitor identity
	 * ------------------------------------------------------------------- */

	/**
	 * Remember the visitor (signed cookie).
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $person     first_name, last_name, phone, job.
	 */
	private static function remember( $contact_id, array $person ) {
		$payload = base64_encode( wp_json_encode( [ (int) $contact_id, $person['first_name'], $person['last_name'], $person['phone'], $person['job'] ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$value   = $payload . '.' . wp_hash( $payload );
		$expire  = time() + YEAR_IN_SECONDS;
		$path    = COOKIEPATH ? COOKIEPATH : '/';
		setcookie( self::COOKIE, $value, $expire, $path, COOKIE_DOMAIN, is_ssl(), true );
		setcookie( self::FLAG_COOKIE, '1', $expire, $path, COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * The remembered visitor, or null.
	 *
	 * @return array|null { id, first_name, last_name, phone, job }
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
		if ( ! is_array( $data ) || 5 !== count( $data ) ) {
			return null;
		}
		return [
			'id'         => (int) $data[0],
			'first_name' => (string) $data[1],
			'last_name'  => (string) $data[2],
			'phone'      => (string) $data[3],
			'job'        => (string) $data[4],
		];
	}

	/**
	 * Contact ID of the current visitor (cookie, then logged-in customer).
	 *
	 * @return int
	 */
	public static function current_id() {
		$cur = self::current();
		if ( $cur && $cur['id'] ) {
			return $cur['id'];
		}
		$user = get_current_user_id();
		if ( $user ) {
			$phone = bavar_normalize_phone( get_user_meta( $user, 'billing_phone', true ) );
			$found = $phone ? self::find_by_phone( $phone ) : null;
			return $found ? (int) $found->id : 0;
		}
		return 0;
	}

	/* ---------------------------------------------------------------------
	 * Requests
	 * ------------------------------------------------------------------- */

	/**
	 * Person fields a form can ask for.
	 *
	 * @return array key => [label, min length]
	 */
	public static function person_fields() {
		return [
			'first_name' => [ 'نام', 2 ],
			'last_name'  => [ 'نام خانوادگی', 2 ],
			'phone'      => [ 'شماره تماس', 0 ],
			'job'        => [ 'شغل', 2 ],
		];
	}

	/**
	 * Read + validate the person fields that a form declared.
	 *
	 * @return array|WP_Error
	 */
	private static function person_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, cache-friendly forms; honeypot + rate limit instead.
		if ( ! empty( $_POST['website'] ) ) {
			return new WP_Error( 'spam', 'درخواست نامعتبر است.' );
		}
		$asked = array_intersect( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_POST['fields'] ?? '' ) ) ), array_keys( self::person_fields() ) );
		$asked = array_unique( array_merge( $asked, [ 'phone' ] ) );

		$person = [];
		foreach ( self::person_fields() as $key => $conf ) {
			$person[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		// phpcs:enable
		$person['phone'] = bavar_normalize_phone( $person['phone'] );

		foreach ( $asked as $key ) {
			$conf = self::person_fields()[ $key ];
			if ( 'phone' === $key ) {
				if ( ! $person['phone'] ) {
					return new WP_Error( 'phone', 'شماره موبایل معتبر نیست. نمونه: ۰۹۱۲۱۲۳۴۵۶۷' );
				}
			} elseif ( mb_strlen( trim( $person[ $key ] ) ) < $conf[1] ) {
				return new WP_Error( $key, sprintf( 'لطفاً %s را درست وارد کنید.', $conf[0] ) );
			}
		}
		if ( self::rate_limited( 'form', 30 ) ) {
			return new WP_Error( 'rate', 'تعداد درخواست‌ها زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}
		return $person;
	}

	/**
	 * Simple per-IP rate limit.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $max    Max per hour.
	 * @return bool
	 */
	private static function rate_limited( $bucket, $max ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'bavar_rl_' . $bucket . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Save a person, remember them and log "phone submitted".
	 *
	 * @param array  $person Person.
	 * @param string $source Source key.
	 * @return int Contact ID.
	 */
	private static function save_person( array $person, $source ) {
		$id = self::upsert( $person + [ 'source' => $source, 'user_id' => get_current_user_id() ] );
		// Keep the cookie complete even when a short form (phone only) was used.
		$known = self::get( $id );
		self::remember(
			$id,
			[
				'first_name' => $known ? $known->first_name : $person['first_name'],
				'last_name'  => $known ? $known->last_name : $person['last_name'],
				'phone'      => $person['phone'],
				'job'        => $known ? $known->job : $person['job'],
			]
		);
		return $id;
	}

	/**
	 * Fields safe to send back to the browser.
	 *
	 * @param int $id Contact ID.
	 * @return array
	 */
	private static function public_person( $id ) {
		$c = self::get( $id );
		return $c ? [
			'first_name' => $c->first_name,
			'last_name'  => $c->last_name,
			'phone'      => $c->phone,
			'job'        => $c->job,
		] : null;
	}

	/**
	 * Entry form.
	 */
	public static function ajax_lead() {
		$person = self::person_from_request();
		if ( is_wp_error( $person ) ) {
			wp_send_json_error( [ 'message' => $person->get_error_message() ], 400 );
		}
		$id = self::save_person( $person, 'gate' );
		self::log( $id, 'phone_submitted', [ 'path' => 'gate' ] );
		wp_send_json_success( [ 'lead' => self::public_person( $id ) ] );
	}

	/**
	 * Section form (course, library, Ashiane Simorgh, consulting).
	 */
	public static function ajax_path() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$key  = sanitize_key( wp_unslash( $_POST['path'] ?? '' ) );
		$path = Bavar_Settings::path( $key );
		if ( ! $path ) {
			wp_send_json_error( [ 'message' => 'بخش انتخاب‌شده معتبر نیست.' ], 400 );
		}
		$person = self::person_from_request();
		if ( is_wp_error( $person ) ) {
			wp_send_json_error( [ 'message' => $person->get_error_message() ], 400 );
		}

		$answers = [];
		foreach ( Bavar_Settings::questions( $key ) as $i => $question ) {
			$answer = sanitize_textarea_field( wp_unslash( $_POST[ 'a' . ( $i + 1 ) ] ?? '' ) );
			if ( '' === trim( $answer ) ) {
				wp_send_json_error( [ 'message' => 'لطفاً به همه‌ی سؤال‌ها پاسخ دهید.' ], 400 );
			}
			$answers[] = [
				'q' => $question,
				'a' => mb_substr( $answer, 0, 2000 ),
			];
		}
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$inline     = ! empty( $_POST['inline'] );
		// phpcs:enable

		$id   = self::save_person( $person, $key );
		$args = [
			'path'       => $key,
			'product_id' => $product_id,
			'data'       => $answers ? [ 'answers' => $answers ] : [],
		];
		self::log( $id, 'phone_submitted', [ 'path' => $key ] );
		if ( 'consult' === $key ) {
			self::log( $id, 'consultation_requested', $args );
		} elseif ( 'simorgh' === $key && $inline ) {
			self::log( $id, 'registration_requested', $args );
		} elseif ( $answers ) {
			self::log( $id, 'form_submitted', $args );
		}

		$redirect = Bavar_Settings::path_target( $key );
		if ( $inline ) {
			$redirect = add_query_arg( 'bavar_sent', '1', $redirect );
		}
		wp_send_json_success(
			[
				'lead'     => self::public_person( $id ),
				'redirect' => $redirect,
			]
		);
	}

	/**
	 * View beacon (works with page caching).
	 */
	public static function ajax_track() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$path       = sanitize_key( wp_unslash( $_POST['path'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		// phpcs:enable
		if ( ( ! $path && ! $product_id ) || self::rate_limited( 'view', 300 ) ) {
			wp_send_json_success();
		}
		if ( $product_id && 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_success();
		}
		$contact = self::current_id();
		// Count one view per visitor, page and half hour.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$seen = 'bavar_v_' . md5( $contact . '|' . $ip . '|' . $path . '|' . $product_id );
		if ( ! get_transient( $seen ) ) {
			set_transient( $seen, 1, 30 * MINUTE_IN_SECONDS );
			self::log(
				$contact,
				'product_viewed',
				[
					'path'       => $path,
					'product_id' => $product_id,
				]
			);
		}
		wp_send_json_success();
	}

	/* ---------------------------------------------------------------------
	 * WooCommerce events
	 * ------------------------------------------------------------------- */

	/**
	 * Checkout page opened with BAVAR products in the cart.
	 */
	public static function purchase_started() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$contact = self::current_id();
		$key     = 'bavar_ps_' . md5( $contact . '|' . WC()->cart->get_cart_hash() );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, HOUR_IN_SECONDS );
		foreach ( WC()->cart->get_cart() as $item ) {
			$pid = (int) $item['product_id'];
			self::log(
				$contact,
				'purchase_started',
				[
					'path'       => self::path_of_product( $pid ),
					'product_id' => $pid,
				]
			);
		}
	}

	/**
	 * Section key for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function path_of_product( $product_id ) {
		$kind = bavar_product_kind( $product_id );
		return 'course' === $kind ? 'course' : ( 'pack' === $kind ? 'library' : '' );
	}

	/**
	 * Contact for an order (created from billing details when needed).
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private static function contact_for_order( $order ) {
		return self::upsert(
			[
				'phone'      => $order->get_billing_phone(),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'job'        => (string) $order->get_meta( '_billing_job' ),
				'source'     => 'order',
				'user_id'    => $order->get_customer_id(),
			]
		);
	}

	/**
	 * Log one event per order item, once per order and type.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $type     Event type.
	 */
	private static function order_event( $order_id, $type ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_bavar_logged_' . $type ) ) {
			return;
		}
		$contact = self::contact_for_order( $order );
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			self::log(
				$contact,
				$type,
				[
					'path'       => self::path_of_product( $pid ),
					'product_id' => $pid,
					'order_id'   => $order_id,
				]
			);
		}
		if ( 'purchase_completed' === $type && $contact ) {
			self::mark_customer( $contact );
		}
		$order->update_meta_data( '_bavar_logged_' . $type, 1 );
		$order->save_meta_data();
	}

	/**
	 * Waiting for card-to-card approval.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_pending( $order_id ) {
		self::order_event( $order_id, 'purchase_pending' );
	}

	/**
	 * Paid.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_paid( $order_id ) {
		self::order_event( $order_id, 'purchase_completed' );
	}

	/**
	 * A purchased file was downloaded.
	 *
	 * @param string $email       Email.
	 * @param string $order_key   Order key.
	 * @param int    $product_id  Product ID.
	 * @param int    $user_id     User ID.
	 * @param string $download_id Download ID.
	 * @param int    $order_id    Order ID.
	 */
	public static function content_accessed( $email, $order_key, $product_id, $user_id, $download_id, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::log(
			self::contact_for_order( $order ),
			'content_accessed',
			[
				'path'       => self::path_of_product( $product_id ),
				'product_id' => (int) $product_id,
				'order_id'   => (int) $order_id,
				'data'       => [ 'file' => (string) $download_id ],
			]
		);
	}
}
