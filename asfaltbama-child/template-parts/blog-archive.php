<?php
/**
 * Articles listing: the posts page (/articles/) and category archives.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$paged       = max( 1, (int) get_query_var( 'paged' ) );
$is_category = is_category();
$current_cat = $is_category ? get_queried_object() : null;

if ( $is_category ) {
	$heading = 'مقالات ' . single_cat_title( '', false );
	$intro   = wp_strip_all_tags( category_description() );
} elseif ( is_archive() ) {
	$heading = wp_strip_all_tags( get_the_archive_title() );
	$intro   = wp_strip_all_tags( get_the_archive_description() );
} else {
	$heading    = 'مقالات تخصصی آسفالت، زیرسازی و عایق‌کاری';
	$posts_page = get_post( (int) get_option( 'page_for_posts' ) );
	$intro      = $posts_page && $posts_page->post_excerpt ? $posts_page->post_excerpt : '';
}

if ( ! $intro ) {
	$intro = 'راهنماهای کاربردی تیم فنی آسفالت با ما درباره‌ی قیمت و انواع آسفالت، زیرسازی و تراکم بستر، درزگیری ترک‌ها و اجرای اصولی ایزوگام؛ برای این‌که پیش از شروع پروژه، تصمیم درست بگیرید.';
}

if ( $paged > 1 ) {
	$heading .= ' – صفحه‌ی ' . asfaltbama_fa_digits( $paged );
}

$categories = get_categories(
	[
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	]
);
?>
<main id="content" class="abm-blog">
	<header class="abm-hero">
		<div class="abm-wrap">
			<?php asfaltbama_breadcrumbs(); ?>
			<h1 class="abm-hero__title"><?php echo esc_html( $heading ); ?></h1>
			<p class="abm-hero__lead"><?php echo esc_html( $intro ); ?></p>

			<?php if ( $categories ) : ?>
				<nav class="abm-chips" aria-label="دسته‌بندی مقالات">
					<a class="abm-chip<?php echo $is_category ? '' : ' is-active'; ?>" href="<?php echo esc_url( asfaltbama_articles_url() ); ?>"<?php echo $is_category ? '' : ' aria-current="page"'; ?>>همه‌ی مقالات</a>
					<?php foreach ( $categories as $cat ) : ?>
						<?php $active = $current_cat && (int) $current_cat->term_id === (int) $cat->term_id; ?>
						<a class="abm-chip<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $cat->name ); ?>
							<span class="abm-chip__count"><?php echo esc_html( asfaltbama_fa_digits( $cat->count ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
	</header>

	<div class="abm-wrap abm-blog__body">
		<?php if ( have_posts() ) : ?>
			<?php
			$show_featured = is_home() && 1 === $paged;
			if ( $show_featured ) :
				the_post();
				$featured_cat = asfaltbama_primary_category();
				?>
				<article class="abm-feature">
					<a class="abm-feature__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php
						if ( has_post_thumbnail() ) {
							the_post_thumbnail(
								'large',
								[
									'fetchpriority' => 'high',
									'alt'           => '',
								]
							);
						} else {
							echo '<span class="abm-card__placeholder"><span>' . esc_html( $featured_cat ? $featured_cat->name : 'آسفالت با ما' ) . '</span></span>';
						}
						?>
					</a>
					<div class="abm-feature__body">
						<span class="abm-feature__badge">تازه‌ترین مقاله</span>
						<h2 class="abm-feature__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="abm-feature__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 40 ) ); ?></p>
						<div class="abm-feature__meta">
							<?php if ( $featured_cat ) : ?>
								<span><?php echo esc_html( $featured_cat->name ); ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( asfaltbama_fa_digits( asfaltbama_reading_minutes() ) ); ?> دقیقه مطالعه</span>
							<time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( asfaltbama_date( get_post()->post_modified ) ); ?></time>
						</div>
						<a class="abm-btn abm-btn--amber" href="<?php the_permalink(); ?>">خواندن مقاله</a>
					</div>
				</article>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="abm-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						echo asfaltbama_post_card( get_post(), 'h2' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function.
					endwhile;
					?>
				</div>
			<?php endif; ?>

			<?php
			the_posts_pagination(
				[
					'mid_size'           => 1,
					'prev_text'          => 'قبلی',
					'next_text'          => 'بعدی',
					'screen_reader_text' => 'صفحه‌بندی مقالات',
					'class'              => 'abm-pagination',
				]
			);
			?>
		<?php else : ?>
			<p class="abm-empty">هنوز مقاله‌ای در این بخش منتشر نشده است.</p>
		<?php endif; ?>

		<?php echo asfaltbama_cta( 'band' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>
	</div>
</main>
