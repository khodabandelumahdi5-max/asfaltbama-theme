<?php
/**
 * Mega menu for the main navigation: hovering a top item opens a floating
 * panel (subcategories with icons, all departments, brands, car makes by
 * country). Panels come from live data, so they follow the catalogue.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Panel HTML for a top-level menu item, or '' if it has none.
 *
 * @param WP_Post $item Menu item.
 * @return string
 */
function yadak_mega_panel( $item ) {
	static $cache = array();
	$key = isset( $item->ID ) ? (int) $item->ID : 0;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$html = '';
	if ( class_exists( 'WooCommerce' ) ) {
		$url = untrailingslashit( (string) $item->url );
		if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
			$term = get_term( (int) $item->object_id, 'product_cat' );
			$html = $term instanceof WP_Term ? yadak_mega_department( $term ) : '';
		} elseif ( untrailingslashit( wc_get_page_permalink( 'shop' ) ) === $url ) {
			$html = yadak_mega_all_departments();
		} elseif ( untrailingslashit( home_url( '/brands' ) ) === $url ) {
			$html = yadak_mega_brands();
		} elseif ( 'yadak-cars' === $item->type ) {
			$html = yadak_mega_cars();
		}
	}
	$cache[ $key ] = $html;
	return $html;
}

/**
 * Top-level product categories (the three departments).
 *
 * @return WP_Term[]
 */
function yadak_mega_departments() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'meta_value_num',
			'meta_key'   => 'order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

function yadak_mega_children( $term ) {
	$children = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => $term->term_id,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	return is_wp_error( $children ) ? array() : $children;
}

/**
 * Product count of a category including its subcategories.
 */
function yadak_mega_count( $term ) {
	$count = (int) $term->count;
	foreach ( get_term_children( $term->term_id, 'product_cat' ) as $child_id ) {
		$child  = get_term( $child_id, 'product_cat' );
		$count += $child instanceof WP_Term ? (int) $child->count : 0;
	}
	return $count;
}

/**
 * Side card: emblem, title, count and a call to action.
 */
function yadak_mega_feature( $title, $text, $url, $cta, $icon = '' ) {
	$emblem = get_stylesheet_directory_uri() . '/assets/brand/emblem-256.webp';
	return '<a class="yadak-mega__feature" href="' . esc_url( $url ) . '">'
		. '<img class="yadak-mega__emblem" src="' . esc_url( $emblem ) . '" alt="" width="180" height="180" loading="lazy" decoding="async">'
		. ( $icon ? '<span class="yadak-mega__feature-icon">' . $icon . '</span>' : '' )
		. '<strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $text ) . '</span>'
		. '<span class="yadak-mega__cta">' . esc_html( $cta ) . ' ' . yadak_icon( 'arrow' ) . '</span></a>';
}

function yadak_mega_department( $term ) {
	$children = yadak_mega_children( $term );
	if ( ! $children ) {
		return '';
	}
	$items = '';
	foreach ( $children as $child ) {
		$items .= '<li><a class="yadak-mega__item" href="' . esc_url( get_term_link( $child ) ) . '">'
			. '<span class="yadak-mega__icon">' . yadak_cat_icon( $child, 26 ) . '</span>'
			. '<span class="yadak-mega__label">' . esc_html( $child->name ) . '<small>' . esc_html( sprintf( /* translators: %s: count */ __( '%s قطعه', 'yadak-child' ), number_format_i18n( $child->count ) ) ) . '</small></span></a></li>';
	}
	return '<div class="yadak-mega__grid"><ul class="yadak-mega__items">' . $items . '</ul>'
		. yadak_mega_feature(
			$term->name,
			sprintf( /* translators: %s: count */ __( '%s قطعه با شماره فنی و ضمانت اصالت', 'yadak-child' ), number_format_i18n( yadak_mega_count( $term ) ) ),
			get_term_link( $term ),
			__( 'مشاهده همه', 'yadak-child' ),
			yadak_cat_icon( $term, 40 )
		) . '</div>';
}

function yadak_mega_all_departments() {
	$cols = '';
	foreach ( yadak_mega_departments() as $dept ) {
		$links = '';
		foreach ( yadak_mega_children( $dept ) as $child ) {
			$links .= '<li><a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a></li>';
		}
		$cols .= '<div class="yadak-mega__col"><a class="yadak-mega__col-head" href="' . esc_url( get_term_link( $dept ) ) . '"><span class="yadak-mega__icon">' . yadak_cat_icon( $dept, 26 ) . '</span>' . esc_html( $dept->name ) . '</a><ul>' . $links . '</ul></div>';
	}
	if ( ! $cols ) {
		return '';
	}
	return '<div class="yadak-mega__grid yadak-mega__grid--cols"><div class="yadak-mega__cols">' . $cols . '</div>'
		. yadak_mega_feature( __( 'همه قطعات', 'yadak-child' ), __( 'جستجو با شماره فنی، مدل خودرو و سال ساخت', 'yadak-child' ), wc_get_page_permalink( 'shop' ), __( 'ورود به فروشگاه', 'yadak-child' ) ) . '</div>';
}

