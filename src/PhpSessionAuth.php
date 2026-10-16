<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Single sign-on for plain PHP apps (blog, LaTeX). The app keeps its own PHP
 * session; this fills it from the login service and clears it on logout.
 *
 * Endpoints the app exposes (each a two-line file calling the methods below):
 *   /auth/callback     -> handleCallback()
 *   /auth/logout       -> logout()
 *   /auth/frontchannel -> frontChannelLogout()   (loaded by the login service's logout page)
 */
final class PhpSessionAuth
{
    private const KEY = 'ps_identity';
    private const LOGIN_AT = 'ps_login_at';
    private const SILENT_AT = 'ps_silent_at';

    private readonly OidcClient $client;
    /** @var callable(Identity): void|null */
    private $onLogin;
    /** @var callable(): void|null */
    private $onLogout;

    /**
     * @param array{issuer: string, client_id: string, client_secret: string, redirect_uri: string, scopes?: string, cache_dir?: string, extension: string, max_age?: int} $config
     */
    public function __construct(private readonly array $config, ?callable $onLogin = null, ?callable $onLogout = null)
    {
        $this->client = new OidcClient($config);
        $this->onLogin = $onLogin;
        $this->onLogout = $onLogout;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new KitException('Start the PHP session before using PlainShow sign-in.');
        }
    }

    public function client(): OidcClient { return $this->client; }

    public function user(): ?Identity
    {
        $claims = $_SESSION[self::KEY] ?? null;
        if (!is_array($claims)) {
            return null;
        }
        $maxAge = (int) ($this->config['max_age'] ?? 43200);
        if (($_SESSION[self::LOGIN_AT] ?? 0) < time() - $maxAge) {
            $this->clearLocal();
            return null;
        }
        try {
            return new Identity($claims);
        } catch (KitException) {
            $this->clearLocal();
            return null;
        }
    }

    /** Returns the signed-in user, or sends the browser to sign in and stops. */
    public function requireUser(): Identity
    {
        $user = $this->user();
        if ($user !== null) {
            if (!$user->hasService($this->config['extension'])) {
                $this->deny('You do not have access to this service.');
            }
            return $user;
        }
        $this->redirect($this->client->authorizationUrl($_SESSION, $this->currentPath()));
    }

    /**
     * On a public page: if nobody is signed in here, ask the login service
     * once, without showing anything, whether they are signed in there.
     * They come back signed in, or come back as a guest. Only for page loads
     * a person makes (not scripts, crawlers or embedded frames), and at most
     * once every ten minutes per browser.
     */
    public function trySilentSignIn(): void
    {
        if ($this->user() !== null || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        if (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') !== 'navigate' || ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'document') {
            return;
        }
        if (($_SESSION[self::SILENT_AT] ?? 0) > time() - 600 || isset($_COOKIE['ps_silent'])) {
            return;
        }
        $_SESSION[self::SILENT_AT] = time();
        setcookie('ps_silent', '1', ['expires' => time() + 600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        $this->redirect($this->client->authorizationUrl($_SESSION, $this->currentPath(), ['prompt' => 'none']));
    }

    public function handleCallback(): never
    {
        try {
            $result = $this->client->handleCallback($_SESSION, $_GET);
        } catch (KitException $error) {
            if ($error->getCode() === 401) {
                $this->redirect(OidcClient::safeReturnPath($error->getMessage()));
            }
            $this->deny($error->getMessage(), $error->getCode() === 403 ? 403 : 400);
        }

        session_regenerate_id(true);
        $_SESSION[self::KEY] = $result['identity']->claims();
        $_SESSION[self::LOGIN_AT] = time();
        if ($this->onLogin) {
            ($this->onLogin)($result['identity']);
        }
        $this->redirect(OidcClient::safeReturnPath($result['return_to']));
    }

    public function logout(): never
    {
        $this->clearLocal();
        $origin = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
        $this->redirect($this->client->logoutUrl($origin . '/'));
    }

    /** Called inside a hidden frame by the login service when someone signs out everywhere. */
    public function frontChannelLogout(): never
    {
        if (rtrim((string) ($_GET['iss'] ?? ''), '/') === $this->client->issuer()) {
            $this->clearLocal();
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: frame-ancestors {$this->client->issuer()}");
        echo 'Signed out.';
        exit;
    }

    private function clearLocal(): void
    {
        if ($this->onLogout) {
            ($this->onLogout)();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    private function currentPath(): string
    {
        return OidcClient::safeReturnPath($_SERVER['REQUEST_URI'] ?? '/');
    }

    private function redirect(string $url): never
    {
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function deny(string $message, int $status = 403): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $home = htmlspecialchars(rtrim((string) ($this->config['core_url'] ?? 'https://plainshow.se'), '/') . '/', ENT_QUOTES, 'UTF-8');
        echo <<<HTML
            <!doctype html><html lang="en" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>PlainShow</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#151515;color:#f0efec;font:15px/1.5 system-ui,sans-serif;padding:16px}
            .f{max-width:440px;width:100%;padding:10px;border:1px solid #373736;border-radius:28px;background:#20201f}.p{padding:28px;border:1px solid #2d2d2d;border-radius:18px;background:#151515}
            h1{margin:0 0 8px;font-size:1.1rem}p{margin:0 0 20px;color:#c3c2b7}a{display:inline-flex;padding:9px 14px;border-radius:6px;background:#fff;color:#0b0b0b;text-decoration:none;font-weight:500}</style></head>
            <body><div class="f"><div class="p"><h1>Unable to continue</h1><p>$safe</p><a href="$home">Back to PlainShow</a></div></div></body></html>
            HTML;
        exit;
    }
}
