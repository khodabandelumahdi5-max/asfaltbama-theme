<?php
/**
 * Quick bulk editing (فروشگاه یدک › ویرایش سریع):
 *  - a spreadsheet-like table of products and variations to edit price,
 *    sale price, colleague/dealer/cost prices and stock in place; only
 *    changed cells are saved, through WooCommerce, so the price history,
 *    warehouse split and search index stay correct;
 *  - a group price change: ±% for a category and/or brand, on a chosen
 *    price field, with Toman rounding and a dry-run preview.
 *
 * For thousands of rows at once, use Products › Export/Import (CSV).
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Bulk {

	const PER_PAGE = 50;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yadak_bulk_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_yadak_bulk_percent', array( __CLASS__, 'percent' ) );
	}

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'ویرایش سریع قیمت و موجودی', 'yadak-core' ), __( 'ویرایش سریع قیمت و موجودی', 'yadak-core' ), 'edit_products', 'yadak-bulk', array( __CLASS__, 'render' ), 3 );
	}

	/**
	 * Editable columns: key => [label, getter].
	 */
	private static function columns() {
		return array(
			'regular' => __( 'قیمت عادی', 'yadak-core' ),
			'sale'    => __( 'قیمت حراج', 'yadak-core' ),
			'b2b'     => __( 'قیمت همکار', 'yadak-core' ),
			'dealer'  => __( 'قیمت عمده', 'yadak-core' ),
			'cost'    => __( 'قیمت خرید', 'yadak-core' ),
			'stock'   => __( 'موجودی', 'yadak-core' ),
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array<string,string>
	 */
	private static function values( $product ) {
		return array(
			'regular' => (string) $product->get_regular_price( 'edit' ),
			'sale'    => (string) $product->get_sale_price( 'edit' ),
			'b2b'     => (string) $product->get_meta( '_yadak_price_b2b', true, 'edit' ),
			'dealer'  => (string) $product->get_meta( '_yadak_price_dealer', true, 'edit' ),
			'cost'    => (string) $product->get_meta( '_yadak_cost', true, 'edit' ),
			'stock'   => $product->get_manage_stock() ? (string) wc_stock_amount( $product->get_stock_quantity() ) : '',
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $key     Column.
	 * @param string     $value   New value ('' clears).
	 */
	private static function set( $product, $key, $value ) {
		switch ( $key ) {
			case 'regular':
				$product->set_regular_price( $value );
				break;
			case 'sale':
				$product->set_sale_price( $value );
				break;
			case 'b2b':
				$product->update_meta_data( '_yadak_price_b2b', $value );
				break;
			case 'dealer':
				$product->update_meta_data( '_yadak_price_dealer', $value );
				break;
			case 'cost':
				$product->update_meta_data( '_yadak_cost', $value );
				break;
			case 'stock':
				if ( '' !== $value ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( wc_stock_amount( $value ) );
				}
				break;
		}
	}

	private static function query_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'type'     => array( 'simple', 'variation' ),
			'limit'    => self::PER_PAGE,
			'page'     => max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ),
			'paginate' => true,
			'orderby'  => 'title',
			'order'    => 'ASC',
			'status'   => array( 'publish', 'private', 'draft' ),
		);
		if ( ! empty( $_GET['s'] ) ) {
			$sku_id = wc_get_product_id_by_sku( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
			if ( $sku_id ) {
				$args['include'] = array( $sku_id );
			} else {
				$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			}
		}
		if ( ! empty( $_GET['cat'] ) ) {
			$args['category'] = array( sanitize_title( wp_unslash( $_GET['cat'] ) ) );
			$args['type']     = array( 'simple' ); // Variations have no categories of their own.
		}
		if ( ! empty( $_GET['stock'] ) ) {
			$args['stock_status'] = sanitize_key( $_GET['stock'] );
		}
		// phpcs:enable
		return $args;
	}

	private static function category_select( $name, $current ) {
		wp_dropdown_categories(
			array(
				'taxonomy'        => 'product_cat',
				'name'            => $name,
				'value_field'     => 'slug',
				'selected'        => $current,
				'hierarchical'    => true,
				'show_option_all' => __( 'همه دسته‌ها', 'yadak-core' ),
				'hide_empty'      => false,
			)
		);
	}

	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$result  = wc_get_products( self::query_args() );
		$columns = self::columns();
		$msg     = isset( $_GET['yadak_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['yadak_msg'] ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ویرایش سریع قیمت و موجودی', 'yadak-core' ); ?></h1>
			<?php if ( $msg ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'هر خانه را تغییر دهید و «ذخیره تغییرات» را بزنید؛ فقط خانه‌های تغییرکرده ذخیره و در تاریخچه قیمت ثبت می‌شوند. کلید Enter به ردیف پایین می‌رود. برای هزاران کالا از «محصولات › درون‌ریزی» (CSV/Excel) استفاده کنید.', 'yadak-core' ); ?></p>

			<form method="get" style="margin:12px 0">
				<input type="hidden" name="page" value="yadak-bulk">
				<input type="search" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" placeholder="<?php esc_attr_e( 'نام یا کد کالا', 'yadak-core' ); ?>">
				<?php self::category_select( 'cat', isset( $_GET['cat'] ) ? sanitize_title( wp_unslash( $_GET['cat'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<select name="stock">
					<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'yadak-core' ); ?></option>
					<option value="instock" <?php selected( isset( $_GET['stock'] ) && 'instock' === $_GET['stock'] ); // phpcs:ignore ?>><?php esc_html_e( 'موجود', 'yadak-core' ); ?></option>
					<option value="outofstock" <?php selected( isset( $_GET['stock'] ) && 'outofstock' === $_GET['stock'] ); // phpcs:ignore ?>><?php esc_html_e( 'ناموجود', 'yadak-core' ); ?></option>
				</select>
				<?php submit_button( __( 'نمایش', 'yadak-core' ), 'secondary', '', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="yadak-bulk-form">
				<input type="hidden" name="action" value="yadak_bulk_save">
				<input type="hidden" name="back" value="<?php echo esc_attr( remove_query_arg( 'yadak_msg', wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput ?>">
				<?php wp_nonce_field( 'yadak_bulk_save' ); ?>
				<style>
					#yadak-bulk-table input{width:110px;direction:ltr}
					#yadak-bulk-table input.changed{background:#fff8e5;border-color:#dba617}
					#yadak-bulk-table td{vertical-align:middle}
				</style>
				<table class="widefat striped" id="yadak-bulk-table">
					<thead><tr><th><?php esc_html_e( 'کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'کد', 'yadak-core' ); ?></th>
					<?php foreach ( $columns as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead>
					<tbody>
					<?php foreach ( $result->products as $product ) : ?>
						<?php $values = self::values( $product ); ?>
						<tr>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) ); ?>"><?php echo esc_html( wp_strip_all_tags( $product->get_name() ) ); ?></a></td>
							<td dir="ltr"><?php echo esc_html( $product->get_sku() ); ?></td>
							<?php foreach ( $columns as $key => $label ) : ?>
								<td>
									<input type="text" inputmode="decimal" name="rows[<?php echo esc_attr( $product->get_id() ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[ $key ] ); ?>" data-orig="<?php echo esc_attr( $values[ $key ] ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $result->products ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'کالایی یافت نشد.', 'yadak-core' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
				<p><?php submit_button( __( 'ذخیره تغییرات', 'yadak-core' ), 'primary', 'submit', false ); ?> <span id="yadak-bulk-count"></span></p>
			</form>
			<?php
			if ( $result->max_num_pages > 1 ) {
				echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ), 'total' => $result->max_num_pages ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			?>
			<script>
			( function () {
				var form = document.getElementById( 'yadak-bulk-form' );
				var count = document.getElementById( 'yadak-bulk-count' );
				var inputs = form.querySelectorAll( 'input[data-orig]' );
				function update() {
					var n = 0;
					inputs.forEach( function ( el ) { var c = el.value !== el.getAttribute( 'data-orig' ); el.classList.toggle( 'changed', c ); if ( c ) { n++; } } );
					count.textContent = n ? n + ' <?php echo esc_js( __( 'خانه تغییر کرده', 'yadak-core' ) ); ?>' : '';
				}
				form.addEventListener( 'input', update );
				form.addEventListener( 'keydown', function ( e ) {
					if ( e.key !== 'Enter' || e.target.tagName !== 'INPUT' ) { return; }
					e.preventDefault();
					var td = e.target.closest( 'td' ), tr = td.parentElement, next = tr.nextElementSibling;
					if ( next ) { next.children[ td.cellIndex ].querySelector( 'input' ).focus(); }
				} );
				form.addEventListener( 'submit', function () {
					// Send only changed cells.
					inputs.forEach( function ( el ) { if ( el.value === el.getAttribute( 'data-orig' ) ) { el.disabled = true; } } );
				} );
				window.addEventListener( 'beforeunload', function ( e ) { if ( count.textContent && ! form.dataset.sending ) { e.preventDefault(); } } );
				form.addEventListener( 'submit', function () { form.dataset.sending = 1; } );
			} )();
			</script>

			<hr>
			<h2><?php esc_html_e( 'تغییر گروهی قیمت (درصدی)', 'yadak-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'مثلاً با افزایش نرخ ارز: همه چراغ‌های یک برند ۸٪ گران شود. ابتدا «پیش‌نمایش» را بزنید.', 'yadak-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yadak_bulk_percent">
				<?php wp_nonce_field( 'yadak_bulk_percent' ); ?>
				<?php self::category_select( 'p_cat', '' ); ?>
				<?php
				if ( taxonomy_exists( 'product_brand' ) ) {
					wp_dropdown_categories(
						array(
							'taxonomy'        => 'product_brand',
							'name'            => 'p_brand',
							'value_field'     => 'slug',
							'show_option_all' => __( 'همه برندها', 'yadak-core' ),
							'hide_empty'      => false,
						)
					);
				}
				?>
				<select name="p_field">
					<option value="all"><?php esc_html_e( 'همه قیمت‌های فروش (عادی، حراج، همکار، عمده)', 'yadak-core' ); ?></option>
					<?php foreach ( array_slice( $columns, 0, 5, true ) as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="p_percent" placeholder="+8 یا -5" dir="ltr" size="6" required> ٪
				<select name="p_round">
					<option value="1000"><?php esc_html_e( 'گرد به ۱٬۰۰۰ تومان', 'yadak-core' ); ?></option>
					<option value="10000"><?php esc_html_e( 'گرد به ۱۰٬۰۰۰ تومان', 'yadak-core' ); ?></option>
					<option value="1"><?php esc_html_e( 'بدون گرد کردن', 'yadak-core' ); ?></option>
				</select>
				<label><input type="checkbox" name="p_apply" value="1"> <?php esc_html_e( 'اعمال شود (بدون تیک فقط پیش‌نمایش)', 'yadak-core' ); ?></label>
				<?php submit_button( __( 'اجرا', 'yadak-core' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_bulk_save' );
		$rows    = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per cell below.
		$allowed = array_keys( self::columns() );
		$changed = 0;
		foreach ( $rows as $id => $cells ) {
			$product = wc_get_product( absint( $id ) );
			if ( ! $product || ! is_array( $cells ) ) {
				continue;
			}
			foreach ( $cells as $key => $value ) {
				if ( in_array( $key, $allowed, true ) ) {
					self::set( $product, $key, wc_format_decimal( yadak_normalize_digits( sanitize_text_field( $value ) ) ) );
					++$changed;
				}
			}
			$product->save();
		}
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : admin_url( 'admin.php?page=yadak-bulk' );
		/* translators: %d: number of cells */
		wp_safe_redirect( add_query_arg( 'yadak_msg', rawurlencode( sprintf( __( '%d خانه ذخیره شد.', 'yadak-core' ), $changed ) ), $back ) );
		exit;
	}

	public static function percent() {
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_bulk_percent' );
		$percent = (float) str_replace( '+', '', yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['p_percent'] ?? '0' ) ) ) );
		$field   = sanitize_key( wp_unslash( $_POST['p_field'] ?? 'all' ) );
		$round   = max( 1, absint( $_POST['p_round'] ?? 1000 ) );
		$apply   = ! empty( $_POST['p_apply'] );
		$fields  = 'all' === $field ? array( 'regular', 'sale', 'b2b', 'dealer' ) : array( $field );
		$args    = array(
			'type'   => array( 'simple', 'variable' ),
			'limit'  => -1,
			'status' => array( 'publish', 'private', 'draft' ),
		);
		$cat   = sanitize_title( wp_unslash( $_POST['p_cat'] ?? '' ) );
		$brand = sanitize_title( wp_unslash( $_POST['p_brand'] ?? '' ) );
		if ( $cat && '0' !== $cat ) {
			$args['category'] = array( $cat );
		}
		if ( $brand && '0' !== $brand ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_brand',
					'field'    => 'slug',
					'terms'    => $brand,
				),
			);
		}
		if ( ! $percent || ! array_intersect( $fields, array_keys( self::columns() ) ) ) {
			wp_safe_redirect( add_query_arg( 'yadak_msg', rawurlencode( __( 'درصد یا فیلد نامعتبر است.', 'yadak-core' ) ), admin_url( 'admin.php?page=yadak-bulk' ) ) );
			exit;
		}

		$items = array();
		foreach ( wc_get_products( $args ) as $product ) {
			$items = array_merge( $items, $product->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $product->get_children() ) ) : array( $product ) );
		}
		$count = 0;
		$example = '';
		foreach ( $items as $product ) {
			$values  = self::values( $product );
			$touched = false;
			foreach ( $fields as $key ) {
				if ( '' === $values[ $key ] || ! (float) $values[ $key ] ) {
					continue;
				}
				$new = round( (float) $values[ $key ] * ( 1 + $percent / 100 ) / $round ) * $round;
				if ( ! $example ) {
					$example = wp_strip_all_tags( $product->get_name() ) . ': ' . Yadak_SMS::plain_money( $values[ $key ] ) . ' → ' . Yadak_SMS::plain_money( $new );
				}
				if ( $apply ) {
					self::set( $product, $key, (string) $new );
				}
				$touched = true;
			}
			if ( $touched ) {
				++$count;
				if ( $apply ) {
					$product->save();
				}
			}
		}
		$msg = $apply
			/* translators: 1: count, 2: example */
			? sprintf( __( 'قیمت %1$d کالا تغییر کرد. نمونه: %2$s', 'yadak-core' ), $count, $example )
			/* translators: 1: count, 2: example */
			: sprintf( __( 'پیش‌نمایش: %1$d کالا تغییر می‌کند. نمونه: %2$s — برای اعمال، تیک «اعمال شود» را بزنید.', 'yadak-core' ), $count, $example );
		wp_safe_redirect( add_query_arg( 'yadak_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=yadak-bulk' ) ) );
		exit;
	}
}
