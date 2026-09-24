<?php
/**
 * Single article (post type "post" only; pages keep Elementor/Hello).
 *
 * Elementor Theme Builder single templates, if ever created, still win.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'single' ) ) {
	get_footer();
	return;
}

while ( have_posts() ) :
	the_post();

	$cat      = asfaltbama_primary_category();
	$content  = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core hook.
	$parts    = asfaltbama_toc( $content );
	$content  = $parts[0];
	$toc      = $parts[1];
	$updated  = get_the_modified_date( 'U' ) > get_the_date( 'U' ) + DAY_IN_SECONDS;
	$services = asfaltbama_service_links();
	?>
	<main id="content" class="abm-post">
		<header class="abm-hero abm-hero--post">
			<div class="abm-wrap abm-wrap--narrow">
				<?php asfaltbama_breadcrumbs(); ?>
				<?php if ( $cat ) : ?>
					<a class="abm-badge abm-badge--hero" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endif; ?>
				<h1 class="abm-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="abm-hero__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<ul class="abm-post__meta">
					<li><?php echo esc_html( asfaltbama_fa_digits( asfaltbama_reading_minutes() ) ); ?> دقیقه مطالعه</li>
					<li>
						<?php if ( $updated ) : ?>
							به‌روزرسانی: <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( asfaltbama_date( get_post()->post_modified ) ); ?></time>
						<?php else : ?>
							انتشار: <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( asfaltbama_date( get_post()->post_date ) ); ?></time>
						<?php endif; ?>
					</li>
					<li>نویسنده: <?php echo esc_html( asfaltbama_author_name() ); ?></li>
				</ul>
			</div>
		</header>

		<div class="abm-wrap abm-post__layout">
			<article <?php post_class( 'abm-post__article' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="abm-post__cover">
						<?php the_post_thumbnail( 'large', [ 'fetchpriority' => 'high' ] ); ?>
					</figure>
				<?php endif; ?>

				<?php if ( count( $toc ) > 1 ) : ?>
					<details class="abm-toc abm-toc--inline">
						<summary>فهرست مطالب</summary>
						<?php asfaltbama_toc_list( $toc ); ?>
					</details>
				<?php endif; ?>

				<div class="abm-prose">
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?>
				</div>

				<?php
				wp_link_pages(
					[
						'before' => '<nav class="abm-pagination" aria-label="صفحات مقاله">',
						'after'  => '</nav>',
					]
				);
				?>

				<?php echo asfaltbama_cta( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>

				<?php
				$tags = get_the_tags();
				if ( $tags ) :
					?>
					<ul class="abm-tags" aria-label="برچسب‌ها">
						<?php foreach ( $tags as $tag ) : ?>
							<li><a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<nav class="abm-postnav" aria-label="مقاله‌های قبلی و بعدی">
					<?php
					previous_post_link( '<div class="abm-postnav__prev"><span>مقاله‌ی قبلی</span>%link</div>' );
					next_post_link( '<div class="abm-postnav__next"><span>مقاله‌ی بعدی</span>%link</div>' );
					?>
				</nav>
			</article>

			<aside class="abm-post__aside" aria-label="ابزارهای مقاله">
				<div class="abm-post__sticky">
					<?php if ( count( $toc ) > 1 ) : ?>
						<nav class="abm-toc abm-panel" aria-label="فهرست مطالب">
							<p class="abm-panel__title">فهرست مطالب</p>
							<?php asfaltbama_toc_list( $toc ); ?>
						</nav>
					<?php endif; ?>

					<div class="abm-panel abm-panel--dark">
						<p class="abm-panel__title">مشاوره‌ی رایگان</p>
						<p>برای بازدید و برآورد قیمت پروژه با کارشناسان ما تماس بگیرید.</p>
						<a class="abm-btn abm-btn--amber abm-btn--block" href="tel:<?php echo esc_attr( ASFALTBAMA_PHONE ); ?>">تماس: <span dir="ltr"><?php echo esc_html( ASFALTBAMA_PHONE_DISPLAY ); ?></span></a>
						<a class="abm-btn abm-btn--ghost abm-btn--block" href="<?php echo esc_url( ASFALTBAMA_WHATSAPP ); ?>" target="_blank" rel="noopener">مشاوره در واتساپ</a>
					</div>

					<nav class="abm-panel" aria-label="خدمات آسفالت با ما">
						<p class="abm-panel__title">خدمات ما</p>
						<ul class="abm-links">
							<?php foreach ( $services as $service ) : ?>
								<li><a href="<?php echo esc_url( home_url( $service[1] ) ); ?>"><?php echo esc_html( $service[0] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</div>
			</aside>
		</div>

		<?php
		$related = asfaltbama_related_posts( 3 );
		if ( $related ) :
			?>
			<section class="abm-related" aria-labelledby="abm-related-title">
				<div class="abm-wrap">
					<h2 id="abm-related-title" class="abm-section-title">مقاله‌های مرتبط</h2>
					<div class="abm-grid">
						<?php
						foreach ( $related as $related_post ) {
							echo asfaltbama_post_card( $related_post, 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function.
						}
						?>
					</div>
					<p class="abm-related__all"><a class="abm-btn abm-btn--dark" href="<?php echo esc_url( asfaltbama_articles_url() ); ?>">همه‌ی مقالات</a></p>
				</div>
			</section>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer();
