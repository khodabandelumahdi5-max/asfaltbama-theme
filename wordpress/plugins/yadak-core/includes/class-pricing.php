<?php
/**
 * Tiered prices: each product can carry a "b2b" (همکار) and a "dealer"
 * (فروشنده/عمده) price. Customers in a B2B group pay their tier price when
 * it is set and lower than the retail price they would otherwise pay.
 *
 * Works for simple products and for each variation of variable products.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Pricing {

	public static function init() {
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'simple_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation' ), 10, 2 );

		foreach ( array( 'woocommerce_product_get_price', 'woocommerce_product_variation_get_price' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'filter_price' ), 20, 2 );
		}
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'variation_prices_hash' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
	}

	/**
	 * @param string $tier Tier key.
	 * @return string Meta key.
	 */
	public static function meta_key( $tier ) {
		return '_yadak_price_' . $tier;
	}

	/**
	 * Extra price fields: meta key => label. Cost price is internal (never shown to customers).
	 *
	 * @return array<string,string>
	 */
	public static function fields() {
		$fields = array( '_yadak_cost' => __( 'قیمت خرید / بهای تمام‌شده', 'yadak-core' ) );
		foreach ( yadak_price_tiers() as $tier => $label ) {
			$fields[ self::meta_key( $tier ) ] = $label;
		}
		return $fields;
	}

	public static function simple_fields() {
		echo '<div class="options_group yadak-tier-prices">';
		foreach ( self::fields() as $key => $label ) {
			woocommerce_wp_text_input(
				array(
					'id'          => $key,
					'label'       => $label . ' (' . get_woocommerce_currency_symbol() . ')',
					'data_type'   => 'price',
					'desc_tip'    => true,
					'description' => '_yadak_cost' === $key ? __( 'برای محاسبه سود و ارزش انبار؛ به مشتری نمایش داده نمی‌شود.', 'yadak-core' ) : __( 'خالی بگذارید تا این گروه قیمت عادی را ببیند.', 'yadak-core' ),
				)
			);
		}
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function save_simple( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		foreach ( array_keys( self::fields() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$product->update_meta_data( $key, wc_format_decimal( yadak_normalize_digits( wp_unslash( $_POST[ $key ] ) ) ) );
			}
		}
		// phpcs:enable
	}

	public static function variation_fields( $loop, $variation_data, $variation ) {
		foreach ( self::fields() as $key => $label ) {
			woocommerce_wp_text_input(
				array(
					'id'            => $key . '_' . $loop,
					'name'          => $key . '[' . $loop . ']',
					'value'         => wc_format_localized_price( get_post_meta( $variation->ID, $key, true ) ),
					'label'         => $label . ' (' . get_woocommerce_currency_symbol() . ')',
					'data_type'     => 'price',
					'wrapper_class' => 'form-row form-row-first',
				)
			);
		}
	}

	/**
	 * @param WC_Product_Variation $variation Variation.
	 * @param int                  $i         Loop index.
	 */
	public static function save_variation( $variation, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the variations nonce.
		foreach ( array_keys( self::fields() ) as $key ) {
			if ( isset( $_POST[ $key ][ $i ] ) ) {
				$variation->update_meta_data( $key, wc_format_decimal( yadak_normalize_digits( wp_unslash( $_POST[ $key ][ $i ] ) ) ) );
			}
		}
		// phpcs:enable
	}

	/**
	 * Tier price of a product for a tier, or null when not set.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $tier    Tier.
	 * @return float|null
	 */
	public static function tier_price( $product, $tier ) {
		if ( ! $tier ) {
			return null;
		}
		$value = $product->get_meta( self::meta_key( $tier ), true, 'edit' );
		return ( '' === $value || null === $value ) ? null : (float) $value;
	}

	/**
	 * Price a given customer pays (used for quotes and admin tools, where the
	 * current user is staff, not the customer).
	 *
	 * @param WC_Product $product Product.
	 * @param int        $user_id Customer.
	 * @return float
	 */
	public static function price_for_user( $product, $user_id ) {
		$base = (float) $product->get_price( 'edit' );
		$tier = self::tier_price( $product, yadak_get_price_tier( $user_id ) );
		return ( null !== $tier && ( ! $base || $tier < $base ) ) ? $tier : $base;
	}

	/**
	 * @param string     $price   Price.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function filter_price( $price, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price;
		}
		$tier_price = self::tier_price( $product, yadak_get_price_tier() );
		if ( null === $tier_price ) {
			return $price;
		}
		if ( '' === $price || $tier_price < (float) $price ) {
			return (string) $tier_price;
		}
		return $price;
	}

	/**
	 * Variation price caches must differ per tier.
	 *
	 * @param array $hash Hash parts.
	 * @return array
	 */
	public static function variation_prices_hash( $hash ) {
		$hash[] = 'yadak-tier-' . yadak_get_price_tier();
		return $hash;
	}

	/**
	 * Show B2B customers their price next to the retail price.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		$tier = yadak_get_price_tier();
		if ( ! $tier || $product->is_type( 'variable' ) ) {
			return $html;
		}
		$tier_price = self::tier_price( $product, $tier );
		$regular    = (float) $product->get_regular_price();
		if ( null === $tier_price || ! $regular || $tier_price >= $regular ) {
			return $html;
		}
		$label = 'dealer' === $tier ? __( 'قیمت عمده شما', 'yadak-core' ) : __( 'قیمت همکار شما', 'yadak-core' );
		return sprintf(
			'<del aria-hidden="true">%1$s</del> <ins>%2$s</ins> <span class="yadak-tier-label">%3$s</span>',
			wc_price( wc_get_price_to_display( $product, array( 'price' => $regular ) ) ),
			wc_price( wc_get_price_to_display( $product ) ),
			esc_html( $label )
		);
	}
}
