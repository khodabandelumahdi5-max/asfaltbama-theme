<?php
/**
 * Buy button (or "in your library" when already owned).
 *
 * @package BavarTheme
 *
 * @var array $args { product: WC_Product, label: string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bavar_product = $args['product'];
$bavar_owned   = class_exists( 'Bavar_Products' ) && Bavar_Products::owned( $bavar_product->get_id() );

if ( $bavar_owned ) {
	printf(
		'<a class="bv-button" href="%s">در کتابخانه‌ی شماست — مشاهده</a>',
		esc_url( wc_get_account_endpoint_url( 'bavar-library' ) )
	);
} elseif ( $bavar_product->is_purchasable() && $bavar_product->is_in_stock() ) {
	printf(
		'<a class="bv-button bv-button--solid" rel="nofollow" href="%s">%s</a>',
		esc_url( add_query_arg( 'add-to-cart', $bavar_product->get_id(), $bavar_product->get_permalink() ) ),
		esc_html( $args['label'] )
	);
} else {
	echo '<span class="bv-button" aria-disabled="true">به‌زودی</span>';
}
