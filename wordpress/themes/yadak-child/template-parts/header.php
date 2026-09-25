<?php
/**
 * Site header: top bar, brand, part search, account/cart, vehicle chip, menu.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yadak_has_wc   = class_exists( 'WooCommerce' );
$yadak_vehicle  = class_exists( 'Yadak_Fitment' ) ? Yadak_Fitment::current_vehicle() : null;
$yadak_account  = $yadak_has_wc ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$yadak_menu     = wp_nav_menu(
	array(
		'theme_location' => 'menu-1',
		'fallback_cb'    => false,
		'container'      => false,
		'echo'           => false,
		'menu_class'     => 'yadak-nav__list',
	)
);
?>
<header id="site-header" class="yadak-header">
	<div class="yadak-topbar">
		<div class="yadak-container yadak-topbar__inner">
			<span><?php echo yadak_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', yadak_opt( 'phone' ) ) ); ?>" dir="ltr"><?php echo esc_html( yadak_opt( 'phone' ) ); ?></a><span class="yadak-topbar__hours"> · <?php echo esc_html( yadak_opt( 'hours' ) ); ?></span></span>
			<?php if ( yadak_opt( 'b2b_url' ) ) : ?>
				<a class="yadak-topbar__b2b" href="<?php echo esc_url( yadak_opt( 'b2b_url' ) ); ?>"><?php esc_html_e( 'فروش همکاری و عمده', 'yadak-child' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="yadak-container yadak-header__main">
		<button class="yadak-header__toggle" type="button" aria-controls="yadak-nav" aria-expanded="false" aria-label="<?php esc_attr_e( 'منو', 'yadak-child' ); ?>">
			<?php echo yadak_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<div class="yadak-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="yadak-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<img class="yadak-brand__mark" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/brand/mark.svg' ); ?>" width="40" height="40" alt="">
					<span class="yadak-brand__text">
						<span class="yadak-brand__name"><?php bloginfo( 'name' ); ?></span>
						<?php if ( yadak_opt( 'brand_latin' ) ) : ?><span class="yadak-brand__latin" lang="en"><?php echo esc_html( yadak_opt( 'brand_latin' ) ); ?></span><?php endif; ?>
					</span>
				</a>
			<?php endif; ?>
		</div>

		<div class="yadak-header__search">
			<?php yadak_search_form( 'yadak-header-s' ); ?>
		</div>

		<div class="yadak-header__actions">
			<a class="yadak-action" href="<?php echo esc_url( $yadak_account ); ?>">
				<?php echo yadak_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="yadak-action__label"><?php echo is_user_logged_in() ? esc_html__( 'حساب من', 'yadak-child' ) : esc_html__( 'ورود / ثبت‌نام', 'yadak-child' ); ?></span>
			</a>
			<?php if ( $yadak_has_wc ) : ?>
				<a class="yadak-action yadak-action--cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'سبد خرید', 'yadak-child' ); ?>">
					<?php echo yadak_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo yadak_cart_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<nav id="yadak-nav" class="yadak-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'yadak-child' ); ?>">
		<div class="yadak-container yadak-nav__inner">
			<?php
			if ( $yadak_menu ) {
				echo $yadak_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $yadak_has_wc ) {
				// No menu yet: list the top product categories.
				$yadak_cats = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'parent'     => 0,
						'hide_empty' => true,
						'number'     => 8,
						'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
					)
				);
				echo '<ul class="yadak-nav__list"><li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'همه قطعات', 'yadak-child' ) . '</a></li>';
				if ( ! is_wp_error( $yadak_cats ) ) {
					foreach ( $yadak_cats as $yadak_cat ) {
						echo '<li><a href="' . esc_url( get_term_link( $yadak_cat ) ) . '">' . esc_html( $yadak_cat->name ) . '</a></li>';
					}
				}
				echo '</ul>';
			}
			?>
			<?php if ( $yadak_vehicle ) : ?>
				<a class="yadak-vehicle-chip" href="<?php echo esc_url( get_term_link( $yadak_vehicle ) ); ?>">
					<?php echo yadak_icon( 'car' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( Yadak_Fitment::path( $yadak_vehicle ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</nav>
</header>
