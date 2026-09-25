<?php
/**
 * Vehicle fitment: vehicle taxonomy (make › model › variant), the vehicle
 * selector, the "current vehicle" cookie and fit checks on product pages.
 *
 * A product tagged with a vehicle term fits that vehicle and every variant
 * below it: a pad tagged "Peugeot › 206" fits "Peugeot › 206 › TU5".
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Fitment {

	const TAXONOMY = 'yadak_vehicle';
	const COOKIE   = 'yadak_vehicle';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_vehicle_choice' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'include_parent_fitment' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'yadak_vehicle_selector', array( __CLASS__, 'shortcode' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'fit_notice' ), 25 );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'archive_notice' ), 5 );
		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'origin_field_add' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'origin_field_edit' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_origin' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_origin' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( __CLASS__, 'origin_column' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( __CLASS__, 'origin_column_value' ), 10, 3 );
	}

	/**
	 * Countries of origin for car makes. The store specializes in the first
	 * three; the order here is the order in the selector and on the homepage.
	 *
	 * @return array<string,string>
	 */
	public static function origins() {
		return apply_filters(
			'yadak_vehicle_origins',
			array(
				'cn'    => __( 'خودروهای چینی', 'yadak-core' ),
				'jp'    => __( 'خودروهای ژاپنی', 'yadak-core' ),
				'kr'    => __( 'خودروهای کره‌ای', 'yadak-core' ),
				'ir'    => __( 'خودروهای ایرانی', 'yadak-core' ),
				'eu'    => __( 'خودروهای اروپایی', 'yadak-core' ),
				'other' => __( 'سایر', 'yadak-core' ),
			)
		);
	}

	/**
	 * Top-level makes grouped by origin (only groups that have makes).
	 *
	 * @return array<string,WP_Term[]>
	 */
	public static function makes_by_origin() {
		$groups = array_fill_keys( array_keys( self::origins() ), array() );
		$makes  = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'parent'     => 0,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $makes ) ) {
			return array();
		}
		foreach ( $makes as $make ) {
			$origin = get_term_meta( $make->term_id, 'yadak_origin', true );
			$groups[ isset( $groups[ $origin ] ) ? $origin : 'other' ][] = $make;
		}
		return array_filter( $groups );
	}

	private static function origin_select( $value ) {
		echo '<select name="yadak_origin" id="yadak_origin"><option value="">' . esc_html__( '— (فقط برای برند خودرو) —', 'yadak-core' ) . '</option>';
		foreach ( self::origins() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		wp_nonce_field( 'yadak_origin', 'yadak_origin_nonce' );
	}

	public static function origin_field_add() {
		echo '<div class="form-field"><label for="yadak_origin">' . esc_html__( 'کشور سازنده (برای برند خودرو)', 'yadak-core' ) . '</label>';
		self::origin_select( '' );
		echo '<p>' . esc_html__( 'برندها در انتخاب خودرو و صفحه اصلی بر اساس این گروه نمایش داده می‌شوند.', 'yadak-core' ) . '</p></div>';
	}

	public static function origin_field_edit( $term ) {
		if ( $term->parent ) {
			return;
		}
		echo '<tr class="form-field"><th scope="row"><label for="yadak_origin">' . esc_html__( 'کشور سازنده', 'yadak-core' ) . '</label></th><td>';
		self::origin_select( get_term_meta( $term->term_id, 'yadak_origin', true ) );
		echo '</td></tr>';
	}

	public static function save_origin( $term_id ) {
		if ( ! isset( $_POST['yadak_origin'], $_POST['yadak_origin_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_origin_nonce'] ), 'yadak_origin' ) ) {
			return;
		}
		$origin = sanitize_key( $_POST['yadak_origin'] );
		if ( array_key_exists( $origin, self::origins() ) ) {
			update_term_meta( $term_id, 'yadak_origin', $origin );
		} else {
			delete_term_meta( $term_id, 'yadak_origin' );
		}
	}

	public static function origin_column( $columns ) {
		$columns['yadak_origin'] = __( 'کشور', 'yadak-core' );
		return $columns;
	}

	public static function origin_column_value( $value, $column, $term_id ) {
		if ( 'yadak_origin' !== $column ) {
			return $value;
		}
		$origins = self::origins();
		$origin  = get_term_meta( $term_id, 'yadak_origin', true );
		return isset( $origins[ $origin ] ) ? esc_html( $origins[ $origin ] ) : '';
	}

	public static function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array(
				'labels'            => array(
					'name'          => __( 'خودروها', 'yadak-core' ),
					'singular_name' => __( 'خودرو', 'yadak-core' ),
					'menu_name'     => __( 'خودروها (سازگاری)', 'yadak-core' ),
					'all_items'     => __( 'همه خودروها', 'yadak-core' ),
					'edit_item'     => __( 'ویرایش خودرو', 'yadak-core' ),
					'add_new_item'  => __( 'افزودن خودرو', 'yadak-core' ),
					'parent_item'   => __( 'سطح بالاتر (برند یا مدل)', 'yadak-core' ),
					'search_items'  => __( 'جستجوی خودرو', 'yadak-core' ),
					'not_found'     => __( 'خودرویی یافت نشد', 'yadak-core' ),
				),
				'description'       => __( 'سلسله‌مراتب: برند خودرو › مدل › تیپ/موتور/سال', 'yadak-core' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'         => 'cars',
					'hierarchical' => true,
					'with_front'   => false,
				),
			)
		);
	}

	/**
	 * GET /wp-json/yadak/v1/vehicles?parent=ID — children of a vehicle term.
	 */
	public static function register_rest() {
		register_rest_route(
			'yadak/v1',
			'/vehicles',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'parent' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( self::children( (int) $request['parent'] ) );
				},
			)
		);
	}

	/**
	 * @param int $parent Parent term ID (0 = makes).
	 * @return array<int,array{id:int,name:string}>
	 */
	public static function children( $parent ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'parent'     => $parent,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			},
			$terms
		);
	}

	/**
	 * Vehicle of the current archive (also on "vehicle + category" pages,
	 * where the queried object may be the category).
	 *
	 * @return WP_Term|null
	 */
	public static function queried_vehicle() {
		$slug = get_query_var( self::TAXONOMY );
		if ( ! $slug || ! is_string( $slug ) ) {
			$object = get_queried_object();
			return ( $object instanceof WP_Term && self::TAXONOMY === $object->taxonomy ) ? $object : null;
		}
		$term = get_term_by( 'slug', wp_basename( $slug ), self::TAXONOMY );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Currently selected vehicle term, or null.
	 *
	 * @return WP_Term|null
	 */
	public static function current_vehicle() {
		$id = isset( $_COOKIE[ self::COOKIE ] ) ? absint( $_COOKIE[ self::COOKIE ] ) : 0;
		if ( ! $id ) {
			return null;
		}
		$term = get_term( $id, self::TAXONOMY );
		return ( $term instanceof WP_Term ) ? $term : null;
	}

	public static function set_current_vehicle( $term_id ) {
		$term_id = absint( $term_id );
		$expire  = $term_id ? time() + YEAR_IN_SECONDS : time() - HOUR_IN_SECONDS;
		setcookie( self::COOKIE, (string) $term_id, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::COOKIE ] = (string) $term_id;
	}

	/**
	 * "Peugeot › 206 › TU5".
	 *
	 * @param WP_Term|int $term Term.
	 * @return string
	 */
	public static function path( $term ) {
		$term = is_object( $term ) ? $term : get_term( (int) $term, self::TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$names = array( $term->name );
		foreach ( get_ancestors( $term->term_id, self::TAXONOMY, 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, self::TAXONOMY );
			if ( $ancestor instanceof WP_Term ) {
				array_unshift( $names, $ancestor->name );
			}
		}
		return implode( ' › ', $names );
	}

	/**
	 * Handles ?yadak_vehicle=ID from the selector: remembers the choice and
	 * redirects to the vehicle's parts page. ?yadak_vehicle=0 clears it.
	 */
	public static function handle_vehicle_choice() {
		if ( ! isset( $_GET['yadak_vehicle'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		// Use the deepest level the visitor picked.
		$picked = array_filter( array_map( 'absint', (array) wp_unslash( $_GET['yadak_vehicle'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term   = $picked ? get_term( (int) end( $picked ), self::TAXONOMY ) : null;

		if ( ! $term instanceof WP_Term ) {
			self::set_current_vehicle( 0 );
			wp_safe_redirect( remove_query_arg( 'yadak_vehicle' ) );
			exit;
		}

		self::set_current_vehicle( $term->term_id );
		do_action( 'yadak_vehicle_selected', $term );
		wp_safe_redirect( get_term_link( $term ) );
		exit;
	}

	/**
	 * On a vehicle archive, also list parts tagged on its ancestors
	 * (a pad tagged "206" belongs on the "206 › TU5" page).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function include_parent_fitment( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->get( self::TAXONOMY ) ) {
			return;
		}
		$slug = $query->get( self::TAXONOMY );
		$term = is_string( $slug ) ? get_term_by( 'slug', wp_basename( $slug ), self::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$ids     = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, self::TAXONOMY, 'taxonomy' ) );
		$fitment = array(
			'relation' => 'OR',
			array(
				'taxonomy'         => self::TAXONOMY,
				'terms'            => $ids,
				'include_children' => false,
			),
			array(
				'taxonomy'         => self::TAXONOMY,
				'terms'            => array( $term->term_id ),
				'include_children' => true,
			),
		);
		$category = $query->get( 'product_cat' );
		if ( $category ) {
			// "Category + vehicle" page (/cars/…/part/<category>/).
			$fitment = array(
				'relation' => 'AND',
				$fitment,
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( (string) $category ),
				),
			);
		}
		$query->set( 'tax_query', $fitment );
	}

	/**
	 * Is this a vehicle archive (with or without a category)?
	 *
	 * @return bool
	 */
	public static function is_vehicle_archive() {
		return is_archive() && null !== self::queried_vehicle() && '' !== (string) get_query_var( self::TAXONOMY );
	}

	/**
	 * Does a product fit a vehicle?
	 *
	 * @param int $product_id Product.
	 * @param int $term_id    Vehicle term.
	 * @return string "yes", "partial" (fits some variants), "no", or "unknown" (no fitment data).
	 */
	public static function product_fits( $product_id, $term_id ) {
		$assigned = wp_get_object_terms( $product_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $assigned ) || ! $assigned ) {
			return 'unknown';
		}
		$lineage = array_merge( array( (int) $term_id ), get_ancestors( $term_id, self::TAXONOMY, 'taxonomy' ) );
		if ( array_intersect( $assigned, $lineage ) ) {
			return 'yes';
		}
		foreach ( $assigned as $assigned_id ) {
			if ( in_array( (int) $term_id, get_ancestors( $assigned_id, self::TAXONOMY, 'taxonomy' ), true ) ) {
				return 'partial';
			}
		}
		return 'no';
	}

	public static function register_assets() {
		wp_register_script( 'yadak-vehicle-selector', YADAK_CORE_URL . 'assets/vehicle-selector.js', array(), YADAK_CORE_VERSION, true );
		wp_localize_script(
			'yadak-vehicle-selector',
			'yadakVehicles',
			array(
				'endpoint' => esc_url_raw( rest_url( 'yadak/v1/vehicles' ) ),
			)
		);
	}

	/**
	 * [yadak_vehicle_selector] — cascading make › model › variant selects.
	 * Submits ?yadak_vehicle[]=… so it works (make only) even without JS.
	 *
	 * @param array $atts Attributes: title, button.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'  => __( 'قطعه مخصوص خودروی شما', 'yadak-core' ),
				'button' => __( 'نمایش قطعات', 'yadak-core' ),
			),
			$atts,
			'yadak_vehicle_selector'
		);
		wp_enqueue_script( 'yadak-vehicle-selector' );

		$current  = self::current_vehicle();
		$levels   = array(
			__( 'برند خودرو', 'yadak-core' ),
			__( 'مدل', 'yadak-core' ),
			__( 'تیپ / موتور', 'yadak-core' ),
		);
		$selected = array();
		if ( $current ) {
			$selected = array_reverse( get_ancestors( $current->term_id, self::TAXONOMY, 'taxonomy' ) );
			$selected[] = $current->term_id;
		}

		ob_start();
		?>
		<form class="yadak-vs" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-selected="<?php echo esc_attr( wp_json_encode( array_map( 'intval', $selected ) ) ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<p class="yadak-vs__title"><?php echo esc_html( $atts['title'] ); ?></p>
			<?php endif; ?>
			<div class="yadak-vs__fields">
				<?php foreach ( $levels as $i => $label ) : ?>
					<label class="yadak-vs__field">
						<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						<select name="yadak_vehicle[]" data-level="<?php echo esc_attr( $i ); ?>" <?php disabled( $i > 0 ); ?>>
							<option value=""><?php echo esc_html( $label ); ?></option>
							<?php
							if ( 0 === $i ) {
								$groups  = self::makes_by_origin();
								$origins = self::origins();
								foreach ( $groups as $origin => $makes ) {
									if ( count( $groups ) > 1 ) {
										echo '<optgroup label="' . esc_attr( $origins[ $origin ] ) . '">';
									}
									foreach ( $makes as $make ) {
										printf( '<option value="%1$d">%2$s</option>', (int) $make->term_id, esc_html( $make->name ) );
									}
									if ( count( $groups ) > 1 ) {
										echo '</optgroup>';
									}
								}
							}
							?>
						</select>
					</label>
				<?php endforeach; ?>
				<button type="submit" class="yadak-vs__submit"><?php echo esc_html( $atts['button'] ); ?></button>
			</div>
			<?php if ( $current ) : ?>
				<p class="yadak-vs__current">
					<?php
					printf(
						/* translators: %s: vehicle path */
						esc_html__( 'خودروی انتخاب‌شده: %s', 'yadak-core' ),
						'<a href="' . esc_url( get_term_link( $current ) ) . '">' . esc_html( self::path( $current ) ) . '</a>'
					);
					?>
					· <a href="<?php echo esc_url( add_query_arg( 'yadak_vehicle', '0', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'حذف', 'yadak-core' ); ?></a>
				</p>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Fit notice on the product page for the selected vehicle.
	 */
	public static function fit_notice() {
		global $product;
		$vehicle = self::current_vehicle();
		if ( ! $product || ! $vehicle ) {
			return;
		}
		$fit   = self::product_fits( $product->get_id(), $vehicle->term_id );
		$path  = self::path( $vehicle );
		$texts = array(
			/* translators: %s: vehicle */
			'yes'     => __( 'با خودروی شما «%s» سازگار است.', 'yadak-core' ),
			/* translators: %s: vehicle */
			'partial' => __( 'با برخی تیپ‌های «%s» سازگار است؛ تیپ و موتور را در تب «سازگاری» بررسی کنید.', 'yadak-core' ),
			/* translators: %s: vehicle */
			'no'      => __( 'این قطعه برای «%s» ثبت نشده است.', 'yadak-core' ),
			/* translators: %s: vehicle */
			'unknown' => __( 'اطلاعات سازگاری این قطعه با «%s» ثبت نشده؛ قبل از خرید با پشتیبانی تماس بگیرید.', 'yadak-core' ),
		);
		printf(
			'<div class="yadak-fit yadak-fit--%1$s">%2$s</div>',
			esc_attr( $fit ),
			esc_html( sprintf( $texts[ $fit ], $path ) )
		);
	}

	/**
	 * "Compatible vehicles" tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function product_tab( $tabs ) {
		global $product;
		if ( ! $product ) {
			return $tabs;
		}
		$terms = wp_get_object_terms( $product->get_id(), self::TAXONOMY );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return $tabs;
		}
		$tabs['yadak_fitment'] = array(
			'title'    => __( 'خودروهای سازگار', 'yadak-core' ),
			'priority' => 15,
			'callback' => static function () use ( $terms ) {
				echo '<ul class="yadak-fitment-list">';
				foreach ( $terms as $term ) {
					printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( get_term_link( $term ) ), esc_html( self::path( $term ) ) );
				}
				echo '</ul>';
			},
		);
		return $tabs;
	}

	/**
	 * On a vehicle archive, show the full vehicle path and remember it.
	 */
	public static function archive_notice() {
		if ( ! self::is_vehicle_archive() ) {
			return;
		}
		$term = self::queried_vehicle();
		if ( ! $term ) {
			return;
		}
		printf(
			'<p class="yadak-archive-vehicle">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: vehicle path */
					__( 'قطعات سازگار با %s', 'yadak-core' ),
					self::path( $term )
				)
			)
		);
	}
}
