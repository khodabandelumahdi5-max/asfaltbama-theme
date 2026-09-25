<?php
/**
 * Advanced part filter for shop, category, vehicle and search pages:
 * part number / keyword, category, car origin (Japanese, Chinese, Korean,
 * Iranian), make › model, model year, in stock only.
 *
 * Parameters (GET): s, yf_cat, yf_origin, yf_make, yf_model, yf_year, yf_stock.
 * Filtered listings are marked noindex (see Yadak_SEO) so they don't
 * compete with the clean category and vehicle URLs.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Filter {

	const PARAMS = array( 'yf_cat', 'yf_origin', 'yf_make', 'yf_model', 'yf_year', 'yf_stock' );

	public static function init() {
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'render' ), 5 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'render' ), 5 );
		add_shortcode( 'yadak_filter', array( __CLASS__, 'shortcode' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply' ), 20 );
	}

	/**
	 * Any filter parameter present?
	 */
	public static function active() {
		foreach ( self::PARAMS as $param ) {
			if ( ! empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}
		}
		return false;
	}

	private static function param( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Vehicle terms for a make/model: the term, its ancestors (parts for the
	 * whole make) and its descendants (parts for specific variants).
	 *
	 * @return array Tax query clause.
	 */
	private static function vehicle_clause( $term_id ) {
		return array(
			'relation' => 'OR',
			array(
				'taxonomy'         => Yadak_Fitment::TAXONOMY,
				'terms'            => array_merge( array( $term_id ), get_ancestors( $term_id, Yadak_Fitment::TAXONOMY, 'taxonomy' ) ),
				'include_children' => false,
			),
			array(
				'taxonomy'         => Yadak_Fitment::TAXONOMY,
				'terms'            => array( $term_id ),
				'include_children' => true,
			),
		);
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function apply( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! self::active() ) {
			return;
		}
		$is_products = $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) || ( $query->is_search() && 'product' === $query->get( 'post_type' ) );
		if ( ! $is_products ) {
			return;
		}

		$tax = (array) $query->get( 'tax_query' );
		$cat = sanitize_title( self::param( 'yf_cat' ) );
		if ( $cat ) {
			$tax[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( $cat ),
			);
		}
		$model = absint( self::param( 'yf_model' ) );
		$make  = absint( self::param( 'yf_make' ) );
		if ( $model || $make ) {
			$tax[] = self::vehicle_clause( $model ? $model : $make );
		} elseif ( self::param( 'yf_origin' ) ) {
			$groups = Yadak_Fitment::makes_by_origin();
			$origin = sanitize_key( self::param( 'yf_origin' ) );
			$ids    = isset( $groups[ $origin ] ) ? wp_list_pluck( $groups[ $origin ], 'term_id' ) : array( 0 );
			$tax[]  = array(
				'taxonomy'         => Yadak_Fitment::TAXONOMY,
				'terms'            => $ids,
				'include_children' => true,
			);
		}
		if ( count( $tax ) > 0 ) {
			$tax['relation'] = 'AND';
			$query->set( 'tax_query', $tax );
		}

		$meta = (array) $query->get( 'meta_query' );
		$year = (int) Yadak_Part_Data::normalize_year( self::param( 'yf_year' ) );
		if ( $year ) {
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => '_yadak_year_from', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_yadak_year_from', 'value' => '' ),
				array( 'key' => '_yadak_year_from', 'value' => $year, 'compare' => '<=', 'type' => 'NUMERIC' ),
			);
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => '_yadak_year_to', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_yadak_year_to', 'value' => '' ),
				array( 'key' => '_yadak_year_to', 'value' => $year, 'compare' => '>=', 'type' => 'NUMERIC' ),
			);
		}
		if ( self::param( 'yf_stock' ) ) {
			$meta[] = array(
				'key'   => '_stock_status',
				'value' => 'instock',
			);
		}
		if ( $meta ) {
			$meta['relation'] = 'AND';
			$query->set( 'meta_query', $meta );
		}
	}

	public static function shortcode() {
		ob_start();
		self::render();
		return ob_get_clean();
	}

	public static function render() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$origins  = Yadak_Fitment::origins();
		$groups   = Yadak_Fitment::makes_by_origin();
		$current  = get_queried_object();
		$cat      = self::param( 'yf_cat' ) ? self::param( 'yf_cat' ) : ( $current instanceof WP_Term && 'product_cat' === $current->taxonomy ? $current->slug : '' );
		$make     = absint( self::param( 'yf_make' ) );
		$model    = absint( self::param( 'yf_model' ) );
		$year     = self::param( 'yf_year' );
		$action   = wc_get_page_permalink( 'shop' );
		$cats     = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);
		$by_parent = array();
		foreach ( is_wp_error( $cats ) ? array() : $cats as $term ) {
			$by_parent[ $term->parent ][] = $term;
		}
		$options = static function ( $parent, $depth ) use ( &$options, $by_parent, $cat ) {
			foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $term ) {
				printf( '<option value="%1$s" %2$s>%3$s%4$s</option>', esc_attr( $term->slug ), selected( $cat, $term->slug, false ), esc_html( str_repeat( '— ', $depth ) ), esc_html( $term->name ) );
				$options( $term->term_id, $depth + 1 );
			}
		};
		wp_enqueue_script( 'yadak-vehicle-selector' ); // Provides yadakVehicles.endpoint.
		?>
		<form class="yadak-filter" method="get" action="<?php echo esc_url( $action ); ?>" role="search" aria-label="<?php esc_attr_e( 'فیلتر پیشرفته قطعات', 'yadak-core' ); ?>">
			<input type="hidden" name="post_type" value="product">
			<label class="yadak-filter__field yadak-filter__field--wide">
				<span><?php esc_html_e( 'شماره فنی / نام قطعه', 'yadak-core' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'مثلاً 92101-D3100 یا چراغ جلو', 'yadak-core' ); ?>">
			</label>
			<label class="yadak-filter__field">
				<span><?php esc_html_e( 'دسته', 'yadak-core' ); ?></span>
				<select name="yf_cat"><option value=""><?php esc_html_e( 'همه دسته‌ها', 'yadak-core' ); ?></option><?php $options( 0, 0 ); ?></select>
			</label>
			<label class="yadak-filter__field">
				<span><?php esc_html_e( 'خودروی', 'yadak-core' ); ?></span>
				<select name="yf_origin">
					<option value=""><?php esc_html_e( 'همه', 'yadak-core' ); ?></option>
					<?php foreach ( $groups as $key => $unused ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( self::param( 'yf_origin' ), $key ); ?>><?php echo esc_html( $origins[ $key ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="yadak-filter__field">
				<span><?php esc_html_e( 'برند خودرو', 'yadak-core' ); ?></span>
				<select name="yf_make" data-yf-make>
					<option value=""><?php esc_html_e( 'همه', 'yadak-core' ); ?></option>
					<?php foreach ( $groups as $key => $makes ) : ?>
						<optgroup label="<?php echo esc_attr( $origins[ $key ] ); ?>" data-origin="<?php echo esc_attr( $key ); ?>">
							<?php foreach ( $makes as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $make, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="yadak-filter__field">
				<span><?php esc_html_e( 'مدل', 'yadak-core' ); ?></span>
				<select name="yf_model" data-yf-model <?php disabled( ! $make ); ?>>
					<option value=""><?php esc_html_e( 'همه مدل‌ها', 'yadak-core' ); ?></option>
					<?php
					if ( $make ) {
						foreach ( Yadak_Fitment::children( $make ) as $child ) {
							printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $child['id'], selected( $model, $child['id'], false ), esc_html( $child['name'] ) );
						}
					}
					?>
				</select>
			</label>
			<label class="yadak-filter__field yadak-filter__field--narrow">
				<span><?php esc_html_e( 'سال ساخت', 'yadak-core' ); ?></span>
				<input type="text" name="yf_year" value="<?php echo esc_attr( $year ); ?>" inputmode="numeric" maxlength="4" placeholder="1400 / 2021" dir="ltr">
			</label>
			<label class="yadak-filter__check">
				<input type="checkbox" name="yf_stock" value="1" <?php checked( (bool) self::param( 'yf_stock' ) ); ?>>
				<span><?php esc_html_e( 'فقط موجود', 'yadak-core' ); ?></span>
			</label>
			<div class="yadak-filter__actions">
				<button type="submit" class="button alt"><?php esc_html_e( 'اعمال فیلتر', 'yadak-core' ); ?></button>
				<?php if ( self::active() || get_search_query() ) : ?>
					<a href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'حذف فیلترها', 'yadak-core' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<script>
		( function () {
			var form = document.currentScript.previousElementSibling;
			var make = form.querySelector( '[data-yf-make]' );
			var model = form.querySelector( '[data-yf-model]' );
			var origin = form.querySelector( '[name=yf_origin]' );
			// Drop empty fields so shared URLs stay short.
			form.addEventListener( 'submit', function () {
				Array.prototype.forEach.call( form.elements, function ( el ) {
					if ( el.name && el.name !== 'post_type' && ! el.value && el.type !== 'checkbox' ) { el.disabled = true; }
				} );
			} );
			origin.addEventListener( 'change', function () {
				Array.prototype.forEach.call( make.querySelectorAll( 'optgroup' ), function ( g ) {
					g.hidden = !! origin.value && g.getAttribute( 'data-origin' ) !== origin.value;
				} );
			} );
			make.addEventListener( 'change', function () {
				model.innerHTML = '<option value="">' + model.options[ 0 ].text + '</option>';
				model.disabled = ! make.value;
				if ( ! make.value || ! window.yadakVehicles ) { return; }
				fetch( window.yadakVehicles.endpoint + ( window.yadakVehicles.endpoint.indexOf( '?' ) < 0 ? '?' : '&' ) + 'parent=' + make.value )
					.then( function ( r ) { return r.json(); } )
					.then( function ( items ) {
						items.forEach( function ( it ) {
							var o = document.createElement( 'option' ); o.value = it.id; o.textContent = it.name; model.appendChild( o );
						} );
					} );
			} );
		} )();
		</script>
		<?php
	}
}
