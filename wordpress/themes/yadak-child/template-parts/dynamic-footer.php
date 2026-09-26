<?php
/**
 * With Elementor active, Hello Elementor loads its "dynamic" footer instead of
 * template-parts/footer.php. Use the store's own footer either way (an Elementor
 * Theme Builder footer, if one is published, still takes precedence).
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/footer' );
