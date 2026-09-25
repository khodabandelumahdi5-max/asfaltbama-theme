<?php
/**
 * Order extras: shipment tracking code, invoice print links, cost-price
 * snapshot per line (for profit reports), last-order date per customer,
 * replacement reminders, and the Moodian (سامانه مودیان) CSV export.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Orders {

	public static function init() {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'admin_tracking_fields' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_tracking' ), 50 );
		add_action( 'woocommerce_order_actions_end', array( __CLASS__, 'admin_print_button' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'customer_order_extras' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'my_orders_actions' ), 10, 2 );

		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_cost' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'touch_customer' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'touch_customer' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule_reminders' ), 10, 2 );
		add_action( 'yadak_daily', array( __CLASS__, 'send_reminders' ) );

		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yadak_moodian_export', array( __CLASS__, 'moodian_export' ) );
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function admin_tracking_fields( $order ) {
		?>
		<div class="yadak-tracking" style="clear:both;padding-top:8px">
			<h3><?php esc_html_e( 'ارسال مرسوله', 'yadak-core' ); ?></h3>
			<p class="form-field form-field-wide">
				<label for="yadak_carrier"><?php esc_html_e( 'شرکت حمل', 'yadak-core' ); ?></label>
				<input type="text" id="yadak_carrier" name="yadak_carrier" value="<?php echo esc_attr( $order->get_meta( '_yadak_carrier' ) ); ?>" placeholder="<?php esc_attr_e( 'پست، تیپاکس، باربری ...', 'yadak-core' ); ?>">
			</p>
			<p class="form-field form-field-wide">
				<label for="yadak_tracking"><?php esc_html_e( 'کد رهگیری', 'yadak-core' ); ?></label>
				<input type="text" id="yadak_tracking" name="yadak_tracking" value="<?php echo esc_attr( $order->get_meta( '_yadak_tracking' ) ); ?>" dir="ltr">
			</p>
			<p class="description"><?php esc_html_e( 'با تغییر وضعیت به «تکمیل‌شده»، کد رهگیری برای مشتری پیامک می‌شود.', 'yadak-core' ); ?></p>
		</div>
		<?php
	}

	public static function save_tracking( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the order save nonce.
		$order = wc_get_order( $order_id );
		if ( ! $order || ! isset( $_POST['yadak_tracking'] ) ) {
			return;
		}
		$order->update_meta_data( '_yadak_tracking', sanitize_text_field( yadak_normalize_digits( wp_unslash( $_POST['yadak_tracking'] ) ) ) );
		$order->update_meta_data( '_yadak_carrier', isset( $_POST['yadak_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['yadak_carrier'] ) ) : '' );
		$order->save_meta_data();
		// phpcs:enable
	}

	public static function admin_print_button( $order_id ) {
		printf(
			'<li class="wide"><a class="button" target="_blank" href="%1$s">%2$s</a></li>',
			esc_url( Yadak_Print::url( 'invoice', $order_id ) ),
			esc_html__( 'چاپ فاکتور', 'yadak-core' )
		);
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function customer_order_extras( $order ) {
		$tracking = $order->get_meta( '_yadak_tracking' );
		echo '<section class="yadak-order-extras">';
		if ( $tracking ) {
			printf(
				'<p><strong>%1$s</strong> %2$s — <span dir="ltr">%3$s</span></p>',
				esc_html__( 'کد رهگیری مرسوله:', 'yadak-core' ),
				esc_html( $order->get_meta( '_yadak_carrier' ) ),
				esc_html( $tracking )
			);
		}
		printf( '<p><a class="button" target="_blank" href="%1$s">%2$s</a></p>', esc_url( Yadak_Print::url( 'invoice', $order->get_id() ) ), esc_html__( 'مشاهده و چاپ فاکتور', 'yadak-core' ) );
		echo '</section>';
	}

	public static function my_orders_actions( $actions, $order ) {
		$actions['yadak_invoice'] = array(
			'url'  => Yadak_Print::url( 'invoice', $order->get_id() ),
			'name' => __( 'فاکتور', 'yadak-core' ),
		);
		return $actions;
	}

	/**
	 * Keep the cost price at the moment of sale on the order line.
	 *
	 * @param WC_Order_Item_Product $item Line.
	 */
	public static function snapshot_cost( $item ) {
		$product = $item->get_product();
		if ( $product ) {
			$cost = self::product_cost( $product );
			if ( null !== $cost ) {
				$item->add_meta_data( '_yadak_cost', $cost, true );
			}
		}
	}

	/**
	 * @param WC_Product $product Product or variation (falls back to the parent's cost).
	 * @return float|null
	 */
	public static function product_cost( $product ) {
		$cost = $product->get_meta( '_yadak_cost', true, 'edit' );
		if ( '' === $cost && $product->get_parent_id() ) {
			$cost = get_post_meta( $product->get_parent_id(), '_yadak_cost', true );
		}
		return '' === $cost ? null : (float) $cost;
	}

	public static function touch_customer( $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		if ( $order && $order->get_customer_id() ) {
			update_user_meta( $order->get_customer_id(), 'yadak_last_order', gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	public static function schedule_reminders( $order_id, $order = null ) {
		global $wpdb;
		$order = $order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! $order->get_customer_id() || $order->get_meta( '_yadak_reminders_set' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$days       = (int) get_post_meta( $product_id, '_yadak_replace_days', true );
			if ( $days <= 0 ) {
				continue;
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				Yadak_Install::table( 'reminders' ),
				array(
					'user_id'    => $order->get_customer_id(),
					'product_id' => $product_id,
					'order_id'   => $order->get_id(),
					'due_date'   => yadak_today( '+' . $days . ' days' ),
				)
			);
		}
		$order->update_meta_data( '_yadak_reminders_set', 1 );
		$order->save_meta_data();
	}

	/**
	 * Daily: SMS due replacement reminders and give the sales rep a follow-up.
	 */
	public static function send_reminders() {
		global $wpdb;
		$table = Yadak_Install::table( 'reminders' );
		$until = yadak_today( '+' . max( 0, (int) Yadak_Settings::get( 'reminder_days_before' ) ) . ' days' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE sent = 0 AND due_date <= %s LIMIT 500", $until ) );
		foreach ( $rows as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			$name    = $product ? $product->get_name() : '';
			Yadak_SMS::notify(
				'replace_due',
				Yadak_SMS::user_mobile( (int) $row->user_id ),
				array(
					'name'    => Yadak_SMS::user_name( (int) $row->user_id ),
					'product' => $name,
					'link'    => $product ? add_query_arg( 'p', $product->get_id(), home_url( '/' ) ) : home_url( '/' ),
				)
			);
			Yadak_CRM::add_activity(
				'user',
				(int) $row->user_id,
				'followup',
				/* translators: %s: product */
				sprintf( __( 'زمان تعویض «%s» — پیگیری برای خرید مجدد', 'yadak-core' ), $name ),
				yadak_today(),
				(int) get_user_meta( (int) $row->user_id, 'yadak_sales_rep', true )
			);
			$wpdb->update( $table, array( 'sent' => 1 ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'خروجی مودیان', 'yadak-core' ), __( 'خروجی مودیان', 'yadak-core' ), 'manage_woocommerce', 'yadak-moodian', array( __CLASS__, 'render_moodian' ), 80 );
	}

	public static function render_moodian() {
		$today = yadak_today();
		if ( 'yes' === Yadak_Settings::get( 'jalali' ) ) {
			$from = substr( Yadak_Jalali::from_gregorian( $today ), 0, 8 ) . '01';
		} else {
			$from = substr( $today, 0, 8 ) . '01';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'خروجی صورتحساب برای سامانه مودیان', 'yadak-core' ); ?></h1>
			<p><?php esc_html_e( 'فایل CSV از ردیف‌های فروش بازه انتخابی (سفارش‌های در حال انجام و تکمیل‌شده) برای بارگذاری دستی در کارپوشه، یا تحویل به حسابدار / نرم‌افزار واسط مودیان. ارسال مستقیم به سامانه به گواهی امضای الکترونیکی و شرکت معتمد نیاز دارد و در این نسخه انجام نمی‌شود.', 'yadak-core' ); ?></p>
			<p><?php esc_html_e( 'قبل از استفاده، «شناسه کالا (مودیان)» محصولات و کد اقتصادی/کد ملی خریداران حقوقی را تکمیل کنید.', 'yadak-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yadak_moodian_export">
				<?php wp_nonce_field( 'yadak_moodian' ); ?>
				<label><?php esc_html_e( 'از تاریخ', 'yadak-core' ); ?> <input type="text" name="from" value="<?php echo esc_attr( $from ); ?>" dir="ltr" size="12"></label>
				<label><?php esc_html_e( 'تا تاریخ', 'yadak-core' ); ?> <input type="text" name="to" value="<?php echo esc_attr( yadak_date_input_value( yadak_today() ) ); ?>" dir="ltr" size="12"></label>
				<?php submit_button( __( 'دریافت CSV', 'yadak-core' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function moodian_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_moodian' );
		$from = yadak_parse_date_input( isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '' );
		$to   = yadak_parse_date_input( isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '' );
		if ( ! $from || ! $to ) {
			wp_die( esc_html__( 'تاریخ نامعتبر است. نمونه: 1405/07/01', 'yadak-core' ) );
		}
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'processing', 'completed' ),
				'date_created' => $from . '...' . $to . ' 23:59:59',
				'orderby'      => 'date',
				'order'        => 'ASC',
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=moodian-' . $from . '-' . $to . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows Persian correctly.
		fputcsv(
			$out,
			array( 'شماره صورتحساب', 'تاریخ (شمسی)', 'تاریخ (میلادی)', 'نوع خریدار', 'نام خریدار', 'کد ملی/شناسه ملی', 'کد اقتصادی', 'کد پستی', 'شناسه کالا', 'شرح کالا', 'تعداد', 'مبلغ واحد', 'تخفیف', 'مبلغ پس از تخفیف', 'مالیات ارزش افزوده', 'مبلغ کل' )
		);
		foreach ( $orders as $order ) {
			$user_id  = (int) $order->get_customer_id();
			$eco      = $user_id ? get_user_meta( $user_id, 'yadak_economic_code', true ) : '';
			$nid      = $user_id ? get_user_meta( $user_id, 'yadak_national_id', true ) : '';
			$date     = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '';
			foreach ( $order->get_items() as $item ) {
				$qty = (float) $item->get_quantity();
				fputcsv(
					$out,
					array(
						$order->get_order_number(),
						Yadak_Jalali::from_gregorian( $date ),
						$date,
						$eco ? 'حقوقی/دارای کد اقتصادی' : 'حقیقی',
						$order->get_formatted_billing_full_name() . ( $user_id && get_user_meta( $user_id, 'yadak_company', true ) ? ' — ' . get_user_meta( $user_id, 'yadak_company', true ) : '' ),
						$nid,
						$eco,
						$order->get_billing_postcode(),
						get_post_meta( $item->get_product_id(), '_yadak_moodian_id', true ),
						$item->get_name(),
						$qty,
						$qty ? round( (float) $item->get_subtotal() / $qty ) : 0,
						round( (float) $item->get_subtotal() - (float) $item->get_total() ),
						round( (float) $item->get_total() ),
						round( (float) $item->get_total_tax() ),
						round( (float) $item->get_total() + (float) $item->get_total_tax() ),
					)
				);
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
