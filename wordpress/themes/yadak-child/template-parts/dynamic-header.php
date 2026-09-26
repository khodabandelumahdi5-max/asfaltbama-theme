<?php
/**
 * With Elementor active, Hello Elementor loads its "dynamic" header instead of
 * template-parts/header.php. Use the store's own header either way (an Elementor
 * Theme Builder header, if one is published, still takes precedence).
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/header' );
