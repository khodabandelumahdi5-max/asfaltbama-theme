<?php
/**
 * Quotations (پیش‌فاکتور).
 *
 * Staff build a quote for a customer (products, quantities, special prices,
 * discount, validity, payment terms) and send it by SMS/email. The customer
 * sees it in My Account › پیش‌فاکتورها, accepts it, and it becomes a pending
 * order at the quoted prices, ready to pay with any gateway (including credit).
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Quotes {

	const POST_TYPE = 'yadak_quote';
	const ENDPOINT  = 'quotes';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'title' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'endpoint_title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_account' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_customer_action' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'پیش‌فاکتورها', 'yadak-core' ),
					'singular_name' => __( 'پیش‌فاکتور', 'yadak-core' ),
					'add_new'       => __( 'پیش‌فاکتور جدید', 'yadak-core' ),
					'add_new_item'  => __( 'پیش‌فاکتور جدید', 'yadak-core' ),
					'edit_item'     => __( 'ویرایش پیش‌فاکتور', 'yadak-core' ),
					'all_items'     => __( 'پیش‌فاکتورها', 'yadak-core' ),
					'not_found'     => __( 'پیش‌فاکتوری نیست.', 'yadak-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Yadak_Settings::MENU,
				'supports'        => array( 'author' ),
				'capability_type' => 'shop_order',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'draft'    => __( 'پیش‌نویس', 'yadak-core' ),
			'sent'     => __( 'ارسال‌شده، در انتظار مشتری', 'yadak-core' ),
			'accepted' => __( 'تأییدشده (سفارش ساخته شد)', 'yadak-core' ),
			'rejected' => __( 'ردشده', 'yadak-core' ),
			'expired'  => __( 'منقضی', 'yadak-core' ),
		);
	}

	public static function customer_id( $quote_id ) {
		return (int) get_post_meta( $quote_id, '_yq_customer', true );
	}

	/**
	 * Current status; a sent quote past its validity date reads as expired.
	 */
	public static function status( $quote_id ) {
		$status = get_post_meta( $quote_id, '_yq_status', true );
		$status = $status ? $status : 'draft';
		$valid  = get_post_meta( $quote_id, '_yq_valid_until', true );
		if ( 'sent' === $status && $valid && $valid < yadak_today() ) {
			return 'expired';
		}
		return $status;
	}

	/**
	 * @return array<int,array{product_id:int,qty:float,price:float}>
	 */
	public static function items( $quote_id ) {
		$items = get_post_meta( $quote_id, '_yq_items', true );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * @return array{subtotal:float,discount:float,total:float}
	 */
	public static function totals( $quote_id ) {
		$subtotal = 0.0;
		foreach ( self::items( $quote_id ) as $item ) {
			$subtotal += $item['qty'] * $item['price'];
		}
		$discount = min( $subtotal, (float) get_post_meta( $quote_id, '_yq_discount', true ) );
		return array(
			'subtotal' => $subtotal,
			'discount' => $discount,
			'total'    => $subtotal - $discount,
		);
	}

	public static function customer_url( $quote_id ) {
		return wc_get_endpoint_url( self::ENDPOINT, (string) $quote_id, wc_get_page_permalink( 'myaccount' ) );
	}

	/* ---------- Admin ---------- */

	public static function title( $data, $postarr ) {
		if ( self::POST_TYPE !== $data['post_type'] ) {
			return $data;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only builds a title; save() checks the nonce.
		$customer = isset( $_POST['yq_customer'] ) ? absint( $_POST['yq_customer'] ) : ( ! empty( $postarr['ID'] ) ? self::customer_id( $postarr['ID'] ) : 0 );
		$name     = $customer ? Yadak_SMS::user_name( $customer ) : '';
		$data['post_title'] = __( 'پیش‌فاکتور', 'yadak-core' ) . ( $name ? ' — ' . $name : '' );
		if ( 'auto-draft' !== $data['post_status'] && 'trash' !== $data['post_status'] ) {
			$data['post_status'] = 'publish';
		}
		return $data;
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_add_inline_script(
			'wc-enhanced-select',
			"jQuery(function($){
				$('#yq-add').on('click',function(e){e.preventDefault();
					var i=Date.now(), row=$('#yq-template').html().replace(/__i__/g,i);
					$('#yq-items tbody').append(row);
					$('#yq-items tbody tr:last .yq-product').addClass('wc-product-search');
					$(document.body).trigger('wc-enhanced-select-init');
				});
				$('#yq-items').on('click','.yq-remove',function(e){e.preventDefault();$(this).closest('tr').remove();});
			});"
		);
	}

	public static function meta_boxes( $post ) {
		add_meta_box( 'yadak-quote', __( 'اقلام و شرایط پیش‌فاکتور', 'yadak-core' ), array( __CLASS__, 'render_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'yadak-quote-status', __( 'وضعیت', 'yadak-core' ), array( __CLASS__, 'render_status_box' ), self::POST_TYPE, 'side', 'high' );
	}

	private static function product_row( $i, $item = null ) {
		$product = $item ? wc_get_product( $item['product_id'] ) : null;
		?>
		<tr>
			<td style="width:45%">
				<select class="<?php echo '__i__' !== $i ? 'wc-product-search ' : ''; ?>yq-product" style="width:100%" name="yq_items[<?php echo esc_attr( $i ); ?>][product_id]" data-action="woocommerce_json_search_products_and_variations" data-placeholder="<?php esc_attr_e( 'جستجوی محصول…', 'yadak-core' ); ?>">
					<?php if ( $product ) : ?>
						<option value="<?php echo esc_attr( $product->get_id() ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
					<?php endif; ?>
				</select>
			</td>
			<td><input type="number" min="0" step="any" name="yq_items[<?php echo esc_attr( $i ); ?>][qty]" value="<?php echo esc_attr( $item ? $item['qty'] : 1 ); ?>" style="width:80px"></td>
			<td><input type="text" name="yq_items[<?php echo esc_attr( $i ); ?>][price]" value="<?php echo esc_attr( $item ? $item['price'] : '' ); ?>" placeholder="<?php esc_attr_e( 'خودکار', 'yadak-core' ); ?>" dir="ltr" style="width:130px"></td>
			<td><?php echo $item ? wp_kses_post( wc_price( $item['qty'] * $item['price'] ) ) : ''; ?></td>
			<td><a href="#" class="yq-remove" aria-label="<?php esc_attr_e( 'حذف ردیف', 'yadak-core' ); ?>">✕</a></td>
		</tr>
		<?php
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'yadak_quote_save', 'yadak_quote_nonce' );
		$customer = self::customer_id( $post->ID );
		if ( ! $customer && isset( $_GET['yq_customer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$customer = absint( $_GET['yq_customer'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$deal = (int) get_post_meta( $post->ID, '_yq_deal', true );
		if ( ! $deal && isset( $_GET['yq_deal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$deal = absint( $_GET['yq_deal'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		echo '<input type="hidden" name="yq_deal" value="' . esc_attr( $deal ) . '">';
		if ( $deal ) {
			/* translators: %s: deal title */
			echo '<p>' . esc_html( sprintf( __( 'فرصت فروش: %s', 'yadak-core' ), get_the_title( $deal ) ) ) . '</p>';
		}
		$user     = $customer ? get_userdata( $customer ) : null;
		$items    = self::items( $post->ID );
		$totals   = self::totals( $post->ID );
		?>
		<p>
			<label for="yq_customer"><strong><?php esc_html_e( 'مشتری', 'yadak-core' ); ?></strong></label><br>
			<select id="yq_customer" name="yq_customer" class="wc-customer-search" style="width:400px" data-placeholder="<?php esc_attr_e( 'نام، ایمیل یا موبایل مشتری…', 'yadak-core' ); ?>" data-allow_clear="true">
				<?php if ( $user ) : ?>
					<option value="<?php echo esc_attr( $user->ID ); ?>" selected><?php echo esc_html( Yadak_SMS::user_name( $user->ID ) . ' (' . $user->user_email . ')' ); ?></option>
				<?php endif; ?>
			</select>
		</p>
		<table class="widefat" id="yq-items">
			<thead><tr><th><?php esc_html_e( 'محصول', 'yadak-core' ); ?></th><th><?php esc_html_e( 'تعداد', 'yadak-core' ); ?></th><th><?php esc_html_e( 'قیمت واحد', 'yadak-core' ); ?></th><th><?php esc_html_e( 'جمع', 'yadak-core' ); ?></th><th></th></tr></thead>
			<tbody>
				<?php
				foreach ( $items as $i => $item ) {
					self::product_row( $i, $item );
				}
				if ( ! $items ) {
					self::product_row( 0 );
				}
				?>
			</tbody>
		</table>
		<script type="text/html" id="yq-template"><?php self::product_row( '__i__' ); ?></script>
		<p><a href="#" class="button" id="yq-add"><?php esc_html_e( '+ افزودن ردیف', 'yadak-core' ); ?></a>
			<span class="description"><?php esc_html_e( 'قیمت خالی = قیمت فعلی محصول برای سطح قیمت همین مشتری.', 'yadak-core' ); ?></span></p>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'جمع اقلام', 'yadak-core' ); ?></th><td><?php echo wp_kses_post( wc_price( $totals['subtotal'] ) ); ?></td></tr>
			<tr><th><label for="yq_discount"><?php esc_html_e( 'تخفیف (مبلغ)', 'yadak-core' ); ?></label></th><td><input type="text" id="yq_discount" name="yq_discount" value="<?php echo esc_attr( get_post_meta( $post->ID, '_yq_discount', true ) ); ?>" dir="ltr"></td></tr>
			<tr><th><?php esc_html_e( 'مبلغ نهایی', 'yadak-core' ); ?></th><td><strong><?php echo wp_kses_post( wc_price( $totals['total'] ) ); ?></strong></td></tr>
			<tr><th><label for="yq_valid_until"><?php esc_html_e( 'اعتبار تا تاریخ', 'yadak-core' ); ?></label></th><td><input type="text" id="yq_valid_until" name="yq_valid_until" value="<?php echo esc_attr( yadak_date_input_value( get_post_meta( $post->ID, '_yq_valid_until', true ) ? get_post_meta( $post->ID, '_yq_valid_until', true ) : yadak_today( '+3 days' ) ) ); ?>" dir="ltr" placeholder="1405/07/10"><p class="description"><?php esc_html_e( 'با نوسان قیمت، اعتبار کوتاه (۲ تا ۷ روز) توصیه می‌شود.', 'yadak-core' ); ?></p></td></tr>
			<tr><th><label for="yq_terms"><?php esc_html_e( 'شرایط پرداخت', 'yadak-core' ); ?></label></th><td><textarea id="yq_terms" name="yq_terms" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'مثلاً: ۵۰٪ نقد، مابقی چک ۳۰ روزه', 'yadak-core' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, '_yq_terms', true ) ); ?></textarea></td></tr>
			<tr><th><label for="yq_note"><?php esc_html_e( 'توضیح برای مشتری', 'yadak-core' ); ?></label></th><td><textarea id="yq_note" name="yq_note" class="large-text" rows="2"><?php echo esc_textarea( get_post_meta( $post->ID, '_yq_note', true ) ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public static function render_status_box( $post ) {
		$status   = self::status( $post->ID );
		$statuses = self::statuses();
		$order_id = (int) get_post_meta( $post->ID, '_yq_order', true );
		echo '<p><strong>' . esc_html( $statuses[ $status ] ) . '</strong></p>';
		if ( $order_id && ( $order = wc_get_order( $order_id ) ) ) {
			/* translators: %s: order number */
			echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'سفارش #%s', 'yadak-core' ), $order->get_order_number() ) ) . '</a></p>';
		}
		if ( in_array( $status, array( 'draft', 'sent', 'expired' ), true ) ) {
			echo '<p><label><input type="checkbox" name="yq_send" value="1"> ' . esc_html__( 'پس از ذخیره برای مشتری ارسال شود (پیامک + ایمیل)', 'yadak-core' ) . '</label></p>';
		}
		if ( 'auto-draft' !== $post->post_status ) {
			echo '<p><a class="button" target="_blank" href="' . esc_url( Yadak_Print::url( 'quote', $post->ID ) ) . '">' . esc_html__( 'چاپ پیش‌فاکتور', 'yadak-core' ) . '</a></p>';
		}
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['yadak_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_quote_nonce'] ), 'yadak_quote_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$customer = isset( $_POST['yq_customer'] ) ? absint( $_POST['yq_customer'] ) : 0;
		update_post_meta( $post_id, '_yq_customer', $customer );
		if ( ! empty( $_POST['yq_deal'] ) ) {
			update_post_meta( $post_id, '_yq_deal', absint( $_POST['yq_deal'] ) );
			update_post_meta( absint( $_POST['yq_deal'] ), '_yl_quote', $post_id );
		}

		$items = array();
		$rows  = isset( $_POST['yq_items'] ) && is_array( $_POST['yq_items'] ) ? wp_unslash( $_POST['yq_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		foreach ( $rows as $row ) {
			$product = wc_get_product( absint( isset( $row['product_id'] ) ? $row['product_id'] : 0 ) );
			$qty     = (float) wc_format_decimal( yadak_normalize_digits( isset( $row['qty'] ) ? $row['qty'] : 0 ) );
			if ( ! $product || $qty <= 0 ) {
				continue;
			}
			$price   = trim( yadak_normalize_digits( isset( $row['price'] ) ? sanitize_text_field( $row['price'] ) : '' ) );
			$items[] = array(
				'product_id' => $product->get_id(),
				'qty'        => $qty,
				'price'      => '' === $price ? Yadak_Pricing::price_for_user( $product, $customer ) : (float) wc_format_decimal( $price ),
			);
		}
		update_post_meta( $post_id, '_yq_items', $items );
		update_post_meta( $post_id, '_yq_discount', isset( $_POST['yq_discount'] ) ? (float) wc_format_decimal( yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['yq_discount'] ) ) ) ) : 0 );
		update_post_meta( $post_id, '_yq_valid_until', yadak_parse_date_input( isset( $_POST['yq_valid_until'] ) ? sanitize_text_field( wp_unslash( $_POST['yq_valid_until'] ) ) : '' ) );
		update_post_meta( $post_id, '_yq_terms', isset( $_POST['yq_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['yq_terms'] ) ) : '' );
		update_post_meta( $post_id, '_yq_note', isset( $_POST['yq_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['yq_note'] ) ) : '' );
		if ( ! get_post_meta( $post_id, '_yq_status', true ) ) {
			update_post_meta( $post_id, '_yq_status', 'draft' );
		}

		if ( ! empty( $_POST['yq_send'] ) && $customer && $items ) {
			self::send( $post_id );
		}
	}

	/**
	 * Mark sent and notify the customer.
	 */
	public static function send( $quote_id ) {
		$customer = self::customer_id( $quote_id );
		$user     = get_userdata( $customer );
		if ( ! $user ) {
			return;
		}
		update_post_meta( $quote_id, '_yq_status', 'sent' );
		$deal = (int) get_post_meta( $quote_id, '_yq_deal', true );
		if ( $deal ) {
			Yadak_CRM::set_deal_stage( $deal, 'quotation' );
		}
		$totals = self::totals( $quote_id );
		$link   = self::customer_url( $quote_id );
		Yadak_SMS::notify(
			'quote_sent',
			Yadak_SMS::user_mobile( $customer ),
			array(
				'name'  => Yadak_SMS::user_name( $customer ),
				'order' => '#' . $quote_id,
				'total' => Yadak_SMS::plain_money( $totals['total'] ),
				'link'  => $link,
			)
		);
		wp_mail(
			$user->user_email,
			/* translators: %d: quote number */
			sprintf( __( 'پیش‌فاکتور #%d', 'yadak-core' ), $quote_id ),
			sprintf(
				/* translators: 1: name, 2: total, 3: link */
				__( "%1\$s عزیز،\nپیش‌فاکتور شما به مبلغ %2\$s صادر شد.\nمشاهده، چاپ و تأیید: %3\$s", 'yadak-core' ),
				Yadak_SMS::user_name( $customer ),
				Yadak_SMS::plain_money( $totals['total'] ),
				$link
			)
		);
		Yadak_CRM::add_activity( 'user', $customer, 'note', sprintf( /* translators: %d: quote */ __( 'پیش‌فاکتور #%d ارسال شد.', 'yadak-core' ), $quote_id ), '', 0, true );
	}

	public static function columns( $columns ) {
		return array(
			'cb'            => $columns['cb'],
			'title'         => __( 'پیش‌فاکتور', 'yadak-core' ),
			'yq_total'      => __( 'مبلغ', 'yadak-core' ),
			'yq_status'     => __( 'وضعیت', 'yadak-core' ),
			'yq_valid'      => __( 'اعتبار تا', 'yadak-core' ),
			'author'        => __( 'فروشنده', 'yadak-core' ),
			'date'          => $columns['date'],
		);
	}

	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'yq_total':
				echo wp_kses_post( wc_price( self::totals( $post_id )['total'] ) );
				break;
			case 'yq_status':
				echo esc_html( self::statuses()[ self::status( $post_id ) ] );
				break;
			case 'yq_valid':
				echo esc_html( yadak_show_date( get_post_meta( $post_id, '_yq_valid_until', true ) ) );
				break;
		}
	}

	/* ---------- Conversion ---------- */

	/**
	 * Turn an accepted quote into a pending order at the quoted prices.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( $quote_id ) {
		$customer_id = self::customer_id( $quote_id );
		$items       = self::items( $quote_id );
		if ( ! $customer_id || ! $items ) {
			return new WP_Error( 'yadak_quote_empty', __( 'پیش‌فاکتور خالی است.', 'yadak-core' ) );
		}
		$order = wc_create_order(
			array(
				'customer_id' => $customer_id,
				'created_via' => 'yadak_quote',
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		foreach ( $items as $row ) {
			$product = wc_get_product( $row['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$line = new WC_Order_Item_Product();
			$line->set_props(
				array(
					'product'  => $product,
					'quantity' => $row['qty'],
					'subtotal' => $row['qty'] * $row['price'],
					'total'    => $row['qty'] * $row['price'],
				)
			);
			Yadak_Orders::snapshot_cost( $line );
			$order->add_item( $line );
		}
		$totals = self::totals( $quote_id );
		if ( $totals['discount'] > 0 ) {
			$fee = new WC_Order_Item_Fee();
			/* translators: %d: quote number */
			$fee->set_name( sprintf( __( 'تخفیف پیش‌فاکتور #%d', 'yadak-core' ), $quote_id ) );
			$fee->set_amount( -$totals['discount'] );
			$fee->set_total( -$totals['discount'] );
			$fee->set_tax_status( 'none' );
			$order->add_item( $fee );
		}
		$customer = new WC_Customer( $customer_id );
		$order->set_address( $customer->get_billing(), 'billing' );
		$order->set_address( $customer->get_shipping(), 'shipping' );
		$order->update_meta_data( '_yadak_quote_id', $quote_id );
		$deal = (int) get_post_meta( $quote_id, '_yq_deal', true );
		if ( $deal ) {
			$order->update_meta_data( '_yadak_deal', $deal );
			update_post_meta( $deal, '_yl_order', $order->get_id() );
			Yadak_CRM::set_deal_stage( $deal, 'won' );
		}
		$order->calculate_totals();
		/* translators: %d: quote number */
		$order->update_status( 'pending', sprintf( __( 'ساخته‌شده از پیش‌فاکتور #%d', 'yadak-core' ), $quote_id ) );
		$order->save();

		update_post_meta( $quote_id, '_yq_status', 'accepted' );
		update_post_meta( $quote_id, '_yq_order', $order->get_id() );
		/* translators: 1: quote, 2: order */
		Yadak_CRM::add_activity( 'user', $customer_id, 'note', sprintf( __( 'پیش‌فاکتور #%1$d تأیید و به سفارش #%2$s تبدیل شد.', 'yadak-core' ), $quote_id, $order->get_order_number() ), '', 0, true );
		return $order;
	}

	/* ---------- My Account ---------- */

	public static function add_endpoints() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function endpoint_title() {
		return __( 'پیش‌فاکتورها', 'yadak-core' );
	}

	private static function customer_quotes( $user_id ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_yq_customer',
						'value' => $user_id,
					),
					array(
						'key'     => '_yq_status',
						'value'   => 'draft',
						'compare' => '!=',
					),
				),
			)
		);
	}

	public static function menu_item( $items ) {
		if ( ! is_user_logged_in() || ! self::customer_quotes( get_current_user_id() ) ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = self::endpoint_title();
			}
		}
		return $new;
	}

	public static function render_account( $value ) {
		$user_id  = get_current_user_id();
		$statuses = self::statuses();
		$quote_id = absint( $value );

		if ( ! $quote_id ) {
			$quotes = self::customer_quotes( $user_id );
			if ( ! $quotes ) {
				echo '<p>' . esc_html__( 'پیش‌فاکتوری برای شما صادر نشده است.', 'yadak-core' ) . '</p>';
				return;
			}
			echo '<table class="shop_table"><thead><tr><th>' . esc_html__( 'شماره', 'yadak-core' ) . '</th><th>' . esc_html__( 'تاریخ', 'yadak-core' ) . '</th><th>' . esc_html__( 'مبلغ', 'yadak-core' ) . '</th><th>' . esc_html__( 'وضعیت', 'yadak-core' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $quotes as $quote ) {
				printf(
					'<tr><td>#%1$d</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td><a class="button" href="%5$s">%6$s</a></td></tr>',
					(int) $quote->ID,
					esc_html( get_the_date( 'Y/m/d', $quote ) ),
					wp_kses_post( wc_price( self::totals( $quote->ID )['total'] ) ),
					esc_html( $statuses[ self::status( $quote->ID ) ] ),
					esc_url( self::customer_url( $quote->ID ) ),
					esc_html__( 'مشاهده', 'yadak-core' )
				);
			}
			echo '</tbody></table>';
			return;
		}

		if ( self::customer_id( $quote_id ) !== $user_id || self::POST_TYPE !== get_post_type( $quote_id ) || 'draft' === self::status( $quote_id ) ) {
			echo '<p>' . esc_html__( 'پیش‌فاکتور یافت نشد.', 'yadak-core' ) . '</p>';
			return;
		}
		$status = self::status( $quote_id );
		$totals = self::totals( $quote_id );
		/* translators: %d: quote */
		echo '<h3>' . esc_html( sprintf( __( 'پیش‌فاکتور #%d', 'yadak-core' ), $quote_id ) ) . ' — ' . esc_html( $statuses[ $status ] ) . '</h3>';
		echo '<table class="shop_table"><thead><tr><th>' . esc_html__( 'محصول', 'yadak-core' ) . '</th><th>' . esc_html__( 'تعداد', 'yadak-core' ) . '</th><th>' . esc_html__( 'قیمت واحد', 'yadak-core' ) . '</th><th>' . esc_html__( 'جمع', 'yadak-core' ) . '</th></tr></thead><tbody>';
		foreach ( self::items( $quote_id ) as $item ) {
			$product = wc_get_product( $item['product_id'] );
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				$product ? '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>' : '—',
				esc_html( number_format_i18n( $item['qty'] ) ),
				wp_kses_post( wc_price( $item['price'] ) ),
				wp_kses_post( wc_price( $item['qty'] * $item['price'] ) )
			);
		}
		echo '</tbody><tfoot>';
		if ( $totals['discount'] > 0 ) {
			echo '<tr><th colspan="3">' . esc_html__( 'تخفیف', 'yadak-core' ) . '</th><td>' . wp_kses_post( wc_price( -$totals['discount'] ) ) . '</td></tr>';
		}
		echo '<tr><th colspan="3">' . esc_html__( 'مبلغ نهایی', 'yadak-core' ) . '</th><td><strong>' . wp_kses_post( wc_price( $totals['total'] ) ) . '</strong></td></tr></tfoot></table>';

		$valid = get_post_meta( $quote_id, '_yq_valid_until', true );
		if ( $valid ) {
			/* translators: %s: date */
			echo '<p>' . esc_html( sprintf( __( 'اعتبار تا: %s', 'yadak-core' ), yadak_show_date( $valid ) ) ) . '</p>';
		}
		foreach ( array( '_yq_terms' => __( 'شرایط پرداخت', 'yadak-core' ), '_yq_note' => __( 'توضیحات', 'yadak-core' ) ) as $key => $label ) {
			$text = get_post_meta( $quote_id, $key, true );
			if ( $text ) {
				echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . nl2br( esc_html( $text ) ) . '</p>';
			}
		}

		echo '<p class="yadak-quote-actions">';
		if ( 'sent' === $status ) {
			echo '<form method="post" style="display:inline">';
			wp_nonce_field( 'yadak_quote_' . $quote_id );
			echo '<input type="hidden" name="yadak_quote" value="' . esc_attr( $quote_id ) . '">';
			echo '<button type="submit" name="yadak_quote_action" value="accept" class="button alt">' . esc_html__( 'تأیید و ثبت سفارش', 'yadak-core' ) . '</button> ';
			echo '<button type="submit" name="yadak_quote_action" value="reject" class="button">' . esc_html__( 'رد', 'yadak-core' ) . '</button>';
			echo '</form> ';
		}
		$order_id = (int) get_post_meta( $quote_id, '_yq_order', true );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( $order && $order->needs_payment() ) {
			echo '<a class="button alt" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'پرداخت سفارش', 'yadak-core' ) . '</a> ';
		}
		echo '<a class="button" target="_blank" href="' . esc_url( Yadak_Print::url( 'quote', $quote_id ) ) . '">' . esc_html__( 'چاپ', 'yadak-core' ) . '</a></p>';
	}

	public static function handle_customer_action() {
		if ( ! isset( $_POST['yadak_quote'], $_POST['yadak_quote_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$quote_id = absint( $_POST['yadak_quote'] );
		check_admin_referer( 'yadak_quote_' . $quote_id );
		if ( self::customer_id( $quote_id ) !== get_current_user_id() || 'sent' !== self::status( $quote_id ) ) {
			wc_add_notice( __( 'این پیش‌فاکتور قابل تأیید نیست (ممکن است منقضی شده باشد).', 'yadak-core' ), 'error' );
			wp_safe_redirect( self::customer_url( $quote_id ) );
			exit;
		}
		if ( 'reject' === $_POST['yadak_quote_action'] ) {
			update_post_meta( $quote_id, '_yq_status', 'rejected' );
			Yadak_CRM::add_activity( 'user', get_current_user_id(), 'note', sprintf( /* translators: %d: quote */ __( 'مشتری پیش‌فاکتور #%d را رد کرد.', 'yadak-core' ), $quote_id ), '', 0, true );
			wc_add_notice( __( 'پیش‌فاکتور رد شد.', 'yadak-core' ) );
			wp_safe_redirect( self::customer_url( $quote_id ) );
			exit;
		}
		$order = self::create_order( $quote_id );
		if ( is_wp_error( $order ) ) {
			wc_add_notice( $order->get_error_message(), 'error' );
			wp_safe_redirect( self::customer_url( $quote_id ) );
			exit;
		}
		wc_add_notice( __( 'سفارش شما ثبت شد؛ روش پرداخت را انتخاب کنید.', 'yadak-core' ) );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Data for Yadak_Print.
	 */
	public static function document_data( $quote_id ) {
		$customer = self::customer_id( $quote_id );
		$lines    = array();
		foreach ( self::items( $quote_id ) as $item ) {
			$product = wc_get_product( $item['product_id'] );
			$lines[] = array(
				'name'     => $product ? $product->get_name() : '—',
				'code'     => $product ? ( $product->get_meta( '_yadak_part_number' ) ? $product->get_meta( '_yadak_part_number' ) : $product->get_sku() ) : '',
				'qty'      => $item['qty'],
				'unit'     => $item['price'],
				'discount' => 0,
				'tax'      => 0,
				'total'    => $item['qty'] * $item['price'],
			);
		}
		$totals = self::totals( $quote_id );
		$rows   = array( __( 'جمع اقلام', 'yadak-core' ) => $totals['subtotal'] );
		if ( $totals['discount'] > 0 ) {
			$rows[ __( 'تخفیف', 'yadak-core' ) ] = -$totals['discount'];
		}
		$rows[ __( 'مبلغ نهایی', 'yadak-core' ) ] = $totals['total'];
		$notes = trim( implode( "\n", array_filter( array( get_post_meta( $quote_id, '_yq_terms', true ) ? __( 'شرایط پرداخت: ', 'yadak-core' ) . get_post_meta( $quote_id, '_yq_terms', true ) : '', get_post_meta( $quote_id, '_yq_note', true ) ) ) ) );
		$rep   = get_userdata( (int) get_post_field( 'post_author', $quote_id ) );
		return array(
			'title'  => __( 'پیش‌فاکتور', 'yadak-core' ),
			'meta'   => array_filter(
				array(
					__( 'شماره', 'yadak-core' )    => (string) $quote_id,
					__( 'تاریخ', 'yadak-core' )    => get_the_date( 'Y/m/d', $quote_id ),
					__( 'اعتبار تا', 'yadak-core' ) => yadak_show_date( get_post_meta( $quote_id, '_yq_valid_until', true ) ),
					__( 'کارشناس', 'yadak-core' )  => $rep ? $rep->display_name : '',
				)
			),
			'seller' => Yadak_Print::seller(),
			'buyer'  => Yadak_Print::buyer( $customer ),
			'lines'  => $lines,
			'totals' => $rows,
			'notes'  => $notes,
		);
	}
}
