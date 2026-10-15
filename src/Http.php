<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/** Minimal HTTP client so the kit has no framework dependency. */
final class Http
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string>|null $form
     * @return array{status: int, body: string}
     */
    public static function request(string $method, string $url, array $headers = [], ?array $form = null, int $timeout = 8): array
    {
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://127.0.0.1') && !str_starts_with($url, 'http://localhost')) {
            throw new KitException('Refusing to call a non-HTTPS address: ' . $url);
        }

        $handle = curl_init($url);
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HTTPHEADER => $lines,
        ]);
        if ($form !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new KitException('Request to ' . parse_url($url, PHP_URL_HOST) . ' failed: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    /** @return array<string, mixed> */
    public static function json(string $method, string $url, array $headers = [], ?array $form = null): array
    {
        $response = self::request($method, $url, $headers + ['Accept' => 'application/json'], $form);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new KitException('Unexpected response from ' . parse_url($url, PHP_URL_HOST) . ' (HTTP ' . $response['status'] . ').');
        }
        if ($response['status'] >= 400) {
            $message = is_string($data['error_description'] ?? null) ? $data['error_description'] : (is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $response['status']);
            throw new KitException($message, $response['status']);
        }
        return $data;
    }
}
