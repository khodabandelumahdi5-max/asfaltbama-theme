<?php
/**
 * Printable A4 documents: sales invoice (فاکتور فروش / رسمی) and quotation
 * (پیش‌فاکتور). URL: /?yadak_print=invoice&id=ORDER or /?yadak_print=quote&id=QUOTE
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Print {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_print' ), 1 );
	}

	public static function url( $type, $id ) {
		return add_query_arg(
			array(
				'yadak_print' => $type,
				'id'          => (int) $id,
			),
			home_url( '/' )
		);
	}

	public static function maybe_print() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['yadak_print'] ) || empty( $_GET['id'] ) ) {
			return;
		}
		$type = sanitize_key( $_GET['yadak_print'] );
		$id   = absint( $_GET['id'] );
		// phpcs:enable
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		if ( 'invoice' === $type ) {
			$order = wc_get_order( $id );
			if ( ! $order || ! ( current_user_can( 'edit_shop_orders' ) || (int) $order->get_customer_id() === get_current_user_id() ) ) {
				wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ), 403 );
			}
			self::render( self::invoice_data( $order ) );
		} elseif ( 'quote' === $type && class_exists( 'Yadak_Quotes' ) ) {
			$quote = get_post( $id );
			if ( ! $quote || Yadak_Quotes::POST_TYPE !== $quote->post_type || ! ( current_user_can( 'edit_shop_orders' ) || Yadak_Quotes::customer_id( $id ) === get_current_user_id() ) ) {
				wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ), 403 );
			}
			self::render( Yadak_Quotes::document_data( $id ) );
		} else {
			return;
		}
		exit;
	}

	/**
	 * @return array<string,string> Label => value.
	 */
	public static function seller() {
		return array_filter(
			array(
				__( 'فروشنده', 'yadak-core' )     => Yadak_Settings::get( 'seller_name' ),
				__( 'کد اقتصادی', 'yadak-core' )  => Yadak_Settings::get( 'seller_economic_code' ),
				__( 'شناسه ملی', 'yadak-core' )   => Yadak_Settings::get( 'seller_national_id' ),
				__( 'شماره ثبت', 'yadak-core' )   => Yadak_Settings::get( 'seller_reg_no' ),
				__( 'نشانی', 'yadak-core' )       => Yadak_Settings::get( 'seller_address' ),
				__( 'کد پستی', 'yadak-core' )     => Yadak_Settings::get( 'seller_postcode' ),
				__( 'تلفن', 'yadak-core' )        => Yadak_Settings::get( 'seller_phone' ),
			)
		);
	}

	/**
	 * Buyer box from a customer user and/or an order.
	 *
	 * @param int           $user_id Customer.
	 * @param WC_Order|null $order   Order.
	 * @return array<string,string>
	 */
	public static function buyer( $user_id, $order = null ) {
		$company = $user_id ? get_user_meta( $user_id, 'yadak_company', true ) : '';
		$name    = $order ? $order->get_formatted_billing_full_name() : Yadak_SMS::user_name( $user_id );
		$address = $order ? implode( '، ', array_filter( array( WC()->countries->get_states( 'IR' )[ $order->get_billing_state() ] ?? $order->get_billing_state(), $order->get_billing_city(), $order->get_billing_address_1(), $order->get_billing_address_2() ) ) ) : '';
		return array_filter(
			array(
				__( 'خریدار', 'yadak-core' )      => trim( $name . ( $company ? ' — ' . $company : '' ) ),
				__( 'کد ملی / شناسه ملی', 'yadak-core' ) => $user_id ? get_user_meta( $user_id, 'yadak_national_id', true ) : '',
				__( 'کد اقتصادی', 'yadak-core' )  => $user_id ? get_user_meta( $user_id, 'yadak_economic_code', true ) : '',
				__( 'نشانی', 'yadak-core' )       => $address,
				__( 'کد پستی', 'yadak-core' )     => $order ? $order->get_billing_postcode() : '',
				__( 'تلفن', 'yadak-core' )        => $order ? $order->get_billing_phone() : Yadak_SMS::user_mobile( $user_id ),
			)
		);
	}

	/**
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function invoice_data( $order ) {
		$user_id  = (int) $order->get_customer_id();
		$official = $user_id && get_user_meta( $user_id, 'yadak_economic_code', true );
		$lines    = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$qty     = (float) $item->get_quantity();
			$lines[] = array(
				'name'     => $item->get_name(),
				'code'     => $product ? ( $product->get_meta( '_yadak_part_number' ) ? $product->get_meta( '_yadak_part_number' ) : $product->get_sku() ) : '',
				'qty'      => $qty,
				'unit'     => $qty ? (float) $item->get_subtotal() / $qty : 0,
				'discount' => (float) $item->get_subtotal() - (float) $item->get_total(),
				'tax'      => (float) $item->get_total_tax(),
				'total'    => (float) $item->get_total() + (float) $item->get_total_tax(),
			);
		}
		$totals = array();
		foreach ( $order->get_fees() as $fee ) {
			$totals[ $fee->get_name() ] = (float) $fee->get_total();
		}
		if ( (float) $order->get_shipping_total() ) {
			$totals[ __( 'هزینه ارسال', 'yadak-core' ) ] = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
		}
		if ( (float) $order->get_total_tax() ) {
			$totals[ __( 'مالیات و عوارض ارزش افزوده', 'yadak-core' ) ] = (float) $order->get_total_tax();
		}
		$totals[ __( 'مبلغ قابل پرداخت', 'yadak-core' ) ] = (float) $order->get_total();

		$date = $order->get_date_created();
		return array(
			'title'  => $official ? __( 'صورتحساب فروش کالا (فاکتور رسمی)', 'yadak-core' ) : __( 'فاکتور فروش', 'yadak-core' ),
			'meta'   => array(
				__( 'شماره', 'yadak-core' )       => $order->get_order_number(),
				__( 'تاریخ', 'yadak-core' )       => $date ? yadak_show_date( $date->date( 'Y-m-d' ) ) : '',
				__( 'روش پرداخت', 'yadak-core' )  => $order->get_payment_method_title(),
				__( 'وضعیت', 'yadak-core' )       => wc_get_order_status_name( $order->get_status() ),
			),
			'seller' => self::seller(),
			'buyer'  => self::buyer( $user_id, $order ),
			'lines'  => $lines,
			'totals' => $totals,
			'notes'  => $order->get_customer_note(),
		);
	}

	/**
	 * Output a full printable page.
	 *
	 * @param array $doc title, meta, seller, buyer, lines, totals, notes, footer.
	 */
	public static function render( $doc ) {
		$font = get_stylesheet_directory() . '/assets/fonts/Vazirmatn-wght.woff2';
		$font = file_exists( $font ) ? get_stylesheet_directory_uri() . '/assets/fonts/Vazirmatn-wght.woff2' : '';
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $doc['title'] . ' ' . reset( $doc['meta'] ) ); ?></title>
<style>
<?php if ( $font ) : ?>
@font-face { font-family: Vazirmatn; src: url(<?php echo esc_url( $font ); ?>) format("woff2"); font-weight: 100 900; }
<?php endif; ?>
* { box-sizing: border-box; }
body { font-family: Vazirmatn, Tahoma, sans-serif; font-feature-settings: "ss01"; color: #111827; background: #f3f4f6; margin: 0; font-size: 12px; line-height: 1.8; }
.sheet { max-width: 210mm; margin: 16px auto; background: #fff; padding: 12mm; }
header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
h1 { font-size: 18px; margin: 0; }
.meta { display: grid; grid-template-columns: auto auto; gap: 0 12px; }
.meta dt { color: #6b7280; } .meta dd { margin: 0; font-weight: 700; }
.parties { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.party { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; }
.party h2 { font-size: 13px; margin: 0 0 4px; }
.party p { margin: 0; } .party span { color: #6b7280; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: right; vertical-align: top; }
th { background: #f3f4f6; font-weight: 700; }
td.num { white-space: nowrap; }
.code { direction: ltr; unicode-bidi: embed; font-feature-settings: normal; color: #4b5563; }
.totals { width: 50%; margin-inline-start: auto; margin-top: 8px; }
.totals tr:last-child td { font-weight: 800; font-size: 14px; background: #f3f4f6; }
.notes { margin-top: 12px; border: 1px dashed #d1d5db; padding: 8px; border-radius: 6px; white-space: pre-line; }
.sign { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 28px; text-align: center; color: #6b7280; }
.sign div { border-top: 1px solid #d1d5db; padding-top: 6px; }
.bar { max-width: 210mm; margin: 16px auto 0; text-align: left; }
.bar button { font: inherit; padding: 8px 18px; border: 0; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; }
@media print { body { background: #fff; } .sheet { margin: 0; padding: 0; max-width: none; } .bar { display: none; } @page { size: A4; margin: 12mm; } }
</style>
</head>
<body>
<div class="bar"><button onclick="window.print()"><?php esc_html_e( 'چاپ / ذخیره PDF', 'yadak-core' ); ?></button></div>
<div class="sheet">
	<header>
		<h1><?php echo esc_html( $doc['title'] ); ?></h1>
		<dl class="meta">
			<?php foreach ( $doc['meta'] as $label => $value ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd>
			<?php endforeach; ?>
		</dl>
	</header>
	<div class="parties">
		<?php foreach ( array( __( 'مشخصات فروشنده', 'yadak-core' ) => $doc['seller'], __( 'مشخصات خریدار', 'yadak-core' ) => $doc['buyer'] ) as $heading => $rows ) : ?>
			<div class="party">
				<h2><?php echo esc_html( $heading ); ?></h2>
				<?php foreach ( $rows as $label => $value ) : ?>
					<p><span><?php echo esc_html( $label ); ?>:</span> <?php echo esc_html( $value ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<table>
		<thead><tr>
			<th>#</th><th><?php esc_html_e( 'شرح کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'تعداد', 'yadak-core' ); ?></th>
			<th><?php esc_html_e( 'مبلغ واحد', 'yadak-core' ); ?></th><th><?php esc_html_e( 'تخفیف', 'yadak-core' ); ?></th>
			<th><?php esc_html_e( 'مالیات', 'yadak-core' ); ?></th><th><?php esc_html_e( 'مبلغ کل', 'yadak-core' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $doc['lines'] as $i => $line ) : ?>
			<tr>
				<td><?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></td>
				<td><?php echo esc_html( $line['name'] ); ?><?php if ( $line['code'] ) : ?><br><span class="code"><?php echo esc_html( $line['code'] ); ?></span><?php endif; ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $line['qty'] ) ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $line['unit'] ) ); ?></td>
				<td class="num"><?php echo esc_html( $line['discount'] ? number_format_i18n( $line['discount'] ) : '—' ); ?></td>
				<td class="num"><?php echo esc_html( $line['tax'] ? number_format_i18n( $line['tax'] ) : '—' ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $line['total'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<table class="totals">
		<?php foreach ( $doc['totals'] as $label => $amount ) : ?>
			<tr><td><?php echo esc_html( $label ); ?></td><td class="num"><?php echo wp_kses_post( wc_price( $amount ) ); ?></td></tr>
		<?php endforeach; ?>
	</table>
	<?php if ( ! empty( $doc['notes'] ) ) : ?>
		<div class="notes"><?php echo esc_html( $doc['notes'] ); ?></div>
	<?php endif; ?>
	<div class="sign"><div><?php esc_html_e( 'مهر و امضای فروشنده', 'yadak-core' ); ?></div><div><?php esc_html_e( 'مهر و امضای خریدار', 'yadak-core' ); ?></div></div>
</div>
</body>
</html>
		<?php
	}
}
