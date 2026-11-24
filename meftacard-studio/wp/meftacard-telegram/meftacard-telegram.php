<?php
/**
 * Plugin Name: Meftacard Telegram Bot
 * Description: ربات تلگرام Meftacard: منوی مشتری، پیگیری سفارش، پشتیبانی، و مدیریت سفارش‌ها (تحویل، رد، آمار) + اعلان‌ها.
 * Version:     2.2.0
 * Author:      MEFTACARD
 * Requires PHP: 7.4
 * Text Domain: meftacard-telegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mefta_Telegram_Notifier {

	const OPT          = 'mefta_tg_settings';
	const PAGE         = 'mefta-telegram';
	const RATE_LIMIT   = 30; // max notifications per IP per hour (anti-spam)

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_mefta_tg_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_mefta_tg_chatid', array( __CLASS__, 'handle_find_chat' ) );

		// 1) New invoice requests coming from the checkout modal (runs before the original handler).
		add_action( 'wp_ajax_nopriv_mefta_create_invoice', array( __CLASS__, 'on_invoice_request' ), 1 );
		add_action( 'wp_ajax_mefta_create_invoice', array( __CLASS__, 'on_invoice_request' ), 1 );

		// 2) Email delivery failures (helps debug OTP / SMTP problems).
		add_action( 'wp_mail_failed', array( __CLASS__, 'on_mail_failed' ) );

		// 3) Generic hooks the main plugin can fire:
		//    do_action( 'mefta_order_paid', $order_id, $amount, $network, $email );
		//    do_action( 'mefta_telegram_notify', 'any text' );
		add_action( 'mefta_order_paid', array( __CLASS__, 'on_order_paid' ), 10, 4 );
		add_action( 'mefta_telegram_notify', array( __CLASS__, 'notify' ), 10, 1 );

		require_once __DIR__ . '/includes/class-bot.php';
		Mefta_TG_Bot::init();
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function settings() {
		$defaults = array(
			'token'         => '',
			'chat_id'       => '',
			'notify_orders' => '1',
			'notify_mail'   => '1',
			'notify_paid'   => '1',
			'products_fa'   => '',
			'products_en'   => '',
		);
		return wp_parse_args( (array) get_option( self::OPT, array() ), $defaults );
	}

	public static function admin_menu() {
		add_options_page( 'Meftacard Telegram', 'Meftacard Telegram', 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	public static function register_settings() {
		register_setting(
			'mefta_tg_group',
			self::OPT,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $in ) {
		$old = self::settings();
		$out = array();

		$token = isset( $in['token'] ) ? trim( (string) $in['token'] ) : '';
		// Empty field = keep existing token (the field is never pre-filled, to avoid exposing it).
		if ( '' === $token ) {
			$out['token'] = $old['token'];
		} elseif ( preg_match( '/^\d{6,12}:[A-Za-z0-9_-]{30,}$/', $token ) ) {
			$out['token'] = $token;
		} else {
			add_settings_error( self::OPT, 'bad_token', 'فرمت توکن ربات درست نیست.' );
			$out['token'] = $old['token'];
		}
		if ( ! empty( $in['clear_token'] ) ) {
			$out['token'] = '';
		}

		$chat = isset( $in['chat_id'] ) ? trim( (string) $in['chat_id'] ) : '';
		$out['chat_id'] = preg_match( '/^(-?\d{3,20}|@[A-Za-z0-9_]{4,64})$/', $chat ) ? $chat : '';
		if ( '' !== $chat && '' === $out['chat_id'] ) {
			add_settings_error( self::OPT, 'bad_chat', 'Chat ID نامعتبر است.' );
		}

		$allowed = array( 'b' => array(), 'i' => array(), 'u' => array(), 'code' => array(), 'a' => array( 'href' => true ) );
		foreach ( array( 'products_fa', 'products_en' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? wp_kses( trim( (string) wp_unslash( $in[ $k ] ) ), $allowed ) : ( $old[ $k ] ?? '' );
		}
		foreach ( array( 'notify_orders', 'notify_mail', 'notify_paid' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? '0' : '1';
		}
		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = self::settings();
		$has_token = '' !== $s['token'];
		$masked    = $has_token ? substr( $s['token'], 0, 4 ) . '••••••••' . substr( $s['token'], -4 ) : '';
		$found     = get_transient( 'mefta_tg_found_chats' );
		?>
		<div class="wrap" dir="rtl" style="max-width:780px">
			<h1>اتصال ربات تلگرام Meftacard</h1>
			<?php settings_errors( self::OPT ); ?>
			<?php if ( isset( $_GET['mefta_tg'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-info"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['mefta_tg'] ) ) ); // phpcs:ignore ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'mefta_tg_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mefta_tg_token">توکن ربات</label></th>
						<td>
							<input type="password" id="mefta_tg_token" name="<?php echo esc_attr( self::OPT ); ?>[token]" class="regular-text" dir="ltr" autocomplete="off" placeholder="<?php echo esc_attr( $has_token ? $masked . ' (ذخیره شده)' : '123456789:AA...' ); ?>">
							<p class="description">از @BotFather بگیرید. برای حفظ توکن فعلی، خالی بگذارید.</p>
							<?php if ( $has_token ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[clear_token]" value="1"> حذف توکن ذخیره‌شده</label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mefta_tg_chat">Chat ID</label></th>
						<td>
							<input type="text" id="mefta_tg_chat" name="<?php echo esc_attr( self::OPT ); ?>[chat_id]" value="<?php echo esc_attr( $s['chat_id'] ); ?>" class="regular-text" dir="ltr">
							<p class="description">شناسه‌ی شما، گروه یا کانال (مثلاً <code>123456789</code>، <code>-100...</code> یا <code>@channel</code>). دکمه‌ی «پیدا کردن Chat ID» در پایین کمک می‌کند.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">اعلان‌ها</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[notify_orders]" value="1" <?php checked( $s['notify_orders'], '1' ); ?>> درخواست فاکتور / سفارش جدید</label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[notify_paid]" value="1" <?php checked( $s['notify_paid'], '1' ); ?>> پرداخت تأیید شده</label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[notify_mail]" value="1" <?php checked( $s['notify_mail'], '1' ); ?>> خطای ارسال ایمیل (OTP / SMTP)</label>
						</td>
					</tr>
					<tr>
						<th scope="row">متن محصولات ربات</th>
						<td>
							<p class="description">لیستی که مشتری با دکمه «محصولات و قیمت‌ها» می‌بیند. خالی = متن پیش‌فرض. تگ‌های مجاز: &lt;b&gt; &lt;i&gt; &lt;code&gt; &lt;a&gt;</p>
							<label>فارسی</label><br>
							<textarea name="<?php echo esc_attr( self::OPT ); ?>[products_fa]" rows="7" class="large-text" dir="rtl"><?php echo esc_textarea( $s['products_fa'] ); ?></textarea>
							<label>English</label><br>
							<textarea name="<?php echo esc_attr( self::OPT ); ?>[products_en]" rows="7" class="large-text" dir="ltr"><?php echo esc_textarea( $s['products_en'] ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>

			<hr>
			<h2>🤖 ربات مشتری و مدیریت سفارش</h2>
			<?php $info = Mefta_TG_Bot::webhook_info(); ?>
			<?php if ( $info && ! empty( $info['url'] ) ) : ?>
				<p>✅ <b>ربات فعال است.</b> در تلگرام به ربات <code>/start</code> بدهید.
				<?php if ( ! empty( $info['last_error_message'] ) ) : ?><br><span style="color:#b32d2e">آخرین خطا: <?php echo esc_html( $info['last_error_message'] ); ?></span><?php endif; ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'mefta_tg_webhook_off' ); ?><input type="hidden" name="action" value="mefta_tg_webhook_off"><?php submit_button( 'غیرفعال کردن ربات', 'secondary', 'submit', false ); ?></form>
			<?php else : ?>
				<p>با فعال‌سازی، مشتری‌ها در ربات منو، پیگیری سفارش و پشتیبانی دارند و شما سفارش‌ها را از تلگرام تحویل می‌دهید.<br><b>قبلش Chat ID را تنظیم و پیام آزمایشی را تست کنید.</b> (بعد از فعال‌سازی، دکمه «پیدا کردن Chat ID» دیگر کار نمی‌کند.)</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'mefta_tg_webhook' ); ?><input type="hidden" name="action" value="mefta_tg_webhook"><?php submit_button( 'فعال‌سازی ربات', 'primary', 'submit', false, ( $has_token && $s['chat_id'] ) ? array() : array( 'disabled' => 'disabled' ) ); ?></form>
			<?php endif; ?>

			<hr>
			<h2>ابزارها</h2>
			<p>۱) در تلگرام به ربات خود پیام <code>/start</code> بدهید (یا ربات را به گروه/کانال اضافه کنید و یک پیام بفرستید). سپس:</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px">
				<?php wp_nonce_field( 'mefta_tg_chatid' ); ?>
				<input type="hidden" name="action" value="mefta_tg_chatid">
				<?php submit_button( 'پیدا کردن Chat ID', 'secondary', 'submit', false, $has_token ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<?php wp_nonce_field( 'mefta_tg_test' ); ?>
				<input type="hidden" name="action" value="mefta_tg_test">
				<?php submit_button( 'ارسال پیام آزمایشی', 'primary', 'submit', false, ( $has_token && $s['chat_id'] ) ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>

			<?php if ( is_array( $found ) && $found ) : ?>
				<h3>چت‌های پیدا شده</h3>
				<table class="widefat striped" style="max-width:600px">
					<thead><tr><th>Chat ID</th><th>نوع</th><th>نام</th></tr></thead>
					<tbody>
					<?php foreach ( $found as $c ) : ?>
						<tr><td dir="ltr"><code><?php echo esc_html( $c['id'] ); ?></code></td><td><?php echo esc_html( $c['type'] ); ?></td><td><?php echo esc_html( $c['title'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Chat ID مورد نظر را در فیلد بالا وارد و ذخیره کنید.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Admin actions
	 * ------------------------------------------------------------------ */

	public static function back( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'mefta_tg' => rawurlencode( $msg ) ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'mefta_tg_test' );
		$res = self::send( "✅ <b>اتصال ربات Meftacard برقرار است</b>\nسایت: " . esc_html( home_url() ) . "\nزمان: " . esc_html( current_time( 'Y-m-d H:i' ) ), true );
		self::back( true === $res ? 'پیام آزمایشی ارسال شد. تلگرام را چک کنید.' : 'ارسال ناموفق: ' . $res );
	}

	public static function handle_find_chat() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'mefta_tg_chatid' );
		$s = self::settings();
		if ( '' === $s['token'] ) {
			self::back( 'ابتدا توکن را ذخیره کنید.' );
		}
		$r = wp_remote_get( 'https://api.telegram.org/bot' . $s['token'] . '/getUpdates?limit=50', array( 'timeout' => 15 ) );
		if ( is_wp_error( $r ) ) {
			self::back( 'خطای اتصال به تلگرام: ' . $r->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['ok'] ) ) {
			$desc = isset( $body['description'] ) ? $body['description'] : 'unknown';
			self::back( 'تلگرام خطا داد: ' . $desc . ( false !== stripos( $desc, 'webhook' ) ? ' (این ربات webhook فعال دارد؛ Chat ID را دستی وارد کنید)' : '' ) );
		}
		$chats = array();
		foreach ( (array) $body['result'] as $u ) {
			foreach ( array( 'message', 'channel_post', 'my_chat_member', 'edited_message' ) as $k ) {
				if ( isset( $u[ $k ]['chat']['id'] ) ) {
					$c                     = $u[ $k ]['chat'];
					$title                 = isset( $c['title'] ) ? $c['title'] : trim( ( isset( $c['first_name'] ) ? $c['first_name'] : '' ) . ' ' . ( isset( $c['username'] ) ? '@' . $c['username'] : '' ) );
					$chats[ (string) $c['id'] ] = array( 'id' => (string) $c['id'], 'type' => isset( $c['type'] ) ? $c['type'] : '', 'title' => $title );
				}
			}
		}
		set_transient( 'mefta_tg_found_chats', array_values( $chats ), 10 * MINUTE_IN_SECONDS );
		self::back( $chats ? count( $chats ) . ' چت پیدا شد.' : 'چتی پیدا نشد. اول به ربات /start بدهید و دوباره امتحان کنید.' );
	}

	/* ------------------------------------------------------------------ *
	 * Event handlers
	 * ------------------------------------------------------------------ */

	public static function on_invoice_request() {
		$s = self::settings();
		if ( '1' !== $s['notify_orders'] || ! self::allow_rate() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only logging; the main handler verifies the nonce.
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$product = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';
		$amount  = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		$network = isset( $_POST['network'] ) ? sanitize_key( wp_unslash( $_POST['network'] ) ) : '';
		// phpcs:enable

		$msg  = "🛒 <b>درخواست فاکتور جدید</b>\n";
		$msg .= '📦 محصول: ' . esc_html( mb_substr( $product, 0, 80 ) ) . "\n";
		$msg .= '💵 مبلغ: <code>' . number_format( $amount, 2 ) . "</code> USDT\n";
		$msg .= '🔗 شبکه: ' . esc_html( strtoupper( $network ) ) . "\n";
		$msg .= '📧 ایمیل: ' . esc_html( self::mask_email( $email ) ) . "\n";
		$msg .= '🕒 ' . esc_html( current_time( 'Y-m-d H:i' ) );
		self::send( $msg );
	}

	public static function on_order_paid( $order_id = '', $amount = 0, $network = '', $email = '' ) {
		$s = self::settings();
		if ( '1' !== $s['notify_paid'] ) {
			return;
		}
		// Rich card with action buttons when the order exists in Meftacard Direct Pay.
		$oid = preg_replace( '/[^A-Z0-9-]/', '', strtoupper( strtok( (string) $order_id, ' ' ) ) );
		if ( $oid && post_type_exists( 'mefta_pay_order' ) ) {
			$ids = get_posts( array( 'post_type' => 'mefta_pay_order', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_dp_order_id', 'value' => $oid ) ) ) );
			if ( $ids && '' !== $s['chat_id'] ) {
				Mefta_TG_Bot::notify_customer( (int) $ids[0], 'n_paid' );
				list( $txt, $kb ) = Mefta_TG_Bot::admin_card( (int) $ids[0] );
				Mefta_TG_Bot::api( 'sendMessage', array( 'chat_id' => $s['chat_id'], 'text' => "💰 <b>پرداخت تأیید شد</b>\n\n" . $txt, 'parse_mode' => 'HTML', 'reply_markup' => $kb ) );
				return;
			}
		}
		$msg  = "💰 <b>پرداخت تأیید شد</b>\n";
		$msg .= '🧾 سفارش: <code>' . esc_html( (string) $order_id ) . "</code>\n";
		$msg .= '💵 مبلغ: <code>' . number_format( (float) $amount, 2 ) . "</code> USDT\n";
		$msg .= '🔗 شبکه: ' . esc_html( strtoupper( (string) $network ) ) . "\n";
		$msg .= '📧 ایمیل: ' . esc_html( self::mask_email( (string) $email ) );
		self::send( $msg );
	}

	public static function on_mail_failed( $wp_error ) {
		$s = self::settings();
		if ( '1' !== $s['notify_mail'] || ! self::allow_rate( 'mail' ) ) {
			return;
		}
		$err = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : 'unknown';
		self::send( "⚠️ <b>خطای ارسال ایمیل</b>\n<code>" . esc_html( mb_substr( $err, 0, 400 ) ) . "</code>\nتنظیمات WP Mail SMTP را بررسی کنید." );
	}

	/** Public helper: do_action( 'mefta_telegram_notify', 'text' ); */
	public static function notify( $text ) {
		self::send( esc_html( (string) $text ) );
	}

	/* ------------------------------------------------------------------ *
	 * Telegram transport
	 * ------------------------------------------------------------------ */

	/**
	 * @return true|string true on success, error message otherwise.
	 */
	public static function send( $html, $blocking = false ) {
		$s = self::settings();
		if ( '' === $s['token'] || '' === $s['chat_id'] ) {
			return 'توکن یا Chat ID تنظیم نشده است.';
		}
		$r = wp_remote_post(
			'https://api.telegram.org/bot' . $s['token'] . '/sendMessage',
			array(
				'timeout'  => $blocking ? 15 : 3,
				'blocking' => $blocking, // never slow down checkout
				'body'     => array(
					'chat_id'                  => $s['chat_id'],
					'text'                     => mb_substr( $html, 0, 4000 ),
					'parse_mode'               => 'HTML',
					'disable_web_page_preview' => 'true',
				),
			)
		);
		if ( ! $blocking ) {
			return true;
		}
		if ( is_wp_error( $r ) ) {
			return $r->get_error_message();
		}
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		return ! empty( $body['ok'] ) ? true : ( isset( $body['description'] ) ? $body['description'] : 'HTTP ' . wp_remote_retrieve_response_code( $r ) );
	}

	private static function allow_rate( $bucket = 'ip' ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'mefta_tg_rl_' . md5( $bucket . '|' . ( 'ip' === $bucket ? $ip : 'all' ) );
		$n   = (int) get_transient( $key );
		if ( $n >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function mask_email( $email ) {
		if ( ! is_email( $email ) ) {
			return '—';
		}
		list( $user, $domain ) = explode( '@', $email, 2 );
		return mb_substr( $user, 0, 2 ) . str_repeat( '•', max( 1, mb_strlen( $user ) - 2 ) ) . '@' . $domain;
	}
}

Mefta_Telegram_Notifier::init();
