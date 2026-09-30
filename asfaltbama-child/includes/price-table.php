<?php
/**
 * Daily price tables.
 *
 * The owner enters current prices per group under Settings → قیمت روز;
 * [abm_price_table group="…"] shows them with the date they were saved.
 * Empty rows are hidden, and a group with no prices shows a "call for
 * today's price" box instead of invented numbers.
 *
 * Saving a group also:
 * - marks the articles that show it as modified (real dateModified), and
 * - adds the save date to their search title («… | به‌روز ۸ مهر ۱۴۰۵»),
 * so the freshness signal in search results is always a true one.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Price groups: key => [title, service name for the empty box, rows].
 * Rows: key => [label, unit].
 *
 * @return array<string,array>
 */
function asfaltbama_price_groups() {
	return apply_filters(
		'asfaltbama_price_groups',
		[
			'asphalt'    => [
				'قیمت روز آسفالت',
				'هر تن آسفالت توپکا و بیندر',
				[
					'topeka_applied'  => [ 'آسفالت گرم توپکا (رویه) — با اجرا', 'تن' ],
					'binder_applied'  => [ 'آسفالت گرم بیندر (زیرین) — با اجرا', 'تن' ],
					'topeka_material' => [ 'آسفالت گرم توپکا — بدون اجرا، تحویل در محل', 'تن' ],
					'binder_material' => [ 'آسفالت گرم بیندر — بدون اجرا، تحویل در محل', 'تن' ],
					'cold_mix'        => [ 'آسفالت سرد فله', 'تن' ],
					'cold_bag'        => [ 'آسفالت سرد کیسه‌ای', 'کیسه' ],
					'm2_012_5'        => [ 'آسفالت ۰-۱۲ (ریزدانه) ۵ سانت کوبیده — با اجرا', 'متر مربع' ],
					'm2_019_5'        => [ 'آسفالت ۰-۱۹ ۵ سانت کوبیده — با اجرا', 'متر مربع' ],
					'subgrade_m2'     => [ 'زیرسازی با مصالح اساس و کوبیدن', 'متر مربع' ],
				],
			],
			'excavation' => [
				'قیمت روز خاکبرداری',
				'خاکبرداری و گودبرداری',
				[
					'exc_machine' => [ 'خاکبرداری با بیل مکانیکی و بارگیری', 'متر مکعب' ],
					'exc_haul'    => [ 'حمل خاک تا محل تخلیه‌ی مجاز', 'متر مکعب' ],
					'exc_manual'  => [ 'خاکبرداری دستی', 'متر مکعب' ],
					'grading'     => [ 'تسطیح و رگلاژ بستر', 'متر مربع' ],
					'excavator_h' => [ 'بیل مکانیکی با اپراتور', 'ساعت' ],
					'bobcat_day'  => [ 'بابکت با اپراتور', 'روز' ],
				],
			],
			'demolition' => [
				'قیمت روز تخریب',
				'تخریب ساختمان',
				[
					'demo_brick'    => [ 'تخریب ساختمان آجری (کلنگی)', 'متر مربع زیربنا' ],
					'demo_concrete' => [ 'تخریب ساختمان اسکلت بتنی', 'متر مربع زیربنا' ],
					'demo_steel'    => [ 'تخریب ساختمان اسکلت فلزی', 'متر مربع زیربنا' ],
					'demo_breaker'  => [ 'تخریب بتن با پیکور', 'متر مکعب' ],
					'demo_wall'     => [ 'تخریب دیوار آجری', 'متر مربع' ],
					'demo_tile'     => [ 'تخریب کاشی و سرامیک', 'متر مربع' ],
					'debris_haul'   => [ 'بارگیری و حمل نخاله', 'سرویس کامیون' ],
				],
			],
			'scrap'      => [
				'قیمت روز خرید ضایعات آهن',
				'خرید ضایعات آهن',
				[
					'scrap_heavy' => [ 'ضایعات آهن سنگین (تیرآهن، ستون، ورق ضخیم)', 'کیلوگرم' ],
					'scrap_rebar' => [ 'میلگرد و آرماتور ضایعاتی', 'کیلوگرم' ],
					'scrap_light' => [ 'ضایعات آهن سبک (پروفیل، ورق نازک)', 'کیلوگرم' ],
					'scrap_cast'  => [ 'چدن', 'کیلوگرم' ],
				],
			],
			'sealing'    => [
				'قیمت روز درزگیری',
				'درزگیری و لکه‌گیری آسفالت',
				[
					'seal_hot'   => [ 'درزگیری با ماستیک گرم (با اجرا)', 'متر طول' ],
					'seal_route' => [ 'شیارزنی و درزگیری ترک‌های عریض', 'متر طول' ],
					'patching'   => [ 'لکه‌گیری آسفالت (برش، تخریب و آسفالت گرم)', 'متر مربع' ],
					'mastic_kg'  => [ 'فروش ماستیک درزگیری (بدون اجرا)', 'کیلوگرم' ],
				],
			],
			'bitumen'    => [
				'قیمت روز قیر',
				'قیر',
				[
					'bit_6070_drum' => [ 'قیر ۶۰/۷۰ بشکه‌ای', 'بشکه' ],
					'bit_6070_bulk' => [ 'قیر ۶۰/۷۰ فله', 'تن' ],
					'bit_mc'        => [ 'قیر محلول MC-250 (پریمکت)', 'بشکه' ],
					'bit_rc'        => [ 'قیر محلول RC-250 (تک‌کت)', 'بشکه' ],
					'bit_emulsion'  => [ 'قیر امولسیون', 'بشکه' ],
					'bit_blown'     => [ 'قیر دمیده ۸۵/۲۵ (قیرگونی و ایزوگام)', 'کیلوگرم' ],
				],
			],
			'isogam'     => [
				'قیمت روز ایزوگام و قیرگونی',
				'ایزوگام و قیرگونی',
				[
					'isogam_install'  => [ 'اجرای ایزوگام با مصالح (یک لایه)', 'متر مربع' ],
					'isogam_labor'    => [ 'اجرای ایزوگام بدون مصالح (دستمزد)', 'متر مربع' ],
					'isogam_roll'     => [ 'رول ایزوگام (خرید بدون نصب)', 'رول' ],
					'bitumen_roofing' => [ 'قیرگونی دو لایه با مصالح', 'متر مربع' ],
					'isogam_remove'   => [ 'جمع‌آوری ایزوگام قدیمی', 'متر مربع' ],
				],
			],
		]
	);
}

