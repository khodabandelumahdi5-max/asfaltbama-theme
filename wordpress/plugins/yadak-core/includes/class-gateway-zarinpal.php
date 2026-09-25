<?php
/**
 * Zarinpal payment gateway (REST API v4) with sandbox mode.
 *
 * Flow: request.json → redirect to StartPay/{authority} → callback
 * (?wc-api=yadak_zarinpal) → verify.json → payment_complete(ref_id).
 * Amounts are sent in Rial; Toman prices are multiplied by 10.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Gateway_Zarinpal extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'yadak_zarinpal';
		$this->method_title       = __( 'زرین‌پال', 'yadak-core' );
		$this->method_description = __( 'پرداخت آنلاین با همه کارت‌های شتاب از طریق زرین‌پال.', 'yadak-core' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'callback' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'فعال', 'yadak-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی زرین‌پال', 'yadak-core' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'عنوان', 'yadak-core' ),
				'type'    => 'text',
				'default' => __( 'پرداخت آنلاین (همه کارت‌های بانکی)', 'yadak-core' ),
			),
			'description' => array(
				'title'   => __( 'توضیح', 'yadak-core' ),
				'type'    => 'textarea',
				'default' => __( 'پس از ثبت سفارش به درگاه امن زرین‌پال منتقل می‌شوید.', 'yadak-core' ),
			),
			'merchant_id' => array(
				'title'       => __( 'مرچنت کد', 'yadak-core' ),
				'type'        => 'text',
				'description' => __( 'کد ۳۶ کاراکتری درگاه از پنل زرین‌پال.', 'yadak-core' ),
			),
			'sandbox'     => array(
				'title'   => __( 'حالت آزمایشی', 'yadak-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'استفاده از sandbox زرین‌پال (پرداخت واقعی انجام نمی‌شود)', 'yadak-core' ),
				'default' => 'no',
			),
		);
	}

	private function base() {
		return 'yes' === $this->get_option( 'sandbox' ) ? 'https://sandbox.zarinpal.com/pg/' : 'https://payment.zarinpal.com/pg/';
	}

	/**
	 * Order total in Rial.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	public static function rial_amount( $order ) {
		$total = (float) $order->get_total();
		switch ( $order->get_currency() ) {
			case 'IRT':
				return (int) round( $total * 10 );
			case 'IRHT': // Thousand Toman.
				return (int) round( $total * 10000 );
			case 'IRHR': // Thousand Rial.
				return (int) round( $total * 1000 );
			default:
				return (int) round( $total );
		}
	}

	private function api( $path, $body ) {
		$res = wp_remote_post(
			$this->base() . 'v4/payment/' . $path . '.json',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'error' => $res->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : array( 'error' => wp_remote_retrieve_body( $res ) );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$res   = $this->api(
			'request',
			array(
				'merchant_id'  => $this->get_option( 'merchant_id' ),
				'amount'       => self::rial_amount( $order ),
				'callback_url' => add_query_arg( 'order_id', $order->get_id(), WC()->api_request_url( $this->id ) ),
				/* translators: %s: order number */
				'description'  => sprintf( __( 'سفارش %s', 'yadak-core' ), $order->get_order_number() ),
				'metadata'     => array_filter(
					array(
						'mobile' => Yadak_Checkout::normalize_mobile( $order->get_billing_phone() ),
						'email'  => $order->get_billing_email(),
					)
				),
			)
		);

		if ( isset( $res['data']['code'] ) && 100 === (int) $res['data']['code'] && ! empty( $res['data']['authority'] ) ) {
			$order->update_meta_data( '_yadak_zp_authority', $res['data']['authority'] );
			$order->save();
			return array(
				'result'   => 'success',
				'redirect' => $this->base() . 'StartPay/' . rawurlencode( $res['data']['authority'] ),
			);
		}

		$message = $this->error_text( $res );
		/* translators: %s: gateway error */
		$order->add_order_note( sprintf( __( 'خطای زرین‌پال در شروع پرداخت: %s', 'yadak-core' ), $message ) );
		/* translators: %s: gateway error */
		wc_add_notice( sprintf( __( 'اتصال به درگاه ممکن نشد: %s', 'yadak-core' ), $message ), 'error' );
		return array( 'result' => 'failure' );
	}

	private function error_text( $res ) {
		if ( ! empty( $res['errors']['message'] ) ) {
			return $res['errors']['message'] . ( isset( $res['errors']['code'] ) ? ' (' . $res['errors']['code'] . ')' : '' );
		}
		if ( ! empty( $res['error'] ) ) {
			return is_string( $res['error'] ) ? $res['error'] : wp_json_encode( $res['error'] );
		}
		return isset( $res['data']['code'] ) ? (string) $res['data']['code'] : __( 'پاسخ نامعتبر', 'yadak-core' );
	}

	/**
	 * Return from Zarinpal.
	 */
	public function callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Gateway callback; verified against the gateway below.
		$order     = wc_get_order( isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0 );
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : '';
		$status    = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';
		// phpcs:enable

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
		if ( $order->is_paid() ) {
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
		if ( ! $authority || $authority !== $order->get_meta( '_yadak_zp_authority' ) || 'OK' !== $status ) {
			$order->update_status( 'failed', __( 'پرداخت زرین‌پال انجام نشد یا لغو شد.', 'yadak-core' ) );
			wc_add_notice( __( 'پرداخت انجام نشد. می‌توانید دوباره تلاش کنید.', 'yadak-core' ), 'error' );
			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;
		}

		$res  = $this->api(
			'verify',
			array(
				'merchant_id' => $this->get_option( 'merchant_id' ),
				'amount'      => self::rial_amount( $order ),
				'authority'   => $authority,
			)
		);
		$code = isset( $res['data']['code'] ) ? (int) $res['data']['code'] : 0;

		if ( 100 === $code || 101 === $code ) {
			$ref = isset( $res['data']['ref_id'] ) ? (string) $res['data']['ref_id'] : '';
			$order->update_meta_data( '_yadak_zp_card', isset( $res['data']['card_pan'] ) ? $res['data']['card_pan'] : '' );
			/* translators: %s: reference id */
			$order->add_order_note( sprintf( __( 'پرداخت زرین‌پال موفق. کد پیگیری: %s', 'yadak-core' ), $ref ) );
			$order->payment_complete( $ref );
			WC()->cart && WC()->cart->empty_cart();
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		/* translators: %s: gateway error */
		$order->update_status( 'failed', sprintf( __( 'تأیید پرداخت زرین‌پال ناموفق: %s', 'yadak-core' ), $this->error_text( $res ) ) );
		wc_add_notice( __( 'تأیید پرداخت ناموفق بود. اگر مبلغ از حساب شما کم شده، طی ۷۲ ساعت برمی‌گردد.', 'yadak-core' ), 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}
}
