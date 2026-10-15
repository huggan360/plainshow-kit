<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/** RS256 verification for tokens issued by login.plainshow.se. Nothing else is accepted. */
final class Jwt
{
    private const LEEWAY = 60;

    /**
     * @param string|list<string>|null $audience accepted `aud` values; null skips the check
     * @return array<string, mixed> the verified claims
     */
    public static function verify(string $token, Jwks $jwks, string $issuer, string|array|null $audience): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new KitException('Malformed token.');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $claims = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($header) || !is_array($claims)) {
            throw new KitException('Malformed token.');
        }
        // Pinning the algorithm stops "alg: none" and HMAC-with-public-key tricks.
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new KitException('Unsupported token algorithm.');
        }

        $key = openssl_pkey_get_public($jwks->pemFor(isset($header['kid']) ? (string) $header['kid'] : null));
        if ($key === false) {
            throw new KitException('Unreadable signing key.');
        }
        $valid = openssl_verify($encodedHeader . '.' . $encodedPayload, self::base64UrlDecode($encodedSignature), $key, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            throw new KitException('Token signature is invalid.');
        }

        $now = time();
        if (!isset($claims['exp']) || (int) $claims['exp'] < $now - self::LEEWAY) {
            throw new KitException('Token has expired.');
        }
        if (isset($claims['nbf']) && (int) $claims['nbf'] > $now + self::LEEWAY) {
            throw new KitException('Token is not valid yet.');
        }
        if (isset($claims['iat']) && (int) $claims['iat'] > $now + self::LEEWAY) {
            throw new KitException('Token was issued in the future.');
        }
        if (isset($claims['iss']) && rtrim((string) $claims['iss'], '/') !== rtrim($issuer, '/')) {
            throw new KitException('Token was issued by someone else.');
        }
        if ($audience !== null) {
            $accepted = (array) $audience;
            $actual = (array) ($claims['aud'] ?? []);
            if (!array_intersect($accepted, $actual)) {
                throw new KitException('Token was issued for another application.');
            }
        }
        return $claims;
    }

    /** @return array<string, mixed> unverified payload, for logging only */
    public static function peek(string $token): array
    {
        $parts = explode('.', $token);
        $claims = count($parts) === 3 ? json_decode(self::base64UrlDecode($parts[1]), true) : null;
        return is_array($claims) ? $claims : [];
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new KitException('Malformed token encoding.');
        }
        return $decoded;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
