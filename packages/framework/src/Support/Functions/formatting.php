<?php

declare(strict_types=1);

/**
 * Formatting helper functions.
 *
 * Provides utility functions for formatting numbers, currencies,
 * dates, and strings with internationalization support.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

if (!function_exists('money_format')) {
    /**
     * Format a number as a currency string.
     *
     * This function provides a modern, internationalization-aware replacement
     * for the deprecated built-in money_format() function using NumberFormatter.
     *
     * @param float $value The numeric value to format.
     * @param string $currency The 3-letter ISO 4217 currency code (e.g., 'BRL', 'USD', 'EUR').
     * @param string $locale The locale for formatting conventions (e.g., 'pt_BR', 'en_US').
     * @return string|false The formatted currency string, or false on failure.
     *
     * @example
     * ```php
     * money_format(1234.56);                      // "R$ 1.234,56"
     * money_format(1234.56, 'USD', 'en_US');      // "$1,234.56"
     * money_format(1234.56, 'EUR', 'de_DE');      // "1.234,56 €"
     * ```
     */
    function money_format(float $value, string $currency = 'USD', string $locale = 'en_US'): string|false
    {
        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($value, $currency);
        } catch (\Throwable $e) {
            error_log('Error in money_format(): ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('number_format_locale')) {
    /**
     * Format a number according to locale conventions.
     *
     * @param float $value The numeric value to format.
     * @param int $decimals Number of decimal places.
     * @param string $locale The locale for formatting conventions.
     * @return string|false The formatted number string, or false on failure.
     *
     * @example
     * ```php
     * number_format_locale(1234567.89);                  // "1.234.567,89" (pt_BR)
     * number_format_locale(1234567.89, 2, 'en_US');      // "1,234,567.89"
     * ```
     */
    function number_format_locale(float $value, int $decimals = 2, string $locale = 'en_US'): string|false
    {
        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
            return $formatter->format($value);
        } catch (\Throwable $e) {
            error_log('Error in number_format_locale(): ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('percent_format')) {
    /**
     * Format a number as a percentage.
     *
     * @param float $value The numeric value (0.15 = 15%).
     * @param int $decimals Number of decimal places.
     * @param string $locale The locale for formatting conventions.
     * @return string|false The formatted percentage string, or false on failure.
     *
     * @example
     * ```php
     * percent_format(0.1534);               // "15,34%" (pt_BR)
     * percent_format(0.1534, 1, 'en_US');   // "15.3%"
     * ```
     */
    function percent_format(float $value, int $decimals = 2, string $locale = 'en_US'): string|false
    {
        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
            return $formatter->format($value);
        } catch (\Throwable $e) {
            error_log('Error in percent_format(): ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('bytes_format')) {
    /**
     * Format bytes to human-readable string.
     *
     * @param int $bytes The number of bytes.
     * @param int $precision Decimal precision.
     * @return string The formatted string (e.g., "1.5 MB").
     *
     * @example
     * ```php
     * bytes_format(1024);           // "1 KB"
     * bytes_format(1536000);        // "1.46 MB"
     * bytes_format(1073741824);     // "1 GB"
     * ```
     */
    function bytes_format(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        if ($bytes === 0) {
            return '0 B';
        }

        $bytes = abs($bytes);
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
    }
}

if (!function_exists('ordinal')) {
    /**
     * Get the ordinal suffix for a number.
     *
     * @param int $number The number.
     * @param string $locale The locale ('en' or 'pt').
     * @return string The number with ordinal suffix.
     *
     * @example
     * ```php
     * ordinal(1);           // "1st"
     * ordinal(2);           // "2nd"
     * ordinal(3);           // "3rd"
     * ordinal(1, 'pt');     // "1º"
     * ```
     */
    function ordinal(int $number, string $locale = 'en'): string
    {
        if ($locale === 'pt') {
            return $number . 'º';
        }

        // English ordinals
        $suffix = 'th';

        if (!in_array($number % 100, [11, 12, 13])) {
            switch ($number % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
            }
        }

        return $number . $suffix;
    }
}

if (!function_exists('truncate')) {
    /**
     * Truncate a string to a specified length with ellipsis.
     *
     * @param string $string The string to truncate.
     * @param int $length Maximum length (including ellipsis).
     * @param string $ellipsis The ellipsis string.
     * @param bool $preserveWords Whether to preserve whole words.
     * @return string The truncated string.
     *
     * @example
     * ```php
     * truncate('Hello World', 8);                    // "Hello..."
     * truncate('Hello World', 8, '…');               // "Hello…"
     * truncate('Hello World Example', 15, '...', true); // "Hello World..."
     * ```
     */
    function truncate(string $string, int $length, string $ellipsis = '...', bool $preserveWords = false): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        $length -= mb_strlen($ellipsis);

        if ($preserveWords) {
            $string = mb_substr($string, 0, $length + 1);
            $lastSpace = mb_strrpos($string, ' ');

            if ($lastSpace !== false) {
                $string = mb_substr($string, 0, $lastSpace);
            } else {
                $string = mb_substr($string, 0, $length);
            }
        } else {
            $string = mb_substr($string, 0, $length);
        }

        return trim($string) . $ellipsis;
    }
}

if (!function_exists('slug')) {
    /**
     * Generate a URL-friendly slug from a string.
     *
     * @param string $string The string to slugify.
     * @param string $separator The separator character.
     * @return string The slugified string.
     *
     * @example
     * ```php
     * slug('Hello World');           // "hello-world"
     * slug('Olá Mundo!');            // "ola-mundo"
     * slug('Hello World', '_');      // "hello_world"
     * ```
     */
    function slug(string $string, string $separator = '-'): string
    {
        // Transliterate accented characters
        $string = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; Lower()',
            $string
        ) ?: mb_strtolower($string);

        // Remove non-alphanumeric characters
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);

        // Replace whitespace and multiple separators with single separator
        $string = preg_replace('/[\s-]+/', $separator, $string);

        // Trim separators from ends
        return trim($string, $separator);
    }
}

if (!function_exists('mask')) {
    /**
     * Apply a mask pattern to a string.
     *
     * @param string $value The value to mask.
     * @param string $pattern The mask pattern (# = digit, @ = letter, * = any).
     * @return string The masked string.
     *
     * @example
     * ```php
     * mask('12345678901', '###.###.###-##');       // "123.456.789-01" (CPF)
     * mask('12345678000199', '##.###.###/####-##'); // "12.345.678/0001-99" (CNPJ)
     * mask('11999998888', '(##) #####-####');      // "(11) 99999-8888"
     * ```
     */
    function mask(string $value, string $pattern): string
    {
        $masked = '';
        $valueIndex = 0;
        $valueLength = strlen($value);

        for ($i = 0, $len = strlen($pattern); $i < $len && $valueIndex < $valueLength; $i++) {
            $patternChar = $pattern[$i];

            if ($patternChar === '#' || $patternChar === '@' || $patternChar === '*') {
                $masked .= $value[$valueIndex];
                $valueIndex++;
            } else {
                $masked .= $patternChar;
            }
        }

        return $masked;
    }
}

if (!function_exists('unmask')) {
    /**
     * Remove all non-alphanumeric characters from a string.
     *
     * @param string $value The value to unmask.
     * @param bool $keepLetters Whether to keep letters (default: only digits).
     * @return string The unmasked string.
     *
     * @example
     * ```php
     * unmask('123.456.789-01');        // "12345678901"
     * unmask('(11) 99999-8888');       // "11999998888"
     * unmask('ABC-123', true);         // "ABC123"
     * ```
     */
    function unmask(string $value, bool $keepLetters = false): string
    {
        if ($keepLetters) {
            return preg_replace('/[^a-zA-Z0-9]/', '', $value);
        }

        return preg_replace('/[^0-9]/', '', $value);
    }
}

if (!function_exists('initials')) {
    /**
     * Get initials from a name.
     *
     * @param string $name The full name.
     * @param int $limit Maximum number of initials.
     * @return string The initials (uppercase).
     *
     * @example
     * ```php
     * initials('John Doe');              // "JD"
     * initials('John Michael Doe');       // "JMD"
     * initials('John Michael Doe', 2);    // "JD"
     * ```
     */
    function initials(string $name, int $limit = 0): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));

                if ($limit > 0 && mb_strlen($initials) >= $limit) {
                    break;
                }
            }
        }

        return $initials;
    }
}

