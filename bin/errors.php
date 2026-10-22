<?php

/*
 * Builds ui/errors/*.html: PlainShow's error pages and the "updating" page.
 * Run after changing the text below:  php bin/errors.php
 *
 * The pages are self-contained (inline CSS, no fonts or scripts from
 * elsewhere) so they work when the app behind them is down. Images are
 * loaded from /_errors/, where the server installs this folder (see
 * core/ops/apache/plainshow-errors.conf). Laravel apps render the same files.
 */

$pages = [
    '401' => ['Sign in first', 'This page needs you to be signed in.', '401'],
    '403' => ['Not for you', 'You do not have access to this page. Ask an administrator if you think you should.', '403'],
    '404' => ['Page not found', 'This page does not exist, or it has moved.', '404'],
    '429' => ['Slow down', 'That was a lot of requests in a short time. Wait a minute and try again.', '429'],
    '500' => ['Something broke', 'Something went wrong on our side. Try again in a moment.', '500'],
    '503' => ['Back soon', 'This service is taking a short break. Try again in a few minutes.', '503'],
    'updating' => ['Updating', 'A new version is being installed. This page reloads by itself when it is done.', '503'],
];

foreach ($pages as $name => [$title, $text, $image]) {
    $code = $name === 'updating' ? '' : $name;
    $refresh = $name === 'updating'
        ? "<script>setInterval(function(){fetch(location.href,{method:'HEAD',cache:'no-store'}).then(function(r){if(r.status!==503)location.reload();}).catch(function(){});},6000);</script>"
        : '';
    $actions = $name === 'updating'
        ? '<p class="small">Usually under a minute.</p>'
        : '<div class="actions"><a class="btn" href="javascript:history.back()">Go back</a><a class="btn primary" href="/">Start page</a></div>';
    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{$title} | PlainShow</title>
<style>
:root{--bg:#151515;--panel:#1d1d1d;--line:#2c2c2c;--text:#f2f2f2;--soft:#b9b9b9;--accent:#fc5000}
@media (prefers-color-scheme:light){:root{--bg:#f6f4f1;--panel:#fff;--line:#e4e0da;--text:#1b1b1b;--soft:#5f5f5f}}
:root[data-theme=dark]{--bg:#151515;--panel:#1d1d1d;--line:#2c2c2c;--text:#f2f2f2;--soft:#b9b9b9}
:root[data-theme=light]{--bg:#f6f4f1;--panel:#fff;--line:#e4e0da;--text:#1b1b1b;--soft:#5f5f5f}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px 16px;background:var(--bg);color:var(--text);font:16px/1.5 Outfit,system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}
main{max-width:460px}
img{width:min(260px,60vw);height:auto;display:block;margin:0 auto 8px}
.code{margin:0;color:var(--accent);font-weight:700;letter-spacing:.12em}
h1{margin:4px 0 8px;font:600 2rem/1.2 Georgia,"Times New Roman",serif}
p{margin:0 0 20px;color:var(--soft)}
.small{font-size:.9rem}
.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{display:inline-block;padding:10px 18px;border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--text);text-decoration:none;font-weight:500}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
</style>
<script>try{var t=localStorage.getItem('plainshow-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body>
<main>
<img src="/_errors/{$image}.webp" alt="" width="260" height="260">
<p class="code">{$code}</p>
<h1>{$title}</h1>
<p>{$text}</p>
{$actions}
</main>
{$refresh}
</body>
</html>

HTML;
    file_put_contents(__DIR__ . "/../ui/errors/$name.html", $html);
}
echo "Wrote " . count($pages) . " pages.\n";