/**
 * Stored prices of a group.
 *
 * @param string $group Group key.
 * @return array{prices:array<string,string>,note:string,updated:int}
 */
function asfaltbama_price_data( $group = 'asphalt' ) {
	$all  = get_option( 'asfaltbama_prices', [] );
	$data = isset( $all[ $group ] ) ? $all[ $group ] : [];
	// 1.30 stored the asphalt group on its own.
	if ( ! $data && 'asphalt' === $group ) {
		$data = get_option( 'asfaltbama_asphalt_prices', [] );
	}
	return [
		'prices'  => isset( $data['prices'] ) && is_array( $data['prices'] ) ? $data['prices'] : [],
		'note'    => isset( $data['note'] ) ? (string) $data['note'] : '',
		'updated' => isset( $data['updated'] ) ? (int) $data['updated'] : 0,
	];
}

/**
 * Persian date of a timestamp, e.g. «۸ مهر ۱۴۰۵».
 *
 * @param int $ts Timestamp.
 * @return string
 */
function asfaltbama_price_date( $ts ) {
	return asfaltbama_date( wp_date( 'Y-m-d H:i:s', $ts ) );
}

/**
 * Register the settings page.
 *
 * @return void
 */
function asfaltbama_price_menu() {
	add_options_page( 'قیمت روز', 'قیمت روز', 'manage_options', 'asfaltbama-prices', 'asfaltbama_price_page' );
}
add_action( 'admin_menu', 'asfaltbama_price_menu' );

/**
 * Published posts that show a group's table.
 *
 * @param string $group Group key.
 * @return int[]
 */
function asfaltbama_price_posts( $group ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '[abm_price_table' ) . '%';
	$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out  = [];
	foreach ( $ids as $id ) {
		if ( asfaltbama_price_group_of( get_post_field( 'post_content', $id ) ) === $group ) {
			$out[] = (int) $id;
		}
	}
	return $out;
}

