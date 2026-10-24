<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Authorization code flow with PKCE against login.plainshow.se.
 * Pending sign-ins live in the caller's session under `ps_oidc`.
 */
final class OidcClient
{
    private const PENDING_LIMIT = 5;
    private const PENDING_TTL = 900;

    private readonly Jwks $jwks;

    /**
     * Without `issuer` the platform's Login is used; without `redirect_uri`,
     * `/auth/callback` on the platform address of `extension`.
     *
     * @param array{issuer?: string, client_id: string, client_secret: string, redirect_uri?: string, extension?: string, scopes?: string, cache_dir?: string} $config
     */
    public function __construct(private array $config)
    {
        $this->config = self::withPlatform($config);
        $config = $this->config;
        foreach (['issuer', 'client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (empty($config[$key])) {
                throw new KitException("PlainShow sign-in is missing its $key setting.");
            }
        }
        $cache = rtrim($config['cache_dir'] ?? sys_get_temp_dir(), '/') . '/plainshow-jwks-' . substr(hash('sha256', $config['issuer']), 0, 12) . '.json';
        $this->jwks = new Jwks($this->endpoint('/oauth/jwks'), $cache);
    }

    /** Fills in the addresses a sign-in config leaves out from the platform file. */
    public static function withPlatform(array $config): array
    {
        if (empty($config['issuer'])) {
            $config['issuer'] = Platform::url('login') ?? '';
        }
        if (empty($config['redirect_uri']) && !empty($config['extension']) && Platform::url($config['extension'])) {
            $config['redirect_uri'] = Platform::url($config['extension']) . '/auth/callback';
        }
        if (empty($config['core_url'])) {
            $config['core_url'] = Platform::url('core') ?? '';
        }

        return $config;
    }

    public function issuer(): string { return rtrim($this->config['issuer'], '/'); }
    public function clientId(): string { return $this->config['client_id']; }
    public function jwks(): Jwks { return $this->jwks; }
    public function endpoint(string $path): string { return $this->issuer() . $path; }

    /** @param array<string, mixed> $session the caller's session array, by reference */
    public function authorizationUrl(array &$session, string $returnTo, array $extra = []): string
    {
        $state = Jwt::base64UrlEncode(random_bytes(24));
        $nonce = Jwt::base64UrlEncode(random_bytes(24));
        $verifier = Jwt::base64UrlEncode(random_bytes(48));

        $pending = array_filter(
            (array) ($session['ps_oidc'] ?? []),
            fn ($entry) => is_array($entry) && ($entry['at'] ?? 0) > time() - self::PENDING_TTL,
        );
        $pending[$state] = ['nonce' => $nonce, 'verifier' => $verifier, 'return' => $returnTo, 'at' => time(), 'silent' => ($extra['prompt'] ?? null) === 'none'];
        $session['ps_oidc'] = array_slice($pending, -self::PENDING_LIMIT, null, true);

        return $this->endpoint('/oauth/authorize') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scopes'] ?? 'openid profile email plainshow',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Jwt::base64UrlEncode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ] + $extra, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $session the caller's session array, by reference
     * @param array<string, mixed> $query the callback's query parameters
     * @return array{identity: Identity, return_to: string, tokens: array<string, mixed>}
     */
    public function handleCallback(array &$session, array $query): array
    {
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $pending = $session['ps_oidc'][$state] ?? null;
        // One use only, whatever happens next.
        unset($session['ps_oidc'][$state]);

        if (!is_array($pending) || ($pending['at'] ?? 0) < time() - self::PENDING_TTL) {
            throw new KitException('This sign-in link has expired. Please try again.', 400);
        }
        if (isset($query['error'])) {
            // A silent check that found nobody signed in is not a failure: go back quietly.
            if (!empty($pending['silent']) && in_array($query['error'], ['login_required', 'interaction_required', 'consent_required', 'account_selection_required', 'access_denied'], true)) {
                throw new KitException((string) $pending['return'], 401);
            }
            throw new KitException((string) ($query['error_description'] ?? $query['error']), 403);
        }
        if (!is_string($query['code'] ?? null) || $query['code'] === '') {
            throw new KitException('The sign-in response had no code.', 400);
        }

        $tokens = Http::json('POST', $this->endpoint('/oauth/token'), [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'code' => $query['code'],
            'code_verifier' => $pending['verifier'],
        ]);

        if (!is_string($tokens['id_token'] ?? null)) {
            throw new KitException('The login service returned no identity token.');
        }
        $claims = Jwt::verify($tokens['id_token'], $this->jwks, $this->issuer(), $this->clientId());
        if (!hash_equals((string) $pending['nonce'], (string) ($claims['nonce'] ?? ''))) {
            throw new KitException('The sign-in response does not match this request.', 400);
        }

        return ['identity' => new Identity($claims), 'return_to' => (string) $pending['return'], 'tokens' => $tokens];
    }

    /** Verifies an access token sent as `Authorization: Bearer ...`. */
    public function verifyAccessToken(string $token, ?string $audience = null): array
    {
        return Jwt::verify($token, $this->jwks, $this->issuer(), $audience);
    }

    public function logoutUrl(string $returnTo): string
    {
        return $this->endpoint('/logout') . '?' . http_build_query(['return' => $returnTo], '', '&', PHP_QUERY_RFC3986);
    }

    /** Only same-host paths are allowed back, so sign-in cannot be used as an open redirect. */
    public static function safeReturnPath(?string $value, string $fallback = '/'): string
    {
        if (!is_string($value) || $value === '' || $value[0] !== '/' || str_starts_with($value, '//') || str_contains($value, '\\')) {
            return $fallback;
        }
        return preg_match('/[\x00-\x1f]/', $value) ? $fallback : $value;
    }
}
