<?php
/**
 * Blog: fonts, shared helpers and markup for the articles pages
 * (home.php, archive.php, single.php) and the latest-posts shortcode.
 *
 * Visual language follows the home page: navy #0f172a, amber #f59e0b,
 * white cards with an amber top edge.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ASFALTBAMA_PHONE', '09191299559' );
define( 'ASFALTBAMA_PHONE_DISPLAY', '۰۹۱۹ ۱۲۹ ۹۵۵۹' );
define( 'ASFALTBAMA_WHATSAPP', 'https://wa.me/989191299559' );

/*
 * Fonts
 * -------------------------------------------------------------------- */

/**
 * Preload the self-hosted Vazirmatn variable font (all weights, one file).
 *
 * @return void
 */
function asfaltbama_preload_font() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_stylesheet_directory_uri() . '/assets/fonts/Vazirmatn-Variable.woff2' )
	);
}
add_action( 'wp_head', 'asfaltbama_preload_font', 1 );

/**
 * Stop Elementor from loading Google Fonts (it requested Vazirmatn in 18
 * weights from fonts.googleapis.com, which is slow or blocked for many
 * visitors in Iran). Vazirmatn is self-hosted and applied site-wide in
 * style.css instead.
 */
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );

/**
 * Enqueue blog styles.
 *
 * @return void
 */
function asfaltbama_blog_enqueue() {
	wp_enqueue_style(
		'asfaltbama-blog',
		get_stylesheet_directory_uri() . '/assets/css/blog.css',
		[ 'asfaltbama-child-style' ],
		ASFALTBAMA_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'asfaltbama_blog_enqueue', 30 );

/*
 * Helpers
 * -------------------------------------------------------------------- */

/**
 * Estimated reading time in minutes.
 *
 * @param WP_Post|int|null $post Post.
 *
 * @return int
 */
function asfaltbama_reading_minutes( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}

	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = asfaltbama_count_words( $text );
	$wpm   = (int) apply_filters( 'asfaltbama_reading_time_wpm', 200 );

	return max( 1, (int) ceil( $words / max( 1, $wpm ) ) );
}

/**
 * URL of the articles (posts) page.
 *
 * @return string
 */
function asfaltbama_articles_url() {
	$posts_page = (int) get_option( 'page_for_posts' );
	return $posts_page ? get_permalink( $posts_page ) : home_url( '/articles/' );
}

/**
 * Primary category of a post (Rank Math primary term when set).
 *
 * @param WP_Post|int|null $post Post.
 *
 * @return WP_Term|null
 */
function asfaltbama_primary_category( $post = null ) {
	$post    = get_post( $post );
	$primary = $post ? (int) get_post_meta( $post->ID, 'rank_math_primary_category', true ) : 0;
	if ( $primary ) {
		$term = get_term( $primary, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}
	}

	$cats = $post ? get_the_category( $post->ID ) : [];
	return $cats ? $cats[0] : null;
}

/**
 * Convert Latin digits to Persian digits.
 *
 * @param string|int $value Text or number.
 *
 * @return string
 */
function asfaltbama_fa_digits( $value ) {
	return strtr( (string) $value, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * Gregorian to Jalali (Solar Hijri) date.
 *
 * The WordPress site language stays English (see README), so WordPress
 * itself cannot print Persian dates.
 *
 * @param int $gy Gregorian year.
 * @param int $gm Gregorian month.
 * @param int $gd Gregorian day.
 *
 * @return int[] [year, month, day]
 */
function asfaltbama_to_jalali( $gy, $gm, $gd ) {
	$g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy   += intdiv( $days - 1, 365 );
		$days  = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}

	return [ $jy, $jm, $jd ];
}

/**
 * Persian (Jalali) date, e.g. «۲ مهر ۱۴۰۵».
 *
 * @param string $mysql_date Date in MySQL format (site local time).
 *
 * @return string
 */
function asfaltbama_date( $mysql_date ) {
	$months = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
	$ts     = strtotime( $mysql_date );
	if ( ! $ts ) {
		return '';
	}

	list( $y, $m, $d ) = asfaltbama_to_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );

	return asfaltbama_fa_digits( $d . ' ' . $months[ $m - 1 ] . ' ' . $y );
}

/**
 * Author name for display: the site team when the account only has a
 * login-style name (e.g. "admin"), which looks poor on articles.
 *
 * @param WP_Post|int|null $post Post.
 *
 * @return string
 */
function asfaltbama_author_name( $post = null ) {
	$post = get_post( $post );
	$user = $post ? get_userdata( $post->post_author ) : null;
	if ( ! $user || '' === trim( $user->display_name ) || $user->display_name === $user->user_login ) {
		return apply_filters( 'asfaltbama_team_name', 'تیم فنی آسفالت با ما' );
	}

	return $user->display_name;
}

