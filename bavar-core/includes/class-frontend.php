<?php
/**
 * Front-end: entry form, section form modal, section cards, request forms
 * and the page-view beacon.
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
	 * Known visitor (cookie or logged-in customer).
	 *
	 * @return array|null { first_name, last_name, phone, job }
	 */
	public static function known_person() {
		$lead = Bavar_CRM::current();
		if ( $lead ) {
			unset( $lead['id'] );
			return $lead;
		}
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$phone = bavar_normalize_phone( get_user_meta( $user_id, 'billing_phone', true ) );
			if ( $phone ) {
				$user = wp_get_current_user();
				return [
					'first_name' => (string) $user->first_name,
					'last_name'  => (string) $user->last_name,
					'phone'      => $phone,
					'job'        => (string) get_user_meta( $user_id, 'billing_job', true ),
				];
			}
		}
		return null;
	}

	/**
	 * Which section / product the current page belongs to (for tracking and
	 * for asking the visitor's number before showing course details).
	 *
	 * @return array|null { path, product_id, require }
	 */
	private static function view_context() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$id = get_queried_object_id();
			return [
				'path'       => Bavar_CRM::path_of_product( $id ),
				'product_id' => $id,
				'require'    => 'course' === bavar_product_kind( $id ),
			];
		}
		if ( is_page() ) {
			$id = get_queried_object_id();
			foreach ( [ 'library', 'simorgh', 'consult' ] as $key ) {
				if ( $id && (int) Bavar_Settings::get( 'page_' . $key ) === $id ) {
					return [
						'path'       => $key,
						'product_id' => 0,
						'require'    => 'simorgh' === $key,
					];
				}
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
				'desc'      => $path['desc'],
				'questions' => Bavar_Settings::questions( $key ),
				'fields'    => Bavar_Settings::fields( $key ),
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
				'view'   => self::view_context(),
				'admin'  => current_user_can( 'manage_options' ),
			]
		);
	}

	/**
	 * Person inputs.
	 *
	 * @param string   $prefix Unique id prefix.
	 * @param string[] $only   Fields to render (all when empty).
	 */
	public static function person_inputs( $prefix, array $only = [] ) {
		$meta = [
			'first_name' => [ 'text', 'given-name', '' ],
			'last_name'  => [ 'text', 'family-name', '' ],
			'phone'      => [ 'tel', 'tel', 'inputmode="tel" dir="ltr" placeholder="۰۹۱۲ ۱۲۳ ۴۵۶۷"' ],
			'job'        => [ 'text', 'organization-title', '' ],
		];
		echo '<div class="bv-form__person">';
		foreach ( Bavar_CRM::person_fields() as $name => $conf ) {
			if ( $only && ! in_array( $name, $only, true ) ) {
				continue;
			}
			printf(
				'<label class="bv-field bv-field--%3$s" data-bv-field="%3$s"><span>%1$s</span><input type="%2$s" name="%3$s" id="%4$s-%3$s" autocomplete="%5$s" %6$s required></label>',
				esc_html( $conf[0] ),
				esc_attr( $meta[ $name ][0] ),
				esc_attr( $name ),
				esc_attr( $prefix ),
				esc_attr( $meta[ $name ][1] ),
				$meta[ $name ][2] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes.
			);
		}
		echo '</div><input type="text" name="website" class="bv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
	}

	/**
	 * Entry form + section form markup.
	 */
	public static function modals() {
		?>
		<div class="bv-modal" id="bv-gate" role="dialog" aria-modal="true" aria-labelledby="bv-gate-title" hidden>
			<div class="bv-modal__panel">
				<button type="button" class="bv-modal__close" data-bv-close aria-label="بستن" hidden>&times;</button>
				<p class="bv-modal__mark" dir="ltr">BAVAR GROUP</p>
				<h2 id="bv-gate-title" class="bv-modal__title"><?php echo esc_html( Bavar_Settings::get( 'gate_title' ) ); ?></h2>
				<p class="bv-modal__text"><?php echo esc_html( Bavar_Settings::get( 'gate_text' ) ); ?></p>
				<form class="bv-form" data-bv-form="gate" novalidate>
					<input type="hidden" name="fields" value="<?php echo esc_attr( Bavar_Settings::DEFAULT_FIELDS ); ?>">
					<?php self::person_inputs( 'bv-gate' ); ?>
					<p class="bv-form__error" role="alert" hidden></p>
					<button type="submit" class="bv-button bv-button--solid">ورود</button>
				</form>
			</div>
		</div>

		<div class="bv-modal" id="bv-questions" role="dialog" aria-modal="true" aria-labelledby="bv-q-title" hidden>
			<div class="bv-modal__panel bv-modal__panel--wide">
				<button type="button" class="bv-modal__close" data-bv-close aria-label="بستن">&times;</button>
				<p class="bv-modal__mark" dir="ltr">BAVAR GROUP</p>
				<h2 id="bv-q-title" class="bv-modal__title" data-bv-q-title></h2>
				<p class="bv-modal__text">برای ادامه، اطلاعات خود را وارد کنید.</p>
				<form class="bv-form" data-bv-form="path" novalidate>
					<input type="hidden" name="path" value="">
					<input type="hidden" name="product_id" value="">
					<input type="hidden" name="fields" value="">
					<div class="bv-form__questions" data-bv-questions></div>
					<?php self::person_inputs( 'bv-q' ); ?>
					<p class="bv-form__error" role="alert" hidden></p>
					<button type="submit" class="bv-button bv-button--solid">ثبت و ادامه</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * [bavar_paths] — the four section cards.
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
				<span class="bv-path__num"><?php echo esc_html( bavar_fa_num( sprintf( '%02d', $i ) ) ); ?></span>
				<span class="bv-path__title"><?php echo esc_html( $path['title'] ); ?></span>
				<?php if ( $path['desc'] ) : ?>
					<span class="bv-path__desc"><?php echo esc_html( $path['desc'] ); ?></span>
				<?php endif; ?>
				<span class="bv-path__cta"><?php echo esc_html( $path['cta'] ); ?> <i aria-hidden="true">←</i></span>
			</a>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [bavar_request path="simorgh|consult" fields="phone" title="…" button="…"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_request( $atts ) {
		$atts = shortcode_atts(
			[
				'path'   => 'consult',
				'fields' => '',
				'title'  => '',
				'button' => '',
			],
			$atts
		);
		$key  = sanitize_key( $atts['path'] );
		$path = Bavar_Settings::path( $key );
		if ( ! $path ) {
			return '';
		}
		$fields = $atts['fields'] ? array_values( array_intersect( array_keys( Bavar_CRM::person_fields() ), array_map( 'trim', explode( ',', $atts['fields'] ) ) ) ) : Bavar_Settings::fields( $key );
		if ( ! in_array( 'phone', $fields, true ) ) {
			$fields[] = 'phone';
		}
		$questions = Bavar_Settings::questions( $key );

		ob_start();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['bavar_sent'] ) ) {
			?>
			<div class="bv-request bv-request--done" id="bv-request">
				<h3>درخواست شما ثبت شد</h3>
				<p>تیم گروه باور به‌زودی با شما تماس می‌گیرد.</p>
			</div>
			<?php
			return ob_get_clean();
		}
		?>
		<div class="bv-request" id="bv-request">
			<h3><?php echo esc_html( $atts['title'] ?: $path['cta'] ); ?></h3>
			<form class="bv-form" data-bv-form="path" data-bv-inline novalidate>
				<input type="hidden" name="path" value="<?php echo esc_attr( $key ); ?>">
				<input type="hidden" name="inline" value="1">
				<input type="hidden" name="fields" value="<?php echo esc_attr( implode( ',', $fields ) ); ?>">
				<?php foreach ( $questions as $i => $question ) : ?>
					<label class="bv-field bv-field--q">
						<span><b><?php echo esc_html( $question ); ?></b></span>
						<textarea name="a<?php echo esc_attr( $i + 1 ); ?>" rows="2" required></textarea>
					</label>
				<?php endforeach; ?>
				<?php self::person_inputs( 'bv-r-' . $key, $fields ); ?>
				<p class="bv-form__error" role="alert" hidden></p>
				<button type="submit" class="bv-button bv-button--solid"><?php echo esc_html( $atts['button'] ?: 'ثبت درخواست' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