/**
 * The group of the first price table in some content.
 *
 * @param string $content Post content.
 * @return string Group key, or '' when there is no table.
 */
function asfaltbama_price_group_of( $content ) {
	if ( ! preg_match( '/\[abm_price_table([^\]]*)\]/', (string) $content, $m ) ) {
		return '';
	}
	return preg_match( '/group=["\']?([a-z_]+)/', $m[1], $g ) ? $g[1] : 'asphalt';
}

/**
 * Render and save the settings page.
 *
 * @return void
 */
function asfaltbama_price_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$groups = asfaltbama_price_groups();
	$saved  = '';

	if ( isset( $_POST['abm_prices_nonce'], $_POST['abm_group'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['abm_prices_nonce'] ) ), 'abm_prices' ) ) {
		$group = sanitize_key( wp_unslash( $_POST['abm_group'] ) );
		if ( isset( $groups[ $group ] ) ) {
			$digits = array_combine( preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ), str_split( '01234567890123456789' ) );
			$prices = [];
			foreach ( array_keys( $groups[ $group ][2] ) as $key ) {
				$raw = isset( $_POST['abm_price'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['abm_price'][ $key ] ) ) : '';
				$num = preg_replace( '/\D/', '', strtr( $raw, $digits ) );
				if ( '' !== $num ) {
					$prices[ $key ] = $num;
				}
			}
			$all           = (array) get_option( 'asfaltbama_prices', [] );
			$all[ $group ] = [
				'prices'  => $prices,
				'note'    => isset( $_POST['abm_price_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['abm_price_note'] ) ) : '',
				'updated' => time(),
			];
			update_option( 'asfaltbama_prices', $all );

			// The articles really changed: record it as their modified date.
			foreach ( asfaltbama_price_posts( $group ) as $id ) {
				// Direct update: wp_update_post would run every save hook for a date change.
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, [ 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $id );
			}
			$saved = $groups[ $group ][0];
		}
	}

	echo '<div class="wrap" dir="rtl" style="text-align:right"><h1>قیمت روز</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $saved ) . ' ذخیره شد؛ جدول و تاریخ به‌روزرسانی در مقاله‌های مربوط و عنوان گوگل آن‌ها اعمال شد.</p></div>';
	}
	echo '<p>قیمت‌ها را به <strong>تومان</strong> وارد کنید. ردیف خالی در سایت نمایش داده نمی‌شود. هر بخش جداگانه ذخیره می‌شود و تاریخ ذخیره، «تاریخ به‌روزرسانی» همان بخش در سایت و عنوان گوگل است؛ پس فقط وقتی ذخیره کنید که قیمت‌ها را واقعاً بررسی کرده‌اید.</p>';

	foreach ( $groups as $group => $def ) {
		$data = asfaltbama_price_data( $group );
		echo '<h2 style="margin-top:2em">' . esc_html( $def[0] ) . '</h2>';
		if ( $data['updated'] ) {
			echo '<p>آخرین به‌روزرسانی: ' . esc_html( asfaltbama_price_date( $data['updated'] ) ) . '</p>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'abm_prices', 'abm_prices_nonce' );
		echo '<input type="hidden" name="abm_group" value="' . esc_attr( $group ) . '"><table class="form-table" role="presentation">';
		foreach ( $def[2] as $key => $row ) {
			$val = isset( $data['prices'][ $key ] ) ? number_format( (float) $data['prices'][ $key ] ) : '';
			printf(
				'<tr><th scope="row"><label for="abm-%1$s">%2$s</label></th><td><input type="text" inputmode="numeric" id="abm-%1$s" name="abm_price[%1$s]" value="%3$s" class="regular-text" dir="ltr"> تومان / %4$s</td></tr>',
				esc_attr( $key ),
				esc_html( $row[0] ),
				esc_attr( $val ),
				esc_html( $row[1] )
			);
		}
		printf(
			'<tr><th scope="row"><label for="abm-note-%1$s">توضیح زیر جدول</label></th><td><textarea id="abm-note-%1$s" name="abm_price_note" rows="2" class="large-text">%2$s</textarea></td></tr>',
			esc_attr( $group ),
			esc_textarea( $data['note'] )
		);
		echo '</table>';
		submit_button( 'ذخیره‌ی ' . $def[0], 'primary', 'submit-' . $group );
		echo '</form>';
	}
	echo '</div>';
}

