<?php
/**
 * Conversion UX for the product page, cart and checkout (mobile first):
 * delivery promise, trust points, ask-an-expert, sticky buy / checkout bars,
 * purchase steps and an order summary on checkout.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customizer: same-day cut-off hour and WhatsApp number.
 */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_setting( 'yadak_ship_cutoff', array( 'default' => '14', 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control(
			'yadak_ship_cutoff',
			array(
				'label'       => __( 'ساعت پایان ارسال همان‌روز', 'yadak-child' ),
				'description' => __( 'سفارش کالای موجود تا این ساعت، «ارسال امروز» نمایش داده می‌شود. ۰ = نمایش داده نشود.', 'yadak-child' ),
				'section'     => 'yadak_store',
				'type'        => 'number',
			)
		);
		$wp_customize->add_setting( 'yadak_whatsapp', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'yadak_whatsapp',
			array(
				'label'       => __( 'شماره واتس‌اپ کارشناس (اختیاری)', 'yadak-child' ),
				'description' => __( 'مثال: 09121234567', 'yadak-child' ),
				'section'     => 'yadak_store',
				'type'        => 'text',
			)
		);
	},
	30
);

/**
 * "ارسال امروز" / "ارسال فردا" / "ارسال شنبه" for an in-stock product.
 *
 * @return string
 */
function yadak_delivery_promise() {
	$cutoff = (int) get_theme_mod( 'yadak_ship_cutoff', 14 );
	if ( $cutoff <= 0 ) {
		return '';
	}
	$now  = new DateTime( 'now', wp_timezone() );
	$hour = (int) $now->format( 'G' );
	$dow  = (int) $now->format( 'w' ); // 5 = Friday.
	if ( 5 !== $dow && $hour < $cutoff ) {
		/* translators: %d: hour */
		return sprintf( __( 'تا ساعت %d سفارش دهید، امروز ارسال می‌شود', 'yadak-child' ), $cutoff );
	}
	// After cut-off: next working day (Friday is off).
	$next = ( 4 === $dow && $hour >= $cutoff ) || 5 === $dow ? __( 'شنبه', 'yadak-child' ) : __( 'فردا', 'yadak-child' );
	/* translators: %s: day */
	return sprintf( __( 'سفارش امروز، %s ارسال می‌شود', 'yadak-child' ), $next );
}

/**
 * Pages whose actions live in a sticky bar instead of the bottom nav.
 */
function yadak_has_action_bar() {
	return function_exists( 'is_product' ) && ( is_product() || is_cart() || ( is_checkout() && ! is_order_received_page() ) );
}

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( yadak_has_action_bar() ) {
			$classes[] = 'yadak-actionbar-page';
		}
		return $classes;
	}
);

/* ---------- Product page ---------- */

add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		if ( ! $product ) {
			return;
		}
		echo '<div class="yadak-buybox-info">';
		if ( $product->is_in_stock() ) {
			$promise = yadak_delivery_promise();
			if ( $promise ) {
				echo '<p class="yadak-ship">' . yadak_icon( 'truck' ) . '<span>' . esc_html( $promise ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} else {
			echo '<p class="yadak-ship is-out">' . esc_html__( 'فعلاً ناموجود — برای زمان تأمین با کارشناس تماس بگیرید.', 'yadak-child' ) . '</p>';
		}
		echo '</div>';
	},
	29
);

