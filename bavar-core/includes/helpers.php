<?php
/**
 * Small shared helpers.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert Persian / Arabic digits to Latin digits.
 *
 * @param string $value Raw value.
 * @return string
 */
function bavar_latin_digits( $value ) {
	$fa = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
	$en = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];
	return str_replace( $fa, $en, (string) $value );
}

/**
 * Normalise an Iranian mobile number to 09xxxxxxxxx. Returns '' when invalid.
 *
 * @param string $phone Raw phone.
 * @return string
 */
function bavar_normalize_phone( $phone ) {
	$phone = preg_replace( '/\D+/', '', bavar_latin_digits( $phone ) );
	if ( 0 === strpos( $phone, '0098' ) ) {
		$phone = '0' . substr( $phone, 4 );
	} elseif ( 0 === strpos( $phone, '98' ) && 12 === strlen( $phone ) ) {
		$phone = '0' . substr( $phone, 2 );
	} elseif ( 10 === strlen( $phone ) && '9' === $phone[0] ) {
		$phone = '0' . $phone;
	}
	return preg_match( '/^09\d{9}$/', $phone ) ? $phone : '';
}

/**
 * Product "kind" used by the BAVAR templates: course | pack | ''.
 *
 * @param int|WC_Product $product Product or ID.
 * @return string
 */
function bavar_product_kind( $product ) {
	$id = is_object( $product ) ? $product->get_id() : (int) $product;
	$kind = get_post_meta( $id, '_bavar_kind', true );
	return in_array( $kind, [ 'course', 'pack' ], true ) ? $kind : '';
}

/**
 * Split a textarea value into trimmed, non-empty lines.
 *
 * @param string $text Text.
 * @return string[]
 */
function bavar_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Format a number with Persian digits.
 *
 * @param int|string $n Number.
 * @return string
 */
function bavar_fa_num( $n ) {
	return str_replace( range( 0, 9 ), [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], (string) $n );
}
