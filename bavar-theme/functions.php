<?php
/**
 * BAVAR GROUP theme.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BAVAR_THEME_VERSION', '1.0.0' );

/**
 * Editable defaults (Appearance → Customize → BAVAR GROUP).
 *
 * @return array
 */
function bavar_theme_defaults() {
	$img = get_stylesheet_directory_uri() . '/assets/images/';
	return [
		'hero_image'       => $img . 'founder-portrait.webp',
		'founder_image'    => $img . 'founder-office.webp',
		'founder_image_2'  => $img . 'founder-city.webp',
		'about_text'       => "گروه توسعه کسب‌وکار باور، به مؤسسی و مدیریت آرش الطافیان، با هدف رشد و توسعه‌ی کسب‌وکارهای ایران بنیان‌گذاری شد و به‌عنوان مجموعه‌ای برای ارتباطات، شبکه‌سازی، افزایش فروش و توسعه‌ی سریع کسب‌وکار فعالیت می‌کند.\n\nاین مجموعه دارای دوره‌های آموزشی حضوری، از جمله «آشیانه سیمرغ‌ها»، و دوره‌های غیرحضوری، از جمله «کتاب مقدس گروه باور»، و همچنین مجموعه‌ای گسترده از کتاب‌ها، آموزش‌ها، چک‌لیست‌ها و تمرین‌های کاربردی است.",
		'philosophy_title' => 'رشد کسب‌وکار از رشد انسان آغاز می‌شود.',
		'philosophy_text'  => 'در BAVAR باور داریم که فروش، شبکه و ثروت نتیجه‌ی انسانی است که آگاهانه یاد می‌گیرد، درست تصمیم می‌گیرد و پیوسته عمل می‌کند. هر دوره، هر کتاب و هر تمرین برای ساختن همین انسان طراحی شده است.',
		'founder_bio'      => 'مؤسس و مدیر گروه توسعه کسب‌وکار باور. هدف او رشد و توسعه‌ی کسب‌وکارهای ایران از مسیر آموزش، ارتباطات، شبکه‌سازی و افزایش فروش است.',
		'phone'            => '',
		'email'            => '',
		'instagram'        => '',
		'telegram'         => '',
		'terms_url'        => '',
		'privacy_url'      => '',
	];
}

/**
 * Read a theme option.
 *
 * @param string $key Key.
 * @return string
 */
function bavar_opt( $key ) {
	$d = bavar_theme_defaults();
	$v = get_theme_mod( 'bavar_' . $key, $d[ $key ] ?? '' );
	return '' === $v && isset( $d[ $key ] ) && false !== strpos( $key, 'image' ) ? $d[ $key ] : $v;
}

/**
 * URL of a BAVAR Core path target, falling back to a home-page anchor.
 *
 * @param string $key      Path key.
 * @param string $fallback Fallback URL.
 * @return string
 */
function bavar_link( $key, $fallback = '' ) {
	if ( class_exists( 'Bavar_Settings' ) ) {
		return Bavar_Settings::path_target( $key );
	}
	return $fallback ?: home_url( '/' );
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		register_nav_menus( [ 'bavar-primary' => 'منوی اصلی BAVAR' ] );
	},
	20
);

// Hello Elementor's own header/footer styles are not used by this theme.
add_filter( 'hello_elementor_header_footer', '__return_false' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );
// The parent reset colours every button and link pink (#c36); theme.css carries its own base styles.
add_filter( 'hello_elementor_enqueue_style', '__return_false' );

add_action(
	'wp_enqueue_scripts',
	function () {
		$uri = get_stylesheet_directory_uri();
		wp_enqueue_style( 'bavar-theme', $uri . '/assets/css/theme.css', [], BAVAR_THEME_VERSION );
		wp_enqueue_script( 'bavar-theme', $uri . '/assets/js/theme.js', [], BAVAR_THEME_VERSION, true );
	},
	20
);

