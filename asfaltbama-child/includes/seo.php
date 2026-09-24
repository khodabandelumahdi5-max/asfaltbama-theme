<?php
/**
 * SEO adjustments. Rank Math is the single source of <head> SEO tags;
 * this file only removes overlaps and fills gaps in its output.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Content language of the site, independent of the admin/site locale.
 *
 * The WordPress site language is currently English, which makes
 * <html lang="en-US">, og:locale=en_US and schema inLanguage=en-US on a
 * Persian site. Setting Settings > General > Site Language to فارسی is the
 * real fix; these helpers keep the output correct either way.
 *
 * @return string Locale in WordPress format, e.g. fa_IR.
 */
function asfaltbama_content_locale() {
	return apply_filters( 'asfaltbama_content_locale', 'fa_IR' );
}

/**
 * Content language as a BCP 47 tag, e.g. fa-IR.
 *
 * @return string
 */
function asfaltbama_content_lang() {
	return str_replace( '_', '-', asfaltbama_content_locale() );
}

/**
 * Stop the parent theme from printing its own meta description.
 * Rank Math already prints one; two description tags confuse crawlers.
 */
add_filter( 'hello_elementor_description_meta_tag', '__return_false' );

/**
 * Correct the <html lang> attribute on the front end.
 *
 * @param string $output Language attributes.
 *
 * @return string
 */
function asfaltbama_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}

	$lang = esc_attr( asfaltbama_content_lang() );

	if ( preg_match( '/\blang="[^"]*"/', $output ) ) {
		return preg_replace( '/\blang="[^"]*"/', 'lang="' . $lang . '"', $output );
	}

	return trim( $output . ' lang="' . $lang . '"' );
}
add_filter( 'language_attributes', 'asfaltbama_language_attributes' );

/**
 * Rank Math: og:locale.
 */
add_filter(
	'rank_math/opengraph/facebook/og_locale',
	function () {
		return asfaltbama_content_locale();
	}
);

/**
 * Rank Math: drop the Slack "Written by: admin / Time to read" labels.
 * They are rendered in English and expose the admin username.
 */
add_filter( 'rank_math/opengraph/slack_enhanced_data', '__return_empty_array' );

/**
 * Rank Math: inLanguage on every schema entity, including the Article
 * rich snippet that is added after the rank_math/json_ld filter runs.
 */
add_filter(
	'rank_math/schema/language',
	function () {
		return asfaltbama_content_lang();
	}
);

/**
 * Business details added to Rank Math's Organization entity when missing.
 *
 * Values entered in Rank Math (Titles & Meta > Local SEO) win; these only
 * fill the gaps. Add the street address and geo coordinates in Rank Math.
 *
 * @return array
 */
function asfaltbama_business_data() {
	$logo = '';
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
	}
	if ( ! $logo ) {
		$logo = get_site_icon_url( 512 );
	}

	$data = [
		'legalName'   => 'شرکت افق آپادانا پاسارگاد',
		'telephone'   => '+989191299559',
		'areaServed'  => [
			[
				'@type' => 'AdministrativeArea',
				'name'  => 'استان تهران',
			],
			[
				'@type' => 'AdministrativeArea',
				'name'  => 'استان البرز',
			],
			[
				'@type' => 'City',
				'name'  => 'تهران',
			],
			[
				'@type' => 'City',
				'name'  => 'کرج',
			],
		],
		'knowsAbout'  => [
			'اجرای آسفالت و تراشه معابر',
			'خاکبرداری و گودبرداری ساختمانی',
			'اجرای ایزوگام و قیرگونی',
			'فروش قیر و مصالح عایق',
			'درزگیری و ماستیک گرم آسفالت',
			'اجاره ماشین‌آلات راه‌سازی',
			'تخریب سازه و خرید ضایعات',
		],
		'contactPoint' => [
			'@type'             => 'ContactPoint',
			'telephone'         => '+989191299559',
			'contactType'       => 'customer service',
			'areaServed'        => 'IR',
			'availableLanguage' => [ 'fa' ],
		],
	];

	if ( $logo ) {
		$data['logo']  = $logo;
		$data['image'] = $logo;
	}

	return apply_filters( 'asfaltbama_business_data', $data );
}

/**
 * Whether a schema entity is the site's organization / local business.
 *
 * @param array $entity Schema entity.
 *
 * @return bool
 */
function asfaltbama_is_organization_entity( $entity ) {
	return isset( $entity['@id'] ) && is_string( $entity['@id'] )
		&& '#organization' === substr( $entity['@id'], -strlen( '#organization' ) );
}

