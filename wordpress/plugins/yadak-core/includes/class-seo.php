<?php
/**
 * SEO landing pages for "part category + vehicle", e.g.
 * /cars/peugeot/206/part/brake-pads/ → «لنت ترمز پژو 206».
 *
 * Adds the rewrite rules, page title/H1/meta description/canonical, category
 * links on vehicle pages, and a sitemap of every combination that has
 * products. Title and description tags are skipped when Yoast or Rank Math
 * is active (they manage them).
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_SEO {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ), 20 );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'page_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'category_links' ), 20 );
		add_action( 'init', array( __CLASS__, 'sitemap' ), 30 );
		add_action( 'set_object_terms', array( __CLASS__, 'flush_cache' ) );
	}

	public static function rewrites() {
		add_rewrite_rule( '^cars/(.+?)/part/([^/]+)/page/([0-9]+)/?$', 'index.php?yadak_vehicle=$matches[1]&product_cat=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '^cars/(.+?)/part/([^/]+)/?$', 'index.php?yadak_vehicle=$matches[1]&product_cat=$matches[2]', 'top' );
	}

	/**
	 * URL of a vehicle + category page.
	 *
	 * @param WP_Term $vehicle  Vehicle term.
	 * @param WP_Term $category Product category.
	 * @return string
	 */
	public static function url( $vehicle, $category ) {
		return trailingslashit( get_term_link( $vehicle ) ) . 'part/' . $category->slug . '/';
	}

	/**
	 * Is this a vehicle + category page? Returns [vehicle, category] or null.
	 *
	 * @return WP_Term[]|null
	 */
	public static function combo() {
		if ( ! Yadak_Fitment::is_vehicle_archive() || ! get_query_var( 'product_cat' ) ) {
			return null;
		}
		$vehicle  = Yadak_Fitment::queried_vehicle();
		$category = get_term_by( 'slug', (string) get_query_var( 'product_cat' ), 'product_cat' );
		return ( $vehicle && $category ) ? array( $vehicle, $category ) : null;
	}

	/**
	 * "لنت ترمز پژو 206" / "قطعات پژو 206".
	 */
	private static function heading() {
		$combo = self::combo();
		if ( $combo ) {
			return $combo[1]->name . ' ' . str_replace( ' › ', ' ', Yadak_Fitment::path( $combo[0] ) );
		}
		if ( Yadak_Fitment::is_vehicle_archive() ) {
			/* translators: %s: vehicle */
			return sprintf( __( 'قطعات یدکی %s', 'yadak-core' ), str_replace( ' › ', ' ', Yadak_Fitment::path( Yadak_Fitment::queried_vehicle() ) ) );
		}
		return '';
	}

	public static function page_title( $title ) {
		$heading = self::heading();
		return $heading ? $heading : $title;
	}

	public static function document_title( $parts ) {
		$heading = self::heading();
		if ( $heading && ! self::seo_plugin_active() ) {
			$parts['title'] = $heading;
		}
		return $parts;
	}

	private static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	public static function head() {
		if ( ! Yadak_Fitment::is_vehicle_archive() || self::seo_plugin_active() ) {
			return;
		}
		$heading = self::heading();
		$combo   = self::combo();
		$vehicle = Yadak_Fitment::queried_vehicle();
		if ( ! $vehicle ) {
			return;
		}
		$url     = $combo ? self::url( $combo[0], $combo[1] ) : get_term_link( $vehicle );
		$paged   = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $paged . '/';
		}
		/* translators: %s: heading */
		$desc = sprintf( __( 'خرید %s با ضمانت اصالت، شماره فنی و مشخصات کامل، قیمت روز و ارسال به سراسر ایران.', 'yadak-core' ), $heading );
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}

	/**
	 * On a vehicle page, link to "category + vehicle" pages that have parts.
	 */
	public static function category_links() {
		if ( ! Yadak_Fitment::is_vehicle_archive() || self::combo() ) {
			return;
		}
		$vehicle = Yadak_Fitment::queried_vehicle();
		$cats    = $vehicle ? self::categories_for_vehicle( $vehicle ) : array();
		if ( ! $cats ) {
			return;
		}
		echo '<nav class="yadak-cat-links" aria-label="' . esc_attr__( 'دسته‌های این خودرو', 'yadak-core' ) . '">';
		foreach ( $cats as $cat ) {
			echo '<a href="' . esc_url( self::url( $vehicle, $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Product categories with parts for a vehicle (including parts tagged on
	 * its ancestors or descendants).
	 *
	 * @param WP_Term $vehicle Vehicle.
	 * @return WP_Term[]
	 */
	public static function categories_for_vehicle( $vehicle ) {
		$key    = 'yadak_vcats_' . $vehicle->term_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			$ids = $cached;
		} else {
			$terms    = array_merge(
				array( $vehicle->term_id ),
				get_ancestors( $vehicle->term_id, Yadak_Fitment::TAXONOMY, 'taxonomy' ),
				get_term_children( $vehicle->term_id, Yadak_Fitment::TAXONOMY )
			);
			$products = get_objects_in_term( $terms, Yadak_Fitment::TAXONOMY );
			$ids      = array();
			if ( ! is_wp_error( $products ) && $products ) {
				$ids = wp_get_object_terms( array_unique( $products ), 'product_cat', array( 'fields' => 'ids' ) );
				$ids = is_wp_error( $ids ) ? array() : array_values( array_unique( array_map( 'intval', $ids ) ) );
			}
			$ids = array_values( array_diff( $ids, array( (int) get_option( 'default_product_cat' ) ) ) );
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}
		return $ids ? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $ids,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		) : array();
	}

	public static function flush_cache( $object_id ) {
		if ( 'product' === get_post_type( $object_id ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_yadak\\_vcats\\_%' OR option_name LIKE '\\_transient\\_timeout\\_yadak\\_vcats\\_%'" );
		}
	}

	public static function sitemap() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once YADAK_CORE_DIR . 'includes/class-sitemap.php';
		wp_register_sitemap_provider( 'yadakparts', new Yadak_Sitemap_Provider() );
	}
}
