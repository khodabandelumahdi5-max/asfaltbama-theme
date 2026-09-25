<?php
/**
 * Price history / audit log: every change to a product's retail, sale or
 * tier price is written to {prefix}yadak_price_log with who, when, old and
 * new value — whether it came from the product screen, quick edit, CSV
 * import, REST API or code.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Price_Log {

	const DB_VERSION = '1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'yadak_price_log';
	}

	/**
	 * Meta keys that are logged, with labels.
	 *
	 * @return array<string,string>
	 */
	public static function tracked_keys() {
		$keys = array(
			'_regular_price' => __( 'قیمت عادی', 'yadak-core' ),
			'_sale_price'    => __( 'قیمت حراج', 'yadak-core' ),
		);
		return $keys + Yadak_Pricing::fields();
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				field varchar(64) NOT NULL,
				old_value varchar(64) NOT NULL DEFAULT '',
				new_value varchar(64) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(32) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY product_id (product_id),
				KEY created_at (created_at)
			) {$charset};"
		);
		update_option( 'yadak_price_log_db', self::DB_VERSION );
	}

	public static function init() {
		if ( get_option( 'yadak_price_log_db' ) !== self::DB_VERSION ) {
			self::install();
		}
		add_action( 'update_postmeta', array( __CLASS__, 'before_update' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'after_add' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'meta_box' ) );
	}

	private static function is_tracked( $object_id, $meta_key ) {
		return array_key_exists( $meta_key, self::tracked_keys() )
			&& in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true );
	}

	public static function before_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::is_tracked( $object_id, $meta_key ) ) {
			self::record( $object_id, $meta_key, get_post_meta( $object_id, $meta_key, true ), $meta_value );
		}
	}

	public static function after_add( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::is_tracked( $object_id, $meta_key ) ) {
			self::record( $object_id, $meta_key, '', $meta_value );
		}
	}

	/**
	 * @return string Where the change came from.
	 */
	private static function source() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'api';
		}
		if ( doing_action( 'wp_ajax_woocommerce_do_ajax_product_import' ) ) {
			return 'import';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		return is_admin() ? 'admin' : 'site';
	}

	private static function record( $product_id, $field, $old, $new ) {
		global $wpdb;
		$old = (string) $old;
		$new = is_scalar( $new ) ? (string) $new : '';
		if ( $old === $new || ( is_numeric( $old ) && is_numeric( $new ) && (float) $old === (float) $new ) ) {
			return;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'product_id' => (int) $product_id,
				'field'      => $field,
				'old_value'  => substr( $old, 0, 64 ),
				'new_value'  => substr( $new, 0, 64 ),
				'user_id'    => get_current_user_id(),
				'source'     => self::source(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		do_action( 'yadak_price_changed', (int) $product_id, $field, $old, $new );
	}

	/**
	 * @param array $args product_id, limit.
	 * @return array<object>
	 */
	public static function entries( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'product_id' => 0, 'limit' => 200 ) );
		$table = self::table();
		if ( $args['product_id'] ) {
			$ids          = array_merge( array( (int) $args['product_id'] ), wc_get_product( $args['product_id'] ) ? wc_get_product( $args['product_id'] )->get_children() : array() );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id IN ({$placeholders}) ORDER BY id DESC LIMIT %d", array_merge( $ids, array( (int) $args['limit'] ) ) ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", (int) $args['limit'] ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'تاریخچه قیمت', 'yadak-core' ),
			__( 'تاریخچه قیمت', 'yadak-core' ),
			'manage_woocommerce',
			'yadak-price-log',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function meta_box() {
		add_meta_box(
			'yadak-price-log',
			__( 'تاریخچه قیمت', 'yadak-core' ),
			static function ( $post ) {
				self::render_table( self::entries( array( 'product_id' => $post->ID, 'limit' => 15 ) ), false );
				printf(
					'<p><a href="%s">%s</a></p>',
					esc_url( admin_url( 'edit.php?post_type=product&page=yadak-price-log&product_id=' . $post->ID ) ),
					esc_html__( 'همه تغییرات', 'yadak-core' )
				);
			},
			'product',
			'side',
			'low'
		);
	}

	public static function render_page() {
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'تاریخچه تغییر قیمت', 'yadak-core' ) . '</h1>';
		echo '<form method="get"><input type="hidden" name="post_type" value="product"><input type="hidden" name="page" value="yadak-price-log">';
		echo '<label>' . esc_html__( 'شناسه محصول:', 'yadak-core' ) . ' <input type="number" name="product_id" value="' . esc_attr( $product_id ? $product_id : '' ) . '"></label> ';
		submit_button( __( 'فیلتر', 'yadak-core' ), 'secondary', '', false );
		echo '</form><br>';
		self::render_table( self::entries( array( 'product_id' => $product_id, 'limit' => 500 ) ), true );
		echo '</div>';
	}

	/**
	 * @param array $rows         Entries.
	 * @param bool  $show_product Include the product column.
	 */
	private static function render_table( $rows, $show_product ) {
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'هنوز تغییری ثبت نشده است.', 'yadak-core' ) . '</p>';
			return;
		}
		$labels  = self::tracked_keys();
		$sources = array(
			'admin'  => __( 'پنل', 'yadak-core' ),
			'ajax'   => __( 'ویرایش سریع', 'yadak-core' ),
			'import' => __( 'درون‌ریزی CSV', 'yadak-core' ),
			'api'    => 'API',
			'cli'    => 'CLI',
			'site'   => __( 'سایت', 'yadak-core' ),
		);
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'زمان', 'yadak-core' ) . '</th>';
		if ( $show_product ) {
			echo '<th>' . esc_html__( 'محصول', 'yadak-core' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'فیلد', 'yadak-core' ) . '</th><th>' . esc_html__( 'قبلی', 'yadak-core' ) . '</th><th>' . esc_html__( 'جدید', 'yadak-core' ) . '</th><th>' . esc_html__( 'کاربر', 'yadak-core' ) . '</th>';
		if ( $show_product ) {
			echo '<th>' . esc_html__( 'منبع', 'yadak-core' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$user = $row->user_id ? get_userdata( (int) $row->user_id ) : null;
			echo '<tr><td>' . esc_html( wp_date( 'Y/m/d H:i', strtotime( $row->created_at . ' UTC' ) ) ) . '</td>';
			if ( $show_product ) {
				echo '<td><a href="' . esc_url( (string) get_edit_post_link( (int) $row->product_id ) ? get_edit_post_link( (int) $row->product_id ) : '#' ) . '">' . esc_html( get_the_title( (int) $row->product_id ) ) . '</a></td>';
			}
			echo '<td>' . esc_html( isset( $labels[ $row->field ] ) ? $labels[ $row->field ] : $row->field ) . '</td>';
			echo '<td>' . esc_html( '' === $row->old_value ? '—' : wc_format_localized_price( $row->old_value ) ) . '</td>';
			echo '<td>' . esc_html( '' === $row->new_value ? '—' : wc_format_localized_price( $row->new_value ) ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : __( 'سیستم', 'yadak-core' ) ) . '</td>';
			if ( $show_product ) {
				echo '<td>' . esc_html( isset( $sources[ $row->source ] ) ? $sources[ $row->source ] : $row->source ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
