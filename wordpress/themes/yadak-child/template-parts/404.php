<?php
/**
 * Not found: search, departments and help instead of a dead end.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yadak_depts = taxonomy_exists( 'product_cat' ) ? get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'parent'     => 0,
		'hide_empty' => false,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	)
) : array();
?>
<main id="content" class="site-main yadak-404">
	<div class="yadak-container">
		<div class="yadak-404__box">
			<img class="yadak-404__emblem" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/brand/emblem-256.webp' ); ?>" width="160" height="160" alt="">
			<p class="yadak-404__code" aria-hidden="true">۴۰۴</p>
			<h1><?php esc_html_e( 'این صفحه پیدا نشد', 'yadak-child' ); ?></h1>
			<p><?php esc_html_e( 'ممکن است آدرس اشتباه باشد یا صفحه جابه‌جا شده باشد. قطعه را با نام یا شماره فنی جستجو کنید:', 'yadak-child' ); ?></p>
			<?php yadak_search_form( 'yadak-404-s' ); ?>
			<?php if ( $yadak_depts && ! is_wp_error( $yadak_depts ) ) : ?>
				<ul class="yadak-404__links">
					<?php foreach ( $yadak_depts as $yadak_dept ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $yadak_dept ) ); ?>"><?php echo yadak_cat_icon( $yadak_dept, 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $yadak_dept->name ); ?></a></li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo yadak_icon( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'صفحه اصلی', 'yadak-child' ); ?></a></li>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</main>
