<?php
/**
 * English and Arabic articles (manifest pages with "layout": "article"),
 * with the design of the Persian articles (single.php) in their own
 * language and direction. Loaded by includes/articles-intl.php.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

$item = asfaltbama_current_manifest_page();
$lang = 'ar' === ( $item['lang'] ?? '' ) ? 'ar' : 'en';
$dir  = 'ar' === $lang ? 'rtl' : 'ltr';

while ( have_posts() ) :
	the_post();

	$content  = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core hook.
	$parts    = asfaltbama_toc( $content );
	$content  = $parts[0];
	$toc      = $parts[1];
	$words    = asfaltbama_count_words( wp_strip_all_tags( strip_shortcodes( get_post()->post_content ) ) );
	$minutes  = max( 1, (int) ceil( $words / ( 'en' === $lang ? 230 : 200 ) ) );
	$updated  = get_the_modified_date( 'U' ) > get_the_date( 'U' ) + DAY_IN_SECONDS;
	$versions = asfaltbama_article_versions();
	$wa       = ASFALTBAMA_WHATSAPP . '?text=' . rawurlencode( sprintf( asfaltbama_i18n( 'wa_text', $lang ), get_the_title() ) );
	$mail     = 'mailto:ofoghapadanapasargad@gmail.com?subject=' . rawurlencode( get_the_title() );
	$toc_list = function () use ( $toc ) {
		echo '<ol class="abm-toc__list">';
		foreach ( $toc as $entry ) {
			printf( '<li><a href="#%s">%s</a></li>', esc_attr( $entry[0] ), esc_html( $entry[1] ) );
		}
		echo '</ol>';
	};
	?>
	<main id="content" class="abm-post abm-post--intl" lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
		<header class="abm-hero abm-hero--post">
			<div class="abm-wrap abm-wrap--narrow">
				<nav class="abm-crumbs" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'crumbs', $lang ) ); ?>"><ol>
					<li><a href="<?php echo esc_url( asfaltbama_intl_home_url( $lang ) ); ?>"><?php echo esc_html( asfaltbama_i18n( 'home', $lang ) ); ?></a></li>
					<li><a href="<?php echo esc_url( asfaltbama_guides_hub_url( $lang ) ); ?>"><?php echo esc_html( asfaltbama_i18n( 'guides', $lang ) ); ?></a></li>
					<li aria-current="page"><?php the_title(); ?></li>
				</ol></nav>
				<span class="abm-badge abm-badge--hero"><?php echo esc_html( asfaltbama_intl_category( $item['category'] ?? '', $lang ) ); ?></span>
				<h1 class="abm-hero__title"><?php the_title(); ?></h1>
				<?php if ( ! empty( $item['excerpt'] ) ) : ?>
					<p class="abm-hero__lead"><?php echo esc_html( $item['excerpt'] ); ?></p>
				<?php endif; ?>
				<ul class="abm-post__meta">
					<li><?php echo esc_html( sprintf( asfaltbama_i18n( 'minutes', $lang ), asfaltbama_lang_digits( $minutes, $lang ) ) ); ?></li>
					<li>
						<?php if ( $updated ) : ?>
							<?php echo esc_html( asfaltbama_i18n( 'updated', $lang ) ); ?> <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( asfaltbama_intl_date( get_post()->post_modified, $lang ) ); ?></time>
						<?php else : ?>
							<?php echo esc_html( asfaltbama_i18n( 'published', $lang ) ); ?> <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( asfaltbama_intl_date( get_post()->post_date, $lang ) ); ?></time>
						<?php endif; ?>
					</li>
					<li><?php echo esc_html( sprintf( asfaltbama_i18n( 'author', $lang ), asfaltbama_i18n( 'team', $lang ) ) ); ?></li>
				</ul>
				<?php if ( count( $versions ) > 1 ) : ?>
					<p class="abm-langs">
						<?php if ( isset( $versions['fa'] ) ) : ?>
							<a href="<?php echo esc_url( $versions['fa'] ); ?>" hreflang="fa" lang="fa" dir="rtl"><?php echo esc_html( asfaltbama_i18n( 'lang_fa', $lang ) ); ?></a>
						<?php endif; ?>
						<?php
						$other = asfaltbama_i18n( 'other_slug', $lang );
						if ( isset( $versions[ $other ] ) ) :
							?>
							<a href="<?php echo esc_url( $versions[ $other ] ); ?>" hreflang="<?php echo esc_attr( $other ); ?>" lang="<?php echo esc_attr( $other ); ?>" dir="<?php echo 'ar' === $other ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( asfaltbama_i18n( 'lang_other', $lang ) ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</header>

		<div class="abm-wrap abm-post__layout">
			<article <?php post_class( 'abm-post__article' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="abm-post__cover">
						<?php
						the_post_thumbnail(
							'large',
							[
								'fetchpriority' => 'high',
								'alt'           => get_the_title(),
							]
						);
						?>
					</figure>
				<?php endif; ?>

				<?php if ( count( $toc ) > 1 ) : ?>
					<details class="abm-toc abm-toc--inline">
						<summary><?php echo esc_html( asfaltbama_i18n( 'toc', $lang ) ); ?></summary>
						<?php $toc_list(); ?>
					</details>
				<?php endif; ?>

				<div class="abm-prose">
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?>
				</div>

				<aside class="abm-cta abm-cta--box" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'contact', $lang ) ); ?>">
					<div class="abm-cta__text">
						<span class="abm-cta__badge"><?php echo esc_html( asfaltbama_i18n( 'cta_badge', $lang ) ); ?></span>
						<p class="abm-cta__title"><?php echo esc_html( asfaltbama_i18n( 'cta_title', $lang ) ); ?></p>
						<p class="abm-cta__desc"><?php echo esc_html( asfaltbama_i18n( 'cta_desc', $lang ) ); ?></p>
					</div>
					<div class="abm-cta__actions">
						<a class="abm-btn abm-btn--amber" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( asfaltbama_i18n( 'whatsapp', $lang ) ); ?>: <bdi dir="ltr">+98 919 129 9559</bdi></a>
						<a class="abm-btn abm-btn--ghost" href="<?php echo esc_url( $mail ); ?>"><?php echo esc_html( asfaltbama_i18n( 'email', $lang ) ); ?></a>
					</div>
				</aside>
			</article>

			<aside class="abm-post__aside" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'tools', $lang ) ); ?>">
				<div class="abm-post__sticky">
					<?php if ( count( $toc ) > 1 ) : ?>
						<nav class="abm-toc abm-panel" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'toc', $lang ) ); ?>">
							<p class="abm-panel__title"><?php echo esc_html( asfaltbama_i18n( 'toc', $lang ) ); ?></p>
							<?php $toc_list(); ?>
						</nav>
					<?php endif; ?>

					<div class="abm-panel abm-panel--dark">
						<p class="abm-panel__title"><?php echo esc_html( asfaltbama_i18n( 'contact', $lang ) ); ?></p>
						<p><?php echo esc_html( asfaltbama_i18n( 'contact_p', $lang ) ); ?></p>
						<a class="abm-btn abm-btn--amber abm-btn--block" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( asfaltbama_i18n( 'whatsapp', $lang ) ); ?></a>
						<a class="abm-btn abm-btn--ghost abm-btn--block" href="<?php echo esc_url( $mail ); ?>"><?php echo esc_html( asfaltbama_i18n( 'email', $lang ) ); ?></a>
					</div>

					<nav class="abm-panel" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'services', $lang ) ); ?>">
						<p class="abm-panel__title"><?php echo esc_html( asfaltbama_i18n( 'services', $lang ) ); ?></p>
						<ul class="abm-links">
							<?php foreach ( asfaltbama_intl_service_links( $lang ) as $link ) : ?>
								<li><a href="<?php echo esc_url( home_url( $link[1] ) ); ?>"><?php echo esc_html( $link[0] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</div>
			</aside>
		</div>

		<?php
		$related = asfaltbama_intl_related( $item, 3 );
		if ( $related ) :
			?>
			<section class="abm-related" aria-labelledby="abm-related-title">
				<div class="abm-wrap">
					<h2 id="abm-related-title" class="abm-section-title"><?php echo esc_html( asfaltbama_i18n( 'related', $lang ) ); ?></h2>
					<div class="abm-grid">
						<?php
						foreach ( $related as $related_item ) {
							echo asfaltbama_intl_card( $related_item, 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function.
						}
						?>
					</div>
					<p class="abm-related__all"><a class="abm-btn abm-btn--dark" href="<?php echo esc_url( asfaltbama_guides_hub_url( $lang ) ); ?>"><?php echo esc_html( asfaltbama_i18n( 'all', $lang ) ); ?></a></p>
				</div>
			</section>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer();
