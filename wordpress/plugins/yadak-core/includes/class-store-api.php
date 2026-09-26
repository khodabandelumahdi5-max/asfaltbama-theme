<?php
/**
 * Part data in WooCommerce's public Store API (/wp-json/wc/store/v1/products),
 * for the mobile app: part number, OEM numbers and compatible vehicles under
 * the product's `extensions.yadak` key.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Store_API {

	public static function init() {
		// WooCommerce may have loaded the Store API before our plugins_loaded init.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register' ) );
		}
	}

	public static function register() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'product',
				'namespace'       => 'yadak',
				'data_callback'   => array( __CLASS__, 'data' ),
				'schema_callback' => array( __CLASS__, 'schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function data( $product ) {
		$vehicles = wp_get_post_terms( $product->get_id(), Yadak_Fitment::TAXONOMY );
		return array(
			'part_number' => (string) $product->get_meta( '_yadak_part_number' ),
			'oem_numbers' => Yadak_Part_Data::split_list( $product->get_meta( '_yadak_oem_numbers' ) ),
			'vehicles'    => is_wp_error( $vehicles ) ? array() : array_values(
				array_map(
					static function ( $term ) {
						return str_replace( ' › ', ' ', Yadak_Fitment::path( $term ) );
					},
					$vehicles
				)
			),
		);
	}

	public static function schema() {
		return array(
			'part_number' => array(
				'description' => __( 'شماره فنی', 'yadak-core' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'oem_numbers' => array(
				'description' => __( 'شماره‌های OEM', 'yadak-core' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'readonly'    => true,
			),
			'vehicles'    => array(
				'description' => __( 'خودروهای سازگار', 'yadak-core' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'readonly'    => true,
			),
		);
	}
}
