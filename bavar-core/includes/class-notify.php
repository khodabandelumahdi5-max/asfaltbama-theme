<?php
/**
 * Lead notifications: when someone leaves their details, send them to the
 * team by email (and optionally SMS via Kavenegar or Telegram).
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Notify {

	/**
	 * Events that can trigger a notification → Persian label.
	 *
	 * @return array
	 */
	public static function events() {
		return [
			'new_contact'            => 'مخاطب جدید (اولین ثبت اطلاعات)',
			'form_submitted'         => 'ثبت فرم یک بخش (با پاسخ سؤال‌ها)',
			'registration_requested' => 'درخواست ثبت‌نام دوره‌ی حضوری',
			'consultation_requested' => 'درخواست مشاوره',
			'purchase_pending'       => 'سفارش کارت‌به‌کارت در انتظار تأیید',
			'purchase_completed'     => 'خرید موفق',
		];
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'bavar_event', [ __CLASS__, 'on_event' ], 10, 3 );
		add_action( 'admin_post_bavar_test_notify', [ __CLASS__, 'test' ] );
	}

	/**
	 * Is an event switched on?
	 *
	 * @param string $key Event key.
	 * @return bool
	 */
	private static function enabled( $key ) {
		$on = Bavar_Settings::get( 'notify_events' );
		return is_array( $on ) && in_array( $key, $on, true );
	}

	/**
	 * Handle a CRM event.
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $type       Event type.
	 * @param array  $args       Event args.
	 */
	public static function on_event( $contact_id, $type, $args ) {
		if ( ! $contact_id ) {
			return;
		}
		$key = $type;
		if ( 'phone_submitted' === $type ) {
			if ( ! empty( $args['followed'] ) ) {
				return;
			}
			// Only the very first submission of a phone number is a "new lead".
			global $wpdb;
			$events = Bavar_CRM::events_table();
			$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE contact_id = %d AND type = 'phone_submitted'", $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $count ) {
				return;
			}
			$key = 'new_contact';
		}
		if ( ! array_key_exists( $key, self::events() ) || ! self::enabled( $key ) ) {
			return;
		}
		$contact = Bavar_CRM::get( $contact_id );
		if ( $contact ) {
			self::send( self::events()[ $key ], self::lines( $contact, $args ), (int) $contact_id );
		}
	}

	/**
	 * Message lines for a contact + event.
	 *
	 * @param object $c    Contact row.
	 * @param array  $args Event args.
	 * @return string[]
	 */
	private static function lines( $c, array $args ) {
		$paths = Bavar_Settings::get( 'paths' );
		$lines = [
			'نام: ' . trim( $c->first_name . ' ' . $c->last_name ),
			'موبایل: ' . $c->phone,
		];
		if ( $c->job ) {
			$lines[] = 'شغل: ' . $c->job;
		}
		if ( ! empty( $args['path'] ) && isset( $paths[ $args['path'] ] ) ) {
			$lines[] = 'بخش: ' . $paths[ $args['path'] ]['label'];
		}
		if ( ! empty( $args['product_id'] ) ) {
			$lines[] = 'محصول: ' . get_the_title( (int) $args['product_id'] );
		}
		if ( ! empty( $args['order_id'] ) ) {
			$lines[] = 'سفارش: #' . (int) $args['order_id'];
		}
		foreach ( (array) ( $args['data']['answers'] ?? [] ) as $qa ) {
			$lines[] = $qa['q'] . ' ' . $qa['a'];
		}
		return $lines;
	}

	/**
	 * Send through every configured channel.
	 *
	 * @param string   $title      Title.
	 * @param string[] $lines      Body lines.
	 * @param int      $contact_id Contact ID (for the admin link).
	 * @param bool     $wait       Wait for SMS / Telegram answers (test button). Lead
	 *                             notifications do not wait, so visitors are never delayed.
	 * @return array channel => true|string error
	 */
	public static function send( $title, array $lines, $contact_id = 0, $wait = false ) {
		$s       = Bavar_Settings::all();
		$results = [];
		$link    = $contact_id ? admin_url( 'admin.php?page=bavar&contact=' . $contact_id ) : admin_url( 'admin.php?page=bavar' );
		$text    = $title . "\n" . implode( "\n", $lines );

		// Email.
		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $s['notify_email'] ) ) ) );
		if ( $emails ) {
			$sent                 = wp_mail( $emails, $title . ' — گروه باور', $text . "\n\nمشاهده در پیشخوان:\n" . $link );
			$results['ایمیل'] = $sent ? true : 'ارسال ایمیل ناموفق بود. افزونه‌ی WP Mail SMTP را نصب و تنظیم کنید.';
		}

		// SMS (Kavenegar).
		$key = trim( (string) $s['sms_api_key'] );
		$to  = array_filter( array_map( 'bavar_normalize_phone', explode( ',', (string) $s['sms_to'] ) ) );
		if ( $key && $to ) {
			$body = [
				'receptor' => implode( ',', $to ),
				'message'  => mb_substr( $text, 0, 600 ),
			];
			if ( trim( (string) $s['sms_sender'] ) ) {
				$body['sender'] = trim( (string) $s['sms_sender'] );
			}
			$res  = wp_remote_post(
				'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/sms/send.json',
				[
					'timeout'  => $wait ? 15 : 3,
					'blocking' => $wait,
					'body'     => $body,
				]
			);
			$data = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
			$results['پیامک'] = ! $wait ? true : ( ( isset( $data['return']['status'] ) && 200 === (int) $data['return']['status'] ) ? true : ( is_wp_error( $res ) ? $res->get_error_message() : (string) ( $data['return']['message'] ?? wp_remote_retrieve_body( $res ) ) ) );
		}

		// Telegram.
		$token = trim( (string) $s['tg_token'] );
		$chat  = trim( (string) $s['tg_chat'] );
		if ( $token && $chat ) {
			$res  = wp_remote_post(
				'https://api.telegram.org/bot' . $token . '/sendMessage',
				[
					'timeout'  => $wait ? 15 : 3,
					'blocking' => $wait,
					'body'     => [
						'chat_id' => $chat,
						'text'    => $text . "\n" . $link,
					],
				]
			);
			$data = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
			$results['تلگرام'] = ! $wait ? true : ( ! empty( $data['ok'] ) ? true : ( is_wp_error( $res ) ? $res->get_error_message() : (string) ( $data['description'] ?? 'ارسال ناموفق بود' ) ) );
		}

		return $results;
	}

	/**
	 * "Send a test message" button.
	 */
	public static function test() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'bavar_test_notify' ) ) {
			wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
		}
		$results = self::send( 'پیام آزمایشی', [ 'اگر این پیام را می‌بینید، اطلاع‌رسانی لیدها درست کار می‌کند.' ], 0, true );
		$report  = [];
		foreach ( $results as $channel => $result ) {
			$report[] = $channel . ': ' . ( true === $result ? 'ارسال شد' : $result );
		}
		set_transient( 'bavar_notify_test', $report ? $report : [ 'هیچ روشی تنظیم نشده است.' ], 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=bavar-settings#bavar-notify' ) );
		exit;
	}
}
