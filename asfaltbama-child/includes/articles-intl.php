<?php
/**
 * English and Arabic articles.
 *
 * Each Persian article has an English and an Arabic version. They are
 * pages (manifest pages with "layout": "article"), so they stay out of
 * the Persian blog, its categories, feeds and the home page post lists.
 * They are rendered by article-intl.php with the same design as the
 * Persian articles, in their own language and direction, and the three
 * versions point at each other with hreflang (manifest hreflang_group).
 * The guides hubs (layout "article-hub") list them with [abm_articles].
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface strings of the English and Arabic articles.
 *
 * @param string $key  String key.
 * @param string $lang en or ar.
 *
 * @return string
 */
function asfaltbama_i18n( $key, $lang ) {
	static $strings = [
		'en' => [
			'home'        => 'Asfaltbama',
			'guides'      => 'Technical guides',
			'minutes'     => '%s min read',
			'published'   => 'Published:',
			'updated'     => 'Updated:',
			'author'      => 'By: %s',
			'team'        => 'Asfaltbama technical team',
			'toc'         => 'Contents',
			'tools'       => 'Article tools',
			'contact'     => 'Talk to our engineers',
			'contact_p'   => 'Send drawings, photos or the approximate area and we will reply with a method statement and a quotation.',
			'whatsapp'    => 'WhatsApp',
			'email'       => 'Email',
			'services'    => 'Where we work',
			'related'     => 'Related guides',
			'all'         => 'All guides',
			'crumbs'      => 'Breadcrumb',
			'read_more'   => 'Read the guide',
			'read_more_a' => 'Read the guide: %s',
			'lang_fa'     => 'نسخه‌ی فارسی',
			'lang_other'  => 'النسخة العربية',
			'other_slug'  => 'ar',
			'wa_text'     => 'Hello, I read your guide "%s" and would like a quotation.',
			'cta_badge'   => 'Iraq · Kuwait · Oman · Bahrain · UAE',
			'cta_title'   => 'Planning asphalt paving, Isogam or bitumen roofing works?',
			'cta_desc'    => 'Ofogh Apadana Pasargad Co. (Asfaltbama) has more than 25 years of experience in asphalt paving and bituminous waterproofing. Send us your project details for a proposal.',
		],
		'ar' => [
			'home'        => 'أسفلت با ما',
			'guides'      => 'الأدلة الفنية',
			'minutes'     => 'قراءة %s دقيقة',
			'published'   => 'نُشر:',
			'updated'     => 'آخر تحديث:',
			'author'      => 'الكاتب: %s',
			'team'        => 'الفريق الفني لشركة أسفلت با ما',
			'toc'         => 'محتويات المقال',
			'tools'       => 'أدوات المقال',
			'contact'     => 'تحدّث مع مهندسينا',
			'contact_p'   => 'أرسل المخططات أو الصور أو المساحة التقريبية، وسنرد عليك بطريقة التنفيذ وعرض السعر.',
			'whatsapp'    => 'واتساب',
			'email'       => 'البريد الإلكتروني',
			'services'    => 'مناطق عملنا',
			'related'     => 'أدلة ذات صلة',
			'all'         => 'كل الأدلة',
			'crumbs'      => 'مسار الصفحة',
			'read_more'   => 'اقرأ الدليل',
			'read_more_a' => 'اقرأ الدليل: %s',
			'lang_fa'     => 'نسخه‌ی فارسی',
			'lang_other'  => 'English version',
			'other_slug'  => 'en',
			'wa_text'     => 'مرحباً، قرأت دليلكم «%s» وأرغب في عرض سعر.',
			'cta_badge'   => 'العراق · الكويت · عُمان · البحرين · الإمارات',
			'cta_title'   => 'لديك مشروع رصف أسفلت أو عزل بالإيزوجام أو بالقير والجنفاص؟',
			'cta_desc'    => 'شركة أفق آبادانا باسارجاد (أسفلت با ما) لديها أكثر من ٢٥ عاماً من الخبرة في رصف الأسفلت والعزل البيتوميني. أرسل تفاصيل مشروعك لتصلك طريقة التنفيذ وعرض السعر.',
		],
	];

	$lang = isset( $strings[ $lang ] ) ? $lang : 'en';
	return $strings[ $lang ][ $key ] ?? $key;
}

