<?php
/**
 * Brand visuals: category illustrations, branded product placeholder,
 * category page banners and the optional hero photo.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Line illustrations per category slug (24×24 grid, stroke = currentColor).
 * Unknown slugs fall back to their parent category, then to a generic part.
 *
 * @return array<string,string>
 */
function yadak_cat_icon_paths() {
	return apply_filters(
		'yadak_cat_icon_paths',
		array(
			'electrical'         => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/><path d="M13 8.5 10.5 12H14l-2.5 3.5"/>',
			'ecu'                => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/><rect x="10" y="10" width="4" height="4" rx=".5"/>',
			'sensors'            => '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14"/>',
			'alternator-starter' => '<circle cx="12" cy="12" r="9"/><path d="M13 6.5 9 13h4l-2 4.5 5-7.5h-4z"/>',
			'wiring'             => '<path d="M3 8c4 0 5 8 9 8s5-8 9-8"/><path d="M3 5v6M21 5v6"/><circle cx="12" cy="16" r="1.4"/>',
			'body-lights'        => '<path d="M11 5a7 7 0 0 0 0 14z"/><path d="M14.5 7H21M14.5 12H20M14.5 17H19"/>',
			'lights'             => '<path d="M11 5a7 7 0 0 0 0 14z"/><path d="M14.5 7H21M14.5 12H20M14.5 17H19"/>',
			'headlights'         => '<path d="M11 5a7 7 0 0 0 0 14z"/><path d="M14.5 7H21M14.5 12H20M14.5 17H19"/>',
			'taillights'         => '<rect x="3" y="7" width="12" height="10" rx="3"/><path d="M6.5 12h5M18 9l3-1.5M18 12h3.5M18 15l3 1.5"/>',
			'fog-lights'         => '<circle cx="9" cy="12" r="5.5"/><path d="M6.5 12h5"/><path d="M17 9h4M17 12h4M17 15h4" stroke-dasharray="1.5 1.5"/>',
			'body-parts'         => '<path d="M5 13.5 7 8.5h10l2 5"/><path d="M3 13.5h18v3.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M6.5 16h2.5M15 16h2.5M10.5 16h3"/>',
			'bumpers'            => '<path d="M5 13.5 7 8.5h10l2 5"/><path d="M3 13.5h18v3.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M6.5 16h2.5M15 16h2.5M10.5 16h3"/>',
			'roof'               => '<path d="M3.5 17 5.5 11h13l2 6"/><path d="M2.5 17h19"/><path d="M7 11l1.5-3.5h7L17 11"/><path d="M6 5h12M9 5v2.5M15 5v2.5"/>',
			'mechanical'         => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 5v1.5M12 17.5V19M5 12h1.5M17.5 12H19"/><path d="M16.5 4.2A9 9 0 0 1 20.5 9"/>',
			'brake'              => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 5v1.5M12 17.5V19M5 12h1.5M17.5 12H19"/><path d="M16.5 4.2A9 9 0 0 1 20.5 9"/>',
			'consumables'        => '<path d="M12 3s6 6.8 6 11a6 6 0 0 1-12 0c0-4.2 6-11 6-11z"/><path d="M9.2 14.5a2.8 2.8 0 0 0 2.8 2.8"/>',
			'suspension'         => '<path d="M12 2v3.5M12 18.5V22"/><path d="M7.5 5.5h9M7.5 18.5h9"/><path d="M8 8l8 1.8-8 1.8 8 1.8-8 1.8 8 1.3"/>',
			'engine'             => '<path d="M12 2v3"/><rect x="9" y="5" width="6" height="4" rx="1"/><path d="M10 9h4l-.6 6h-2.8z"/><path d="M12 15v4M10 21.5h4"/>',
			'accessories'        => '<path d="M4 17h16M6 17V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8"/><path d="M9 11h6M9 14h6"/>',
			'_default'           => '<path d="M12 3 4 7.5v9L12 21l8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
		)
	);
}

/**
 * @param WP_Term|null $term Product category.
 * @param int          $size Pixels.
 * @return string SVG.
 */
function yadak_cat_icon( $term, $size = 48 ) {
	$paths = yadak_cat_icon_paths();
	$path  = $paths['_default'];
	while ( $term instanceof WP_Term ) {
		if ( isset( $paths[ $term->slug ] ) ) {
			$path = $paths[ $term->slug ];
			break;
		}
		$term = $term->parent ? get_term( $term->parent, 'product_cat' ) : null;
	}
	return '<svg class="yadak-cat-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

/**
 * Branded placeholder instead of WooCommerce's grey mountain.
 */
add_filter(
	'woocommerce_placeholder_img_src',
	static function () {
		return get_stylesheet_directory_uri() . '/assets/brand/placeholder.svg';
	}
);

/**
 * Category pages: brand banner icon next to the title.
 */
add_action(
	'woocommerce_archive_description',
	static function () {
		if ( is_product_category() ) {
			echo '<span class="yadak-archive-icon">' . yadak_cat_icon( get_queried_object(), 64 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	1
);

/**
 * Customizer: optional hero photo (a real photo of your warehouse, shop or
 * parts). The brand overlay keeps text readable on any photo.
 */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_setting(
			'yadak_hero_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'yadak_hero_image',
				array(
					'label'       => __( 'عکس پس‌زمینه صفحه اصلی (اختیاری)', 'yadak-child' ),
					'description' => __( 'عکس افقی حداقل ۱۹۲۰×۱۰۰۰ از انبار، فروشگاه یا قطعات واقعی. روی عکس لایه سرمه‌ای برند می‌نشیند تا متن خوانا بماند.', 'yadak-child' ),
					'section'     => 'yadak_store',
				)
			)
		);
	},
	20
);

/**
 * Inline style for the hero photo, if one is set.
 *
 * @return string
 */
function yadak_hero_style() {
	$image = get_theme_mod( 'yadak_hero_image', '' );
	return $image ? '--y-hero-photo:url(' . esc_url( $image ) . ')' : '';
}
