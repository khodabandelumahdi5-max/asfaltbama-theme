<?php
/**
 * Page speed fixes found by Lighthouse on the home page:
 *
 * - the hero background (the LCP element) was a 2560px, ~680 KB CSS
 *   background that the browser only found after the Elementor CSS loaded;
 *   it is now preloaded with high priority, and phones and tablets get a
 *   smaller copy of the same image;
 * - WordPress gave fetchpriority="high" to a small badge image instead;
 * - an off-screen image above the article grid was not lazy-loaded;
 * - the Elementor loop carousel has role="list" with no list items (an
 *   accessibility error);
 * - the skip link pointed at #content, which Elementor pages do not have;
 * - static files were sent with "Cache-Control: private", so browsers
 *   downloaded fonts, scripts and images again on every visit.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The front page hero: the first top-level Elementor element with a
 * background image.
 *
 * @return array{el:string,id:int,page:int}|null
 */
function asfaltbama_front_hero() {
	static $hero = false;
	if ( false !== $hero ) {
		return $hero;
	}
	$hero = null;
	$page = (int) get_option( 'page_on_front' );
	$data = $page ? json_decode( (string) get_post_meta( $page, '_elementor_data', true ), true ) : null;
	if ( ! is_array( $data ) ) {
		return $hero;
	}
	foreach ( array_slice( $data, 0, 2 ) as $el ) {
		$bg = $el['settings']['background_image'] ?? null;
		if ( ! empty( $bg['id'] ) && ! empty( $el['id'] ) ) {
			$hero = [
				'el'   => (string) $el['id'],
				'id'   => (int) $bg['id'],
				'page' => $page,
			];
			break;
		}
	}
	return $hero;
}

/**
 * Preload the hero image and give smaller screens a smaller copy.
 *
 * @return void
 */
function asfaltbama_hero_preload() {
	if ( ! is_front_page() ) {
		return;
	}
	$hero = asfaltbama_front_hero();
	if ( ! $hero ) {
		return;
	}
	$full   = wp_get_attachment_image_src( $hero['id'], 'full' );
	$tablet = wp_get_attachment_image_src( $hero['id'], 'large' );
	$phone  = wp_get_attachment_image_src( $hero['id'], 'medium_large' );
	if ( ! $full ) {
		return;
	}
	$wide   = wp_get_attachment_image_src( $hero['id'], '1536x1536' );
	$tablet = $tablet ? $tablet[0] : $full[0];
	$phone  = $phone ? $phone[0] : $tablet;
	// Laptops (up to 1600px wide) get the 1536px copy; only larger screens the full image.
	$wide = $wide ? $wide[0] : $full[0];

	// One preload per breakpoint, so each device fetches only its own copy.
	printf( '<link rel="preload" as="image" href="%s" media="(max-width: 767px)" fetchpriority="high">' . "\n", esc_url( $phone ) );
	printf( '<link rel="preload" as="image" href="%s" media="(min-width: 768px) and (max-width: 1024px)" fetchpriority="high">' . "\n", esc_url( $tablet ) );
	printf( '<link rel="preload" as="image" href="%s" media="(min-width: 1025px) and (max-width: 1600px)" fetchpriority="high">' . "\n", esc_url( $wide ) );
	printf( '<link rel="preload" as="image" href="%s" media="(min-width: 1601px)" fetchpriority="high">' . "\n", esc_url( $full[0] ) );

	$sel = sprintf( '.elementor-%1$d .elementor-element.elementor-element-%2$s:not(.elementor-motion-effects-element-type-background)', $hero['page'], preg_replace( '/[^a-z0-9]/', '', $hero['el'] ) );
	printf(
		"<style id=\"abm-hero-bg\">@media (max-width:767px){%1\$s{background-image:url(\"%2\$s\")!important}}@media (min-width:768px) and (max-width:1024px){%1\$s{background-image:url(\"%3\$s\")!important}}@media (min-width:1025px) and (max-width:1600px){%1\$s{background-image:url(\"%4\$s\")!important}}</style>\n",
		$sel, // Built from sanitized parts above.
		esc_url( $phone ),
		esc_url( $tablet ),
		esc_url( $wide )
	);
}
add_action( 'wp_head', 'asfaltbama_hero_preload', 1 );

/**
 * HTML fixes, applied in the page buffer (see site-fixes.php).
 *
 * @param string $html Page HTML.
 * @return string
 */
