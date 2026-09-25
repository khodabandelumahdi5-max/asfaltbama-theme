<?php
/**
 * 3D / AR product view. Upload a .glb (and optionally .usdz for iPhone AR)
 * in Media, paste its URL in the product's «مشخصات قطعه» tab, and the product
 * page gets a «نمای سه‌بعدی» button that opens a rotatable 3D view with
 * "view in your space" AR on supported phones (Google model-viewer,
 * self-hosted).
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Model3D {

	public static function init() {
		add_filter( 'upload_mimes', array( __CLASS__, 'mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'filetype' ), 10, 4 );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'button' ), 32 );
		add_filter( 'woocommerce_post_class', array( __CLASS__, 'post_class' ), 10, 2 );
	}

	public static function mimes( $mimes ) {
		if ( current_user_can( 'edit_products' ) ) {
			$mimes['glb']  = 'model/gltf-binary';
			$mimes['usdz'] = 'model/vnd.usdz+zip';
		}
		return $mimes;
	}

	/**
	 * PHP's fileinfo does not know glb/usdz; trust the extension for staff uploads.
	 */
	public static function filetype( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'glb', 'usdz' ), true ) && current_user_can( 'edit_products' ) ) {
			$data['ext']  = $ext;
			$data['type'] = 'glb' === $ext ? 'model/gltf-binary' : 'model/vnd.usdz+zip';
		}
		return $data;
	}

	public static function fields() {
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_yadak_model_glb',
				'label'       => __( 'مدل سه‌بعدی (GLB)', 'yadak-core' ),
				'placeholder' => 'https://…/part.glb',
				'desc_tip'    => true,
				'description' => __( 'فایل .glb را در «رسانه» بارگذاری و نشانی آن را اینجا بگذارید. حجم زیر ۵ مگابایت توصیه می‌شود.', 'yadak-core' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_yadak_model_usdz',
				'label'       => __( 'مدل AR آیفون (USDZ، اختیاری)', 'yadak-core' ),
				'placeholder' => 'https://…/part.usdz',
			)
		);
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		foreach ( array( '_yadak_model_glb', '_yadak_model_usdz' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$product->update_meta_data( $key, esc_url_raw( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		// phpcs:enable
	}

	public static function post_class( $classes, $product ) {
		if ( $product && $product->get_meta( '_yadak_model_glb' ) ) {
			$classes[] = 'has-3d-model';
		}
		return $classes;
	}

	public static function button() {
		global $product;
		$glb = $product ? $product->get_meta( '_yadak_model_glb' ) : '';
		if ( ! $glb ) {
			return;
		}
		$usdz   = $product->get_meta( '_yadak_model_usdz' );
		$poster = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' );
		?>
		<button type="button" class="button yadak-3d-open" data-yadak-3d>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 3 7v10l9 5 9-5V7z"/><path d="m3 7 9 5 9-5M12 12v10"/></svg>
			<?php esc_html_e( 'نمای سه‌بعدی و AR', 'yadak-core' ); ?>
		</button>
		<dialog class="yadak-3d-dialog" aria-label="<?php esc_attr_e( 'نمای سه‌بعدی محصول', 'yadak-core' ); ?>">
			<form method="dialog"><button class="yadak-3d-close" aria-label="<?php esc_attr_e( 'بستن', 'yadak-core' ); ?>">✕</button></form>
			<model-viewer
				data-src="<?php echo esc_url( $glb ); ?>"
				<?php if ( $usdz ) : ?>ios-src="<?php echo esc_url( $usdz ); ?>"<?php endif; ?>
				<?php if ( $poster ) : ?>poster="<?php echo esc_url( $poster ); ?>"<?php endif; ?>
				alt="<?php echo esc_attr( $product->get_name() ); ?>"
				dir="ltr" camera-controls touch-action="pan-y" auto-rotate shadow-intensity="1" exposure="1"
				ar ar-modes="webxr scene-viewer quick-look"
				style="width:100%;height:min(70vh,560px);background:#f5f7fa;border-radius:12px">
				<button slot="ar-button" class="button yadak-3d-ar"><?php esc_html_e( 'مشاهده در فضای واقعی (AR)', 'yadak-core' ); ?></button>
			</model-viewer>
			<p class="yadak-3d-help"><?php esc_html_e( 'با کشیدن بچرخانید، با دو انگشت یا اسکرول بزرگ‌نمایی کنید.', 'yadak-core' ); ?></p>
		</dialog>
		<script>
		( function () {
			var btn = document.querySelector( '[data-yadak-3d]' );
			var dlg = document.querySelector( '.yadak-3d-dialog' );
			if ( ! btn || ! dlg || typeof dlg.showModal !== 'function' ) { if ( btn ) { btn.hidden = true; } return; }
			var loaded = false;
			btn.addEventListener( 'click', function () {
				if ( ! loaded ) {
					loaded = true;
					var s = document.createElement( 'script' );
					s.type = 'module';
					s.src = <?php echo wp_json_encode( YADAK_CORE_URL . 'assets/vendor/model-viewer.min.js?ver=4.3.1' ); ?>;
					document.head.appendChild( s );
					var mv = dlg.querySelector( 'model-viewer' );
					mv.setAttribute( 'src', mv.getAttribute( 'data-src' ) );
				}
				dlg.showModal();
			} );
			dlg.addEventListener( 'click', function ( e ) { if ( e.target === dlg ) { dlg.close(); } } );
		} )();
		</script>
		<?php
	}
}
