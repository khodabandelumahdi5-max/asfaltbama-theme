<?php
/**
 * BAVAR fields on WooCommerce products (course / pack) and buying behaviour.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Products {

	/**
	 * Course page sections: key => heading.
	 *
	 * @return array
	 */
	public static function course_sections() {
		return [
			'what'     => 'دوره چیست؟',
			'who'      => 'برای چه کسانی است؟',
			'topics'   => 'چه موضوعاتی دارد؟',
			'receive'  => 'چه چیزهایی دریافت می‌کنید؟',
			'duration' => 'مدت و حجم محتوا',
			'access'   => 'نحوه‌ی دسترسی',
			'terms'    => 'شرایط استفاده',
			'support'  => 'پشتیبانی',
		];
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_product', [ __CLASS__, 'meta_box' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save' ] );
		add_filter( 'woocommerce_is_sold_individually', [ __CLASS__, 'sold_individually' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', [ __CLASS__, 'to_checkout' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'block_rebuy' ], 10, 2 );
		add_action( 'woocommerce_process_product_file_download_paths', [ __CLASS__, 'grant_new_files' ], 10, 3 );
		add_filter( 'wc_price', [ __CLASS__, 'persian_price' ], 20 );

		// SEO (skipped when a dedicated SEO plugin is active).
		if ( ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) ) {
			add_filter( 'pre_get_document_title', [ __CLASS__, 'seo_title' ], 20 );
			add_action( 'wp_head', [ __CLASS__, 'seo_head' ], 2 );
			add_filter( 'wp_robots', [ __CLASS__, 'seo_robots' ] );
		}
	}

	/**
	 * Meta box.
	 */
	public static function meta_box() {
		add_meta_box( 'bavar_product', 'BAVAR — نوع محصول و محتوا', [ __CLASS__, 'render' ], 'product', 'normal', 'high' );
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		$kind = bavar_product_kind( $post->ID );
		$get  = function ( $key ) use ( $post ) {
			return get_post_meta( $post->ID, '_bavar_' . $key, true );
		};
		wp_nonce_field( 'bavar_product', 'bavar_product_nonce' );
		?>
		<style>
			.bavar-box p{margin:0 0 14px}.bavar-box label{display:block;font-weight:600;margin-bottom:4px}
			.bavar-box [data-kind]{display:none}.bavar-box[data-current="course"] [data-kind~="course"],.bavar-box[data-current="pack"] [data-kind~="pack"]{display:block}
			.bavar-books{border:1px solid #ddd;max-height:320px;overflow:auto;margin:0 0 10px;padding:0}.bavar-books li{display:flex;justify-content:space-between;padding:8px 12px;margin:0;border-bottom:1px solid #eee}
		</style>
		<div class="bavar-box" data-current="<?php echo esc_attr( $kind ); ?>">
			<p>
				<label for="bavar_kind">نوع محصول</label>
				<select id="bavar_kind" name="bavar_kind" onchange="this.closest('.bavar-box').dataset.current=this.value">
					<option value="">محصول عادی ووکامرس</option>
					<option value="course" <?php selected( $kind, 'course' ); ?>>دوره (مثل کتاب مقدس گروه باور)</option>
					<option value="pack" <?php selected( $kind, 'pack' ); ?>>پک کتابخانه‌ی باور</option>
				</select>
			</p>
			<p data-kind="course pack">
				<label for="bavar_subtitle">زیرعنوان</label>
				<input class="widefat" id="bavar_subtitle" name="bavar_subtitle" value="<?php echo esc_attr( $get( 'subtitle' ) ); ?>" placeholder="مثلاً: دوره جامع غیرحضوری گروه باور">
			</p>
			<p data-kind="pack">
				<label for="bavar_topic">موضوع پک</label>
				<input class="widefat" id="bavar_topic" name="bavar_topic" value="<?php echo esc_attr( $get( 'topic' ) ); ?>" placeholder="مثلاً: فروش و مذاکره">
			</p>
			<p data-kind="course pack">
				<label for="bavar_includes">محتوای قابل دریافت (هر مورد در یک خط)</label>
				<textarea class="widefat" rows="5" id="bavar_includes" name="bavar_includes" placeholder="فایل صوتی&#10;ویدیو&#10;متن و PDF&#10;تمرین&#10;محتوای کاربردی"><?php echo esc_textarea( $get( 'includes' ) ); ?></textarea>
			</p>
			<p data-kind="course pack">
				<label for="bavar_access_terms">شرایط دسترسی (زیر دکمه‌ی خرید نمایش داده می‌شود)</label>
				<textarea class="widefat" rows="3" id="bavar_access_terms" name="bavar_access_terms"><?php echo esc_textarea( $get( 'access_terms' ) ); ?></textarea>
			</p>

			<p data-kind="course pack">
				<label for="bavar_benefits">مزایا (هر مورد در یک خط)</label>
				<textarea class="widefat" rows="4" id="bavar_benefits" name="bavar_benefits"><?php echo esc_textarea( $get( 'benefits' ) ); ?></textarea>
			</p>
			<p data-kind="course pack">
				<label for="bavar_faq">سؤالات متداول</label>
				<textarea class="widefat" rows="6" id="bavar_faq" name="bavar_faq" placeholder="سؤال اول؟&#10;پاسخ سؤال اول&#10;&#10;سؤال دوم؟&#10;پاسخ سؤال دوم"><?php echo esc_textarea( $get( 'faq' ) ); ?></textarea>
				<span class="description">هر سؤال در یک خط و پاسخش در خط‌های بعد؛ بین دو سؤال یک خط خالی بگذارید.</span>
			</p>
			<p data-kind="course pack">
				<label for="bavar_buy_url">لینک خرید دلخواه (اختیاری)</label>
				<input class="widefat" dir="ltr" type="url" id="bavar_buy_url" name="bavar_buy_url" value="<?php echo esc_attr( $get( 'buy_url' ) ); ?>" placeholder="https://">
				<span class="description">خالی بگذارید تا خرید از همین سایت انجام شود.</span>
			</p>
			<p data-kind="course pack">
				<label><input type="checkbox" name="bavar_grant_existing" value="yes" <?php checked( 'no' !== $get( 'grant_existing' ) ); ?>> فایل‌هایی که بعداً به این محصول اضافه می‌کنم (PDF، صوت، ویدیو…) برای خریداران قبلی هم در دسترس قرار بگیرد</label>
			</p>

			<div data-kind="course pack">
				<h4>سئو</h4>
				<p>
					<label for="bavar_seo_title">عنوان سئو</label>
					<input class="widefat" id="bavar_seo_title" name="bavar_seo_title" value="<?php echo esc_attr( $get( 'seo_title' ) ); ?>">
				</p>
				<p>
					<label for="bavar_seo_desc">توضیحات متا</label>
					<textarea class="widefat" rows="2" id="bavar_seo_desc" name="bavar_seo_desc"><?php echo esc_textarea( $get( 'seo_desc' ) ); ?></textarea>
				</p>
				<p><label><input type="checkbox" name="bavar_noindex" value="yes" <?php checked( 'yes', $get( 'noindex' ) ); ?>> این صفحه در موتورهای جستجو ایندکس نشود</label></p>
				<p class="description">نشانی اختصاصی (نامک) و تصویر شاخص از بخش‌های خود وردپرس تنظیم می‌شوند.</p>
			</div>

			<div data-kind="course">
				<h4>بخش‌های صفحه‌ی دوره</h4>
				<?php foreach ( self::course_sections() as $key => $label ) : ?>
					<p>
						<label for="bavar_sec_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
						<textarea class="widefat" rows="3" id="bavar_sec_<?php echo esc_attr( $key ); ?>" name="bavar_sec_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $get( 'sec_' . $key ) ); ?></textarea>
					</p>
				<?php endforeach; ?>
				<p>
					<label for="bavar_spot_course">شناسه‌ی دوره در اسپات‌پلیر (اختیاری)</label>
					<input class="widefat" dir="ltr" id="bavar_spot_course" name="bavar_spot_course" value="<?php echo esc_attr( $get( 'spot_course' ) ); ?>" placeholder="مثلاً 5f1a...">
					<span class="description">اگر پر شود، پس از پرداخت موفق لایسنس اسپات‌پلیر به‌صورت خودکار ساخته و در «کتابخانه من» خریدار نمایش داده می‌شود.</span>
				</p>
			</div>

			<div data-kind="pack">
				<p>
					<label for="bavar_book_count">تعداد کتاب‌ها (اختیاری — در غیر این صورت خودکار شمرده می‌شود)</label>
					<input type="number" min="0" id="bavar_book_count" name="bavar_book_count" value="<?php echo esc_attr( $get( 'book_count' ) ); ?>">
				</p>
				<?php
				$books = Bavar_Books::for_pack( $post->ID );
				echo '<h4>کتاب‌های این پک (' . esc_html( count( $books ) ) . ')</h4>';
				if ( $books ) {
					echo '<ol class="bavar-books">';
					foreach ( $books as $book ) {
						printf( '<li><span>%s — %s</span><a href="%s">ویرایش</a></li>', esc_html( $book->post_title ), esc_html( get_post_meta( $book->ID, '_bavar_author', true ) ), esc_url( get_edit_post_link( $book->ID ) ) );
					}
					echo '</ol>';
				}
				if ( 'auto-draft' !== $post->post_status ) {
					printf( '<a class="button" href="%s">+ افزودن کتاب به این پک</a> ', esc_url( admin_url( 'post-new.php?post_type=bavar_book&bavar_pack=' . $post->ID ) ) );
					printf( '<a class="button-link" href="%s">مدیریت کتاب‌های این پک</a>', esc_url( admin_url( 'edit.php?post_type=bavar_book&bavar_pack=' . $post->ID ) ) );
				} else {
					echo '<p class="description">ابتدا پک را ذخیره کنید، سپس کتاب‌ها را اضافه کنید.</p>';
				}
				?>
				<p class="description" style="margin-top:12px">فایل‌های پک (PDF، صوت، ویدیو و …) را در بخش «اطلاعات محصول ← همگانی ← فایل‌های قابل دانلود» اضافه کنید (گزینه‌های «مجازی» و «دانلودی» را فعال کنید). فقط خریداران به این فایل‌ها دسترسی دارند. برای غیرفعال کردن یک فایل، آن را از همین فهرست حذف کنید؛ برای غیرفعال کردن کل پک، وضعیت محصول را «پیش‌نویس» کنید.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save.
	 *
	 * @param int $post_id Product ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['bavar_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bavar_product_nonce'] ), 'bavar_product' ) ) {
			return;
		}
		$kind = sanitize_key( wp_unslash( $_POST['bavar_kind'] ?? '' ) );
		update_post_meta( $post_id, '_bavar_kind', in_array( $kind, [ 'course', 'pack' ], true ) ? $kind : '' );

		$text = [ 'subtitle', 'topic', 'spot_course', 'seo_title' ];
		$area = [ 'includes', 'access_terms', 'benefits', 'faq', 'seo_desc' ];
		foreach ( array_keys( self::course_sections() ) as $key ) {
			$area[] = 'sec_' . $key;
		}
		foreach ( $text as $key ) {
			update_post_meta( $post_id, '_bavar_' . $key, sanitize_text_field( wp_unslash( $_POST[ 'bavar_' . $key ] ?? '' ) ) );
		}
		foreach ( $area as $key ) {
			update_post_meta( $post_id, '_bavar_' . $key, sanitize_textarea_field( wp_unslash( $_POST[ 'bavar_' . $key ] ?? '' ) ) );
		}
		update_post_meta( $post_id, '_bavar_book_count', absint( $_POST['bavar_book_count'] ?? 0 ) ?: '' );
		update_post_meta( $post_id, '_bavar_buy_url', esc_url_raw( wp_unslash( $_POST['bavar_buy_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_bavar_grant_existing', empty( $_POST['bavar_grant_existing'] ) ? 'no' : 'yes' );
		update_post_meta( $post_id, '_bavar_noindex', empty( $_POST['bavar_noindex'] ) ? '' : 'yes' );
		Bavar_Books::flush_count( $post_id );
	}

	/**
	 * Digital products are bought once.
	 *
	 * @param bool       $value   Current.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function sold_individually( $value, $product ) {
		return bavar_product_kind( $product->get_parent_id() ?: $product->get_id() ) ? true : $value;
	}

	/**
	 * Buying a course or pack goes straight to checkout.
	 *
	 * @param string          $url     Redirect.
	 * @param WC_Product|null $product Product.
	 * @return string
	 */
	public static function to_checkout( $url, $product = null ) {
		if ( $product && bavar_product_kind( $product ) ) {
			// Going straight to checkout: the "added to cart" notice is noise.
			wc_clear_notices();
			return wc_get_checkout_url();
		}
		return $url;
	}

	/**
	 * Stop a customer from paying twice for something they already own.
	 *
	 * @param bool $passed     Validation state.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function block_rebuy( $passed, $product_id ) {
		if ( ! $passed || ! bavar_product_kind( $product_id ) ) {
			return $passed;
		}
		if ( self::owned( $product_id ) ) {
			wc_add_notice( 'این محصول را قبلاً خریده‌اید و در «کتابخانه من» در دسترس است.', 'notice' );
			return false;
		}
		// Already in the cart (bought once only): continue to checkout instead of an error.
		if ( WC()->cart && WC()->cart->find_product_in_cart( WC()->cart->generate_cart_id( $product_id ) ) ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
		return $passed;
	}

	/**
	 * Give buyers who already own a product access to files added later.
	 *
	 * WooCommerce only grants access to the files that existed at purchase
	 * time; this runs while the product's file list is saved (before the new
	 * list is written), so the previous list is still in the database.
	 *
	 * @param int                   $product_id   Product ID.
	 * @param int                   $variation_id Variation ID.
	 * @param WC_Product_Download[] $downloads    New file list.
	 */
	public static function grant_new_files( $product_id, $variation_id, $downloads ) {
		global $wpdb;
		$target = $variation_id ? $variation_id : $product_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checkbox from the product form WooCommerce already verified.
		$flag = isset( $_POST['bavar_product_nonce'] ) ? ( empty( $_POST['bavar_grant_existing'] ) ? 'no' : 'yes' ) : get_post_meta( $product_id, '_bavar_grant_existing', true );
		if ( 'no' === $flag || ! $downloads ) {
			return;
		}
		$old     = get_post_meta( $target, '_downloadable_files', true );
		$old_ids = is_array( $old ) ? array_map( 'strval', array_keys( $old ) ) : [];
		$new_ids = array_diff( array_map( 'strval', array_keys( $downloads ) ), $old_ids );
		if ( ! $new_ids ) {
			return;
		}

		// Paid orders that contain the product.
		$table     = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
		$order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM {$table} WHERE product_id = %d", $target ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lookup    = $wpdb->prefix . 'wc_order_product_lookup';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup ) ) === $lookup ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$column    = $variation_id ? 'variation_id' : 'product_id';
			$order_ids = array_merge( $order_ids, $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM {$lookup} WHERE {$column} = %d", $target ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$granted = [];
		foreach ( array_unique( array_map( 'intval', $order_ids ) ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! $order->has_status( [ 'processing', 'completed' ] ) ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( (int) ( $variation_id ? $item->get_variation_id() : $item->get_product_id() ) !== (int) $target ) {
					continue;
				}
				foreach ( $new_ids as $download_id ) {
					wc_downloadable_file_permission( $download_id, $target, $order, $item->get_quantity(), $item );
				}
				$granted[] = $order_id;
			}
		}

		if ( $granted ) {
			/**
			 * New files were added to a product and given to earlier buyers.
			 * Hook SMS / email notifications here.
			 *
			 * @param int   $product_id Product ID.
			 * @param array $new_ids    New download IDs.
			 * @param int[] $order_ids  Orders that received access.
			 */
			do_action( 'bavar_new_content', (int) $target, array_values( $new_ids ), array_values( array_unique( $granted ) ) );
		}
	}

	/**
	 * Show prices with Persian digits on the site (HTML entities untouched).
	 *
	 * @param string $html Price HTML.
	 * @return string
	 */
	public static function persian_price( $html ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		return preg_replace_callback(
			'/&#?[a-z0-9]+;|\d/i',
			function ( $m ) {
				return ctype_digit( $m[0] ) ? bavar_fa_num( $m[0] ) : $m[0];
			},
			$html
		);
	}

	/**
	 * Buy URL of a product (custom link, or add-to-cart on this site).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function buy_url( $product ) {
		$custom = get_post_meta( $product->get_id(), '_bavar_buy_url', true );
		return $custom ? $custom : add_query_arg( 'add-to-cart', $product->get_id(), $product->get_permalink() );
	}

	/**
	 * FAQ entries parsed from the textarea.
	 *
	 * @param int $product_id Product ID.
	 * @return array[] { q, a }
	 */
	public static function faq( $product_id ) {
		$out = [];
		foreach ( preg_split( '/\n\s*\n/', trim( (string) get_post_meta( $product_id, '_bavar_faq', true ) ) ) as $block ) {
			$lines = bavar_lines( $block );
			if ( $lines ) {
				$out[] = [
					'q' => array_shift( $lines ),
					'a' => implode( "\n", $lines ),
				];
			}
		}
		return $out;
	}

	/**
	 * Current BAVAR product on a single product page.
	 *
	 * @return int
	 */
	private static function seo_product() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return 0;
		}
		$id = get_queried_object_id();
		return bavar_product_kind( $id ) ? $id : 0;
	}

	/**
	 * SEO title.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function seo_title( $title ) {
		$id = self::seo_product();
		$custom = $id ? get_post_meta( $id, '_bavar_seo_title', true ) : '';
		return $custom ? $custom : $title;
	}

	/**
	 * Meta description, Open Graph and Product structured data.
	 */
	public static function seo_head() {
		$id = self::seo_product();
		if ( ! $id ) {
			return;
		}
		$product = wc_get_product( $id );
		$desc    = get_post_meta( $id, '_bavar_seo_desc', true );
		$desc    = $desc ? $desc : wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 30, '…' );
		$image   = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'large' ) : '';
		$title   = self::seo_title( $product->get_name() );

		if ( $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:type" content="product">' . "\n" . '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $desc ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		if ( $image ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		}

		$data = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'description' => $desc,
			'url'         => $product->get_permalink(),
			'brand'       => [
				'@type' => 'Brand',
				'name'  => 'BAVAR GROUP',
			],
		];
		if ( $image ) {
			$data['image'] = $image;
		}
		if ( '' !== $product->get_price() ) {
			$data['offers'] = [
				'@type'         => 'Offer',
				'price'         => (string) wc_get_price_to_display( $product ),
				'priceCurrency' => 'IRT' === get_woocommerce_currency() ? 'IRR' : get_woocommerce_currency(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => $product->get_permalink(),
			];
			if ( 'IRT' === get_woocommerce_currency() ) {
				$data['offers']['price'] = (string) ( (float) wc_get_price_to_display( $product ) * 10 );
			}
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * Robots.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function seo_robots( $robots ) {
		$id = self::seo_product();
		if ( $id && 'yes' === get_post_meta( $id, '_bavar_noindex', true ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Whether the current user already bought the product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function owned( $product_id ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return false;
		}
		return wc_customer_bought_product( $user->user_email, $user->ID, $product_id );
	}
}
