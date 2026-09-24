<?php
/**
 * Shared helpers.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize Persian/Arabic text for matching: Arabic Yeh/Kaf to Persian,
 * Persian and Arabic-Indic digits to Latin, ZWNJ to space, lowercase,
 * collapsed whitespace.
 *
 * @param string $text Raw text.
 * @return string
 */
function yadak_normalize( $text ) {
	$text = (string) $text;
	$map  = array(
		'ي' => 'ی',
		'ى' => 'ی',
		'ك' => 'ک',
		'ة' => 'ه',
		'أ' => 'ا',
		'إ' => 'ا',
		'ؤ' => 'و',
		"\u{200C}" => ' ',
		"\u{200F}" => '',
		"\u{200E}" => '',
		'ـ' => '',
	);
	$text = yadak_normalize_digits( strtr( $text, $map ) );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = preg_replace( '/\s+/u', ' ', $text );

	return trim( $text );
}

/**
 * Convert Persian and Arabic-Indic digits to Latin digits, leaving the rest.
 *
 * @param string $text Text.
 * @return string
 */
function yadak_normalize_digits( $text ) {
	return strtr(
		(string) $text,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		)
	);
}

/**
 * Compact form of a part/OEM number: normalized, with spaces, dashes, dots
 * and slashes removed, so "04465-0K090" matches "044650k090".
 *
 * @param string $number Part number.
 * @return string
 */
function yadak_compact_number( $number ) {
	return preg_replace( '/[\s\-\.\/_]+/u', '', yadak_normalize( $number ) );
}

/**
 * Customer groups. The key is stored in user meta; the value is the label.
 *
 * @return array<string,string>
 */
function yadak_customer_groups() {
	return apply_filters(
		'yadak_customer_groups',
		array(
			'retail'      => __( 'مشتری عادی', 'yadak-core' ),
			'mechanic'    => __( 'مکانیک', 'yadak-core' ),
			'repair_shop' => __( 'تعمیرگاه', 'yadak-core' ),
			'fleet'       => __( 'ناوگان', 'yadak-core' ),
			'dealer'      => __( 'فروشنده قطعات', 'yadak-core' ),
			'wholesale'   => __( 'عمده‌فروش', 'yadak-core' ),
		)
	);
}

/**
 * Which price tier each customer group buys at.
 * "b2b" = قیمت همکار, "dealer" = قیمت فروشنده/عمده. Groups not listed use retail.
 *
 * @return array<string,string>
 */
function yadak_group_price_tiers() {
	return apply_filters(
		'yadak_group_price_tiers',
		array(
			'mechanic'    => 'b2b',
			'repair_shop' => 'b2b',
			'fleet'       => 'b2b',
			'dealer'      => 'dealer',
			'wholesale'   => 'dealer',
		)
	);
}

/**
 * Price tiers other than retail, with labels.
 *
 * @return array<string,string>
 */
function yadak_price_tiers() {
	return array(
		'b2b'    => __( 'قیمت همکار (مکانیک، تعمیرگاه، ناوگان)', 'yadak-core' ),
		'dealer' => __( 'قیمت فروشنده / عمده', 'yadak-core' ),
	);
}

/**
 * Customer group of a user ("retail" for guests and unknown values).
 *
 * @param int|null $user_id User ID, defaults to the current user.
 * @return string
 */
function yadak_get_customer_group( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	if ( ! $user_id ) {
		return 'retail';
	}
	$group = get_user_meta( $user_id, 'yadak_customer_group', true );
	return array_key_exists( $group, yadak_customer_groups() ) ? $group : 'retail';
}

/**
 * Price tier of a user, or "" for retail.
 *
 * @param int|null $user_id User ID, defaults to the current user.
 * @return string
 */
function yadak_get_price_tier( $user_id = null ) {
	$tiers = yadak_group_price_tiers();
	$group = yadak_get_customer_group( $user_id );
	return isset( $tiers[ $group ] ) ? $tiers[ $group ] : '';
}

/**
 * Format an amount in the store currency.
 *
 * @param float $amount Amount.
 * @return string HTML.
 */
function yadak_money( $amount ) {
	return wc_price( (float) $amount );
}
