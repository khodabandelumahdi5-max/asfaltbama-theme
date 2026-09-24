<?php
/**
 * Books: one post per book, linked to a pack (WooCommerce product).
 * Keeping books as their own records scales to thousands of books and keeps
 * every book's content separate on the pack page.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Books {

	const CPT = 'bavar_book';

	/**
	 * Editable fields: key => [label, type].
	 *
	 * @return array
	 */
	public static function fields() {
		return [
			'author'    => [ 'نویسنده', 'text' ],
			'topic'     => [ 'موضوع', 'text' ],
			'why'       => [ 'چرا این کتاب انتخاب شده؟', 'textarea' ],
			'summary'   => [ 'خلاصه و محتوای گروه باور', 'textarea' ],
			'checklist' => [ 'چک‌لیست (هر مورد در یک خط)', 'textarea' ],
			'exercise'  => [ 'تمرین', 'textarea' ],
		];
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'meta_boxes' ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'filter_dropdown' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_query' ] );
	}

	/**
	 * Register the post type.
	 */
	public static function register() {
		register_post_type(
			self::CPT,
			[
				'labels'             => [
					'name'               => 'کتاب‌ها',
					'singular_name'      => 'کتاب',
					'add_new'            => 'افزودن کتاب',
					'add_new_item'       => 'افزودن کتاب جدید',
					'edit_item'          => 'ویرایش کتاب',
					'search_items'       => 'جستجوی کتاب',
					'not_found'          => 'کتابی پیدا نشد',
					'featured_image'     => 'تصویر جلد',
					'set_featured_image' => 'انتخاب تصویر جلد',
					'menu_name'          => 'کتاب‌ها',
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'bavar',
				'show_in_rest'       => false,
				'supports'           => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
				'menu_icon'          => 'dashicons-book',
			]
		);
		foreach ( array_keys( self::fields() ) as $key ) {
			register_post_meta( self::CPT, '_bavar_' . $key, [ 'type' => 'string', 'single' => true ] );
		}
		register_post_meta( self::CPT, '_bavar_pack_id', [ 'type' => 'integer', 'single' => true ] );
	}

	/**
	 * All packs for dropdowns.
	 *
	 * @return WP_Post[]
	 */
	public static function packs() {
		return get_posts(
			[
				'post_type'   => 'product',
				'post_status' => [ 'publish', 'draft', 'private' ],
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'meta_key'    => '_bavar_kind', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => 'pack', // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes() {
		add_meta_box( 'bavar_book_fields', 'اطلاعات کتاب', [ __CLASS__, 'render_box' ], self::CPT, 'normal', 'high' );
		add_meta_box( 'bavar_book_pack', 'پک', [ __CLASS__, 'render_pack_box' ], self::CPT, 'side', 'high' );
	}

	/**
	 * Fields box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ) {
		wp_nonce_field( 'bavar_book', 'bavar_book_nonce' );
		echo '<p style="color:#666">توضیح کتاب را در ویرایشگر بالا و تصویر جلد را در «تصویر جلد» وارد کنید.</p>';
		foreach ( self::fields() as $key => $f ) {
			$value = get_post_meta( $post->ID, '_bavar_' . $key, true );
			echo '<p><label for="bavar_' . esc_attr( $key ) . '"><strong>' . esc_html( $f[0] ) . '</strong></label><br>';
			if ( 'textarea' === $f[1] ) {
				echo '<textarea class="widefat" rows="4" id="bavar_' . esc_attr( $key ) . '" name="bavar_' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input class="widefat" type="text" id="bavar_' . esc_attr( $key ) . '" name="bavar_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			echo '</p>';
		}
	}

	/**
	 * Pack select box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_pack_box( $post ) {
		$current = (int) get_post_meta( $post->ID, '_bavar_pack_id', true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $current && isset( $_GET['bavar_pack'] ) ) {
			$current = absint( $_GET['bavar_pack'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		echo '<select name="bavar_pack_id" class="widefat"><option value="0">— انتخاب پک —</option>';
		foreach ( self::packs() as $pack ) {
			printf( '<option value="%d" %s>%s</option>', (int) $pack->ID, selected( $current, $pack->ID, false ), esc_html( $pack->post_title ) );
		}
		echo '</select><p class="description">ترتیب نمایش در پک با «ترتیب» در بخش ویژگی‌ها تعیین می‌شود.</p>';
	}

	/**
	 * Save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['bavar_book_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bavar_book_nonce'] ), 'bavar_book' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( self::fields() as $key => $f ) {
			$raw   = wp_unslash( $_POST[ 'bavar_' . $key ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = 'textarea' === $f[1] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			update_post_meta( $post_id, '_bavar_' . $key, $value );
		}
		$old = (int) get_post_meta( $post_id, '_bavar_pack_id', true );
		$new = absint( $_POST['bavar_pack_id'] ?? 0 );
		update_post_meta( $post_id, '_bavar_pack_id', $new );
		self::flush_count( $old );
		self::flush_count( $new );
	}

	/**
	 * Books of one pack, in display order.
	 *
	 * @param int $pack_id Product ID.
	 * @return WP_Post[]
	 */
	public static function for_pack( $pack_id ) {
		return get_posts(
			[
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => [
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				],
				'meta_key'    => '_bavar_pack_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => (int) $pack_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);
	}

	/**
	 * Cached book count for a pack (shown on library cards).
	 *
	 * @param int $pack_id Product ID.
	 * @return int
	 */
	public static function count( $pack_id ) {
		$manual = (int) get_post_meta( $pack_id, '_bavar_book_count', true );
		if ( $manual ) {
			return $manual;
		}
		$cached = get_transient( 'bavar_books_' . $pack_id );
		if ( false === $cached ) {
			$cached = count( self::for_pack( $pack_id ) );
			set_transient( 'bavar_books_' . $pack_id, $cached, DAY_IN_SECONDS );
		}
		return (int) $cached;
	}

	/**
	 * Clear the cached count.
	 *
	 * @param int $pack_id Product ID.
	 */
	public static function flush_count( $pack_id ) {
		if ( $pack_id ) {
			delete_transient( 'bavar_books_' . $pack_id );
		}
	}

	/**
	 * List columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		$out = [];
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['bavar_author'] = 'نویسنده';
				$out['bavar_pack']   = 'پک';
			}
		}
		return $out;
	}

	/**
	 * Column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column( $col, $post_id ) {
		if ( 'bavar_author' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_bavar_author', true ) );
		} elseif ( 'bavar_pack' === $col ) {
			$pack = (int) get_post_meta( $post_id, '_bavar_pack_id', true );
			if ( $pack ) {
				printf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'bavar_pack', $pack ) ), esc_html( get_the_title( $pack ) ) );
			} else {
				echo '—';
			}
		}
	}

	/**
	 * Pack filter above the books list.
	 *
	 * @param string $post_type Post type.
	 */
	public static function filter_dropdown( $post_type ) {
		if ( self::CPT !== $post_type ) {
			return;
		}
		$current = absint( $_GET['bavar_pack'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="bavar_pack"><option value="0">همه‌ی پک‌ها</option>';
		foreach ( self::packs() as $pack ) {
			printf( '<option value="%d" %s>%s</option>', (int) $pack->ID, selected( $current, $pack->ID, false ), esc_html( $pack->post_title ) );
		}
		echo '</select>';
	}

	/**
	 * Apply the pack filter.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$pack = absint( $_GET['bavar_pack'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pack ) {
			$query->set( 'meta_key', '_bavar_pack_id' );
			$query->set( 'meta_value', $pack );
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}
}
