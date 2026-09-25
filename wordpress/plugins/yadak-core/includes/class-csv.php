<?php
/**
 * Bulk editing through WooCommerce's built-in CSV importer/exporter
 * (Products › Export / Import). Adds Persian-labelled columns for the part
 * fields, tier prices and compatible vehicles, so a file exported from the
 * store can be edited in Excel and imported back with "Update existing
 * products" to change prices and stock of thousands of products at once.
 *
 * Vehicles column format (like categories): "Peugeot > 206 > TU5, Saipa > Pride".
 * Missing vehicles are created.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_CSV {

	const VEHICLES = 'yadak_vehicles';

	/**
	 * Export column ID => [meta key, label].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function meta_columns() {
		$columns = array();
		foreach ( Yadak_Part_Data::fields() as $meta_key => $label ) {
			$columns[ ltrim( $meta_key, '_' ) ] = array( $meta_key, $label );
		}
		foreach ( Yadak_Pricing::fields() as $meta_key => $label ) {
			$columns[ ltrim( $meta_key, '_' ) ] = array( $meta_key, $label );
		}
		return $columns;
	}

	private static function vehicles_label() {
		return __( 'خودروهای سازگار', 'yadak-core' );
	}

	public static function init() {
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'import_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'import_defaults' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'import_vehicles' ), 10, 2 );

		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'export_columns' ) );
		foreach ( self::meta_columns() as $column_id => $def ) {
			$meta_key = $def[0];
			add_filter(
				'woocommerce_product_export_product_column_' . $column_id,
				static function ( $value, $product ) use ( $meta_key ) {
					return $product->get_meta( $meta_key, true, 'edit' );
				},
				10,
				2
			);
		}
		add_filter( 'woocommerce_product_export_product_column_' . self::VEHICLES, array( __CLASS__, 'export_vehicles' ), 10, 2 );
	}

	public static function import_options( $options ) {
		foreach ( self::meta_columns() as $def ) {
			$options[ 'meta:' . $def[0] ] = $def[1];
		}
		$options[ self::VEHICLES ] = self::vehicles_label();
		return $options;
	}

	/**
	 * Map exported column headers back to their fields automatically.
	 *
	 * @param array $mappings Header => field.
	 * @return array
	 */
	public static function import_defaults( $mappings ) {
		foreach ( self::meta_columns() as $def ) {
			$mappings[ $def[1] ] = 'meta:' . $def[0];
		}
		$mappings[ self::vehicles_label() ] = self::VEHICLES;
		$mappings['Vehicles']               = self::VEHICLES;
		$mappings['Part Number']            = 'meta:_yadak_part_number';
		$mappings['OEM']                    = 'meta:_yadak_oem_numbers';
		return $mappings;
	}

	public static function export_columns( $columns ) {
		foreach ( self::meta_columns() as $column_id => $def ) {
			$columns[ $column_id ] = $def[1];
		}
		$columns[ self::VEHICLES ] = self::vehicles_label();
		return $columns;
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $data    Parsed row.
	 */
	public static function import_vehicles( $product, $data ) {
		// Model years may be typed in Jalali; store Gregorian like the product form does.
		foreach ( array( '_yadak_year_from', '_yadak_year_to' ) as $key ) {
			$year = $product->get_meta( $key );
			if ( '' !== $year && Yadak_Part_Data::normalize_year( $year ) !== $year ) {
				$product->update_meta_data( $key, Yadak_Part_Data::normalize_year( $year ) );
				$product->save_meta_data();
			}
		}
		if ( ! isset( $data[ self::VEHICLES ] ) || $product->is_type( 'variation' ) ) {
			return;
		}
		$term_ids = array();
		foreach ( array_filter( array_map( 'trim', preg_split( '/[,|،]/u', (string) $data[ self::VEHICLES ] ) ) ) as $path ) {
			$parent = 0;
			foreach ( array_filter( array_map( 'trim', explode( '>', $path ) ) ) as $name ) {
				$existing = get_terms(
					array(
						'taxonomy'   => Yadak_Fitment::TAXONOMY,
						'name'       => $name,
						'parent'     => $parent,
						'hide_empty' => false,
						'fields'     => 'ids',
						'number'     => 1,
					)
				);
				if ( ! is_wp_error( $existing ) && $existing ) {
					$parent = (int) $existing[0];
					continue;
				}
				$created = wp_insert_term( $name, Yadak_Fitment::TAXONOMY, array( 'parent' => $parent ) );
				if ( is_wp_error( $created ) ) {
					continue 2;
				}
				$parent = (int) $created['term_id'];
			}
			if ( $parent ) {
				$term_ids[] = $parent;
			}
		}
		wp_set_object_terms( $product->get_id(), $term_ids, Yadak_Fitment::TAXONOMY );
	}

	/**
	 * @param string     $value   Value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function export_vehicles( $value, $product ) {
		$terms = wp_get_object_terms( $product->get_id(), Yadak_Fitment::TAXONOMY );
		if ( is_wp_error( $terms ) ) {
			return '';
		}
		return implode(
			', ',
			array_map(
				static function ( $term ) {
					return str_replace( ' › ', ' > ', Yadak_Fitment::path( $term ) );
				},
				$terms
			)
		);
	}
}
