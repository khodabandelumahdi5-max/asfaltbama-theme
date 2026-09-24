<?php
/**
 * Asfaltbama child theme functions and definitions
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ASFALTBAMA_CHILD_VERSION', '1.2.1' );
define( 'ASFALTBAMA_CHILD_PATH', get_stylesheet_directory() );

/**
 * Load child theme stylesheet after the parent theme styles.
 *
 * @return void
 */
function asfaltbama_child_enqueue_styles() {
	wp_enqueue_style(
		'asfaltbama-child-style',
		get_stylesheet_uri(),
		[ 'hello-elementor-theme-style' ],
		ASFALTBAMA_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'asfaltbama_child_enqueue_styles', 20 );

require ASFALTBAMA_CHILD_PATH . '/includes/shortcodes.php';
require ASFALTBAMA_CHILD_PATH . '/includes/seo.php';
require ASFALTBAMA_CHILD_PATH . '/includes/redirects.php';

if ( is_admin() ) {
	require ASFALTBAMA_CHILD_PATH . '/includes/content-importer.php';
}
