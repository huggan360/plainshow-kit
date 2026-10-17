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

    /**
     * Query parameters for the request. $account names the person's own
     * account in the extension when it has one (Cloud's Nextcloud user); it
     * is signed too.
     *
     * @return array{user: int, widget: string, ts: int, sig: string, account?: string}
     */
    public static function sign(int $userId, string $widget, string $secret, ?int $now = null, ?string $account = null): array
    {
        $ts = $now ?? time();
        $query = ['user' => $userId, 'widget' => $widget, 'ts' => $ts, 'sig' => hash_hmac('sha256', self::payload($userId, $widget, $ts, $account), $secret)];
        return $account === null || $account === '' ? $query : $query + ['account' => $account];
    }

    /** The user id the request is for, after checking the signature and its age. */
    public static function verify(array $query, string $widget, string $secret, ?int $now = null): int
    {
        return self::verifyWithAccount($query, $widget, $secret, $now)['user'];
    }

    /**
     * The user id and their account in the extension (null when core sent none).
     *
     * @return array{user: int, account: ?string}
     */
    public static function verifyWithAccount(array $query, string $widget, string $secret, ?int $now = null): array
    {
        $user = (int) ($query['user'] ?? 0);
        $ts = (int) ($query['ts'] ?? 0);
        $sig = (string) ($query['sig'] ?? '');
        $account = isset($query['account']) && is_string($query['account']) && $query['account'] !== '' ? $query['account'] : null;
        if ($secret === '' || $user <= 0 || abs(($now ?? time()) - $ts) > self::MAX_AGE) {
            throw new KitException('Widget request expired or incomplete.');
        }
        if (!hash_equals(hash_hmac('sha256', self::payload($user, $widget, $ts, $account), $secret), $sig)) {
            throw new KitException('Widget request signature is wrong.');
        }
        return ['user' => $user, 'account' => $account];
    }

    /** Without an account the payload is the same as before 1.4, so older extensions keep working. */
    private static function payload(int $user, string $widget, int $ts, ?string $account): string
    {
        return $account === null || $account === '' ? "$user|$widget|$ts" : "$user|$widget|$ts|$account";
    }
}