/**
 * Replace en-* inLanguage values with the content language, recursively.
 *
 * @param mixed $value Schema value.
 *
 * @return mixed
 */
function asfaltbama_fix_in_language( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	foreach ( $value as $key => $item ) {
		if ( 'inLanguage' === $key && is_string( $item ) && 0 === strpos( $item, 'en' ) ) {
			$value[ $key ] = asfaltbama_content_lang();
		} elseif ( is_array( $item ) ) {
			$value[ $key ] = asfaltbama_fix_in_language( $item );
		}
	}

	return $value;
}

/**
 * Rank Math JSON-LD: complete the organization entity and fix inLanguage.
 *
 * @param array $data Schema entities keyed by Rank Math.
 *
 * @return array
 */
function asfaltbama_rank_math_json_ld( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$business = null;

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) || ! asfaltbama_is_organization_entity( $entity ) ) {
			continue;
		}

		if ( null === $business ) {
			$business = asfaltbama_business_data();
		}

		foreach ( $business as $prop => $val ) {
			if ( empty( $entity[ $prop ] ) ) {
				$entity[ $prop ] = $val;
			}
		}

		$data[ $key ] = $entity;
	}

	return asfaltbama_fix_in_language( $data );
}
add_filter( 'rank_math/json_ld', 'asfaltbama_rank_math_json_ld', 99 );

/**
 * Whether a JSON-LD block describes the business itself.
 *
 * Such blocks were pasted into Elementor HTML widgets and duplicate Rank
 * Math's organization entity. Blocks for Service, FAQPage etc. are kept.
 *
 * @param string $json JSON-LD source.
 *
 * @return bool
 */
function asfaltbama_is_business_json_ld( $json ) {
	$data = json_decode( $json, true );
	if ( ! is_array( $data ) ) {
		return false;
	}

	$entities = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : [ $data ];
	$business = apply_filters(
		'asfaltbama_duplicate_business_types',
		[ 'HomeAndConstructionBusiness', 'LocalBusiness', 'GeneralContractor', 'Organization' ]
	);

	foreach ( $entities as $entity ) {
		$types = isset( $entity['@type'] ) ? (array) $entity['@type'] : [];
		if ( ! array_intersect( $types, $business ) ) {
			return false;
		}
	}

	return ! empty( $entities );
}

/**
 * Remove head-only SEO tags from Elementor HTML widgets.
 *
 * Several pages and the header template contain HTML widgets with
 * <title>, <meta name="description">, Open Graph tags, a rel=canonical
 * (one pointing to a 404) and a copy of the business schema. In <body>
 * these are invalid, and they conflict with Rank Math. Microdata
 * (<meta itemprop>) and other JSON-LD blocks are left alone.
 *
 * @param string                 $content Widget HTML.
 * @param \Elementor\Widget_Base $widget  Widget instance.
 *
 * @return string
 */
function asfaltbama_strip_widget_seo_tags( $content, $widget ) {
	if ( ! apply_filters( 'asfaltbama_strip_widget_seo_tags', true ) ) {
		return $content;
	}

	if ( 'html' !== $widget->get_name() ) {
		return $content;
	}

	$content = preg_replace( '#<title\b[^>]*>.*?</title>#is', '', $content );
	$content = preg_replace( '#<meta\b(?![^>]*\bitemprop=)[^>]*>#i', '', $content );
	$content = preg_replace( '#<link\b[^>]*\brel=["\']?canonical\b[^>]*>#i', '', $content );

	// The same JSON-LD pasted into two widgets on one page (e.g. the Service
	// schema on /asphalt-joint-sealing/): keep the first. Kept here, not in
	// the closure: each closure instance would get its own fresh statics.
	static $seen = [];

	return preg_replace_callback(
		'#<script\b[^>]*application/ld\+json[^>]*>(.*?)</script>#is',
		function ( $m ) use ( &$seen ) {
			if ( asfaltbama_is_business_json_ld( $m[1] ) ) {
				return '';
			}

			$decoded     = json_decode( $m[1], true );
			$key         = md5( null === $decoded ? trim( $m[1] ) : wp_json_encode( $decoded ) );
			if ( isset( $seen[ $key ] ) ) {
				return '';
			}
			$seen[ $key ] = true;

			return $m[0];
		},
		$content
	);
}
add_filter( 'elementor/widget/render_content', 'asfaltbama_strip_widget_seo_tags', 10, 2 );

/**
 * Whether a page's Elementor content contains its own <h1>.
 *
 * Checks the stored Elementor data for a Heading/Animated Headline set to
 * H1, or a literal <h1> in an HTML or text widget.
 *
 * @param int $post_id Post ID.
 *
 * @return bool
 */
