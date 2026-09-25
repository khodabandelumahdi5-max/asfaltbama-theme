<?php
/**
 * SMS: Kavenegar and Melipayamak drivers (or "log only" for testing),
 * templates per event, a send log, and the store events that send them.
 * Other providers can be added with the `yadak_sms_send` filter.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_SMS {

	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_processing' ), 20, 2 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'on_order_processing' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ), 20, 2 );
		add_action( 'woocommerce_low_stock', array( __CLASS__, 'on_low_stock' ) );
		add_action( 'woocommerce_no_stock', array( __CLASS__, 'on_low_stock' ) );
		add_action( 'yadak_ledger_entry_added', array( __CLASS__, 'on_ledger_entry' ), 10, 2 );
		add_action( 'yadak_customer_group_changed', array( __CLASS__, 'on_group_changed' ), 10, 3 );
		add_action( 'yadak_daily', array( __CLASS__, 'cheque_reminders' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yadak_sms_test', array( __CLASS__, 'handle_test' ) );
	}

	/**
	 * @return array<string,string>
	 */
	public static function drivers() {
		return apply_filters(
			'yadak_sms_drivers',
			array(
				'none'        => __( 'خاموش', 'yadak-core' ),
				'log'         => __( 'فقط ثبت در گزارش (آزمایشی)', 'yadak-core' ),
				'kavenegar'   => __( 'کاوه‌نگار', 'yadak-core' ),
				'melipayamak' => __( 'ملی‌پیامک', 'yadak-core' ),
			)
		);
	}

	/**
	 * Event key => label.
	 *
	 * @return array<string,string>
	 */
	public static function events() {
		return array(
			'order_received'  => __( 'پیامک مشتری: ثبت سفارش', 'yadak-core' ),
			'order_shipped'   => __( 'پیامک مشتری: ارسال سفارش', 'yadak-core' ),
			'admin_new_order' => __( 'پیامک مدیر: سفارش جدید', 'yadak-core' ),
			'admin_low_stock' => __( 'پیامک مدیر: کمبود موجودی', 'yadak-core' ),
			'credit_charge'   => __( 'پیامک مشتری: خرید اعتباری', 'yadak-core' ),
			'payment'         => __( 'پیامک مشتری: ثبت دریافت', 'yadak-core' ),
			'cheque_due'      => __( 'پیامک مشتری: سررسید چک (فردا)', 'yadak-core' ),
			'b2b_approved'    => __( 'پیامک مشتری: تأیید حساب همکار', 'yadak-core' ),
			'quote_sent'      => __( 'پیامک مشتری: پیش‌فاکتور جدید', 'yadak-core' ),
			'replace_due'     => __( 'پیامک مشتری: یادآوری تعویض قطعه', 'yadak-core' ),
		);
	}

	/**
	 * @return array<string,string> Setting key => default text.
	 */
	public static function default_templates() {
		return array(
			'sms_tpl_order_received'  => '{name} عزیز، سفارش {order} به مبلغ {total} ثبت شد. {site}',
			'sms_tpl_order_shipped'   => 'سفارش {order} ارسال شد. کد رهگیری: {tracking} — {site}',
			'sms_tpl_admin_new_order' => 'سفارش جدید {order} — {total} — {name}',
			'sms_tpl_admin_low_stock' => 'کمبود موجودی: {product} (موجودی {amount})',
			'sms_tpl_credit_charge'   => 'مبلغ {amount} بابت سفارش {order} به حساب شما ثبت شد. اعتبار باقی‌مانده: {total} — {site}',
			'sms_tpl_payment'         => 'دریافت {amount} از شما ثبت شد. مانده حساب: {total} — {site}',
			'sms_tpl_cheque_due'      => '{name} عزیز، چک شما به مبلغ {amount} فردا ({date}) سررسید است. {site}',
			'sms_tpl_b2b_approved'    => '{name} عزیز، حساب همکار شما فعال شد. از این پس قیمت همکار را می‌بینید. {link}',
			'sms_tpl_quote_sent'      => 'پیش‌فاکتور {order} به مبلغ {total} برای شما صادر شد. مشاهده و تأیید: {link}',
			'sms_tpl_replace_due'     => '{name} عزیز، زمان تعویض {product} خودروی شما نزدیک است. سفارش: {link}',
		);
	}

	/**
	 * Send one SMS through the configured driver and log it.
	 *
	 * @param string $mobile  Mobile number.
	 * @param string $message Text.
	 * @param string $event   Event key for the log.
	 * @return bool
	 */
	public static function send( $mobile, $message, $event = '' ) {
		global $wpdb;
		$driver = Yadak_Settings::get( 'sms_driver' );
		$mobile = Yadak_Checkout::normalize_mobile( $mobile );
		if ( 'none' === $driver || ! $mobile || '' === trim( $message ) ) {
			return false;
		}

		$result = array(
			'ok'       => false,
			'response' => '',
		);
		switch ( $driver ) {
			case 'log':
				$result = array(
					'ok'       => true,
					'response' => 'logged',
				);
				break;
			case 'kavenegar':
				$result = self::send_kavenegar( $mobile, $message );
				break;
			case 'melipayamak':
				$result = self::send_melipayamak( $mobile, $message );
				break;
			default:
				$result = apply_filters( 'yadak_sms_send', $result, $driver, $mobile, $message );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Yadak_Install::table( 'sms_log' ),
			array(
				'mobile'     => $mobile,
				'message'    => $message,
				'event'      => $event,
				'status'     => $result['ok'] ? 'sent' : 'failed',
				'response'   => mb_substr( (string) $result['response'], 0, 1000 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return (bool) $result['ok'];
	}

	/**
	 * https://api.kavenegar.com/v1/{API-KEY}/sms/send.json
	 */
	private static function send_kavenegar( $mobile, $message ) {
		$key = Yadak_Settings::get( 'sms_api_key' );
		$res = wp_remote_post(
			'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/sms/send.json',
			array(
				'timeout' => 15,
				'body'    => array_filter(
					array(
						'receptor' => $mobile,
						'sender'   => Yadak_Settings::get( 'sms_sender' ),
						'message'  => $message,
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'response' => $res->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return array(
			'ok'       => isset( $body['return']['status'] ) && 200 === (int) $body['return']['status'],
			'response' => wp_remote_retrieve_body( $res ),
		);
	}

	/**
	 * https://rest.payamak-panel.com/api/SendSMS/SendSMS
	 */
	private static function send_melipayamak( $mobile, $message ) {
		$res = wp_remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
			array(
				'timeout' => 15,
				'body'    => array(
					'username' => Yadak_Settings::get( 'sms_username' ),
					'password' => Yadak_Settings::get( 'sms_password' ),
					'to'       => $mobile,
					'from'     => Yadak_Settings::get( 'sms_sender' ),
					'text'     => $message,
					'isflash'  => 'false',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'response' => $res->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return array(
			'ok'       => isset( $body['RetStatus'] ) && 1 === (int) $body['RetStatus'],
			'response' => wp_remote_retrieve_body( $res ),
		);
	}

	/**
	 * Fill a template and send it.
	 *
	 * @param string $event  Event key.
	 * @param string $mobile Mobile.
	 * @param array  $vars   Placeholder values without braces.
	 * @return bool
	 */
	public static function notify( $event, $mobile, $vars ) {
		$template = Yadak_Settings::get( 'sms_tpl_' . $event );
		if ( '' === trim( $template ) ) {
			return false;
		}
		$vars += array( 'site' => get_bloginfo( 'name' ) );
		$map   = array();
		foreach ( $vars as $key => $value ) {
			$map[ '{' . $key . '}' ] = wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
		}
		return self::send( $mobile, strtr( $template, $map ), $event );
	}

	/**
	 * Admin mobiles (comma separated setting).
	 *
	 * @return string[]
	 */
	public static function admin_mobiles() {
		return array_filter( array_map( 'trim', explode( ',', Yadak_Settings::get( 'admin_mobile' ) ) ) );
	}

	public static function plain_money( $amount ) {
		return wp_strip_all_tags( html_entity_decode( wc_price( (float) $amount ), ENT_QUOTES, 'UTF-8' ) );
	}

	public static function user_mobile( $user_id ) {
		return (string) get_user_meta( $user_id, 'billing_phone', true );
	}

	public static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		return trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name;
	}

	/**
	 * @param int      $order_id Order.
	 * @param WC_Order $order    Order.
	 */
	public static function on_order_processing( $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_yadak_sms_received' ) ) {
			return;
		}
		$order->update_meta_data( '_yadak_sms_received', 1 );
		$order->save_meta_data();
		$vars = array(
			'name'  => $order->get_formatted_billing_full_name(),
			'order' => $order->get_order_number(),
			'total' => self::plain_money( $order->get_total() ),
			'link'  => $order->get_view_order_url(),
		);
		self::notify( 'order_received', $order->get_billing_phone(), $vars );
		foreach ( self::admin_mobiles() as $mobile ) {
			self::notify( 'admin_new_order', $mobile, $vars );
		}
	}

	public static function on_order_completed( $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::notify(
			'order_shipped',
			$order->get_billing_phone(),
			array(
				'name'     => $order->get_formatted_billing_full_name(),
				'order'    => $order->get_order_number(),
				'tracking' => $order->get_meta( '_yadak_tracking' ) ? $order->get_meta( '_yadak_tracking' ) : '—',
				'link'     => $order->get_view_order_url(),
			)
		);
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function on_low_stock( $product ) {
		if ( ! $product || get_transient( 'yadak_low_stock_sms_' . $product->get_id() ) ) {
			return;
		}
		set_transient( 'yadak_low_stock_sms_' . $product->get_id(), 1, DAY_IN_SECONDS );
		foreach ( self::admin_mobiles() as $mobile ) {
			self::notify(
				'admin_low_stock',
				$mobile,
				array(
					'product' => $product->get_name(),
					'amount'  => (string) $product->get_stock_quantity(),
				)
			);
		}
	}

	public static function on_ledger_entry( $entry_id, $entry ) {
		$user_id = (int) $entry['user_id'];
		if ( 'charge' === $entry['type'] ) {
			$order = wc_get_order( (int) $entry['order_id'] );
			self::notify(
				'credit_charge',
				self::user_mobile( $user_id ),
				array(
					'name'   => self::user_name( $user_id ),
					'amount' => self::plain_money( $entry['amount'] ),
					'order'  => $order ? $order->get_order_number() : '',
					'total'  => self::plain_money( Yadak_Credit::available_credit( $user_id ) ),
				)
			);
		} elseif ( 'payment' === $entry['type'] ) {
			self::notify(
				'payment',
				self::user_mobile( $user_id ),
				array(
					'name'   => self::user_name( $user_id ),
					'amount' => self::plain_money( $entry['amount'] ),
					'total'  => self::plain_money( max( 0, Yadak_Credit::balance( $user_id ) ) ),
				)
			);
		}
	}

	public static function on_group_changed( $user_id, $group, $old ) {
		if ( 'retail' === $old && yadak_get_price_tier( $user_id ) ) {
			self::notify(
				'b2b_approved',
				self::user_mobile( $user_id ),
				array(
					'name' => self::user_name( $user_id ),
					'link' => wc_get_page_permalink( 'shop' ),
				)
			);
		}
	}

	/**
	 * Daily: remind customers whose cheques are due tomorrow.
	 */
	public static function cheque_reminders() {
		global $wpdb;
		$table    = Yadak_Credit::table();
		$tomorrow = yadak_today( '+1 day' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE method = 'cheque' AND due_date = %s", $tomorrow ) );
		foreach ( $rows as $row ) {
			self::notify(
				'cheque_due',
				self::user_mobile( (int) $row->user_id ),
				array(
					'name'   => self::user_name( (int) $row->user_id ),
					'amount' => self::plain_money( $row->amount ),
					'date'   => yadak_show_date( $row->due_date ),
				)
			);
		}
	}

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'گزارش پیامک', 'yadak-core' ), __( 'گزارش پیامک', 'yadak-core' ), 'manage_woocommerce', 'yadak-sms-log', array( __CLASS__, 'render_log' ), 90 );
	}

	public static function render_log() {
		global $wpdb;
		$table = Yadak_Install::table( 'sms_log' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 300" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = self::events();
		echo '<div class="wrap"><h1>' . esc_html__( 'گزارش پیامک‌ها', 'yadak-core' ) . '</h1>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'زمان', 'yadak-core' ) . '</th><th>' . esc_html__( 'موبایل', 'yadak-core' ) . '</th><th>' . esc_html__( 'رویداد', 'yadak-core' ) . '</th><th>' . esc_html__( 'متن', 'yadak-core' ) . '</th><th>' . esc_html__( 'وضعیت', 'yadak-core' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'هنوز پیامکی ارسال نشده است.', 'yadak-core' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%1$s</td><td dir="ltr">%2$s</td><td>%3$s</td><td>%4$s</td><td title="%6$s">%5$s</td></tr>',
				esc_html( yadak_show_date( $row->created_at, 'Y/m/d H:i', true ) ),
				esc_html( $row->mobile ),
				esc_html( isset( $events[ $row->event ] ) ? $events[ $row->event ] : $row->event ),
				esc_html( $row->message ),
				'sent' === $row->status ? '✅' : '❌',
				esc_attr( $row->response )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function render_test_form() {
		?>
		<h2><?php esc_html_e( 'ارسال پیامک آزمایشی', 'yadak-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="yadak_sms_test">
			<?php wp_nonce_field( 'yadak_sms_test' ); ?>
			<input type="text" name="mobile" placeholder="09xxxxxxxxx" dir="ltr">
			<?php submit_button( __( 'ارسال', 'yadak-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	public static function handle_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_sms_test' );
		$ok = self::send( isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '', __( 'پیامک آزمایشی فروشگاه', 'yadak-core' ) . ' ' . get_bloginfo( 'name' ), 'test' );
		wp_safe_redirect( admin_url( 'admin.php?page=yadak-sms-log&sent=' . ( $ok ? 1 : 0 ) ) );
		exit;
	}
}
