<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * The language PlainShow speaks to this visitor: English, Dutch or Swedish.
 *
 * The choice is the `ps_lang` cookie, set for the whole domain by the
 * language switch in every app's account menu, so it follows the person
 * from one service to the next. Without it the browser's languages decide,
 * and English is the fallback.
 *
 * Texts are looked up by their source text in `<dir>/<lang>.json`
 * ({"Source text": "Translation"}); `{name}` placeholders are filled in after
 * translating. A text with no translation stays as it is.
 */
final class Lang
{
    public const SUPPORTED = ['en', 'nl', 'sv'];
    public const NAMES = ['en' => 'English', 'nl' => 'Nederlands', 'sv' => 'Svenska'];
    public const COOKIE = 'ps_lang';

    private static ?string $current = null;
    /** @var array<string, string> */
    private static array $dictionary = [];

    /** The language for a cookie value and an Accept-Language header. */
    public static function detect(?string $cookie, ?string $acceptLanguage, string $fallback = 'en'): string
    {
        if ($cookie !== null && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }
        $ranked = [];
        foreach (explode(',', (string) $acceptLanguage) as $index => $part) {
            [$tag, $quality] = array_pad(array_map('trim', explode(';q=', trim($part))), 2, '1');
            $code = strtolower(substr($tag, 0, 2));
            if ($code === 'nb' || $code === 'nn' || $code === 'no' || $code === 'da') {
                continue; // close to Swedish, but let the person choose
            }
            if (in_array($code, self::SUPPORTED, true)) {
                $ranked[] = [(float) $quality, -$index, $code];
            }
        }
        rsort($ranked);

        return $ranked[0][2] ?? $fallback;
    }

    /** The language of this request (cookie, then the browser), remembered once known. */
    public static function current(): string
    {
        return self::$current ??= self::detect($_COOKIE[self::COOKIE] ?? null, $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
    }

    public static function use(string $lang): void
    {
        self::$current = in_array($lang, self::SUPPORTED, true) ? $lang : 'en';
    }

    /**
     * Load the translations for the current language from `<dir>/<lang>.json`.
     * `$source` is the language the texts are written in (no file needed for it).
     */
    public static function load(string $dir, string $source = 'en'): void
    {
        $lang = self::current();
        self::$dictionary = [];
        if ($lang === $source) {
            return;
        }
        $file = rtrim($dir, '/') . "/$lang.json";
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        self::$dictionary = is_array($data) ? array_filter($data, 'is_string') : [];
    }

    /** @param array<string, string|int|float> $params */
    public static function t(string $text, array $params = []): string
    {
        $out = self::$dictionary[$text] ?? $text;
        foreach ($params as $name => $value) {
            $out = str_replace('{' . $name . '}', (string) $value, $out);
        }

        return $out;
    }

    /** One text for exactly one, another for any other count ({n} is the count). */
    public static function plural(int $count, string $one, string $many, array $params = []): string
    {
        return self::t($count === 1 ? $one : $many, ['n' => $count] + $params);
    }

    /** The dictionary in use, for pages that hand it to their scripts. */
    public static function dictionary(): array
    {
        return self::$dictionary;
    }

    /** The cookie's domain for a host: the host without its first label (login.example.org: example.org). */
    public static function cookieDomain(string $host): string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host));
        if (filter_var($host, FILTER_VALIDATE_IP) || !str_contains($host, '.')) {
            return '';
        }
        $labels = explode('.', $host);

        return count($labels) > 2 ? implode('.', array_slice($labels, 1)) : $host;
    }

    /**
     * The language switch for an account menu (kit plainshow.js makes the
     * buttons work).
     */
    public static function menuSwitch(): string
    {
        $buttons = '';
        foreach (self::SUPPORTED as $code) {
            $active = $code === self::current();
            $buttons .= '<button type="button" data-ps-lang="' . $code . '" title="' . self::NAMES[$code] . '" lang="' . $code . '"'
                . ' aria-pressed="' . ($active ? 'true' : 'false') . '"' . ($active ? ' class="is-active"' : '') . '>' . strtoupper($code) . '</button>';
        }
        $label = ['en' => 'Language', 'nl' => 'Taal', 'sv' => 'Språk'][self::current()];

        return '<div class="ps-dropdown__lang" role="group" aria-label="' . $label . '"><i class="bx bx-globe" aria-hidden="true"></i><span>' . $label . '</span><span class="ps-lang">' . $buttons . '</span></div>';
    }
}
