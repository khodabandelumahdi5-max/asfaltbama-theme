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
				<textarea class="widefat" rows="5" id="bavar_includes" name="bavar_includes" placeholder="Audio&#10;Video&#10;Text&#10;Exercises&#10;Practical Materials"><?php echo esc_textarea( $get( 'includes' ) ); ?></textarea>
			</p>
			<p data-kind="course pack">
				<label for="bavar_access_terms">شرایط دسترسی (زیر دکمه‌ی خرید نمایش داده می‌شود)</label>
				<textarea class="widefat" rows="3" id="bavar_access_terms" name="bavar_access_terms"><?php echo esc_textarea( $get( 'access_terms' ) ); ?></textarea>
			</p>

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
				<p class="description" style="margin-top:12px">فایل‌های PDF پک را در بخش «اطلاعات محصول ← همگانی ← فایل‌های قابل دانلود» اضافه کنید (گزینه‌های «مجازی» و «دانلودی» را فعال کنید). فقط خریداران به این فایل‌ها دسترسی دارند.</p>
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

		$text = [ 'subtitle', 'topic', 'spot_course' ];
		$area = [ 'includes', 'access_terms' ];
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
