<?php
/**
 * Pack page: title, short description, price and buy button first; then
 * specifications, benefits, every book in its own block, deliverables, FAQ
 * and a second buy box.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
$bavar_id     = $product->get_id();
$bavar_topic  = get_post_meta( $bavar_id, '_bavar_topic', true );
$bavar_terms  = get_post_meta( $bavar_id, '_bavar_access_terms', true );
$bavar_books  = class_exists( 'Bavar_Books' ) ? Bavar_Books::for_pack( $bavar_id ) : [];
$bavar_count  = class_exists( 'Bavar_Books' ) ? Bavar_Books::count( $bavar_id ) : count( $bavar_books );
$bavar_total  = count( $bavar_books );
$bavar_files  = $product->is_downloadable() ? count( $product->get_downloads() ) : 0;
$bavar_part   = function ( $part ) use ( $product ) {
	get_template_part(
		'template-parts/product-parts',
		null,
		[
			'product' => $product,
			'part'    => $part,
			'num'     => '',
		]
	);
};
?>
<section class="bv-product__hero">
	<div class="bv-wrap bv-product__hero-grid">
		<figure class="bv-product__image bv-reveal bv-reveal--image">
			<?php
			if ( $product->get_image_id() ) {
				echo wp_get_attachment_image( $product->get_image_id(), 'large', false, [ 'fetchpriority' => 'high' ] );
			} else {
				echo '<span class="bv-product__ph">پک کتاب<br>گروه باور</span>';
			}
			?>
		</figure>
		<div class="bv-product__summary">
			<p class="bv-eyebrow bv-reveal">پک کتاب</p>
			<h1 class="bv-product__title bv-reveal"><?php the_title(); ?></h1>
			<div class="bv-product__short bv-reveal"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<div class="bv-product__buy bv-reveal">
				<p class="bv-product__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
				<?php get_template_part( 'template-parts/buy', null, [ 'product' => $product, 'label' => 'خرید این پک' ] ); ?>
			</div>
			<p class="bv-product__note bv-reveal">این پک به‌صورت یک مجموعه‌ی کامل خریداری می‌شود.</p>
		</div>
	</div>
</section>

<section class="bv-product__section">
	<div class="bv-wrap bv-wrap--narrow bv-reveal">
		<p class="bv-eyebrow">مشخصات پک</p>
		<dl class="bv-facts">
			<div><dt>نام پک</dt><dd><?php the_title(); ?></dd></div>
			<?php if ( $bavar_topic ) : ?>
				<div><dt>موضوع</dt><dd><?php echo esc_html( $bavar_topic ); ?></dd></div>
			<?php endif; ?>
			<?php if ( $bavar_count ) : ?>
				<div><dt>تعداد کتاب‌ها</dt><dd><?php echo esc_html( bavar_fa_num( $bavar_count ) ); ?> کتاب</dd></div>
			<?php endif; ?>
			<?php if ( $bavar_files ) : ?>
				<div><dt>فایل‌های قابل دریافت</dt><dd><?php echo esc_html( bavar_fa_num( $bavar_files ) ); ?> فایل</dd></div>
			<?php endif; ?>
			<div><dt>نحوه‌ی دریافت</dt><dd>بلافاصله پس از پرداخت، در حساب کاربری</dd></div>
		</dl>
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="bv-prose bv-product__about"><?php the_content(); ?></div>
		<?php endif; ?>
	</div>
</section>

<?php $bavar_part( 'benefits' ); ?>

<?php if ( $bavar_books ) : ?>
	<section class="bv-books" aria-label="کتاب‌های این پک">
		<div class="bv-wrap">
			<p class="bv-eyebrow">کتاب‌های این پک</p>
			<h2 class="bv-title"><?php echo esc_html( bavar_fa_num( $bavar_total ) ); ?> کتاب در این پک</h2>

			<nav class="bv-books__index" aria-label="فهرست کتاب‌ها">
				<?php foreach ( $bavar_books as $bavar_i => $bavar_book ) : ?>
					<a href="#book-<?php echo esc_attr( $bavar_book->ID ); ?>"><span><?php echo esc_html( bavar_fa_num( sprintf( '%02d', $bavar_i + 1 ) ) ); ?></span> <?php echo esc_html( $bavar_book->post_title ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			foreach ( $bavar_books as $bavar_i => $bavar_book ) :
				$bavar_meta      = function ( $key ) use ( $bavar_book ) {
					return get_post_meta( $bavar_book->ID, '_bavar_' . $key, true );
				};
				$bavar_checklist = bavar_lines( $bavar_meta( 'checklist' ) );
				?>
				<article class="bv-book" id="book-<?php echo esc_attr( $bavar_book->ID ); ?>">
					<span class="bv-book__num">کتاب <?php echo esc_html( bavar_fa_num( sprintf( '%02d', $bavar_i + 1 ) ) ); ?> از <?php echo esc_html( bavar_fa_num( sprintf( '%02d', $bavar_total ) ) ); ?></span>
					<div class="bv-book__grid">
						<figure class="bv-book__cover">
							<?php
							if ( has_post_thumbnail( $bavar_book ) ) {
								echo get_the_post_thumbnail( $bavar_book, 'medium_large', [ 'loading' => 'lazy' ] );
							} else {
								echo '<span class="bv-book__ph">' . esc_html( $bavar_book->post_title ) . '</span>';
							}
							?>
						</figure>
						<div class="bv-book__body">
							<h3 class="bv-book__title"><?php echo esc_html( $bavar_book->post_title ); ?></h3>
							<dl class="bv-facts">
								<?php if ( $bavar_meta( 'author' ) ) : ?>
									<div><dt>نویسنده</dt><dd><?php echo esc_html( $bavar_meta( 'author' ) ); ?></dd></div>
								<?php endif; ?>
								<?php if ( $bavar_meta( 'topic' ) ) : ?>
									<div><dt>موضوع</dt><dd><?php echo esc_html( $bavar_meta( 'topic' ) ); ?></dd></div>
								<?php endif; ?>
							</dl>

							<?php if ( trim( $bavar_book->post_content ) ) : ?>
								<div class="bv-book__block">
									<h4>معرفی کتاب</h4>
									<div class="bv-prose"><?php echo wp_kses_post( apply_filters( 'the_content', $bavar_book->post_content ) ); ?></div>
								</div>
							<?php endif; ?>

							<?php foreach ( [ 'why' => 'چرا این کتاب انتخاب شده؟', 'summary' => 'خلاصه و محتوای گروه باور' ] as $bavar_key => $bavar_label ) : ?>
								<?php if ( $bavar_meta( $bavar_key ) ) : ?>
									<div class="bv-book__block">
										<h4><?php echo esc_html( $bavar_label ); ?></h4>
										<?php echo wp_kses_post( wpautop( esc_html( $bavar_meta( $bavar_key ) ) ) ); ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>

							<?php if ( $bavar_checklist ) : ?>
								<div class="bv-book__block">
									<h4>چک‌لیست</h4>
									<ul class="bv-checklist">
										<?php foreach ( $bavar_checklist as $bavar_line ) : ?>
											<li><?php echo esc_html( $bavar_line ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>

							<?php if ( $bavar_meta( 'exercise' ) ) : ?>
								<div class="bv-book__block">
									<h4>تمرین</h4>
									<?php echo wp_kses_post( wpautop( esc_html( $bavar_meta( 'exercise' ) ) ) ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
$bavar_part( 'includes' );
$bavar_part( 'faq' );
?>

<section class="bv-buybox">
	<div class="bv-wrap bv-wrap--narrow bv-reveal">
		<h2 class="bv-buybox__title">خرید <?php the_title(); ?></h2>
		<?php if ( $bavar_count ) : ?>
			<p class="bv-muted"><?php echo esc_html( bavar_fa_num( $bavar_count ) ); ?> کتاب، یکجا</p>
		<?php endif; ?>
		<p class="bv-buybox__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
		<p class="bv-buybox__terms"><?php echo esc_html( $bavar_terms ? $bavar_terms : 'پس از پرداخت موفق، همه‌ی فایل‌های این پک (پی‌دی‌اف، صوت، ویدیو و …) بلافاصله در «محتوای خریداری‌شده من» برای شما قابل دریافت است.' ); ?></p>
		<?php get_template_part( 'template-parts/buy', null, [ 'product' => $product, 'label' => 'خرید و دریافت دسترسی' ] ); ?>
	</div>
</section>
