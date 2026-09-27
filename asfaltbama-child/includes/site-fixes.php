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
				return ! in_array( $link[1], [ '/asphalt-price-factors/', '/service-areas/', '/industrial-asphalt-waterproofing/' ], true );
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

	// Company email instead of the personal address set in the plugin.
	$html = str_replace( 'khodabandelumahdi9@gmail.com', 'ofoghapadanapasargad@gmail.com', $html );

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

	// «مناطق تحت پوشش» after «مقالات» in the quick links.
	$html = preg_replace(
		'#(<a href="[^"]*/articles/"\s*>\s*مقالات\s*</a>)#u',
		'$1' . "\n" . '<a href="' . esc_url( home_url( '/service-areas/' ) ) . '">مناطق تحت پوشش</a>'
			. "\n" . '<a href="' . esc_url( home_url( '/industrial-asphalt-waterproofing/' ) ) . '">آسفالت و عایق کارخانه‌ها</a>'
			. "\n" . '<a href="' . esc_url( home_url( '/asphalt-contractor-middle-east/' ) ) . '" lang="en" dir="ltr">English</a>'
			. "\n" . '<a href="' . esc_url( home_url( '/asphalt-contractor-middle-east-ar/' ) ) . '" lang="ar">العربية</a>',
		$html,
		1
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

	if ( function_exists( 'asfaltbama_wire_quote_form' ) ) {
		$html = asfaltbama_wire_quote_form( $html );
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
	$html .= '</p>' . asfaltbama_area_links() . '</div></section>';

	return $content . $html;
}
add_filter( 'the_content', 'asfaltbama_service_related_articles', 30 );

/**
 * Area pages from the manifest (pages with area_served), except the hub.
 *
 * @return array[] Page manifest items.
 */
function asfaltbama_area_pages() {
	$pages = [];
	foreach ( (array) ( asfaltbama_content_manifest()['pages'] ?? [] ) as $item ) {
		if ( ! empty( $item['area_served'] ) && 'service-areas' !== $item['slug'] && 'fa' === ( $item['lang'] ?? 'fa' ) ) {
			$pages[] = $item;
		}
	}
	return $pages;
}

/**
 * Links to the area pages, for the service pages.
 *
 * @return string
 */
function asfaltbama_area_links() {
	$items = '';
	foreach ( asfaltbama_area_pages() as $item ) {
		$items .= '<a href="' . esc_url( home_url( '/' . $item['slug'] . '/' ) ) . '">' . esc_html( implode( ' و ', $item['area_served'] ) ) . '</a>';
	}
	if ( ! $items ) {
		return '';
	}
	return '<nav class="abm-areas" aria-label="مناطق تحت پوشش"><span class="abm-areas__title">مناطق تحت پوشش:</span>' . $items . '<a href="' . esc_url( home_url( '/service-areas/' ) ) . '">همه‌ی مناطق</a></nav>';
}

/**
 * Service entity for the area pages: what is offered and where.
 *
 * @param array $data Schema entities.
 *
 * @return array
 */
function asfaltbama_area_schema( $data ) {
	if ( ! is_array( $data ) || ! is_page() ) {
		return $data;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	foreach ( (array) ( asfaltbama_content_manifest()['pages'] ?? [] ) as $item ) {
		if ( $item['slug'] !== $slug || empty( $item['area_served'] ) ) {
			continue;
		}
		$url   = get_permalink( get_queried_object_id() );
		$areas = [];
		foreach ( (array) $item['area_served'] as $area ) {
			$areas[] = [
				'@type' => 'Place',
				'name'  => $area,
			];
		}
		$data['asfaltbamaAreaService'] = [
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'name'        => get_the_title( get_queried_object_id() ),
			'serviceType' => [
				'fa' => 'آسفالت‌کاری، خاکبرداری و عایق‌کاری',
				'en' => 'Asphalt paving and waterproofing',
				'ar' => 'رصف الأسفلت والعزل المائي',
			][ $item['lang'] ?? 'fa' ] ?? 'آسفالت‌کاری، خاکبرداری و عایق‌کاری',
			'description' => $item['seo_description'] ?? '',
			'url'         => $url,
			'provider'    => [ '@id' => home_url( '/#organization' ) ],
			'areaServed'  => $areas,
		];
		break;
	}
	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_area_schema', 20 );

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

/**
 * Tag archives: noindex, follow and out of the sitemap. Each tag lists
 * only a few articles that the category pages already list, so indexing
 * them would add thin, near-duplicate pages. The tags still link related
 * articles for visitors.
 *
 * @param array $robots Robots values.
 *
 * @return array
 */
function asfaltbama_tag_robots( $robots ) {
	if ( is_tag() ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'asfaltbama_tag_robots' );

/**
 * Keep tag archives out of Rank Math's sitemap.
 *
 * @param bool   $exclude  Whether to exclude.
 * @param string $taxonomy Taxonomy.
 *
 * @return bool
 */
function asfaltbama_tag_sitemap( $exclude, $taxonomy ) {
	return 'post_tag' === $taxonomy ? true : $exclude;
}
add_filter( 'rank_math/sitemap/exclude_taxonomy', 'asfaltbama_tag_sitemap', 10, 2 );

/**
 * Whether the current page is written in the editor rather than built with
 * Elementor (area pages, the services page).
 *
 * @return bool
 */
function asfaltbama_is_plain_page() {
	return is_page() && ! is_front_page()
		&& 'builder' !== get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true );
}

/**
 * Body class for plain pages, for the page title styles.
 *
 * @param string[] $classes Body classes.
 *
 * @return string[]
 */
function asfaltbama_plain_page_class( $classes ) {
	if ( asfaltbama_is_plain_page() ) {
		$classes[] = 'abm-plain-page';
	}
	return $classes;
}
add_filter( 'body_class', 'asfaltbama_plain_page_class' );

/**
 * Give plain pages the article typography and a call-to-action box.
 *
 * @param string $content Page content.
 *
 * @return string
 */
function asfaltbama_plain_page_wrap( $content ) {
	if ( ! asfaltbama_is_plain_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$lang = asfaltbama_page_lang();
	if ( function_exists( 'asfaltbama_is_intl_article' ) && asfaltbama_is_intl_article() ) {
		// article-intl.php and guides-intl.php lay these pages out.
		return $content;
	}
	if ( function_exists( 'asfaltbama_is_article_hub' ) && asfaltbama_is_article_hub() ) {
		return $content;
	}
	if ( asfaltbama_is_landing() ) {
		// Landing pages bring their own full-width layout and contact band.
		$dir = 'en' === $lang ? 'ltr' : 'rtl';
		return '<div class="abm-lp" lang="' . esc_attr( $lang ) . '" dir="' . $dir . '">' . $content . '</div>';
	}
	if ( 'fa' !== $lang ) {
		// English / Arabic pages carry their own email and WhatsApp box.
		$dir = 'ar' === $lang ? 'rtl' : 'ltr';
		return '<div class="abm-page abm-prose" lang="' . esc_attr( $lang ) . '" dir="' . $dir . '">' . $content . '</div>';
	}
	return '<div class="abm-page abm-prose">' . $content . asfaltbama_cta( 'box' ) . '</div>';
}
add_filter( 'the_content', 'asfaltbama_plain_page_wrap', 40 );

/**
 * Manifest item of the current page, if the theme created it.
 *
 * @return array|null
 */
function asfaltbama_current_manifest_page() {
	static $item = false;
	if ( false === $item ) {
		$item = null;
		if ( is_page() ) {
			$slug = get_post_field( 'post_name', get_queried_object_id() );
			foreach ( (array) ( asfaltbama_content_manifest()['pages'] ?? [] ) as $page ) {
				if ( $page['slug'] === $slug ) {
					$item = $page;
					break;
				}
			}
		}
	}
	return $item;
}

/**
 * Language of the current page: fa, or en / ar for the international pages.
 *
 * @return string
 */
function asfaltbama_page_lang() {
	$item = asfaltbama_current_manifest_page();
	return $item['lang'] ?? 'fa';
}

/**
 * English and Arabic pages: <html lang>, og:locale and schema inLanguage.
 *
 * @param string $locale Locale.
 *
 * @return string
 */
function asfaltbama_intl_locale( $locale ) {
	$map = [
		'en' => 'en_US',
		'ar' => 'ar',
	];
	return $map[ asfaltbama_page_lang() ] ?? $locale;
}
add_filter( 'asfaltbama_content_locale', 'asfaltbama_intl_locale' );

/**
 * og:locale needs a region: ar -> ar_AR.
 *
 * @param string $locale Locale.
 *
 * @return string
 */
function asfaltbama_intl_og_locale( $locale ) {
	return 'ar' === $locale ? 'ar_AR' : $locale;
}
add_filter( 'rank_math/opengraph/facebook/og_locale', 'asfaltbama_intl_og_locale', 20 );

/**
 * hreflang links between the language versions of a page (manifest
 * hreflang_group), with the English page as x-default.
 *
 * @return void
 */
function asfaltbama_hreflang_links() {
	$group = '';
	if ( is_singular( 'post' ) ) {
		// Persian articles with English and Arabic versions.
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		foreach ( (array) ( asfaltbama_content_manifest()['posts'] ?? [] ) as $post ) {
			if ( $post['slug'] === $slug ) {
				$group = $post['hreflang_group'] ?? '';
				break;
			}
		}
	} else {
		$item  = asfaltbama_current_manifest_page();
		$group = $item['hreflang_group'] ?? '';
	}
	if ( '' === $group ) {
		return;
	}
	$tags = [
		'fa' => 'fa-IR',
		'en' => 'en',
		'ar' => 'ar',
	];
	$manifest = asfaltbama_content_manifest();
	$links    = [];
	foreach ( (array) ( $manifest['posts'] ?? [] ) as $post ) {
		if ( ( $post['hreflang_group'] ?? '' ) === $group ) {
			$links['fa'] = home_url( '/' . $post['slug'] . '/' );
		}
	}
	foreach ( (array) ( $manifest['pages'] ?? [] ) as $page ) {
		if ( ( $page['hreflang_group'] ?? '' ) === $group ) {
			$links[ $page['lang'] ?? 'fa' ] = home_url( '/' . $page['slug'] . '/' );
		}
	}
	if ( count( $links ) < 2 ) {
		return;
	}
	foreach ( $links as $lang => $url ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $tags[ $lang ] ?? $lang ), esc_url( $url ) );
	}
	$default = $links['en'] ?? reset( $links );
	printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $default ) );
}
add_action( 'wp_head', 'asfaltbama_hreflang_links', 5 );

