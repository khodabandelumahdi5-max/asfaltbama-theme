<?php
/**
 * Site footer and the mobile bottom bar.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yadak_has_wc      = class_exists( 'WooCommerce' );
$yadak_footer_menu = wp_nav_menu(
	array(
		'theme_location' => 'menu-2',
		'fallback_cb'    => false,
		'container'      => false,
		'echo'           => false,
		'menu_class'     => 'yadak-footer__links',
		'depth'          => 1,
	)
);
?>
<footer id="site-footer" class="yadak-footer">
	<div class="yadak-container yadak-footer__grid">
		<div class="yadak-footer__col yadak-footer__about">
			<p class="yadak-footer__brand"><?php bloginfo( 'name' ); ?></p>
			<p><?php bloginfo( 'description' ); ?></p>
		</div>

		<div class="yadak-footer__col">
			<p class="yadak-footer__title"><?php esc_html_e( 'دسترسی سریع', 'yadak-child' ); ?></p>
			<?php
			if ( $yadak_footer_menu ) {
				echo $yadak_footer_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $yadak_has_wc ) {
				echo '<ul class="yadak-footer__links">';
				echo '<li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'همه قطعات', 'yadak-child' ) . '</a></li>';
				echo '<li><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'حساب کاربری', 'yadak-child' ) . '</a></li>';
				echo '<li><a href="' . esc_url( wc_get_endpoint_url( 'garage', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'گاراژ من', 'yadak-child' ) . '</a></li>';
				echo '<li><a href="' . esc_url( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'پیگیری سفارش', 'yadak-child' ) . '</a></li>';
				echo '</ul>';
			}
			?>
		</div>

		<div class="yadak-footer__col">
			<p class="yadak-footer__title"><?php esc_html_e( 'تماس با ما', 'yadak-child' ); ?></p>
			<ul class="yadak-footer__links">
				<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', yadak_opt( 'phone' ) ) ); ?>" dir="ltr"><?php echo esc_html( yadak_opt( 'phone' ) ); ?></a></li>
				<li><?php echo esc_html( yadak_opt( 'hours' ) ); ?></li>
				<li><?php echo esc_html( yadak_opt( 'address' ) ); ?></li>
			</ul>
		</div>

		<div class="yadak-footer__col yadak-footer__seal">
			<?php
			$yadak_seal = yadak_opt( 'trust_seal' );
			if ( $yadak_seal ) {
				echo yadak_sanitize_seal( $yadak_seal ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( current_user_can( 'edit_theme_options' ) ) {
				echo '<p class="yadak-footer__hint">' . esc_html__( 'جای نماد اعتماد: کد اینماد را در «سفارشی‌سازی › اطلاعات فروشگاه» وارد کنید.', 'yadak-child' ) . '</p>';
			}
			?>
		</div>
	</div>
	<div class="yadak-footer__bottom">
		<div class="yadak-container">
			<?php
			/* translators: 1: year, 2: site name */
			echo esc_html( sprintf( __( '© %1$s %2$s — همه حقوق محفوظ است.', 'yadak-child' ), wp_date( 'Y' ), get_bloginfo( 'name' ) ) );
			?>
		</div>
	</div>
</footer>

<?php if ( $yadak_has_wc ) : ?>
<nav class="yadak-bottombar" aria-label="<?php esc_attr_e( 'دسترسی سریع موبایل', 'yadak-child' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo yadak_icon( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'خانه', 'yadak-child' ); ?></span></a>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo yadak_icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'قطعات', 'yadak-child' ); ?></span></a>
	<a href="<?php echo esc_url( wc_get_endpoint_url( 'garage', '', wc_get_page_permalink( 'myaccount' ) ) ); ?>"><?php echo yadak_icon( 'car' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'گاراژ', 'yadak-child' ); ?></span></a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo yadak_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'سبد', 'yadak-child' ); ?></span></a>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo yadak_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'حساب', 'yadak-child' ); ?></span></a>
</nav>
<?php endif; ?>
