<?php
/**
 * "خرید اعتباری" payment gateway for customers with a credit limit.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Credit_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'yadak_credit';
		$this->method_title       = __( 'خرید اعتباری (حساب همکار)', 'yadak-core' );
		$this->method_description = __( 'مشتریانی که سقف اعتبار دارند، تا سقف اعتبار باقی‌مانده بدون پرداخت آنلاین خرید می‌کنند. مبلغ به حساب مشتری بدهکار می‌شود.', 'yadak-core' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'فعال', 'yadak-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی خرید اعتباری', 'yadak-core' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'   => __( 'عنوان', 'yadak-core' ),
				'type'    => 'text',
				'default' => __( 'خرید اعتباری (از حساب همکار)', 'yadak-core' ),
			),
			'description'  => array(
				'title'   => __( 'توضیح', 'yadak-core' ),
				'type'    => 'textarea',
				'default' => __( 'مبلغ این سفارش به حساب شما بدهکار می‌شود و طبق شرایط پرداخت توافق‌شده تسویه می‌کنید.', 'yadak-core' ),
			),
			'order_status' => array(
				'title'   => __( 'وضعیت سفارش پس از ثبت', 'yadak-core' ),
				'type'    => 'select',
				'options' => array(
					'processing' => __( 'در حال انجام (ارسال بدون تأیید)', 'yadak-core' ),
					'on-hold'    => __( 'در انتظار بررسی (تأیید دستی فروش)', 'yadak-core' ),
				),
				'default' => 'processing',
			),
		);
	}

	/**
	 * Cart total that would be charged.
	 *
	 * @return float
	 */
	private function cart_total() {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order ? (float) $order->get_total() : 0.0;
		}
		return WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
	}

	public function is_available() {
		if ( ! parent::is_available() || ! is_user_logged_in() ) {
			return false;
		}
		$user_id = get_current_user_id();
		return Yadak_Credit::credit_limit( $user_id ) > 0 && Yadak_Credit::available_credit( $user_id ) >= $this->cart_total();
	}

	public function get_description() {
		$description = parent::get_description();
		if ( is_user_logged_in() ) {
			$description .= '<br>' . sprintf(
				/* translators: %s: available credit */
				__( 'اعتبار قابل استفاده شما: %s', 'yadak-core' ),
				yadak_money( Yadak_Credit::available_credit( get_current_user_id() ) )
			);
		}
		return $description;
	}

	public function process_payment( $order_id ) {
		$order   = wc_get_order( $order_id );
		$user_id = $order->get_customer_id();

		// Re-check at the moment of purchase.
		if ( ! $user_id || $user_id !== get_current_user_id() || Yadak_Credit::available_credit( $user_id ) < (float) $order->get_total() ) {
			wc_add_notice( __( 'اعتبار کافی برای این سفارش ندارید. لطفاً روش پرداخت دیگری انتخاب کنید.', 'yadak-core' ), 'error' );
			return array( 'result' => 'failure' );
		}

		Yadak_Credit::add_entry(
			array(
				'user_id'  => $user_id,
				'type'     => 'charge',
				'amount'   => $order->get_total(),
				'order_id' => $order->get_id(),
			)
		);
		$order->update_meta_data( '_yadak_credit_charged', 'yes' );
		$order->update_status( $this->get_option( 'order_status', 'processing' ), __( 'ثبت با خرید اعتباری.', 'yadak-core' ) );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
