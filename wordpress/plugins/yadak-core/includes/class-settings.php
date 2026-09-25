<?php
/**
 * "فروشگاه یدک" admin menu and settings (seller details for invoices, SMS,
 * warehouses, Jalali dates). Other modules add their pages under the same
 * menu with parent slug Yadak_Settings::MENU.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Settings {

	const MENU   = 'yadak';
	const OPTION = 'yadak_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'seller_name'          => get_bloginfo( 'name' ),
			'seller_economic_code' => '',
			'seller_national_id'   => '',
			'seller_reg_no'        => '',
			'seller_address'       => '',
			'seller_postcode'      => '',
			'seller_phone'         => '',
			'jalali'               => 'yes',
			'sms_driver'           => 'none',
			'sms_api_key'          => '',
			'sms_username'         => '',
			'sms_password'         => '',
			'sms_sender'           => '',
			'admin_mobile'         => '',
			'warehouses'           => '',
			'reminder_days_before' => '0',
		) + Yadak_SMS::default_templates();
	}

	/**
	 * @param string $key Setting.
	 * @return string
	 */
	public static function get( $key ) {
		static $cache = null;
		if ( null === $cache || doing_action( 'update_option_' . self::OPTION ) ) {
			$cache = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		}
		return isset( $cache[ $key ] ) ? (string) $cache[ $key ] : '';
	}

	public static function menu() {
		add_menu_page(
			__( 'فروشگاه یدک', 'yadak-core' ),
			__( 'فروشگاه یدک', 'yadak-core' ),
			'manage_woocommerce',
			self::MENU,
			array( 'Yadak_Dashboard', 'render' ),
			'dashicons-car',
			56
		);
		add_submenu_page( self::MENU, __( 'داشبورد', 'yadak-core' ), __( 'داشبورد', 'yadak-core' ), 'manage_woocommerce', self::MENU, array( 'Yadak_Dashboard', 'render' ) );
		add_submenu_page( self::MENU, __( 'تنظیمات', 'yadak-core' ), __( 'تنظیمات', 'yadak-core' ), 'manage_woocommerce', 'yadak-settings', array( __CLASS__, 'render' ), 99 );
	}

	/**
	 * Fields per tab: key => [label, type, help].
	 *
	 * @return array<string,array{0:string,1:array}>
	 */
	public static function tabs() {
		$sms = array(
			'sms_driver'   => array( __( 'سرویس پیامک', 'yadak-core' ), 'select', '', Yadak_SMS::drivers() ),
			'sms_api_key'  => array( __( 'کلید API (کاوه‌نگار)', 'yadak-core' ), 'text' ),
			'sms_username' => array( __( 'نام کاربری (ملی‌پیامک)', 'yadak-core' ), 'text' ),
			'sms_password' => array( __( 'رمز عبور (ملی‌پیامک)', 'yadak-core' ), 'password' ),
			'sms_sender'   => array( __( 'شماره فرستنده', 'yadak-core' ), 'text' ),
			'admin_mobile' => array( __( 'موبایل مدیر (برای هشدارها)', 'yadak-core' ), 'text', __( 'چند شماره را با کاما جدا کنید.', 'yadak-core' ) ),
		);
		foreach ( Yadak_SMS::events() as $event => $label ) {
			$sms[ 'sms_tpl_' . $event ] = array( $label, 'textarea', __( 'خالی = ارسال نشود.', 'yadak-core' ) );
		}
		$sms['_sms_help'] = array( __( 'متغیرها', 'yadak-core' ), 'help', '{name} {order} {total} {status} {tracking} {amount} {date} {product} {link} {site}' );

		return array(
			'seller'     => array(
				__( 'فروشنده و فاکتور', 'yadak-core' ),
				array(
					'seller_name'          => array( __( 'نام فروشنده / شرکت', 'yadak-core' ), 'text' ),
					'seller_economic_code' => array( __( 'کد اقتصادی', 'yadak-core' ), 'text' ),
					'seller_national_id'   => array( __( 'شناسه ملی / کد ملی', 'yadak-core' ), 'text' ),
					'seller_reg_no'        => array( __( 'شماره ثبت', 'yadak-core' ), 'text' ),
					'seller_address'       => array( __( 'نشانی', 'yadak-core' ), 'textarea' ),
					'seller_postcode'      => array( __( 'کد پستی', 'yadak-core' ), 'text' ),
					'seller_phone'         => array( __( 'تلفن', 'yadak-core' ), 'text' ),
					'jalali'               => array(
						__( 'تاریخ شمسی', 'yadak-core' ),
						'select',
						__( 'نمایش تاریخ‌ها در سایت و پنل به شمسی. اگر افزونه دیگری تاریخ را شمسی می‌کند، خاموش کنید.', 'yadak-core' ),
						array(
							'yes' => __( 'روشن', 'yadak-core' ),
							'no'  => __( 'خاموش', 'yadak-core' ),
						),
					),
				),
			),
			'sms'        => array( __( 'پیامک', 'yadak-core' ), $sms ),
			'warehouses' => array(
				__( 'انبارها و یادآوری', 'yadak-core' ),
				array(
					'warehouses'           => array(
						__( 'انبارها', 'yadak-core' ),
						'textarea',
						__( 'هر انبار در یک خط: «کد | نام». مثال: thr | انبار تهران. ترتیب خطوط = اولویت برداشت برای سفارش‌ها. کد را بعداً تغییر ندهید.', 'yadak-core' ),
					),
					'reminder_days_before' => array( __( 'یادآوری تعویض، چند روز زودتر', 'yadak-core' ), 'number' ),
				),
			),
		);
	}

	public static function register() {
		register_setting(
			'yadak_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Keep settings from other tabs; sanitize the posted ones.
	 *
	 * @param array $input Posted.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$fields  = array();
		foreach ( self::tabs() as $tab ) {
			$fields += $tab[1];
		}
		foreach ( (array) $input as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) || 'help' === $fields[ $key ][1] ) {
				continue;
			}
			$current[ $key ] = 'textarea' === $fields[ $key ][1] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		return $current;
	}

	public static function render() {
		$tabs   = self::tabs();
		$active = isset( $_GET['tab'], $tabs[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'seller'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'تنظیمات فروشگاه یدک', 'yadak-core' ) . '</h1><nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( admin_url( 'admin.php?page=yadak-settings&tab=' . $key ) ),
				$key === $active ? ' nav-tab-active' : '',
				esc_html( $tab[0] )
			);
		}
		echo '</nav><form method="post" action="options.php">';
		settings_fields( 'yadak_settings' );
		echo '<table class="form-table" role="presentation">';
		foreach ( $tabs[ $active ][1] as $key => $field ) {
			$name  = self::OPTION . '[' . $key . ']';
			$value = self::get( $key );
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td>';
			switch ( $field[1] ) {
				case 'help':
					echo '<code dir="ltr">' . esc_html( $field[2] ) . '</code>';
					break;
				case 'textarea':
					echo '<textarea class="large-text" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
					break;
				case 'select':
					echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $field[3] as $opt => $label ) {
						echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $value, $opt, false ) . '>' . esc_html( $label ) . '</option>';
					}
					echo '</select>';
					break;
				default:
					echo '<input class="regular-text" type="' . esc_attr( $field[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" autocomplete="off">';
			}
			if ( ! empty( $field[2] ) && 'help' !== $field[1] ) {
				echo '<p class="description">' . esc_html( $field[2] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button();
		echo '</form>';
		if ( 'sms' === $active ) {
			Yadak_SMS::render_test_form();
		}
		echo '</div>';
	}
}
