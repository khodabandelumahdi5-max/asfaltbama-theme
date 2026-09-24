<?php
/**
 * Admin: contacts (CRM), contact profile + activity, analytics, settings.
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
		add_action( 'admin_post_bavar_export_contacts', [ __CLASS__, 'export' ] );
		add_action( 'admin_post_bavar_save_contact', [ __CLASS__, 'save_contact' ] );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'گروه باور', 'گروه باور', 'manage_woocommerce', 'bavar', [ __CLASS__, 'contacts_page' ], 'dashicons-star-filled', 3 );
		add_submenu_page( 'bavar', 'مخاطبان', 'مخاطبان و لیدها', 'manage_woocommerce', 'bavar', [ __CLASS__, 'contacts_page' ] );
		add_submenu_page( 'bavar', 'آمار', 'آمار', 'manage_woocommerce', 'bavar-analytics', [ __CLASS__, 'analytics_page' ] );
		add_submenu_page( 'bavar', 'تنظیمات گروه باور', 'تنظیمات', 'manage_options', 'bavar-settings', [ __CLASS__, 'settings_page' ] );
	}

	/**
	 * Section labels.
	 *
	 * @return array
	 */
	private static function path_labels() {
		$out = [];
		foreach ( Bavar_Settings::get( 'paths' ) as $key => $p ) {
			$out[ $key ] = $p['label'];
		}
		return $out;
	}

	/**
	 * Format a local MySQL datetime.
	 *
	 * @param string|null $mysql Datetime.
	 * @return string
	 */
	private static function date( $mysql ) {
		if ( ! $mysql ) {
			return '—';
		}
		return wp_date( 'Y/m/d H:i', strtotime( $mysql ) - (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/* ---------------------------------------------------------------------
	 * Contacts
	 * ------------------------------------------------------------------- */

	/**
	 * Filters from the request.
	 *
	 * @return array
	 */
	private static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$date = function ( $key ) {
			$v = sanitize_text_field( wp_unslash( $_GET[ $key ] ?? '' ) );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		return [
			'path'    => sanitize_key( wp_unslash( $_GET['path'] ?? '' ) ),
			'product' => absint( $_GET['product'] ?? 0 ),
			'status'  => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'from'    => $date( 'from' ),
			'to'      => $date( 'to' ),
			's'       => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
		];
		// phpcs:enable
	}

	/**
	 * WHERE clause.
	 *
	 * @param array $f Filters.
	 * @return string
	 */
	private static function where( array $f ) {
		global $wpdb;
		$events = Bavar_CRM::events_table();
		$where  = [ '1=1' ];
		if ( $f['path'] ) {
			$where[] = $wpdb->prepare( "(c.source = %s OR EXISTS (SELECT 1 FROM {$events} e WHERE e.contact_id = c.id AND e.path = %s))", $f['path'], $f['path'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $f['product'] ) {
			$where[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$events} e WHERE e.contact_id = c.id AND e.product_id = %d)", $f['product'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $f['status'] ) {
			$where[] = $wpdb->prepare( 'c.status = %s', $f['status'] );
		}
		if ( $f['from'] ) {
			$where[] = $wpdb->prepare( 'c.created_at >= %s', $f['from'] . ' 00:00:00' );
		}
		if ( $f['to'] ) {
			$where[] = $wpdb->prepare( 'c.created_at <= %s', $f['to'] . ' 23:59:59' );
		}
		if ( $f['s'] ) {
			$like    = '%' . $wpdb->esc_like( bavar_latin_digits( $f['s'] ) ) . '%';
			$where[] = $wpdb->prepare( '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.phone LIKE %s OR c.job LIKE %s OR c.notes LIKE %s)', $like, $like, $like, $like, $like );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Sections a contact has touched.
	 *
	 * @param int[] $ids Contact IDs.
	 * @return array contact_id => path[]
	 */
	private static function paths_for( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return [];
		}
		$events = Bavar_CRM::events_table();
		$in     = implode( ',', array_map( 'intval', $ids ) );
		$rows   = $wpdb->get_results( "SELECT DISTINCT contact_id, path FROM {$events} WHERE contact_id IN ({$in}) AND path <> '' AND path <> 'gate'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out    = [];
		foreach ( $rows as $r ) {
			$out[ (int) $r->contact_id ][] = $r->path;
		}
		return $out;
	}

	/**
	 * Products that appear in events (for the filter).
	 *
	 * @return array id => title
	 */
	private static function tracked_products() {
		global $wpdb;
		$events = Bavar_CRM::events_table();
		$ids    = $wpdb->get_col( "SELECT DISTINCT product_id FROM {$events} WHERE product_id > 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out    = [];
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = get_the_title( (int) $id );
		}
		asort( $out );
		return $out;
	}

	/**
	 * Contacts page (list, or one profile).
	 */
	public static function contacts_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$contact = absint( $_GET['contact'] ?? 0 );
		if ( $contact ) {
			self::contact_profile( $contact );
			return;
		}

		global $wpdb;
		$f        = self::filters();
		$table    = Bavar_CRM::contacts_table();
		$where    = self::where( $f );
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset   = ( $paged - 1 ) * self::PER_PAGE;
		$statuses = Bavar_CRM::statuses();
		$sources  = Bavar_CRM::sources();
		$paths    = self::path_labels();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} c WHERE {$where}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM {$table} c WHERE {$where} ORDER BY COALESCE(c.last_activity_at, c.created_at) DESC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ) );
		$stats = $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$table} GROUP BY status", OBJECT_K );
		// phpcs:enable
		$touched = self::paths_for( wp_list_pluck( $rows, 'id' ) );
		$events  = Bavar_CRM::event_types();
		$export  = wp_nonce_url( add_query_arg( array_filter( $f ) + [ 'action' => 'bavar_export_contacts' ], admin_url( 'admin-post.php' ) ), 'bavar_export' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">مخاطبان و لیدها</h1>
			<a class="page-title-action" href="<?php echo esc_url( $export ); ?>">خروجی اکسل (CSV)</a>
			<p>
				<?php foreach ( $statuses as $k => $l ) : ?>
					<span style="display:inline-block;margin-left:18px"><?php echo esc_html( $l ); ?>: <strong><?php echo esc_html( isset( $stats[ $k ] ) ? $stats[ $k ]->n : 0 ); ?></strong></span>
				<?php endforeach; ?>
			</p>
			<form method="get" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:12px 0">
				<input type="hidden" name="page" value="bavar">
				<select name="path"><option value="">همه‌ی بخش‌ها</option>
					<?php foreach ( $paths as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['path'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="product"><option value="0">همه‌ی محصولات</option>
					<?php foreach ( self::tracked_products() as $id => $title ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $f['product'], $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status"><option value="">همه‌ی وضعیت‌ها</option>
					<?php foreach ( $statuses as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<label>از <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
				<label>تا <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
				<input type="search" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="نام، موبایل، شغل یا توضیحات">
				<button class="button">فیلتر</button>
				<?php if ( array_filter( $f ) ) : ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=bavar' ) ); ?>">حذف فیلترها</a>
				<?php endif; ?>
			</form>
			<p><?php echo esc_html( sprintf( '%s مخاطب', number_format_i18n( $total ) ) ); ?></p>
			<table class="widefat striped">
				<thead><tr><th>نام</th><th>موبایل</th><th>شغل</th><th>منبع ورود</th><th>بخش‌های موردنظر</th><th>وضعیت پیگیری</th><th>آخرین فعالیت</th><th>تاریخ ثبت</th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="8">مخاطبی پیدا نشد.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<?php $url = admin_url( 'admin.php?page=bavar&contact=' . (int) $r->id ); ?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( trim( $r->first_name . ' ' . $r->last_name ) ?: '(بدون نام)' ); ?></a></strong></td>
						<td dir="ltr" style="text-align:right"><a href="tel:<?php echo esc_attr( $r->phone ); ?>"><?php echo esc_html( $r->phone ); ?></a></td>
						<td><?php echo esc_html( $r->job ); ?></td>
						<td><?php echo esc_html( $sources[ $r->source ] ?? $r->source ); ?></td>
						<td><?php echo esc_html( implode( '، ', array_map( fn( $p ) => $paths[ $p ] ?? $p, $touched[ (int) $r->id ] ?? [] ) ) ?: '—' ); ?></td>
						<td><?php echo esc_html( $statuses[ $r->status ] ?? $r->status ); ?></td>
						<td><?php echo esc_html( $events[ $r->last_activity ] ?? '—' ); ?><br><small><?php echo esc_html( self::date( $r->last_activity_at ) ); ?></small></td>
						<td><?php echo esc_html( self::date( $r->created_at ) ); ?></td>
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
	 * One contact: editable fields + activity timeline + orders.
	 *
	 * @param int $id Contact ID.
	 */
	private static function contact_profile( $id ) {
		global $wpdb;
		$c = Bavar_CRM::get( $id );
		if ( ! $c ) {
			echo '<div class="wrap"><p>مخاطب پیدا نشد.</p></div>';
			return;
		}
		$events_table = Bavar_CRM::events_table();
		$events       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$events_table} WHERE contact_id = %d ORDER BY id DESC LIMIT 300", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$types        = Bavar_CRM::event_types();
		$paths        = self::path_labels();
		$sources      = Bavar_CRM::sources();
		$orders       = function_exists( 'wc_get_orders' ) ? wc_get_orders(
			[
				'billing_phone' => $c->phone,
				'limit'         => 50,
			]
		) : [];
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bavar' ) ); ?>">→ بازگشت به فهرست مخاطبان</a></p>
			<h1><?php echo esc_html( trim( $c->first_name . ' ' . $c->last_name ) ?: $c->phone ); ?></h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( ! empty( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p>ذخیره شد.</p></div>
			<?php endif; ?>
			<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:24px;align-items:start">
				<div class="postbox" style="padding:16px 20px;min-width:0">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="bavar_save_contact">
						<input type="hidden" name="id" value="<?php echo esc_attr( $c->id ); ?>">
						<?php wp_nonce_field( 'bavar_save_contact_' . $c->id ); ?>
						<table class="form-table" role="presentation">
							<tr><th>موبایل</th><td dir="ltr" style="text-align:right"><a href="tel:<?php echo esc_attr( $c->phone ); ?>"><?php echo esc_html( $c->phone ); ?></a></td></tr>
							<tr><th><label for="bv-first">نام</label></th><td><input class="regular-text" id="bv-first" name="first_name" value="<?php echo esc_attr( $c->first_name ); ?>"></td></tr>
							<tr><th><label for="bv-last">نام خانوادگی</label></th><td><input class="regular-text" id="bv-last" name="last_name" value="<?php echo esc_attr( $c->last_name ); ?>"></td></tr>
							<tr><th><label for="bv-job">شغل</label></th><td><input class="regular-text" id="bv-job" name="job" value="<?php echo esc_attr( $c->job ); ?>"></td></tr>
							<tr><th><label for="bv-status">وضعیت پیگیری</label></th><td>
								<select id="bv-status" name="status">
									<?php foreach ( Bavar_CRM::statuses() as $k => $l ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c->status, $k ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
							<tr><th><label for="bv-notes">توضیحات</label></th><td><textarea class="large-text" rows="5" id="bv-notes" name="notes"><?php echo esc_textarea( (string) $c->notes ); ?></textarea></td></tr>
							<tr><th>منبع ورود</th><td><?php echo esc_html( $sources[ $c->source ] ?? $c->source ); ?></td></tr>
							<tr><th>تاریخ ثبت</th><td><?php echo esc_html( self::date( $c->created_at ) ); ?></td></tr>
						</table>
						<?php submit_button( 'ذخیره' ); ?>
					</form>
					<?php if ( $orders ) : ?>
						<h2>سفارش‌ها</h2>
						<ul>
							<?php foreach ( $orders as $o ) : ?>
								<li><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">سفارش #<?php echo esc_html( $o->get_order_number() ); ?></a> — <?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?> — <?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p class="description">برای لغو یا تغییر دسترسی به فایل‌ها، سفارش را باز کنید و از بخش «مجوزهای دانلود» استفاده کنید.</p>
					<?php endif; ?>
				</div>
				<div class="postbox" style="padding:16px 20px;min-width:0">
					<h2>تاریخچه‌ی فعالیت</h2>
					<?php if ( ! $events ) : ?>
						<p>فعالیتی ثبت نشده است.</p>
					<?php endif; ?>
					<table class="widefat striped">
						<tbody>
						<?php foreach ( $events as $e ) : ?>
							<?php $data = json_decode( (string) $e->data, true ); ?>
							<tr>
								<td style="white-space:nowrap"><?php echo esc_html( self::date( $e->created_at ) ); ?></td>
								<td>
									<strong><?php echo esc_html( $types[ $e->type ] ?? $e->type ); ?></strong>
									<?php if ( $e->path && isset( $paths[ $e->path ] ) ) : ?>
										— <?php echo esc_html( $paths[ $e->path ] ); ?>
									<?php endif; ?>
									<?php if ( $e->product_id ) : ?>
										— <?php echo esc_html( get_the_title( (int) $e->product_id ) ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $data['answers'] ) ) : ?>
										<?php foreach ( $data['answers'] as $qa ) : ?>
											<p style="margin:6px 0 0"><em><?php echo esc_html( $qa['q'] ); ?></em><br><?php echo nl2br( esc_html( $qa['a'] ) ); ?></p>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save a contact from its profile.
	 */
	public static function save_contact() {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'bavar_save_contact_' . $id ) ) {
			wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
		}
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) );
		Bavar_CRM::update(
			$id,
			[
				'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
				'job'        => sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) ),
				'status'     => array_key_exists( $status, Bavar_CRM::statuses() ) ? $status : 'new',
				'notes'      => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			]
		);
		wp_safe_redirect( admin_url( 'admin.php?page=bavar&contact=' . $id . '&saved=1' ) );
		exit;
	}

	/**
	 * CSV export (UTF-8 with BOM so Excel shows Persian correctly).
	 */
	public static function export() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'bavar_export' ) ) {
			wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
		}
		global $wpdb;
		$table    = Bavar_CRM::contacts_table();
		$where    = self::where( self::filters() );
		$rows     = $wpdb->get_results( "SELECT c.* FROM {$table} c WHERE {$where} ORDER BY c.id DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$touched  = self::paths_for( wp_list_pluck( $rows, 'id' ) );
		$paths    = self::path_labels();
		$statuses = Bavar_CRM::statuses();
		$sources  = Bavar_CRM::sources();
		$types    = Bavar_CRM::event_types();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bavar-contacts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, [ 'نام', 'نام خانوادگی', 'موبایل', 'شغل', 'منبع ورود', 'بخش‌های موردنظر', 'وضعیت پیگیری', 'آخرین فعالیت', 'زمان آخرین فعالیت', 'تاریخ ثبت', 'توضیحات' ], ',', '"', '\\' );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				[
					self::csv_safe( $r->first_name ),
					self::csv_safe( $r->last_name ),
					$r->phone,
					self::csv_safe( $r->job ),
					$sources[ $r->source ] ?? $r->source,
					implode( '، ', array_map( fn( $p ) => $paths[ $p ] ?? $p, $touched[ (int) $r->id ] ?? [] ) ),
					$statuses[ $r->status ] ?? $r->status,
					$types[ $r->last_activity ] ?? '',
					(string) $r->last_activity_at,
					$r->created_at,
					self::csv_safe( (string) $r->notes ),
				],
				',',
				'"',
				'\\'
			);
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

	/* ---------------------------------------------------------------------
	 * Analytics
	 * ------------------------------------------------------------------- */

	/**
	 * Analytics page.
	 */
	public static function analytics_page() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$from = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
		$to   = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );
		// phpcs:enable
		$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : wp_date( 'Y-m-d' );
		$a    = $from . ' 00:00:00';
		$b    = $to . ' 23:59:59';

		$events   = Bavar_CRM::events_table();
		$contacts = Bavar_CRM::contacts_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_type  = $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) n FROM {$events} WHERE created_at BETWEEN %s AND %s GROUP BY type", $a, $b ), OBJECT_K );
		$buyers   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT contact_id) FROM {$events} WHERE type = 'purchase_completed' AND created_at BETWEEN %s AND %s", $a, $b ) );
		$new      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$contacts} WHERE created_at BETWEEN %s AND %s", $a, $b ) );
		$sources  = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) n FROM {$contacts} WHERE created_at BETWEEN %s AND %s GROUP BY source ORDER BY n DESC", $a, $b ) );
		$products = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, path,
					SUM(type = 'product_viewed') views,
					SUM(type = 'purchase_started') started,
					SUM(type = 'purchase_completed') completed,
					SUM(type = 'content_accessed') accessed
				FROM {$events} WHERE created_at BETWEEN %s AND %s AND (product_id > 0 OR path IN ('library','simorgh','consult','course'))
				GROUP BY product_id, path ORDER BY views DESC LIMIT 50",
				$a,
				$b
			)
		);
		// phpcs:enable

		$n     = fn( $type ) => isset( $by_type[ $type ] ) ? (int) $by_type[ $type ]->n : 0;
		$tiles = [
			'بازدید محصولات و بخش‌ها' => $n( 'product_viewed' ),
			'مخاطب جدید'              => $new,
			'ثبت شماره'               => $n( 'phone_submitted' ),
			'درخواست مشاوره'          => $n( 'consultation_requested' ),
			'درخواست ثبت‌نام حضوری'   => $n( 'registration_requested' ),
			'شروع خرید'               => $n( 'purchase_started' ),
			'خرید موفق'               => $n( 'purchase_completed' ),
			'کاربران خریدار'          => $buyers,
			'دسترسی به محتوا'         => $n( 'content_accessed' ),
		];
		$paths      = self::path_labels();
		$source_map = Bavar_CRM::sources();
		?>
		<div class="wrap">
			<h1>آمار</h1>
			<form method="get" style="display:flex;gap:8px;align-items:center;margin:12px 0 20px">
				<input type="hidden" name="page" value="bavar-analytics">
				<label>از <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
				<label>تا <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
				<button class="button">نمایش</button>
			</form>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:28px">
				<?php foreach ( $tiles as $label => $value ) : ?>
					<div class="postbox" style="padding:16px 18px;margin:0;min-width:0">
						<div style="color:#646970"><?php echo esc_html( $label ); ?></div>
						<div style="font-size:26px;font-weight:600;margin-top:6px"><?php echo esc_html( number_format_i18n( $value ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<h2>محصولات و بخش‌ها</h2>
			<table class="widefat striped" style="margin-bottom:28px">
				<thead><tr><th>محصول / بخش</th><th>بازدید</th><th>شروع خرید</th><th>خرید موفق</th><th>دسترسی به محتوا</th></tr></thead>
				<tbody>
				<?php if ( ! $products ) : ?>
					<tr><td colspan="5">در این بازه داده‌ای ثبت نشده است.</td></tr>
				<?php endif; ?>
				<?php foreach ( $products as $p ) : ?>
					<tr>
						<td><?php echo esc_html( $p->product_id ? get_the_title( (int) $p->product_id ) : ( $paths[ $p->path ] ?? $p->path ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $p->views ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $p->started ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $p->completed ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $p->accessed ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>منبع ورود مخاطبان جدید</h2>
			<table class="widefat striped" style="max-width:520px">
				<tbody>
				<?php if ( ! $sources ) : ?>
					<tr><td>در این بازه مخاطب جدیدی ثبت نشده است.</td></tr>
				<?php endif; ?>
				<?php foreach ( $sources as $s ) : ?>
					<tr><td><?php echo esc_html( $source_map[ $s->source ] ?? ( $s->source ?: '—' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $s->n ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

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
			foreach ( [ 'label', 'title', 'desc', 'cta' ] as $field ) {
				$out['paths'][ $key ][ $field ] = sanitize_text_field( $p[ $field ] ?? '' );
			}
			$out['paths'][ $key ]['questions'] = sanitize_textarea_field( $p['questions'] ?? '' );
			$fields                            = array_intersect( [ 'first_name', 'last_name', 'phone', 'job' ], array_map( 'sanitize_key', (array) ( $p['fields'] ?? [] ) ) );
			$out['paths'][ $key ]['fields']    = implode( ',', array_unique( array_merge( $fields, [ 'phone' ] ) ) );
			$out['paths'][ $key ]['target']    = esc_url_raw( $p['target'] ?? '' );
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
				echo '<input class="large-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		};
		?>
		<div class="wrap">
			<h1>تنظیمات گروه باور</h1>
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

				<h2>چهار بخش اصلی</h2>
				<?php foreach ( $s['paths'] as $key => $p ) : ?>
					<h3 style="margin-top:28px"><?php echo esc_html( $p['label'] ); ?></h3>
					<table class="form-table">
						<?php
						$base = $name . '[paths][' . $key . ']';
						$field( 'نام کوتاه (برای پیشخوان)', $base . '[label]', $p['label'] );
						$field( 'عنوان', $base . '[title]', $p['title'] );
						$field( 'توضیح کوتاه', $base . '[desc]', $p['desc'] );
						$field( 'متن دکمه', $base . '[cta]', $p['cta'] );
						$field( 'سؤال‌های اختیاری (هر سؤال در یک خط)', $base . '[questions]', $p['questions'], 'textarea', 'اگر خالی باشد، فقط اطلاعات تماس گرفته می‌شود.' );
						$current = Bavar_Settings::fields( $key );
						echo '<tr><th scope="row">اطلاعاتی که گرفته می‌شود</th><td>';
						foreach ( Bavar_CRM::person_fields() as $fk => $fconf ) {
							printf(
								'<label style="margin-left:16px"><input type="checkbox" name="%s[fields][]" value="%s" %s %s> %s</label>',
								esc_attr( $base ),
								esc_attr( $fk ),
								checked( in_array( $fk, $current, true ), true, false ),
								'phone' === $fk ? 'disabled' : '',
								esc_html( $fconf[0] )
							);
						}
						echo '<p class="description">شماره تماس همیشه گرفته می‌شود.</p></td></tr>';
						$field( 'مقصد (اختیاری)', $base . '[target]', $p['target'], 'url', 'خالی بگذارید تا خودکار تعیین شود: ' . Bavar_Settings::path_target( $key ) );
						?>
					</table>
				<?php endforeach; ?>

				<h2>برگه‌ها</h2>
				<table class="form-table">
					<?php
					foreach ( [
						'page_library' => 'برگه‌ی پک‌های کتاب',
						'page_simorgh' => 'برگه‌ی آشیانه سیمرغ',
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
