<?php
/**
 * Jalali (Solar Hijri) calendar: conversion, formatting, and an optional
 * `wp_date` filter so dates across the site and admin show in Jalali.
 * Machine formats (Y-m-d, ISO, timestamps) are left Gregorian so date
 * inputs and stored values keep working.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Jalali {

	const MONTHS = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	// PHP 'w': 0 = Sunday.
	const WEEKDAYS = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );

	public static function init() {
		if ( 'yes' === Yadak_Settings::get( 'jalali' ) ) {
			add_filter( 'wp_date', array( __CLASS__, 'filter_wp_date' ), 10, 4 );
		}
	}

	/**
	 * @return int[] [jy, jm, jd]
	 */
	public static function g2j( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * @return int[] [gy, gm, gd]
	 */
	public static function j2g( $jy, $jm, $jd ) {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			--$days;
			$gy  += 100 * intdiv( $days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				++$days;
			}
		}
		$gy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd    = $days + 1;
		$leap  = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
		$month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $month[ $gm ]; $gm++ ) {
			$gd -= $month[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}

	/**
	 * Format a timestamp in Jalali, in the site timezone.
	 *
	 * @param string            $format    PHP date format.
	 * @param int|null          $timestamp Unix timestamp.
	 * @param DateTimeZone|null $tz        Timezone.
	 * @return string
	 */
	public static function format( $format, $timestamp = null, $tz = null ) {
		$dt = new DateTime( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );
		$dt->setTimezone( $tz ? $tz : wp_timezone() );
		list( $jy, $jm, $jd ) = self::g2j( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );

		$out = '';
		$len = strlen( $format );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $format[ $i ];
			if ( '\\' === $ch && $i + 1 < $len ) {
				$out .= $format[ ++$i ];
				continue;
			}
			switch ( $ch ) {
				case 'Y':
				case 'o':
					$out .= $jy;
					break;
				case 'y':
					$out .= substr( (string) $jy, -2 );
					break;
				case 'm':
					$out .= str_pad( (string) $jm, 2, '0', STR_PAD_LEFT );
					break;
				case 'n':
					$out .= $jm;
					break;
				case 'd':
					$out .= str_pad( (string) $jd, 2, '0', STR_PAD_LEFT );
					break;
				case 'j':
					$out .= $jd;
					break;
				case 'F':
				case 'M':
					$out .= self::MONTHS[ $jm - 1 ];
					break;
				case 'l':
				case 'D':
					$out .= self::WEEKDAYS[ (int) $dt->format( 'w' ) ];
					break;
				case 'S':
					break;
				case 't':
					$out .= $jm <= 6 ? 31 : ( $jm < 12 ? 30 : ( self::is_leap( $jy ) ? 30 : 29 ) );
					break;
				case 'a':
					$out .= 'am' === $dt->format( 'a' ) ? 'ق.ظ' : 'ب.ظ';
					break;
				case 'A':
					$out .= 'AM' === $dt->format( 'A' ) ? 'قبل از ظهر' : 'بعد از ظهر';
					break;
				default:
					$out .= ctype_alpha( $ch ) ? $dt->format( $ch ) : $ch;
			}
		}
		return $out;
	}

	public static function is_leap( $jy ) {
		list( $gy, $gm, $gd ) = self::j2g( $jy, 12, 30 );
		list( , $jm2 ) = self::g2j( $gy, $gm, $gd );
		return 12 === $jm2;
	}

	/**
	 * Formats that feed inputs, storage or machines stay Gregorian.
	 *
	 * @param string $format Format.
	 * @return bool
	 */
	public static function is_machine_format( $format ) {
		if ( preg_match( '/[crUeT]|\\\\T/', $format ) ) {
			return true;
		}
		return in_array( $format, array( 'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Ymd', 'Y', 'm', 'd', 'H', 'i', 's', 'G', 'H:i', 'G:i', 'g:i a', 'g:i A', 'h:i A' ), true )
			|| ! preg_match( '/[YyFMmndjlD]/', $format );
	}

	public static function filter_wp_date( $date, $format, $timestamp, $timezone ) {
		if ( self::is_machine_format( $format ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_cron() ) {
			return $date;
		}
		return self::format( $format, $timestamp, $timezone );
	}

	/**
	 * Parse "1405/07/03" (Persian or Latin digits) to "2026-09-25", or ''.
	 *
	 * @param string $jalali Jalali date.
	 * @return string
	 */
	public static function to_gregorian( $jalali ) {
		if ( ! preg_match( '/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', trim( yadak_normalize_digits( $jalali ) ), $m ) ) {
			return '';
		}
		if ( (int) $m[1] > 1700 ) { // Already Gregorian.
			return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
		}
		list( $gy, $gm, $gd ) = self::j2g( (int) $m[1], (int) $m[2], (int) $m[3] );
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * "2026-09-25" → "1405/07/03".
	 *
	 * @param string $gregorian Y-m-d.
	 * @return string
	 */
	public static function from_gregorian( $gregorian ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', (string) $gregorian, $m ) ) {
			return '';
		}
		list( $jy, $jm, $jd ) = self::g2j( (int) $m[1], (int) $m[2], (int) $m[3] );
		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}
}

/**
 * Display a stored date (Y-m-d or MySQL datetime, UTC for datetimes) in the
 * site's calendar.
 *
 * @param string $value  Date.
 * @param string $format Format.
 * @param bool   $utc    Value is UTC.
 * @return string
 */
function yadak_show_date( $value, $format = 'Y/m/d', $utc = false ) {
	if ( ! $value ) {
		return '';
	}
	$ts = $utc ? strtotime( $value . ' UTC' ) : ( new DateTime( $value, wp_timezone() ) )->getTimestamp();
	return 'yes' === Yadak_Settings::get( 'jalali' ) ? Yadak_Jalali::format( $format, $ts ) : wp_date( $format, $ts );
}

/**
 * Accept a Jalali or Gregorian date from a form, return Y-m-d or ''.
 *
 * @param string $value Input.
 * @return string
 */
function yadak_parse_date_input( $value ) {
	return Yadak_Jalali::to_gregorian( (string) $value );
}

/**
 * Value for a date text input (Jalali when enabled).
 *
 * @param string $ymd Y-m-d.
 * @return string
 */
function yadak_date_input_value( $ymd ) {
	if ( ! $ymd ) {
		return '';
	}
	return 'yes' === Yadak_Settings::get( 'jalali' ) ? Yadak_Jalali::from_gregorian( $ymd ) : substr( $ymd, 0, 10 );
}

/**
 * Today in the site timezone, Y-m-d.
 *
 * @param string $modify Optional modifier, e.g. "+3 days".
 * @return string
 */
function yadak_today( $modify = '' ) {
	$dt = new DateTime( 'now', wp_timezone() );
	if ( $modify ) {
		$dt->modify( $modify );
	}
	return $dt->format( 'Y-m-d' );
}
