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
}
add_action( 'template_redirect', 'asfaltbama_alias_redirect', 1 );
