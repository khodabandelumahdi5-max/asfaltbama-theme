<?php
/**
 * BAVAR LIBRARY: pack grid with search, topic filter and pagination.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Library {

	const PER_PAGE = 24;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'bavar_library', [ __CLASS__, 'shortcode_library' ] );
		add_shortcode( 'bavar_featured_packs', [ __CLASS__, 'shortcode_featured' ] );
	}

	/**
	 * Base query args for packs.
	 *
	 * @return array
	 */
	private static function base_args() {
		return [
			'post_type'   => 'product',
			'post_status' => 'publish',
			'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'   => '_bavar_kind',
					'value' => 'pack',
				],
			],
		];
	}

	/**
	 * One pack card.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function card( $product ) {
		$id    = $product->get_id();
		$count = Bavar_Books::count( $id );
		$topic = get_post_meta( $id, '_bavar_topic', true );
		ob_start();
		?>
		<a class="bv-pack" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<span class="bv-pack__img">
				<?php
				if ( $product->get_image_id() ) {
					echo wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, [ 'loading' => 'lazy' ] );
				} else {
					echo '<span class="bv-pack__ph">پک کتاب<br>گروه باور</span>';
				}
				?>
			</span>
			<span class="bv-pack__meta">
				<?php if ( $topic ) : ?>
					<span class="bv-pack__topic"><?php echo esc_html( $topic ); ?></span>
				<?php endif; ?>
				<?php if ( $count ) : ?>
					<span class="bv-pack__count"><?php echo esc_html( bavar_fa_num( $count ) ); ?> کتاب</span>
				<?php endif; ?>
			</span>
			<span class="bv-pack__title"><?php echo esc_html( $product->get_name() ); ?></span>
			<span class="bv-pack__desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 18 ) ); ?></span>
			<span class="bv-pack__foot">
				<span class="bv-pack__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				<span class="bv-pack__cta">مشاهده پک</span>
			</span>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * [bavar_library]
	 *
	 * @return string
	 */
	public static function shortcode_library() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$topic  = sanitize_title( wp_unslash( $_GET['topic'] ?? '' ) );
		$paged  = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable

		$args = self::base_args() + [
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => [
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			],
		];
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( $topic ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $topic,
				],
			];
		}
		$query = new WP_Query( $args );

		// Topics = product categories that contain at least one pack.
		$pack_ids = get_posts( self::base_args() + [ 'numberposts' => -1, 'fields' => 'ids' ] );
		$topics   = $pack_ids ? wp_get_object_terms( $pack_ids, 'product_cat', [ 'orderby' => 'name' ] ) : [];
		$topics   = is_wp_error( $topics ) ? [] : array_filter( $topics, fn( $t ) => 'uncategorized' !== $t->slug );

		$base = get_permalink();
		ob_start();
		?>
		<div class="bv-library">
			<form class="bv-library__filters" method="get" action="<?php echo esc_url( $base ); ?>">
				<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی پک یا موضوع…" aria-label="جستجو">
				<?php if ( $topics ) : ?>
					<select name="topic" aria-label="موضوع" onchange="this.form.submit()">
						<option value="">همه‌ی موضوعات</option>
						<?php foreach ( $topics as $t ) : ?>
							<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $topic, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="submit" class="bv-button">جستجو</button>
			</form>

			<p class="bv-library__count"><?php echo esc_html( bavar_fa_num( $query->found_posts ) ); ?> پک</p>

			<?php if ( $query->have_posts() ) : ?>
				<div class="bv-packs">
					<?php
					foreach ( $query->posts as $post ) {
						$product = wc_get_product( $post );
						if ( $product ) {
							echo self::card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					?>
				</div>
				<?php
				if ( $query->max_num_pages > 1 ) {
					echo '<nav class="bv-pagination" aria-label="صفحه‌ها">';
					for ( $i = 1; $i <= $query->max_num_pages; $i++ ) {
						$url = add_query_arg( array_filter( [ 'q' => $search, 'topic' => $topic, 'pg' => $i > 1 ? $i : null ] ), $base );
						printf( '<a href="%s" %s>%s</a>', esc_url( $url ), $i === $paged ? 'aria-current="page"' : '', esc_html( bavar_fa_num( $i ) ) );
					}
					echo '</nav>';
				}
				?>
			<?php else : ?>
				<p class="bv-empty">پکی با این مشخصات پیدا نشد.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [bavar_featured_packs count="3"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_featured( $atts ) {
		$atts  = shortcode_atts( [ 'count' => 3 ], $atts );
		$ids   = wc_get_featured_product_ids();
		$args  = self::base_args() + [
			'posts_per_page' => absint( $atts['count'] ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];
		if ( $ids ) {
			$featured = new WP_Query( $args + [ 'post__in' => $ids ] );
			$query    = $featured->have_posts() ? $featured : new WP_Query( $args );
		} else {
			$query = new WP_Query( $args );
		}
		if ( ! $query->have_posts() ) {
			return '';
		}
		$html = '<div class="bv-packs bv-packs--featured">';
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post );
			if ( $product ) {
				$html .= self::card( $product );
			}
		}
		return $html . '</div>';
	}
}
