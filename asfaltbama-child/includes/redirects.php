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
	// Old posts used date permalinks (/2026/06/21/<slug>/): match on the slug.
	$slug = preg_replace( '#^\d{4}/\d{2}/\d{2}/#', '', $slug );
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
			'تفاوت-قیرگونی-و-ایزوگام' => 'bitumen-roofing-vs-isogam',
			'ترکخوردگی'    => 'asphalt-crack-sealing-guide',
			'ترک‌خوردگی'   => 'asphalt-crack-sealing-guide',
			'بابکت'        => 'bobcat-rental-guide',
			'کامپکت'       => 'subgrade-preparation-compaction',
			'زیرسازی'      => 'subgrade-preparation-compaction',
			'گرم-یا-سرد'   => 'cold-vs-hot-asphalt',
			'فرسوده'       => 'asphalt-patching-guide',
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

/**
 * Old URLs that Search Console still shows with impressions but that now
 * answer 404: the categories and tags of the previous theme, old service
 * pages, and the demo posts of the original template (farming articles,
 * «سلام دنیا»).
 *
 * - Old categories, tags and pages go (301) to the page on the same topic.
 * - Demo posts and the demo categories and tags of the original template
 *   (gas-oil, oil-factory, robotic) answer 410 Gone, so Google drops them
 *   faster than a 404.
 *
 * @return void
 */
function asfaltbama_legacy_redirects() {
	if ( ! is_404() ) {
		return;
	}
	$path = strtolower( trim( rawurldecode( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ), '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $path ) {
		return;
	}

	if ( preg_match( '#(farming|soil-health|irrigation|crop-yield|سلام-دنیا|hello-world|^category/(gas-oil|oil-factory)(/|$)|^tag/robotic(/|$))#u', $path ) ) {
		status_header( 410 );
		nocache_headers();
		return;
	}

	$map = apply_filters(
		'asfaltbama_legacy_redirects',
		[
			'خدمات-کامیون'          => '/machinery-rental/',
			'اجاره-ماشینآلات'       => '/machinery-rental/',
			'اجاره-ماشین‌آلات'      => '/machinery-rental/',
			'road-landscaping'      => '/site-landscaping-asphalt/',
			'category/excavation'   => '/category/excavation-grading/',
			'category/company'      => '/about-us/',
			'category/manufacture'  => '/asphalt-plant/',
			'category/guide'        => '/articles/',
			'category/asphalt'      => '/category/asphalt-paving/',
			'category/insulation'   => '/category/waterproofing-isogam/',
			'category/industry'     => '/industrial-asphalt-waterproofing/',
			'category/compaction'   => '/subgrade-preparation-compaction/',
			'tag/oil'               => '/bitumen-price/',
			'tag/construction'      => '/services/',
			'tag/factory'           => '/asphalt-plant/',
			'tag/manufacture'       => '/asphalt-plant/',
		]
	);
	foreach ( $map as $old => $target ) {
		if ( $path === $old || 0 === strpos( $path, $old . '/' ) ) {
			wp_safe_redirect( home_url( $target ), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'asfaltbama_legacy_redirects', 0 );