/**
 * Arabic-Indic digits for Arabic text.
 *
 * @param string|int $value Text or number.
 *
 * @return string
 */
function asfaltbama_ar_digits( $value ) {
	return strtr( (string) $value, [ '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩' ] );
}

/**
 * Number in the digits of a language.
 *
 * @param string|int $value Number.
 * @param string     $lang  fa, ar or en.
 *
 * @return string
 */
function asfaltbama_lang_digits( $value, $lang ) {
	if ( 'fa' === $lang ) {
		return asfaltbama_fa_digits( $value );
	}
	return 'ar' === $lang ? asfaltbama_ar_digits( $value ) : (string) $value;
}

/**
 * Gregorian date in English or Arabic, e.g. "27 September 2026".
 *
 * @param string $mysql_date Date in MySQL format.
 * @param string $lang       en or ar.
 *
 * @return string
 */
function asfaltbama_intl_date( $mysql_date, $lang ) {
	$ts = strtotime( $mysql_date );
	if ( ! $ts ) {
		return '';
	}
	if ( 'ar' === $lang ) {
		$months = [ 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' ];
		return asfaltbama_ar_digits( gmdate( 'j', $ts ) ) . ' ' . $months[ (int) gmdate( 'n', $ts ) - 1 ] . ' ' . asfaltbama_ar_digits( gmdate( 'Y', $ts ) );
	}
	return gmdate( 'j F Y', $ts );
}

/**
 * Category names of the English and Arabic articles.
 *
 * @param string $slug Category slug of the Persian article.
 * @param string $lang en or ar.
 *
 * @return string
 */
function asfaltbama_intl_category( $slug, $lang ) {
	$names = [
		'asphalt-paving'       => [ 'Asphalt paving', 'رصف الأسفلت' ],
		'excavation-grading'   => [ 'Excavation & grading', 'الحفر وتسوية الأراضي' ],
		'waterproofing-isogam' => [ 'Waterproofing', 'العزل المائي' ],
		'joint-sealing'        => [ 'Crack sealing', 'سدّ الشقوق' ],
		'cost-estimation'      => [ 'Cost estimation', 'تقدير التكاليف' ],
		'demolition'           => [ 'Demolition', 'الهدم' ],
		'machinery-rental'     => [ 'Machinery', 'المعدات' ],
		'bitumen'              => [ 'Bitumen', 'البيتومين' ],
		'contracts-warranty'   => [ 'Contracts & warranty', 'العقود والضمان' ],
	];
	$pair = $names[ $slug ] ?? [ 'Guides', 'أدلة' ];
	return 'ar' === $lang ? $pair[1] : $pair[0];
}

/**
 * English or Arabic articles in the manifest.
 *
 * @param string $lang en or ar.
 *
 * @return array[]
 */
function asfaltbama_intl_articles( $lang ) {
	$items = [];
	foreach ( (array) ( asfaltbama_content_manifest()['pages'] ?? [] ) as $page ) {
		if ( 'article' === ( $page['layout'] ?? '' ) && ( $page['lang'] ?? '' ) === $lang ) {
			$items[] = $page;
		}
	}
	return $items;
}

/**
 * Whether the current page is an English or Arabic article.
 *
 * @return bool
 */
function asfaltbama_is_intl_article() {
	$item = function_exists( 'asfaltbama_current_manifest_page' ) ? asfaltbama_current_manifest_page() : null;
	return $item && 'article' === ( $item['layout'] ?? '' );
}

/**
 * Whether the current page is a guides hub.
 *
 * @return bool
 */
function asfaltbama_is_article_hub() {
	$item = function_exists( 'asfaltbama_current_manifest_page' ) ? asfaltbama_current_manifest_page() : null;
	return $item && 'article-hub' === ( $item['layout'] ?? '' );
}

/**
 * Slug of the guides hub of a language.
 *
 * @param string $lang en or ar.
 *
 * @return string
 */
function asfaltbama_guides_hub_url( $lang ) {
	foreach ( (array) ( asfaltbama_content_manifest()['pages'] ?? [] ) as $page ) {
		if ( 'article-hub' === ( $page['layout'] ?? '' ) && ( $page['lang'] ?? '' ) === $lang ) {
			return home_url( '/' . $page['slug'] . '/' );
		}
	}
	return home_url( '/' );
}

/**
 * Home of the international pages of a language (the landing hub).
 *
 * @param string $lang en or ar.
 *
 * @return string
 */
function asfaltbama_intl_home_url( $lang ) {
	return home_url( 'ar' === $lang ? '/asphalt-contractor-middle-east-ar/' : '/asphalt-contractor-middle-east/' );
}

/**
 * Language versions of the current article (Persian post or English /
 * Arabic page): lang => URL.
 *
 * @return string[]
 */
function asfaltbama_article_versions() {
	$manifest = asfaltbama_content_manifest();
	$group    = '';
	if ( is_singular( 'post' ) ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		foreach ( (array) ( $manifest['posts'] ?? [] ) as $post ) {
			if ( $post['slug'] === $slug ) {
				$group = $post['hreflang_group'] ?? '';
			}
		}
	} else {
		$item  = asfaltbama_current_manifest_page();
		$group = $item['hreflang_group'] ?? '';
	}
	if ( '' === $group ) {
		return [];
	}
	$versions = [];
	foreach ( (array) ( $manifest['posts'] ?? [] ) as $post ) {
		if ( ( $post['hreflang_group'] ?? '' ) === $group ) {
			$versions['fa'] = home_url( '/' . $post['slug'] . '/' );
		}
	}
	foreach ( (array) ( $manifest['pages'] ?? [] ) as $page ) {
		if ( ( $page['hreflang_group'] ?? '' ) === $group ) {
			$versions[ $page['lang'] ?? 'fa' ] = home_url( '/' . $page['slug'] . '/' );
		}
	}
	return $versions;
}

/**
 * Use article-intl.php for the English and Arabic articles and
 * guides-intl.php for their hubs.
 *
 * @param string $template Template path.
 *
 * @return string
 */
function asfaltbama_intl_article_template( $template ) {
	if ( ! is_page() ) {
		return $template;
	}
	$file = '';
	if ( asfaltbama_is_intl_article() ) {
		$file = ASFALTBAMA_CHILD_PATH . '/article-intl.php';
	} elseif ( asfaltbama_is_article_hub() ) {
		$file = ASFALTBAMA_CHILD_PATH . '/guides-intl.php';
	}
	return $file && is_readable( $file ) ? $file : $template;
}
add_filter( 'template_include', 'asfaltbama_intl_article_template', 20 );

/**
 * Articles and hubs: keep the markup as written and hide the theme title
 * on the hubs (their content starts with the hero).
 *
 * @return void
 */
function asfaltbama_intl_article_setup() {
	if ( is_admin() || ! is_page() ) {
		return;
	}
	if ( asfaltbama_is_intl_article() || asfaltbama_is_article_hub() ) {
		remove_filter( 'the_content', 'wptexturize' );
		add_filter( 'hello_elementor_page_title', '__return_false', 99 );
		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = asfaltbama_is_intl_article() ? 'abm-intl-article' : 'abm-intl-hub';
				return $classes;
			}
		);
	}
}
add_action( 'wp', 'asfaltbama_intl_article_setup' );

