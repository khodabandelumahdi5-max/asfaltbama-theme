<?php
/**
 * Admin: BAVAR menu with leads (CRM view + CSV export) and settings.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Admin {

	const PER_PAGE = 50;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_post_bavar_export_leads', [ __CLASS__, 'export' ] );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'BAVAR', 'BAVAR', 'manage_woocommerce', 'bavar', [ __CLASS__, 'leads_page' ], 'dashicons-star-filled', 3 );
		add_submenu_page( 'bavar', 'لیدها', 'لیدها و درخواست‌ها', 'manage_woocommerce', 'bavar', [ __CLASS__, 'leads_page' ] );
		add_submenu_page( 'bavar', 'تنظیمات BAVAR', 'تنظیمات', 'manage_options', 'bavar-settings', [ __CLASS__, 'settings_page' ] );
	}

	/**
	 * Labels.
	 *
	 * @return array
	 */
	public static function labels() {
		$paths = [ '' => '—' ];
		foreach ( Bavar_Settings::get( 'paths' ) as $key => $p ) {
			$paths[ $key ] = $p['en'] . ' — ' . $p['subtitle'];
		}
		return [
			'path'   => $paths,
			'source' => [
				'gate'  => 'ورود به سایت',
				'path'  => 'فرم سه‌سؤالی',
				'order' => 'سفارش',
			],
			'status' => [
				'lead'    => 'لید',
				'pending' => 'در انتظار پرداخت',
				'paid'    => 'خریدار',
			],
		];
	}

	/**
	 * Current filters from the request.
	 *
	 * @return array
	 */
	private static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return [
			'path'   => sanitize_key( wp_unslash( $_GET['path'] ?? '' ) ),
			'source' => sanitize_key( wp_unslash( $_GET['source'] ?? '' ) ),
			'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			's'      => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
		];
		// phpcs:enable
	}

	/**
	 * WHERE clause for filters.
	 *
	 * @param array $f Filters.
	 * @return string
	 */
	private static function where( array $f ) {
		global $wpdb;
		$where = [ '1=1' ];
		foreach ( [ 'path', 'source' ] as $col ) {
			if ( $f[ $col ] ) {
				$where[] = $wpdb->prepare( "{$col} = %s", $f[ $col ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		if ( $f['status'] ) {
			$where[] = $wpdb->prepare( 'purchase_status = %s', $f['status'] );
		}
		if ( $f['s'] ) {
			$like    = '%' . $wpdb->esc_like( bavar_latin_digits( $f['s'] ) ) . '%';
			$where[] = $wpdb->prepare( '(full_name LIKE %s OR phone LIKE %s OR job LIKE %s)', $like, $like, $like );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Leads page.
	 */
	public static function leads_page() {
		global $wpdb;
		$f      = self::filters();
		$labels = self::labels();
		$table  = Bavar_Leads::table();
		$where  = self::where( $f );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $paged - 1 ) * self::PER_PAGE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ) );
		$stats = $wpdb->get_results( "SELECT purchase_status, COUNT(DISTINCT phone) n FROM {$table} GROUP BY purchase_status", OBJECT_K );
		// phpcs:enable

		$export = wp_nonce_url( add_query_arg( array_filter( $f ) + [ 'action' => 'bavar_export_leads' ], admin_url( 'admin-post.php' ) ), 'bavar_export' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">لیدها و درخواست‌ها</h1>
			<a class="page-title-action" href="<?php echo esc_url( $export ); ?>">خروجی Excel (CSV)</a>
			<p>
				<?php foreach ( $labels['status'] as $k => $l ) : ?>
					<span style="display:inline-block;margin-left:18px"><?php echo esc_html( $l ); ?>: <strong><?php echo esc_html( isset( $stats[ $k ] ) ? $stats[ $k ]->n : 0 ); ?></strong> نفر</span>
				<?php endforeach; ?>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="bavar">
				<p class="search-box" style="float:none;margin:10px 0">
					<?php foreach ( [ 'path', 'source', 'status' ] as $key ) : ?>
						<select name="<?php echo esc_attr( $key ); ?>">
							<option value=""><?php echo esc_html( [ 'path' => 'همه‌ی مسیرها', 'source' => 'همه‌ی منابع', 'status' => 'همه‌ی وضعیت‌ها' ][ $key ] ); ?></option>
							<?php foreach ( $labels[ $key ] as $k => $l ) : ?>
								<?php
								if ( '' === $k ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f[ $key ], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endforeach; ?>
					<input type="search" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="نام، موبایل یا شغل">
					<button class="button">فیلتر</button>
				</p>
			</form>
			<table class="widefat striped">
				<thead><tr><th>تاریخ</th><th>نام</th><th>موبایل</th><th>شغل</th><th>مسیر / محصول</th><th>منبع</th><th>وضعیت</th><th>پاسخ‌ها</th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="8">موردی ثبت نشده است.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $r->created_at ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); ?></td>
						<td><?php echo esc_html( $r->full_name ); ?></td>
						<td dir="ltr" style="text-align:right"><a href="tel:<?php echo esc_attr( $r->phone ); ?>"><?php echo esc_html( $r->phone ); ?></a></td>
						<td><?php echo esc_html( $r->job ); ?></td>
						<td>
							<?php echo esc_html( $labels['path'][ $r->path ] ?? $r->path ); ?>
							<?php if ( $r->product_id ) : ?>
								<br><small><?php echo esc_html( get_the_title( $r->product_id ) ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $labels['source'][ $r->source ] ?? $r->source ); ?></td>
						<td>
							<?php echo esc_html( $labels['status'][ $r->purchase_status ] ?? $r->purchase_status ); ?>
							<?php if ( $r->order_id ) : ?>
								<?php $o = function_exists( 'wc_get_order' ) ? wc_get_order( $r->order_id ) : null; ?>
								<?php if ( $o ) : ?>
									<br><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">سفارش #<?php echo esc_html( $o->get_order_number() ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$answers = json_decode( (string) $r->answers, true );
							if ( $answers ) {
								echo '<details><summary>مشاهده</summary>';
								foreach ( $answers as $qa ) {
									echo '<p><strong>' . esc_html( $qa['q'] ) . '</strong><br>' . nl2br( esc_html( $qa['a'] ) ) . '</p>';
								}
								echo '</details>';
							} else {
								echo '—';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$pages = (int) ceil( $total / self::PER_PAGE );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					[
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					]
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * CSV export (UTF-8 with BOM so Excel shows Persian correctly).
	 */
	public static function export() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'bavar_export' ) ) {
			wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
		}
		global $wpdb;
		$labels = self::labels();
		$table  = Bavar_Leads::table();
		$where  = self::where( self::filters() );
		$rows   = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bavar-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, [ 'Date', 'Full Name', 'Phone Number', 'Job / Business', 'Selected Path', 'Selected Product', 'Source', 'Purchase Status', 'Order', 'Q1', 'A1', 'Q2', 'A2', 'Q3', 'A3', 'Page' ] );
		foreach ( $rows as $r ) {
			$qa   = json_decode( (string) $r['answers'], true ) ?: [];
			$line = [
				$r['created_at'],
				self::csv_safe( $r['full_name'] ),
				$r['phone'],
				self::csv_safe( $r['job'] ),
				$labels['path'][ $r['path'] ] ?? $r['path'],
				$r['product_id'] ? get_the_title( (int) $r['product_id'] ) : '',
				$labels['source'][ $r['source'] ] ?? $r['source'],
				$labels['status'][ $r['purchase_status'] ] ?? $r['purchase_status'],
				$r['order_id'] ?: '',
			];
			for ( $i = 0; $i < 3; $i++ ) {
				$line[] = $qa[ $i ]['q'] ?? '';
				$line[] = self::csv_safe( $qa[ $i ]['a'] ?? '' );
			}
			$line[] = $r['page_url'];
			fputcsv( $out, $line );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Neutralise spreadsheet formulas in user-supplied text.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function csv_safe( $value ) {
		return preg_match( '/^[=+\-@\t\r]/', (string) $value ) ? "'" . $value : (string) $value;
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting( 'bavar_settings', Bavar_Settings::OPTION, [ 'sanitize_callback' => [ __CLASS__, 'sanitize' ] ] );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];
		$out   = [
			'gate_mode'    => in_array( $input['gate_mode'] ?? '', [ 'required', 'dismissible', 'off' ], true ) ? $input['gate_mode'] : 'required',
			'gate_title'   => sanitize_text_field( $input['gate_title'] ?? '' ),
			'gate_text'    => sanitize_textarea_field( $input['gate_text'] ?? '' ),
			'spot_api_key' => sanitize_text_field( $input['spot_api_key'] ?? '' ),
			'spot_offline' => (string) absint( $input['spot_offline'] ?? 30 ),
			'page_library' => absint( $input['page_library'] ?? 0 ),
			'page_simorgh' => absint( $input['page_simorgh'] ?? 0 ),
			'page_consult' => absint( $input['page_consult'] ?? 0 ),
			'paths'        => [],
		];
		foreach ( array_keys( Bavar_Settings::default_paths() ) as $key ) {
			$p = $input['paths'][ $key ] ?? [];
			foreach ( [ 'en', 'title', 'subtitle', 'desc', 'cta', 'q1', 'q2', 'q3' ] as $field ) {
				$out['paths'][ $key ][ $field ] = sanitize_text_field( $p[ $field ] ?? '' );
			}
			$out['paths'][ $key ]['target'] = esc_url_raw( $p['target'] ?? '' );
		}
		return $out;
	}

	/**
	 * Settings page.
	 */
	public static function settings_page() {
		$s     = Bavar_Settings::all();
		$name  = Bavar_Settings::OPTION;
		$field = function ( $label, $key, $value, $type = 'text', $help = '' ) {
			echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
			if ( 'textarea' === $type ) {
				echo '<textarea class="large-text" rows="3" name="' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		};
		?>
		<div class="wrap">
			<h1>تنظیمات BAVAR</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'bavar_settings' ); ?>

				<h2>فرم ورود به سایت</h2>
				<table class="form-table">
					<tr><th scope="row">نمایش فرم</th><td>
						<select name="<?php echo esc_attr( $name ); ?>[gate_mode]">
							<option value="required" <?php selected( $s['gate_mode'], 'required' ); ?>>اجباری (قبل از دیدن سایت)</option>
							<option value="dismissible" <?php selected( $s['gate_mode'], 'dismissible' ); ?>>قابل بستن</option>
							<option value="off" <?php selected( $s['gate_mode'], 'off' ); ?>>خاموش</option>
						</select>
					</td></tr>
					<?php
					$field( 'عنوان', $name . '[gate_title]', $s['gate_title'] );
					$field( 'توضیح', $name . '[gate_text]', $s['gate_text'], 'textarea' );
					?>
				</table>

				<h2>چهار مسیر اصلی و سه سؤال هر مسیر</h2>
				<?php foreach ( $s['paths'] as $key => $p ) : ?>
					<h3 style="margin-top:28px"><?php echo esc_html( $p['en'] ); ?></h3>
					<table class="form-table">
						<?php
						$base = $name . '[paths][' . $key . ']';
						$field( 'عنوان انگلیسی', $base . '[en]', $p['en'] );
						$field( 'عنوان', $base . '[title]', $p['title'] );
						$field( 'زیرعنوان', $base . '[subtitle]', $p['subtitle'] );
						$field( 'توضیح کوتاه', $base . '[desc]', $p['desc'] );
						$field( 'متن دکمه', $base . '[cta]', $p['cta'] );
						$field( 'سؤال ۱', $base . '[q1]', $p['q1'] );
						$field( 'سؤال ۲', $base . '[q2]', $p['q2'] );
						$field( 'سؤال ۳', $base . '[q3]', $p['q3'] );
						$field( 'مقصد (اختیاری)', $base . '[target]', $p['target'], 'url', 'خالی بگذارید تا خودکار تعیین شود: ' . Bavar_Settings::path_target( $key ) );
						?>
					</table>
				<?php endforeach; ?>

				<h2>برگه‌ها</h2>
				<table class="form-table">
					<?php
					foreach ( [
						'page_library' => 'برگه‌ی کتابخانه (BAVAR LIBRARY)',
						'page_simorgh' => 'برگه‌ی آشیانه سیمرغ‌ها',
						'page_consult' => 'برگه‌ی مشاوره',
					] as $key => $label ) {
						echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
						wp_dropdown_pages(
							[
								'name'              => esc_attr( $name . '[' . $key . ']' ),
								'selected'          => (int) $s[ $key ],
								'show_option_none'  => '—',
								'option_none_value' => 0,
							]
						);
						echo '</td></tr>';
					}
					?>
				</table>

				<h2>اسپات‌پلیر (اختیاری)</h2>
				<table class="form-table">
					<?php
					$field( 'کلید API', $name . '[spot_api_key]', $s['spot_api_key'], 'password', 'از پنل اسپات‌پلیر دریافت کنید. شناسه‌ی دوره را در صفحه‌ی ویرایش محصول دوره وارد کنید.' );
					$field( 'روزهای استفاده‌ی آفلاین', $name . '[spot_offline]', $s['spot_offline'], 'number' );
					?>
				</table>

				<?php submit_button( 'ذخیره‌ی تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}
}
