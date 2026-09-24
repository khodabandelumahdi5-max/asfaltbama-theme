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

	$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words   = asfaltbama_count_words( $content );

	// Reading speed in words per minute.
	$wpm     = (int) apply_filters( 'asfaltbama_reading_time_wpm', 200 );
	$minutes = max( 1, (int) ceil( $words / max( 1, $wpm ) ) );

	return '<div class="webrra-reading-time">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
                <span>' . esc_html( number_format_i18n( $minutes ) ) . ' دقیقه مطالعه</span>
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
 * Register shortcodes.
 *
 * @return void
 */
function asfaltbama_register_shortcodes() {
	add_shortcode( 'reading_time', 'asfaltbama_reading_time_shortcode' );
	add_shortcode( 'article_category_badge', 'asfaltbama_article_category_badge_shortcode' );
}
add_action( 'init', 'asfaltbama_register_shortcodes' );
