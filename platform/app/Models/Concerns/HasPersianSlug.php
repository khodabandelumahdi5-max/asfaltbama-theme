<?php

namespace App\Models\Concerns;

trait HasPersianSlug
{
    /**
     * Build a URL slug that keeps Persian letters, unique within the table.
     */
    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)), '-') ?: 'item';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