/**
 * Breadcrumb trail for the current view: list of [label, url].
 * The last item is the current page and has no URL.
 *
 * @return array[]
 */
function asfaltbama_breadcrumb_items() {
	$items = [ [ 'خانه', home_url( '/' ) ] ];

	if ( is_home() ) {
		$items[] = [ 'مقالات', '' ];
	} elseif ( is_category() ) {
		$items[] = [ 'مقالات', asfaltbama_articles_url() ];
		$items[] = [ single_cat_title( '', false ), '' ];
	} elseif ( is_singular( 'post' ) ) {
		$items[] = [ 'مقالات', asfaltbama_articles_url() ];
		$cat     = asfaltbama_primary_category();
		if ( $cat ) {
			$items[] = [ $cat->name, get_category_link( $cat ) ];
		}
		$items[] = [ get_the_title(), '' ];
	} elseif ( is_archive() ) {
		$items[] = [ 'مقالات', asfaltbama_articles_url() ];
		$items[] = [ wp_strip_all_tags( get_the_archive_title() ), '' ];
	}

	return $items;
}

/**
 * Print the breadcrumb navigation.
 *
 * @return void
 */
function asfaltbama_breadcrumbs() {
	$items = asfaltbama_breadcrumb_items();
	echo '<nav class="abm-crumbs" aria-label="مسیر صفحه"><ol>';
	foreach ( $items as $item ) {
		if ( $item[1] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $item[1] ), esc_html( $item[0] ) );
		} else {
			printf( '<li aria-current="page">%s</li>', esc_html( $item[0] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * Add BreadcrumbList to Rank Math's schema graph on blog views, unless
 * Rank Math already outputs one (its breadcrumbs option).
 *
 * @param array $data Schema entities.
 *
 * @return array
 */
function asfaltbama_breadcrumb_schema( $data ) {
	if ( ! is_array( $data ) || ! ( is_home() || is_category() || is_singular( 'post' ) ) ) {
		return $data;
	}

	foreach ( $data as $entity ) {
		if ( is_array( $entity ) && isset( $entity['@type'] ) && in_array( 'BreadcrumbList', (array) $entity['@type'], true ) ) {
			return $data;
		}
	}

	$list     = [];
	$position = 1;
	foreach ( asfaltbama_breadcrumb_items() as $item ) {
		$entry = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => $item[0],
		];
		if ( $item[1] ) {
			$entry['item'] = $item[1];
		}
		$list[] = $entry;
	}

	if ( is_singular() ) {
		$current = get_permalink();
	} elseif ( is_category() ) {
		$current = get_category_link( get_queried_object() );
	} else {
		$current = asfaltbama_articles_url();
	}

	$data['asfaltbamaBreadcrumbs'] = [
		'@type'           => 'BreadcrumbList',
		'@id'             => $current . '#breadcrumb',
		'itemListElement' => $list,
	];

	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_breadcrumb_schema', 20 );

/**
 * Add ids to <h2> headings and collect them for a table of contents.
 *
 * @param string $html Post content HTML.
 *
 * @return array{0:string,1:array} [html with ids, list of [id, text]]
 */
function asfaltbama_toc( $html ) {
	$toc = [];
	$n   = 0;

	$html = preg_replace_callback(
		'#<h2\b([^>]*)>(.*?)</h2>#is',
		function ( $m ) use ( &$toc, &$n ) {
			$attrs = $m[1];
			$text  = trim( wp_strip_all_tags( $m[2] ) );
			if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $attrs, $id ) ) {
				$anchor = $id[1];
			} else {
				$anchor = 'section-' . ( ++$n );
				$attrs .= ' id="' . $anchor . '"';
			}
			$toc[] = [ $anchor, $text ];
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$html
	);

	return [ $html, $toc ];
}

/**
 * Print the table of contents list.
 *
 * @param array $toc [id, text] pairs.
 *
 * @return void
 */
function asfaltbama_toc_list( $toc ) {
	echo '<ol class="abm-toc__list">';
	foreach ( $toc as $item ) {
		printf( '<li><a href="#%s">%s</a></li>', esc_attr( $item[0] ), esc_html( $item[1] ) );
	}
	echo '</ol>';
}

/**
 * Post card markup, shared by the archive, related posts and the
 * [asfaltbama_latest_posts] shortcode.
 *
 * @param WP_Post $post    Post.
 * @param string  $heading Heading tag for the title (h2 or h3).
 *
 * @return string
 */
function asfaltbama_post_card( $post, $heading = 'h2' ) {
	$url     = get_permalink( $post );
	$title   = get_the_title( $post );
	$cat     = asfaltbama_primary_category( $post );
	$heading = in_array( $heading, [ 'h2', 'h3' ], true ) ? $heading : 'h2';

	$html = '<article class="abm-card">';

	$html .= '<a class="abm-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">';
	if ( has_post_thumbnail( $post ) ) {
		$html .= get_the_post_thumbnail( $post, 'medium_large', [ 'loading' => 'lazy' ] );
	} else {
		$html .= '<span class="abm-card__placeholder"><span>' . esc_html( $cat ? $cat->name : 'آسفالت با ما' ) . '</span></span>';
	}
	$html .= '</a>';

	$html .= '<div class="abm-card__body"><div class="abm-card__meta">';
	if ( $cat ) {
		$html .= '<a class="abm-badge" href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
	}
	$html .= '<span class="abm-card__time">' . esc_html( asfaltbama_fa_digits( asfaltbama_reading_minutes( $post ) ) ) . ' دقیقه مطالعه</span>';
	$html .= '</div>';

	$html .= sprintf( '<%1$s class="abm-card__title"><a href="%2$s">%3$s</a></%1$s>', $heading, esc_url( $url ), esc_html( $title ) );
	$html .= '<p class="abm-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 30 ) ) . '</p>';

	$html .= '<div class="abm-card__foot">';
	$html .= '<time datetime="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . esc_html( asfaltbama_date( $post->post_modified ) ) . '</time>';
	$html .= '<a class="abm-card__more" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( 'ادامه‌ی مطلب: ' . $title ) . '">ادامه‌ی مطلب</a>';
	$html .= '</div></div></article>';

	return $html;
}

/**
 * Consultation call-to-action block.
 *
 * @param string $variant 'band' (wide) or 'box' (in-article / sidebar).
 *
 * @return string
 */
function asfaltbama_cta( $variant = 'band' ) {
	$html  = '<aside class="abm-cta abm-cta--' . esc_attr( $variant ) . '" aria-label="مشاوره و بازدید رایگان">';
	$html .= '<div class="abm-cta__text"><span class="abm-cta__badge">بازدید و برآورد رایگان</span>';
	$html .= '<p class="abm-cta__title">پروژه‌ی آسفالت، خاکبرداری یا ایزوگام دارید؟</p>';
	$html .= '<p class="abm-cta__desc">کارشناسان آسفالت با ما در تهران و البرز رایگان بازدید می‌کنند و پیشنهاد قیمت مکتوب با ضمانت کتبی می‌دهند.</p></div>';
	$html .= '<div class="abm-cta__actions">';
	$html .= '<a class="abm-btn abm-btn--amber" href="tel:' . esc_attr( ASFALTBAMA_PHONE ) . '">تماس: <span dir="ltr">' . esc_html( ASFALTBAMA_PHONE_DISPLAY ) . '</span></a>';
	$html .= '<a class="abm-btn abm-btn--ghost" href="' . esc_url( ASFALTBAMA_WHATSAPP ) . '" target="_blank" rel="noopener">مشاوره در واتساپ</a>';
	$html .= '</div></aside>';

	return $html;
}

/**
 * Service pages, for internal links in the article sidebar.
 *
 * @return array[] [label, path]
 */
function asfaltbama_service_links() {
	return apply_filters(
		'asfaltbama_service_links',
		[
			[ 'آسفالت‌کاری و تراشه', '/asphalt-paving/' ],
			[ 'خاکبرداری و گودبرداری', '/excavation-and-grading/' ],
			[ 'اجرای ایزوگام و قیرگونی', '/isogam-waterproofing/' ],
			[ 'درزگیری و ماستیک گرم', '/asphalt-joint-sealing/' ],
			[ 'اجاره ماشین‌آلات راه‌سازی', '/machinery-rental/' ],
			[ 'تخریب و خرید ضایعات', '/demolition-scrap/' ],
			[ 'قیمت هر متر آسفالت', '/asphalt-cost-per-square-meter/' ],
		]
	);
}

/**
 * Related posts: same category first, then latest.
 *
 * @param int $count Number of posts.
 *
 * @return WP_Post[]
 */
function asfaltbama_related_posts( $count = 3 ) {
	$post    = get_post();
	$exclude = [ $post->ID ];
	$cat     = asfaltbama_primary_category( $post );
	$related = [];

	if ( $cat ) {
		$related = get_posts(
			[
				'numberposts'  => $count,
				'category__in' => [ $cat->term_id ],
				'post__not_in' => $exclude,
			]
		);
	}

	if ( count( $related ) < $count ) {
		$exclude = array_merge( $exclude, wp_list_pluck( $related, 'ID' ) );
		$related = array_merge(
			$related,
			get_posts(
				[
					'numberposts'  => $count - count( $related ),
					'post__not_in' => $exclude,
				]
			)
		);
	}

	return $related;
}
