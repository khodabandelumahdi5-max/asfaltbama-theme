<?php
/**
 * One-click store setup (فروشگاه یدک › راه‌اندازی سریع).
 *
 * Every step is optional and safe to run again: existing categories,
 * vehicles, pages and menus (matched by slug/name) are left untouched.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Setup {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 50 );
		add_action( 'admin_post_yadak_setup', array( __CLASS__, 'run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'nudge' ) );
	}

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'راه‌اندازی سریع', 'yadak-core' ), __( 'راه‌اندازی سریع', 'yadak-core' ), 'manage_options', 'yadak-setup', array( __CLASS__, 'render' ), 98 );
	}

	public static function nudge() {
		if ( get_option( 'yadak_setup_done' ) || ! current_user_can( 'manage_options' ) || ( isset( $_GET['page'] ) && 'yadak-setup' === $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( 'فروشگاه هنوز راه‌اندازی نشده است.', 'yadak-core' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=yadak-setup' ) ) . '">' . esc_html__( 'راه‌اندازی سریع', 'yadak-core' ) . '</a></p></div>';
	}

	/**
	 * @return array<string,array{0:string,1:string,2:bool}> key => [label, help, checked by default]
	 */
	public static function steps() {
		return array(
			'brand'      => array( __( 'نام برند و شعار', 'yadak-core' ), __( 'عنوان سایت «قطعه فوری» و شعار سئو.', 'yadak-core' ), true ),
			'woo'        => array( __( 'تنظیمات ووکامرس برای ایران', 'yadak-core' ), __( 'فروشگاه در تهران، فروش و ارسال فقط ایران، واحد پول تومان بدون اعشار، کیلوگرم و سانتی‌متر، نظرها و امتیاز فعال، تأیید خریدار واقعی برای نظر.', 'yadak-core' ), true ),
			'permalinks' => array( __( 'پیوندهای یکتای سئو', 'yadak-core' ), __( 'ساختار «/نام-نوشته/» و ساخت دوباره قوانین آدرس.', 'yadak-core' ), true ),
			'classic'    => array( __( 'صفحه سبد و پرداخت کلاسیک', 'yadak-core' ), __( 'برای خرید اعتباری، کارت‌به‌کارت و اعتبارسنجی موبایل و کد پستی.', 'yadak-core' ), true ),
			'categories' => array( __( 'درخت دسته‌بندی قطعات', 'yadak-core' ), __( 'سه بخش برقی و الکترونیک، بدنه، سقف و چراغ، عمومی و مکانیکی با زیردسته‌ها و نامک لاتین.', 'yadak-core' ), true ),
			'vehicles'   => array( __( 'فهرست خودروها', 'yadak-core' ), __( 'برندها و مدل‌های پرتردد ژاپنی، چینی، کره‌ای و ایرانی با کشور سازنده و نامک لاتین. تیپ و موتور را بعداً خودتان اضافه کنید.', 'yadak-core' ), true ),
			'brands'     => array( __( 'برندهای سازنده قطعه', 'yadak-core' ), __( 'برندهای رایج (بوش، دنسو، موبیس، والئو، ایساکو و …) با نام لاتین، کشور و نوع، آدرس «/brand/نام/» و برگه «برندها». لوگو را خودتان در محصولات › برندها بگذارید.', 'yadak-core' ), true ),
			'pages'      => array( __( 'برگه‌های پایه (پیش‌نویس)', 'yadak-core' ), __( 'درباره ما، تماس، همکاری عمده، قوانین، مرجوعی و ضمانت، ارسال. به‌صورت پیش‌نویس ساخته می‌شوند تا متن را بازبینی و بعد منتشر کنید.', 'yadak-core' ), true ),
			'menus'      => array( __( 'منوی بالا و پایین', 'yadak-core' ), __( 'منوی بالا با سه بخش فروشگاه و منوی پایین با برگه‌ها.', 'yadak-core' ), true ),
			'live'       => array( __( 'باز کردن فروشگاه برای همه (خاموش کردن «به‌زودی»)', 'yadak-core' ), __( 'فقط وقتی محصولات و برگه‌ها آماده شد تیک بزنید.', 'yadak-core' ), false ),
		);
	}

	public static function render() {
		$log = get_transient( 'yadak_setup_log' );
		delete_transient( 'yadak_setup_log' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'راه‌اندازی سریع فروشگاه', 'yadak-core' ); ?></h1>
			<?php if ( $log ) : ?>
				<div class="notice notice-success"><p><?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', (array) $log ) ) ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'مرحله‌ها را انتخاب و اجرا کنید. اجرای دوباره چیزی را خراب نمی‌کند؛ موارد موجود دست نمی‌خورند.', 'yadak-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yadak_setup">
				<?php wp_nonce_field( 'yadak_setup' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( self::steps() as $key => $step ) : ?>
						<tr>
							<th scope="row"><label><input type="checkbox" name="steps[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $step[2] ); ?>> <?php echo esc_html( $step[0] ); ?></label></th>
							<td><p class="description"><?php echo esc_html( $step[1] ); ?></p></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'اجرای راه‌اندازی', 'yadak-core' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'بعد از راه‌اندازی', 'yadak-core' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'فروشگاه یدک › تنظیمات: اطلاعات فروشنده (فاکتور)، پنل پیامک، انبارها.', 'yadak-core' ); ?></li>
				<li><?php esc_html_e( 'ووکامرس › پیکربندی › پرداخت‌ها: زرین‌پال (مرچنت کد)، کارت‌به‌کارت، خرید اعتباری.', 'yadak-core' ); ?></li>
				<li><?php esc_html_e( 'نمایش › سفارشی‌سازی › اطلاعات فروشگاه: تلفن، ساعت کاری، نشانی، اینستاگرام، کد اینماد، عکس هدر.', 'yadak-core' ); ?></li>
				<li><?php esc_html_e( 'محصولات › درون‌ریزی: فایل اکسل/CSV محصولات.', 'yadak-core' ); ?></li>
			</ol>
		</div>
		<?php
	}

	public static function run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		check_admin_referer( 'yadak_setup' );
		$steps = isset( $_POST['steps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['steps'] ) ) : array();
		$log   = array();
		foreach ( array_keys( self::steps() ) as $step ) {
			if ( in_array( $step, $steps, true ) ) {
				$log[] = call_user_func( array( __CLASS__, 'step_' . $step ) );
			}
		}
		update_option( 'yadak_setup_done', 1 );
		flush_rewrite_rules();
		set_transient( 'yadak_setup_log', $log, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=yadak-setup' ) );
		exit;
	}

	/* ---------- Steps ---------- */

	public static function step_brand() {
		update_option( 'blogname', 'قطعه فوری' );
		update_option( 'blogdescription', 'خرید آنلاین قطعات خودروهای ژاپنی، چینی، کره‌ای و ایرانی' );
		$settings                = (array) get_option( Yadak_Settings::OPTION, array() );
		$settings['seller_name'] = isset( $settings['seller_name'] ) && $settings['seller_name'] ? $settings['seller_name'] : 'قطعه فوری';
		update_option( Yadak_Settings::OPTION, $settings );
		return __( '✔ نام برند و شعار تنظیم شد.', 'yadak-core' );
	}

	public static function step_woo() {
		$options = array(
			'woocommerce_default_country'           => 'IR:THR',
			'woocommerce_allowed_countries'         => 'specific',
			'woocommerce_specific_allowed_countries' => array( 'IR' ),
			'woocommerce_ship_to_countries'         => 'specific',
			'woocommerce_specific_ship_to_countries' => array( 'IR' ),
			'woocommerce_currency'                  => 'IRT',
			'woocommerce_currency_pos'              => 'right_space',
			'woocommerce_price_thousand_sep'        => ',',
			'woocommerce_price_decimal_sep'         => '.',
			'woocommerce_price_num_decimals'        => '0',
			'woocommerce_weight_unit'               => 'kg',
			'woocommerce_dimension_unit'            => 'cm',
			'woocommerce_enable_reviews'            => 'yes',
			'woocommerce_review_rating_verification_label' => 'yes',
			'woocommerce_review_rating_verification_required' => 'yes',
			'woocommerce_enable_review_rating'      => 'yes',
			'woocommerce_manage_stock'              => 'yes',
			'woocommerce_notify_low_stock_amount'   => '3',
			'woocommerce_enable_guest_checkout'     => 'yes',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_myaccount_registration' => 'yes',
			'woocommerce_calc_taxes'                => 'no',
		);
		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
		// WooCommerce saves these in English at install time, before the Persian
		// translation loads. Replace only that default, never an edited text.
		$privacy = array(
			'woocommerce_checkout_privacy_policy_text'     => 'اطلاعات شما فقط برای پردازش و ارسال سفارش و پشتیبانی استفاده می‌شود؛ جزئیات در [privacy_policy].',
			'woocommerce_registration_privacy_policy_text' => 'اطلاعات شما فقط برای مدیریت حساب و سفارش‌ها استفاده می‌شود؛ جزئیات در [privacy_policy].',
		);
		foreach ( $privacy as $key => $value ) {
			if ( 0 === strpos( (string) get_option( $key, 'Your personal data' ), 'Your personal data' ) ) {
				update_option( $key, $value );
			}
		}
		return __( '✔ ووکامرس برای ایران تنظیم شد (تومان، فقط ایران، نظر فقط از خریدار واقعی). مالیات خاموش است؛ اگر مشمول ارزش افزوده هستید، در ووکامرس › مالیات نرخ را وارد کنید.', 'yadak-core' );
	}

	public static function step_permalinks() {
		update_option( 'permalink_structure', '/%postname%/' );
		self::brand_base();
		return __( '✔ پیوندهای یکتا روی «/نام-نوشته/» تنظیم شد.', 'yadak-core' );
	}

	/**
	 * Latin brand URLs (/brand/bosch/) instead of the translated base (/برند/…).
	 */
	private static function brand_base() {
		if ( '' === (string) get_option( 'woocommerce_brand_permalink', '' ) ) {
			update_option( 'woocommerce_brand_permalink', 'brand' );
			if ( class_exists( 'WC_Brands' ) && taxonomy_exists( 'product_brand' ) ) {
				WC_Brands::init_taxonomy(); // Re-register so the flush below uses the new base.
				Yadak_Brands::rewrites();
			}
		}
	}

	/**
	 * Common part brands in Iran: [fa, slug/Latin, country, type].
	 * Country and type are starting points; check them against your suppliers.
	 */
	public static function brands() {
		return array(
			array( 'بوش', 'Bosch', 'de', 'oe' ),
			array( 'والئو', 'Valeo', 'fr', 'oe' ),
			array( 'دنسو', 'Denso', 'jp', 'oe' ),
			array( 'ان‌جی‌کی', 'NGK', 'jp', 'oe' ),
			array( 'آیسین', 'Aisin', 'jp', 'oe' ),
			array( 'کایابا', 'KYB', 'jp', 'oe' ),
			array( 'اکسدی', 'Exedy', 'jp', 'oe' ),
			array( 'هلا', 'Hella', 'de', 'oe' ),
			array( 'ماله', 'Mahle', 'de', 'oe' ),
			array( 'مان فیلتر', 'Mann-Filter', 'de', 'oe' ),
			array( 'زاکس', 'Sachs', 'de', 'oe' ),
			array( 'کنتیننتال', 'Continental', 'de', 'oe' ),
			array( 'فبی', 'Febi', 'de', 'aftermarket' ),
			array( 'موبیس', 'Mobis', 'kr', 'genuine' ),
			array( 'ماندو', 'Mando', 'kr', 'oe' ),
			array( 'دانگیل', 'Dongil', 'kr', '' ), // Type unknown: set it in Products › Brands.
			array( 'دپو', 'Depo', 'tw', 'aftermarket' ),
			array( 'تی‌وای‌سی', 'TYC', 'tw', 'aftermarket' ),
			array( 'ایساکو', 'Isaco', 'ir', 'genuine' ),
			array( 'سایپا یدک', 'Saipa Yadak', 'ir', 'genuine' ),
			array( 'کروز', 'Crouse', 'ir', 'oe' ),
		);
	}

	public static function step_brands() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return __( '✖ برندهای ووکامرس در دسترس نیست (ووکامرس را به‌روز کنید).', 'yadak-core' );
		}
		self::brand_base();
		$made = 0;
		foreach ( self::brands() as $brand ) {
			$slug = sanitize_title( $brand[1] );
			if ( get_term_by( 'slug', $slug, 'product_brand' ) ) {
				continue; // Keep the owner's edits.
			}
			$term = wp_insert_term( $brand[0], 'product_brand', array( 'slug' => $slug ) );
			if ( is_wp_error( $term ) ) {
				continue;
			}
			update_term_meta( $term['term_id'], 'yadak_latin', $brand[1] );
			update_term_meta( $term['term_id'], 'yadak_country', $brand[2] );
			update_term_meta( $term['term_id'], 'yadak_brand_type', $brand[3] );
			++$made;
		}
		if ( ! get_page_by_path( 'brands' ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish', // Built from live brand data, nothing to proofread.
					'post_name'    => 'brands',
					'post_title'   => 'برندها',
					'post_content' => '<!-- wp:paragraph --><p>قطعات برندهای معتبر سازنده، از قطعه اصلی شرکتی تا تأمین‌کنندگان خط تولید خودروسازان. برند را انتخاب کنید تا همه قطعات موجود آن را ببینید.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[yadak_brands]<!-- /wp:shortcode -->',
				)
			);
		}
		/* translators: %d: count */
		return sprintf( __( '✔ %d برند ساخته شد و برگه «برندها» آماده است. کشور و نوع هر برند را در محصولات › برندها بازبینی کنید و لوگو بگذارید.', 'yadak-core' ), $made );
	}

	public static function step_classic() {
		Yadak_Checkout::use_classic_pages();
		return __( '✔ صفحه سبد خرید و پرداخت کلاسیک شد.', 'yadak-core' );
	}

	/**
	 * Find or create a term path; keeps existing terms, sets slug on new ones.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param array  $path     [ [name, slug], ... ] from top to bottom.
	 * @return int Deepest term id.
	 */
	private static function term_path( $taxonomy, $path ) {
		$parent = 0;
		foreach ( $path as $node ) {
			list( $name, $slug ) = $node;
			$existing = get_term_by( 'slug', $slug, $taxonomy );
			if ( $existing && (int) $existing->parent === $parent ) {
				$parent = (int) $existing->term_id;
				continue;
			}
			$made = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent, 'slug' => $slug ) );
			if ( is_wp_error( $made ) ) {
				$id     = $made->get_error_data( 'term_exists' );
				$parent = $id ? (int) $id : $parent;
				continue;
			}
			$parent = (int) $made['term_id'];
		}
		return $parent;
	}

	public static function categories() {
		return array(
			array( 'برق و الکترونیک خودرو', 'electrical', array(
				array( 'ECU و ماژول‌ها', 'ecu' ),
				array( 'سنسورها', 'sensors' ),
				array( 'دینام و استارت', 'alternator-starter' ),
				array( 'سیم‌کشی، فیوز و رله', 'wiring' ),
				array( 'باتری', 'batteries' ),
				array( 'کلید و سوئیچ', 'switches' ),
			) ),
			array( 'بدنه، سقف و چراغ', 'body-lights', array(
				array( 'چراغ جلو', 'headlights' ),
				array( 'چراغ عقب (خطر)', 'taillights' ),
				array( 'مه‌شکن و پروژکتور', 'fog-lights' ),
				array( 'لامپ و LED', 'bulbs' ),
				array( 'قطعات بدنه (سپر، جلوپنجره، گلگیر)', 'body-parts' ),
				array( 'آینه بغل', 'mirrors' ),
				array( 'سقف و باربند', 'roof' ),
				array( 'شیشه و برف‌پاک‌کن', 'glass-wipers' ),
			) ),
			array( 'عمومی و مکانیکی', 'mechanical', array(
				array( 'لوازم مصرفی (فیلتر، روغن، تسمه)', 'consumables' ),
				array( 'ترمز', 'brake' ),
				array( 'جلوبندی و تعلیق', 'suspension' ),
				array( 'قطعات موتور', 'engine' ),
				array( 'کلاچ و گیربکس', 'clutch' ),
				array( 'خنک‌کاری و کولر', 'cooling' ),
			) ),
		);
	}

	public static function step_categories() {
		$count = 0;
		foreach ( self::categories() as $order => $dept ) {
			$parent = self::term_path( 'product_cat', array( array( $dept[0], $dept[1] ) ) );
			update_term_meta( $parent, 'order', $order );
			foreach ( $dept[2] as $sub ) {
				self::term_path( 'product_cat', array( array( $dept[0], $dept[1] ), $sub ) );
				++$count;
			}
		}
		return sprintf( /* translators: %d: count */ __( '✔ ۳ بخش و %d زیردسته آماده است.', 'yadak-core' ), $count );
	}

	/**
	 * Popular makes and models in Iran: [make fa, slug, origin, [ [model fa, slug], ... ] ].
	 */
	public static function vehicles() {
		return array(
			array( 'تویوتا', 'toyota', 'jp', array( array( 'کرولا', 'corolla' ), array( 'کمری', 'camry' ), array( 'یاریس', 'yaris' ), array( 'راوفور', 'rav4' ), array( 'پرادو', 'prado' ), array( 'لندکروزر', 'land-cruiser' ), array( 'هایلوکس', 'hilux' ) ) ),
			array( 'نیسان', 'nissan', 'jp', array( array( 'ماکسیما', 'maxima' ), array( 'تینا', 'teana' ), array( 'سانی', 'sunny' ), array( 'قشقایی', 'qashqai' ), array( 'ایکس‌تریل', 'x-trail' ), array( 'جوک', 'juke' ), array( 'پاترول', 'patrol' ) ) ),
			array( 'مزدا', 'mazda', 'jp', array( array( 'مزدا ۳', 'mazda-3' ), array( 'مزدا ۶', 'mazda-6' ), array( 'CX-5', 'cx-5' ) ) ),
			array( 'میتسوبیشی', 'mitsubishi', 'jp', array( array( 'پاجرو', 'pajero' ), array( 'ASX', 'asx' ), array( 'اوتلندر', 'outlander' ), array( 'لنسر', 'lancer' ) ) ),
			array( 'هوندا', 'honda', 'jp', array( array( 'سیویک', 'civic' ), array( 'آکورد', 'accord' ), array( 'CR-V', 'cr-v' ) ) ),
			array( 'سوزوکی', 'suzuki', 'jp', array( array( 'گرند ویتارا', 'grand-vitara' ), array( 'سوئیفت', 'swift' ) ) ),
			array( 'لکسوس', 'lexus', 'jp', array( array( 'ES', 'es' ), array( 'NX', 'nx' ), array( 'RX', 'rx' ) ) ),
			array( 'هیوندای', 'hyundai', 'kr', array( array( 'النترا', 'elantra' ), array( 'سوناتا', 'sonata' ), array( 'اکسنت', 'accent' ), array( 'ورنا', 'verna' ), array( 'i20', 'i20' ), array( 'آزرا', 'azera' ), array( 'توسان', 'tucson' ), array( 'سانتافه', 'santa-fe' ) ) ),
			array( 'کیا', 'kia', 'kr', array( array( 'ریو', 'rio' ), array( 'پیکانتو', 'picanto' ), array( 'سراتو', 'cerato' ), array( 'اپتیما', 'optima' ), array( 'کادنزا', 'cadenza' ), array( 'اسپورتیج', 'sportage' ), array( 'سورنتو', 'sorento' ) ) ),
			array( 'سانگ‌یانگ', 'ssangyong', 'kr', array( array( 'تیوولی', 'tivoli' ), array( 'کوراندو', 'korando' ), array( 'رکستون', 'rexton' ) ) ),
			array( 'چری', 'chery', 'cn', array( array( 'تیگو ۵', 'tiggo-5' ), array( 'تیگو ۷', 'tiggo-7' ), array( 'تیگو ۸', 'tiggo-8' ), array( 'آریزو ۵', 'arrizo-5' ), array( 'آریزو ۶', 'arrizo-6' ) ) ),
			array( 'ام‌وی‌ام', 'mvm', 'cn', array( array( '110', '110' ), array( '315', '315' ), array( 'X22', 'x22' ), array( 'X33', 'x33' ), array( 'X55', 'x55' ) ) ),
			array( 'جک', 'jac', 'cn', array( array( 'J4', 'j4' ), array( 'S3', 's3' ), array( 'S5', 's5' ) ) ),
			array( 'هاوال', 'haval', 'cn', array( array( 'H2', 'h2' ), array( 'H6', 'h6' ) ) ),
			array( 'کی‌ام‌سی', 'kmc', 'cn', array( array( 'J7', 'j7' ), array( 'K7', 'k7' ), array( 'T8', 't8' ) ) ),
			array( 'جیلی', 'geely', 'cn', array( array( 'امگرند', 'emgrand' ) ) ),
			array( 'دانگ‌فنگ', 'dongfeng', 'cn', array( array( 'H30 کراس', 'h30-cross' ) ) ),
			array( 'بریلیانس', 'brilliance', 'cn', array( array( 'H320', 'h320' ), array( 'H330', 'h330' ), array( 'V5', 'v5' ) ) ),
			array( 'لیفان', 'lifan', 'cn', array( array( 'X60', 'x60' ), array( '620', '620' ) ) ),
			array( 'چانگان', 'changan', 'cn', array( array( 'CS35', 'cs35' ), array( 'CS55', 'cs55' ) ) ),
			array( 'ایران‌خودرو', 'ikco', 'ir', array( array( 'پژو ۲۰۶', 'peugeot-206' ), array( 'پژو ۲۰۷', 'peugeot-207' ), array( 'پژو پارس', 'peugeot-pars' ), array( 'پژو ۴۰۵', 'peugeot-405' ), array( 'سمند', 'samand' ), array( 'دنا', 'dena' ), array( 'رانا', 'runna' ), array( 'تارا', 'tara' ) ) ),
			array( 'سایپا', 'saipa', 'ir', array( array( 'پراید', 'pride' ), array( 'تیبا', 'tiba' ), array( 'ساینا', 'saina' ), array( 'کوییک', 'quick' ), array( 'شاهین', 'shahin' ) ) ),
		);
	}

	public static function step_vehicles() {
		$models = 0;
		foreach ( self::vehicles() as $make ) {
			$make_id = self::term_path( Yadak_Fitment::TAXONOMY, array( array( $make[0], $make[1] ) ) );
			if ( ! get_term_meta( $make_id, 'yadak_origin', true ) ) {
				update_term_meta( $make_id, 'yadak_origin', $make[2] );
			}
			foreach ( $make[3] as $model ) {
				self::term_path( Yadak_Fitment::TAXONOMY, array( array( $make[0], $make[1] ), $model ) );
				++$models;
			}
		}
		return sprintf( /* translators: 1: makes, 2: models */ __( '✔ %1$d برند و %2$d مدل خودرو آماده است.', 'yadak-core' ), count( self::vehicles() ), $models );
	}

	/**
	 * @return array<string,array{0:string,1:string}> slug => [title, content]
	 */
	public static function pages() {
		$note = '<!-- wp:paragraph --><p><strong>[پیش‌نویس — قبل از انتشار، متن داخل کروشه‌ها را تکمیل و بازبینی کنید.]</strong></p><!-- /wp:paragraph -->';
		$p    = static function ( $text ) {
			return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
		};
		$h    = static function ( $text ) {
			return '<!-- wp:heading --><h2 class="wp-block-heading">' . $text . '</h2><!-- /wp:heading -->';
		};
		return array(
			'about'    => array( 'درباره قطعه فوری', $note . $p( 'قطعه فوری فروشگاه تخصصی قطعات برقی و الکترونیک، بدنه، سقف و چراغ و قطعات مکانیکی خودروهای ژاپنی، چینی، کره‌ای و ایرانی است. هر قطعه با شماره فنی و مشخصات سازگاری ثبت می‌شود تا قطعه درست را همان بار اول بگیرید.' ) . $p( '[سابقه کسب‌وکار، محل انبار، تیم و مجوزها]' ) ),
			'contact'  => array( 'تماس با ما', $note . $p( 'تلفن: [شماره]' ) . $p( 'ساعت پاسخگویی: [روزها و ساعت‌ها]' ) . $p( 'نشانی: [نشانی کامل و کد پستی]' ) . $p( 'برای استعلام قطعه، شماره فنی یا مدل، سال و تیپ خودرو را بفرستید.' ) ),
			'b2b'      => array( 'همکاری و فروش عمده', $note . $p( 'مکانیک، تعمیرگاه، فروشنده قطعات یا ناوگان هستید؟ با حساب همکار قیمت ویژه همکار، خرید اعتباری، صورت‌حساب آنلاین و کارشناس فروش اختصاصی خواهید داشت.' ) . $h( 'چطور حساب همکار بگیرم؟' ) . $p( 'در صفحه «حساب من» ثبت‌نام کنید و نوع خرید را «مکانیک/تعمیرگاه/فروشنده/عمده‌فروش» انتخاب کنید. کارشناس ما بعد از بررسی، حساب شما را فعال می‌کند.' ) . $p( '[شرایط پرداخت، حداقل خرید، مدارک لازم]' ) ),
			'terms'    => array( 'قوانین و مقررات', $note . $p( '[قوانین خرید، ثبت سفارش، قیمت‌ها و نوسان قیمت، مسئولیت انتخاب قطعه، حریم خصوصی — مطابق قوانین تجارت الکترونیک و الزامات اینماد]' ) ),
			'returns'  => array( 'شرایط مرجوعی و ضمانت', $note . $p( '[مدت مهلت مرجوعی، شرایط کالای سالم و بسته‌بندی، قطعات برقی نصب‌شده، ضمانت اصالت و سلامت، روند بازگشت وجه]' ) ),
			'shipping' => array( 'ارسال و تحویل', $note . $p( '[روش‌های ارسال: پیک شهری، پست، تیپاکس، باربری برای قطعات حجیم؛ زمان‌بندی ارسال فوری؛ هزینه‌ها]' ) ),
		);
	}

	public static function step_pages() {
		$made = 0;
		foreach ( self::pages() as $slug => $page ) {
			if ( get_page_by_path( $slug ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_name'    => $slug,
					'post_title'   => $page[0],
					'post_content' => $page[1],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				++$made;
				if ( 'terms' === $slug ) {
					update_option( 'woocommerce_terms_page_id', $id );
				}
				if ( 'b2b' === $slug && ! get_theme_mod( 'yadak_b2b_url' ) ) {
					set_theme_mod( 'yadak_b2b_url', home_url( '/b2b/' ) );
				}
			}
		}
		return sprintf( /* translators: %d: count */ __( '✔ %d برگه پیش‌نویس ساخته شد (برگه‌ها › پیش‌نویس‌ها). بعد از تکمیل متن منتشر کنید.', 'yadak-core' ), $made );
	}

	public static function step_menus() {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$out       = array();
		if ( empty( $locations['menu-1'] ) ) {
			$menu_id = wp_create_nav_menu( 'منوی اصلی' );
			if ( ! is_wp_error( $menu_id ) ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => 'همه قطعات',
						'menu-item-url'    => wc_get_page_permalink( 'shop' ),
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
				foreach ( self::categories() as $dept ) {
					$term = get_term_by( 'slug', $dept[1], 'product_cat' );
					if ( $term ) {
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'     => $term->name,
								'menu-item-object'    => 'product_cat',
								'menu-item-object-id' => $term->term_id,
								'menu-item-type'      => 'taxonomy',
								'menu-item-status'    => 'publish',
							)
						);
					}
				}
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => 'برندها',
						'menu-item-url'    => home_url( '/brands/' ),
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => 'همکاری و فروش عمده',
						'menu-item-url'    => home_url( '/b2b/' ),
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
				$locations['menu-1'] = $menu_id;
				$out[]               = 'منوی بالا';
			}
		}
		if ( empty( $locations['menu-2'] ) ) {
			$menu_id = wp_create_nav_menu( 'منوی پایین' );
			if ( ! is_wp_error( $menu_id ) ) {
				foreach ( array( 'about', 'contact', 'b2b', 'shipping', 'returns', 'terms' ) as $slug ) {
					$page = get_page_by_path( $slug );
					if ( $page ) {
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'     => $page->post_title,
								'menu-item-object'    => 'page',
								'menu-item-object-id' => $page->ID,
								'menu-item-type'      => 'post_type',
								'menu-item-status'    => 'publish',
							)
						);
					}
				}
				$locations['menu-2'] = $menu_id;
				$out[]               = 'منوی پایین';
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		return $out ? '✔ ' . implode( ' و ', $out ) . ' ساخته شد (برگه‌های پیش‌نویس بعد از انتشار در منو دیده می‌شوند).' : __( '✔ منوها از قبل تنظیم شده بودند.', 'yadak-core' );
	}

	public static function step_live() {
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'blog_public', 1 );
		return __( '✔ فروشگاه برای همه باز شد و ایندکس موتورهای جستجو فعال است.', 'yadak-core' );
	}
}