add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		if ( ! $product ) {
			return;
		}
		$pages = array(
			'shield'  => array( __( 'ضمانت اصالت', 'yadak-child' ), 'returns' ),
			'receipt' => array( __( 'فاکتور رسمی', 'yadak-child' ), '' ),
			'return'  => array( __( 'امکان مرجوعی', 'yadak-child' ), 'returns' ),
		);
		echo '<ul class="yadak-trustlist">';
		foreach ( $pages as $icon => $item ) {
			$page = $item[1] ? get_page_by_path( $item[1] ) : null;
			$text = yadak_icon( $icon ) . '<span>' . esc_html( $item[0] ) . '</span>';
			echo '<li>' . ( $page && 'publish' === $page->post_status ? '<a href="' . esc_url( get_permalink( $page ) ) . '">' . $text . '</a>' : $text ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul>';

		// Ask an expert, with the product and part number prefilled.
		$pn      = $product->get_meta( '_yadak_part_number' );
		$message = sprintf(
			/* translators: 1: product, 2: part number, 3: url */
			__( 'سلام، درباره این قطعه سؤال دارم: %1$s %2$s %3$s', 'yadak-child' ),
			$product->get_name(),
			$pn ? '(' . $pn . ')' : '',
			get_permalink( $product->get_id() )
		);
		$phone = preg_replace( '/[^0-9+]/', '', yadak_opt( 'phone' ) );
		$wa    = preg_replace( '/\D/', '', (string) get_theme_mod( 'yadak_whatsapp', '' ) );
		if ( $wa && 0 === strpos( $wa, '0' ) ) {
			$wa = '98' . substr( $wa, 1 );
		}
		echo '<div class="yadak-expert"><p>' . esc_html__( 'مطمئن نیستید این قطعه به خودروی شما می‌خورد؟', 'yadak-child' ) . '</p><div>';
		if ( $wa ) {
			echo '<a class="yadak-expert__btn" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $message ) ) . '">' . esc_html__( 'پرسش در واتس‌اپ', 'yadak-child' ) . '</a>';
		}
		if ( $phone ) {
			echo '<a class="yadak-expert__btn" href="tel:' . esc_attr( $phone ) . '">' . yadak_icon( 'phone' ) . esc_html__( 'تماس با کارشناس', 'yadak-child' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div>';
	},
	36
);

/* ---------- Purchase steps (cart & checkout) ---------- */

function yadak_checkout_steps( $current ) {
	$steps = array(
		'cart'     => __( 'سبد خرید', 'yadak-child' ),
		'checkout' => __( 'اطلاعات ارسال و پرداخت', 'yadak-child' ),
		'done'     => __( 'ثبت سفارش', 'yadak-child' ),
	);
	$index = array_search( $current, array_keys( $steps ), true );
	echo '<ol class="yadak-steps" aria-label="' . esc_attr__( 'مراحل خرید', 'yadak-child' ) . '">';
	$i = 0;
	foreach ( $steps as $key => $label ) {
		$state = $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : '' );
		echo '<li class="' . esc_attr( $state ) . '"' . ( 'is-current' === $state ? ' aria-current="step"' : '' ) . '><span>' . esc_html( number_format_i18n( $i + 1 ) ) . '</span>' . esc_html( $label ) . '</li>';
		++$i;
	}
	echo '</ol>';
}

add_action( 'woocommerce_before_cart', static function () { yadak_checkout_steps( 'cart' ); }, 1 );
add_action( 'woocommerce_before_checkout_form', static function () { yadak_checkout_steps( 'checkout' ); }, 1 );
add_action(
	'woocommerce_before_thankyou',
	static function () {
		yadak_checkout_steps( 'done' );
	},
	1
);

/**
 * Checkout: compact order summary at the top (mobile users otherwise see it
 * only after the whole form).
 */
add_action(
	'woocommerce_before_checkout_form',
	static function () {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$count = WC()->cart->get_cart_contents_count();
		echo '<details class="yadak-mini-summary"><summary><span>';
		/* translators: %s: item count */
		echo esc_html( sprintf( __( 'خلاصه سفارش (%s کالا)', 'yadak-child' ), number_format_i18n( $count ) ) );
		echo '</span><strong class="yadak-mini-summary__total">' . wp_kses_post( WC()->cart->get_total() ) . '</strong></summary><ul>';
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'];
			echo '<li><span>' . esc_html( $product->get_name() ) . ' × ' . esc_html( number_format_i18n( $item['quantity'] ) ) . '</span><span>' . wp_kses_post( WC()->cart->get_product_subtotal( $product, $item['quantity'] ) ) . '</span></li>';
		}
		echo '</ul><a href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'ویرایش سبد', 'yadak-child' ) . '</a></details>';
	},
	5
);

/* ---------- Sticky action bars (mobile) ---------- */

add_action(
	'wp_footer',
	static function () {
		if ( ! yadak_has_action_bar() ) {
			return;
		}
		if ( is_product() ) {
			global $product;
			$product = $product ? $product : wc_get_product( get_queried_object_id() );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return;
			}
			echo '<div class="yadak-actionbar" data-mode="product" hidden><div class="yadak-actionbar__info"><span class="yadak-actionbar__title">' . esc_html( $product->get_name() ) . '</span><span class="yadak-actionbar__price">' . wp_kses_post( $product->get_price_html() ) . '</span></div><button type="button" class="yadak-actionbar__btn">' . esc_html( $product->single_add_to_cart_text() ) . '</button></div>';
		} elseif ( is_cart() && WC()->cart && ! WC()->cart->is_empty() ) {
			echo '<div class="yadak-actionbar" data-mode="cart"><div class="yadak-actionbar__info"><span class="yadak-actionbar__title">' . esc_html__( 'جمع کل', 'yadak-child' ) . '</span><span class="yadak-actionbar__price">' . wp_kses_post( WC()->cart->get_total() ) . '</span></div><a class="yadak-actionbar__btn" href="' . esc_url( wc_get_checkout_url() ) . '">' . esc_html__( 'ادامه و پرداخت', 'yadak-child' ) . '</a></div>';
		} elseif ( is_checkout() ) {
			echo '<div class="yadak-actionbar" data-mode="checkout"><div class="yadak-actionbar__info"><span class="yadak-actionbar__title">' . esc_html__( 'مبلغ قابل پرداخت', 'yadak-child' ) . '</span><span class="yadak-actionbar__price">' . ( WC()->cart ? wp_kses_post( WC()->cart->get_total() ) : '' ) . '</span></div><button type="button" class="yadak-actionbar__btn">' . esc_html__( 'ثبت سفارش', 'yadak-child' ) . '</button></div>';
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( yadak_has_action_bar() ) {
			wp_enqueue_script( 'yadak-ux', get_stylesheet_directory_uri() . '/assets/js/ux.js', array( 'jquery' ), YADAK_CHILD_VERSION, true );
			wp_localize_script(
				'yadak-ux',
				'yadakUx',
				array(
					'copied' => __( 'کپی شد', 'yadak-child' ),
					'copy'   => __( 'کپی', 'yadak-child' ),
					'less'   => __( 'کم کردن', 'yadak-child' ),
					'more'   => __( 'زیاد کردن', 'yadak-child' ),
				)
			);
		}
	},
	30
);
