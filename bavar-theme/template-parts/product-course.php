<?php
/**
 * Course sales page (e.g. «کتاب مقدس گروه باور»).
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
$bavar_id       = $product->get_id();
$bavar_subtitle = get_post_meta( $bavar_id, '_bavar_subtitle', true );
$bavar_includes = bavar_lines( get_post_meta( $bavar_id, '_bavar_includes', true ) );
$bavar_terms    = get_post_meta( $bavar_id, '_bavar_access_terms', true );
$bavar_sections = class_exists( 'Bavar_Products' ) ? Bavar_Products::course_sections() : [];
?>
<section class="bv-product__hero">
	<div class="bv-wrap bv-product__hero-grid">
		<figure class="bv-product__image bv-reveal bv-reveal--image">
			<?php
			if ( $product->get_image_id() ) {
				echo wp_get_attachment_image( $product->get_image_id(), 'large', false, [ 'fetchpriority' => 'high' ] );
			} else {
				echo '<span class="bv-product__ph" dir="ltr">BOOK OF<br>BAVAR</span>';
			}
			?>
		</figure>
		<div class="bv-product__summary">
			<p class="bv-eyebrow bv-reveal" dir="ltr">BAVAR COURSE</p>
			<h1 class="bv-product__title bv-reveal"><?php the_title(); ?></h1>
			<?php if ( $bavar_subtitle ) : ?>
				<p class="bv-product__subtitle bv-reveal"><?php echo esc_html( $bavar_subtitle ); ?></p>
			<?php endif; ?>
			<?php if ( $bavar_includes ) : ?>
				<ul class="bv-chips bv-reveal" dir="ltr">
					<?php foreach ( $bavar_includes as $bavar_line ) : ?>
						<li><?php echo esc_html( $bavar_line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="bv-product__short bv-reveal"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<p class="bv-product__price bv-reveal"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
			<div class="bv-reveal"><?php get_template_part( 'template-parts/buy', null, [ 'product' => $product, 'label' => 'خرید دوره' ] ); ?></div>
		</div>
	</div>
</section>

<?php if ( trim( get_the_content() ) ) : ?>
	<section class="bv-product__section">
		<div class="bv-wrap bv-wrap--narrow bv-prose bv-reveal"><?php the_content(); ?></div>
	</section>
<?php endif; ?>

<section class="bv-product__details">
	<div class="bv-wrap">
		<?php
		$bavar_n = 0;
		foreach ( $bavar_sections as $bavar_key => $bavar_heading ) :
			$bavar_text = get_post_meta( $bavar_id, '_bavar_sec_' . $bavar_key, true );
			if ( ! $bavar_text ) {
				continue;
			}
			++$bavar_n;
			?>
			<article class="bv-detail bv-reveal">
				<span class="bv-detail__num" dir="ltr"><?php echo esc_html( sprintf( '%02d', $bavar_n ) ); ?></span>
				<h2 class="bv-detail__title"><?php echo esc_html( $bavar_heading ); ?></h2>
				<div class="bv-detail__body"><?php echo wp_kses_post( wpautop( esc_html( $bavar_text ) ) ); ?></div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="bv-buybox">
	<div class="bv-wrap bv-wrap--narrow bv-reveal">
		<p class="bv-eyebrow" dir="ltr">ENROL</p>
		<h2 class="bv-buybox__title"><?php the_title(); ?></h2>
		<p class="bv-buybox__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
		<?php if ( $bavar_terms ) : ?>
			<p class="bv-buybox__terms"><?php echo esc_html( $bavar_terms ); ?></p>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/buy', null, [ 'product' => $product, 'label' => 'خرید دوره' ] ); ?>
	</div>
</section>
