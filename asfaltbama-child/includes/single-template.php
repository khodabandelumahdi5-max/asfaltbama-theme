<?php
/**
 * Articles always use the theme's own single.php.
 *
 * The «Asphalt Business Suite» plugin swaps in its own dark single-post
 * template (body class aas-single-template-active). That template does not
 * load the article styles (summary boxes, stat rows, callouts, tables), and
 * its brown background does not match the Elementor home page. The theme
 * template uses the same navy/amber palette and white cards as the home page.
 *
 * Turn off with: add_filter( 'asfaltbama_force_theme_single', '__return_false' );
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request is a Persian article that should use single.php.
 *
 * @return bool
 */
function asfaltbama_use_theme_single() {
	return is_singular( 'post' ) && apply_filters( 'asfaltbama_force_theme_single', true );
}

/**
 * Run last, after the plugin has picked its template.
 *
 * @param string $template Template chosen so far.
 * @return string
 */
function asfaltbama_theme_single_template( $template ) {
	if ( ! asfaltbama_use_theme_single() ) {
		return $template;
	}
	// The Arabic/English article template is ours too: keep it.
	if ( 'article-intl.php' === basename( (string) $template ) ) {
		return $template;
	}
	$own = get_stylesheet_directory() . '/single.php';
	return file_exists( $own ) ? $own : $template;
}
add_filter( 'template_include', 'asfaltbama_theme_single_template', PHP_INT_MAX );

/**
 * Drop the plugin's body class so its single-page CSS does not apply.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function asfaltbama_theme_single_body_class( $classes ) {
	if ( asfaltbama_use_theme_single() ) {
		$classes   = array_values( array_diff( $classes, [ 'aas-single-template-active' ] ) );
		$classes[] = 'abm-single';
	}
	return $classes;
}
add_filter( 'body_class', 'asfaltbama_theme_single_body_class', PHP_INT_MAX );
