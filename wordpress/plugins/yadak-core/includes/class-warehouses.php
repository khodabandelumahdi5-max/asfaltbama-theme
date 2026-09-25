<?php
/**
 * Multi-warehouse stock.
 *
 * Warehouses are defined in settings ("code | name", one per line, in
 * picking priority). Each product keeps `_yadak_wh_stock` = [code => qty]
 * and WooCommerce's own stock is always the sum, so the storefront, cart and
 * reports keep working unchanged. When WooCommerce changes the total (an
 * order, a refund, a CSV import of the stock column), the difference is
 * taken from warehouses in priority order, or added to the first one.
 * Every change is logged in {prefix}yadak_stock_moves.
 *
 * Variable products: per-warehouse stock is kept on simple products; for
 * variations, WooCommerce's single stock is used.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Warehouses {

	const META = '_yadak_wh_stock';

	/** @var bool Set while this class writes stock itself. */
	private static $syncing = false;

	public static function init() {
		if ( ! self::all() ) {
			return;
		}
		add_action( 'woocommerce_product_options_stock_fields', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ), 30 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'reconcile' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yadak_wh_transfer', array( __CLASS__, 'handle_transfer' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'availability' ), 31 );

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'csv_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'csv_defaults' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'csv_import' ), 20, 2 );
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'csv_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'csv_columns' ) );
		foreach ( array_keys( self::all() ) as $code ) {
			add_filter(
				'woocommerce_product_export_product_column_yadak_wh_' . $code,
				static function ( $value, $product ) use ( $code ) {
					$stock = self::stock( $product->get_id() );
					return isset( $stock[ $code ] ) ? $stock[ $code ] : '';
				},
				10,
				2
			);
		}
	}

	/**
	 * @return array<string,string> code => name, in priority order.
	 */
	public static function all() {
		$list = array();
		foreach ( preg_split( '/\r\n|\n/', Yadak_Settings::get( 'warehouses' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$code  = sanitize_key( $parts[0] );
			if ( $code ) {
				$list[ $code ] = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $code;
			}
		}
		return $list;
	}

	/**
	 * @return array<string,float>
	 */
	public static function stock( $product_id ) {
		$stock = get_post_meta( $product_id, self::META, true );
		$out   = array();
		foreach ( array_keys( self::all() ) as $code ) {
			$out[ $code ] = is_array( $stock ) && isset( $stock[ $code ] ) ? (float) $stock[ $code ] : 0.0;
		}
		if ( ! is_array( $stock ) && $out ) {
			// Not split yet: everything is in the first (main) warehouse.
			$out[ array_key_first( $out ) ] = (float) get_post_meta( $product_id, '_stock', true );
		}
		return $out;
	}

	private static function log( $product_id, $warehouse, $qty, $reason, $note = '' ) {
		global $wpdb;
		if ( 0.0 === (float) $qty ) {
			return;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Yadak_Install::table( 'stock_moves' ),
			array(
				'product_id' => (int) $product_id,
				'warehouse'  => $warehouse,
				'qty'        => $qty,
				'reason'     => $reason,
				'note'       => $note,
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Store per-warehouse stock and make WooCommerce's stock the sum.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $stock   code => qty.
	 * @param string     $reason  Log reason.
	 * @param bool       $save    Save the product.
	 */
	public static function set_stock( $product, $stock, $reason, $save = true ) {
		$old = self::stock( $product->get_id() );
		$new = array();
		foreach ( array_keys( self::all() ) as $code ) {
			$new[ $code ] = isset( $stock[ $code ] ) ? (float) $stock[ $code ] : $old[ $code ];
			self::log( $product->get_id(), $code, $new[ $code ] - $old[ $code ], $reason );
		}
		update_post_meta( $product->get_id(), self::META, $new );
		self::$syncing = true;
		$product->set_manage_stock( true );
		$product->set_stock_quantity( array_sum( $new ) );
		if ( $save ) {
			$product->save();
		}
		self::$syncing = false;
	}

	public static function product_fields() {
		global $product_object;
		if ( ! $product_object || $product_object->is_type( 'variable' ) ) {
			return;
		}
		$stock = self::stock( $product_object->get_id() );
		echo '<div class="options_group yadak-wh"><p class="form-field"><strong>' . esc_html__( 'موجودی به تفکیک انبار', 'yadak-core' ) . '</strong><br><span class="description">' . esc_html__( 'جمع این اعداد، موجودی کل محصول می‌شود.', 'yadak-core' ) . '</span></p>';
		foreach ( self::all() as $code => $name ) {
			woocommerce_wp_text_input(
				array(
					'id'                => 'yadak_wh_' . $code,
					'name'              => 'yadak_wh[' . $code . ']',
					'label'             => $name,
					'type'              => 'number',
					'value'             => wc_stock_amount( $stock[ $code ] ),
					'custom_attributes' => array( 'step' => 'any' ),
				)
			);
		}
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function save_product( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		if ( ! isset( $_POST['yadak_wh'] ) || ! is_array( $_POST['yadak_wh'] ) || ! $product->get_manage_stock() || $product->is_type( 'variable' ) ) {
			return;
		}
		$stock = array();
		foreach ( wp_unslash( $_POST['yadak_wh'] ) as $code => $qty ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to float below.
			$stock[ sanitize_key( $code ) ] = (float) wc_format_decimal( yadak_normalize_digits( $qty ) );
		}
		// phpcs:enable
		self::set_stock( $product, $stock, 'manual', false );
	}

	/**
	 * Spread a change of WooCommerce's total stock over the warehouses.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function reconcile( $product ) {
		if ( self::$syncing || ! $product || $product->is_type( 'variation' ) || ! $product->get_manage_stock() ) {
			return;
		}
		$stock = self::stock( $product->get_id() );
		$total = (float) $product->get_stock_quantity();
		$delta = $total - array_sum( $stock );
		if ( abs( $delta ) < 0.0001 ) {
			return;
		}
		$codes  = array_keys( $stock );
		$reason = did_action( 'woocommerce_reduce_order_stock' ) || doing_action( 'woocommerce_payment_complete' ) ? 'order' : 'sync';
		if ( $delta > 0 ) {
			$stock[ $codes[0] ] += $delta;
			self::log( $product->get_id(), $codes[0], $delta, $reason );
		} else {
			$need = -$delta;
			foreach ( $codes as $code ) {
				$take = min( $need, max( 0, $stock[ $code ] ) );
				if ( $take > 0 ) {
					$stock[ $code ] -= $take;
					$need           -= $take;
					self::log( $product->get_id(), $code, -$take, $reason );
				}
			}
			if ( $need > 0 ) { // Oversold: record it on the first warehouse.
				$stock[ $codes[0] ] -= $need;
				self::log( $product->get_id(), $codes[0], -$need, $reason );
			}
		}
		update_post_meta( $product->get_id(), self::META, $stock );
	}

	public static function availability() {
		global $product;
		if ( ! $product || $product->is_type( 'variable' ) || count( self::all() ) < 2 || ! $product->get_manage_stock() ) {
			return;
		}
		$parts = array();
		foreach ( self::stock( $product->get_id() ) as $code => $qty ) {
			if ( $qty > 0 ) {
				$parts[] = self::all()[ $code ];
			}
		}
		if ( $parts ) {
			/* translators: %s: warehouses */
			echo '<p class="yadak-wh-availability">' . esc_html( sprintf( __( 'موجود در: %s', 'yadak-core' ), implode( '، ', $parts ) ) ) . '</p>';
		}
	}

	/* ---------- Admin page ---------- */

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'انبارها', 'yadak-core' ), __( 'انبارها', 'yadak-core' ), 'manage_woocommerce', 'yadak-warehouses', array( __CLASS__, 'render' ), 20 );
	}

	public static function render() {
		global $wpdb;
		$warehouses = self::all();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$low    = ! empty( $_GET['low'] );
		$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		// phpcs:enable
		$args = array(
			'type'         => array( 'simple' ),
			'manage_stock' => true,
			'limit'        => 50,
			'page'         => $paged,
			'paginate'     => true,
			'orderby'      => 'title',
			'order'        => 'ASC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		$result = wc_get_products( $args );

		// Value per warehouse (qty × cost) over all managed products.
		$values = array_fill_keys( array_keys( $warehouses ), 0.0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.post_id, s.meta_value AS wh, c.meta_value AS cost FROM {$wpdb->postmeta} s LEFT JOIN {$wpdb->postmeta} c ON c.post_id = s.post_id AND c.meta_key = '_yadak_cost' WHERE s.meta_key = %s", self::META ) );
		foreach ( $rows as $row ) {
			$stock = maybe_unserialize( $row->wh );
			foreach ( $values as $code => $sum ) {
				if ( is_array( $stock ) && isset( $stock[ $code ] ) ) {
					$values[ $code ] += max( 0, (float) $stock[ $code ] ) * (float) $row->cost;
				}
			}
		}
		$low_default = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'انبارها', 'yadak-core' ); ?></h1>
			<?php
			$messages = array(
				'done'    => array( 'success', __( 'انتقال انجام شد.', 'yadak-core' ) ),
				'short'   => array( 'error', __( 'موجودی انبار مبدأ کافی نیست.', 'yadak-core' ) ),
				'invalid' => array( 'error', __( 'کالا یا انبار نامعتبر است.', 'yadak-core' ) ),
			);
			$msg = isset( $_GET['yadak_msg'] ) ? sanitize_key( $_GET['yadak_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $messages[ $msg ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $messages[ $msg ][0] ) . '"><p>' . esc_html( $messages[ $msg ][1] ) . '</p></div>';
			}
			?>
			<p>
				<?php
				foreach ( $warehouses as $code => $name ) {
					/* translators: 1: warehouse, 2: value */
					echo '<span style="display:inline-block;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:8px 12px;margin:0 0 6px 6px">' . esc_html( sprintf( __( '%1$s — ارزش موجودی (به قیمت خرید): %2$s', 'yadak-core' ), $name, Yadak_SMS::plain_money( $values[ $code ] ) ) ) . '</span>';
				}
				?>
			</p>

			<h2><?php esc_html_e( 'انتقال بین انبارها', 'yadak-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yadak_wh_transfer">
				<?php wp_nonce_field( 'yadak_wh_transfer' ); ?>
				<input type="text" name="product" placeholder="<?php esc_attr_e( 'کد کالا (SKU) یا شناسه محصول', 'yadak-core' ); ?>" dir="ltr" required>
				<select name="from"><?php foreach ( $warehouses as $code => $name ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( __( 'از: ', 'yadak-core' ) . $name ); ?></option><?php endforeach; ?></select>
				<select name="to"><?php foreach ( $warehouses as $code => $name ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, array_keys( $warehouses )[1] ?? '' ); ?>><?php echo esc_html( __( 'به: ', 'yadak-core' ) . $name ); ?></option><?php endforeach; ?></select>
				<input type="number" name="qty" min="1" step="any" placeholder="<?php esc_attr_e( 'تعداد', 'yadak-core' ); ?>" required style="width:90px">
				<input type="text" name="note" placeholder="<?php esc_attr_e( 'توضیح (اختیاری)', 'yadak-core' ); ?>">
				<?php submit_button( __( 'انتقال', 'yadak-core' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'موجودی کالاها', 'yadak-core' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="yadak-warehouses">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'نام یا کد کالا', 'yadak-core' ); ?>">
				<label><input type="checkbox" name="low" value="1" <?php checked( $low ); ?>> <?php esc_html_e( 'فقط کمبودها', 'yadak-core' ); ?></label>
				<?php submit_button( __( 'نمایش', 'yadak-core' ), 'secondary', '', false ); ?>
			</form>
			<table class="widefat striped" style="margin-top:8px">
				<thead><tr><th><?php esc_html_e( 'کالا', 'yadak-core' ); ?></th><th><?php esc_html_e( 'کد', 'yadak-core' ); ?></th>
				<?php foreach ( $warehouses as $name ) : ?><th><?php echo esc_html( $name ); ?></th><?php endforeach; ?>
				<th><?php esc_html_e( 'جمع', 'yadak-core' ); ?></th><th><?php esc_html_e( 'حداقل', 'yadak-core' ); ?></th></tr></thead>
				<tbody>
				<?php
				foreach ( $result->products as $product ) {
					$min   = $product->get_low_stock_amount() ? (int) $product->get_low_stock_amount() : $low_default;
					$total = (float) $product->get_stock_quantity();
					if ( $low && $total > $min ) {
						continue;
					}
					$stock = self::stock( $product->get_id() );
					echo '<tr' . ( $total <= $min ? ' style="background:#fcf0f1"' : '' ) . '><td><a href="' . esc_url( (string) get_edit_post_link( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a></td><td dir="ltr">' . esc_html( $product->get_sku() ) . '</td>';
					foreach ( $stock as $qty ) {
						echo '<td>' . esc_html( wc_stock_amount( $qty ) ) . '</td>';
					}
					echo '<td><strong>' . esc_html( wc_stock_amount( $total ) ) . '</strong></td><td>' . esc_html( $min ) . '</td></tr>';
				}
				?>
				</tbody>
			</table>
			<?php
			if ( $result->max_num_pages > 1 ) {
				echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $paged, 'total' => $result->max_num_pages ) ) ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	public static function handle_transfer() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_wh_transfer' );
		$ref     = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';
		$id      = wc_get_product_id_by_sku( $ref );
		$product = wc_get_product( $id ? $id : absint( $ref ) );
		$from    = isset( $_POST['from'] ) ? sanitize_key( $_POST['from'] ) : '';
		$to      = isset( $_POST['to'] ) ? sanitize_key( $_POST['to'] ) : '';
		$qty     = isset( $_POST['qty'] ) ? (float) wc_format_decimal( yadak_normalize_digits( sanitize_text_field( wp_unslash( $_POST['qty'] ) ) ) ) : 0;
		$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$all     = self::all();
		$back    = admin_url( 'admin.php?page=yadak-warehouses' );

		if ( ! $product || ! isset( $all[ $from ], $all[ $to ] ) || $from === $to || $qty <= 0 ) {
			wp_safe_redirect( add_query_arg( 'yadak_msg', 'invalid', $back ) );
			exit;
		}
		$stock = self::stock( $product->get_id() );
		if ( $stock[ $from ] < $qty ) {
			wp_safe_redirect( add_query_arg( 'yadak_msg', 'short', $back ) );
			exit;
		}
		$stock[ $from ] -= $qty;
		$stock[ $to ]   += $qty;
		update_post_meta( $product->get_id(), self::META, $stock );
		self::log( $product->get_id(), $from, -$qty, 'transfer', $note );
		self::log( $product->get_id(), $to, $qty, 'transfer', $note );
		wp_safe_redirect( add_query_arg( 'yadak_msg', 'done', $back ) );
		exit;
	}

	/* ---------- CSV ---------- */

	private static function csv_label( $name ) {
		/* translators: %s: warehouse */
		return sprintf( __( 'موجودی: %s', 'yadak-core' ), $name );
	}

	public static function csv_options( $options ) {
		foreach ( self::all() as $code => $name ) {
			$options[ 'yadak_wh_' . $code ] = self::csv_label( $name );
		}
		return $options;
	}

	public static function csv_defaults( $mappings ) {
		foreach ( self::all() as $code => $name ) {
			$mappings[ self::csv_label( $name ) ] = 'yadak_wh_' . $code;
		}
		return $mappings;
	}

	public static function csv_columns( $columns ) {
		foreach ( self::all() as $code => $name ) {
			$columns[ 'yadak_wh_' . $code ] = self::csv_label( $name );
		}
		return $columns;
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $data    Row.
	 */
	public static function csv_import( $product, $data ) {
		$stock = array();
		foreach ( array_keys( self::all() ) as $code ) {
			if ( isset( $data[ 'yadak_wh_' . $code ] ) && '' !== $data[ 'yadak_wh_' . $code ] ) {
				$stock[ $code ] = (float) wc_format_decimal( yadak_normalize_digits( $data[ 'yadak_wh_' . $code ] ) );
			}
		}
		if ( $stock && ! $product->is_type( 'variable' ) ) {
			self::set_stock( $product, $stock, 'import' );
		}
	}
}
