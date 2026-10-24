<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Where everything of this PlainShow lives: the domain, the address of each
 * installed app and a few facts about the server. The installer writes
 * /etc/plainshow/platform.json (no secrets in it); every app reads it here, so
 * no app has an address of its own written into its code.
 *
 *   {"domain": "example.com",
 *    "urls": {"core": "https://example.com", "login": "https://login.example.com", ...},
 *    "system": {"web_user": "www-data", ...}}
 *
 * Without the file (the first PlainShow, before it had one) the addresses are
 * plainshow.se's. PLAINSHOW_PLATFORM points somewhere else, for tests.
 */
final class Platform
{
    public const FILE = '/etc/plainshow/platform.json';

    /** Every app PlainShow knows, in the order menus show them. */
    public const APPS = ['core', 'login', 'blog', 'split', 'latex', 'cloud', 'web'];

    private const DEFAULT_DOMAIN = 'plainshow.se';

    private static ?array $data = null;

    /** The whole file, read once. */
    public static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $file = getenv('PLAINSHOW_PLATFORM') ?: self::FILE;
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!is_array($data)) {
            $data = ['domain' => self::DEFAULT_DOMAIN, 'urls' => self::defaultUrls(self::DEFAULT_DOMAIN), 'system' => []];
        }
        $data['domain'] = strtolower((string) ($data['domain'] ?? self::DEFAULT_DOMAIN));
        $data['urls'] = array_map(fn ($url) => rtrim((string) $url, '/'), array_filter((array) ($data['urls'] ?? []), 'is_string'));
        $data['system'] = (array) ($data['system'] ?? []);

        return self::$data = $data;
    }

    /** Forget what was read, so the next call reads the file again (tests, long-running servers). */
    public static function reset(): void
    {
        self::$data = null;
    }

    /** The domain every app shares, for example plainshow.se. */
    public static function domain(): string
    {
        return self::data()['domain'];
    }

    /** The address of an app without a trailing slash, or null when it is not installed. */
    public static function url(string $app): ?string
    {
        return self::data()['urls'][$app] ?? null;
    }

    /** The address of an app; throws when it is not installed. */
    public static function require(string $app): string
    {
        return self::url($app) ?? throw new KitException("PlainShow app '$app' is not installed.");
    }

    /** The host name of an app, for example login.plainshow.se. */
    public static function host(string $app): ?string
    {
        $url = self::url($app);

        return $url === null ? null : strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /** @return array<string, string> app => address, installed apps only, in menu order */
    public static function urls(): array
    {
        $urls = self::data()['urls'];
        $ordered = [];
        foreach (self::APPS as $app) {
            if (isset($urls[$app])) {
                $ordered[$app] = $urls[$app];
            }
        }

        return $ordered + $urls;
    }

    public static function installed(string $app): bool
    {
        return self::url($app) !== null;
    }

    /** Whether a host belongs to this PlainShow: the domain itself or one of its subdomains, or an app's host. */
    public static function ownsHost(string $host): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
        $domain = self::domain();
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return true;
        }
        foreach (self::urls() as $url) {
            if (strtolower((string) parse_url($url, PHP_URL_HOST)) === $host) {
                return true;
            }
        }

        return false;
    }

    /** A fact about the server, for example system('web_user', 'www-data'). */
    public static function system(string $key, ?string $default = null): ?string
    {
        $value = self::data()['system'][$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** plainshow.se's own layout: the dashboard on the domain, every app on its own subdomain. */
    public static function defaultUrls(string $domain): array
    {
        $urls = ['core' => "https://$domain"];
        foreach (array_slice(self::APPS, 1) as $app) {
            $urls[$app] = "https://$app.$domain";
        }

        return $urls;
    }
}
