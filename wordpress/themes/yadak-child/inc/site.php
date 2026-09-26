<?php
/**
 * Site-wide details: search engine verification, no links to unpublished
 * pages, contact card shortcode, and no Elementor Google Fonts.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is a same-site URL a published page? Other URLs are assumed fine.
 *
 * @param string $url URL.
 * @return bool
 */
function yadak_url_is_live( $url ) {
	$home = wp_parse_url( home_url( '/' ) );
	$link = wp_parse_url( (string) $url );
	if ( empty( $link['path'] ) || ( isset( $link['host'] ) && isset( $home['host'] ) && $link['host'] !== $home['host'] ) ) {
		return true;
	}
	$base = isset( $home['path'] ) ? trailingslashit( $home['path'] ) : '/';
	$path = trim( substr( $link['path'], strlen( $base ) - 1 ), '/' );
	if ( '' === $path ) {
		return true;
	}
	$page = get_page_by_path( $path );
	return ! $page || 'publish' === $page->post_status;
}

/**
 * Menus: drop links to pages that are still drafts (they would 404).
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( $items ) {
		return array_values(
			array_filter(
				$items,
				static function ( $item ) {
					if ( 'post_type' === $item->type ) {
						return 'publish' === get_post_status( (int) $item->object_id );
					}
					return 'custom' !== $item->type || yadak_url_is_live( $item->url );
				}
			)
		);
	},
	5
);

/**
 * Google Search Console / Bing Webmaster verification codes (Customizer).
 */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$fields = array(
			'yadak_gsc'  => array( __( 'کد تأیید Google Search Console', 'yadak-child' ), __( 'در Search Console روش «HTML tag» را انتخاب کنید و کل تگ یا فقط مقدار content را اینجا بگذارید.', 'yadak-child' ) ),
			'yadak_bing' => array( __( 'کد تأیید Bing Webmaster (اختیاری)', 'yadak-child' ), '' ),
		);
		foreach ( $fields as $id => $field ) {
			$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'yadak_verification_code' ) );
			$wp_customize->add_control(
				$id,
				array(
					'label'       => $field[0],
					'description' => $field[1],
					'section'     => 'yadak_store',
					'type'        => 'text',
				)
			);
		}
	},
	40
);

/**
 * Accept the whole <meta> tag or just its content value.
 */
function yadak_verification_code( $value ) {
	if ( preg_match( '/content=["\']([^"\']+)["\']/', (string) $value, $m ) ) {
		$value = $m[1];
	}
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value );
}

add_action(
	'wp_head',
	static function () {
		$codes = array(
			'google-site-verification' => get_theme_mod( 'yadak_gsc', '' ),
			'msvalidate.01'            => get_theme_mod( 'yadak_bing', '' ),
		);
		foreach ( $codes as $name => $code ) {
			if ( $code ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $code ) . '">' . "\n";
			}
		}
	},
	1
);

/**
 * [yadak_contact] — phone, WhatsApp, hours, address and Instagram from the
 * Customizer, so pages stay correct when details change.
 */
add_shortcode(
	'yadak_contact',
	static function () {
		$rows  = array();
		$phone = yadak_opt( 'phone' );
		if ( $phone ) {
			$rows[] = array( 'phone', __( 'تلفن', 'yadak-child' ), '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" dir="ltr">' . esc_html( $phone ) . '</a>' );
		}
		$wa = preg_replace( '/\D/', '', (string) get_theme_mod( 'yadak_whatsapp', '' ) );
		if ( $wa ) {
			$wa_link = 'https://wa.me/' . ( 0 === strpos( $wa, '0' ) ? '98' . substr( $wa, 1 ) : $wa );
			$rows[]  = array( 'headset', __( 'واتس‌اپ کارشناس', 'yadak-child' ), '<a href="' . esc_url( $wa_link ) . '" target="_blank" rel="noopener">' . esc_html__( 'شروع گفتگو', 'yadak-child' ) . '</a>' );
		}
		if ( yadak_opt( 'hours' ) ) {
			$rows[] = array( 'truck', __( 'ساعت کاری', 'yadak-child' ), esc_html( yadak_opt( 'hours' ) ) );
		}
		if ( yadak_opt( 'address' ) ) {
			$rows[] = array( 'home', __( 'نشانی', 'yadak-child' ), esc_html( yadak_opt( 'address' ) ) );
		}
		if ( yadak_opt( 'instagram' ) ) {
			$rows[] = array( 'user', __( 'اینستاگرام', 'yadak-child' ), '<a href="' . esc_url( yadak_opt( 'instagram' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'صفحه ما', 'yadak-child' ) . '</a>' );
		}
		if ( ! $rows ) {
			return '';
		}
		$out = '<ul class="yadak-contact">';
		foreach ( $rows as $row ) {
			$out .= '<li><span class="yadak-contact__icon">' . yadak_icon( $row[0] ) . '</span><span><small>' . esc_html( $row[1] ) . '</small>' . $row[2] . '</span></li>';
		}
		return $out . '</ul>';
	}
);

/**
 * Elementor's Google Fonts: unused by the store's pages, slow, and Google
 * is not reliably reachable from Iran.
 */
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
