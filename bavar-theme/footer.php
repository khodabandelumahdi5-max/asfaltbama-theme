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
			'About'     => home_url( '/#about' ),
			'Courses'   => home_url( '/#paths' ),
			'Library'   => $bavar_library ? get_permalink( $bavar_library ) : '',
			'Contact'   => '#contact',
			'Terms'     => bavar_opt( 'terms_url' ),
			'Privacy'   => bavar_opt( 'privacy_url' ),
			'Instagram' => bavar_opt( 'instagram' ),
			'Telegram'  => bavar_opt( 'telegram' ),
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

			<nav class="bv-footer__links" dir="ltr" aria-label="Footer">
				<?php foreach ( $bavar_links as $label => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" <?php echo in_array( $label, [ 'Instagram', 'Telegram' ], true ) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<p class="bv-footer__copy" dir="ltr">© <?php echo esc_html( gmdate( 'Y' ) ); ?> BAVAR GROUP. All rights reserved.</p>
		</div>
	</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
