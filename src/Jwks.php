<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * The login service's public keys, fetched once and cached on disk.
 * A token naming an unknown key triggers one refetch, so key rotation works
 * without restarting anything.
 */
final class Jwks
{
    /** @var array<string, string>|null kid => PEM */
    private ?array $keys = null;

    public function __construct(
        private readonly string $url,
        private readonly string $cacheFile,
        private readonly int $ttl = 3600,
    ) {
    }

    public function pemFor(?string $kid): string
    {
        $keys = $this->load(false);
        $pem = $this->pick($keys, $kid);
        if ($pem === null) {
            $pem = $this->pick($this->load(true), $kid);
        }
        if ($pem === null) {
            throw new KitException('No signing key matches this token.');
        }
        return $pem;
    }

    /** @param array<string, string> $keys */
    private function pick(array $keys, ?string $kid): ?string
    {
        if ($kid !== null && $kid !== '') {
            return $keys[$kid] ?? null;
        }
        return count($keys) === 1 ? array_values($keys)[0] : null;
    }

    /** @return array<string, string> */
    private function load(bool $refresh): array
    {
        if (!$refresh && $this->keys !== null) {
            return $this->keys;
        }
        if (!$refresh && is_file($this->cacheFile) && filemtime($this->cacheFile) > time() - $this->ttl) {
            $cached = json_decode((string) file_get_contents($this->cacheFile), true);
            if (is_array($cached) && $cached) {
                return $this->keys = $cached;
            }
        }

        $document = Http::json('GET', $this->url);
        $keys = [];
        foreach ((array) ($document['keys'] ?? []) as $index => $jwk) {
            if (!is_array($jwk) || ($jwk['kty'] ?? '') !== 'RSA' || !isset($jwk['n'], $jwk['e'])) {
                continue;
            }
            if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
                continue;
            }
            $keys[(string) ($jwk['kid'] ?? 'key-' . $index)] = self::rsaPem((string) $jwk['n'], (string) $jwk['e']);
        }
        if (!$keys) {
            throw new KitException('The login service published no usable signing keys.');
        }

        $directory = dirname($this->cacheFile);
        if (is_dir($directory) && is_writable($directory)) {
            $temporary = $this->cacheFile . '.' . bin2hex(random_bytes(4));
            file_put_contents($temporary, json_encode($keys), LOCK_EX);
            @chmod($temporary, 0640);
            rename($temporary, $this->cacheFile);
        }
        return $this->keys = $keys;
    }

    /** Builds a SubjectPublicKeyInfo PEM from an RSA modulus and exponent. */
    public static function rsaPem(string $n, string $e): string
    {
        $modulus = Jwt::base64UrlDecode($n);
        $exponent = Jwt::base64UrlDecode($e);
        $rsaKey = self::sequence(self::integer($modulus) . self::integer($exponent));
        $algorithm = self::sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $der = self::sequence($algorithm . "\x03" . self::length(strlen($rsaKey) + 1) . "\x00" . $rsaKey);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::length(strlen($bytes)) . $bytes;
    }

    private static function sequence(string $content): string
    {
        return "\x30" . self::length(strlen($content)) . $content;
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
