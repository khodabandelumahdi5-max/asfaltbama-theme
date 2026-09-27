<?php
/**
 * Site-wide fixes found in the SEO audit: head clean-up, the business
 * plugin's footer links, internal links without a trailing slash, avatar
 * alt text, empty image placeholders and article links on service pages.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Head clean-up: the RSD, Windows Live Writer and shortlink tags and the
 * comments feed serve no visitor, and the emoji script is ~20 KB of
 * render-blocking JavaScript that every current browser can do without.
 */
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Service links for the footer: [label, path].
 *
 * @return array[]
 */
function asfaltbama_footer_service_links() {
	return array_values(
		array_filter(
			asfaltbama_service_links(),
			function ( $link ) {
				return '/asphalt-price-factors/' !== $link[1];
			}
		)
	);
}

/**
 * Fix the footer printed by the Asphalt Business Suite plugin:
 * - the phone lines and the «تماس سریع» button link to "#";
 * - «مقالات» points at /asphalt-cost-per-square-meter/ instead of /articles/;
 * - the services are plain text, so they pass no link value.
 *
 * @param string $html Footer HTML.
 *
 * @return string
 */
function asfaltbama_fix_business_footer( $html ) {
	$tel = 'tel:' . ASFALTBAMA_PHONE;

	$html = str_replace(
		[
			'<a href="#">☎',
			'<a href="#">📱',
			'<a class="aas-btn aas-btn-primary" href="#">',
		],
		[
			'<a href="tel:02177344068">☎',
			'<a href="' . $tel . '">📱',
			'<a class="aas-btn aas-btn-primary" href="' . $tel . '">',
		],
		$html
	);

	$html = preg_replace(
		'#href="[^"]*/asphalt-cost-per-square-meter/?"(\s*)>(\s*)مقالات#u',
		'href="' . esc_url( asfaltbama_articles_url() ) . '"$1>$2مقالات',
		$html
	);

	$links = '';
	foreach ( asfaltbama_footer_service_links() as $link ) {
		$links .= '<span><a href="' . esc_url( home_url( $link[1] ) ) . '">' . esc_html( $link[0] ) . '</a></span>';
	}

	return preg_replace(
		'#(<div class="aas-footer-card[^"]*aas-footer-services">\s*<h3>[^<]*</h3>\s*<div>).*?(</div>)#su',
		'$1' . $links . '$2',
		$html,
		1
	);
}

/**
 * Rewrite the finished page: trailing slashes on root-relative internal
 * links (the header menu links /asphalt-paving, which answers with a 301)
 * and the business plugin's footer.
 *
 * @param string $html Page HTML.
 *
 * @return string
 */
function asfaltbama_filter_page_html( $html ) {
	if ( false === stripos( $html, '<html' ) ) {
		return $html;
	}

	$html = preg_replace( '#href="(/[a-z0-9\-/]*[a-z0-9\-])"#', 'href="$1/"', $html );

	$start = strpos( $html, '<footer class="aas-site-footer"' );
	if ( false !== $start ) {
		$end = strpos( $html, '</footer>', $start );
		if ( false !== $end ) {
			$footer = substr( $html, $start, $end - $start );
			$html   = substr_replace( $html, asfaltbama_fix_business_footer( $footer ), $start, $end - $start );
		}
	}

	return $html;
}

/**
 * Buffer front-end HTML pages for asfaltbama_filter_page_html().
 *
 * @return void
 */
function asfaltbama_start_page_buffer() {
	if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_customize_preview() ) {
		return;
	}
	if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	ob_start( 'asfaltbama_filter_page_html' );
}
add_action( 'template_redirect', 'asfaltbama_start_page_buffer', 99 );

/**
 * Give author avatars an alt text (WordPress prints alt='').
 *
 * @param array $args Avatar arguments.
 *
 * @return array
 */
function asfaltbama_avatar_alt( $args ) {
	if ( empty( $args['alt'] ) ) {
		$post        = get_post();
		$args['alt'] = 'تصویر نویسنده: ' . ( $post ? asfaltbama_author_name( $post ) : 'آسفالت با ما' );
	}
	return $args;
}
add_filter( 'pre_get_avatar_data', 'asfaltbama_avatar_alt' );

/**
 * Drop Elementor image widgets that only show the grey placeholder (e.g. a
 * loop item whose post has no featured image).
 *
 * @param string                 $content Widget HTML.
 * @param \Elementor\Widget_Base $widget  Widget.
 *
 * @return string
 */
function asfaltbama_hide_placeholder_images( $content, $widget ) {
	if ( 'image' === $widget->get_name() && false !== strpos( $content, 'elementor/assets/images/placeholder.png' ) ) {
		return '';
	}
	return $content;
}
add_filter( 'elementor/widget/render_content', 'asfaltbama_hide_placeholder_images', 10, 2 );

/**
 * Article categories related to each service page.
 *
 * @return array Page slug => category slugs.
 */
