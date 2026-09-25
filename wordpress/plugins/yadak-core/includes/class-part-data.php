<?php
/**
 * Spare-part fields on products: part number, OEM numbers, alternative
 * names, quality tier, country of origin, warranty.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Part_Data {

	/**
	 * Meta key => label. Also used by the CSV importer/exporter.
	 *
	 * @return array<string,string>
	 */
	public static function fields() {
		return array(
			'_yadak_part_number' => __( 'شماره فنی (Part Number)', 'yadak-core' ),
			'_yadak_oem_numbers' => __( 'شماره‌های OEM', 'yadak-core' ),
			'_yadak_alt_names'   => __( 'نام‌های دیگر / عامیانه', 'yadak-core' ),
			'_yadak_quality'     => __( 'کیفیت', 'yadak-core' ),
			'_yadak_origin'      => __( 'کشور سازنده', 'yadak-core' ),
			'_yadak_warranty'    => __( 'گارانتی', 'yadak-core' ),
			'_yadak_replace_days' => __( 'دوره تعویض (روز)', 'yadak-core' ),
			'_yadak_moodian_id'  => __( 'شناسه کالا (مودیان)', 'yadak-core' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function qualities() {
		return array(
			''            => __( '— انتخاب —', 'yadak-core' ),
			'genuine'     => __( 'اصلی (جنیون)', 'yadak-core' ),
			'oem'         => __( 'OEM (سازنده قطعه اصلی)', 'yadak-core' ),
			'aftermarket' => __( 'افترمارکت', 'yadak-core' ),
			'used'        => __( 'استوک', 'yadak-core' ),
		);
	}

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'admin_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'admin_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'summary_specs' ), 21 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'loop_part_number' ), 4 );
		add_filter( 'woocommerce_display_product_attributes', array( __CLASS__, 'additional_information' ), 10, 2 );
	}

	public static function admin_tab( $tabs ) {
		$tabs['yadak_part'] = array(
			'label'    => __( 'مشخصات قطعه', 'yadak-core' ),
			'target'   => 'yadak_part_data',
			'priority' => 15,
		);
		return $tabs;
	}

	public static function admin_panel() {
		echo '<div id="yadak_part_data" class="panel woocommerce_options_panel hidden"><div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_yadak_part_number',
				'label'       => __( 'شماره فنی', 'yadak-core' ),
				'placeholder' => 'مثلاً 0986494123',
				'desc_tip'    => true,
				'description' => __( 'شماره فنی سازنده قطعه. در جستجو با یا بدون خط تیره پیدا می‌شود.', 'yadak-core' ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => '_yadak_oem_numbers',
				'label'       => __( 'شماره‌های OEM', 'yadak-core' ),
				'placeholder' => __( 'هر شماره در یک خط یا با کاما جدا شود', 'yadak-core' ),
				'desc_tip'    => true,
				'description' => __( 'شماره‌های فنی خودروساز و شماره‌های جایگزین (Cross Reference).', 'yadak-core' ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => '_yadak_alt_names',
				'label'       => __( 'نام‌های دیگر', 'yadak-core' ),
				'placeholder' => __( 'مثلاً: لنت جلو، lent, brake pad', 'yadak-core' ),
				'desc_tip'    => true,
				'description' => __( 'نام‌های عامیانه، فینگلیش یا انگلیسی که مشتری ممکن است جستجو کند. در سایت نمایش داده نمی‌شود.', 'yadak-core' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => '_yadak_quality',
				'label'   => __( 'کیفیت', 'yadak-core' ),
				'options' => self::qualities(),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => '_yadak_origin',
				'label' => __( 'کشور سازنده', 'yadak-core' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_yadak_warranty',
				'label'       => __( 'گارانتی', 'yadak-core' ),
				'placeholder' => __( 'مثلاً: ۶ ماه ضمانت اصالت و سلامت', 'yadak-core' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_yadak_replace_days',
				'label'             => __( 'دوره تعویض (روز)', 'yadak-core' ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => 0 ),
				'desc_tip'          => true,
				'description'       => __( 'برای قطعات مصرفی (لنت، فیلتر، روغن، تسمه). این تعداد روز بعد از تحویل سفارش، به مشتری یادآوری تعویض ارسال می‌شود. خالی = بدون یادآوری.', 'yadak-core' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_yadak_moodian_id',
				'label'       => __( 'شناسه کالا (مودیان)', 'yadak-core' ),
				'desc_tip'    => true,
				'description' => __( 'شناسه ۱۳ رقمی کالا/خدمت در سامانه مودیان، برای خروجی صورتحساب.', 'yadak-core' ),
			)
		);
		echo '</div></div>';
	}

	/**
	 * @param WC_Product $product Product being saved from the admin screen.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		foreach ( array_keys( self::fields() ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] );
			$value = in_array( $key, array( '_yadak_oem_numbers', '_yadak_alt_names' ), true ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			if ( '_yadak_quality' === $key && ! array_key_exists( $value, self::qualities() ) ) {
				$value = '';
			}
			$product->update_meta_data( $key, $value );
		}
		// phpcs:enable
	}

	/**
	 * Split a list of numbers/names typed one per line or comma separated.
	 *
	 * @param string $value Raw value.
	 * @return string[]
	 */
	public static function split_list( $value ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,،]+/u', (string) $value ) ) ) );
	}

	/**
	 * Visible spec rows for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,string> Label => value.
	 */
	public static function spec_rows( $product ) {
		$rows    = array();
		$brands  = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) ) : array();
		$quality = $product->get_meta( '_yadak_quality' );
		$quals   = self::qualities();

		if ( ! is_wp_error( $brands ) && $brands ) {
			$rows[ __( 'برند', 'yadak-core' ) ] = implode( '، ', $brands );
		}
		if ( $product->get_meta( '_yadak_part_number' ) ) {
			$rows[ __( 'شماره فنی', 'yadak-core' ) ] = $product->get_meta( '_yadak_part_number' );
		}
		$oem = self::split_list( $product->get_meta( '_yadak_oem_numbers' ) );
		if ( $oem ) {
			$rows[ __( 'شماره OEM', 'yadak-core' ) ] = implode( '، ', $oem );
		}
		if ( $quality && isset( $quals[ $quality ] ) ) {
			$rows[ __( 'کیفیت', 'yadak-core' ) ] = $quals[ $quality ];
		}
		if ( $product->get_meta( '_yadak_origin' ) ) {
			$rows[ __( 'کشور سازنده', 'yadak-core' ) ] = $product->get_meta( '_yadak_origin' );
		}
		if ( $product->get_meta( '_yadak_warranty' ) ) {
			$rows[ __( 'گارانتی', 'yadak-core' ) ] = $product->get_meta( '_yadak_warranty' );
		}
		if ( $product->get_sku() && $product->get_sku() !== $product->get_meta( '_yadak_part_number' ) ) {
			$rows[ __( 'کد کالا', 'yadak-core' ) ] = $product->get_sku();
		}
		return $rows;
	}

	public static function summary_specs() {
		global $product;
		if ( ! $product ) {
			return;
		}
		$rows = self::spec_rows( $product );
		if ( ! $rows ) {
			return;
		}
		echo '<dl class="yadak-specs">';
		foreach ( $rows as $label => $value ) {
			printf( '<div><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</dl>';
	}

	public static function loop_part_number() {
		global $product;
		if ( $product && $product->get_meta( '_yadak_part_number' ) ) {
			printf( '<span class="yadak-loop-pn">%s</span>', esc_html( $product->get_meta( '_yadak_part_number' ) ) );
		}
	}

	/**
	 * Add origin/warranty to the "Additional information" tab.
	 *
	 * @param array      $attributes Rows.
	 * @param WC_Product $product    Product.
	 * @return array
	 */
	public static function additional_information( $attributes, $product ) {
		$extra = array(
			'_yadak_origin'   => __( 'کشور سازنده', 'yadak-core' ),
			'_yadak_warranty' => __( 'گارانتی', 'yadak-core' ),
		);
		foreach ( $extra as $key => $label ) {
			$value = $product->get_meta( $key );
			if ( $value ) {
				$attributes[ 'yadak' . $key ] = array(
					'label' => $label,
					'value' => esc_html( $value ),
				);
			}
		}
		return $attributes;
	}
}
