<?php
/**
 * Site shortcodes: [reading_time] and [article_category_badge].
 *
 * Moved here from the parent theme's functions.php so they survive
 * Hello Elementor updates. Function names are new on purpose: if an old,
 * edited copy of the parent theme still declares the webrra_* functions,
 * there is no "cannot redeclare" fatal error, and the registrations below
 * (on init) replace the parent's.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Count words in a string, including Persian/Arabic script.
 *
 * PHP's str_word_count() only understands ASCII letters and returns ~0 for
 * Persian text. Here a word is a run of Unicode letters, marks and digits;
 * the zero-width non-joiner (نیم‌فاصله) keeps compound words together.
 *
 * @param string $text Plain text.
 *
 * @return int
 */
function asfaltbama_count_words( $text ) {
	$count = preg_match_all( '/[\p{L}\p{M}\p{N}\x{200C}]+/u', $text );

	return false === $count ? 0 : $count;
}

/**
 * [reading_time] – estimated reading time of the current post.
 *
 * @return string
 */
function asfaltbama_reading_time_shortcode() {
	if ( ! is_singular() ) {
		return '';
	}

	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$minutes = asfaltbama_reading_minutes( $post );

	return '<div class="webrra-reading-time">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
                <span>' . esc_html( asfaltbama_fa_digits( $minutes ) ) . ' دقیقه مطالعه</span>
            </div>';
}

/**
 * [article_category_badge] – name of the first category of the current post.
 *
 * @return string
 */
function asfaltbama_article_category_badge_shortcode() {
	$categories = get_the_category();

	if ( empty( $categories ) ) {
		return '';
	}

	return '<div class="card-category-badge">' . esc_html( $categories[0]->name ) . '</div>';
}

/**
 * [asfaltbama_latest_posts count="3" title="..."] – latest articles as
 * cards, with links to the articles and about pages. Meant for the home
 * page (Elementor "Shortcode" widget).
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function asfaltbama_latest_posts_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'count' => 3,
			'title' => 'آخرین مقالات',
		],
		$atts,
		'asfaltbama_latest_posts'
	);

	$posts = get_posts(
		[
			'numberposts'      => max( 1, min( 12, (int) $atts['count'] ) ),
			'post_status'      => 'publish',
			'suppress_filters' => false,
		]
	);

	if ( empty( $posts ) ) {
		return '';
	}

	$about     = get_page_by_path( 'about-us' );
	$about_url = $about ? get_permalink( $about ) : home_url( '/about-us/' );

	$html  = '<section class="abm-latest" aria-labelledby="abm-latest-title"><div class="abm-wrap">';
	$html .= '<h2 id="abm-latest-title" class="abm-section-title">' . esc_html( $atts['title'] ) . '</h2>';
	$html .= '<div class="abm-grid">';
	foreach ( $posts as $post ) {
		$html .= asfaltbama_post_card( $post, 'h3' );
	}
	$html .= '</div>';
	$html .= '<p class="abm-latest__links">';
	$html .= '<a class="abm-btn abm-btn--dark" href="' . esc_url( asfaltbama_articles_url() ) . '">همه‌ی مقالات</a>';
	$html .= '<a class="abm-btn abm-btn--amber" href="' . esc_url( $about_url ) . '">درباره‌ی آسفالت با ما</a>';
	$html .= '</p></div></section>';

	return $html;
}

/**
 * Register shortcodes.
 *
 * @return void
 */
function asfaltbama_register_shortcodes() {
	add_shortcode( 'reading_time', 'asfaltbama_reading_time_shortcode' );
	add_shortcode( 'article_category_badge', 'asfaltbama_article_category_badge_shortcode' );
	add_shortcode( 'asfaltbama_latest_posts', 'asfaltbama_latest_posts_shortcode' );
}
add_action( 'init', 'asfaltbama_register_shortcodes' );
