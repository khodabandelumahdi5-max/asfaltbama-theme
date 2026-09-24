<?php
/**
 * Home page — sections 01 to 09.
 *
 * @package BavarTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bavar_library = class_exists( 'Bavar_Settings' ) ? (int) Bavar_Settings::get( 'page_library' ) : 0;
$bavar_what    = [ 'رشد کسب‌وکار', 'فروش', 'شبکه‌سازی', 'آموزش', 'توسعه‌ی فردی', 'کتاب و دانش' ];
?>
<main id="content" class="bv-home">

	<!-- 01 — Text logo + portrait -->
	<section class="bv-hero">
		<div class="bv-hero__logo bv-reveal">
			<?php bavar_wordmark( 'h1', 'bv-wordmark--hero' ); ?>
		</div>
		<figure class="bv-hero__portrait bv-reveal bv-reveal--image">
			<img src="<?php echo esc_url( bavar_opt( 'hero_image' ) ); ?>" alt="آرش الطافیان، بنیان‌گذار گروه باور" width="941" height="1672" fetchpriority="high" data-bv-parallax>
		</figure>
		<a class="bv-hero__scroll" href="#about" aria-label="ادامه"><span></span></a>
	</section>

	<!-- 02 — About -->
	<section class="bv-section bv-about" id="about">
		<div class="bv-wrap bv-wrap--narrow">
			<p class="bv-eyebrow bv-reveal"><span>۰۲</span> درباره‌ی گروه باور</p>
			<h2 class="bv-about__title bv-reveal">گروه توسعه کسب‌وکار باور</h2>
			<div class="bv-about__text bv-reveal">
				<?php
				foreach ( preg_split( '/\n\s*\n/', bavar_opt( 'about_text' ) ) as $bavar_p ) {
					echo '<p>' . esc_html( trim( $bavar_p ) ) . '</p>';
				}
				?>
			</div>
		</div>
	</section>

	<!-- 03 — What we do -->
	<section class="bv-section bv-what">
		<div class="bv-wrap">
			<p class="bv-eyebrow bv-reveal"><span>۰۳</span> حوزه‌های فعالیت</p>
			<ul class="bv-what__list">
				<?php foreach ( $bavar_what as $bavar_i => $bavar_item ) : ?>
					<li class="bv-reveal">
						<span class="bv-what__num"><?php echo esc_html( bavar_fa_num_safe( sprintf( '%02d', $bavar_i + 1 ) ) ); ?></span>
						<span class="bv-what__fa"><?php echo esc_html( $bavar_item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<!-- 04 — Products & services -->
	<section class="bv-section bv-services" id="paths">
		<div class="bv-wrap">
			<p class="bv-eyebrow bv-reveal"><span>۰۴</span> محصولات و خدمات</p>
			<h2 class="bv-title bv-reveal">مسیر خود را انتخاب کنید</h2>
			<div class="bv-reveal">
				<?php echo shortcode_exists( 'bavar_paths' ) ? do_shortcode( '[bavar_paths]' ) : ''; ?>
			</div>
		</div>
	</section>

	<!-- 05 — Library -->
	<section class="bv-section bv-home-library" id="library">
		<div class="bv-wrap">
			<div class="bv-section__head">
				<div>
					<p class="bv-eyebrow bv-reveal"><span>۰۵</span> پک‌های کتاب</p>
					<h2 class="bv-title bv-reveal">پک‌های برتر کتاب و آموزش گروه باور</h2>
				</div>
				<?php if ( $bavar_library ) : ?>
					<a class="bv-button bv-reveal" href="<?php echo esc_url( get_permalink( $bavar_library ) ); ?>">مشاهده‌ی همه‌ی پک‌ها</a>
				<?php endif; ?>
			</div>
			<div class="bv-reveal">
				<?php
				$bavar_featured = shortcode_exists( 'bavar_featured_packs' ) ? do_shortcode( '[bavar_featured_packs count="3"]' ) : '';
				echo $bavar_featured ? $bavar_featured : '<p class="bv-muted">پک‌های کتاب به‌زودی منتشر می‌شوند.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</div>
	</section>

	<!-- 06 — Philosophy -->
	<section class="bv-section bv-philosophy">
		<div class="bv-wrap bv-wrap--narrow">
			<p class="bv-eyebrow bv-reveal"><span>۰۶</span> فلسفه‌ی باور</p>
			<p class="bv-philosophy__title bv-reveal"><?php echo esc_html( bavar_opt( 'philosophy_title' ) ); ?></p>
			<p class="bv-philosophy__text bv-reveal"><?php echo esc_html( bavar_opt( 'philosophy_text' ) ); ?></p>
		</div>
	</section>

	<!-- 07 — Founder -->
	<section class="bv-section bv-founder" id="founder">
		<div class="bv-wrap bv-founder__grid">
			<div class="bv-founder__images">
				<figure class="bv-founder__main bv-reveal bv-reveal--image">
					<img src="<?php echo esc_url( bavar_opt( 'founder_image' ) ); ?>" alt="آرش الطافیان" width="941" height="1672" loading="lazy">
				</figure>
				<?php if ( bavar_opt( 'founder_image_2' ) ) : ?>
					<figure class="bv-founder__second bv-reveal bv-reveal--image">
						<img src="<?php echo esc_url( bavar_opt( 'founder_image_2' ) ); ?>" alt="" width="1122" height="1402" loading="lazy">
					</figure>
				<?php endif; ?>
			</div>
			<div class="bv-founder__text">
				<p class="bv-eyebrow bv-reveal"><span>۰۷</span> بنیان‌گذار</p>
				<h2 class="bv-founder__name bv-reveal">آرش الطافیان</h2>
				<p class="bv-founder__fa bv-reveal">بنیان‌گذار و مدیر گروه توسعه کسب‌وکار باور</p>
				<p class="bv-founder__bio bv-reveal"><?php echo esc_html( bavar_opt( 'founder_bio' ) ); ?></p>
			</div>
		</div>
	</section>

	<!-- 08 — Final CTA -->
	<section class="bv-section bv-final">
		<div class="bv-wrap bv-final__inner">
			<p class="bv-final__title bv-reveal">به دنیای باور<br>وارد شوید</p>
			<a class="bv-button bv-button--solid bv-reveal" href="#paths">انتخاب مسیر</a>
		</div>
	</section>

</main>
<?php
get_footer();
