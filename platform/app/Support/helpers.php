<?php

use Carbon\CarbonInterface;

if (! function_exists('fa_digits')) {
    /** Convert Latin digits to Persian digits. */
    function fa_digits(string|int|float|null $value): string
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}

if (! function_exists('fa_number')) {
    /** Format a number with thousands separators in Persian digits. */
    function fa_number(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = number_format($value, $decimals, '٫', '٬');

        return fa_digits($decimals > 0 ? rtrim(rtrim($formatted, '0'), '٫') : $formatted);
    }
}

if (! function_exists('toman')) {
    /** Format a price in Toman. */
    function toman(int|float|null $value): string
    {
        return $value === null ? 'توافقی' : fa_number($value).' تومان';
    }
}

if (! function_exists('price_range')) {
    function price_range(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return 'قیمت توافقی';
        }
        if ($min !== null && $max !== null && $min !== $max) {
            return fa_number($min).' تا '.fa_number($max).' تومان';
        }

        return toman($min ?? $max);
    }
}

if (! function_exists('jdate')) {
    /** Format a date in the Persian (Jalali) calendar. */
    function jdate(?CarbonInterface $date, string $pattern = 'd MMMM y'): string
    {
        if ($date === null) {
            return '—';
        }

        $formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        return $formatter->format($date->getTimestamp());
    }
}