/**
 * Body class with the page language, for direction-aware styles.
 *
 * @param string[] $classes Body classes.
 *
 * @return string[]
 */
function asfaltbama_lang_body_class( $classes ) {
	if ( 'fa' !== asfaltbama_page_lang() ) {
		$classes[] = 'abm-lang-' . asfaltbama_page_lang();
	}
	return $classes;
}
add_filter( 'body_class', 'asfaltbama_lang_body_class' );

/**
 * [abm_gallery files="a.jpg|b.jpg" captions="…|…" lang="en"]: project
 * photos imported from content/images, with captions in the page language
 * (also used as the alt text).
 *
 * @param array $atts Attributes.
 *
 * @return string
 */
function asfaltbama_gallery_shortcode( $atts ) {
	$atts     = shortcode_atts(
		[
			'files'    => '',
			'captions' => '',
			'lang'     => 'fa',
		],
		$atts
	);
	$files    = array_filter( array_map( 'trim', explode( '|', $atts['files'] ) ) );
	$captions = array_map( 'trim', explode( '|', $atts['captions'] ) );

	$items = '';
	foreach ( array_values( $files ) as $i => $file ) {
		$id = asfaltbama_imported_media_id( $file );
		if ( ! $id ) {
			continue;
		}
		$caption = $captions[ $i ] ?? '';
		$items  .= '<figure class="abm-gallery__item">';
		$items  .= wp_get_attachment_image(
			$id,
			'medium_large',
			false,
			[
				'loading' => 'lazy',
				'alt'     => $caption ? $caption : get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'sizes'   => '(max-width: 600px) 50vw, 280px',
			]
		);
		if ( $caption ) {
			$items .= '<figcaption>' . esc_html( $caption ) . '</figcaption>';
		}
		$items .= '</figure>';
	}

	return $items ? '<div class="abm-gallery__grid abm-gallery__grid--inline">' . $items . '</div>' : '';
}
add_shortcode( 'abm_gallery', 'asfaltbama_gallery_shortcode' );

