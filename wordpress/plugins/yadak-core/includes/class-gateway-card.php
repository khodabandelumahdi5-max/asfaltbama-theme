<?php
/**
 * Card-to-card (کارت‌به‌کارت) gateway: shows the store card, asks for the
 * transfer reference number, and holds the order until staff confirm it.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Gateway_Card extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'yadak_card';
		$this->method_title       = __( 'کارت‌به‌کارت', 'yadak-core' );
		$this->method_description = __( 'مشتری مبلغ را به کارت فروشگاه واریز و شماره پیگیری را وارد می‌کند؛ سفارش پس از بررسی شما تأیید می‌شود.', 'yadak-core' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_info' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'فعال', 'yadak-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی کارت‌به‌کارت', 'yadak-core' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'عنوان', 'yadak-core' ),
				'type'    => 'text',
				'default' => __( 'کارت‌به‌کارت', 'yadak-core' ),
			),
			'description' => array(
				'title'   => __( 'توضیح', 'yadak-core' ),
				'type'    => 'textarea',
				'default' => __( 'مبلغ سفارش را به کارت زیر واریز کنید و شماره پیگیری را وارد کنید. سفارش پس از تأیید واریز ارسال می‌شود.', 'yadak-core' ),
			),
			'card_number' => array(
				'title' => __( 'شماره کارت', 'yadak-core' ),
				'type'  => 'text',
			),
			'card_holder' => array(
				'title' => __( 'نام صاحب کارت', 'yadak-core' ),
				'type'  => 'text',
			),
			'bank'        => array(
				'title' => __( 'بانک', 'yadak-core' ),
				'type'  => 'text',
			),
		);
	}

	public function payment_fields() {
		echo wp_kses_post( wpautop( $this->get_description() ) );
		$card = preg_replace( '/\D/', '', yadak_normalize_digits( $this->get_option( 'card_number' ) ) );
		?>
		<div class="yadak-card-box">
			<?php if ( $card ) : ?>
				<p class="yadak-card-box__number" dir="ltr"><?php echo esc_html( trim( chunk_split( $card, 4, ' ' ) ) ); ?></p>
			<?php endif; ?>
			<p><?php echo esc_html( trim( $this->get_option( 'card_holder' ) . ' — ' . $this->get_option( 'bank' ), ' —' ) ); ?></p>
		</div>
		<p class="form-row form-row-wide">
			<label for="yadak_card_ref"><?php esc_html_e( 'شماره پیگیری / مرجع تراکنش', 'yadak-core' ); ?> <abbr class="required">*</abbr></label>
			<input type="text" class="input-text" id="yadak_card_ref" name="yadak_card_ref" inputmode="numeric" dir="ltr" autocomplete="off">
		</p>
		<p class="form-row form-row-wide">
			<label for="yadak_card_last4"><?php esc_html_e( '۴ رقم آخر کارت واریزکننده', 'yadak-core' ); ?></label>
			<input type="text" class="input-text" id="yadak_card_last4" name="yadak_card_last4" maxlength="4" inputmode="numeric" dir="ltr" autocomplete="off">
		</p>
		<?php
	}

	public function validate_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$ref = isset( $_POST['yadak_card_ref'] ) ? trim( yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['yadak_card_ref'] ) ) ) ) : '';
		if ( strlen( $ref ) < 4 ) {
			wc_add_notice( __( 'شماره پیگیری واریز را وارد کنید.', 'yadak-core' ), 'error' );
			return false;
		}
		return true;
	}

	public function process_payment( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$order = wc_get_order( $order_id );
		$ref   = isset( $_POST['yadak_card_ref'] ) ? yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['yadak_card_ref'] ) ) ) : '';
		$last4 = isset( $_POST['yadak_card_last4'] ) ? preg_replace( '/\D/', '', yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['yadak_card_last4'] ) ) ) ) : '';
		// phpcs:enable
		$order->update_meta_data( '_yadak_card_ref', $ref );
		$order->update_meta_data( '_yadak_card_last4', $last4 );
		$order->update_status(
			'on-hold',
			/* translators: 1: reference, 2: last 4 digits */
			sprintf( __( 'کارت‌به‌کارت — شماره پیگیری: %1$s — ۴ رقم آخر کارت: %2$s. پس از بررسی واریز، وضعیت را «در حال انجام» کنید.', 'yadak-core' ), $ref, $last4 ? $last4 : '—' )
		);
		wc_maybe_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public function admin_order_info( $order ) {
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}
		printf(
			'<p><strong>%1$s</strong> <span dir="ltr">%2$s</span><br><strong>%3$s</strong> <span dir="ltr">%4$s</span></p>',
			esc_html__( 'شماره پیگیری کارت‌به‌کارت:', 'yadak-core' ),
			esc_html( $order->get_meta( '_yadak_card_ref' ) ),
			esc_html__( '۴ رقم آخر کارت:', 'yadak-core' ),
			esc_html( $order->get_meta( '_yadak_card_last4' ) )
		);
	}
}