function asfaltbama_service_categories() {
	return apply_filters(
		'asfaltbama_service_categories',
		[
			'asphalt-paving'         => [ 'asphalt-paving', 'cost-estimation' ],
			'excavation-and-grading' => [ 'excavation-grading', 'machinery-rental' ],
			'demolition-scrap'       => [ 'demolition', 'cost-estimation' ],
			'isogam-waterproofing'   => [ 'waterproofing-isogam', 'bitumen' ],
			'asphalt-joint-sealing'  => [ 'joint-sealing', 'asphalt-paving' ],
			'machinery-rental'       => [ 'machinery-rental', 'excavation-grading' ],
		]
	);
}

/**
 * Append three related articles to service pages. They linked to no
 * article at all, so the guides that support each service got no link
 * value and visitors had no path to them.
 *
 * @param string $content Page content.
 *
 * @return string
 */
function asfaltbama_service_related_articles( $content ) {
	static $done = false;

	if ( $done || ! is_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$map  = asfaltbama_service_categories();
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( ! isset( $map[ $slug ] ) ) {
		return $content;
	}

	$ids = [];
	foreach ( $map[ $slug ] as $cat_slug ) {
		$term = get_category_by_slug( $cat_slug );
		if ( $term ) {
			$ids[] = $term->term_id;
		}
	}
	if ( ! $ids ) {
		return $content;
	}

	$posts = get_posts(
		[
			'numberposts'  => 3,
			'category__in' => $ids,
		]
	);
	if ( ! $posts ) {
		return $content;
	}

	$done     = true;
	$services = asfaltbama_service_pages();
	$label    = isset( $services[ $slug ] ) ? $services[ $slug ][1] : get_the_title();
	$main_cat = get_category_by_slug( $map[ $slug ][0] );

	$html  = '<section class="abm-latest abm-service-articles" aria-labelledby="abm-service-articles-title"><div class="abm-wrap">';
	$html .= '<h2 id="abm-service-articles-title" class="abm-section-title">' . esc_html( 'راهنماهای ' . $label ) . '</h2>';
	$html .= '<div class="abm-grid">';
	foreach ( $posts as $post ) {
		$html .= asfaltbama_post_card( $post, 'h3' );
	}
	$html .= '</div><p class="abm-latest__links">';
	if ( $main_cat ) {
		$html .= '<a class="abm-btn abm-btn--dark" href="' . esc_url( get_category_link( $main_cat ) ) . '">' . esc_html( 'همه‌ی مقالات ' . $main_cat->name ) . '</a>';
	}
	$html .= '<a class="abm-btn abm-btn--amber" href="tel:' . esc_attr( ASFALTBAMA_PHONE ) . '">مشاوره‌ی رایگان: <span dir="ltr">' . esc_html( ASFALTBAMA_PHONE_DISPLAY ) . '</span></a>';
	$html .= '</p></div></section>';

	return $content . $html;
}
add_filter( 'the_content', 'asfaltbama_service_related_articles', 30 );

/**
 * Project photo gallery on a service page (manifest service_galleries),
 * placed before the related articles. Real project photos show visitors
 * and search engines first-hand work, not stock images.
 *
 * @param string $content Page content.
 *
 * @return string
 */
function asfaltbama_service_gallery( $content ) {
	static $done = false;

	if ( $done || ! is_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$galleries = (array) ( asfaltbama_content_manifest()['service_galleries'] ?? [] );
	$slug      = get_post_field( 'post_name', get_queried_object_id() );
	if ( empty( $galleries[ $slug ]['images'] ) ) {
		return $content;
	}
	$gallery = $galleries[ $slug ];

	$items = '';
	foreach ( (array) $gallery['images'] as $source ) {
		$id = asfaltbama_imported_media_id( $source );
		if ( ! $id ) {
			continue;
		}
		$caption = wp_get_attachment_caption( $id ) ?: get_the_title( $id );
		$items  .= '<figure class="abm-gallery__item">';
		$items  .= wp_get_attachment_image(
			$id,
			'medium_large',
			false,
			[
				'loading' => 'lazy',
				'sizes'   => '(max-width: 600px) 50vw, 300px',
			]
		);
		$items  .= '<figcaption>' . esc_html( $caption ) . '</figcaption></figure>';
	}
	if ( ! $items ) {
		return $content;
	}

	$done  = true;
	$html  = '<section class="abm-latest abm-gallery" aria-labelledby="abm-gallery-title"><div class="abm-wrap">';
	$html .= '<h2 id="abm-gallery-title" class="abm-section-title">' . esc_html( $gallery['title'] ) . '</h2>';
	if ( ! empty( $gallery['intro'] ) ) {
		$html .= '<p class="abm-gallery__intro">' . esc_html( $gallery['intro'] ) . '</p>';
	}
	$html .= '<div class="abm-gallery__grid">' . $items . '</div></div></section>';

	return $content . $html;
}
add_filter( 'the_content', 'asfaltbama_service_gallery', 29 );
