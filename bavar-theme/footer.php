<?php
/**
 * Footer.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'footer' ) ) :
	$bavar_library = class_exists( 'Bavar_Settings' ) ? (int) Bavar_Settings::get( 'page_library' ) : 0;
	$bavar_links   = array_filter(
		[
			'درباره'      => home_url( '/#about' ),
			'دوره‌ها'      => home_url( '/#paths' ),
			'پک‌های کتاب'  => $bavar_library ? get_permalink( $bavar_library ) : '',
			'تماس'        => '#contact',
			'قوانین'      => bavar_opt( 'terms_url' ),
			'حریم خصوصی'  => bavar_opt( 'privacy_url' ),
			'اینستاگرام'  => bavar_opt( 'instagram' ),
			'تلگرام'      => bavar_opt( 'telegram' ),
		]
	);
	$bavar_phone = bavar_opt( 'phone' );
	$bavar_email = bavar_opt( 'email' );
	?>
	<footer class="bv-footer" id="contact">
		<div class="bv-wrap">
			<?php bavar_wordmark( 'p', 'bv-wordmark--footer' ); ?>

			<?php if ( $bavar_phone || $bavar_email ) : ?>
				<p class="bv-footer__contact">
					<?php if ( $bavar_phone ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $bavar_phone ) ); ?>" dir="ltr"><?php echo esc_html( $bavar_phone ); ?></a>
					<?php endif; ?>
					<?php if ( $bavar_email ) : ?>
						<a href="mailto:<?php echo esc_attr( $bavar_email ); ?>" dir="ltr"><?php echo esc_html( $bavar_email ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<nav class="bv-footer__links" aria-label="پیوندهای پایین صفحه">
				<?php foreach ( $bavar_links as $label => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" <?php echo in_array( $label, [ 'اینستاگرام', 'تلگرام' ], true ) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<p class="bv-footer__copy">© <?php echo esc_html( bavar_fa_num_safe( wp_date( 'Y' ) ) ); ?> گروه توسعه کسب‌وکار باور. تمامی حقوق محفوظ است.</p>
		</div>
	</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
