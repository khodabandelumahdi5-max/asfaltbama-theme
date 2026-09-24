<?php
/**
 * Front-end: entry gate, three-question modal, path cards and request forms.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'wp_footer', [ __CLASS__, 'modals' ] );
		add_shortcode( 'bavar_paths', [ __CLASS__, 'shortcode_paths' ] );
		add_shortcode( 'bavar_request', [ __CLASS__, 'shortcode_request' ] );
	}

	/**
	 * Known visitor details (cookie or logged-in customer).
	 *
	 * @return array|null
	 */
	public static function known_person() {
		$lead = Bavar_Leads::current();
		if ( $lead ) {
			return [
				'name'  => $lead['full_name'],
				'phone' => $lead['phone'],
				'job'   => $lead['job'],
			];
		}
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$phone = bavar_normalize_phone( get_user_meta( $user_id, 'billing_phone', true ) );
			if ( $phone ) {
				$user = wp_get_current_user();
				return [
					'name'  => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
					'phone' => $phone,
					'job'   => (string) get_user_meta( $user_id, 'billing_job', true ),
				];
			}
		}
		return null;
	}

	/**
	 * Scripts and styles.
	 */
	public static function assets() {
		wp_enqueue_style( 'bavar-core', BAVAR_CORE_URL . 'assets/css/bavar-core.css', [], BAVAR_CORE_VERSION );
		wp_enqueue_script( 'bavar-core', BAVAR_CORE_URL . 'assets/js/bavar-core.js', [], BAVAR_CORE_VERSION, true );

		$paths = [];
		foreach ( Bavar_Settings::get( 'paths' ) as $key => $path ) {
			$paths[ $key ] = [
				'title'     => $path['title'],
				'subtitle'  => $path['subtitle'],
				'questions' => [ $path['q1'], $path['q2'], $path['q3'] ],
				'target'    => Bavar_Settings::path_target( $key ),
			];
		}

		$gate = Bavar_Settings::get( 'gate_mode' );
		// Never block checkout / account pages or admins previewing the site.
		if ( current_user_can( 'manage_options' ) || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) ) {
			$gate = 'off';
		}

		wp_localize_script(
			'bavar-core',
			'BAVAR',
			[
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'gate'   => $gate,
				'person' => self::known_person(),
				'paths'  => $paths,
			]
		);
	}

	/**
	 * Person fields shared by every form.
	 *
	 * @param string $prefix Unique id prefix.
	 */
	private static function person_fields( $prefix ) {
		$fields = [
			'name'  => [ 'نام و نام خانوادگی', 'text', 'name' ],
			'phone' => [ 'شماره تماس', 'tel', 'tel' ],
			'job'   => [ 'شغل / حوزه‌ی فعالیت', 'text', 'organization-title' ],
		];
		foreach ( $fields as $name => $f ) {
			printf(
				'<label class="bv-field"><span>%1$s</span><input type="%2$s" name="%3$s" id="%4$s-%3$s" autocomplete="%5$s" %6$s required></label>',
				esc_html( $f[0] ),
				esc_attr( $f[1] ),
				esc_attr( $name ),
				esc_attr( $prefix ),
				esc_attr( $f[2] ),
				'phone' === $name ? 'inputmode="tel" dir="ltr" placeholder="09xx xxx xxxx"' : ''
			);
		}
		echo '<input type="text" name="website" class="bv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
	}

	/**
	 * Entry gate + questions modal markup.
	 */
	public static function modals() {
		$title = Bavar_Settings::get( 'gate_title' );
		$text  = Bavar_Settings::get( 'gate_text' );
		?>
		<div class="bv-modal" id="bv-gate" role="dialog" aria-modal="true" aria-labelledby="bv-gate-title" hidden>
			<div class="bv-modal__panel">
				<button type="button" class="bv-modal__close" data-bv-close aria-label="بستن" hidden>&times;</button>
				<p class="bv-modal__mark" dir="ltr">BAVAR GROUP</p>
				<h2 id="bv-gate-title" class="bv-modal__title"><?php echo esc_html( $title ); ?></h2>
				<p class="bv-modal__text"><?php echo esc_html( $text ); ?></p>
				<form class="bv-form" data-bv-form="gate" novalidate>
					<?php self::person_fields( 'bv-gate' ); ?>
					<p class="bv-form__error" role="alert" hidden></p>
					<button type="submit" class="bv-button bv-button--solid">ورود به BAVAR</button>
				</form>
			</div>
		</div>

		<div class="bv-modal" id="bv-questions" role="dialog" aria-modal="true" aria-labelledby="bv-q-title" hidden>
			<div class="bv-modal__panel bv-modal__panel--wide">
				<button type="button" class="bv-modal__close" data-bv-close aria-label="بستن">&times;</button>
				<p class="bv-modal__mark" data-bv-q-subtitle></p>
				<h2 id="bv-q-title" class="bv-modal__title" data-bv-q-title></h2>
				<p class="bv-modal__text">برای اینکه مسیر مناسب شما را پیشنهاد دهیم، به سه سؤال کوتاه پاسخ دهید.</p>
				<form class="bv-form" data-bv-form="path" novalidate>
					<input type="hidden" name="path" value="">
					<input type="hidden" name="product_id" value="">
					<?php foreach ( [ 1, 2, 3 ] as $i ) : ?>
						<label class="bv-field bv-field--q">
							<span><em><?php echo esc_html( bavar_fa_num( '0' . $i ) ); ?></em> <b data-bv-q="<?php echo esc_attr( $i ); ?>"></b></span>
							<textarea name="a<?php echo esc_attr( $i ); ?>" rows="2" required></textarea>
						</label>
					<?php endforeach; ?>
					<div class="bv-form__person"><?php self::person_fields( 'bv-q' ); ?></div>
					<p class="bv-form__error" role="alert" hidden></p>
					<button type="submit" class="bv-button bv-button--solid">ثبت و ادامه</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * [bavar_paths] — the four entry cards.
	 *
	 * @return string
	 */
	public static function shortcode_paths() {
		$i = 0;
		ob_start();
		echo '<div class="bv-paths">';
		foreach ( Bavar_Settings::get( 'paths' ) as $key => $path ) {
			++$i;
			?>
			<a class="bv-path" href="<?php echo esc_url( Bavar_Settings::path_target( $key ) ); ?>" data-bv-path="<?php echo esc_attr( $key ); ?>">
				<span class="bv-path__num" dir="ltr"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></span>
				<span class="bv-path__en" dir="ltr"><?php echo esc_html( $path['en'] ); ?></span>
				<span class="bv-path__title"><?php echo esc_html( $path['title'] ); ?></span>
				<span class="bv-path__sub"><?php echo esc_html( $path['subtitle'] ); ?></span>
				<span class="bv-path__desc"><?php echo esc_html( $path['desc'] ); ?></span>
				<span class="bv-path__cta"><?php echo esc_html( $path['cta'] ); ?> <i aria-hidden="true">←</i></span>
			</a>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [bavar_request path="simorgh|consult"] — inline request form.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_request( $atts ) {
		$atts = shortcode_atts( [ 'path' => 'consult' ], $atts );
		$key  = sanitize_key( $atts['path'] );
		$path = Bavar_Settings::path( $key );
		if ( ! $path ) {
			return '';
		}
		ob_start();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['bavar_sent'] ) ) {
			?>
			<div class="bv-request bv-request--done">
				<p class="bv-request__mark" dir="ltr">THANK YOU</p>
				<h3>درخواست شما ثبت شد</h3>
				<p>تیم BAVAR GROUP به‌زودی با شما تماس می‌گیرد.</p>
			</div>
			<?php
			return ob_get_clean();
		}
		?>
		<div class="bv-request">
			<p class="bv-request__mark" dir="ltr"><?php echo esc_html( $path['en'] ); ?></p>
			<h3><?php echo esc_html( $path['cta'] ); ?></h3>
			<form class="bv-form" data-bv-form="path" data-bv-inline novalidate>
				<input type="hidden" name="path" value="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( [ 1, 2, 3 ] as $i ) : ?>
					<label class="bv-field bv-field--q">
						<span><em><?php echo esc_html( bavar_fa_num( '0' . $i ) ); ?></em> <b><?php echo esc_html( $path[ 'q' . $i ] ); ?></b></span>
						<textarea name="a<?php echo esc_attr( $i ); ?>" rows="2" required></textarea>
					</label>
				<?php endforeach; ?>
				<div class="bv-form__person"><?php self::person_fields( 'bv-r-' . $key ); ?></div>
				<p class="bv-form__error" role="alert" hidden></p>
				<button type="submit" class="bv-button bv-button--solid">ثبت درخواست</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
