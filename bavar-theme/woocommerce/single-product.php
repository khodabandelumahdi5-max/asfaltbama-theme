<?php
/**
 * Single product: BAVAR course / pack layouts, default WooCommerce otherwise.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$bavar_kind = function_exists( 'bavar_product_kind' ) ? bavar_product_kind( get_the_ID() ) : '';

	if ( $bavar_kind ) {
		echo '<main id="content" class="bv-product bv-product--' . esc_attr( $bavar_kind ) . '">';
		echo '<div class="bv-wrap">';
		woocommerce_output_all_notices();
		echo '</div>';
		get_template_part( 'template-parts/product', $bavar_kind );
		echo '</main>';
	} else {
		echo '<main id="content" class="bv-page"><div class="bv-wrap bv-page__content bv-woo-default">';
		wc_get_template_part( 'content', 'single-product' );
		echo '</div></main>';
	}
endwhile;

get_footer();
