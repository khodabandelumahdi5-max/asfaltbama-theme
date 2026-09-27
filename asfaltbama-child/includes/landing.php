<?php
/**
 * Landing pages (manifest pages with "layout": "landing"): the
 * international English/Arabic pages and the Persian industrial pages.
 * Their markup comes from tools/build_landings.py; this file provides the
 * shortcodes, the stylesheet and the page-level switches.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Whether the current page is a landing page.
 *
 * @return bool
 */
function asfaltbama_is_landing() {
	$item = function_exists( 'asfaltbama_current_manifest_page' ) ? asfaltbama_current_manifest_page() : null;
	return $item && 'landing' === ( $item['layout'] ?? '' );
}

/**
 * Page-level switches once the query is known: keep the landing markup
 * as written (no automatic paragraphs), hide the theme's page title (the
 * hero has the H1) and load the landing stylesheet.
 *
 * @return void
 */
function asfaltbama_landing_setup() {
	if ( is_admin() || ! asfaltbama_is_landing() ) {
		return;
	}
	remove_filter( 'the_content', 'wpautop' );
	remove_filter( 'the_content', 'wptexturize' );
	add_filter( 'hello_elementor_page_title', '__return_false', 99 );
	add_filter(
		'body_class',
		function ( $classes ) {
			$classes[] = 'abm-landing';
			return $classes;
		}
	);
	add_action(
		'wp_enqueue_scripts',
		function () {
			wp_enqueue_style(
				'asfaltbama-landing',
				get_stylesheet_directory_uri() . '/assets/css/landing.css',
				[ 'asfaltbama-blog' ],
				ASFALTBAMA_CHILD_VERSION
			);
		},
		40
	);
}
add_action( 'wp', 'asfaltbama_landing_setup' );

/**
 * [abm_img file="…" alt="…" class="…" caption="…" eager="1"]: a project
 * photo imported from content/images, responsive, lazy unless eager.
 *
 * @param array $atts Attributes.
 *
 * @return string
 */
function asfaltbama_img_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'file'    => '',
			'alt'     => '',
			'class'   => '',
			'caption' => '',
			'eager'   => '',
		],
		$atts
	);
	$id = asfaltbama_imported_media_id( $atts['file'] );
	if ( ! $id ) {
		return '';
	}
	$img = wp_get_attachment_image(
		$id,
		'large',
		false,
		[
			'alt'           => $atts['alt'],
			'loading'       => $atts['eager'] ? 'eager' : 'lazy',
			'fetchpriority' => false !== strpos( $atts['class'], 'lp-ph--main' ) ? 'high' : 'auto',
			'sizes'         => false !== strpos( $atts['class'], 'abm-figure' ) ? '(max-width: 900px) 92vw, 780px' : ( $atts['eager'] ? '(max-width: 900px) 90vw, 560px' : '(max-width: 700px) 90vw, 420px' ),
		]
	);
	$class = esc_attr( trim( 'lp-figure ' . $atts['class'] ) );
	$cap   = '' !== $atts['caption'] ? '<figcaption>' . esc_html( $atts['caption'] ) . '</figcaption>' : '';
	return '<figure class="' . $class . '">' . $img . $cap . '</figure>';
}
add_shortcode( 'abm_img', 'asfaltbama_img_shortcode' );

/**
 * [abm_contact lang="en|ar|fa" style="hero|band"]: WhatsApp, email and
 * (Persian pages) phone buttons.
 *
 * @param array $atts Attributes.
 *
 * @return string
 */
function asfaltbama_contact_shortcode( $atts ) {
	$atts  = shortcode_atts(
		[
			'lang'  => 'fa',
			'style' => 'hero',
		],
		$atts
	);
	$email = 'ofoghapadanapasargad@gmail.com';
	$texts = [
		'en' => [ 'WhatsApp', 'Email us', 'Hello, I have a project and would like a proposal.' ],
		'ar' => [ 'واتساب', 'البريد الإلكتروني', 'مرحباً، لدي مشروع وأرغب في عرض سعر.' ],
		'fa' => [ 'واتساپ', 'ایمیل', 'سلام، برای پروژه‌ام استعلام می‌خواهم.' ],
	];
	$t     = $texts[ $atts['lang'] ] ?? $texts['fa'];
	$wa    = add_query_arg( 'text', rawurlencode( $t[2] ), ASFALTBAMA_WHATSAPP );

	$icon_wa   = '<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>';
	$icon_mail = '<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.1L4.1 7H4v.2l8 5.2 8-5.2V7h-.1L12 12.1z"/></svg>';
	$icon_tel  = '<svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M6.6 10.8a15.2 15.2 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.3 11.4 11.4 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .6 3.6 1 1 0 0 1-.3 1z"/></svg>';

	$html  = '<div class="lp-actions lp-actions--' . esc_attr( $atts['style'] ) . '">';
	$html .= '<a class="lp-btn lp-btn--wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . $icon_wa . '<span>' . esc_html( $t[0] ) . ' <bdi dir="ltr">+98 919 129 9559</bdi></span></a>';
	$html .= '<a class="lp-btn lp-btn--mail" href="mailto:' . esc_attr( $email ) . '">' . $icon_mail . '<span>' . esc_html( $t[1] ) . ' <bdi dir="ltr">' . esc_html( $email ) . '</bdi></span></a>';
	if ( 'fa' === $atts['lang'] ) {
		$html .= '<a class="lp-btn lp-btn--tel" href="tel:' . esc_attr( ASFALTBAMA_PHONE ) . '">' . $icon_tel . '<span>تماس <bdi dir="ltr">' . esc_html( ASFALTBAMA_PHONE_DISPLAY ) . '</bdi></span></a>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'abm_contact', 'asfaltbama_contact_shortcode' );
