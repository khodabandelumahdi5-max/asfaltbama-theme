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
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_tax( self::TAXONOMY ) ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$ids = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, self::TAXONOMY, 'taxonomy' ) );
		$query->set(
			'tax_query',
			array(
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
			)
		);
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
								foreach ( self::children( 0 ) as $make ) {
									printf( '<option value="%1$d">%2$s</option>', (int) $make['id'], esc_html( $make['name'] ) );
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
		if ( ! is_tax( self::TAXONOMY ) ) {
			return;
		}
		$term = get_queried_object();
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