/**
 * Author links point to the about page: author archives are off (the link
 * answered with a redirect) and /author/<login>/ exposed the login name.
 *
 * @return string
 */
function asfaltbama_author_link() {
	return home_url( '/about-us/' );
}
add_filter( 'author_link', 'asfaltbama_author_link' );

/**
 * Article schema: the author Person carried the login name ("admin").
 * Name the editorial team instead and point it to the about page.
 *
 * @param array $data Schema entities.
 *
 * @return array
 */
function asfaltbama_author_schema( $data ) {
	if ( ! is_array( $data ) || ! is_singular( 'post' ) ) {
		return $data;
	}
	$post  = get_post();
	$login = $post ? get_the_author_meta( 'user_login', $post->post_author ) : '';
	$name  = asfaltbama_author_name( $post );
	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) || 'Person' !== ( $entity['@type'] ?? '' ) ) {
			continue;
		}
		if ( ( $entity['name'] ?? '' ) === $login || ( $entity['name'] ?? '' ) === 'admin' ) {
			$data[ $key ]['name'] = $name;
			$data[ $key ]['url']  = home_url( '/about-us/' );
			unset( $data[ $key ]['image'], $data[ $key ]['sameAs'] );
		}
	}
	// Other entities may repeat the author's name inline.
	array_walk_recursive(
		$data,
		function ( &$value, $k ) use ( $login, $name ) {
			if ( 'name' === $k && ( $value === $login || 'admin' === $value ) ) {
				$value = $name;
			}
		}
	);
	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_author_schema', 99 );