if (!function_exists('human_time_diff')) {
    /**
     * Get a human-readable time difference.
     *
     * @param int|string|\DateTimeInterface $from Start time (timestamp, string, or DateTime).
     * @param int|string|\DateTimeInterface|null $to End time (default: now).
     * @param string $locale Language for output ('en' or 'pt').
     * @return string Human-readable time difference.
     *
     * @example
     * ```php
     * human_time_diff(time() - 30);           // "30 seconds ago"
     * human_time_diff(time() - 3600);         // "1 hour ago"
     * human_time_diff(time() + 86400);        // "in 1 day"
     * human_time_diff(time() - 30, null, 'pt'); // "há 30 segundos"
     * ```
     */
    function human_time_diff(
        int|string|\DateTimeInterface $from,
        int|string|\DateTimeInterface|null $to = null,
        string $locale = 'en'
    ): string {
        // Convert to timestamps
        if ($from instanceof \DateTimeInterface) {
            $from = $from->getTimestamp();
        } elseif (is_string($from)) {
            $from = strtotime($from);
        }

        if ($to === null) {
            $to = time();
        } elseif ($to instanceof \DateTimeInterface) {
            $to = $to->getTimestamp();
        } elseif (is_string($to)) {
            $to = strtotime($to);
        }

        $diff = $to - $from;
        $isFuture = $diff < 0;
        $diff = abs($diff);

        // Time units in seconds
        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        // Portuguese translations
        $translations = [
            'pt' => [
                'year' => ['ano', 'anos'],
                'month' => ['mês', 'meses'],
                'week' => ['semana', 'semanas'],
                'day' => ['dia', 'dias'],
                'hour' => ['hora', 'horas'],
                'minute' => ['minuto', 'minutos'],
                'second' => ['segundo', 'segundos'],
                'ago' => 'há %s',
                'future' => 'em %s',
                'now' => 'agora',
            ],
            'en' => [
                'year' => ['year', 'years'],
                'month' => ['month', 'months'],
                'week' => ['week', 'weeks'],
                'day' => ['day', 'days'],
                'hour' => ['hour', 'hours'],
                'minute' => ['minute', 'minutes'],
                'second' => ['second', 'seconds'],
                'ago' => '%s ago',
                'future' => 'in %s',
                'now' => 'just now',
            ],
        ];

        $lang = $translations[$locale] ?? $translations['en'];

        foreach ($units as $unit => $seconds) {
            $count = (int) floor($diff / $seconds);

            if ($count >= 1) {
                $unitName = $count === 1 ? $lang[$unit][0] : $lang[$unit][1];
                $timeStr = $count . ' ' . $unitName;

                return $isFuture
                    ? sprintf($lang['future'], $timeStr)
                    : sprintf($lang['ago'], $timeStr);
            }
        }

        return $lang['now'];
    }
}
