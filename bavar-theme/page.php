<?php
/**
 * Generic page (library, Ashiane Simorgh, consulting, checkout, MY BAVAR…).
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<main id="content" class="bv-page">
		<header class="bv-page__head">
			<div class="bv-wrap">
				<p class="bv-eyebrow" dir="ltr">BAVAR GROUP</p>
				<h1 class="bv-page__title<?php echo preg_match( '/\p{Arabic}/u', get_the_title() ) ? '' : ' bv-page__title--en'; ?>"><?php the_title(); ?></h1>
			</div>
		</header>
		<div class="bv-wrap bv-page__content">
			<?php the_content(); ?>
		</div>
	</main>
	<?php
endwhile;
get_footer();