/**
 * Bylines: show the editorial team instead of the login name ("admin")
 * that the article template prints.
 *
 * @param string $name Author display name.
 *
 * @return string
 */
function asfaltbama_byline_name( $name ) {
	if ( is_admin() || wp_doing_ajax() || ( ! in_the_loop() && ! is_singular( 'post' ) ) ) {
		return $name;
	}
	$post = get_post();
	return $post ? asfaltbama_author_name( $post ) : $name;
}
add_filter( 'the_author', 'asfaltbama_byline_name' );
add_filter( 'get_the_author_display_name', 'asfaltbama_byline_name' );

/**
 * Article dates in the Persian calendar with Persian digits, wherever the
 * site's default date format is used on the front end. Machine formats
 * (c, U, Y-m-d …) used by feeds and schema are left alone.
 *
 * @param string       $the_date Formatted date.
 * @param string       $format   Requested format ('' = site default).
 * @param WP_Post|null $post     Post.
 *
 * @return string
 */
function asfaltbama_jalali_the_date( $the_date, $format, $post ) {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( '' !== $format && get_option( 'date_format' ) !== $format ) ) {
		return $the_date;
	}
	$post = get_post( $post );
	return $post ? asfaltbama_date( $post->post_date ) : $the_date;
}
add_filter( 'get_the_date', 'asfaltbama_jalali_the_date', 10, 3 );
