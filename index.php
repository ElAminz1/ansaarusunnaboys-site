<?php
/* Ansaaru Sunna Academy – Boys Boarding: the school website.

   Every page is drawn from the content published in the school portal (Website section, Super Admin). This file
   asks the portal for that content at most every two minutes and keeps a copy in cache/, so the website stays up
   and fast even when the portal is busy or down. If the portal has never been reached, the copy that came with
   these files (lib/seed_feed.json) is shown. Contact messages are passed on to the portal from this server.

   Settings: config.php (made from config.sample.php): portal_url and key. Nothing else to change here. */
error_reporting(E_ALL); ini_set('display_errors', '0');
date_default_timezone_set('Africa/Nairobi');
require __DIR__ . '/lib/site_render.php';

$cfg = is_file(__DIR__ . '/config.php') ? (array)(include __DIR__ . '/config.php') : [];
$portal = rtrim((string)($cfg['portal_url'] ?? 'https://portal.ansaarusunnaboys.co.ke/'), '/') . '/';
$key = (string)($cfg['key'] ?? '');
$cacheDir = __DIR__ . '/cache'; if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . '/feed.json';
$ttl = (int)($cfg['refresh_seconds'] ?? 120);

/** Asks the portal for the published content. Returns [status, body]. */
function web_get(string $url, int $timeout = 6): array {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'ASA-Website/1.0', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $b = curl_exec($c); $s = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c);
        return [$s, is_string($b) ? $b : ''];
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: ASA-Website/1.0\r\n"]]);
    $b = @file_get_contents($url, false, $ctx); $s = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $s = (int)$m[1];
    return [$s, is_string($b) ? $b : ''];
}
function web_post(string $url, array $data, int $timeout = 8): array {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_USERAGENT => 'ASA-Website/1.0']);
        $b = curl_exec($c); $s = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c);
        return [$s, is_string($b) ? $b : ''];
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => $timeout, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\nUser-Agent: ASA-Website/1.0\r\n", 'content' => json_encode($data)]]);
    $b = @file_get_contents($url, false, $ctx); $s = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $s = (int)$m[1];
    return [$s, is_string($b) ? $b : ''];
}
/** The content to draw: the cached copy while it is fresh, else a new copy from the portal, else the old copy. */
function web_feed(string $portal, string $key, string $cacheFile, int $ttl): array {
    $have = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
    $fresh = is_array($have) && (time() - (int)@filemtime($cacheFile)) < $ttl;
    if ($fresh) return $have;
    /* one visitor refreshes; the others are given the copy meanwhile */
    $lock = @fopen($cacheFile . '.lock', 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB) && is_array($have)) return $have;
    [$st, $body] = web_get($portal . 'site_feed.php?' . http_build_query(['v' => is_array($have) ? (string)($have['version'] ?? '') : '', 'key' => $key]));
    $new = $st === 200 ? json_decode($body, true) : null;
    if (is_array($new) && isset($new['pages'], $new['settings'])) {
        @file_put_contents($cacheFile . '.tmp', $body, LOCK_EX); @rename($cacheFile . '.tmp', $cacheFile);
        $have = $new;
    } elseif (is_array($have)) @touch($cacheFile);   // 304 (nothing new), or the portal is down: keep the copy, ask again later
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    if (is_array($have)) return $have;
    $seed = json_decode((string)@file_get_contents(__DIR__ . '/lib/seed_feed.json'), true);
    return is_array($seed) ? $seed : [];
}

$uri = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = trim(rawurldecode($uri), '/');
/* old addresses (about.html, index.html) move for good to the new ones */
if (preg_match('~^(.*?)(?:index)?\.html?$~', $path, $m)) { $to = '/' . trim($m[1], '/'); header('Location: ' . ($to === '/' ? '/' : $to), true, 301); exit; }
if ($path === 'index.php') $path = '';

$feed = web_feed($portal, $key, $cacheFile, $ttl);
if (!$feed) { http_response_code(503); header('Retry-After: 120'); exit('The website is being updated. Please try again in a few minutes.'); }

$ctx = ['asset' => '/assets/', 'img' => '/', 'media' => $portal . 'site_file.php?id=', 'link' => '', 'preview' => false, 'now' => date('Y-m-d H:i')];
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; frame-src https://www.youtube-nocookie.com https://www.youtube.com; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'");

if ($path === 'sitemap.xml') { header('Content-Type: application/xml; charset=utf-8'); echo sr_sitemap($feed); exit; }
if ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    $no = ($feed['seo']['robots'] ?? 'index,follow') === 'noindex,nofollow';
    echo "User-agent: *\n" . ($no ? "Disallow: /\n" : "Disallow: /cache/\nDisallow: /lib/\n") . 'Sitemap: ' . rtrim((string)($feed['seo']['base_url'] ?? ''), '/') . "/sitemap.xml\n"; exit;
}

/* the contact form: checked here, then passed to the portal, which keeps it for the school */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($path !== 'contact') { http_response_code(405); exit; }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $data = ['key' => $key, 'ip' => $ip];
    foreach (['name', 'email', 'phone', 'subject', 'message', 'website', 't'] as $k) $data[$k] = mb_substr((string)($_POST[$k] ?? ''), 0, 4000);
    $state = 'err'; $err = '';
    if ($key === '') $err = 'The form cannot send right now. Please call or WhatsApp the school.';
    else {
        [$st, $body] = web_post($portal . 'site_contact.php', $data);
        $j = json_decode($body, true);
        if ($st === 200 && !empty($j['ok'])) $state = 'ok';
        else $err = is_array($j) && !empty($j['error']) ? (string)$j['error'] : 'The message could not be sent just now. Please call or WhatsApp the school.';
    }
    if ($state === 'ok') {   // back to the page the form was on, so a refresh does not send it again
        $ref = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        $back = preg_match('~^/[A-Za-z0-9/_-]*$~', $ref) && $ref !== '' ? $ref : '/contact';
        header('Location: ' . $back . '?sent=1#contact-form', true, 303); exit;
    }
    $ctx['contact_state'] = $state; $ctx['contact_error'] = $err;
    header('Cache-Control: no-store');
} elseif (($_GET['sent'] ?? '') === '1') { $ctx['contact_state'] = 'ok'; header('Cache-Control: no-store'); }

[$status, $html] = sr_render($feed, $path, $ctx);
http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') header('Cache-Control: public, max-age=60');
echo $html;