add_action(
	'wp_head',
	function () {
		$uri = get_stylesheet_directory_uri() . '/assets/fonts/';
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $uri . 'cormorant-garamond-500.woff2' ) );
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $uri . 'vazirmatn-arabic.woff2' ) );
		echo '<meta name="theme-color" content="#000000">' . "\n";
	},
	1
);

add_action(
	'customize_register',
	function ( $c ) {
		$c->add_section(
			'bavar_theme',
			[
				'title'    => 'BAVAR GROUP',
				'priority' => 25,
			]
		);
		$defaults = bavar_theme_defaults();
		$fields   = [
			'hero_image'       => [ 'تصویر اصلی (زیر لوگو)', 'image' ],
			'about_text'       => [ 'متن معرفی برند', 'textarea' ],
			'philosophy_title' => [ 'BAVAR PHILOSOPHY — جمله‌ی اصلی', 'text' ],
			'philosophy_text'  => [ 'BAVAR PHILOSOPHY — توضیح', 'textarea' ],
			'founder_image'    => [ 'تصویر بخش مؤسس', 'image' ],
			'founder_image_2'  => [ 'تصویر دوم بخش مؤسس', 'image' ],
			'founder_bio'      => [ 'معرفی کوتاه مؤسس', 'textarea' ],
			'phone'            => [ 'تلفن', 'text' ],
			'email'            => [ 'ایمیل', 'text' ],
			'instagram'        => [ 'لینک اینستاگرام', 'url' ],
			'telegram'         => [ 'لینک تلگرام', 'url' ],
			'terms_url'        => [ 'لینک قوانین (Terms)', 'url' ],
			'privacy_url'      => [ 'لینک حریم خصوصی (Privacy)', 'url' ],
		];
		foreach ( $fields as $key => $f ) {
			$sanitize = [
				'image'    => 'esc_url_raw',
				'url'      => 'esc_url_raw',
				'textarea' => 'sanitize_textarea_field',
				'text'     => 'sanitize_text_field',
			][ $f[1] ];
			$c->add_setting(
				'bavar_' . $key,
				[
					'default'           => $defaults[ $key ],
					'sanitize_callback' => $sanitize,
				]
			);
			if ( 'image' === $f[1] ) {
				$c->add_control(
					new WP_Customize_Image_Control(
						$c,
						'bavar_' . $key,
						[
							'label'   => $f[0],
							'section' => 'bavar_theme',
						]
					)
				);
			} else {
				$c->add_control(
					'bavar_' . $key,
					[
						'label'   => $f[0],
						'section' => 'bavar_theme',
						'type'    => $f[1],
					]
				);
			}
		}
	}
);

/**
 * Default menu when none is assigned.
 */
function bavar_default_menu() {
	$library = class_exists( 'Bavar_Settings' ) ? (int) Bavar_Settings::get( 'page_library' ) : 0;
	$items   = [
		'HOME'        => home_url( '/' ),
		'BAVAR GROUP' => home_url( '/#about' ),
		'COURSES'     => home_url( '/#paths' ),
		'LIBRARY'     => $library ? get_permalink( $library ) : home_url( '/#library' ),
		'ABOUT'       => home_url( '/#founder' ),
		'CONTACT'     => '#contact',
	];
	echo '<ul class="bv-menu">';
	foreach ( $items as $label => $url ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * The BAVAR wordmark.
 *
 * @param string $tag   Wrapper tag for the brand name.
 * @param string $class Extra class.
 */
function bavar_wordmark( $tag = 'p', $class = '' ) {
	$tag = in_array( $tag, [ 'h1', 'p', 'span' ], true ) ? $tag : 'p';
	printf(
		'<div class="bv-wordmark %1$s" dir="ltr"><%2$s class="bv-wordmark__name">BAVAR GROUP</%2$s><span class="bv-wordmark__founder">Founder Arash Altafian</span></div>',
		esc_attr( $class ),
		$tag // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above.
	);
}
