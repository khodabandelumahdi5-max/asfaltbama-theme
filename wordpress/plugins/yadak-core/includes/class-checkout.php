<?php
/**
 * Iranian checkout: mobile number and 10-digit postcode validation, Persian
 * digits accepted everywhere, Iran as the default country.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Checkout {

	public static function init() {
		add_filter( 'default_checkout_billing_country', array( __CLASS__, 'default_country' ) );
		add_filter( 'default_checkout_shipping_country', array( __CLASS__, 'default_country' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'fields' ) );
		// Checkout JS re-applies address fields per country from these, so set them here too.
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'address_fields' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'normalize_posted' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'wc_get_price_decimals', array( __CLASS__, 'price_decimals' ) );
		add_filter( 'woocommerce_price_format', array( __CLASS__, 'price_format' ), 10, 2 );
		add_filter( 'woocommerce_get_terms_page_id', array( __CLASS__, 'published_terms_page' ) );
	}

	/**
	 * Ask customers to accept the terms only once that page is published;
	 * a draft would put a required checkbox next to a 404 link.
	 */
	public static function published_terms_page( $id ) {
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	private static function is_rial_currency() {
		return in_array( get_woocommerce_currency(), array( 'IRT', 'IRR', 'IRHT', 'IRHR' ), true );
	}

	/**
	 * Toman and Rial have no decimals.
	 *
	 * @param int $decimals Decimals.
	 * @return int
	 */
	public static function price_decimals( $decimals ) {
		return self::is_rial_currency() ? 0 : $decimals;
	}

	/**
	 * "۱٬۸۵۰٬۰۰۰ تومان": amount first, then a space and the currency name.
	 *
	 * @param string $format   Format.
	 * @param string $position Currency position setting.
	 * @return string
	 */
	public static function price_format( $format, $position ) {
		return self::is_rial_currency() ? '%2$s&nbsp;%1$s' : $format;
	}

	public static function default_country( $country ) {
		return $country ? $country : 'IR';
	}

	/**
	 * Is this a valid Iranian mobile number (09xxxxxxxxx, +989…, 00989…)?
	 *
	 * @param string $phone Phone.
	 * @return string Normalized 09xxxxxxxxx, or '' when invalid.
	 */
	public static function normalize_mobile( $phone ) {
		$digits = preg_replace( '/\D+/', '', yadak_normalize_digits( $phone ) );
		if ( preg_match( '/^(?:0098|98|0)?(9\d{9})$/', $digits, $m ) ) {
			return '0' . $m[1];
		}
		return '';
	}

	/**
	 * @param array $fields Default address fields.
	 * @return array
	 */
	public static function address_fields( $fields ) {
		if ( isset( $fields['phone'] ) ) {
			$fields['phone']['required'] = true;
			$fields['phone']['label']    = __( 'شماره موبایل', 'yadak-core' );
			$fields['phone']['priority'] = 5; // Mobile first: the key contact in Iran (SMS updates).
		}
		unset( $fields['company'] ); // Business details live in the customer profile.
		if ( isset( $fields['address_2'] ) ) {
			$fields['address_2']['placeholder'] = __( 'پلاک، واحد (اختیاری)', 'yadak-core' );
		}
		return $fields;
	}

	public static function fields( $fields ) {
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required']    = true;
			$fields['billing']['billing_phone']['label']       = __( 'شماره موبایل', 'yadak-core' );
			$fields['billing']['billing_phone']['placeholder'] = '09xxxxxxxxx';
			$fields['billing']['billing_phone']['custom_attributes'] = array(
				'inputmode' => 'tel',
				'dir'       => 'ltr',
			);
		}
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['priority'] = 5;
			$fields['billing']['billing_phone']['description'] = __( 'وضعیت سفارش و کد رهگیری پیامک می‌شود.', 'yadak-core' );
		}
		// Many customers have no e-mail; SMS covers order updates.
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['required'] = false;
			$fields['billing']['billing_email']['label']    = __( 'ایمیل (اختیاری)', 'yadak-core' );
			$fields['billing']['billing_email']['priority'] = 120;
		}
		unset( $fields['billing']['billing_company'], $fields['shipping']['shipping_company'] );
		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['placeholder'] = __( 'مثلاً: قبل از ارسال تماس بگیرید / VIN خودرو برای تطبیق قطعه', 'yadak-core' );
		}
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$key = $group . '_postcode';
			if ( isset( $fields[ $group ][ $key ] ) ) {
				$fields[ $group ][ $key ]['placeholder']       = __( 'کد پستی ۱۰ رقمی', 'yadak-core' );
				$fields[ $group ][ $key ]['custom_attributes'] = array(
					'inputmode' => 'numeric',
					'dir'       => 'ltr',
				);
			}
		}
		return $fields;
	}

	public static function normalize_posted( $data ) {
		foreach ( array( 'billing_phone', 'billing_postcode', 'shipping_postcode' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = preg_replace( '/[\s\-]+/', '', yadak_normalize_digits( $data[ $key ] ) );
			}
		}
		if ( ! empty( $data['billing_phone'] ) && self::normalize_mobile( $data['billing_phone'] ) ) {
			$data['billing_phone'] = self::normalize_mobile( $data['billing_phone'] );
		}
		return $data;
	}

	/**
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		if ( ! empty( $data['billing_phone'] ) && ! self::normalize_mobile( $data['billing_phone'] ) ) {
			$errors->add( 'validation', __( 'شماره موبایل معتبر نیست؛ مثال: 09121234567', 'yadak-core' ) );
		}
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( 'shipping' === $group && empty( $data['ship_to_different_address'] ) ) {
				continue;
			}
			$country  = isset( $data[ $group . '_country' ] ) ? $data[ $group . '_country' ] : 'IR';
			$postcode = isset( $data[ $group . '_postcode' ] ) ? $data[ $group . '_postcode' ] : '';
			if ( 'IR' === $country && '' !== $postcode && ! preg_match( '/^\d{10}$/', $postcode ) ) {
				$errors->add( 'validation', __( 'کد پستی باید ۱۰ رقم باشد.', 'yadak-core' ) );
			}
		}
	}

	/**
	 * Use the classic cart/checkout shortcodes (needed by the credit gateway
	 * and the Iranian field rules) when the pages still hold the default blocks.
	 */
	public static function use_classic_pages() {
		$pages = array(
			'checkout' => array( 'woocommerce/checkout', '[woocommerce_checkout]' ),
			'cart'     => array( 'woocommerce/cart', '[woocommerce_cart]' ),
		);
		foreach ( $pages as $page => $swap ) {
			$page_id = wc_get_page_id( $page );
			$post    = $page_id > 0 ? get_post( $page_id ) : null;
			if ( $post && has_block( $swap[0], $post ) ) {
				wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => '<!-- wp:shortcode -->' . $swap[1] . '<!-- /wp:shortcode -->',
					)
				);
			}
		}
	}
}
