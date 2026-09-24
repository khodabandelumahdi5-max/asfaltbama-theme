<?php
/**
 * Header.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$bavar_account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bavar' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content">رفتن به محتوا</a>

<?php if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'header' ) ) : ?>
<header class="bv-header" data-bv-header dir="ltr">
	<div class="bv-header__inner">
		<a class="bv-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="BAVAR GROUP">BAVAR <span>GROUP</span></a>

		<nav class="bv-header__nav" id="bv-nav" aria-label="منوی اصلی" dir="rtl">
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'bavar-primary',
					'container'      => false,
					'menu_class'     => 'bv-menu',
					'depth'          => 1,
					'fallback_cb'    => 'bavar_default_menu',
				]
			);
			?>
			<div class="bv-header__mobile-actions">
				<a href="<?php echo esc_url( $bavar_account ); ?>">حساب کاربری</a>
			</div>
		</nav>

		<div class="bv-header__actions">
			<a class="bv-header__account" href="<?php echo esc_url( $bavar_account ); ?>">حساب کاربری</a>
			<a class="bv-header__join" href="<?php echo esc_url( home_url( '/#paths' ) ); ?>">ورود به باور</a>
			<button class="bv-header__toggle" type="button" aria-controls="bv-nav" aria-expanded="false" data-bv-toggle>
				<span></span><span></span><span class="screen-reader-text">منو</span>
			</button>
		</div>
	</div>
</header>
<?php endif; ?>
