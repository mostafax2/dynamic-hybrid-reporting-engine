<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Support;

/**
 * Resolves which CSS framework the host application uses.
 *
 * Resolution order:
 *  1. config('reporting-engine.blade.theme') — explicit wins
 *  2. Presence of tailwind.config.{js,ts} in the project root
 *  3. Default: 'bootstrap'
 */
final class ThemeDetector
{
    private static ?string $resolved = null;

    public static function resolve(?string $override = null): string
    {
        if ($override !== null) {
            return $override;
        }

        if (static::$resolved !== null) {
            return static::$resolved;
        }

        $configured = config('reporting-engine.blade.theme', 'auto');

        if ($configured !== 'auto') {
            return static::$resolved = $configured;
        }

        if (function_exists('base_path') && (
            file_exists(base_path('tailwind.config.js')) ||
            file_exists(base_path('tailwind.config.ts'))
        )) {
            return static::$resolved = 'tailwind';
        }

        return static::$resolved = 'bootstrap';
    }

    public static function isRtl(): bool
    {
        $locale    = app()->getLocale();
        $rtlLocales = config('reporting-engine.blade.rtl_locales', ['ar', 'fa', 'he', 'ur']);
        return in_array($locale, $rtlLocales, true);
    }

    public static function reset(): void
    {
        static::$resolved = null;
    }
}