function yadak_mega_brands() {
	if ( ! class_exists( 'Yadak_Brands' ) || ! taxonomy_exists( 'product_brand' ) ) {
		return '';
	}
	$brands = get_terms(
		array(
			'taxonomy'   => 'product_brand',
			'hide_empty' => false,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 12,
		)
	);
	if ( is_wp_error( $brands ) || ! $brands ) {
		return '';
	}
	$items = '';
	foreach ( $brands as $brand ) {
		$items .= '<li><a class="yadak-mega__brand" href="' . esc_url( get_term_link( $brand ) ) . '"><span class="yadak-mega__brand-logo">' . Yadak_Brands::logo_html( $brand, 'thumbnail' ) . '</span><span>' . esc_html( $brand->name ) . '</span></a></li>';
	}
	return '<div class="yadak-mega__grid"><ul class="yadak-mega__brands">' . $items . '</ul>'
		. yadak_mega_feature( __( 'برندهای معتبر', 'yadak-child' ), __( 'جنیون، سازنده خط تولید (OE) و افترمارکت', 'yadak-child' ), home_url( '/brands/' ), __( 'همه برندها', 'yadak-child' ) ) . '</div>';
}

function yadak_mega_cars() {
	if ( ! class_exists( 'Yadak_Fitment' ) ) {
		return '';
	}
	$origins = Yadak_Fitment::origins();
	$cols    = '';
	foreach ( Yadak_Fitment::makes_by_origin() as $code => $makes ) {
		if ( ! $makes || ! isset( $origins[ $code ] ) ) {
			continue;
		}
		$links = '';
		foreach ( array_slice( $makes, 0, 8 ) as $make ) {
			$links .= '<li><a href="' . esc_url( get_term_link( $make ) ) . '">' . esc_html( $make->name ) . '</a></li>';
		}
		$cols .= '<div class="yadak-mega__col"><span class="yadak-mega__col-head">' . esc_html( $origins[ $code ] ) . '</span><ul>' . $links . '</ul></div>';
	}
	if ( ! $cols ) {
		return '';
	}
	return '<div class="yadak-mega__grid yadak-mega__grid--cols"><div class="yadak-mega__cols yadak-mega__cols--4">' . $cols . '</div>'
		. yadak_mega_feature( __( 'قطعه مخصوص خودروی شما', 'yadak-child' ), __( 'خودرو را انتخاب کنید تا فقط قطعات سازگار نمایش داده شود.', 'yadak-child' ), home_url( '/' ), __( 'انتخاب خودرو', 'yadak-child' ), yadak_icon( 'car' ) ) . '</div>';
}

/**
 * «خودروها» item after the first menu item, unless the menu already links
 * to a vehicle page.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( $items, $args ) {
		if ( 'menu-1' !== ( $args->theme_location ?? '' ) || ! class_exists( 'Yadak_Fitment' ) ) {
			return $items;
		}
		foreach ( $items as $item ) {
			if ( 'yadak_vehicle' === $item->object ) {
				return $items;
			}
		}
		$cars                   = new stdClass();
		$cars->ID               = -1;
		$cars->db_id            = -1;
		$cars->menu_item_parent = 0;
		$cars->object_id        = 0;
		$cars->object           = 'custom';
		$cars->type             = 'yadak-cars';
		$cars->title            = __( 'خودروها', 'yadak-child' );
		$cars->url              = home_url( '/#yadak-cars' );
		$cars->classes          = array( 'menu-item' );
		$cars->target           = '';
		$cars->attr_title       = '';
		$cars->description      = '';
		$cars->xfn              = '';
		$cars->current          = false;
		$cars                   = new WP_Post( $cars );
		array_splice( $items, 1, 0, array( $cars ) );
		foreach ( array_values( $items ) as $i => $item ) {
			$item->menu_order = $i + 1;
		}
		return $items;
	},
	10,
	2
);

add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item, $args, $depth ) {
		if ( 0 === $depth && 'menu-1' === ( $args->theme_location ?? '' ) && yadak_mega_panel( $item ) ) {
			$classes[] = 'has-mega';
		}
		return $classes;
	},
	10,
	4
);

add_filter(
	'walker_nav_menu_start_el',
	static function ( $output, $item, $depth, $args ) {
		if ( 0 !== $depth || 'menu-1' !== ( $args->theme_location ?? '' ) ) {
			return $output;
		}
		$panel = yadak_mega_panel( $item );
		if ( ! $panel ) {
			return $output;
		}
		// Toggle for touch screens and the mobile drawer.
		$toggle = '<button type="button" class="yadak-mega__toggle" aria-expanded="false" aria-label="' . esc_attr( sprintf( /* translators: %s: menu item */ __( 'زیرمنوی %s', 'yadak-child' ), $item->title ) ) . '">' . yadak_icon( 'chevron' ) . '</button>';
		return $output . $toggle . '<div class="yadak-mega"><div class="yadak-container">' . $panel . '</div></div>';
	},
	10,
	4
);