/**
 * Card of an English or Arabic article.
 *
 * @param array  $item    Manifest page.
 * @param string $heading h2 or h3.
 *
 * @return string
 */
function asfaltbama_intl_card( $item, $heading = 'h2' ) {
	$lang  = $item['lang'];
	$url   = home_url( '/' . $item['slug'] . '/' );
	$page  = get_page_by_path( $item['slug'] );
	$words = $page ? asfaltbama_count_words( wp_strip_all_tags( strip_shortcodes( $page->post_content ) ) ) : 0;
	$mins  = max( 1, (int) ceil( $words / ( 'en' === $lang ? 230 : 200 ) ) );

	$html  = '<article class="abm-card">';
	$html .= '<a class="abm-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">';
	if ( $page && has_post_thumbnail( $page ) ) {
		$html .= get_the_post_thumbnail(
			$page,
			'medium_large',
			[
				'loading' => 'lazy',
				'alt'     => $item['title'],
			]
		);
	} else {
		$html .= '<span class="abm-card__placeholder"><span>' . esc_html( asfaltbama_intl_category( $item['category'] ?? '', $lang ) ) . '</span></span>';
	}
	$html .= '</a><div class="abm-card__body"><div class="abm-card__meta">';
	$html .= '<span class="abm-badge">' . esc_html( asfaltbama_intl_category( $item['category'] ?? '', $lang ) ) . '</span>';
	$html .= '<span class="abm-card__time">' . esc_html( sprintf( asfaltbama_i18n( 'minutes', $lang ), asfaltbama_lang_digits( $mins, $lang ) ) ) . '</span>';
	$html .= '</div>';
	$html .= sprintf( '<%1$s class="abm-card__title"><a href="%2$s">%3$s</a></%1$s>', 'h3' === $heading ? 'h3' : 'h2', esc_url( $url ), esc_html( $item['title'] ) );
	$html .= '<p class="abm-card__excerpt">' . esc_html( $item['excerpt'] ?? '' ) . '</p>';
	$html .= '<div class="abm-card__foot"><span></span><a class="abm-card__more" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( asfaltbama_i18n( 'read_more_a', $lang ), $item['title'] ) ) . '">' . esc_html( asfaltbama_i18n( 'read_more', $lang ) ) . '</a></div>';
	$html .= '</div></article>';

	return $html;
}

