<?php
/**
 * MY BAVAR: WooCommerce "My account" with a "My library" tab that groups
 * each purchased course / pack with its protected downloads.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Account {

	const ENDPOINT = 'bavar-library';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'endpoint' ] );
		add_filter( 'woocommerce_get_query_vars', [ __CLASS__, 'query_vars' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'menu' ] );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', fn() => 'محتوای خریداری‌شده من' );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ __CLASS__, 'render' ] );
		add_action( 'woocommerce_account_dashboard', [ __CLASS__, 'dashboard' ], 5 );
	}

	/**
	 * Register the endpoint.
	 */
	public static function endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Let WooCommerce know about the endpoint.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Account menu.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu( $items ) {
		$labels = [
			'dashboard'       => 'پروفایل',
			self::ENDPOINT    => 'محتوای خریداری‌شده من',
			'orders'          => 'خریدها',
			'downloads'       => 'دانلودها',
			'edit-account'    => 'حساب کاربری',
			'customer-logout' => 'خروج',
		];
		$out = [];
		foreach ( $labels as $key => $label ) {
			if ( self::ENDPOINT === $key || isset( $items[ $key ] ) ) {
				$out[ $key ] = $label;
			}
		}
		return $out;
	}

	/**
	 * Paid (and awaiting-approval) BAVAR purchases of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array[] { product, order, item, status }
	 */
	public static function purchases( $user_id ) {
		$orders = wc_get_orders(
			[
				'customer_id' => $user_id,
				'status'      => [ 'wc-processing', 'wc-completed', 'wc-on-hold' ],
				'limit'       => -1,
			]
		);
		$out = [];
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}
				$pid = $product->get_parent_id() ?: $product->get_id();
				if ( isset( $out[ $pid ] ) && 'on-hold' !== $out[ $pid ]['status'] ) {
					continue;
				}
				$out[ $pid ] = [
					'product' => wc_get_product( $pid ),
					'order'   => $order,
					'item'    => $item,
					'status'  => $order->get_status(),
				];
			}
		}
		return $out;
	}

	/**
	 * Dashboard greeting.
	 */
	public static function dashboard() {
		$count = count( self::purchases( get_current_user_id() ) );
		printf(
			'<div class="bv-account-hello"><p class="bv-account-hello__mark">حساب کاربری من</p><p>شما %s محصول خریداری‌شده دارید.</p><a class="bv-button" href="%s">محتوای خریداری‌شده من</a></div>',
			esc_html( bavar_fa_num( $count ) ),
			esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) )
		);
	}

	/**
	 * Persian label for a file by extension.
	 *
	 * @param string $file File URL or path.
	 * @return string
	 */
	public static function file_type( $file ) {
		$ext = strtolower( pathinfo( (string) wp_parse_url( $file, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$map = [
			'pdf'  => 'پی‌دی‌اف',
			'mp3'  => 'صوت',
			'm4a'  => 'صوت',
			'wav'  => 'صوت',
			'ogg'  => 'صوت',
			'aac'  => 'صوت',
			'mp4'  => 'ویدیو',
			'mov'  => 'ویدیو',
			'mkv'  => 'ویدیو',
			'webm' => 'ویدیو',
			'zip'  => 'فایل فشرده',
			'rar'  => 'فایل فشرده',
			'epub' => 'کتاب',
			'docx' => 'سند',
			'xlsx' => 'سند',
		];
		return $map[ $ext ] ?? 'فایل';
	}

	/**
	 * "Purchased content" tab.
	 */
	public static function render() {
		$user_id   = get_current_user_id();
		$purchases = self::purchases( $user_id );
		$downloads = [];
		foreach ( wc_get_customer_available_downloads( $user_id ) as $d ) {
			$downloads[ (int) $d['product_id'] ][] = $d;
		}

		if ( ! $purchases ) {
			echo '<div class="bv-empty"><p>هنوز محصولی خریداری نکرده‌اید.</p>';
			$library = (int) Bavar_Settings::get( 'page_library' );
			if ( $library ) {
				echo '<a class="bv-button" href="' . esc_url( get_permalink( $library ) ) . '">مشاهده‌ی پک‌های کتاب</a>';
			}
			echo '</div>';
			return;
		}

		echo '<div class="bv-mylib">';
		foreach ( $purchases as $pid => $p ) {
			$product = $p['product'];
			if ( ! $product ) {
				continue;
			}
			$kind = bavar_product_kind( $pid );
			?>
			<article class="bv-mylib__item">
				<div class="bv-mylib__img">
					<?php
					if ( $product->get_image_id() ) {
						echo wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail' );
					} else {
						echo '<span class="bv-mylib__ph">باور</span>';
					}
					?>
				</div>
				<div class="bv-mylib__body">
					<p class="bv-mylib__kind"><?php echo esc_html( 'course' === $kind ? 'دوره' : ( 'pack' === $kind ? 'پک کتاب' : 'محصول' ) ); ?></p>
					<h3><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>

					<?php if ( 'on-hold' === $p['status'] ) : ?>
						<p class="bv-mylib__status">در انتظار تأیید پرداخت. <a href="<?php echo esc_url( $p['order']->get_view_order_url() ); ?>">ارسال یا مشاهده‌ی فیش</a></p>
					<?php else : ?>
						<?php
						$key = $p['item']->get_meta( '_bavar_spot_key' );
						$url = $p['item']->get_meta( '_bavar_spot_url' );
						if ( $key ) :
							?>
							<div class="bv-mylib__license">
								<span>کد لایسنس اسپات‌پلیر</span>
								<code dir="ltr"><?php echo esc_html( $key ); ?></code>
								<?php if ( $url ) : ?>
									<a class="bv-button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">ورود به دوره</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $downloads[ $pid ] ) ) : ?>
							<ul class="bv-mylib__files">
								<?php foreach ( $downloads[ $pid ] as $d ) : ?>
									<li>
										<?php $bavar_type = self::file_type( $d['file']['file'] ?? '' ); ?>
										<span><em class="bv-mylib__type"><?php echo esc_html( $bavar_type ); ?></em> <?php echo esc_html( $d['download_name'] ?: $d['file']['name'] ); ?></span>
										<a class="bv-button bv-button--solid" href="<?php echo esc_url( $d['download_url'] ); ?>">دانلود</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php elseif ( ! $key ) : ?>
							<p class="bv-mylib__status">دسترسی شما فعال است. راه ارتباطی و محتوای این محصول از طرف تیم باور برایتان ارسال می‌شود.</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</article>
			<?php
		}
		echo '</div>';
	}
}
