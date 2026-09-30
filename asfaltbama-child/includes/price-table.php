<?php
/**
 * Daily asphalt price table.
 *
 * The owner enters the current per-ton prices under Settings → قیمت روز آسفالت;
 * the [abm_price_table] shortcode shows them with the date they were last
 * saved. Rows left empty are hidden, and with no prices at all the shortcode
 * shows a "call for today's price" box instead of inventing numbers.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Price rows: option key => label.
 *
 * @return array<string,string>
 */
function asfaltbama_price_rows() {
	return [
		'topeka_applied'  => 'آسفالت گرم توپکا (رویه) — با اجرا',
		'binder_applied'  => 'آسفالت گرم بیندر (زیرین) — با اجرا',
		'topeka_material' => 'آسفالت گرم توپکا — بدون اجرا، تحویل در محل',
		'binder_material' => 'آسفالت گرم بیندر — بدون اجرا، تحویل در محل',
		'cold_mix'        => 'آسفالت سرد (کیسه‌ای یا فله)',
	];
}

/**
 * Stored prices.
 *
 * @return array{prices:array<string,string>,note:string,updated:int}
 */
function asfaltbama_price_data() {
	$data = get_option( 'asfaltbama_asphalt_prices', [] );
	return [
		'prices'  => isset( $data['prices'] ) && is_array( $data['prices'] ) ? $data['prices'] : [],
		'note'    => isset( $data['note'] ) ? (string) $data['note'] : '',
		'updated' => isset( $data['updated'] ) ? (int) $data['updated'] : 0,
	];
}

/**
 * Register the settings page.
 *
 * @return void
 */
function asfaltbama_price_menu() {
	add_options_page( 'قیمت روز آسفالت', 'قیمت روز آسفالت', 'manage_options', 'asfaltbama-prices', 'asfaltbama_price_page' );
}
add_action( 'admin_menu', 'asfaltbama_price_menu' );

/**
 * Render and save the settings page.
 *
 * @return void
 */
function asfaltbama_price_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = false;
	if ( isset( $_POST['abm_prices_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['abm_prices_nonce'] ) ), 'abm_prices' ) ) {
		$prices = [];
		foreach ( array_keys( asfaltbama_price_rows() ) as $key ) {
			$raw = isset( $_POST['abm_price'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['abm_price'][ $key ] ) ) : '';
			// Accept Persian/Arabic digits and thousands separators.
			$raw = strtr( $raw, array_combine( preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ), str_split( '01234567890123456789' ) ) );
			$num = preg_replace( '/\D/', '', $raw );
			if ( '' !== $num ) {
				$prices[ $key ] = $num;
			}
		}
		update_option(
			'asfaltbama_asphalt_prices',
			[
				'prices'  => $prices,
				'note'    => isset( $_POST['abm_price_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['abm_price_note'] ) ) : '',
				'updated' => time(),
			]
		);
		$saved = true;
	}

	$data = asfaltbama_price_data();
	echo '<div class="wrap" dir="rtl" style="text-align:right"><h1>قیمت روز آسفالت</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success"><p>قیمت‌ها ذخیره شد و در مقاله‌ی «قیمت هر تن آسفالت» نمایش داده می‌شود.</p></div>';
	}
	echo '<p>قیمت هر تن را به <strong>تومان</strong> وارد کنید. ردیف‌های خالی در سایت نمایش داده نمی‌شوند. تاریخ به‌روزرسانی هنگام ذخیره خودکار ثبت می‌شود.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'abm_prices', 'abm_prices_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( asfaltbama_price_rows() as $key => $label ) {
		$val = isset( $data['prices'][ $key ] ) ? number_format( (float) $data['prices'][ $key ] ) : '';
		printf(
			'<tr><th scope="row"><label for="abm-%1$s">%2$s</label></th><td><input type="text" inputmode="numeric" id="abm-%1$s" name="abm_price[%1$s]" value="%3$s" class="regular-text" dir="ltr"> تومان / تن</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $val )
		);
	}
	printf(
		'<tr><th scope="row"><label for="abm-note">توضیح زیر جدول</label></th><td><textarea id="abm-note" name="abm_price_note" rows="3" class="large-text">%s</textarea><p class="description">مثلاً: «قیمت‌ها برای متراژ بالای ۵۰۰ متر در تهران است.»</p></td></tr>',
		esc_textarea( $data['note'] )
	);
	echo '</table>';
	submit_button( 'ذخیره‌ی قیمت‌ها' );
	echo '</form>';
	if ( $data['updated'] ) {
		echo '<p>آخرین به‌روزرسانی: ' . esc_html( date_i18n( 'j F Y', $data['updated'] ) ) . '</p>';
	}
	echo '</div>';
}

/**
 * Format a number with Persian digits and separators.
 *
 * @param string $num Digits.
 * @return string
 */
function asfaltbama_fa_number( $num ) {
	return strtr( number_format( (float) $num ), [ ',' => '٬', '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * [abm_price_table] shortcode.
 *
 * @return string
 */
function asfaltbama_price_table_shortcode() {
	$data = asfaltbama_price_data();
	$rows = '';
	foreach ( asfaltbama_price_rows() as $key => $label ) {
		if ( ! empty( $data['prices'][ $key ] ) ) {
			$rows .= '<tr><td>' . esc_html( $label ) . '</td><td><strong>' . esc_html( asfaltbama_fa_number( $data['prices'][ $key ] ) ) . '</strong> تومان</td></tr>';
		}
	}

	$tel = '<a href="tel:09191299559">۰۹۱۹ ۱۲۹ ۹۵۵۹</a>';
	if ( '' === $rows ) {
		return '<div class="abm-callout abm-callout--info"><p class="abm-callout__title">قیمت روز هر تن آسفالت</p><p>قیمت آسفالت با نرخ قیر و هزینه‌ی حمل تغییر می‌کند. برای قیمت امروز هر تن آسفالت توپکا و بیندر، با اجرا یا بدون اجرا، با ' . $tel . ' تماس بگیرید یا از <a href="https://asfaltbama.com/contact-us/">فرم استعلام قیمت</a> درخواست بدهید.</p></div>';
	}

	$out  = '<div class="abm-price-table">';
	$out .= '<table><thead><tr><th>نوع آسفالت</th><th>قیمت هر تن</th></tr></thead><tbody>' . $rows . '</tbody></table>';
	if ( $data['updated'] ) {
		$out .= '<p class="abm-price-table__date">آخرین به‌روزرسانی: ' . esc_html( date_i18n( 'j F Y', $data['updated'] ) ) . '</p>';
	}
	if ( '' !== $data['note'] ) {
		$out .= '<p class="abm-price-table__note">' . esc_html( $data['note'] ) . '</p>';
	}
	$out .= '<p class="abm-price-table__cta">قیمت نهایی پس از بازدید و با توجه به متراژ، ضخامت، زیرسازی و مسیر حمل مشخص می‌شود. استعلام: ' . $tel . '</p>';
	$out .= '</div>';
	return $out;
}
add_shortcode( 'abm_price_table', 'asfaltbama_price_table_shortcode' );
