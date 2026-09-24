<?php
/**
 * Yadak child theme for Hello Elementor.
 *
 * The brand name is the WordPress Site Title (Settings › General) and the
 * logo is the Custom Logo, so renaming the brand needs no code change.
 * Contact details, trust seal and homepage texts live in Appearance ›
 * Customize › «اطلاعات فروشگاه».
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YADAK_CHILD_VERSION', '0.1.0' );

/**
 * Theme option with default.
 *
 * @param string $key Option key (without the yadak_ prefix).
 * @return string
 */
function yadak_opt( $key ) {
	$defaults = array(
		'phone'         => '021-00000000',
		'hours'         => 'شنبه تا پنجشنبه ۹ تا ۱۸',
		'address'       => 'تهران',
		'hero_title'    => 'قطعه درست، برای خودروی شما',
		'hero_subtitle' => 'خودروی‌تان را انتخاب کنید تا فقط قطعات سازگار را ببینید؛ با ضمانت اصالت، فاکتور رسمی و ارسال به سراسر ایران.',
		'b2b_url'       => '',
		'trust_seal'    => '',
	);
	$value = get_theme_mod( 'yadak_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
	return is_string( $value ) ? $value : '';
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		// The child theme ships its own layout; keep only the parent's reset.
		wp_dequeue_style( 'hello-elementor-theme-style' );
		wp_dequeue_style( 'hello-elementor-header-footer' );

		wp_enqueue_style( 'yadak-child', get_stylesheet_uri(), array( 'hello-elementor' ), YADAK_CHILD_VERSION );
		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_style( 'yadak-shop', get_stylesheet_directory_uri() . '/assets/css/shop.css', array( 'yadak-child' ), YADAK_CHILD_VERSION );
		}
		wp_enqueue_script( 'yadak-child', get_stylesheet_directory_uri() . '/assets/js/theme.js', array(), YADAK_CHILD_VERSION, true );
	},
	20
);

add_action(
	'wp_head',
	static function () {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_stylesheet_directory_uri() . '/assets/fonts/Vazirmatn-wght.woff2' )
		);
		echo '<meta name="theme-color" content="#0f172a">' . "\n";
	},
	1
);

add_action(
	'after_setup_theme',
	static function () {
		load_child_theme_textdomain( 'yadak-child', get_stylesheet_directory() . '/languages' );
	},
	20
);

/**
 * Customizer: store contact details, trust seal and homepage texts.
 */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_section(
			'yadak_store',
			array(
				'title'       => __( 'اطلاعات فروشگاه', 'yadak-child' ),
				'description' => __( 'نام برند همان «عنوان سایت» و لوگو همان «نشان سایت» در بخش «هویت سایت» است.', 'yadak-child' ),
				'priority'    => 30,
			)
		);
		$fields = array(
			'phone'         => array( __( 'تلفن پشتیبانی', 'yadak-child' ), 'text' ),
			'hours'         => array( __( 'ساعات پاسخگویی', 'yadak-child' ), 'text' ),
			'address'       => array( __( 'آدرس', 'yadak-child' ), 'textarea' ),
			'hero_title'    => array( __( 'تیتر صفحه اصلی', 'yadak-child' ), 'text' ),
			'hero_subtitle' => array( __( 'زیرتیتر صفحه اصلی', 'yadak-child' ), 'textarea' ),
			'b2b_url'       => array( __( 'لینک صفحه همکاری (B2B)', 'yadak-child' ), 'url' ),
			'trust_seal'    => array( __( 'کد نماد اعتماد (اینماد)', 'yadak-child' ), 'textarea' ),
		);
		foreach ( $fields as $key => $def ) {
			$wp_customize->add_setting(
				'yadak_' . $key,
				array(
					'default'           => yadak_opt( $key ),
					'sanitize_callback' => 'trust_seal' === $key ? 'yadak_sanitize_seal' : ( 'url' === $def[1] ? 'esc_url_raw' : 'sanitize_textarea_field' ),
				)
			);
			$wp_customize->add_control(
				'yadak_' . $key,
				array(
					'label'   => $def[0],
					'section' => 'yadak_store',
					'type'    => $def[1],
				)
			);
		}
	}
);

/**
 * Trust seal snippets are a link wrapping an image.
 *
 * @param string $html HTML.
 * @return string
 */
function yadak_sanitize_seal( $html ) {
	return wp_kses(
		$html,
		array(
			'a'   => array( 'href' => true, 'target' => true, 'referrerpolicy' => true, 'rel' => true ),
			'img' => array( 'src' => true, 'alt' => true, 'style' => true, 'id' => true, 'code' => true, 'referrerpolicy' => true, 'width' => true, 'height' => true ),
		)
	);
}

/**
 * Keep the header cart count fresh after AJAX add-to-cart.
 */
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		$fragments['span.yadak-cart-count'] = yadak_cart_count_html();
		return $fragments;
	}
);

function yadak_cart_count_html() {
	$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	return '<span class="yadak-cart-count" data-count="' . esc_attr( $count ) . '">' . esc_html( number_format_i18n( $count ) ) . '</span>';
}

/**
 * Inline SVG icons (stroke icons, currentColor).
 *
 * @param string $name Icon.
 * @return string
 */
function yadak_icon( $name ) {
	$paths = array(
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'cart'    => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
		'car'     => '<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/><circle cx="7.5" cy="13" r=".8"/><circle cx="16.5" cy="13" r=".8"/>',
		'shield'  => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		'truck'   => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
		'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		'return'  => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
		'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/>',
		'arrow'   => '<path d="M15 6l-6 6 6 6"/>',
		'home'    => '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/>',
		'grid'    => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="yadak-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
}

/**
 * Product search form used in the header and hero.
 *
 * @param string $id Unique input id.
 */
function yadak_search_form( $id = 'yadak-s' ) {
	?>
	<form role="search" method="get" class="yadak-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'جستجوی قطعه', 'yadak-child' ); ?></label>
		<input type="search" id="<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'نام قطعه، شماره فنی، OEM یا خودرو…', 'yadak-child' ); ?>" autocomplete="off" />
		<input type="hidden" name="post_type" value="product" />
		<button type="submit" aria-label="<?php esc_attr_e( 'جستجو', 'yadak-child' ); ?>"><?php echo yadak_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	</form>
	<?php
}

/**
 * Tidy WooCommerce defaults for this layout.
 */
add_action(
	'init',
	static function () {
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	}
);
add_filter(
	'loop_shop_columns',
	static function () {
		return 4;
	}
);
add_filter(
	'loop_shop_per_page',
	static function () {
		return 24;
	}
);