/**
 * [abm_articles lang="en" category=""]: cards of the English or Arabic
 * articles, grouped by category.
 *
 * @param array $atts Attributes.
 *
 * @return string
 */
function asfaltbama_articles_shortcode( $atts ) {
	$atts  = shortcode_atts(
		[
			'lang' => 'en',
		],
		$atts
	);
	$lang  = 'ar' === $atts['lang'] ? 'ar' : 'en';
	$items = asfaltbama_intl_articles( $lang );
	if ( ! $items ) {
		return '';
	}

	$groups = [];
	foreach ( $items as $item ) {
		$groups[ $item['category'] ?? '' ][] = $item;
	}

	$html = '';
	foreach ( $groups as $category => $group ) {
		$html .= '<section class="abm-hub-group"><h2 class="abm-section-title">' . esc_html( asfaltbama_intl_category( $category, $lang ) ) . '</h2><div class="abm-grid">';
		foreach ( $group as $item ) {
			$html .= asfaltbama_intl_card( $item, 'h3' );
		}
		$html .= '</div></section>';
	}
	return '<div class="abm-hub" lang="' . esc_attr( $lang ) . '" dir="' . ( 'ar' === $lang ? 'rtl' : 'ltr' ) . '">' . $html . '</div>';
}
add_shortcode( 'abm_articles', 'asfaltbama_articles_shortcode' );

/**
 * Related articles in the same language: same category first.
 *
 * @param array $item  Current manifest page.
 * @param int   $count Number of articles.
 *
 * @return array[]
 */