function asfaltbama_elementor_has_h1( $post_id ) {
	$data = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! is_string( $data ) || '' === $data ) {
		return false;
	}

	return (bool) preg_match( '/"(?:header_size|tag|title_tag)"\s*:\s*"h1"|<h1[\s>]|\\\\u003ch1/i', $data );
}

/**
 * Hide Hello's automatic page title (an <h1>) on Elementor pages that
 * already have their own H1, so each page has exactly one.
 *
 * Several service pages showed two H1s: the WordPress page title (one of
 * them still reading «عنوان: …») and the Elementor hero heading.
 *
 * @param bool $show Whether to show the title.
 *
 * @return bool
 */
function asfaltbama_hide_duplicate_page_title( $show ) {
	if ( ! $show || ! is_page() ) {
		return $show;
	}

	return asfaltbama_elementor_has_h1( get_queried_object_id() ) ? false : $show;
}
add_filter( 'hello_elementor_page_title', 'asfaltbama_hide_duplicate_page_title', 20 );

/**
 * Service definitions for the service pages, keyed by page slug.
 *
 * @return array
 */
function asfaltbama_service_pages() {
	return apply_filters(
		'asfaltbama_service_pages',
		[
			'asphalt-paving'         => [ 'آسفالت‌کاری و تراشه', 'اجرای آسفالت', 'پخش آسفالت گرم توپکا، بیندر و تراشه با فینیشر و غلتک برای حیاط، پارکینگ، محوطه و معابر.' ],
			'excavation-and-grading' => [ 'خاکبرداری و گودبرداری', 'خاکبرداری', 'خاکبرداری، گودبرداری ساختمانی، تسطیح و رگلاژ بستر، بارگیری و حمل نخاله.' ],
			'demolition-scrap'       => [ 'تخریب ساختمان و خرید ضایعات آهن', 'تخریب ساختمان', 'تخریب ساختمان کلنگی، بتنی و فلزی به روش دستی و مکانیکی، حمل نخاله و خرید ضایعات آهن.' ],
			'isogam-waterproofing'   => [ 'اجرای ایزوگام و قیرگونی', 'عایق‌کاری بام', 'نصب ایزوگام فویل‌دار و قیرگونی با تست آب‌بندی و ضمانت کتبی.' ],
			'asphalt-joint-sealing'  => [ 'درزگیری و ماستیک گرم آسفالت', 'درزگیری آسفالت', 'درزگیری ترک‌های طولی و عرضی آسفالت با ماستیک گرم پلیمری.' ],
			'machinery-rental'       => [ 'اجاره ماشین‌آلات راه‌سازی', 'اجاره ماشین‌آلات', 'اجاره بابکت، مینی‌بیل، بیل مکانیکی، فینیشر و غلتک با اپراتور.' ],
		]
	);
}

/**
 * Whether a page's Elementor content already contains Service JSON-LD.
 *
 * @param int $post_id Post ID.
 *
 * @return bool
 */
function asfaltbama_page_has_service_schema( $post_id ) {
	$data = get_post_meta( $post_id, '_elementor_data', true );
	return is_string( $data ) && (bool) preg_match( '/@type[\\\\"\s:]+Service\b/', $data );
}

/**
 * Add a Service entity to Rank Math's graph on service pages.
 *
 * @param array $data Schema entities.
 *
 * @return array
 */
function asfaltbama_service_schema( $data ) {
	if ( ! is_array( $data ) || ! is_page() ) {
		return $data;
	}

	$page     = get_queried_object();
	$services = asfaltbama_service_pages();
	if ( ! $page || ! isset( $services[ $page->post_name ] ) || asfaltbama_page_has_service_schema( $page->ID ) ) {
		return $data;
	}

	list( $name, $type, $description ) = $services[ $page->post_name ];
	$url = get_permalink( $page );

	$data['asfaltbamaService'] = [
		'@type'       => 'Service',
		'@id'         => $url . '#service',
		'name'        => $name,
		'serviceType' => $type,
		'description' => $description,
		'url'         => $url,
		'provider'    => [ '@id' => home_url( '/#organization' ) ],
		'areaServed'  => [
			[
				'@type' => 'City',
				'name'  => 'تهران',
			],
			[
				'@type' => 'City',
				'name'  => 'کرج',
			],
			[
				'@type' => 'AdministrativeArea',
				'name'  => 'استان البرز',
			],
		],
	];

	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_service_schema', 20 );
