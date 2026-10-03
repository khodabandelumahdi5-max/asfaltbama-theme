<?php
/**
 * 301 fallbacks for URL aliases.
 *
 * The header menu (an Elementor HTML widget) links to /about-us/,
 * /articles/ and /services/. Until those pages exist, and for old URLs
 * such as the Persian-slug About page, send visitors to whichever page of
 * the same group does exist instead of a 404. Only runs on 404s, so real
 * pages always win.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Groups of interchangeable page slugs, preferred slug first.
 *
 * @return array[]
 */
function asfaltbama_alias_groups() {
	return apply_filters(
		'asfaltbama_alias_groups',
		[
			[ 'about-us', 'درباره-ما' ],
			[ 'articles', 'blog', 'مقالات' ],
			[ 'services', 'خدمات' ],
		]
	);
}

/**
 * URL of the first published page in a group, other than $skip.
 *
 * @param string[] $group Page slugs.
 * @param string   $skip  Slug that was requested.
 *
 * @return string Empty string when none exists.
 */
function asfaltbama_alias_target( $group, $skip ) {
	foreach ( $group as $slug ) {
		if ( $slug === $skip ) {
			continue;
		}
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
	}

	// The posts page may use a slug outside the group.
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page && in_array( 'articles', $group, true ) ) {
		return get_permalink( $posts_page );
	}

	return '';
}

/**
 * Redirect 404s that match an alias group.
 *
 * @return void
 */
function asfaltbama_alias_redirect() {
	if ( ! is_404() ) {
		return;
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$slug = strtolower( trim( rawurldecode( (string) $path ), '/' ) );
	if ( '' === $slug || false !== strpos( $slug, '/' ) ) {
		return;
	}

	foreach ( asfaltbama_alias_groups() as $group ) {
		if ( ! in_array( $slug, $group, true ) ) {
			continue;
		}
		$target = asfaltbama_alias_target( $group, $slug );
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
		return;
	}

	// Old Persian-slug articles that Google still sends visitors to
	// (e.g. «قیمت-آسفالت-در-سال-۱۴۰۵-راهنمای-جامع-…»): send them to the
	// current article on the same topic instead of a 404.
	foreach ( asfaltbama_topic_redirects() as $needle => $target_slug ) {
		if ( false === mb_strpos( $slug, $needle ) ) {
			continue;
		}
		$post = get_page_by_path( $target_slug, OBJECT, 'post' );
		if ( $post && 'publish' === $post->post_status ) {
			wp_safe_redirect( get_permalink( $post ), 301 );
			exit;
		}
		return;
	}
}

/**
 * Topic words in old Persian slugs => slug of the current article.
 * The first match wins, so more specific words come first.
 *
 * @return array<string,string>
 */
function asfaltbama_topic_redirects() {
	return apply_filters(
		'asfaltbama_topic_redirects',
		[
			'قیرگونی'      => 'bitumen-roofing-price',
			'ایزوگام'      => 'isogam-price-guide',
			'درزگیری'      => 'crack-sealing-cost',
			'لکه-گیری'     => 'asphalt-patching-guide',
			'گودبرداری'    => 'deep-excavation-price',
			'خاکبرداری'    => 'excavation-cost-guide',
			'تخریب'        => 'demolition-cost-guide',
			'ضایعات'       => 'scrap-iron-selling-guide',
			'نخاله'        => 'construction-debris-removal',
			'قیمت-آسفالت' => 'asphalt-price-per-ton',
			'آسفالت'       => 'asphalt-price-per-ton',
		]
	);
}
add_action( 'template_redirect', 'asfaltbama_alias_redirect', 1 );

/**
 * Old archive URLs that used to land on the home page.
 *
 * - /author/<login>/… listed the articles; Rank Math sent it to the home
 *   page, so searches that matched an article landed on the home page.
 *   Send it to the articles list instead.
 * - /page/2/, /page/3/… on the static front page answered 200 with the
 *   home page content (endless duplicates): send them to the home page.
 *
 * Runs on parse_request, before Rank Math's own author redirect.
 *
 * @param WP $wp Current request.
 * @return void
 */
function asfaltbama_archive_redirects( $wp ) {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( preg_match( '#^author(/|$)#', $path ) ) {
		wp_safe_redirect( asfaltbama_articles_url(), 301 );
		exit;
	}

	if ( preg_match( '#^page/\d+$#', $path ) && 'page' === get_option( 'show_on_front' ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'parse_request', 'asfaltbama_archive_redirects', 0 );
