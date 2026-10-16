<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Requests one PlainShow app makes to another on someone's behalf, such as
 * the API gateway in core passing a call on to the blog, or core asking
 * login for a personal API token. The receiver trusts the person and
 * context named in the headers because they are signed with the secret the
 * two apps share, over the method, the path, the time and the body.
 *
 * The context carries what the receiver needs to act as that person: their
 * identity claims (`ps_role`, `ps_services`, `ps_caps`, names) and the
 * token's `scopes`.
 */
final class Signed
{
    public const MAX_AGE = 120;

    /** @return array<string, string> headers to send */
    public static function headers(string $method, string $path, string $body, int $user, array $context, string $secret, ?int $now = null): array
    {
        if ($secret === '') {
            throw new KitException('No shared secret to sign with.');
        }
        $ts = (string) ($now ?? time());
        $encoded = rtrim(strtr(base64_encode(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        return [
            'X-PlainShow-User' => (string) $user,
            'X-PlainShow-Context' => $encoded,
            'X-PlainShow-Time' => $ts,
            'X-PlainShow-Signature' => hash_hmac('sha256', self::payload($method, $path, $ts, (string) $user, $encoded, $body), $secret),
        ];
    }

    /** True when the request carries a signature at all, so the receiver should verify it. */
    public static function present(array $server): bool
    {
        return isset($server['HTTP_X_PLAINSHOW_SIGNATURE']);
    }

    /**
     * Checks a signed request from $_SERVER and its raw body.
     *
     * @return array{user: int, context: array<string, mixed>}
     */
    public static function verify(array $server, string $body, string $secret, ?int $now = null): array
    {
        $user = (string) ($server['HTTP_X_PLAINSHOW_USER'] ?? '');
        $encoded = (string) ($server['HTTP_X_PLAINSHOW_CONTEXT'] ?? '');
        $ts = (string) ($server['HTTP_X_PLAINSHOW_TIME'] ?? '');
        $signature = (string) ($server['HTTP_X_PLAINSHOW_SIGNATURE'] ?? '');
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) ($server['REQUEST_URI'] ?? '/');
        if ($secret === '' || !ctype_digit($user) || !ctype_digit($ts) || abs(($now ?? time()) - (int) $ts) > self::MAX_AGE) {
            throw new KitException('Signed request expired or incomplete.');
        }
        if (!hash_equals(hash_hmac('sha256', self::payload($method, $path, $ts, $user, $encoded, $body), $secret), $signature)) {
            throw new KitException('Signed request signature is wrong.');
        }
        $context = json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);

        return ['user' => (int) $user, 'context' => is_array($context) ? $context : []];
    }

    /** An identity for the person a verified request is for. */
    public static function identity(array $verified): Identity
    {
        return new Identity(['sub' => (string) $verified['user']] + array_intersect_key($verified['context'], array_flip([
            'preferred_username', 'given_name', 'family_name', 'name', 'email', 'picture', 'ps_role', 'ps_services', 'ps_caps', 'ps_pi_user',
        ])));
    }

    /** @return list<string> */
    public static function scopes(array $verified): array
    {
        return array_values(array_map('strval', (array) ($verified['context']['scopes'] ?? [])));
    }

    private static function payload(string $method, string $path, string $ts, string $user, string $context, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $ts, $user, $context, hash('sha256', $body)]);
    }
}
