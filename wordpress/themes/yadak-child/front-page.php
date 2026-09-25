<?php
/**
 * Homepage: vehicle-first hero, makes, categories, best sellers, trust, B2B.
 *
 * Content written in the editor on the static front page is shown under the
 * built-in sections, so extra blocks can be added without code.
 *
 * @package YadakChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$yadak_has_wc = class_exists( 'WooCommerce' );
$yadak_makes  = class_exists( 'Yadak_Fitment' ) ? Yadak_Fitment::makes_by_origin() : array();
$yadak_origin = class_exists( 'Yadak_Fitment' ) ? Yadak_Fitment::origins() : array();
$yadak_cats   = $yadak_has_wc ? get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'parent'     => 0,
		'hide_empty' => false,
		'number'     => 8,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		'orderby'    => 'menu_order',
	)
) : array();
?>
<main id="content" class="yadak-home">

	<section class="yadak-hero<?php echo get_theme_mod( 'yadak_hero_image', '' ) ? ' has-photo' : ''; ?>" style="<?php echo esc_attr( yadak_hero_style() ); ?>">
		<div class="yadak-hero__beams" aria-hidden="true"></div>
		<div class="yadak-container yadak-hero__inner">
			<div class="yadak-hero__copy">
				<h1><?php echo esc_html( yadak_opt( 'hero_title' ) ); ?></h1>
				<p><?php echo esc_html( yadak_opt( 'hero_subtitle' ) ); ?></p>
				<div class="yadak-hero__panel">
					<?php
					if ( shortcode_exists( 'yadak_vehicle_selector' ) ) {
						echo do_shortcode( '[yadak_vehicle_selector]' );
					}
					?>
					<div class="yadak-hero__or"><span><?php esc_html_e( 'یا جستجو با نام یا شماره فنی', 'yadak-child' ); ?></span></div>
					<?php yadak_search_form( 'yadak-hero-s' ); ?>
				</div>
			</div>
			<div class="yadak-stage">
				<div class="yadak-stage__canvas">
					<div class="yadak-stage__loading" aria-hidden="true"></div>
					<div class="yadak-stage__tip" hidden></div>
				</div>
				<p class="yadak-stage__hint"><?php esc_html_e( 'خودرو را بچرخانید و روی هر قطعه بزنید', 'yadak-child' ); ?></p>
				<nav class="yadak-stage__chips" aria-label="<?php esc_attr_e( 'دسته‌های قطعات بدنه و چراغ', 'yadak-child' ); ?>">
					<?php
					$yadak_seen = array();
					foreach ( yadak_3d_parts() as $yadak_key => $yadak_part ) {
						if ( isset( $yadak_seen[ $yadak_part['label'] ] ) ) {
							continue;
						}
						$yadak_seen[ $yadak_part['label'] ] = true;
						echo '<a data-part="' . esc_attr( $yadak_key ) . '" href="' . esc_url( $yadak_part['url'] ) . '">' . esc_html( $yadak_part['label'] ) . '</a>';
					}
					?>
				</nav>
			</div>
		</div>
	</section>

	<?php if ( $yadak_makes ) : ?>
		<section class="yadak-section">
			<div class="yadak-container">
				<div class="yadak-section__head">
					<h2><?php esc_html_e( 'قطعه بر اساس خودرو', 'yadak-child' ); ?></h2>
				</div>
				<div class="yadak-origins">
					<?php foreach ( $yadak_makes as $yadak_key => $yadak_group ) : ?>
						<div class="yadak-origin yadak-origin--<?php echo esc_attr( $yadak_key ); ?>">
							<h3><?php echo esc_html( $yadak_origin[ $yadak_key ] ); ?></h3>
							<ul class="yadak-makes">
								<?php foreach ( $yadak_group as $yadak_make ) : ?>
									<li><a href="<?php echo esc_url( get_term_link( $yadak_make ) ); ?>"><?php echo esc_html( $yadak_make->name ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $yadak_cats ) && $yadak_cats ) : ?>
		<section class="yadak-section">
			<div class="yadak-container">
				<div class="yadak-section__head">
					<h2><?php esc_html_e( 'بخش‌های فروشگاه', 'yadak-child' ); ?></h2>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'همه قطعات', 'yadak-child' ); ?> <?php echo yadak_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</div>
				<div class="yadak-depts">
					<?php foreach ( $yadak_cats as $yadak_i => $yadak_cat ) : ?>
						<?php
						$yadak_subs  = get_terms(
							array(
								'taxonomy'   => 'product_cat',
								'parent'     => $yadak_cat->term_id,
								'hide_empty' => false,
								'number'     => 8,
							)
						);
						$yadak_thumb = (int) get_term_meta( $yadak_cat->term_id, 'thumbnail_id', true );
						?>
						<article class="yadak-dept yadak-dept--<?php echo esc_attr( $yadak_i % 3 ); ?>">
							<?php if ( $yadak_thumb ) : ?>
								<?php echo wp_get_attachment_image( $yadak_thumb, 'large', false, array( 'class' => 'yadak-dept__photo', 'alt' => '', 'loading' => 'lazy' ) ); ?>
							<?php endif; ?>
							<span class="yadak-dept__art"><?php echo yadak_cat_icon( $yadak_cat, 180 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div class="yadak-dept__body">
								<span class="yadak-dept__icon"><?php echo yadak_cat_icon( $yadak_cat, 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<h3><a href="<?php echo esc_url( get_term_link( $yadak_cat ) ); ?>"><?php echo esc_html( $yadak_cat->name ); ?></a></h3>
								<?php if ( ! is_wp_error( $yadak_subs ) && $yadak_subs ) : ?>
									<ul>
										<?php foreach ( $yadak_subs as $yadak_sub ) : ?>
											<li><a href="<?php echo esc_url( get_term_link( $yadak_sub ) ); ?>"><?php echo esc_html( $yadak_sub->name ); ?></a></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $yadak_has_wc ) : ?>
		<section class="yadak-section">
			<div class="yadak-container">
				<div class="yadak-section__head">
					<h2><?php esc_html_e( 'پرفروش‌ترین قطعات', 'yadak-child' ); ?></h2>
				</div>
				<?php echo do_shortcode( '[products limit="8" columns="4" orderby="popularity"]' ); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$yadak_brand_strip = shortcode_exists( 'yadak_brands' ) ? do_shortcode( '[yadak_brands layout="strip" limit="6"]' ) : '';
	if ( $yadak_brand_strip ) :
		$yadak_brands_page = get_page_by_path( 'brands' );
		?>
		<section class="yadak-section">
			<div class="yadak-container">
				<div class="yadak-section__head">
					<h2><?php esc_html_e( 'برندهای معتبر', 'yadak-child' ); ?></h2>
					<?php if ( $yadak_brands_page && 'publish' === $yadak_brands_page->post_status ) : ?>
						<a href="<?php echo esc_url( get_permalink( $yadak_brands_page ) ); ?>"><?php esc_html_e( 'همه برندها', 'yadak-child' ); ?> <?php echo yadak_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php endif; ?>
				</div>
				<?php echo $yadak_brand_strip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the shortcode. ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="yadak-section yadak-trust">
		<div class="yadak-container">
			<ul class="yadak-trust__grid">
				<?php
				$yadak_trust = array(
					array( 'shield', __( 'ضمانت اصالت', 'yadak-child' ), __( 'کیفیت هر قطعه (اصلی، OEM، افترمارکت) شفاف مشخص شده است.', 'yadak-child' ) ),
					array( 'receipt', __( 'فاکتور رسمی', 'yadak-child' ), __( 'برای هر خرید فاکتور صادر می‌شود؛ مناسب تعمیرگاه‌ها و شرکت‌ها.', 'yadak-child' ) ),
					array( 'truck', __( 'ارسال به سراسر ایران', 'yadak-child' ), __( 'ارسال با پست و باربری، همراه کد رهگیری.', 'yadak-child' ) ),
					array( 'headset', __( 'مشاوره قبل از خرید', 'yadak-child' ), __( 'مطمئن نیستید کدام قطعه؟ کارشناس ما کمک می‌کند.', 'yadak-child' ) ),
				);
				foreach ( $yadak_trust as $yadak_item ) :
					?>
					<li>
						<?php echo yadak_icon( $yadak_item[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<strong><?php echo esc_html( $yadak_item[1] ); ?></strong>
						<span><?php echo esc_html( $yadak_item[2] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="yadak-section">
		<div class="yadak-container">
			<div class="yadak-b2b">
				<div>
					<h2><?php esc_html_e( 'مکانیک، تعمیرگاه یا فروشنده قطعه هستید؟', 'yadak-child' ); ?></h2>
					<p><?php esc_html_e( 'با حساب همکار، قیمت ویژه همکار، خرید اعتباری، صورت‌حساب آنلاین و کارشناس فروش اختصاصی داشته باشید.', 'yadak-child' ); ?></p>
				</div>
				<a class="yadak-btn yadak-btn--accent" href="<?php echo esc_url( yadak_opt( 'b2b_url' ) ? yadak_opt( 'b2b_url' ) : ( $yadak_has_wc ? wc_get_page_permalink( 'myaccount' ) : wp_registration_url() ) ); ?>"><?php esc_html_e( 'درخواست حساب همکار', 'yadak-child' ); ?></a>
			</div>
		</div>
	</section>

	<?php
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) ) :
			?>
			<section class="yadak-section">
				<div class="yadak-container yadak-prose"><?php the_content(); ?></div>
			</section>
			<?php
		endif;
	endwhile;
	?>
</main>
<?php
get_footer();
