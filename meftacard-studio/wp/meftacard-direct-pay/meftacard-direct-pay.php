<?php
/**
 * Plugin Name: Meftacard Direct Pay (USDT BEP20)
 * Description: پرداخت مستقیم USDT روی شبکه BEP20 به کیف پول شما، بدون درگاه واسط. هر سفارش مبلغ یکتا دارد و پرداخت به‌صورت خودکار روی بلاکچین BSC تأیید می‌شود.
 * Version:     1.3.1
 * Author:      MEFTACARD
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mefta_Direct_Pay {

	const OPT        = 'mefta_dp_settings';
	const CPT        = 'mefta_pay_order';
	const CURSOR     = 'mefta_dp_cursor';
	const UNMATCHED  = 'mefta_dp_unmatched';
	const USDT       = '0x55d398326f99059fF775485246999027B3197955'; // BSC-USD (18 decimals)
	const TOPIC      = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef'; // Transfer
	const CONFIRMS   = 3;
	const CHUNK      = 5000;
	const GRACE      = 1800; // keep watching 30 min after invoice expiry
	const TOLERANCE  = 2000;  // accept up to 0.20 USDT less (exchange withdrawal fees), units of 0.0001
	const OVERPAY    = 10000; // accept up to 1 USDT more

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );

		// Take over invoice creation (runs before the original OxaPay handler, which never runs when enabled).
		add_action( 'wp_ajax_nopriv_mefta_create_invoice', array( __CLASS__, 'ajax_create_invoice' ), -10 );
		add_action( 'wp_ajax_mefta_create_invoice', array( __CLASS__, 'ajax_create_invoice' ), -10 );

		// Real email verification (replaces the original demo OTP).
		add_action( 'wp_ajax_nopriv_mefta_send_otp', array( __CLASS__, 'ajax_send_otp' ), -10 );
		add_action( 'wp_ajax_mefta_send_otp', array( __CLASS__, 'ajax_send_otp' ), -10 );
		add_action( 'wp_ajax_nopriv_mefta_verify_otp', array( __CLASS__, 'ajax_verify_otp' ), -10 );
		add_action( 'wp_ajax_mefta_verify_otp', array( __CLASS__, 'ajax_verify_otp' ), -10 );

		// The homepage template prints its own <script> tags (no wp_head/enqueue), so rewrite its HTML output.
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), -9999 );

		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'wp_head', array( __CLASS__, 'front_config' ), 1 );
		add_filter( 'script_loader_src', array( __CLASS__, 'swap_app_js' ), 20, 2 );

		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'mefta_dp_cron', array( __CLASS__, 'scan' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'mefta_dp_cron' ) ) {
			wp_schedule_event( time() + 60, 'mefta_dp_2min', 'mefta_dp_cron' );
		}
	}

	/* ------------------------------------------------------------ settings */

	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPT, array() ),
			array(
				'enabled'      => '1',
				'wallet'       => '0xA016E9BbEeDEb1182a68D4c6BD7FA589C7D40d8d',
				'minutes'      => 30,
				'min_amount'   => 0.5,
				'rpc'          => 'https://bsc-rpc.publicnode.com',
				'notify_email' => get_option( 'admin_email' ),
			)
		);
	}

	public static function register_settings() {
		register_setting( 'mefta_dp_group', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$old = self::settings();
		$out = array();
		$out['enabled'] = empty( $in['enabled'] ) ? '0' : '1';
		$w = isset( $in['wallet'] ) ? trim( (string) $in['wallet'] ) : '';
		if ( preg_match( '/^0x[0-9a-fA-F]{40}$/', $w ) ) {
			$out['wallet'] = $w;
		} else {
			add_settings_error( self::OPT, 'wallet', 'آدرس کیف پول BEP20 نامعتبر است (باید با 0x شروع شود و ۴۲ کاراکتر باشد).' );
			$out['wallet'] = $old['wallet'];
		}
		$out['minutes']      = max( 10, min( 180, (int) ( $in['minutes'] ?? 30 ) ) );
		$out['min_amount']   = max( 0.1, (float) ( $in['min_amount'] ?? 0.5 ) );
		$rpc                 = esc_url_raw( trim( (string) ( $in['rpc'] ?? '' ) ) );
		$out['rpc']          = ( $rpc && 0 === strpos( $rpc, 'https://' ) ) ? $rpc : 'https://bsc-rpc.publicnode.com';
		$out['notify_email'] = is_email( $in['notify_email'] ?? '' ) ? sanitize_email( $in['notify_email'] ) : $old['notify_email'];
		return $out;
	}

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::CPT, 'تنظیمات پرداخت مستقیم', 'تنظیمات', 'manage_options', 'mefta-direct-pay', array( __CLASS__, 'render_settings' ) );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::settings();
		$n = self::OPT;
		$test = null;
		if ( isset( $_POST['mefta_dp_test'] ) && check_admin_referer( 'mefta_dp_test' ) ) {
			$test = self::rpc( 'eth_blockNumber', array() );
		}
		$unmatched = array_slice( array_reverse( (array) get_option( self::UNMATCHED, array() ) ), 0, 20 );
		?>
		<div class="wrap" dir="rtl" style="max-width:820px">
			<h1>پرداخت مستقیم USDT (BEP20)</h1>
			<?php settings_errors( self::OPT ); ?>
			<p>مشتری مبلغ یکتای هر سفارش را مستقیم به کیف پول شما می‌فرستد و افزونه هر چند ثانیه بلاکچین BSC را بررسی می‌کند. هیچ درگاه یا واسطی در کار نیست.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'mefta_dp_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th>فعال</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?>> پرداخت مستقیم به‌جای OxaPay استفاده شود</label></td></tr>
					<tr><th><label for="dpw">آدرس کیف پول BEP20</label></th><td><input id="dpw" type="text" dir="ltr" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[wallet]" value="<?php echo esc_attr( $s['wallet'] ); ?>"><p class="description">فقط کیف پولی که کلید خصوصی‌اش دست شماست (Trust Wallet، MetaMask، SafePal). آدرس واریز صرافی استفاده نکنید.</p></td></tr>
					<tr><th><label for="dpm">مهلت پرداخت (دقیقه)</label></th><td><input id="dpm" type="number" min="10" max="180" name="<?php echo esc_attr( $n ); ?>[minutes]" value="<?php echo esc_attr( $s['minutes'] ); ?>"></td></tr>
					<tr><th><label for="dpa">حداقل مبلغ سفارش (USDT)</label></th><td><input id="dpa" type="number" step="0.01" min="0.1" name="<?php echo esc_attr( $n ); ?>[min_amount]" value="<?php echo esc_attr( $s['min_amount'] ); ?>"></td></tr>
					<tr><th><label for="dpe">ایمیل اطلاع‌رسانی سفارش</label></th><td><input id="dpe" type="email" dir="ltr" class="regular-text" name="<?php echo esc_attr( $n ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>"></td></tr>
					<tr><th><label for="dpr">آدرس RPC شبکه BSC</label></th><td><input id="dpr" type="url" dir="ltr" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[rpc]" value="<?php echo esc_attr( $s['rpc'] ); ?>"><p class="description">پیش‌فرض رایگان است و نیازی به تغییر ندارد.</p></td></tr>
				</table>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
			<form method="post"><?php wp_nonce_field( 'mefta_dp_test' ); ?><input type="hidden" name="mefta_dp_test" value="1"><?php submit_button( 'تست اتصال به بلاکچین', 'secondary', 'submit', false ); ?></form>
			<?php if ( null !== $test ) : ?>
				<div class="notice <?php echo is_wp_error( $test ) ? 'notice-error' : 'notice-success'; ?>"><p>
					<?php echo is_wp_error( $test ) ? esc_html( 'خطا: ' . $test->get_error_message() ) : esc_html( 'اتصال برقرار است. آخرین بلاک: ' . hexdec( substr( (string) $test, 2 ) ) ); ?>
				</p></div>
			<?php endif; ?>
			<h2>واریزهای بدون سفارش</h2>
			<p class="description">واریزهایی که به کیف پول شما رسیده ولی با هیچ سفارشی جور نشده‌اند (مثلاً مبلغ اشتباه). این‌ها را دستی بررسی کنید.</p>
			<?php if ( $unmatched ) : ?>
				<table class="widefat striped"><thead><tr><th>زمان</th><th>مبلغ (USDT)</th><th>تراکنش</th></tr></thead><tbody>
				<?php foreach ( $unmatched as $u ) : ?>
					<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $u['t'] ) ); ?></td><td dir="ltr"><?php echo esc_html( $u['amount'] ); ?></td><td dir="ltr"><a href="<?php echo esc_url( 'https://bscscan.com/tx/' . $u['tx'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( substr( $u['tx'], 0, 18 ) . '…' ); ?></a></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php else : ?>
				<p>موردی نیست.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ orders (CPT) */

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array( 'name' => 'سفارش‌های USDT', 'singular_name' => 'سفارش USDT', 'menu_name' => 'سفارش‌های USDT', 'edit_item' => 'جزئیات سفارش' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-money-alt',
				'menu_position'   => 56,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	public static function columns( $cols ) {
		return array(
			'cb'        => $cols['cb'],
			'title'     => 'شماره سفارش',
			'dp_email'  => 'ایمیل',
			'dp_prod'   => 'محصول',
			'dp_amount' => 'مبلغ (USDT)',
			'dp_status' => 'وضعیت',
			'date'      => $cols['date'],
		);
	}

	public static function column_value( $col, $id ) {
		$m = self::meta( $id );
		switch ( $col ) {
			case 'dp_email':
				echo esc_html( $m['email'] );
				break;
			case 'dp_prod':
				echo esc_html( $m['product'] );
				break;
			case 'dp_amount':
				echo '<span dir="ltr">' . esc_html( $m['pay_amount'] ) . '</span>';
				break;
			case 'dp_status':
				$labels = array( 'pending' => '⏳ در انتظار', 'paid' => '✅ پرداخت شد', 'delivered' => '📦 تحویل شد', 'expired' => '⌛ منقضی', 'rejected' => '❌ رد شد' );
				echo esc_html( $labels[ $m['status'] ] ?? $m['status'] );
				if ( 'paid' === $m['status'] && 'tolerance' === $m['match'] ) {
					echo ' <small style="color:#b45309">(مبلغ نزدیک)</small>';
				}
				break;
		}
	}

	public static function meta_box() {
		add_meta_box( 'mefta_dp_details', 'جزئیات پرداخت', array( __CLASS__, 'render_meta_box' ), self::CPT, 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		$m = self::meta( $post->ID );
		wp_nonce_field( 'mefta_dp_status', 'mefta_dp_nonce' );
		$rows = array(
			'ایمیل مشتری'   => $m['email'],
			'محصول'          => $m['product'],
			'مبلغ پایه'      => $m['base_amount'] . ' USDT',
			'مبلغ قابل پرداخت' => $m['pay_amount'] . ' USDT',
			'مهلت'           => $m['expires'] ? wp_date( 'Y-m-d H:i', $m['expires'] ) : '',
			'مبلغ دریافتی'   => $m['paid_amount'] ? $m['paid_amount'] . ' USDT' : '—',
		);
		echo '<table class="form-table" dir="rtl">';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td dir="auto">' . esc_html( $v ) . '</td></tr>';
		}
		if ( $m['tx'] ) {
			echo '<tr><th>تراکنش</th><td dir="ltr"><a target="_blank" rel="noopener" href="' . esc_url( 'https://bscscan.com/tx/' . $m['tx'] ) . '">' . esc_html( $m['tx'] ) . '</a></td></tr>';
		}
		$delivery = (string) get_post_meta( $post->ID, '_dp_delivery', true );
		if ( '' !== $delivery ) {
			echo '<tr><th>محصول ارسال‌شده</th><td><pre dir="auto" style="white-space:pre-wrap;background:#f6f7f7;padding:8px">' . esc_html( $delivery ) . '</pre></td></tr>';
		}
		echo '<tr><th>وضعیت</th><td><select name="mefta_dp_status">';
		foreach ( array( 'pending' => 'در انتظار', 'paid' => 'پرداخت شد', 'delivered' => 'تحویل شد', 'expired' => 'منقضی', 'rejected' => 'رد شد' ) as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $m['status'], $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select><p class="description">تغییر دستی به «پرداخت شد» برای مشتری ایمیل تأیید می‌فرستد.</p></td></tr></table>';
	}

	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['mefta_dp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mefta_dp_nonce'] ) ), 'mefta_dp_status' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['mefta_dp_status'] ) ) {
			return;
		}
		$new = sanitize_key( wp_unslash( $_POST['mefta_dp_status'] ) );
		$old = get_post_meta( $post_id, '_dp_status', true );
		if ( ! in_array( $new, array( 'pending', 'paid', 'delivered', 'expired', 'rejected' ), true ) || $new === $old ) {
			return;
		}
		if ( 'paid' === $new && in_array( $old, array( 'pending', 'expired', 'rejected' ), true ) ) {
			self::mark_paid( $post_id, '', '', 'manual' );
		} else {
			update_post_meta( $post_id, '_dp_status', $new );
		}
	}

	private static function meta( $id ) {
		$g = function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, '_dp_' . $k, true );
		};
		return array(
			'email'       => $g( 'email' ),
			'product'     => $g( 'product' ),
			'base_amount' => $g( 'base_amount' ),
			'pay_amount'  => $g( 'pay_amount' ),
			'units'       => (int) $g( 'units' ),
			'status'      => $g( 'status' ),
			'expires'     => (int) $g( 'expires' ),
			'tx'          => $g( 'tx' ),
			'paid_amount' => $g( 'paid_amount' ),
			'match'       => $g( 'match' ),
		);
	}

	/* ------------------------------------------------------------ invoice */

	public static function ajax_create_invoice() {
		$s = self::settings();
		if ( '1' !== $s['enabled'] ) {
			return; // let the original OxaPay handler run
		}
		if ( ! self::rate_ok( 'inv', 40 ) ) {
			wp_send_json_error( array( 'message' => 'Too many requests. Please wait a few minutes and try again.' ), 429 );
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$product = isset( $_POST['product'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['product'] ) ), 0, 120 ) : 'Order';
		$amount  = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		// phpcs:enable
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}
		if ( $amount < (float) $s['min_amount'] || $amount > 10000 ) {
			wp_send_json_error( array( 'message' => 'Invalid order amount.' ) );
		}
		if ( ! get_transient( 'mefta_dp_ver_' . md5( strtolower( $email ) ) ) ) {
			wp_send_json_error( array( 'message' => 'Please verify your email address first.' ) );
		}

		// Reuse an open invoice for the same email + product + amount (avoids duplicates on network re-clicks).
		$existing = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_query'  => array(
					array( 'key' => '_dp_status', 'value' => 'pending' ),
					array( 'key' => '_dp_email', 'value' => $email ),
					array( 'key' => '_dp_product', 'value' => $product ),
					array( 'key' => '_dp_base_amount', 'value' => number_format( $amount, 2, '.', '' ) ),
					array( 'key' => '_dp_expires', 'value' => time() + 300, 'compare' => '>', 'type' => 'NUMERIC' ),
				),
			)
		);
		if ( $existing ) {
			wp_send_json_success( self::invoice_payload( $existing[0]->ID ) );
		}

		$base_units = (int) round( $amount * 100 ) * 100;
		$taken      = self::pending_units();
		$units      = 0;
		for ( $i = 0; $i < 200; $i++ ) {
			$try = $base_units + wp_rand( 11, 999 );
			if ( ! isset( $taken[ $try ] ) ) {
				$units = $try;
				break;
			}
		}
		if ( ! $units ) {
			wp_send_json_error( array( 'message' => 'Checkout is busy. Please try again in a minute.' ) );
		}

		// No look-alike characters (0/O, 1/I/L) so customers can type the ID reliably; guaranteed unique.
		$alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		do {
			$order_id = 'MC-';
			for ( $i = 0; $i < 8; $i++ ) {
				$order_id .= $alpha[ wp_rand( 0, strlen( $alpha ) - 1 ) ];
			}
			$dupe = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_dp_order_id', 'value' => $order_id ) ) ) );
		} while ( $dupe );
		$post_id  = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $order_id,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Could not create the invoice. Please try again.' ) );
		}
		$expires = time() + 60 * (int) $s['minutes'];
		$meta    = array(
			'order_id'    => $order_id,
			'email'       => $email,
			'product'     => $product,
			'base_amount' => number_format( $amount, 2, '.', '' ),
			'pay_amount'  => number_format( $units / 10000, 4, '.', '' ),
			'units'       => $units,
			'wallet'      => $s['wallet'],
			'status'      => 'pending',
			'expires'     => $expires,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $post_id, '_dp_' . $k, $v );
		}
		// Make sure the scan cursor starts before this order.
		if ( ! get_option( self::CURSOR ) ) {
			$bn = self::block_number();
			if ( $bn ) {
				update_option( self::CURSOR, max( 0, $bn - 50 ), false );
			}
		}
		do_action( 'mefta_telegram_notify', "🧾 فاکتور مستقیم {$order_id}\n📦 {$product}\n💵 {$meta['pay_amount']} USDT (BEP20)" );
		wp_send_json_success( self::invoice_payload( $post_id ) );
	}

	private static function invoice_payload( $post_id ) {
		$m  = self::meta( $post_id );
		$w  = get_post_meta( $post_id, '_dp_wallet', true );
		$id = get_post_meta( $post_id, '_dp_order_id', true );
		return array(
			'order_id'     => $id,
			'track_id'     => '',
			'pay_amount'   => $m['pay_amount'],
			'address'      => $w,
			'network'      => 'BEP20',
			'status'       => 'Waiting',
			'expired_at'   => $m['expires'],
			'gateway_mode' => 'direct',
			'qr_code'      => 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . rawurlencode( $w ),
			'message'      => sprintf( 'Send exactly %s USDT on BEP20 (BSC). If you pay from an exchange, make sure the received amount is exact (add the withdrawal fee).', $m['pay_amount'] ),
		);
	}

	private static function pending_units() {
		$ids = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => 500,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_dp_status', 'value' => 'pending' ) ),
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) get_post_meta( $id, '_dp_units', true ) ] = $id;
		}
		return $out;
	}

	private static function rate_ok( $bucket, $limit ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'mefta_dp_rl_' . $bucket . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	/* ------------------------------------------------------------ email OTP */

	public static function ajax_send_otp() {
		if ( '1' !== self::settings()['enabled'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}
		$ek  = md5( strtolower( $email ) );
		$cnt = (int) get_transient( 'mefta_dp_otpn_' . $ek );
		if ( $cnt >= 5 || ! self::rate_ok( 'otp', 20 ) ) {
			wp_send_json_error( array( 'message' => 'Too many verification requests. Please wait and try again later.' ) );
		}
		$code = (string) wp_rand( 100000, 999999 );
		set_transient( 'mefta_dp_otp_' . $ek, array( 'h' => hash_hmac( 'sha256', $code, wp_salt() ), 'tries' => 0 ), 10 * MINUTE_IN_SECONDS );
		set_transient( 'mefta_dp_otpn_' . $ek, $cnt + 1, HOUR_IN_SECONDS );

		$body  = "Your Meftacard verification code is:\n\n    {$code}\n\n";
		$body .= "Enter this code on meftacard.com to continue your order. It expires in 10 minutes.\n\n";
		$body .= "If you did not request this code, you can ignore this email.\n\n— MEFTACARD\nhttps://meftacard.com\n";
		if ( ! wp_mail( $email, "{$code} is your Meftacard verification code", $body ) ) {
			wp_send_json_error( array( 'message' => 'We could not send the email right now. Please try again or contact support@meftacard.com.' ) );
		}
		wp_send_json_success(
			array(
				'mode'    => 'email',
				'message' => "We sent a 6-digit code to {$email}. Check your inbox and spam folder.",
			)
		);
	}

	public static function ajax_verify_otp() {
		if ( '1' !== self::settings()['enabled'] ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ) ) : '';
		// phpcs:enable
		$ek  = md5( strtolower( $email ) );
		$rec = get_transient( 'mefta_dp_otp_' . $ek );
		if ( ! is_array( $rec ) ) {
			wp_send_json_error( array( 'message' => 'The code has expired. Please request a new one.' ) );
		}
		if ( $rec['tries'] >= 5 ) {
			delete_transient( 'mefta_dp_otp_' . $ek );
			wp_send_json_error( array( 'message' => 'Too many wrong attempts. Please request a new code.' ) );
		}
		if ( ! hash_equals( $rec['h'], hash_hmac( 'sha256', $code, wp_salt() ) ) ) {
			$rec['tries']++;
			set_transient( 'mefta_dp_otp_' . $ek, $rec, 10 * MINUTE_IN_SECONDS );
			wp_send_json_error( array( 'message' => 'Invalid code. Please check the 6-digit code in your email.' ) );
		}
		delete_transient( 'mefta_dp_otp_' . $ek );
		set_transient( 'mefta_dp_ver_' . $ek, 1, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'message' => 'Email verified.' ) );
	}

	/* ------------------------------------------------------------ homepage HTML rewrite */

	public static function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || '1' !== self::settings()['enabled'] ) {
			return;
		}
		ob_start( array( __CLASS__, 'rewrite_html' ) );
	}

	public static function rewrite_html( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, 'meftacard-flagship-homepage/assets/app.js' ) ) {
			return $html;
		}
		$file = plugin_dir_path( __FILE__ ) . 'assets/app.js';
		$src  = add_query_arg( 'ver', '1.3.0-' . filemtime( $file ), plugins_url( 'assets/app.js', __FILE__ ) );
		$html = preg_replace( '#src="[^"]*meftacard-flagship-homepage/assets/app\.js[^"]*"#', 'src="' . esc_url( $src ) . '"', $html );
		// Only the real wallet, BEP20 only; never expose placeholder addresses.
		$wallet = self::settings()['wallet'];
		$html   = preg_replace( '#"wallets":\{[^}]*\}#', '"wallets":{"BEP20":"' . $wallet . '"}', $html );
		$html   = str_replace( '"otpMode":"demo"', '"otpMode":"email"', $html );
		if ( false === strpos( $html, 'window.MEFTA_DIRECT' ) ) {
			$cfg  = '<script>window.MEFTA_DIRECT=' . wp_json_encode( array( 'statusUrl' => rest_url( 'mefta-pay/v1/status' ) ) ) . ';</script>';
			$pos  = strpos( $html, '<script>window.MEFTACARD_CONFIG' );
			$html = false !== $pos ? substr_replace( $html, $cfg . "\n", $pos, 0 ) : str_replace( '</head>', $cfg . '</head>', $html );
		}
		return $html;
	}

	/* ------------------------------------------------------------ REST status */

	public static function rest() {
		register_rest_route(
			'mefta-pay/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'order_id' => array( 'required' => true, 'type' => 'string' ) ),
				'callback'            => array( __CLASS__, 'rest_status' ),
			)
		);
	}

	public static function rest_status( WP_REST_Request $req ) {
		$oid = strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $req->get_param( 'order_id' ) ) );
		$ids = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_dp_order_id', 'value' => $oid ) ),
			)
		);
		if ( ! $ids ) {
			return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
		}
		$id = $ids[0];
		if ( 'pending' === get_post_meta( $id, '_dp_status', true ) ) {
			self::scan();
		}
		$st  = get_post_meta( $id, '_dp_status', true );
		$map = array( 'pending' => 'Waiting', 'paid' => 'Paid', 'delivered' => 'Paid', 'expired' => 'Expired', 'rejected' => 'Cancelled' );
		$res = new WP_REST_Response( array( 'order_id' => $oid, 'status' => $map[ $st ] ?? 'Waiting', 'network' => 'BEP20' ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/* ------------------------------------------------------------ blockchain scan */

	public static function cron_schedules( $s ) {
		$s['mefta_dp_2min'] = array( 'interval' => 120, 'display' => 'Every 2 minutes (Meftacard Direct Pay)' );
		return $s;
	}

	private static function rpc( $method, $params ) {
		$r = wp_remote_post(
			self::settings()['rpc'],
			array(
				'timeout' => 12,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$b = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( isset( $b['error'] ) ) {
			return new WP_Error( 'rpc', is_array( $b['error'] ) ? ( $b['error']['message'] ?? 'rpc error' ) : 'rpc error' );
		}
		return $b['result'] ?? new WP_Error( 'rpc', 'empty response' );
	}

	private static function block_number() {
		$r = self::rpc( 'eth_blockNumber', array() );
		return is_wp_error( $r ) ? 0 : (int) hexdec( substr( (string) $r, 2 ) );
	}

	/** Scan new USDT transfers to the wallet and match them to pending orders. */
	public static function scan() {
		// Atomic lock (add_option fails if the row exists); stale locks expire after 60 s.
		if ( ! add_option( 'mefta_dp_lock', time(), '', false ) ) {
			if ( time() - (int) get_option( 'mefta_dp_lock' ) < 60 ) {
				return;
			}
			update_option( 'mefta_dp_lock', time(), false );
		}
		try {
			self::scan_inner();
		} finally {
			delete_option( 'mefta_dp_lock' );
		}
	}

	private static function scan_inner() {

		$pending = self::pending_units();
		// Expire old invoices.
		foreach ( $pending as $u => $id ) {
			if ( time() > (int) get_post_meta( $id, '_dp_expires', true ) + self::GRACE ) {
				update_post_meta( $id, '_dp_status', 'expired' );
				unset( $pending[ $u ] );
			}
		}
		$latest = self::block_number();
		if ( ! $latest ) {
			return;
		}
		$to     = $latest - self::CONFIRMS;
		$cursor = (int) get_option( self::CURSOR );
		if ( ! $pending ) {
			update_option( self::CURSOR, $to, false ); // nothing to watch: fast-forward
			return;
		}
		$from = $cursor ? $cursor + 1 : $to - 2000;
		$from = max( $from, $to - 4 * self::CHUNK ); // never scan more than ~5 hours back in one run
		$wallet_topic = '0x' . str_pad( strtolower( substr( self::settings()['wallet'], 2 ) ), 64, '0', STR_PAD_LEFT );

		while ( $from <= $to ) {
			$end  = min( $from + self::CHUNK - 1, $to );
			$logs = self::rpc(
				'eth_getLogs',
				array(
					array(
						'fromBlock' => '0x' . dechex( $from ),
						'toBlock'   => '0x' . dechex( $end ),
						'address'   => self::USDT,
						'topics'    => array( self::TOPIC, null, $wallet_topic ),
					),
				)
			);
			if ( is_wp_error( $logs ) ) {
				return; // keep cursor; retry next time
			}
			foreach ( (array) $logs as $log ) {
				self::match_log( $log, $pending );
			}
			update_option( self::CURSOR, $end, false );
			$from = $end + 1;
		}
	}

	private static function match_log( $log, &$pending ) {
		if ( ! empty( $log['removed'] ) ) {
			return; // chain reorganisation
		}
		$tx  = strtolower( (string) ( $log['transactionHash'] ?? '' ) );
		$key = $tx . ':' . hexdec( substr( (string) ( $log['logIndex'] ?? '0x0' ), 2 ) );
		if ( ! $tx || self::tx_used( $key ) ) {
			return;
		}
		$units = (int) round( hexdec( ltrim( substr( (string) $log['data'], 2 ), '0' ) ?: '0' ) / 1e14 );

		// 1) exact unique amount
		if ( isset( $pending[ $units ] ) ) {
			self::remember_tx( $key );
			self::mark_paid( $pending[ $units ], $tx, $units, 'exact' );
			unset( $pending[ $units ] );
			return;
		}
		// 2) near match (exchange fee deducted or rounded) — only if exactly one pending order fits
		$cands = array();
		foreach ( $pending as $u => $id ) {
			if ( $units >= $u - self::TOLERANCE && $units <= $u + self::OVERPAY ) {
				$cands[ $u ] = $id;
			}
		}
		if ( 1 === count( $cands ) ) {
			$u = array_key_first( $cands );
			self::remember_tx( $key );
			self::mark_paid( $cands[ $u ], $tx, $units, 'tolerance' );
			unset( $pending[ $u ] );
			return;
		}
		// 3) unmatched — keep for manual review
		$list   = (array) get_option( self::UNMATCHED, array() );
		$list[] = array( 'tx' => $tx, 'amount' => number_format( $units / 10000, 4, '.', '' ), 't' => time() );
		update_option( self::UNMATCHED, array_slice( $list, -100 ), false );
		self::remember_tx( $key );
		do_action( 'mefta_telegram_notify', '⚠️ واریز بدون سفارش: ' . number_format( $units / 10000, 4, '.', '' ) . " USDT\nhttps://bscscan.com/tx/" . $tx );
	}

	private static function tx_used( $tx ) {
		return in_array( $tx, (array) get_option( 'mefta_dp_used_tx', array() ), true );
	}

	private static function remember_tx( $tx ) {
		$used   = (array) get_option( 'mefta_dp_used_tx', array() );
		$used[] = $tx;
		update_option( 'mefta_dp_used_tx', array_slice( array_unique( $used ), -1000 ), false );
	}

	private static function mark_paid( $post_id, $tx, $units, $match ) {
		if ( in_array( get_post_meta( $post_id, '_dp_status', true ), array( 'paid', 'delivered' ), true ) ) {
			return;
		}
		update_post_meta( $post_id, '_dp_status', 'paid' );
		update_post_meta( $post_id, '_dp_match', $match );
		update_post_meta( $post_id, '_dp_paid_at', time() );
		if ( $tx ) {
			update_post_meta( $post_id, '_dp_tx', $tx );
		}
		if ( $units ) {
			update_post_meta( $post_id, '_dp_paid_amount', number_format( $units / 10000, 4, '.', '' ) );
		}
		$m   = self::meta( $post_id );
		$oid = get_post_meta( $post_id, '_dp_order_id', true );
		$paid = $m['paid_amount'] ?: $m['pay_amount'];

		// Customer email.
		$body  = "Hello,\n\nWe have received your payment for order {$oid}.\n\n";
		$body .= "Product: {$m['product']}\nAmount: {$paid} USDT (BEP20)\n";
		if ( $tx ) {
			$body .= "Transaction: https://bscscan.com/tx/{$tx}\n";
		}
		$body .= "\nYour order is being processed and will be delivered to this email address.\n";
		$body .= "Questions? Reply to this email or message us on Telegram: https://t.me/meftacard\n\n— MEFTACARD\nhttps://meftacard.com\n";
		wp_mail( $m['email'], "Payment received – Order {$oid}", $body );

		// Owner email.
		$s     = self::settings();
		$admin = "New paid order {$oid}\n\nCustomer: {$m['email']}\nProduct: {$m['product']}\nExpected: {$m['pay_amount']} USDT\nReceived: {$paid} USDT\nMatch: {$match}\n";
		if ( $tx ) {
			$admin .= "Tx: https://bscscan.com/tx/{$tx}\n";
		}
		$admin .= "\nOrder: " . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";
		wp_mail( $s['notify_email'], "💰 Paid order {$oid} – {$m['product']}", $admin );

		// Telegram (Meftacard Telegram Notifier plugin).
		do_action( 'mefta_order_paid', $oid . ( 'tolerance' === $match ? ' (مبلغ نزدیک)' : '' ), (float) $paid, 'BEP20', $m['email'] );
	}

	/* ------------------------------------------------------------ front-end */

	public static function front_config() {
		if ( '1' !== self::settings()['enabled'] ) {
			return;
		}
		echo '<script>window.MEFTA_DIRECT=' . wp_json_encode( array( 'statusUrl' => rest_url( 'mefta-pay/v1/status' ) ) ) . ';</script>' . "\n";
	}

	/** Serve the updated checkout script (BEP20-only, no fake fallbacks, direct-pay polling) instead of the original. */
	public static function swap_app_js( $src, $handle ) {
		if ( is_string( $src ) && false !== strpos( $src, 'meftacard-flagship-homepage/assets/app.js' ) ) {
			$file = plugin_dir_path( __FILE__ ) . 'assets/app.js';
			return add_query_arg( 'ver', '1.2.0-' . filemtime( $file ), plugins_url( 'assets/app.js', __FILE__ ) );
		}
		return $src;
	}
}

Mefta_Direct_Pay::init();

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'mefta_dp_cron' );
	}
);
