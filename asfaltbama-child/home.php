<?php
/**
 * Posts page (/articles/).
 *
 * Elementor Theme Builder archive templates, if ever created, still win.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'archive' ) ) {
	get_template_part( 'template-parts/blog-archive' );
}

get_footer();
