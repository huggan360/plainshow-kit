<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Dashboard widgets. Core asks an extension for a widget's data on behalf of
 * a signed-in person; the request carries who it is for and a signature made
 * with the secret core and the extension share. Extensions answer with typed
 * JSON that core draws:
 *
 *   ['type' => 'list', 'items' => [['title', 'meta', 'url', 'icon'|'image'|'avatar']], 'more' => ['label', 'url'], 'empty' => '...']
 *   ['type' => 'metrics', 'metrics' => [['label', 'value', 'tone']], 'items' => [...], 'more' => [...]]
 *   ['type' => 'gallery', 'images' => [['src', 'url', 'alt']], 'more' => [...]]
 */
final class Widgets
{
    private const MAX_AGE = 60;

    /** @return array{user: int, widget: string, ts: int, sig: string} query parameters for the request */
    public static function sign(int $userId, string $widget, string $secret, ?int $now = null): array
    {
        $ts = $now ?? time();
        return ['user' => $userId, 'widget' => $widget, 'ts' => $ts, 'sig' => hash_hmac('sha256', "$userId|$widget|$ts", $secret)];
    }

    /** The user id the request is for, after checking the signature and its age. */
    public static function verify(array $query, string $widget, string $secret, ?int $now = null): int
    {
        $user = (int) ($query['user'] ?? 0);
        $ts = (int) ($query['ts'] ?? 0);
        $sig = (string) ($query['sig'] ?? '');
        if ($secret === '' || $user <= 0 || abs(($now ?? time()) - $ts) > self::MAX_AGE) {
            throw new KitException('Widget request expired or incomplete.');
        }
        if (!hash_equals(hash_hmac('sha256', "$user|$widget|$ts", $secret), $sig)) {
            throw new KitException('Widget request signature is wrong.');
        }
        return $user;
    }
}
