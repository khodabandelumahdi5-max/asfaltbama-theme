<?php
/**
 * "Card to card + receipt" payment method.
 * Loaded after WooCommerce so WC_Payment_Gateway exists.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Gateway_Card extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'bavar_card';
		$this->method_title       = 'کارت به کارت + ارسال فیش (BAVAR)';
		$this->method_description = 'خریدار مبلغ را کارت‌به‌کارت می‌کند و تصویر فیش را از طریق لینک اختصاصی ارسال می‌کند. پس از بررسی، وضعیت سفارش را «تکمیل‌شده» کنید تا دسترسی خریدار خودکار فعال شود.';
		$this->has_fields         = false;
		$this->supports           = [ 'products' ];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'     => [
				'title'   => 'فعال',
				'type'    => 'checkbox',
				'label'   => 'فعال‌سازی پرداخت کارت‌به‌کارت',
				'default' => 'yes',
			],
			'title'       => [
				'title'   => 'عنوان',
				'type'    => 'text',
				'default' => 'کارت به کارت و ارسال فیش',
			],
			'description' => [
				'title'   => 'توضیح در صفحه‌ی پرداخت',
				'type'    => 'textarea',
				'default' => 'پس از ثبت سفارش، شماره کارت نمایش داده می‌شود و لینک ارسال فیش در اختیار شما قرار می‌گیرد. دسترسی پس از تأیید فیش فعال می‌شود.',
			],
			'card_number' => [
				'title'   => 'شماره کارت',
				'type'    => 'text',
				'default' => '',
			],
			'card_holder' => [
				'title'   => 'به نام',
				'type'    => 'text',
				'default' => '',
			],
			'bank'        => [
				'title'   => 'بانک',
				'type'    => 'text',
				'default' => '',
			],
			'sheba'       => [
				'title'   => 'شماره شبا (اختیاری)',
				'type'    => 'text',
				'default' => '',
			],
		];
	}

	/**
	 * Place the order on hold until the receipt is checked.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', 'در انتظار دریافت و تأیید فیش کارت‌به‌کارت.' );
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}
}
