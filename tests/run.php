<?php

declare(strict_types=1);

// Dependency-free checks: php tests/run.php
spl_autoload_register(function (string $class): void {
    $prefix = 'PlainShow\\Kit\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use PlainShow\Kit\Access;
use PlainShow\Kit\Identity;
use PlainShow\Kit\Jwks;
use PlainShow\Kit\Jwt;
use PlainShow\Kit\KitException;
use PlainShow\Kit\OidcClient;
use PlainShow\Kit\Signed;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}
function throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (KitException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

// A JWK built from a real key converts back to the same public key.
$private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$details = openssl_pkey_get_details($private);
$pem = Jwks::rsaPem(Jwt::base64UrlEncode($details['rsa']['n']), Jwt::base64UrlEncode($details['rsa']['e']));
check(trim($pem) === trim($details['key']), 'JWK to PEM conversion matches OpenSSL');

// Fake JWKS served from a temp cache file, so no network is needed.
$cache = tempnam(sys_get_temp_dir(), 'jwks');
file_put_contents($cache, json_encode(['k1' => $pem]));
touch($cache);
$jwks = new Jwks('https://login.invalid/oauth/jwks', $cache);

$sign = function (array $claims, array $header = ['alg' => 'RS256', 'kid' => 'k1']) use ($private): string {
    $input = Jwt::base64UrlEncode(json_encode($header)) . '.' . Jwt::base64UrlEncode(json_encode($claims));
    openssl_sign($input, $signature, $private, OPENSSL_ALGO_SHA256);
    return $input . '.' . Jwt::base64UrlEncode($signature);
};
$claims = ['iss' => 'https://login.plainshow.se', 'aud' => 'blog', 'sub' => '7', 'exp' => time() + 300, 'iat' => time()];

check(Jwt::verify($sign($claims), $jwks, 'https://login.plainshow.se', 'blog')['sub'] === '7', 'Valid token verifies');
throws(fn () => Jwt::verify($sign($claims), $jwks, 'https://login.plainshow.se', 'latex'), 'Wrong audience rejected');
throws(fn () => Jwt::verify($sign($claims), $jwks, 'https://evil.example', 'blog'), 'Wrong issuer rejected');
throws(fn () => Jwt::verify($sign(['exp' => time() - 3600] + $claims), $jwks, 'https://login.plainshow.se', 'blog'), 'Expired token rejected');
throws(fn () => Jwt::verify($sign($claims, ['alg' => 'HS256', 'kid' => 'k1']), $jwks, 'https://login.plainshow.se', 'blog'), 'HMAC algorithm rejected');
throws(fn () => Jwt::verify($sign($claims, ['alg' => 'none']), $jwks, 'https://login.plainshow.se', 'blog'), 'Algorithm none rejected');
$tampered = explode('.', $sign($claims));
$tampered[1] = Jwt::base64UrlEncode(json_encode(['sub' => '1'] + $claims));
throws(fn () => Jwt::verify(implode('.', $tampered), $jwks, 'https://login.plainshow.se', 'blog'), 'Tampered payload rejected');
unlink($cache);

// Return paths cannot leave the app.
check(OidcClient::safeReturnPath('/blogfeed?x=1') === '/blogfeed?x=1', 'Local path allowed');
foreach (['https://evil.example/', '//evil.example', '/\\evil.example', "/x\r\nLocation: y", 'javascript:alert(1)', ''] as $bad) {
    check(OidcClient::safeReturnPath($bad) === '/', 'Unsafe return path refused: ' . json_encode($bad));
}

// Access rules.
$extensions = [
    ['id' => 'blog', 'enabled' => true, 'default_access' => ['admin', 'moderator', 'member']],
    ['id' => 'latex', 'enabled' => true, 'default_access' => ['admin', 'moderator', 'member']],
    ['id' => 'web', 'enabled' => true, 'default_access' => ['admin']],
    ['id' => 'cloud', 'enabled' => false, 'default_access' => ['admin', 'member']],
];
$patterns = Access::defaultRolePatterns();
$admin = Access::resolve('admin', $extensions, [], $patterns['admin'], []);
check($admin['services'] === ['blog', 'latex', 'web'] && $admin['capabilities'] === ['*'], 'Admin gets every enabled service and *');
$member = Access::resolve('member', $extensions, ['latex' => 'deny', 'web' => 'allow'], $patterns['member'], ['blog.post.moderate' => 'allow']);
check($member['services'] === ['blog', 'web'], 'Member overrides: deny wins, allow adds, disabled stays off');
check(in_array('blog.post.moderate', $member['capabilities'], true), 'Capability allow is added');
$moderator = Access::resolve('moderator', $extensions, ['blog' => 'deny'], ['blog.post.moderate'], []);
check($moderator['capabilities'] === [], 'Capabilities of an unusable service are dropped');
$known = ['blog.post.create', 'blog.post.moderate', 'blog.comment.moderate', 'latex.project.create', 'core.users.manage'];
check(Access::expand(['*.moderate'], $known) === ['blog.post.moderate', 'blog.comment.moderate'], 'Suffix pattern expands');
check(Access::expand(['blog.*'], $known) === ['blog.post.create', 'blog.post.moderate', 'blog.comment.moderate'], 'Prefix pattern expands');
check(Access::expand(['*.view', 'core.dashboard.use'], ['core.audit.view', 'core.system.view', 'blog.post.view', 'core.dashboard.use']) === ['blog.post.view', 'core.dashboard.use'], 'Wildcards never reach core capabilities');

$identity = new Identity(['sub' => '3', 'ps_caps' => ['blog.*'], 'ps_services' => ['blog'], 'given_name' => 'Hugo', 'family_name' => 'Hansson']);
check($identity->can('blog.post.moderate') && !$identity->can('latex.project.create'), 'Identity capability wildcard');
check($identity->initials() === 'HH', 'Initials from name');

$signed = \PlainShow\Kit\Widgets::sign(7, 'latest-posts', 'secret', 1000);
check(\PlainShow\Kit\Widgets::verify($signed, 'latest-posts', 'secret', 1030) === 7, 'Widget request verifies');
foreach ([[$signed, 'other', 'secret', 1030], [$signed, 'latest-posts', 'wrong', 1030], [$signed, 'latest-posts', 'secret', 1100], [['user' => 8] + $signed, 'latest-posts', 'secret', 1030]] as [$query, $widget, $secret, $now]) {
    try {
        \PlainShow\Kit\Widgets::verify($query, $widget, $secret, $now);
        check(false, 'Bad widget request is refused');
    } catch (\PlainShow\Kit\KitException) {
        check(true, 'Bad widget request is refused');
    }
}

// Signed requests between apps
$h = Signed::headers('post', '/api/v1/posts?x=1', '{"a":1}', 6, ['ps_role' => 'admin', 'ps_caps' => ['*'], 'scopes' => ['blog']], 'shh', 1000);
$server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/posts?x=1'];
foreach ($h as $name => $value) { $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value; }
check(Signed::present($server), 'Signed request is detected');
$v = Signed::verify($server, '{"a":1}', 'shh', 1050);
check($v['user'] === 6 && Signed::scopes($v) === ['blog'] && Signed::identity($v)->can('anything'), 'Signed request verifies and carries context');
throws(fn () => Signed::verify($server, '{"a":2}', 'shh', 1050), 'Changed body is refused');
throws(fn () => Signed::verify(['REQUEST_URI' => '/api/v1/other'] + $server, '{"a":1}', 'shh', 1050), 'Changed path is refused');
throws(fn () => Signed::verify($server, '{"a":1}', 'other', 1050), 'Wrong secret is refused');
throws(fn () => Signed::verify($server, '{"a":1}', 'shh', 1500), 'Old signed request is refused');
throws(fn () => Signed::verify(['HTTP_X_PLAINSHOW_USER' => '7'] + $server, '{"a":1}', 'shh', 1050), 'Changed user is refused');

// OpenAPI from routes
$spec = PlainShow\Kit\OpenApi::build([['method' => 'GET', 'path' => '/health', 'capability' => null], ['method' => 'POST', 'path' => '/things/{id}', 'capability' => 'x.thing.create'], ['method' => 'GET', 'path' => '/widgets/a', 'capability' => null]],
    ['POST /things/{id}' => ['summary' => 'Change a thing', 'body' => ['name' => ['string', 'The name', true]]]], ['title' => 'T', 'version' => '1'], 'https://example.test/api');
check($spec['paths']['/health']['get']['security'] === [] && $spec['paths']['/things/{id}']['post']['x-capability'] === 'x.thing.create', 'OpenAPI marks public routes and capabilities');
check($spec['paths']['/things/{id}']['post']['parameters'][0]['name'] === 'id' && $spec['paths']['/things/{id}']['post']['requestBody']['content']['application/json']['schema']['required'] === ['name'], 'OpenAPI has path parameters and bodies');
check(($spec['paths']['/widgets/a']['get']['x-internal'] ?? false) === true, 'Widget routes are internal');

echo "Kit checks passed: $checks\n";