function asfaltbama_intl_related( $item, $count = 3 ) {
	$same  = [];
	$other = [];
	foreach ( asfaltbama_intl_articles( $item['lang'] ) as $candidate ) {
		if ( $candidate['slug'] === $item['slug'] ) {
			continue;
		}
		if ( ( $candidate['category'] ?? '' ) === ( $item['category'] ?? '' ) ) {
			$same[] = $candidate;
		} else {
			$other[] = $candidate;
		}
	}
	return array_slice( array_merge( $same, $other ), 0, $count );
}

/**
 * BlogPosting and breadcrumb schema for the English and Arabic articles.
 *
 * @param array $data Schema entities.
 *
 * @return array
 */
function asfaltbama_intl_article_schema( $data ) {
	if ( ! is_array( $data ) || ! is_page() || ! asfaltbama_is_intl_article() ) {
		return $data;
	}
	$item = asfaltbama_current_manifest_page();
	$post = get_post( get_queried_object_id() );
	$url  = get_permalink( $post );
	$lang = $item['lang'];

	$article = [
		'@type'            => 'BlogPosting',
		'@id'              => $url . '#article',
		'headline'         => $item['title'],
		'description'      => $item['seo_description'] ?? ( $item['excerpt'] ?? '' ),
		'inLanguage'       => $lang,
		'datePublished'    => get_the_date( 'c', $post ),
		'dateModified'     => get_the_modified_date( 'c', $post ),
		'mainEntityOfPage' => $url,
		'author'           => [
			'@type' => 'Organization',
			'name'  => asfaltbama_i18n( 'team', $lang ),
			'url'   => asfaltbama_intl_home_url( $lang ),
		],
		'publisher'        => [
			'@type' => 'Organization',
			'name'  => 'Ofogh Apadana Pasargad Co. (Asfaltbama)',
			'url'   => home_url( '/' ),
		],
	];
	if ( has_post_thumbnail( $post ) ) {
		$article['image'] = get_the_post_thumbnail_url( $post, 'full' );
	}
	$data['asfaltbamaArticle'] = $article;

	$data['asfaltbamaIntlCrumbs'] = [
		'@type'           => 'BreadcrumbList',
		'@id'             => $url . '#breadcrumb-intl',
		'itemListElement' => [
			[
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => asfaltbama_i18n( 'home', $lang ),
				'item'     => asfaltbama_intl_home_url( $lang ),
			],
			[
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => asfaltbama_i18n( 'guides', $lang ),
				'item'     => asfaltbama_guides_hub_url( $lang ),
			],
			[
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $item['title'],
			],
		],
	];

	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_intl_article_schema', 25 );

/**
 * Country pages for the article sidebar: [label, path].
 *
 * @param string $lang en or ar.
 *
 * @return array[]
 */
function asfaltbama_intl_service_links( $lang ) {
	if ( 'ar' === $lang ) {
		return [
			[ 'العراق', '/asphalt-waterproofing-iraq-ar/' ],
			[ 'كربلاء', '/asphalt-waterproofing-karbala/' ],
			[ 'النجف', '/asphalt-waterproofing-najaf/' ],
			[ 'البصرة', '/asphalt-waterproofing-basra/' ],
			[ 'أربيل', '/asphalt-waterproofing-erbil/' ],
			[ 'الكويت', '/asphalt-waterproofing-kuwait-ar/' ],
			[ 'سلطنة عُمان', '/asphalt-waterproofing-oman-ar/' ],
			[ 'البحرين', '/asphalt-waterproofing-bahrain-ar/' ],
			[ 'دبي والإمارات', '/asphalt-waterproofing-dubai-uae-ar/' ],
		];
	}
	return [
		[ 'Iraq', '/asphalt-waterproofing-iraq/' ],
		[ 'Kuwait', '/asphalt-waterproofing-kuwait/' ],
		[ 'Oman', '/asphalt-waterproofing-oman/' ],
		[ 'Bahrain', '/asphalt-waterproofing-bahrain/' ],
		[ 'Dubai & UAE', '/asphalt-waterproofing-dubai-uae/' ],
		[ 'All Middle East services', '/asphalt-contractor-middle-east/' ],
	];
}
