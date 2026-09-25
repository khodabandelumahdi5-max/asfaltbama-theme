<?php
/**
 * Part manufacturer brands (Bosch, Denso, Mobis, …) on top of WooCommerce's
 * own `product_brand` taxonomy:
 *
 * - brand fields: Latin name, country, brand type (genuine / OE / aftermarket),
 *   official website; the logo is WooCommerce's brand thumbnail;
 * - brand page header: logo or wordmark, country, type, product count and
 *   links to the brand's categories;
 * - "category + brand" pages, e.g. /brand/bosch/part/brake-pads/ →
 *   «لنت ترمز بوش», with title, description, canonical and sitemap;
 * - [yadak_brands] index grouped by country (layout="strip" for a row);
 * - CollectionPage + Brand schema; empty brand pages are noindex.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Brands {

	const TAX = 'product_brand';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ), 20 );
		add_action( 'product_brand_add_form_fields', array( __CLASS__, 'add_fields' ) );
		add_action( 'product_brand_edit_form_fields', array( __CLASS__, 'edit_fields' ) );
		add_action( 'created_product_brand', array( __CLASS__, 'save_fields' ) );
		add_action( 'edited_product_brand', array( __CLASS__, 'save_fields' ) );
		add_filter( 'manage_edit-product_brand_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_product_brand_custom_column', array( __CLASS__, 'column' ), 10, 3 );

		// Our header replaces WooCommerce's brand description block.
		add_filter( 'pre_option_wc_brands_show_description', array( __CLASS__, 'hide_wc_description' ) );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'header' ), 5 );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'page_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'flush_cache' ) );
		add_shortcode( 'yadak_brands', array( __CLASS__, 'shortcode' ) );
	}

	/* ---------- Data ---------- */

	/**
	 * @return array<string,string> code => Persian name
	 */
	public static function countries() {
		return array(
			'de'    => __( 'آلمان', 'yadak-core' ),
			'jp'    => __( 'ژاپن', 'yadak-core' ),
			'kr'    => __( 'کره جنوبی', 'yadak-core' ),
			'fr'    => __( 'فرانسه', 'yadak-core' ),
			'it'    => __( 'ایتالیا', 'yadak-core' ),
			'es'    => __( 'اسپانیا', 'yadak-core' ),
			'gb'    => __( 'انگلستان', 'yadak-core' ),
			'se'    => __( 'سوئد', 'yadak-core' ),
			'us'    => __( 'آمریکا', 'yadak-core' ),
			'tw'    => __( 'تایوان', 'yadak-core' ),
			'cn'    => __( 'چین', 'yadak-core' ),
			'tr'    => __( 'ترکیه', 'yadak-core' ),
			'in'    => __( 'هند', 'yadak-core' ),
			'ir'    => __( 'ایران', 'yadak-core' ),
			'other' => __( 'سایر', 'yadak-core' ),
		);
	}

	/**
	 * @return array<string,string> type => label
	 */
	public static function types() {
		return array(
			'genuine'     => __( 'قطعه اصلی شرکتی (جنیون)', 'yadak-core' ),
			'oe'          => __( 'سازنده قطعه خط تولید (OE)', 'yadak-core' ),
			'aftermarket' => __( 'افترمارکت', 'yadak-core' ),
		);
	}

	public static function latin( $term ) {
		return (string) get_term_meta( $term->term_id, 'yadak_latin', true );
	}

	/**
	 * "بوش (Bosch)" or just the name.
	 */
	public static function label( $term ) {
		$latin = self::latin( $term );
		return $latin && $latin !== $term->name ? $term->name . ' (' . $latin . ')' : $term->name;
	}

	public static function logo_url( $term, $size = 'medium' ) {
		$id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		return $id ? (string) wp_get_attachment_image_url( $id, $size ) : '';
	}

	/**
	 * Logo image, or a typographic wordmark until a logo is uploaded.
	 */
	public static function logo_html( $term, $size = 'medium' ) {
		$url = self::logo_url( $term, $size );
		if ( $url ) {
			return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( self::label( $term ) ) . '" loading="lazy" decoding="async">';
		}
		$latin = self::latin( $term );
		if ( ! $latin ) {
			// No logo or Latin name: a letter badge, so cards don't repeat the name.
			return '<span class="yadak-monogram" aria-hidden="true">' . esc_html( mb_substr( $term->name, 0, 1 ) ) . '</span>';
		}
		return '<span class="yadak-wordmark" dir="ltr">' . esc_html( $latin ) . '</span>';
	}

	/* ---------- Admin fields ---------- */

	public static function add_fields() {
		?>
		<div class="form-field">
			<label for="yadak_latin"><?php esc_html_e( 'نام لاتین', 'yadak-core' ); ?></label>
			<input type="text" name="yadak_latin" id="yadak_latin" dir="ltr">
			<p><?php esc_html_e( 'مثلاً Bosch. در عنوان صفحه، جستجو و نشان برند (تا وقتی لوگو ندارید) استفاده می‌شود.', 'yadak-core' ); ?></p>
		</div>
		<div class="form-field">
			<label for="yadak_country"><?php esc_html_e( 'کشور برند', 'yadak-core' ); ?></label>
			<?php self::select( 'yadak_country', self::countries(), '' ); ?>
		</div>
		<div class="form-field">
			<label for="yadak_brand_type"><?php esc_html_e( 'نوع برند', 'yadak-core' ); ?></label>
			<?php self::select( 'yadak_brand_type', self::types(), '' ); ?>
		</div>
		<div class="form-field">
			<label for="yadak_website"><?php esc_html_e( 'وب‌سایت رسمی (اختیاری)', 'yadak-core' ); ?></label>
			<input type="url" name="yadak_website" id="yadak_website" dir="ltr" placeholder="https://">
		</div>
		<?php
	}

	public static function edit_fields( $term ) {
		$rows = array(
			'yadak_latin'      => __( 'نام لاتین', 'yadak-core' ),
			'yadak_country'    => __( 'کشور برند', 'yadak-core' ),
			'yadak_brand_type' => __( 'نوع برند', 'yadak-core' ),
			'yadak_website'    => __( 'وب‌سایت رسمی (اختیاری)', 'yadak-core' ),
		);
		foreach ( $rows as $key => $label ) {
			$value = (string) get_term_meta( $term->term_id, $key, true );
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'yadak_country' === $key ) {
				self::select( $key, self::countries(), $value );
			} elseif ( 'yadak_brand_type' === $key ) {
				self::select( $key, self::types(), $value );
			} else {
				echo '<input type="' . ( 'yadak_website' === $key ? 'url' : 'text' ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" dir="ltr">';
			}
			echo '</td></tr>';
		}
	}

	private static function select( $name, $choices, $value ) {
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"><option value="">—</option>';
		foreach ( $choices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function save_fields( $term_id ) {
		// Term screens are nonce-checked by WordPress before these hooks run.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'manage_product_terms' ) || ! isset( $_POST['yadak_latin'] ) ) {
			return;
		}
		update_term_meta( $term_id, 'yadak_latin', sanitize_text_field( wp_unslash( $_POST['yadak_latin'] ) ) );
		$country = sanitize_key( wp_unslash( $_POST['yadak_country'] ?? '' ) );
		update_term_meta( $term_id, 'yadak_country', isset( self::countries()[ $country ] ) ? $country : '' );
		$type = sanitize_key( wp_unslash( $_POST['yadak_brand_type'] ?? '' ) );
		update_term_meta( $term_id, 'yadak_brand_type', isset( self::types()[ $type ] ) ? $type : '' );
		update_term_meta( $term_id, 'yadak_website', esc_url_raw( wp_unslash( $_POST['yadak_website'] ?? '' ) ) );
		// phpcs:enable
	}

	public static function columns( $columns ) {
		$columns['yadak_country'] = __( 'کشور', 'yadak-core' );
		return $columns;
	}

	public static function column( $content, $column, $term_id ) {
		if ( 'yadak_country' === $column ) {
			$country = get_term_meta( $term_id, 'yadak_country', true );
			$content = isset( self::countries()[ $country ] ) ? esc_html( self::countries()[ $country ] ) : '—';
		}
		return $content;
	}

	/* ---------- Brand + category pages ---------- */

	private static function base() {
		$tax = get_taxonomy( self::TAX );
		return $tax && ! empty( $tax->rewrite['slug'] ) ? $tax->rewrite['slug'] : 'brand';
	}

	public static function rewrites() {
		if ( ! taxonomy_exists( self::TAX ) ) {
			return;
		}
		$base = preg_quote( self::base(), '#' );
		add_rewrite_rule( '^' . $base . '/(.+?)/part/([^/]+)/page/([0-9]+)/?$', 'index.php?product_brand=$matches[1]&product_cat=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '^' . $base . '/(.+?)/part/([^/]+)/?$', 'index.php?product_brand=$matches[1]&product_cat=$matches[2]', 'top' );
	}

	public static function url( $brand, $category ) {
		return trailingslashit( get_term_link( $brand ) ) . 'part/' . $category->slug . '/';
	}

	/**
	 * Brand of the current brand or brand + category page.
	 *
	 * @return WP_Term|null
	 */
	public static function queried_brand() {
		if ( ! taxonomy_exists( self::TAX ) || ! is_tax( self::TAX ) ) {
			return null;
		}
		$slug = (string) get_query_var( self::TAX );
		$slug = basename( untrailingslashit( $slug ) ); // Child brands come as parent/child.
		$term = get_term_by( 'slug', $slug, self::TAX );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * [brand, category] on a brand + category page, else null.
	 *
	 * @return WP_Term[]|null
	 */
	public static function combo() {
		$brand = self::queried_brand();
		if ( ! $brand || ! get_query_var( 'product_cat' ) ) {
			return null;
		}
		$category = get_term_by( 'slug', (string) get_query_var( 'product_cat' ), 'product_cat' );
		return $category ? array( $brand, $category ) : null;
	}

	public static function is_brand_page() {
		return (bool) self::queried_brand();
	}

	/**
	 * Categories that have products of a brand.
	 *
	 * @return WP_Term[]
	 */
	public static function categories_for_brand( $brand ) {
		$key = 'yadak_bcats_' . $brand->term_id;
		$ids = get_transient( $key );
		if ( ! is_array( $ids ) ) {
			$products = get_objects_in_term( $brand->term_id, self::TAX );
			$ids      = array();
			if ( ! is_wp_error( $products ) && $products ) {
				$ids = wp_get_object_terms( array_unique( $products ), 'product_cat', array( 'fields' => 'ids' ) );
				$ids = is_wp_error( $ids ) ? array() : array_values( array_unique( array_map( 'intval', $ids ) ) );
			}
			$ids = array_values( array_diff( $ids, array( (int) get_option( 'default_product_cat' ) ) ) );
			set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		}
		return $ids ? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $ids,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		) : array();
	}

	public static function flush_cache( $object_id ) {
		if ( 'product' === get_post_type( $object_id ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_yadak\\_bcats\\_%' OR option_name LIKE '\\_transient\\_timeout\\_yadak\\_bcats\\_%'" );
		}
	}

	/**
	 * URLs of every brand + category page with products (for the sitemap).
	 *
	 * @return string[]
	 */
	public static function sitemap_urls() {
		$urls   = array();
		$brands = taxonomy_exists( self::TAX ) ? get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => true ) ) : array();
		foreach ( is_wp_error( $brands ) ? array() : $brands as $brand ) {
			foreach ( self::categories_for_brand( $brand ) as $cat ) {
				$urls[] = self::url( $brand, $cat );
			}
		}
		return $urls;
	}

	/* ---------- Front end ---------- */

	public static function hide_wc_description( $value ) {
		return 'no';
	}

	/**
	 * «قطعات بوش (Bosch)» / «لنت ترمز بوش».
	 */
	private static function heading() {
		$combo = self::combo();
		if ( $combo ) {
			return yadak_join_name( $combo[1]->name, $combo[0]->name );
		}
		$brand = self::queried_brand();
		/* translators: %s: brand */
		return $brand ? sprintf( __( 'قطعات %s', 'yadak-core' ), self::label( $brand ) ) : '';
	}

	public static function page_title( $title ) {
		$heading = self::heading();
		return $heading ? $heading : $title;
	}

	private static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	public static function document_title( $parts ) {
		$heading = self::heading();
		if ( $heading && ! self::seo_plugin_active() ) {
			$combo = self::combo();
			$latin = $combo ? self::latin( $combo[0] ) : '';
			/* translators: 1: heading, 2: Latin brand name */
			$parts['title'] = $combo && $latin ? sprintf( __( 'خرید %1$s %2$s', 'yadak-core' ), $heading, $latin ) : $heading;
		}
		return $parts;
	}

	private static function canonical() {
		$combo = self::combo();
		$url   = $combo ? self::url( $combo[0], $combo[1] ) : get_term_link( self::queried_brand() );
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		return $paged > 1 ? trailingslashit( $url ) . 'page/' . $paged . '/' : $url;
	}

	private static function description( $brand ) {
		$combo = self::combo();
		if ( ! $combo && $brand->description ) {
			return wp_strip_all_tags( $brand->description );
		}
		/* translators: %s: heading */
		return sprintf( __( 'خرید %s با ضمانت اصالت، شماره فنی و مشخصات کامل، قیمت روز و ارسال فوری به سراسر ایران.', 'yadak-core' ), self::heading() );
	}

	public static function head() {
		$brand = self::queried_brand();
		if ( ! $brand ) {
			return;
		}
		$url   = self::canonical();
		$title = self::heading();
		$desc  = mb_substr( trim( preg_replace( '/\s+/u', ' ', self::description( $brand ) ) ), 0, 160 );
		$logo  = self::logo_url( $brand, 'large' );
		if ( ! self::seo_plugin_active() ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			$tags = array(
				'og:locale'      => 'fa_IR',
				'og:site_name'   => get_bloginfo( 'name' ),
				'og:type'        => 'website',
				'og:title'       => $title,
				'og:description' => $desc,
				'og:url'         => $url,
				'og:image'       => $logo,
			);
			foreach ( array_filter( $tags ) as $property => $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}

		$about = array_filter(
			array(
				'@type'         => 'Brand',
				'name'          => $brand->name,
				'alternateName' => self::latin( $brand ),
				'logo'          => $logo,
				'sameAs'        => (string) get_term_meta( $brand->term_id, 'yadak_website', true ),
			)
		);
		$page  = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'name'        => $title,
			'url'         => $url,
			'description' => $desc,
			'inLanguage'  => 'fa-IR',
			'isPartOf'    => array( '@id' => home_url( '/' ) . '#website' ),
			'about'       => $about,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}

	/**
	 * Empty brand pages are thin content: keep them out of the index.
	 */
	public static function robots( $robots ) {
		$brand = self::queried_brand();
		if ( $brand && ! self::seo_plugin_active() ) {
			global $wp_query;
			if ( ! $wp_query->found_posts ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
				unset( $robots['index'] );
			}
		}
		return $robots;
	}

	/**
	 * Brand page header: logo, facts, intro and category links.
	 */
	public static function header() {
		$brand = self::queried_brand();
		if ( ! $brand ) {
			return;
		}
		$combo   = self::combo();
		$country = get_term_meta( $brand->term_id, 'yadak_country', true );
		$type    = get_term_meta( $brand->term_id, 'yadak_brand_type', true );
		$facts   = array();
		if ( isset( self::countries()[ $country ] ) ) {
			/* translators: %s: country */
			$facts[] = sprintf( __( 'برند %s', 'yadak-core' ), self::countries()[ $country ] );
		}
		if ( isset( self::types()[ $type ] ) ) {
			$facts[] = self::types()[ $type ];
		}
		global $wp_query;
		/* translators: %s: number of products */
		$facts[] = sprintf( __( '%s قطعه', 'yadak-core' ), number_format_i18n( (int) $wp_query->found_posts ) );

		echo '<div class="yadak-brand-hero"><a class="yadak-brand-hero__logo" href="' . esc_url( get_term_link( $brand ) ) . '">' . self::logo_html( $brand ) . '</a><div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<ul class="yadak-brand-hero__facts">';
		foreach ( $facts as $fact ) {
			echo '<li>' . esc_html( $fact ) . '</li>';
		}
		echo '</ul>';
		if ( $combo ) {
			/* translators: %s: brand */
			echo '<p><a href="' . esc_url( get_term_link( $brand ) ) . '">' . esc_html( sprintf( __( 'همه قطعات %s ←', 'yadak-core' ), $brand->name ) ) . '</a></p>';
		} elseif ( ! $brand->description ) {
			/* translators: %s: brand */
			echo '<p>' . esc_html( sprintf( __( 'قطعات %s با شماره فنی دقیق، ضمانت اصالت و انتخاب بر اساس خودرو. برای پیدا کردن قطعه درست، خودرو و سال ساخت را در فیلتر انتخاب کنید.', 'yadak-core' ), self::label( $brand ) ) ) . '</p>';
		}
		echo '</div></div>';

		$cats = self::categories_for_brand( $brand );
		if ( $cats ) {
			echo '<nav class="yadak-cat-links" aria-label="' . esc_attr__( 'دسته‌های این برند', 'yadak-core' ) . '">';
			foreach ( $cats as $cat ) {
				$current = $combo && $combo[1]->term_id === $cat->term_id;
				echo '<a href="' . esc_url( self::url( $brand, $cat ) ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $cat->name ) . '</a>';
			}
			echo '</nav>';
		}
	}

	/**
	 * [yadak_brands] — brands with products, grouped by country.
	 * [yadak_brands layout="strip" limit="12"] — one row, for the home page.
	 */
	public static function shortcode( $atts ) {
		$atts   = shortcode_atts(
			array(
				'layout' => 'grid',
				'limit'  => 0,
			),
			$atts,
			'yadak_brands'
		);
		$brands = taxonomy_exists( self::TAX ) ? get_terms(
			array(
				'taxonomy'   => self::TAX,
				'hide_empty' => true,
				'orderby'    => 'strip' === $atts['layout'] ? 'count' : 'name',
				'order'      => 'strip' === $atts['layout'] ? 'DESC' : 'ASC',
				'number'     => (int) $atts['limit'],
			)
		) : array();
		if ( is_wp_error( $brands ) || ! $brands ) {
			return 'strip' === $atts['layout'] ? '' : '<p class="yadak-brands-empty">' . esc_html__( 'برندها بعد از افزودن محصول اینجا نمایش داده می‌شوند.', 'yadak-core' ) . '</p>';
		}
		$card = static function ( $brand ) {
			return '<li><a class="yadak-brand-card" href="' . esc_url( get_term_link( $brand ) ) . '"><span class="yadak-brand-card__logo">' . self::logo_html( $brand, 'thumbnail' ) . '</span><span class="yadak-brand-card__name">' . esc_html( $brand->name ) . '</span><span class="yadak-brand-card__count">' . esc_html( sprintf( /* translators: %s: count */ __( '%s قطعه', 'yadak-core' ), number_format_i18n( $brand->count ) ) ) . '</span></a></li>';
		};
		if ( 'strip' === $atts['layout'] ) {
			return '<ul class="yadak-brand-grid yadak-brand-grid--strip">' . implode( '', array_map( $card, $brands ) ) . '</ul>';
		}
		$groups = array();
		foreach ( $brands as $brand ) {
			$country = (string) get_term_meta( $brand->term_id, 'yadak_country', true );
			$groups[ isset( self::countries()[ $country ] ) ? $country : 'other' ][] = $brand;
		}
		$out = '';
		foreach ( self::countries() as $code => $name ) {
			if ( empty( $groups[ $code ] ) ) {
				continue;
			}
			/* translators: %s: country */
			$title = 'other' === $code ? __( 'سایر برندها', 'yadak-core' ) : sprintf( __( 'برندهای %s', 'yadak-core' ), $name );
			$out  .= '<section class="yadak-brand-group"><h2>' . esc_html( $title ) . '</h2><ul class="yadak-brand-grid">' . implode( '', array_map( $card, $groups[ $code ] ) ) . '</ul></section>';
		}
		return $out;
	}
}
