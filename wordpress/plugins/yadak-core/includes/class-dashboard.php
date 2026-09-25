<?php
/**
 * Sales & finance dashboard (فروشگاه یدک › داشبورد): today's sales, month
 * revenue and gross profit (from cost-price snapshots), receivables,
 * inventory value, low stock, top products and customers, new vs repeat
 * customers, and dead stock (no sale in 90 days).
 *
 * Computed from the last 90 days of orders on each view; cached 10 minutes.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Dashboard {

	const PAID = array( 'processing', 'completed', 'on-hold' );

	/**
	 * @return array Report data.
	 */
	public static function data() {
		$cached = get_transient( 'yadak_dashboard' );
		if ( is_array( $cached ) && empty( $_GET['refresh'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $cached;
		}
		global $wpdb;
		$tz         = wp_timezone();
		$today      = ( new DateTime( 'today', $tz ) )->getTimestamp();
		$month_from = ( new DateTime( 'today -29 days', $tz ) )->getTimestamp();
		$from90     = ( new DateTime( 'today -89 days', $tz ) )->getTimestamp();

		$d = array(
			'today_orders'  => 0,
			'today_revenue' => 0.0,
			'month_orders'  => 0,
			'month_revenue' => 0.0,
			'month_cost'    => 0.0,
			'month_costed'  => 0.0,
			'products'      => array(),
			'customers'     => array(),
			'sold90'        => array(),
			'new_customers' => 0,
			'repeat'        => 0,
		);

		$page = 1;
		do {
			$orders = wc_get_orders(
				array(
					'status'       => self::PAID,
					'date_created' => '>=' . $from90,
					'limit'        => 200,
					'page'         => $page,
					'type'         => 'shop_order',
				)
			);
			foreach ( $orders as $order ) {
				$ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
				foreach ( $order->get_items() as $item ) {
					$d['sold90'][ $item->get_product_id() ] = true;
				}
				if ( $ts < $month_from ) {
					continue;
				}
				$total = (float) $order->get_total() - (float) $order->get_total_refunded();
				++$d['month_orders'];
				$d['month_revenue'] += $total;
				if ( $ts >= $today ) {
					++$d['today_orders'];
					$d['today_revenue'] += $total;
				}
				foreach ( $order->get_items() as $item ) {
					$pid  = $item->get_product_id();
					$line = (float) $item->get_total();
					$cost = $item->get_meta( '_yadak_cost', true );
					if ( '' !== $cost ) {
						$d['month_cost']   += (float) $cost * $item->get_quantity();
						$d['month_costed'] += $line;
					}
					if ( ! isset( $d['products'][ $pid ] ) ) {
						$d['products'][ $pid ] = array( 'qty' => 0, 'revenue' => 0.0, 'profit' => null );
					}
					$d['products'][ $pid ]['qty']     += $item->get_quantity();
					$d['products'][ $pid ]['revenue'] += $line;
					if ( '' !== $cost ) {
						$d['products'][ $pid ]['profit'] = (float) $d['products'][ $pid ]['profit'] + $line - (float) $cost * $item->get_quantity();
					}
				}
				$cid = $order->get_customer_id();
				if ( $cid ) {
					if ( ! isset( $d['customers'][ $cid ] ) ) {
						$d['customers'][ $cid ] = array( 'orders' => 0, 'revenue' => 0.0 );
					}
					++$d['customers'][ $cid ]['orders'];
					$d['customers'][ $cid ]['revenue'] += $total;
				}
			}
			++$page;
		} while ( count( $orders ) === 200 && $page < 50 );

		foreach ( array_keys( $d['customers'] ) as $cid ) {
			if ( wc_get_customer_order_count( $cid ) > 1 ) {
				++$d['repeat'];
			} else {
				++$d['new_customers'];
			}
		}
		uasort( $d['products'], static function ( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
		uasort( $d['customers'], static function ( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
		$d['products']  = array_slice( $d['products'], 0, 10, true );
		$d['customers'] = array_slice( $d['customers'], 0, 10, true );

		// Receivables = positive customer balances.
		$ledger = Yadak_Credit::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$d['receivables'] = (float) $wpdb->get_var( "SELECT SUM(b) FROM (SELECT SUM(CASE WHEN type = 'charge' THEN amount ELSE -amount END) AS b FROM {$ledger} GROUP BY user_id) t WHERE b > 0" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$d['cheques_week'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$ledger} WHERE method = 'cheque' AND due_date BETWEEN %s AND %s", yadak_today(), yadak_today( '+7 days' ) ) );

		// Inventory value at cost and dead stock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$stock_rows = $wpdb->get_results(
			"SELECT p.ID, s.meta_value AS stock, c.meta_value AS cost, l.meta_value AS low
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_stock'
			LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_yadak_cost'
			LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_low_stock_amount'
			WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'"
		);
		$low_default       = (float) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$d['stock_value']  = 0.0;
		$d['stock_nocost'] = 0;
		$d['low']          = array();
		$d['dead']         = array();
		$d['dead_value']   = 0.0;
		foreach ( $stock_rows as $row ) {
			$qty = (float) $row->stock;
			if ( $qty > 0 ) {
				if ( '' === (string) $row->cost || null === $row->cost ) {
					++$d['stock_nocost'];
				}
				$d['stock_value'] += $qty * (float) $row->cost;
			}
			$min = '' !== (string) $row->low && null !== $row->low ? (float) $row->low : $low_default;
			if ( $qty <= $min ) {
				$d['low'][ (int) $row->ID ] = $qty;
			}
			$parent = wp_get_post_parent_id( (int) $row->ID );
			if ( $qty > 0 && empty( $d['sold90'][ $parent ? $parent : (int) $row->ID ] ) && strtotime( get_post_field( 'post_date_gmt', (int) $row->ID ) . ' UTC' ) < $from90 ) {
				$d['dead'][ (int) $row->ID ] = $qty * (float) $row->cost;
				$d['dead_value']            += $qty * (float) $row->cost;
			}
		}
		asort( $d['low'] );
		arsort( $d['dead'] );
		$d['low']  = array_slice( $d['low'], 0, 15, true );
		$d['dead'] = array_slice( $d['dead'], 0, 15, true );
		unset( $d['sold90'] );
		$d['generated'] = time();

		set_transient( 'yadak_dashboard', $d, 10 * MINUTE_IN_SECONDS );
		return $d;
	}

	/**
	 * Live order pipeline: what needs action now (not cached).
	 */
	private static function render_orders() {
		$statuses = array(
			'pending'    => __( 'در انتظار پرداخت', 'yadak-core' ),
			'on-hold'    => __( 'در انتظار بررسی (کارت‌به‌کارت/اعتباری)', 'yadak-core' ),
			'processing' => __( 'پرداخت‌شده، آماده ارسال', 'yadak-core' ),
		);
		$orders_url = class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
			? admin_url( 'admin.php?page=wc-orders&status=wc-' )
			: admin_url( 'edit.php?post_type=shop_order&post_status=wc-' );
		echo '<h2 style="font-size:14px">' . esc_html__( 'سفارش‌هایی که منتظر شما هستند', 'yadak-core' ) . '</h2><div class="yd-tiles" style="margin-top:6px">';
		foreach ( $statuses as $status => $label ) {
			$count = wc_orders_count( $status );
			echo '<a class="yd-tile" style="text-decoration:none" href="' . esc_url( $orders_url . $status ) . '"><div class="yd-tile__label">' . esc_html( $label ) . '</div><div class="yd-tile__value">' . esc_html( number_format_i18n( $count ) ) . '</div></a>';
		}
		echo '</div>';

		$waiting = wc_get_orders(
			array(
				'status'  => array( 'processing', 'on-hold' ),
				'limit'   => 10,
				'orderby' => 'date',
				'order'   => 'ASC',
			)
		);
		if ( ! $waiting ) {
			return;
		}
		echo '<table class="widefat striped" style="margin-bottom:20px"><thead><tr><th>' . esc_html__( 'سفارش', 'yadak-core' ) . '</th><th>' . esc_html__( 'مشتری', 'yadak-core' ) . '</th><th>' . esc_html__( 'مبلغ', 'yadak-core' ) . '</th><th>' . esc_html__( 'وضعیت', 'yadak-core' ) . '</th><th>' . esc_html__( 'منتظر از', 'yadak-core' ) . '</th><th>' . esc_html__( 'شهر', 'yadak-core' ) . '</th></tr></thead><tbody>';
		foreach ( $waiting as $order ) {
			$created = $order->get_date_created();
			$hours   = $created ? ( time() - $created->getTimestamp() ) / HOUR_IN_SECONDS : 0;
			$style   = $hours > 24 ? 'color:#b32d2e;font-weight:700' : ( $hours > 4 ? 'color:#996800;font-weight:700' : '' );
			printf(
				'<tr><td><a href="%1$s">#%2$s</a></td><td>%3$s</td><td class="yd-num">%4$s</td><td>%5$s</td><td style="%6$s">%7$s</td><td>%8$s</td></tr>',
				esc_url( $order->get_edit_order_url() ),
				esc_html( $order->get_order_number() ),
				esc_html( $order->get_formatted_billing_full_name() ),
				esc_html( Yadak_SMS::plain_money( $order->get_total() ) ),
				esc_html( wc_get_order_status_name( $order->get_status() ) ),
				esc_attr( $style ),
				esc_html( $created ? human_time_diff( $created->getTimestamp() ) : '' ),
				esc_html( $order->get_billing_city() )
			);
		}
		echo '</tbody></table>';
	}

	private static function tile( $label, $value, $sub = '' ) {
		echo '<div class="yd-tile"><div class="yd-tile__label">' . esc_html( $label ) . '</div><div class="yd-tile__value">' . esc_html( $value ) . '</div>' . ( $sub ? '<div class="yd-tile__sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div>';
	}

	public static function render() {
		$d      = self::data();
		$money  = array( 'Yadak_SMS', 'plain_money' );
		$profit = $d['month_costed'] - $d['month_cost'];
		$margin = $d['month_costed'] > 0 ? round( 100 * $profit / $d['month_costed'] ) : null;
		$due    = count( Yadak_CRM::due_items( get_current_user_id(), yadak_today() ) );
		?>
		<div class="wrap yd">
			<style>
				.yd-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin:12px 0 20px}
				.yd-tile{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px}
				.yd-tile__label{color:#646970;font-size:12px}
				.yd-tile__value{font-size:20px;font-weight:700;color:#1d2327;margin-top:2px}
				.yd-tile__sub{color:#646970;font-size:12px;margin-top:2px}
				.yd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px}
				.yd-grid h2{font-size:14px;margin:0 0 8px}
				.yd-grid .widefat td,.yd-grid .widefat th{padding:6px 8px}
				.yd-num{white-space:nowrap}
			</style>
			<h1><?php esc_html_e( 'داشبورد فروشگاه', 'yadak-core' ); ?></h1>
			<p class="description">
				<?php
				/* translators: %s: time */
				echo esc_html( sprintf( __( 'به‌روزرسانی: %s', 'yadak-core' ), wp_date( 'Y/m/d H:i', $d['generated'] ) ) );
				?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak&refresh=1' ) ); ?>"><?php esc_html_e( 'بازخوانی', 'yadak-core' ); ?></a>
				<?php if ( $due ) : ?>
					— <a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak-followups' ) ); ?>"><?php /* translators: %d: count */ echo esc_html( sprintf( __( '%d پیگیری امروز دارید', 'yadak-core' ), $due ) ); ?></a>
				<?php endif; ?>
			</p>

			<div class="yd-tiles">
				<?php
				/* translators: %s: count */
				self::tile( __( 'فروش امروز', 'yadak-core' ), $money( $d['today_revenue'] ), sprintf( __( '%s سفارش', 'yadak-core' ), number_format_i18n( $d['today_orders'] ) ) );
				/* translators: %s: count */
				self::tile( __( 'فروش ۳۰ روز', 'yadak-core' ), $money( $d['month_revenue'] ), sprintf( __( '%s سفارش', 'yadak-core' ), number_format_i18n( $d['month_orders'] ) ) );
				self::tile(
					__( 'سود ناخالص ۳۰ روز', 'yadak-core' ),
					$d['month_costed'] > 0 ? $money( $profit ) : '—',
					/* translators: %s: percent */
					null !== $margin ? sprintf( __( 'حاشیه سود %s٪ (فقط اقلام دارای قیمت خرید)', 'yadak-core' ), number_format_i18n( $margin ) ) : __( 'قیمت خرید محصولات را وارد کنید', 'yadak-core' )
				);
				self::tile( __( 'مطالبات از مشتریان', 'yadak-core' ), $money( $d['receivables'] ), $d['cheques_week'] ? sprintf( /* translators: %s: amount */ __( 'چک‌های سررسید ۷ روز آینده: %s', 'yadak-core' ), $money( $d['cheques_week'] ) ) : '' );
				self::tile( __( 'ارزش موجودی انبار', 'yadak-core' ), $money( $d['stock_value'] ), $d['stock_nocost'] ? sprintf( /* translators: %s: count */ __( '%s کالای موجود بدون قیمت خرید', 'yadak-core' ), number_format_i18n( $d['stock_nocost'] ) ) : __( 'به قیمت خرید', 'yadak-core' ) );
				self::tile( __( 'خواب سرمایه', 'yadak-core' ), $money( $d['dead_value'] ), __( 'موجودی بدون فروش در ۹۰ روز', 'yadak-core' ) );
				self::tile( __( 'مشتریان ۳۰ روز', 'yadak-core' ), number_format_i18n( $d['new_customers'] + $d['repeat'] ), sprintf( /* translators: 1: new, 2: repeat */ __( '%1$s جدید، %2$s تکراری', 'yadak-core' ), number_format_i18n( $d['new_customers'] ), number_format_i18n( $d['repeat'] ) ) );
				self::tile( __( 'کالاهای رو به اتمام', 'yadak-core' ), number_format_i18n( count( $d['low'] ) ), __( 'موجودی ≤ حداقل', 'yadak-core' ) );
				?>
			</div>

			<?php self::render_orders(); ?>

			<div class="yd-grid">
				<div>
					<h2><?php esc_html_e( 'پرفروش‌ترین کالاها (۳۰ روز)', 'yadak-core' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'تعداد', 'yadak-core' ); ?></th><th><?php esc_html_e( 'فروش', 'yadak-core' ); ?></th><th><?php esc_html_e( 'سود', 'yadak-core' ); ?></th></tr></thead><tbody>
					<?php foreach ( $d['products'] as $pid => $row ) : ?>
						<tr><td><a href="<?php echo esc_url( (string) get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td><td class="yd-num"><?php echo esc_html( number_format_i18n( $row['qty'] ) ); ?></td><td class="yd-num"><?php echo esc_html( $money( $row['revenue'] ) ); ?></td><td class="yd-num"><?php echo esc_html( null === $row['profit'] ? '—' : $money( $row['profit'] ) ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $d['products'] ) : ?><tr><td colspan="4"><?php esc_html_e( 'فروشی ثبت نشده.', 'yadak-core' ); ?></td></tr><?php endif; ?>
					</tbody></table>
				</div>
				<div>
					<h2><?php esc_html_e( 'مشتریان برتر (۳۰ روز)', 'yadak-core' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'مشتری', 'yadak-core' ); ?></th><th><?php esc_html_e( 'سفارش', 'yadak-core' ); ?></th><th><?php esc_html_e( 'خرید', 'yadak-core' ); ?></th><th><?php esc_html_e( 'مانده', 'yadak-core' ); ?></th></tr></thead><tbody>
					<?php foreach ( $d['customers'] as $cid => $row ) : ?>
						<tr><td><a href="<?php echo esc_url( get_edit_user_link( $cid ) ); ?>"><?php echo esc_html( Yadak_SMS::user_name( $cid ) ); ?></a></td><td class="yd-num"><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></td><td class="yd-num"><?php echo esc_html( $money( $row['revenue'] ) ); ?></td><td class="yd-num"><?php echo esc_html( $money( max( 0, Yadak_Credit::balance( $cid ) ) ) ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $d['customers'] ) : ?><tr><td colspan="4"><?php esc_html_e( 'فروشی ثبت نشده.', 'yadak-core' ); ?></td></tr><?php endif; ?>
					</tbody></table>
				</div>
				<div>
					<h2><?php esc_html_e( 'کمبود موجودی', 'yadak-core' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'موجودی', 'yadak-core' ); ?></th></tr></thead><tbody>
					<?php foreach ( $d['low'] as $pid => $qty ) : ?>
						<tr><td><a href="<?php echo esc_url( (string) get_edit_post_link( wp_get_post_parent_id( $pid ) ? wp_get_post_parent_id( $pid ) : $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td><td class="yd-num"><?php echo esc_html( wc_stock_amount( $qty ) ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $d['low'] ) : ?><tr><td colspan="2"><?php esc_html_e( 'کمبودی نیست.', 'yadak-core' ); ?></td></tr><?php endif; ?>
					</tbody></table>
				</div>
				<div>
					<h2><?php esc_html_e( 'خواب سرمایه (بدون فروش ۹۰ روز)', 'yadak-core' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'ارزش موجودی', 'yadak-core' ); ?></th></tr></thead><tbody>
					<?php foreach ( $d['dead'] as $pid => $value ) : ?>
						<tr><td><a href="<?php echo esc_url( (string) get_edit_post_link( wp_get_post_parent_id( $pid ) ? wp_get_post_parent_id( $pid ) : $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td><td class="yd-num"><?php echo esc_html( $value ? $money( $value ) : '—' ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $d['dead'] ) : ?><tr><td colspan="2"><?php esc_html_e( 'موردی نیست.', 'yadak-core' ); ?></td></tr><?php endif; ?>
					</tbody></table>
				</div>
			</div>
		</div>
		<?php
	}
}
