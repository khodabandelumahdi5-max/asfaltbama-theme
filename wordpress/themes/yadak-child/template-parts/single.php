<?php
/**
 * Pages and posts: brand banner with the title and breadcrumb, the text in
 * a readable card, and a help box on content pages (not cart/checkout).
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yadak_is_store_page = function_exists( 'is_woocommerce' ) && ( is_cart() || is_checkout() || is_account_page() );

while ( have_posts() ) :
	the_post();
	?>
<main id="content" <?php post_class( 'site-main yadak-page' . ( $yadak_is_store_page ? ' yadak-page--store' : '' ) ); ?>>
	<div class="yadak-container">
		<?php if ( apply_filters( 'hello_elementor_page_title', true ) ) : ?>
			<header class="yadak-page__head">
				<nav class="yadak-page__crumbs" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'yadak-child' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'yadak-child' ); ?></a>
					<?php
					foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $yadak_parent ) {
						echo ' / <a href="' . esc_url( get_permalink( $yadak_parent ) ) . '">' . esc_html( get_the_title( $yadak_parent ) ) . '</a>';
					}
					?>
					/ <span aria-current="page"><?php the_title(); ?></span>
				</nav>
				<?php the_title( '<h1 class="yadak-page__title">', '</h1>' ); ?>
				<?php if ( has_excerpt() ) : ?>
					<p class="yadak-page__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( $yadak_is_store_page ) : ?>
			<div class="page-content"><?php the_content(); ?></div>
		<?php else : ?>
			<div class="yadak-page__layout">
				<article class="yadak-page__body page-content yadak-prose">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</article>
				<aside class="yadak-page__aside">
					<div class="yadak-help">
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/brand/emblem-128.webp' ); ?>" width="64" height="64" alt="" loading="lazy">
						<h2><?php esc_html_e( 'قطعه درست را پیدا نکردید؟', 'yadak-child' ); ?></h2>
						<p><?php esc_html_e( 'شماره فنی، مدل و سال خودرو را بفرستید؛ کارشناس ما قطعه سازگار را پیدا می‌کند.', 'yadak-child' ); ?></p>
						<?php if ( yadak_opt( 'phone' ) ) : ?>
							<a class="yadak-btn yadak-btn--accent" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', yadak_opt( 'phone' ) ) ); ?>"><?php echo yadak_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span dir="ltr"><?php echo esc_html( yadak_opt( 'phone' ) ); ?></span></a>
						<?php endif; ?>
						<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
							<a class="yadak-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'جستجو در فروشگاه', 'yadak-child' ); ?></a>
						<?php endif; ?>
					</div>
				</aside>
			</div>
		<?php endif; ?>

		<?php comments_template(); ?>
	</div>
</main>
	<?php
endwhile;
