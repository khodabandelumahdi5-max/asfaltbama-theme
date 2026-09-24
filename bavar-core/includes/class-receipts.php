<?php
/**
 * Receipt upload for card-to-card orders, stored outside public reach.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Receipts {

	const MAX_BYTES = 5242880; // 5 MB.

	/**
	 * Hooks.
	 */
	public static function init() {
		require_once BAVAR_CORE_DIR . 'includes/class-gateway-card.php';
		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'Bavar_Gateway_Card';
				return $gateways;
			}
		);

		add_action( 'woocommerce_thankyou_bavar_card', [ __CLASS__, 'box' ] );
		add_action( 'woocommerce_view_order', [ __CLASS__, 'box' ], 5 );
		add_action( 'admin_post_bavar_receipt', [ __CLASS__, 'upload' ] );
		add_action( 'admin_post_nopriv_bavar_receipt', [ __CLASS__, 'upload' ] );
		add_action( 'admin_post_bavar_view_receipt', [ __CLASS__, 'view' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'admin_box' ] );
		add_action( 'woocommerce_email_before_order_table', [ __CLASS__, 'email_link' ], 10, 3 );
	}

	/**
	 * Private upload directory.
	 *
	 * @return string
	 */
	public static function dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'bavar-receipts';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/**
	 * Card details + upload form (thank-you page and "view order").
	 *
	 * @param int $order_id Order ID.
	 */
	public static function box( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'bavar_card' !== $order->get_payment_method() ) {
			return;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gw       = $gateways['bavar_card'] ?? null;
		$has      = (bool) $order->get_meta( '_bavar_receipt' );
		$done     = $order->has_status( [ 'processing', 'completed' ] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flash = sanitize_key( wp_unslash( $_GET['receipt'] ?? '' ) );
		?>
		<section class="bv-receipt">
			<p class="bv-receipt__mark" dir="ltr">PAYMENT</p>
			<?php if ( $done ) : ?>
				<h3>پرداخت شما تأیید شد</h3>
				<p>دسترسی شما فعال است. <a href="<?php echo esc_url( wc_get_account_endpoint_url( Bavar_Account::ENDPOINT ) ); ?>">ورود به کتابخانه من</a></p>
			<?php else : ?>
				<h3>مبلغ <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?> را به کارت زیر واریز کنید</h3>
				<?php if ( $gw ) : ?>
					<dl class="bv-receipt__card">
						<?php if ( $gw->get_option( 'card_number' ) ) : ?>
							<div><dt>شماره کارت</dt><dd dir="ltr"><?php echo esc_html( $gw->get_option( 'card_number' ) ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $gw->get_option( 'card_holder' ) ) : ?>
							<div><dt>به نام</dt><dd><?php echo esc_html( $gw->get_option( 'card_holder' ) ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $gw->get_option( 'bank' ) ) : ?>
							<div><dt>بانک</dt><dd><?php echo esc_html( $gw->get_option( 'bank' ) ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $gw->get_option( 'sheba' ) ) : ?>
							<div><dt>شبا</dt><dd dir="ltr"><?php echo esc_html( $gw->get_option( 'sheba' ) ); ?></dd></div>
						<?php endif; ?>
					</dl>
				<?php endif; ?>

				<?php if ( 'ok' === $flash ) : ?>
					<p class="bv-receipt__ok">فیش شما دریافت شد. پس از بررسی، دسترسی شما فعال می‌شود.</p>
				<?php elseif ( $flash ) : ?>
					<p class="bv-receipt__err"><?php echo esc_html( self::error_text( $flash ) ); ?></p>
				<?php elseif ( $has ) : ?>
					<p class="bv-receipt__ok">فیش شما قبلاً ارسال شده و در حال بررسی است. در صورت نیاز می‌توانید فیش جدید ارسال کنید.</p>
				<?php endif; ?>

				<form class="bv-receipt__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bavar_receipt">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
					<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
					<?php wp_nonce_field( 'bavar_receipt_' . $order->get_id(), 'bavar_receipt_nonce' ); ?>
					<label class="bv-field">
						<span>تصویر یا PDF فیش واریزی (حداکثر ۵ مگابایت)</span>
						<input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
					</label>
					<button type="submit" class="bv-button bv-button--solid">ارسال فیش</button>
				</form>
				<p class="bv-receipt__link">لینک اختصاصی ارسال فیش این سفارش: <a href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>" dir="ltr">مشاهده</a></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Error messages.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function error_text( $code ) {
		$map = [
			'size' => 'حجم فایل بیش از ۵ مگابایت است.',
			'type' => 'فقط تصویر (JPG, PNG, WEBP) یا PDF قابل ارسال است.',
			'none' => 'فایلی انتخاب نشده است.',
		];
		return $map[ $code ] ?? 'ارسال فیش ناموفق بود. دوباره تلاش کنید.';
	}

	/**
	 * Handle the upload.
	 */
	public static function upload() {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );
		$key      = sanitize_text_field( wp_unslash( $_POST['order_key'] ?? '' ) );
		$nonce    = sanitize_key( $_POST['bavar_receipt_nonce'] ?? '' );

		// The order key proves ownership for guests; the nonce blocks CSRF.
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || ! wp_verify_nonce( $nonce, 'bavar_receipt_' . $order_id ) || 'bavar_card' !== $order->get_payment_method() ) {
			wp_die( 'درخواست نامعتبر است.', '', [ 'response' => 403 ] );
		}

		$back = $order->get_checkout_order_received_url();
		$fail = function ( $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'receipt', $code, $back ) );
			exit;
		};

		if ( empty( $_FILES['receipt']['tmp_name'] ) || ! is_uploaded_file( $_FILES['receipt']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$fail( 'none' );
		}
		$file = $_FILES['receipt']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			$fail( 'size' );
		}
		$allowed = [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
		];
		$check = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			$fail( 'type' );
		}

		$name = 'order-' . $order_id . '-' . wp_generate_password( 20, false ) . '.' . $check['ext'];
		if ( ! move_uploaded_file( $file['tmp_name'], self::dir() . '/' . $name ) ) {
			$fail( 'error' );
		}

		$order->update_meta_data( '_bavar_receipt', $name );
		$order->update_meta_data( '_bavar_receipt_at', current_time( 'mysql' ) );
		if ( $order->has_status( 'pending' ) ) {
			$order->set_status( 'on-hold' );
		}
		$order->add_order_note( 'خریدار فیش پرداخت را ارسال کرد. پس از بررسی، وضعیت را «تکمیل‌شده» کنید تا دسترسی فعال شود.' );
		$order->save();

		wp_mail(
			get_option( 'admin_email' ),
			'فیش جدید — سفارش #' . $order->get_order_number(),
			'فیش پرداخت سفارش #' . $order->get_order_number() . ' ارسال شد.' . "\n" . $order->get_edit_order_url()
		);

		wp_safe_redirect( add_query_arg( 'receipt', 'ok', $back ) );
		exit;
	}

	/**
	 * Stream a receipt to an admin.
	 */
	public static function view() {
		$order_id = absint( $_GET['order_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'edit_shop_orders' ) || ! check_admin_referer( 'bavar_view_receipt_' . $order_id ) ) {
			wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
		}
		$order = wc_get_order( $order_id );
		$name  = $order ? basename( (string) $order->get_meta( '_bavar_receipt' ) ) : '';
		$path  = self::dir() . '/' . $name;
		if ( ! $name || ! is_file( $path ) ) {
			wp_die( 'فیش پیدا نشد.' );
		}
		$type = wp_check_filetype( $path );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Receipt box on the admin order screen (HPOS and legacy).
	 */
	public static function admin_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box(
			'bavar_receipt',
			'فیش پرداخت',
			function ( $post_or_order ) {
				$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
				if ( ! $order || 'bavar_card' !== $order->get_payment_method() ) {
					echo '<p>این سفارش با کارت‌به‌کارت پرداخت نشده است.</p>';
					return;
				}
				if ( ! $order->get_meta( '_bavar_receipt' ) ) {
					echo '<p>هنوز فیشی ارسال نشده است.</p>';
					return;
				}
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=bavar_view_receipt&order_id=' . $order->get_id() ), 'bavar_view_receipt_' . $order->get_id() );
				printf( '<p><a class="button button-primary" target="_blank" href="%s">مشاهده‌ی فیش</a></p>', esc_url( $url ) );
				printf( '<p>ارسال: %s</p>', esc_html( $order->get_meta( '_bavar_receipt_at' ) ) );
				echo '<p class="description">پس از تأیید، وضعیت سفارش را «تکمیل‌شده» کنید تا دسترسی خریدار فعال شود.</p>';
			},
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Receipt link in the on-hold email.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Admin email.
	 * @param bool     $plain_text    Plain text.
	 */
	public static function email_link( $order, $sent_to_admin, $plain_text ) {
		if ( $sent_to_admin || 'bavar_card' !== $order->get_payment_method() || ! $order->has_status( 'on-hold' ) ) {
			return;
		}
		$url = $order->get_checkout_order_received_url();
		if ( $plain_text ) {
			echo "لینک ارسال فیش: {$url}\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<p><a href="' . esc_url( $url ) . '">ارسال فیش پرداخت</a></p>';
		}
	}
}
