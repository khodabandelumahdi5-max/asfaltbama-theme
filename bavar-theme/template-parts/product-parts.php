<?php
/**
 * Shared blocks of the course and pack pages: benefits, deliverables, FAQ.
 *
 * @package BavarTheme
 *
 * @var array $args { product: WC_Product, part: benefits|includes|faq, num: string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bavar_id = $args['product']->get_id();

if ( 'benefits' === $args['part'] ) {
	$bavar_items = bavar_lines( get_post_meta( $bavar_id, '_bavar_benefits', true ) );
	if ( $bavar_items ) {
		?>
		<section class="bv-product__section">
			<div class="bv-wrap bv-wrap--narrow bv-reveal">
				<p class="bv-eyebrow"><span><?php echo esc_html( $args['num'] ); ?></span> مزایا</p>
				<ul class="bv-benefits">
					<?php foreach ( $bavar_items as $bavar_line ) : ?>
						<li><?php echo esc_html( $bavar_line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
	}
} elseif ( 'includes' === $args['part'] ) {
	$bavar_items = bavar_lines( get_post_meta( $bavar_id, '_bavar_includes', true ) );
	if ( $bavar_items ) {
		?>
		<section class="bv-product__section">
			<div class="bv-wrap bv-wrap--narrow bv-reveal">
				<p class="bv-eyebrow"><span><?php echo esc_html( $args['num'] ); ?></span> محتوای قابل دریافت</p>
				<ul class="bv-chips bv-chips--start">
					<?php foreach ( $bavar_items as $bavar_line ) : ?>
						<li><?php echo esc_html( $bavar_line ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="bv-muted">پس از خرید، همه‌ی این محتوا در «محتوای خریداری‌شده من» در حساب کاربری شما قرار می‌گیرد.</p>
			</div>
		</section>
		<?php
	}
} elseif ( 'faq' === $args['part'] ) {
	$bavar_items = class_exists( 'Bavar_Products' ) ? Bavar_Products::faq( $bavar_id ) : [];
	if ( $bavar_items ) {
		?>
		<section class="bv-product__section">
			<div class="bv-wrap bv-wrap--narrow bv-reveal">
				<p class="bv-eyebrow"><span><?php echo esc_html( $args['num'] ); ?></span> سؤالات متداول</p>
				<div class="bv-faq">
					<?php foreach ( $bavar_items as $bavar_qa ) : ?>
						<details>
							<summary><?php echo esc_html( $bavar_qa['q'] ); ?></summary>
							<div><?php echo wp_kses_post( wpautop( esc_html( $bavar_qa['a'] ) ) ); ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
