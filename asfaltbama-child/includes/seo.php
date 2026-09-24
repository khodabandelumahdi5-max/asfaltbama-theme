<?php
/**
 * SEO adjustments. Rank Math is the single source of <head> SEO tags;
 * this file only removes overlaps and fills gaps in its output.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Content language of the site, independent of the admin/site locale.
 *
 * The WordPress site language is currently English, which makes
 * <html lang="en-US">, og:locale=en_US and schema inLanguage=en-US on a
 * Persian site. Setting Settings > General > Site Language to فارسی is the
 * real fix; these helpers keep the output correct either way.
 *
 * @return string Locale in WordPress format, e.g. fa_IR.
 */
function asfaltbama_content_locale() {
	return apply_filters( 'asfaltbama_content_locale', 'fa_IR' );
}

/**
 * Content language as a BCP 47 tag, e.g. fa-IR.
 *
 * @return string
 */
function asfaltbama_content_lang() {
	return str_replace( '_', '-', asfaltbama_content_locale() );
}

/**
 * Stop the parent theme from printing its own meta description.
 * Rank Math already prints one; two description tags confuse crawlers.
 */
add_filter( 'hello_elementor_description_meta_tag', '__return_false' );

/**
 * Correct the <html lang> attribute on the front end.
 *
 * @param string $output Language attributes.
 *
 * @return string
 */
function asfaltbama_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}

	$lang = esc_attr( asfaltbama_content_lang() );

	if ( preg_match( '/\blang="[^"]*"/', $output ) ) {
		return preg_replace( '/\blang="[^"]*"/', 'lang="' . $lang . '"', $output );
	}

	return trim( $output . ' lang="' . $lang . '"' );
}
add_filter( 'language_attributes', 'asfaltbama_language_attributes' );

/**
 * Rank Math: og:locale.
 */
add_filter(
	'rank_math/opengraph/facebook/og_locale',
	function () {
		return asfaltbama_content_locale();
	}
);

/**
 * Rank Math: drop the Slack "Written by: admin / Time to read" labels.
 * They are rendered in English and expose the admin username.
 */
add_filter( 'rank_math/opengraph/slack_enhanced_sharing', '__return_false' );

/**
 * Business details added to Rank Math's Organization entity when missing.
 *
 * Values entered in Rank Math (Titles & Meta > Local SEO) win; these only
 * fill the gaps. Add the street address and geo coordinates in Rank Math.
 *
 * @return array
 */
function asfaltbama_business_data() {
	$logo = '';
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
	}
	if ( ! $logo ) {
		$logo = get_site_icon_url( 512 );
	}

	$data = [
		'legalName'   => 'شرکت افق آپادانا پاسارگاد',
		'telephone'   => '+989191299559',
		'areaServed'  => [
			[
				'@type' => 'AdministrativeArea',
				'name'  => 'استان تهران',
			],
			[
				'@type' => 'AdministrativeArea',
				'name'  => 'استان البرز',
			],
			[
				'@type' => 'City',
				'name'  => 'تهران',
			],
			[
				'@type' => 'City',
				'name'  => 'کرج',
			],
		],
		'knowsAbout'  => [
			'اجرای آسفالت و تراشه معابر',
			'خاکبرداری و گودبرداری ساختمانی',
			'اجرای ایزوگام و قیرگونی',
			'فروش قیر و مصالح عایق',
			'درزگیری و ماستیک گرم آسفالت',
			'اجاره ماشین‌آلات راه‌سازی',
			'تخریب سازه و خرید ضایعات',
		],
		'contactPoint' => [
			'@type'             => 'ContactPoint',
			'telephone'         => '+989191299559',
			'contactType'       => 'customer service',
			'areaServed'        => 'IR',
			'availableLanguage' => [ 'fa' ],
		],
	];

	if ( $logo ) {
		$data['logo']  = $logo;
		$data['image'] = $logo;
	}

	return apply_filters( 'asfaltbama_business_data', $data );
}

/**
 * Whether a schema entity is the site's organization / local business.
 *
 * @param array $entity Schema entity.
 *
 * @return bool
 */
function asfaltbama_is_organization_entity( $entity ) {
	return isset( $entity['@id'] ) && is_string( $entity['@id'] )
		&& '#organization' === substr( $entity['@id'], -strlen( '#organization' ) );
}

/**
 * Replace en-* inLanguage values with the content language, recursively.
 *
 * @param mixed $value Schema value.
 *
 * @return mixed
 */
function asfaltbama_fix_in_language( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	foreach ( $value as $key => $item ) {
		if ( 'inLanguage' === $key && is_string( $item ) && 0 === strpos( $item, 'en' ) ) {
			$value[ $key ] = asfaltbama_content_lang();
		} elseif ( is_array( $item ) ) {
			$value[ $key ] = asfaltbama_fix_in_language( $item );
		}
	}

	return $value;
}

/**
 * Rank Math JSON-LD: complete the organization entity and fix inLanguage.
 *
 * @param array $data Schema entities keyed by Rank Math.
 *
 * @return array
 */
function asfaltbama_rank_math_json_ld( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$business = null;

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) || ! asfaltbama_is_organization_entity( $entity ) ) {
			continue;
		}

		if ( null === $business ) {
			$business = asfaltbama_business_data();
		}

		foreach ( $business as $prop => $val ) {
			if ( empty( $entity[ $prop ] ) ) {
				$entity[ $prop ] = $val;
			}
		}

		$data[ $key ] = $entity;
	}

	return asfaltbama_fix_in_language( $data );
}
add_filter( 'rank_math/json_ld', 'asfaltbama_rank_math_json_ld', 99 );
