<?php
/**
 * Core sitemap provider listing every "category + vehicle" page with parts
 * (/wp-sitemap-yadakparts-1.xml). Vehicle and category archives themselves
 * are already in WordPress's taxonomy sitemaps.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Sitemap_Provider extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'yadakparts';
		$this->object_type = 'yadakparts';
	}

	/**
	 * @return string[]
	 */
	private function urls() {
		$urls     = array();
		$vehicles = get_terms(
			array(
				'taxonomy'   => Yadak_Fitment::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $vehicles ) ) {
			return $urls;
		}
		foreach ( $vehicles as $vehicle ) {
			foreach ( Yadak_SEO::categories_for_vehicle( $vehicle ) as $cat ) {
				$urls[] = Yadak_SEO::url( $vehicle, $cat );
			}
		}
		return $urls;
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		$per   = wp_sitemaps_get_max_urls( $this->object_type );
		$slice = array_slice( $this->urls(), ( $page_num - 1 ) * $per, $per );
		return array_map(
			static function ( $loc ) {
				return array( 'loc' => $loc );
			},
			$slice
		);
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return (int) ceil( count( $this->urls() ) / wp_sitemaps_get_max_urls( $this->object_type ) );
	}
}
