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

// Dutch and Swedish, shown when the visitor's language (ps_lang cookie or browser) asks for it.
$translations = [
    'nl' => [
        '401' => ['Eerst inloggen', 'Je moet ingelogd zijn om deze pagina te zien.'],
        '403' => ['Niet voor jou', 'Je hebt geen toegang tot deze pagina. Vraag een beheerder als je denkt dat dat wel zou moeten.'],
        '404' => ['Pagina niet gevonden', 'Deze pagina bestaat niet, of is verplaatst.'],
        '429' => ['Rustig aan', 'Dat waren veel verzoeken in korte tijd. Wacht een minuut en probeer het opnieuw.'],
        '500' => ['Er ging iets mis', 'Er ging iets mis aan onze kant. Probeer het zo opnieuw.'],
        '503' => ['Zo terug', 'Deze dienst neemt een korte pauze. Probeer het over een paar minuten opnieuw.'],
        'updating' => ['Bijwerken', 'Er wordt een nieuwe versie geïnstalleerd. Deze pagina laadt vanzelf opnieuw als het klaar is.'],
        'back' => 'Terug', 'home' => 'Startpagina', 'soon' => 'Meestal binnen een minuut.',
    ],
    'sv' => [
        '401' => ['Logga in först', 'Du behöver vara inloggad för att se den här sidan.'],
        '403' => ['Inte för dig', 'Du har inte tillgång till den här sidan. Fråga en administratör om du tycker att du borde ha det.'],
        '404' => ['Sidan finns inte', 'Den här sidan finns inte, eller så har den flyttats.'],
        '429' => ['Ta det lugnt', 'Det var många förfrågningar på kort tid. Vänta en minut och försök igen.'],
        '500' => ['Något gick sönder', 'Något gick fel hos oss. Försök igen om en stund.'],
        '503' => ['Strax tillbaka', 'Tjänsten tar en kort paus. Försök igen om några minuter.'],
        'updating' => ['Uppdaterar', 'En ny version installeras. Sidan laddas om av sig själv när det är klart.'],
        'back' => 'Tillbaka', 'home' => 'Startsidan', 'soon' => 'Oftast inom en minut.',
    ],
];

foreach ($pages as $name => [$title, $text, $image]) {
    $words = [];
    foreach ($translations as $code => $set) {
        $words[$code] = ['title' => $set[$name][0], 'text' => $set[$name][1], 'back' => $set['back'], 'home' => $set['home'], 'soon' => $set['soon']];
    }
    $words = json_encode($words, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $translate = "<script>(function(){var w={$words};var m=document.cookie.match(/(?:^|; )ps_lang=([a-z]+)/);var l=m?m[1]:null;if(!w[l]&&l!=='en'){var a=navigator.languages||[navigator.language||'en'];for(var i=0;i<a.length;i++){var c=String(a[i]).slice(0,2).toLowerCase();if(w[c]||c==='en'){l=c;break;}}}var t=w[l];if(!t)return;document.documentElement.lang=l;document.querySelectorAll('[data-k]').forEach(function(n){var k=n.getAttribute('data-k');if(t[k])n.textContent=t[k];});document.title=t.title+' | PlainShow';})();</script>";
    $code = $name === 'updating' ? '' : $name;
    $refresh = $name === 'updating'
        ? "<script>setInterval(function(){fetch(location.href,{method:'HEAD',cache:'no-store'}).then(function(r){if(r.status!==503)location.reload();}).catch(function(){});},6000);</script>"
        : '';
    $actions = $name === 'updating'
        ? '<p class="small" data-k="soon">Usually under a minute.</p>'
        : '<div class="actions"><a class="btn" href="javascript:history.back()" data-k="back">Go back</a><a class="btn primary" href="/" data-k="home">Start page</a></div>';
    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{$title} | PlainShow</title>
<style>
:root{--bg:#151515;--panel:#1d1d1d;--line:#2c2c2c;--text:#f2f2f2;--soft:#b9b9b9;--accent:#fc5000;--accent-fg:#fff;--tint:none}
@media (prefers-color-scheme:light){:root{--bg:#f6f4f1;--panel:#fff;--line:#e4e0da;--text:#2f2e2b;--soft:#55534e}}
:root[data-theme=dark]{--bg:#151515;--panel:#1d1d1d;--line:#2c2c2c;--text:#f2f2f2;--soft:#b9b9b9}
:root[data-theme=light]{--bg:#f6f4f1;--panel:#fff;--line:#e4e0da;--text:#2f2e2b;--soft:#55534e}
:root[data-accent=teal]{--accent:#0ea5a0;--tint:hue-rotate(160deg) saturate(.92) brightness(.92)}
:root[data-accent=indigo]{--accent:#6a5cf6;--tint:hue-rotate(232deg) saturate(.85)}
:root[data-accent=red]{--accent:#e5263f;--tint:hue-rotate(-24deg) saturate(1.05)}
:root[data-accent=yellow]{--accent:#f2b705;--accent-fg:#2f2e2b;--tint:hue-rotate(38deg) saturate(1.5) brightness(1.45)}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px 16px;background:var(--bg);color:var(--text);font:16px/1.5 Outfit,system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}
main{max-width:460px}
img{width:min(260px,60vw);height:auto;display:block;margin:0 auto 8px;filter:var(--tint)}
.code{margin:0;color:var(--accent);font-weight:700;letter-spacing:.12em}
h1{margin:4px 0 8px;font:600 2rem/1.2 Georgia,"Times New Roman",serif}
p{margin:0 0 20px;color:var(--soft)}
.small{font-size:.9rem}
.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{display:inline-block;padding:10px 18px;border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--text);text-decoration:none;font-weight:500}
.btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-fg)}
</style>
<script>try{var t=localStorage.getItem('plainshow-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t)}catch(e){}var a=document.cookie.match(/(?:^|; )ps_accent=(teal|indigo|red|yellow)(?:;|$)/);if(a)document.documentElement.setAttribute('data-accent',a[1]);</script>
</head>
<body>
<main>
<img src="/_errors/{$image}.webp" alt="" width="260" height="260">
<p class="code">{$code}</p>
<h1 data-k="title">{$title}</h1>
<p data-k="text">{$text}</p>
{$actions}
</main>
{$translate}
{$refresh}
</body>
</html>

HTML;
    file_put_contents(__DIR__ . "/../ui/errors/$name.html", $html);
}
echo "Wrote " . count($pages) . " pages.\n";
