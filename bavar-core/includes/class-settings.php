<?php
/**
 * Plugin settings with defaults.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_Settings {

	const OPTION = 'bavar_settings';

	/**
	 * Person fields every section asks for by default.
	 */
	const DEFAULT_FIELDS = 'first_name,last_name,phone,job';

	/**
	 * The four sections shown on the home page. Every text is Persian and
	 * editable in BAVAR → Settings.
	 *
	 * @return array
	 */
	public static function default_paths() {
		return [
			'course'  => [
				'label'     => 'کتاب مقدس گروه باور',
				'title'     => 'خرید دوره اصلی و جامع غیرحضوری گروه باور، کتاب مقدس گروه باور',
				'desc'      => '',
				'cta'       => 'مشاهده و خرید',
				'questions' => '',
				'fields'    => self::DEFAULT_FIELDS,
				'target'    => '',
			],
			'library' => [
				'label'     => 'پک‌های کتاب',
				'title'     => 'خرید پک‌های برتر کتاب و آموزش گروه باور',
				'desc'      => 'خرید پک‌های کتاب به صورت دسته‌ای بر اساس نیاز شما',
				'cta'       => 'مشاهده پک‌ها',
				'questions' => '',
				'fields'    => self::DEFAULT_FIELDS,
				'target'    => '',
			],
			'simorgh' => [
				'label'     => 'آشیانه سیمرغ',
				'title'     => 'دوره حضوری آشیانه سیمرغ',
				'desc'      => 'ثبت‌نام در دوره‌ی حضوری گروه باور',
				'cta'       => 'مشاهده و ثبت‌نام',
				'questions' => '',
				'fields'    => self::DEFAULT_FIELDS,
				'target'    => '',
			],
			'consult' => [
				'label'     => 'مشاوره',
				'title'     => 'مشاوره',
				'desc'      => 'یک ساعت مشاوره‌ی حضوری با مدیریت مجموعه',
				'cta'       => 'درخواست مشاوره',
				'questions' => '',
				'fields'    => self::DEFAULT_FIELDS,
				'target'    => '',
			],
		];
	}

	/**
	 * Optional extra questions of a section (one per line in the settings).
	 *
	 * @param string $key Path key.
	 * @return string[]
	 */
	public static function questions( $key ) {
		$path = self::path( $key );
		return $path ? array_slice( bavar_lines( $path['questions'] ), 0, 5 ) : [];
	}

	/**
	 * Person fields a section asks for.
	 *
	 * @param string $key Path key.
	 * @return string[]
	 */
	public static function fields( $key ) {
		$path  = self::path( $key );
		$valid = [ 'first_name', 'last_name', 'phone', 'job' ];
		$list  = array_intersect( $valid, array_map( 'trim', explode( ',', $path ? $path['fields'] : self::DEFAULT_FIELDS ) ) );
		return array_values( array_unique( array_merge( $list, [ 'phone' ] ) ) );
	}

	/**
	 * All defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'gate_mode'     => 'required', // required | dismissible | off.
			'gate_title'    => 'برای ورود به گروه باور اطلاعات خود را وارد کنید',
			'gate_text'     => 'اطلاعات شما محرمانه می‌ماند و فقط برای ارتباط تیم باور با شما استفاده می‌شود.',
			'paths'         => self::default_paths(),
			'spot_api_key'  => '',
			'spot_offline'  => '30',
			'page_library'  => 0,
			'page_simorgh'  => 0,
			'page_consult'  => 0,
		];
	}

	/**
	 * Full settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved    = get_option( self::OPTION, [] );
		$saved    = is_array( $saved ) ? $saved : [];
		$defaults = self::defaults();
		$out      = array_merge( $defaults, $saved );

		$paths = [];
		foreach ( $defaults['paths'] as $key => $def ) {
			$paths[ $key ] = array_merge( $def, isset( $saved['paths'][ $key ] ) && is_array( $saved['paths'][ $key ] ) ? self::filled( $saved['paths'][ $key ] ) : [] );
		}
		$out['paths'] = $paths;
		return $out;
	}

	/**
	 * Keep saved values, but fall back to defaults for emptied required texts.
	 *
	 * @param array $values Saved path values.
	 * @return array
	 */
	private static function filled( array $values ) {
		$optional = [ 'desc', 'questions', 'target' ];
		return array_filter(
			$values,
			function ( $value, $key ) use ( $optional ) {
				return in_array( $key, $optional, true ) || '' !== (string) $value;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * One path's config (or null).
	 *
	 * @param string $key Path key.
	 * @return array|null
	 */
	public static function path( $key ) {
		$paths = self::get( 'paths' );
		return $paths[ $key ] ?? null;
	}

	/**
	 * Where a path leads after the three questions are answered.
	 *
	 * @param string $key Path key.
	 * @return string
	 */
	public static function path_target( $key ) {
		$path = self::path( $key );
		if ( ! empty( $path['target'] ) ) {
			return $path['target'];
		}

		switch ( $key ) {
			case 'course':
				$ids = get_posts(
					[
						'post_type'   => 'product',
						'post_status' => 'publish',
						'numberposts' => 1,
						'fields'      => 'ids',
						'meta_key'    => '_bavar_kind', // phpcs:ignore WordPress.DB.SlowDBQuery
						'meta_value'  => 'course', // phpcs:ignore WordPress.DB.SlowDBQuery
					]
				);
				return $ids ? get_permalink( $ids[0] ) : home_url( '/' );
			case 'library':
			case 'simorgh':
			case 'consult':
				$page = (int) self::get( 'page_' . $key );
				return $page ? get_permalink( $page ) : home_url( '/' );
		}
		return home_url( '/' );
	}
}
