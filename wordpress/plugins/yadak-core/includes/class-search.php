<?php
/**
 * Product search that understands spare-part queries.
 *
 * Every product keeps a normalized search index in `_yadak_search`: title,
 * SKU, part/OEM numbers (as typed and compacted), alternative names, brand
 * and compatible vehicles. The storefront product search matches every word
 * of the query against that index, so "لنت ۲۰۶", "lent 206" (if listed as an
 * alternative name) and "04465-0K090" / "044650k090" all find the part.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Search {

	const META = '_yadak_search';

	public static function init() {
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'index_product_object' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 10, 4 );
		add_action( 'edited_' . Yadak_Fitment::TAXONOMY, array( __CLASS__, 'on_vehicle_renamed' ) );
		add_filter( 'posts_search', array( __CLASS__, 'posts_search' ), 20, 2 );
		add_action( 'admin_post_yadak_reindex', array( __CLASS__, 'admin_reindex' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YADAK_CORE_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_correct' ), 5 );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'corrected_notice' ), 1 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command(
				'yadak reindex',
				static function () {
					$count = self::reindex_all();
					WP_CLI::success( sprintf( '%d products indexed.', $count ) );
				}
			);
		}
	}

	/**
	 * Build and store the search index of one product.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function index( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return;
		}

		$parts   = array( $product->get_name(), $product->get_sku() );
		$numbers = array_merge(
			array( $product->get_meta( '_yadak_part_number' ) ),
			Yadak_Part_Data::split_list( $product->get_meta( '_yadak_oem_numbers' ) )
		);
		foreach ( $numbers as $number ) {
			$parts[] = $number;
			$parts[] = yadak_compact_number( $number );
		}
		$parts[] = yadak_compact_number( $product->get_sku() );
		$parts   = array_merge( $parts, Yadak_Part_Data::split_list( $product->get_meta( '_yadak_alt_names' ) ) );

		foreach ( array( 'product_brand', 'product_cat' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$names = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $names ) ) {
					$parts = array_merge( $parts, $names );
				}
			}
		}
		$vehicles = wp_get_post_terms( $product_id, Yadak_Fitment::TAXONOMY );
		if ( ! is_wp_error( $vehicles ) ) {
			foreach ( $vehicles as $vehicle ) {
				$parts[] = Yadak_Fitment::path( $vehicle );
			}
		}

		$index = ' ' . yadak_normalize( implode( ' | ', array_filter( array_map( 'strval', $parts ) ) ) ) . ' ';
		update_post_meta( $product_id, self::META, $index );
		delete_transient( 'yadak_search_vocab' );
	}

	/* ---------- Typo tolerance ---------- */

	/**
	 * All words in the search index, as a set.
	 *
	 * @return array<string,true>
	 */
	public static function vocabulary() {
		$vocab = get_transient( 'yadak_search_vocab' );
		if ( is_array( $vocab ) ) {
			return $vocab;
		}
		global $wpdb;
		$vocab = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) );
		foreach ( $rows as $row ) {
			foreach ( preg_split( '/[^\p{L}\p{N}]+/u', $row, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
				$len = mb_strlen( $word );
				if ( $len >= 2 && $len <= 30 ) {
					$vocab[ $word ] = true;
				}
			}
			if ( count( $vocab ) > 60000 ) {
				break;
			}
		}
		set_transient( 'yadak_search_vocab', $vocab, DAY_IN_SECONDS );
		return $vocab;
	}

	/**
	 * Levenshtein distance on characters (not bytes), for Persian text.
	 */
	public static function distance( $a, $b ) {
		$map = array();
		$enc = static function ( $str ) use ( &$map ) {
			$out = '';
			foreach ( preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
				if ( ! isset( $map[ $ch ] ) ) {
					$map[ $ch ] = chr( count( $map ) % 256 );
				}
				$out .= $map[ $ch ];
			}
			return $out;
		};
		return levenshtein( $enc( $a ), $enc( $b ) );
	}

	/**
	 * Closest indexed spelling of a query, or '' when nothing better exists.
	 *
	 * @param string $query Raw query.
	 * @return string
	 */
	public static function suggest( $query ) {
		$vocab   = self::vocabulary();
		$words   = preg_split( '/\s+/u', yadak_normalize( $query ), -1, PREG_SPLIT_NO_EMPTY );
		$changed = false;
		foreach ( $words as $i => $word ) {
			$len = mb_strlen( $word );
			if ( isset( $vocab[ $word ] ) || $len < 3 || preg_match( '/^\d+$/', $word ) ) {
				continue;
			}
			$max  = $len <= 5 ? 1 : 2;
			$best = '';
			$dist = $max + 1;
			foreach ( $vocab as $candidate => $unused ) {
				if ( abs( mb_strlen( $candidate ) - $len ) > $max ) {
					continue;
				}
				$d = self::distance( $word, $candidate );
				if ( $d < $dist ) {
					$dist = $d;
					$best = $candidate;
					if ( 1 === $d ) {
						break;
					}
				}
			}
			if ( $best ) {
				$words[ $i ] = $best;
				$changed     = true;
			}
		}
		return $changed ? implode( ' ', $words ) : '';
	}

	/**
	 * No results? Retry once with the closest spelling.
	 */
	public static function maybe_correct() {
		global $wp_query;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_search() || isset( $_GET['yadak_from'] ) || $wp_query->found_posts > 0 || 'product' !== get_query_var( 'post_type' ) ) {
			return;
		}
		$query      = get_search_query( false );
		$suggestion = $query ? self::suggest( $query ) : '';
		if ( ! $suggestion ) {
			return;
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					's'          => rawurlencode( $suggestion ),
					'post_type'  => 'product',
					'yadak_from' => rawurlencode( $query ),
				),
				home_url( '/' )
			)
		);
		exit;
	}

	public static function corrected_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_search() || empty( $_GET['yadak_from'] ) ) {
			return;
		}
		$from = sanitize_text_field( wp_unslash( $_GET['yadak_from'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<p class="yadak-search-corrected">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: original query, 2: corrected query */
					__( 'نتیجه‌ای برای «%1$s» نبود؛ نتایج «%2$s» نمایش داده می‌شود.', 'yadak-core' ),
					$from,
					get_search_query( false )
				)
			)
		);
	}

	/**
	 * @param WC_Product $product Saved product.
	 */
	public static function index_product_object( $product ) {
		self::index( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
	}

	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( Yadak_Fitment::TAXONOMY, 'product_brand', 'product_cat' ), true ) && 'product' === get_post_type( $object_id ) ) {
			self::index( $object_id );
		}
	}

	/**
	 * A renamed vehicle changes the index of every product under it.
	 *
	 * @param int $term_id Term.
	 */
	public static function on_vehicle_renamed( $term_id ) {
		$terms = array_merge( array( $term_id ), get_term_children( $term_id, Yadak_Fitment::TAXONOMY ) );
		$ids   = get_objects_in_term( $terms, Yadak_Fitment::TAXONOMY );
		if ( is_wp_error( $ids ) ) {
			return;
		}
		foreach ( array_unique( $ids ) as $id ) {
			self::index( $id );
		}
	}

	/**
	 * @return int Number of products indexed.
	 */
	public static function reindex_all() {
		$count = 0;
		$page  = 1;
		do {
			$ids = wc_get_products(
				array(
					'status' => array( 'publish', 'private', 'draft', 'pending' ),
					'limit'  => 200,
					'page'   => $page,
					'return' => 'ids',
				)
			);
			foreach ( $ids as $id ) {
				self::index( $id );
				++$count;
			}
			++$page;
		} while ( count( $ids ) === 200 );
		return $count;
	}

	public static function admin_reindex() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_reindex' );
		$count = self::reindex_all();
		wp_safe_redirect( add_query_arg( 'yadak_reindexed', $count, admin_url( 'edit.php?post_type=product' ) ) );
		exit;
	}

	public static function plugin_links( $links ) {
		$url     = wp_nonce_url( admin_url( 'admin-post.php?action=yadak_reindex' ), 'yadak_reindex' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'بازسازی ایندکس جستجو', 'yadak-core' ) . '</a>';
		return $links;
	}

	/**
	 * Replace the default search clause for storefront product searches.
	 *
	 * @param string   $search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function posts_search( $search, $query ) {
		global $wpdb;

		if ( is_admin() || ! $query->is_search() || '' === $search ) {
			return $search;
		}
		$post_type = (array) $query->get( 'post_type' );
		if ( ! in_array( 'product', $post_type, true ) ) {
			return $search;
		}

		$raw   = (string) $query->get( 's' );
		$norm  = yadak_normalize( $raw );
		$words = array_filter( explode( ' ', $norm ), static function ( $w ) {
			return '' !== $w;
		} );
		if ( ! $words ) {
			return $search;
		}

		$index_match = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} ysm WHERE ysm.post_id = {$wpdb->posts}.ID AND ysm.meta_key = '" . self::META . "' AND ysm.meta_value LIKE %s)";

		$all_words = array();
		foreach ( $words as $word ) {
			$like        = '%' . $wpdb->esc_like( $word ) . '%';
			$all_words[] = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s OR {$index_match})", $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$clause = '(' . implode( ' AND ', $all_words ) . ')';

		// The whole query as one part number, e.g. "04465 0K090".
		$compact = yadak_compact_number( $raw );
		if ( strlen( $compact ) >= 4 ) {
			$clause = '(' . $clause . ' OR ' . $wpdb->prepare( $index_match, '%' . $wpdb->esc_like( $compact ) . '%' ) . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return ' AND ' . $clause . ' ';
	}
}
