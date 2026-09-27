<?php
/**
 * English and Arabic guides hubs (manifest pages with "layout":
 * "article-hub"): the articles hero and the page content, which lists the
 * guides with [abm_articles]. Loaded by includes/articles-intl.php.
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
	?>
	<main id="content" class="abm-blog abm-post abm-post--intl abm-guides" lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
		<header class="abm-hero">
			<div class="abm-wrap">
				<nav class="abm-crumbs" aria-label="<?php echo esc_attr( asfaltbama_i18n( 'crumbs', $lang ) ); ?>"><ol>
					<li><a href="<?php echo esc_url( asfaltbama_intl_home_url( $lang ) ); ?>"><?php echo esc_html( asfaltbama_i18n( 'home', $lang ) ); ?></a></li>
					<li aria-current="page"><?php echo esc_html( asfaltbama_i18n( 'guides', $lang ) ); ?></li>
				</ol></nav>
				<h1 class="abm-hero__title"><?php the_title(); ?></h1>
				<?php if ( ! empty( $item['excerpt'] ) ) : ?>
					<p class="abm-hero__lead"><?php echo esc_html( $item['excerpt'] ); ?></p>
				<?php endif; ?>
			</div>
		</header>
		<div class="abm-wrap abm-guides__body">
			<?php the_content(); ?>
		</div>
	</main>
	<?php
endwhile;

get_footer();