function asfaltbama_perf_html( $html ) {
	// Carousel wrapper: role="list" without listitem children.
	$html = preg_replace( '/(<div class="swiper elementor-loop-container[^"]*")\s+role="list"/', '$1', $html );

	// Skip link target: Elementor full-width pages have no #content, and no
	// <main> either, so the page content is also marked as the main landmark.
	if ( false === strpos( $html, 'id="content"' ) ) {
		$main = false === strpos( $html, '<main' ) ? ' role="main"' : '';
		$html = preg_replace( '/<div data-elementor-type="wp-page"/', '<div id="content" tabindex="-1"' . $main . ' data-elementor-type="wp-page"', $html, 1 );
	}

	if ( is_front_page() && asfaltbama_front_hero() ) {
		// The hero background is the priority image now.
		$html = str_replace( 'fetchpriority="high" ', '', $html );

		// Lazy-load every content image after the first two.
		$n    = 0;
		$html = preg_replace_callback(
			'/<img\b(?![^>]*\bloading=)[^>]*>/',
			function ( $m ) use ( &$n ) {
				return ++$n > 2 ? str_replace( '<img ', '<img loading="lazy" ', $m[0] ) : $m[0];
			},
			$html
		);
	}
	return $html;
}

/**
 * Browser caching for static files, written to .htaccess between markers
 * (WordPress's own insert_with_markers, the way it writes its rewrite
 * rules). Every directive is inside <IfModule>, so a server without the
 * module ignores it. Removed again when the theme is switched off.
 *
 * @return void
 */
function asfaltbama_cache_rules() {
	if ( get_option( 'asfaltbama_cache_rules' ) === ASFALTBAMA_CHILD_VERSION ) {
		return;
	}
	update_option( 'asfaltbama_cache_rules', ASFALTBAMA_CHILD_VERSION, false );

	$file = ABSPATH . '.htaccess';
	if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	insert_with_markers(
		$file,
		'Asfaltbama Cache',
		[
			'<IfModule mod_expires.c>',
			'ExpiresActive On',
			'ExpiresByType font/woff2 "access plus 1 year"',
			'ExpiresByType text/css "access plus 1 year"',
			'ExpiresByType application/javascript "access plus 1 year"',
			'ExpiresByType text/javascript "access plus 1 year"',
			'ExpiresByType image/webp "access plus 6 months"',
			'ExpiresByType image/jpeg "access plus 6 months"',
			'ExpiresByType image/png "access plus 6 months"',
			'ExpiresByType image/svg+xml "access plus 6 months"',
			'ExpiresByType image/x-icon "access plus 6 months"',
			'ExpiresByType video/mp4 "access plus 6 months"',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'<FilesMatch "\.(woff2|css|js)$">',
			'Header set Cache-Control "public, max-age=31536000"',
			'</FilesMatch>',
			'<FilesMatch "\.(webp|jpe?g|png|svg|ico|mp4)$">',
			'Header set Cache-Control "public, max-age=15552000"',
			'</FilesMatch>',
			'</IfModule>',
		]
	);
}
add_action( 'admin_init', 'asfaltbama_cache_rules' );

/**
 * Take the caching rules out when the theme is switched off.
 *
 * @return void
 */
function asfaltbama_cache_rules_remove() {
	$file = ABSPATH . '.htaccess';
	if ( file_exists( $file ) && is_writable( $file ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		insert_with_markers( $file, 'Asfaltbama Cache', [] );
	}
	delete_option( 'asfaltbama_cache_rules' );
}
add_action( 'switch_theme', 'asfaltbama_cache_rules_remove' );

/**
 * The spam-protection script of the contact form does not need to block
 * rendering: defer it.
 *
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 * @param string $src    Source.
 * @return string
 */
function asfaltbama_defer_scripts( $tag, $handle, $src ) {
	if ( false !== strpos( $src, 'spam-protect-for-contact-form7' ) && false === strpos( $tag, ' defer' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'asfaltbama_defer_scripts', 10, 3 );

/**
 * Clear the page cache (LiteSpeed Cache or WP Super Cache, if used) when prices are
 * saved, so visitors see the new table at once.
 *
 * @return void
 */
function asfaltbama_purge_page_cache() {
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'wp_cache_clear_cache' ) ) { // WP Super Cache.
		wp_cache_clear_cache();
	}
}
add_action( 'update_option_asfaltbama_prices', 'asfaltbama_purge_page_cache' );

