<?php
/**
 * Customer account (دفتر حساب مشتری) and B2B credit.
 *
 * Every customer has a ledger in {prefix}yadak_ledger:
 *   charge   — an order bought on credit (debit)
 *   payment  — money received: cash, card, transfer or cheque (credit)
 *   reversal — a credit order that was cancelled/refunded (credit)
 * Balance = charges − payments − reversals. Available credit = limit − balance.
 * The "خرید اعتباری" gateway only appears when the cart fits the available credit.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Credit {

	const DB_VERSION = '1';
	const ENDPOINT   = 'statement';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'yadak_ledger';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				type varchar(16) NOT NULL,
				amount decimal(20,2) NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				method varchar(32) NOT NULL DEFAULT '',
				due_date date DEFAULT NULL,
				note varchar(255) NOT NULL DEFAULT '',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY order_id (order_id)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'yadak_ledger_db', self::DB_VERSION );
	}

	public static function init() {
		if ( get_option( 'yadak_ledger_db' ) !== self::DB_VERSION ) {
			self::install();
		}
		require_once YADAK_CORE_DIR . 'includes/class-credit-gateway.php';
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_reverse' ), 10, 4 );

		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_statement' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'profile_ledger' ), 20 );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_ledger' ), 20 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_payment' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_payment' ) );
	}

	public static function register_gateway( $gateways ) {
		$gateways[] = 'Yadak_Credit_Gateway';
		return $gateways;
	}

	/**
	 * @return array<string,string>
	 */
	public static function payment_methods() {
		return array(
			'cash'     => __( 'نقد', 'yadak-core' ),
			'card'     => __( 'کارت‌به‌کارت / پوز', 'yadak-core' ),
			'transfer' => __( 'حواله بانکی (پایا/ساتنا)', 'yadak-core' ),
			'cheque'   => __( 'چک', 'yadak-core' ),
			'other'    => __( 'سایر', 'yadak-core' ),
		);
	}

	/**
	 * Add a ledger entry.
	 *
	 * @param array $entry user_id, type, amount, order_id, method, due_date, note.
	 * @return int Entry ID.
	 */
	public static function add_entry( $entry ) {
		global $wpdb;
		$entry = wp_parse_args(
			$entry,
			array(
				'order_id' => 0,
				'method'   => '',
				'due_date' => null,
				'note'     => '',
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'user_id'    => (int) $entry['user_id'],
				'type'       => $entry['type'],
				'amount'     => wc_format_decimal( $entry['amount'], 2 ),
				'order_id'   => (int) $entry['order_id'],
				'method'     => $entry['method'],
				'due_date'   => $entry['due_date'] ? $entry['due_date'] : null,
				'note'       => mb_substr( (string) $entry['note'], 0, 255 ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		do_action( 'yadak_ledger_entry_added', (int) $wpdb->insert_id, $entry );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $user_id User.
	 * @return array<object> Oldest first.
	 */
	public static function entries( $user_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at ASC, id ASC", $user_id ) );
	}

	/**
	 * Outstanding balance (positive = customer owes).
	 *
	 * @param int $user_id User.
	 * @return float
	 */
	public static function balance( $user_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CASE WHEN type = 'charge' THEN amount ELSE -amount END) FROM {$table} WHERE user_id = %d", $user_id ) );
		return (float) $sum;
	}

	public static function credit_limit( $user_id ) {
		return (float) get_user_meta( $user_id, 'yadak_credit_limit', true );
	}

	public static function used_credit( $user_id ) {
		return max( 0.0, self::balance( $user_id ) );
	}

	public static function available_credit( $user_id ) {
		return max( 0.0, self::credit_limit( $user_id ) - self::used_credit( $user_id ) );
	}

	/**
	 * A cancelled, refunded or failed credit order gives the credit back once.
	 */
	public static function maybe_reverse( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return;
		}
		if ( 'yes' !== $order->get_meta( '_yadak_credit_charged' ) || 'yes' === $order->get_meta( '_yadak_credit_reversed' ) ) {
			return;
		}
		self::add_entry(
			array(
				'user_id'  => $order->get_customer_id(),
				'type'     => 'reversal',
				'amount'   => $order->get_total(),
				'order_id' => $order->get_id(),
				/* translators: %s: order status */
				'note'     => sprintf( __( 'برگشت سفارش (%s)', 'yadak-core' ), wc_get_order_status_name( $to ) ),
			)
		);
		$order->update_meta_data( '_yadak_credit_reversed', 'yes' );
		$order->save_meta_data();
		$order->add_order_note( __( 'مبلغ سفارش به اعتبار مشتری برگشت داده شد.', 'yadak-core' ) );
	}

	public static function add_endpoints() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function title() {
		return __( 'صورت‌حساب و اعتبار', 'yadak-core' );
	}

	/**
	 * Only customers with a credit limit or ledger entries see the statement.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function has_account( $user_id ) {
		return self::credit_limit( $user_id ) > 0 || self::entries( $user_id );
	}

	public static function menu_item( $items ) {
		if ( ! self::has_account( get_current_user_id() ) ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = self::title();
			}
		}
		return $new;
	}

	/**
	 * Ledger table with a running balance.
	 *
	 * @param int  $user_id User.
	 * @param bool $admin   Link orders to the admin screen.
	 */
	public static function render_ledger( $user_id, $admin = false ) {
		$entries = self::entries( $user_id );
		$methods = self::payment_methods();
		$types   = array(
			'charge'   => __( 'خرید اعتباری', 'yadak-core' ),
			'payment'  => __( 'پرداخت', 'yadak-core' ),
			'reversal' => __( 'برگشت', 'yadak-core' ),
		);
		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'هنوز گردشی ثبت نشده است.', 'yadak-core' ) . '</p>';
			return;
		}
		$running = 0.0;
		echo '<table class="yadak-ledger widefat striped shop_table"><thead><tr>';
		foreach ( array( __( 'تاریخ', 'yadak-core' ), __( 'شرح', 'yadak-core' ), __( 'بدهکار', 'yadak-core' ), __( 'بستانکار', 'yadak-core' ), __( 'مانده', 'yadak-core' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $entries as $e ) {
			$is_debit = 'charge' === $e->type;
			$running += $is_debit ? (float) $e->amount : -(float) $e->amount;
			$desc     = isset( $types[ $e->type ] ) ? $types[ $e->type ] : $e->type;
			if ( $e->method && isset( $methods[ $e->method ] ) ) {
				$desc .= ' — ' . $methods[ $e->method ];
			}
			if ( $e->due_date ) {
				/* translators: %s: cheque due date */
				$desc .= ' — ' . sprintf( __( 'سررسید %s', 'yadak-core' ), wp_date( 'Y/m/d', strtotime( $e->due_date ) ) );
			}
			if ( $e->note ) {
				$desc .= ' — ' . $e->note;
			}
			echo '<tr><td>' . esc_html( wp_date( 'Y/m/d', strtotime( $e->created_at . ' UTC' ) ) ) . '</td><td>' . esc_html( $desc );
			if ( $e->order_id ) {
				if ( $admin ) {
					$order = wc_get_order( (int) $e->order_id );
					$url   = $order ? $order->get_edit_order_url() : '';
				} else {
					$url = wc_get_endpoint_url( 'view-order', (int) $e->order_id, wc_get_page_permalink( 'myaccount' ) );
				}
				/* translators: %d: order number */
				echo ' <a href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'سفارش #%d', 'yadak-core' ), $e->order_id ) ) . '</a>';
			}
			echo '</td><td>' . ( $is_debit ? wp_kses_post( yadak_money( $e->amount ) ) : '' ) . '</td>';
			echo '<td>' . ( $is_debit ? '' : wp_kses_post( yadak_money( $e->amount ) ) ) . '</td>';
			echo '<td>' . wp_kses_post( yadak_money( $running ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Summary boxes: limit, balance, available.
	 *
	 * @param int $user_id User.
	 */
	public static function render_summary( $user_id ) {
		$balance = self::balance( $user_id );
		$cells   = array(
			__( 'سقف اعتبار', 'yadak-core' )       => self::credit_limit( $user_id ),
			__( 'مانده بدهی', 'yadak-core' )        => max( 0, $balance ),
			__( 'اعتبار قابل استفاده', 'yadak-core' ) => self::available_credit( $user_id ),
		);
		if ( $balance < 0 ) {
			$cells[ __( 'بستانکاری شما', 'yadak-core' ) ] = -$balance;
		}
		echo '<div class="yadak-credit-summary">';
		foreach ( $cells as $label => $amount ) {
			echo '<div class="yadak-credit-summary__cell"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses_post( yadak_money( $amount ) ) . '</strong></div>';
		}
		echo '</div>';
	}

	public static function render_statement() {
		$user_id = get_current_user_id();
		self::render_summary( $user_id );
		self::render_ledger( $user_id );
	}

	/**
	 * Ledger and "record payment" form on the user profile screen.
	 *
	 * @param WP_User $user User.
	 */
	public static function profile_ledger( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_nonce_field( 'yadak_payment', 'yadak_payment_nonce' );
		echo '<h2>' . esc_html__( 'حساب مالی مشتری', 'yadak-core' ) . '</h2>';
		self::render_summary( $user->ID );
		self::render_ledger( $user->ID, true );
		?>
		<h3><?php esc_html_e( 'ثبت دریافت از مشتری', 'yadak-core' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="yadak_payment_amount"><?php esc_html_e( 'مبلغ (تومان)', 'yadak-core' ); ?></label></th>
				<td><input type="number" min="0" step="1" name="yadak_payment_amount" id="yadak_payment_amount" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="yadak_payment_method"><?php esc_html_e( 'روش', 'yadak-core' ); ?></label></th>
				<td>
					<select name="yadak_payment_method" id="yadak_payment_method">
						<?php foreach ( self::payment_methods() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="yadak_payment_due"><?php esc_html_e( 'تاریخ سررسید چک (میلادی)', 'yadak-core' ); ?></label></th>
				<td><input type="date" name="yadak_payment_due" id="yadak_payment_due" /></td>
			</tr>
			<tr>
				<th><label for="yadak_payment_note"><?php esc_html_e( 'شرح (شماره چک، بانک، ...)', 'yadak-core' ); ?></label></th>
				<td><input type="text" name="yadak_payment_note" id="yadak_payment_note" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int $user_id User.
	 */
	public static function save_payment( $user_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['yadak_payment_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_payment_nonce'] ), 'yadak_payment' ) ) {
			return;
		}
		$amount = isset( $_POST['yadak_payment_amount'] ) ? (float) wc_format_decimal( yadak_normalize_digits( wp_unslash( $_POST['yadak_payment_amount'] ) ) ) : 0;
		if ( $amount <= 0 ) {
			return;
		}
		$method = isset( $_POST['yadak_payment_method'] ) ? sanitize_key( $_POST['yadak_payment_method'] ) : 'other';
		$due    = isset( $_POST['yadak_payment_due'] ) ? sanitize_text_field( wp_unslash( $_POST['yadak_payment_due'] ) ) : '';
		self::add_entry(
			array(
				'user_id'  => $user_id,
				'type'     => 'payment',
				'amount'   => $amount,
				'method'   => array_key_exists( $method, self::payment_methods() ) ? $method : 'other',
				'due_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : null,
				'note'     => isset( $_POST['yadak_payment_note'] ) ? sanitize_text_field( wp_unslash( $_POST['yadak_payment_note'] ) ) : '',
			)
		);
	}
}
