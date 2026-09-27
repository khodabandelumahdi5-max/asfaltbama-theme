<?php
/**
 * Quote / consultation requests.
 *
 * The contact page form (an Elementor HTML widget) only showed an alert
 * and threw the data away. The page output is rewritten so the form posts
 * to admin-post.php; each request is saved as an "abm_request" entry in
 * the dashboard (Requests menu) and emailed to the company address.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ASFALTBAMA_REQUEST_EMAIL', 'ofoghapadanapasargad@gmail.com' );

/**
 * Private post type holding the requests (visible to administrators only).
 *
 * @return void
 */
function asfaltbama_register_requests() {
	register_post_type(
		'abm_request',
		[
			'labels'              => [
				'name'          => 'درخواست‌های مشتری',
				'singular_name' => 'درخواست',
				'menu_name'     => 'درخواست‌ها',
				'all_items'     => 'همه‌ی درخواست‌ها',
				'edit_item'     => 'جزئیات درخواست',
				'not_found'     => 'هنوز درخواستی ثبت نشده است.',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 3,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => [ 'title', 'editor' ],
			'capability_type'     => 'post',
			'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
		]
	);
}
add_action( 'init', 'asfaltbama_register_requests' );

/**
 * Service choices; the contact form select is rebuilt from this list.
 *
 * @return string[]
 */
function asfaltbama_request_services() {
	return [
		'اجرای آسفالت و روکش مکانیزه / دستی',
		'نصب ایزوگام و قیرگونی',
		'خاکبرداری، گودبرداری و تسطیح اراضی',
		'درزگیری و ماستیک پلیمری گرم',
		'اجاره ماشین‌آلات راه‌سازی (غلتک، گریدر، فینیشر)',
		'تخریب سازه و خرید ضایعات آهن',
		'درخواست پیش‌فاکتور / فاکتور رسمی',
		'مشاوره‌ی فنی',
	];
}

/**
 * Make the contact page form real: give the fields names, point the form
 * at admin-post.php and add a honeypot and a timestamp against bots.
 *
 * @param string $html Page HTML.
 *
 * @return string
 */
function asfaltbama_wire_quote_form( $html ) {
	$start = strpos( $html, "<form onsubmit=\"event.preventDefault(); alert(" );
	if ( false === $start ) {
		return $html;
	}
	$end = strpos( $html, '</form>', $start );
	if ( false === $end ) {
		return $html;
	}
	$form = substr( $html, $start, $end - $start );

	$open  = '<form id="quote" class="abm-quote-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$open .= '<input type="hidden" name="action" value="asfaltbama_quote">';
	$open .= '<input type="hidden" name="abm_page" value="' . esc_attr( home_url( add_query_arg( [] ) ) ) . '">';
	$open .= '<input type="hidden" name="abm_t" value="' . esc_attr( time() ) . '">';
	$open .= '<div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Website<input type="text" name="abm_website" tabindex="-1" autocomplete="off"></label></div>';
	// Result message after the redirect back.
	$status = isset( $_GET['abm_sent'] ) ? sanitize_key( wp_unslash( $_GET['abm_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$notice = '';
	if ( '1' === $status ) {
		$notice = '<div class="abm-form-msg abm-form-msg--ok" role="status">درخواست شما ثبت شد. کارشناسان شرکت افق آپادانا پاسارگاد به‌زودی با شما تماس می‌گیرند. برای کار فوری: <a href="tel:' . esc_attr( ASFALTBAMA_PHONE ) . '">' . esc_html( ASFALTBAMA_PHONE_DISPLAY ) . '</a></div>';
	} elseif ( 'err' === $status ) {
		$notice = '<div class="abm-form-msg abm-form-msg--err" role="alert">لطفاً نام و شماره‌ی تماس را درست وارد کنید و دوباره بفرستید، یا مستقیم تماس بگیرید: <a href="tel:' . esc_attr( ASFALTBAMA_PHONE ) . '">' . esc_html( ASFALTBAMA_PHONE_DISPLAY ) . '</a></div>';
	}
	$open .= $notice;
	$form  = $open . substr( $form, strpos( $form, '>' ) + 1 );

	$form = preg_replace( '#<input type="text" class="ac-form-input"#', '<input type="text" name="abm_name" maxlength="120" class="ac-form-input"', $form, 1 );
	$form = preg_replace( '#<input type="tel" class="ac-form-input"#', '<input type="tel" name="abm_phone" maxlength="30" class="ac-form-input"', $form, 1 );
	$form = preg_replace( '#<textarea class="ac-form-textarea"#', '<textarea name="abm_details" maxlength="3000" class="ac-form-textarea"', $form, 1 );

	$options = '';
	foreach ( asfaltbama_request_services() as $service ) {
		$options .= '<option>' . esc_html( $service ) . '</option>';
	}
	$form = preg_replace( '#<select class="ac-form-select">.*?</select>#su', '<select name="abm_service" class="ac-form-select">' . $options . '</select>', $form, 1 );

	return substr_replace( $html, $form, $start, $end - $start );
}

/**
 * Handle a submitted request: validate, save, email, redirect back.
 *
 * @return void
 */
function asfaltbama_handle_quote() {
	// phpcs:disable WordPress.Security.NonceVerification -- public form on cached pages; honeypot, timing and rate limit instead.
	$back = isset( $_POST['abm_page'] ) ? esc_url_raw( wp_unslash( $_POST['abm_page'] ) ) : home_url( '/contact-us/' );
	$back = wp_validate_redirect( $back, home_url( '/contact-us/' ) );
	$back = remove_query_arg( 'abm_sent', $back );

	$name    = sanitize_text_field( wp_unslash( $_POST['abm_name'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['abm_phone'] ?? '' ) );
	$service = sanitize_text_field( wp_unslash( $_POST['abm_service'] ?? '' ) );
	$details = sanitize_textarea_field( wp_unslash( $_POST['abm_details'] ?? '' ) );
	$trap    = (string) wp_unslash( $_POST['abm_website'] ?? '' );
	$started = (int) ( $_POST['abm_t'] ?? 0 );
	// phpcs:enable

	// Bots: filled honeypot or submitted in under 3 seconds. Pretend success.
	if ( '' !== $trap || ( $started && time() - $started < 3 ) ) {
		wp_safe_redirect( add_query_arg( 'abm_sent', '1', $back ) . '#quote' );
		exit;
	}

	$digits = preg_replace( '/\D+/', '', strtr( $phone, array_combine( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], range( 0, 9 ) ) ) );
	if ( '' === $name || strlen( $digits ) < 8 ) {
		wp_safe_redirect( add_query_arg( 'abm_sent', 'err', $back ) . '#quote' );
		exit;
	}

	// At most 5 requests per hour from one address.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'abm_req_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		wp_safe_redirect( add_query_arg( 'abm_sent', '1', $back ) . '#quote' );
		exit;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	if ( ! in_array( $service, asfaltbama_request_services(), true ) ) {
		$service = 'نامشخص';
	}

	$body  = "نام / شرکت: {$name}\n";
	$body .= "تلفن: {$phone}\n";
	$body .= "خدمت: {$service}\n";
	$body .= "توضیحات:\n{$details}\n\n";
	$body .= 'صفحه: ' . $back . "\n";
	$body .= 'زمان: ' . wp_date( 'Y-m-d H:i' ) . "\n";

	$id = wp_insert_post(
		[
			'post_type'    => 'abm_request',
			'post_status'  => 'publish',
			'post_title'   => $service . ' – ' . $name . ' – ' . $phone,
			'post_content' => $body,
		]
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_abm_phone', $phone );
		update_post_meta( $id, '_abm_service', $service );
	}

	wp_mail(
		ASFALTBAMA_REQUEST_EMAIL,
		'درخواست جدید از سایت: ' . $service . ' – ' . $name,
		$body . "\nهمه‌ی درخواست‌ها: " . admin_url( 'edit.php?post_type=abm_request' ),
		[ 'Content-Type: text/plain; charset=UTF-8' ]
	);

	wp_safe_redirect( add_query_arg( 'abm_sent', '1', $back ) . '#quote' );
	exit;
}
add_action( 'admin_post_nopriv_asfaltbama_quote', 'asfaltbama_handle_quote' );
add_action( 'admin_post_asfaltbama_quote', 'asfaltbama_handle_quote' );

/**
 * Requests list: phone and service columns.
 *
 * @param string[] $columns Columns.
 *
 * @return string[]
 */
function asfaltbama_request_columns( $columns ) {
	return [
		'cb'          => $columns['cb'] ?? '',
		'title'       => 'درخواست',
		'abm_phone'   => 'تلفن',
		'abm_service' => 'خدمت',
		'date'        => 'تاریخ',
	];
}
add_filter( 'manage_abm_request_posts_columns', 'asfaltbama_request_columns' );

/**
 * Requests list column values.
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 *
 * @return void
 */
function asfaltbama_request_column_value( $column, $post_id ) {
	if ( 'abm_phone' === $column ) {
		$phone = get_post_meta( $post_id, '_abm_phone', true );
		echo '<a href="tel:' . esc_attr( $phone ) . '" dir="ltr">' . esc_html( $phone ) . '</a>';
	} elseif ( 'abm_service' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_abm_service', true ) );
	}
}
add_action( 'manage_abm_request_posts_custom_column', 'asfaltbama_request_column_value', 10, 2 );

/**
 * Dashboard notice with the number of requests in the last 7 days.
 *
 * @return void
 */
function asfaltbama_request_notice() {
	$screen = get_current_screen();
	if ( ! current_user_can( 'edit_posts' ) || ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}
	$recent = get_posts(
		[
			'post_type'   => 'abm_request',
			'numberposts' => -1,
			'fields'      => 'ids',
			'date_query'  => [ [ 'after' => '7 days ago' ] ],
		]
	);
	if ( $recent ) {
		printf(
			'<div class="notice notice-info" dir="rtl" style="text-align:right"><p><strong>%s</strong> درخواست مشتری در ۷ روز اخیر ثبت شده است. <a href="%s">مشاهده‌ی درخواست‌ها</a></p></div>',
			esc_html( asfaltbama_fa_digits( count( $recent ) ) ),
			esc_url( admin_url( 'edit.php?post_type=abm_request' ) )
		);
	}
}
add_action( 'admin_notices', 'asfaltbama_request_notice' );
