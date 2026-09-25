<?php
/**
 * Structured data and meta tags for rich results.
 *
 * - Product JSON-LD (WooCommerce's own) is extended with brand, mpn (part
 *   number), item condition, category and the vehicles the part fits
 *   (isAccessoryOrSparePartFor). Review stars come from WooCommerce reviews
 *   (aggregateRating) once products have ratings.
 * - AutoPartsStore (a LocalBusiness / Organization) and WebSite with a
 *   product SearchAction on the homepage.
 * - Meta description + Open Graph on products and categories, and noindex on
 *   filtered/sorted/search listings. Skipped when Yoast or Rank Math is active.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Schema {

	public static function init() {
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'product' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'organization' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'meta' ), 3 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	private static function seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * @param array      $markup  Product markup.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function product( $markup, $product ) {
		$brands = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $product->get_id(), 'product_brand' ) : array();
		if ( ! is_wp_error( $brands ) && $brands ) {
			$markup['brand'] = array_filter(
				array(
					'@type'         => 'Brand',
					'name'          => $brands[0]->name,
					'alternateName' => Yadak_Brands::latin( $brands[0] ),
					'url'           => get_term_link( $brands[0] ),
					'logo'          => Yadak_Brands::logo_url( $brands[0], 'large' ),
				)
			);
		}
		$pn = $product->get_meta( '_yadak_part_number' );
		if ( $pn ) {
			$markup['mpn'] = $pn;
		}
		$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cats ) && $cats ) {
			$markup['category'] = implode( ' > ', $cats );
		}
		$condition = 'used' === $product->get_meta( '_yadak_quality' ) ? 'https://schema.org/UsedCondition' : 'https://schema.org/NewCondition';
		if ( isset( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
			foreach ( $markup['offers'] as $i => $offer ) {
				$markup['offers'][ $i ]                  = self::to_iso_currency( $offer );
				$markup['offers'][ $i ]['itemCondition'] = $condition;
			}
		}
		$vehicles = wp_get_object_terms( $product->get_id(), Yadak_Fitment::TAXONOMY );
		if ( ! is_wp_error( $vehicles ) && $vehicles ) {
			$markup['isAccessoryOrSparePartFor'] = array_map(
				static function ( $term ) {
					return array(
						'@type' => 'Car',
						'name'  => str_replace( ' › ', ' ', Yadak_Fitment::path( $term ) ),
					);
				},
				array_slice( $vehicles, 0, 20 )
			);
		}
		$oem = Yadak_Part_Data::split_list( $product->get_meta( '_yadak_oem_numbers' ) );
		if ( $oem ) {
			$markup['additionalProperty'] = array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'OEM',
					'value' => implode( ', ', $oem ),
				),
			);
		}
		return $markup;
	}

	/**
	 * Toman (IRT) is not an ISO 4217 code, so Google rejects it. Convert
	 * every price in an offer to Rial (IRR).
	 *
	 * @param array $node Offer / price specification.
	 * @return array
	 */
	public static function to_iso_currency( $node ) {
		$factor = array(
			'IRT'  => 10,
			'IRHT' => 10000,
			'IRHR' => 1000,
		);
		if ( isset( $node['priceCurrency'], $factor[ $node['priceCurrency'] ] ) ) {
			$f = $factor[ $node['priceCurrency'] ];
			foreach ( array( 'price', 'lowPrice', 'highPrice' ) as $key ) {
				if ( isset( $node[ $key ] ) && is_numeric( $node[ $key ] ) ) {
					$node[ $key ] = (string) ( (float) $node[ $key ] * $f );
				}
			}
			$node['priceCurrency'] = 'IRR';
		}
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				$node[ $key ] = self::to_iso_currency( $value );
			}
		}
		return $node;
	}

	/**
	 * Logo for Google: a raster image is required (custom logo, site icon, or the theme's PNG).
	 */
	public static function logo_url() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			return (string) wp_get_attachment_image_url( $id, 'full' );
		}
		if ( has_site_icon() ) {
			return get_site_icon_url( 512 );
		}
		$png = get_stylesheet_directory() . '/assets/brand/logo-512.png';
		return file_exists( $png ) ? get_stylesheet_directory_uri() . '/assets/brand/logo-512.png' : '';
	}

	public static function organization() {
		if ( ! is_front_page() ) {
			return;
		}
		$home  = home_url( '/' );
		$phone = get_theme_mod( 'yadak_phone', Yadak_Settings::get( 'seller_phone' ) );
		$store = array_filter(
			array(
				'@type'         => 'AutoPartsStore',
				'@id'           => $home . '#organization',
				'name'          => get_bloginfo( 'name' ),
				'alternateName' => get_theme_mod( 'yadak_brand_latin', 'GetFori' ),
				'url'           => $home,
				'logo'          => self::logo_url(),
				'image'         => self::logo_url(),
				'description'   => get_bloginfo( 'description' ),
				'telephone'     => $phone,
				'priceRange'    => '$$',
				'currenciesAccepted' => 'IRR',
				'address'       => Yadak_Settings::get( 'seller_address' ) ? array_filter(
					array(
						'@type'           => 'PostalAddress',
						'streetAddress'   => Yadak_Settings::get( 'seller_address' ),
						'postalCode'      => Yadak_Settings::get( 'seller_postcode' ),
						'addressCountry'  => 'IR',
					)
				) : null,
				'sameAs'        => array_values( array_filter( array( get_theme_mod( 'yadak_instagram', '' ) ) ) ),
			)
		);
		$site  = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => 'fa-IR',
			'publisher'       => array( '@id' => $home . '#organization' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}&post_type=product',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array( $store, $site ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		) . "</script>\n";
	}

	public static function meta() {
		// Vehicle and brand pages get their own title/description/canonical.
		if ( self::seo_plugin() || Yadak_Fitment::is_vehicle_archive() || Yadak_Brands::is_brand_page() ) {
			return;
		}
		$title = '';
		$desc  = '';
		$image = '';
		$type  = 'website';
		$url   = '';
		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( ! $product ) {
				return;
			}
			$title = $product->get_name();
			$pn    = $product->get_meta( '_yadak_part_number' );
			$desc  = wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() );
			/* translators: 1: product, 2: part number */
			$desc  = trim( sprintf( __( 'خرید %1$s%2$s با ضمانت اصالت و ارسال فوری.', 'yadak-core' ), $title, $pn ? ' (شماره فنی ' . $pn . ')' : '' ) . ' ' . wp_trim_words( $desc, 20, '…' ) );
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
			$type  = 'product';
			$url   = get_permalink( $product->get_id() );
		} elseif ( is_product_category() ) {
			$term  = get_queried_object();
			$title = $term->name;
			/* translators: %s: category */
			$desc  = $term->description ? wp_strip_all_tags( $term->description ) : sprintf( __( 'خرید %s خودروهای ژاپنی، چینی، کره‌ای و ایرانی با شماره فنی دقیق، ضمانت اصالت و ارسال فوری.', 'yadak-core' ), $term->name );
			$url   = get_term_link( $term );
		} elseif ( is_front_page() ) {
			$title = get_bloginfo( 'name' );
			$desc  = get_bloginfo( 'description' );
			$image = self::logo_url();
			$url   = home_url( '/' );
		} else {
			return;
		}
		$desc = mb_substr( trim( preg_replace( '/\s+/u', ' ', $desc ) ), 0, 160 );
		if ( ! $image || is_front_page() ) {
			$cover = get_stylesheet_directory() . '/assets/brand/og-cover.png';
			$image = file_exists( $cover ) ? get_stylesheet_directory_uri() . '/assets/brand/og-cover.png' : $image;
		}
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		$tags = array(
			'og:locale'      => 'fa_IR',
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:type'        => $type,
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => is_wp_error( $url ) ? '' : $url,
			'og:image'       => $image,
		);
		foreach ( array_filter( $tags ) as $property => $content ) {
			echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	}

	/**
	 * Filtered, sorted and internal-search listings: noindex, follow.
	 */
	public static function robots( $robots ) {
		if ( self::seo_plugin() || is_admin() ) {
			return $robots;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$params = array_intersect( array_keys( $_GET ), array_merge( Yadak_Filter::PARAMS, array( 'orderby', 'min_price', 'max_price', 'yadak_from', 'add-to-cart' ) ) );
		if ( $params || is_search() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}
}
