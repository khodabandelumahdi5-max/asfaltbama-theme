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
	 * Default questions used by every path until the admin changes them.
	 *
	 * @return string[]
	 */
	public static function default_questions() {
		return [
			'در حال حاضر در چه مرحله‌ای از مسیر کسب‌وکار خود قرار دارید؟',
			'مهم‌ترین چالش فعلی شما چیست؟',
			'از BAVAR GROUP چه نتیجه‌ای می‌خواهید؟',
		];
	}

	/**
	 * The four entry paths shown on the home page.
	 *
	 * @return array
	 */
	public static function default_paths() {
		$q = self::default_questions();
		return [
			'course'  => [
				'en'       => 'Book of Bavar',
				'title'    => 'خرید دوره اصلی و جامع غیرحضوری گروه باور',
				'subtitle' => 'کتاب مقدس گروه باور',
				'desc'     => 'صوتی + نوشتاری + تصویری',
				'cta'      => 'مشاهده و خرید',
				'q1'       => $q[0],
				'q2'       => $q[1],
				'q3'       => $q[2],
				'target'   => '',
			],
			'library' => [
				'en'       => 'Bavar Library',
				'title'    => 'خرید پک‌های برتر کتاب و آموزش گروه باور',
				'subtitle' => 'کتاب‌های منتخب جهان',
				'desc'     => 'خلاصه و توسعه‌ی محتوایی + چک‌لیست + تمرین‌های کاربردی',
				'cta'      => 'مشاهده پک‌ها',
				'q1'       => $q[0],
				'q2'       => $q[1],
				'q3'       => $q[2],
				'target'   => '',
			],
			'simorgh' => [
				'en'       => 'Ashiane Simorgh',
				'title'    => 'شرکت در دوره حضوری',
				'subtitle' => 'آشیانه سیمرغ‌ها',
				'desc'     => 'دوره‌ی حضوری گروه باور',
				'cta'      => 'درخواست شرکت',
				'q1'       => $q[0],
				'q2'       => $q[1],
				'q3'       => $q[2],
				'target'   => '',
			],
			'consult' => [
				'en'       => 'Consulting',
				'title'    => 'مشاوره غیرحضوری با مدیریت مجموعه',
				'subtitle' => 'آرش الطافیان',
				'desc'     => 'جلسه‌ی مشاوره‌ی اختصاصی',
				'cta'      => 'درخواست مشاوره',
				'q1'       => $q[0],
				'q2'       => $q[1],
				'q3'       => $q[2],
				'target'   => '',
			],
		];
	}

	/**
	 * All defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'gate_mode'     => 'required', // required | dismissible | off.
			'gate_title'    => 'برای ورود به مسیر BAVAR GROUP اطلاعات خود را وارد کنید',
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
			$paths[ $key ] = array_merge( $def, isset( $saved['paths'][ $key ] ) && is_array( $saved['paths'][ $key ] ) ? array_filter( $saved['paths'][ $key ], 'strlen' ) : [] );
		}
		$out['paths'] = $paths;
		return $out;
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