/**
 * Format a number with Persian digits and separators.
 *
 * @param string $num Digits.
 * @return string
 */
function asfaltbama_fa_number( $num ) {
	return strtr( number_format( (float) $num ), [ ',' => '٬', '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * [abm_price_table group="…"] shortcode.
 *
 * @param array $atts Attributes.
 * @return string
 */
function asfaltbama_price_table_shortcode( $atts ) {
	$atts   = shortcode_atts( [ 'group' => 'asphalt' ], $atts );
	$groups = asfaltbama_price_groups();
	$group  = isset( $groups[ $atts['group'] ] ) ? $atts['group'] : 'asphalt';
	$def    = $groups[ $group ];
	$data   = asfaltbama_price_data( $group );
	$tel    = '<a href="tel:09191299559">۰۹۱۹ ۱۲۹ ۹۵۵۹</a>';

	$rows = '';
	foreach ( $def[2] as $key => $row ) {
		if ( ! empty( $data['prices'][ $key ] ) ) {
			$rows .= '<tr><td>' . esc_html( $row[0] ) . '</td><td><strong>' . esc_html( asfaltbama_fa_number( $data['prices'][ $key ] ) ) . '</strong> تومان</td><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
	}

	if ( '' === $rows ) {
		return '<div class="abm-callout abm-callout--info"><p class="abm-callout__title">' . esc_html( $def[0] ) . '</p><p>' . esc_html( 'قیمت ' . $def[1] ) . ' با شرایط بازار و محل پروژه تغییر می‌کند. برای قیمت امروز با ' . $tel . ' تماس بگیرید یا از <a href="https://asfaltbama.com/contact-us/">فرم استعلام قیمت</a> درخواست بدهید.</p></div>';
	}

	$out  = '<div class="abm-price-table">';
	$out .= '<table><thead><tr><th>شرح</th><th>قیمت</th><th>واحد</th></tr></thead><tbody>' . $rows . '</tbody></table>';
	if ( $data['updated'] ) {
		$out .= '<p class="abm-price-table__date">آخرین به‌روزرسانی: ' . esc_html( asfaltbama_price_date( $data['updated'] ) ) . '</p>';
	}
	if ( '' !== $data['note'] ) {
		$out .= '<p class="abm-price-table__note">' . esc_html( $data['note'] ) . '</p>';
	}
	$out .= '<p class="abm-price-table__cta">قیمت نهایی پس از بازدید و با توجه به حجم کار، دسترسی و شرایط محل مشخص می‌شود. استعلام: ' . $tel . '</p>';
	$out .= '</div>';
	return $out;
}
add_shortcode( 'abm_price_table', 'asfaltbama_price_table_shortcode' );

/**
 * Search title: add the real update date of the article's price table,
 * e.g. «قیمت خاکبرداری ۱۴۰۵ | … | به‌روز ۸ مهر».
 *
 * @param string $title Title from Rank Math.
 * @return string
 */
function asfaltbama_price_title_date( $title ) {
	if ( ! is_singular( 'post' ) ) {
		return $title;
	}
	$group = asfaltbama_price_group_of( get_post_field( 'post_content', get_queried_object_id() ) );
	if ( '' === $group ) {
		return $title;
	}
	$data = asfaltbama_price_data( $group );
	if ( ! $data['updated'] || ! $data['prices'] ) {
		return $title;
	}
	// «۸ مهر ۱۴۰۵» → «۸ مهر»: the year is usually in the title already.
	$date  = preg_replace( '/\s*[۰-۹]{4}$/u', '', asfaltbama_price_date( $data['updated'] ) );
	$title = preg_replace( '/\s*\|\s*آسفالت با ما\s*$/u', '', $title );
	return $title . ' | به‌روز ' . $date;
}
add_filter( 'rank_math/frontend/title', 'asfaltbama_price_title_date', 20 );
