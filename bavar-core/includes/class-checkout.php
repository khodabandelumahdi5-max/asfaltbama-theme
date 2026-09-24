<?php
/**
 * Checkout tuned for digital products and Iranian buyers:
 * name, mobile and job only; mobile number doubles as the username.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Checkout {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'fields' ], 20 );
		add_filter( 'woocommerce_checkout_get_value', [ __CLASS__, 'prefill' ], 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate' ], 10, 2 );
		add_filter( 'woocommerce_checkout_posted_data', [ __CLASS__, 'posted_data' ] );
		add_filter( 'woocommerce_new_customer_data', [ __CLASS__, 'username_is_phone' ] );
		add_filter( 'authenticate', [ __CLASS__, 'login_with_phone' ], 25, 3 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'admin_job' ] );
	}

	/**
	 * Checkout fields.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public static function fields( $fields ) {
		$only_virtual = WC()->cart && ! WC()->cart->needs_shipping();
		if ( $only_virtual ) {
			foreach ( [ 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' ] as $key ) {
				unset( $fields['billing'][ $key ] );
			}
		}

		$fields['billing']['billing_first_name']['priority'] = 10;
		$fields['billing']['billing_last_name']['priority']  = 20;
		$fields['billing']['billing_phone']                  = array_merge(
			$fields['billing']['billing_phone'] ?? [],
			[
				'label'             => 'شماره موبایل',
				'required'          => true,
				'priority'          => 30,
				'class'             => [ 'form-row-wide' ],
				'placeholder'       => '09xx xxx xxxx',
				'custom_attributes' => [
					'dir'       => 'ltr',
					'inputmode' => 'tel',
				],
			]
		);
		$fields['billing']['billing_job'] = [
			'label'    => 'شغل',
			'required' => true,
			'priority' => 40,
			'class'    => [ 'form-row-wide' ],
		];
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['required'] = false;
			$fields['billing']['billing_email']['label']    = 'ایمیل (اختیاری)';
			$fields['billing']['billing_email']['priority'] = 50;
		}
		if ( isset( $fields['account']['account_password'] ) ) {
			$fields['account']['account_password']['label'] = 'رمز عبور برای ورود به حساب کاربری';
		}
		unset( $fields['order']['order_comments'] );
		return $fields;
	}

	/**
	 * Prefill from the lead captured on entry.
	 *
	 * @param mixed  $value Current value.
	 * @param string $input Field key.
	 * @return mixed
	 */
	public static function prefill( $value, $input ) {
		if ( null !== $value && '' !== $value ) {
			return $value;
		}
		$person = Bavar_Frontend::known_person();
		if ( ! $person ) {
			return $value;
		}
		$map = [
			'billing_first_name' => 'first_name',
			'billing_last_name'  => 'last_name',
			'billing_phone'      => 'phone',
			'billing_job'        => 'job',
		];
		return isset( $map[ $input ] ) && '' !== $person[ $map[ $input ] ] ? $person[ $map[ $input ] ] : $value;
	}

	/**
	 * Normalise the phone and fill a placeholder email when none is given
	 * (WooCommerce needs one to create the customer account).
	 *
	 * @param array $data Posted data.
	 * @return array
	 */
	public static function posted_data( $data ) {
		if ( ! empty( $data['billing_phone'] ) ) {
			$normal = bavar_normalize_phone( $data['billing_phone'] );
			if ( $normal ) {
				$data['billing_phone'] = $normal;
			}
		}
		if ( empty( $data['billing_email'] ) && ! empty( $data['billing_phone'] ) ) {
			$host                  = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'example.com';
			$host                  = false === strpos( $host, '.' ) ? $host . '.local' : $host;
			$data['billing_email'] = 'u' . preg_replace( '/\D/', '', $data['billing_phone'] ) . '@' . $host;
		}
		return $data;
	}

	/**
	 * Validate the mobile number.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		if ( ! empty( $data['billing_phone'] ) && ! bavar_normalize_phone( $data['billing_phone'] ) ) {
			$errors->add( 'billing_phone', 'شماره موبایل معتبر نیست. نمونه: ۰۹۱۲۱۲۳۴۵۶۷' );
		}
	}

	/**
	 * New customers log in with their mobile number.
	 *
	 * @param array $data New user data.
	 * @return array
	 */
	public static function username_is_phone( $data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce.
		$phone = bavar_normalize_phone( sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) ) );
		if ( $phone && ! username_exists( $phone ) ) {
			$data['user_login'] = $phone;
		}
		return $data;
	}

	/**
	 * Allow logging in with the mobile number saved on the account.
	 *
	 * @param WP_User|WP_Error|null $user     Result so far.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public static function login_with_phone( $user, $username, $password ) {
		if ( $user instanceof WP_User || '' === (string) $username || '' === (string) $password ) {
			return $user;
		}
		$phone = bavar_normalize_phone( $username );
		if ( ! $phone ) {
			return $user;
		}
		// Username is the phone (possibly typed with Persian digits or +98).
		if ( username_exists( $phone ) ) {
			return $phone === $username ? $user : wp_authenticate_username_password( null, $phone, $password );
		}
		$found = get_users(
			[
				'meta_key'   => 'billing_phone', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery
				'number'     => 2,
			]
		);
		if ( 1 === count( $found ) ) {
			return wp_authenticate_username_password( null, $found[0]->user_login, $password );
		}
		return $user;
	}

	/**
	 * Show the job on the admin order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_job( $order ) {
		$job = $order->get_meta( '_billing_job' );
		if ( $job ) {
			echo '<p><strong>شغل:</strong> ' . esc_html( $job ) . '</p>';
		}
	}
}
