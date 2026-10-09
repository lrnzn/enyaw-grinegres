<?php
function config($key = null) {
    $c = $GLOBALS['config'];
    if ($key === null) return $c;
    foreach (explode('.', $key) as $k) { $c = $c[$k] ?? null; }
    return $c;
}
function demo() { return !empty(config('demo_mode')); }
/** Empty string -> NULL (for optional database columns). */
function nn($v) { $v = is_string($v) ? trim($v) : $v; return ($v === '' || $v === null) ? null : $v; }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function base_path() {
    static $b = null;
    if ($b === null) $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $b;
}
function url($path = '') { return base_path() . '/index.php?url=' . ltrim($path, '/'); }
function asset($path)    { return base_path() . '/' . ltrim($path, '/'); }

function current_user() { return $_SESSION['user'] ?? null; }
function is_admin()     { return (current_user()['role'] ?? '') === 'admin'; }

function flash($type, $msg) { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function get_flash() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    $ok = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf']);
    if (!$ok) { http_response_code(419); exit('Session expired. Go back, refresh the page and try again.'); }
}

function avatar($name, $photo = null) {
    // Photos are served only to signed-in users through PhotoController (never directly).
    if ($photo && is_file(ROOT . '/uploads/' . $photo)) return url('photo/show/' . $photo);
    $parts = preg_split('/\s+/', trim($name));
    $first = function ($w) { return preg_match('/^./u', $w, $m) ? $m[0] : ''; };
    $ini = strtoupper($first($parts[0]) . (count($parts) > 1 ? $first(end($parts)) : ''));
    $colors = ['#1E8049', '#25789F', '#14463A', '#2B8C80', '#3F86B8', '#4F8F3A', '#1B4F6B', '#3A7F6E'];
    $c = $colors[abs(crc32($name)) % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="' . $c . '"/>'
         . '<text x="60" y="76" font-family="Lexend,Arial,sans-serif" font-size="44" font-weight="600" fill="#fff" text-anchor="middle">' . e($ini) . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function fmt_dt($s, $f = 'M d, Y h:i A') { return $s ? date($f, strtotime($s)) : '—'; }
function fmt_time($s) { return $s ? date('h:i A', strtotime($s)) : '—'; }

function action_label($a) {
    return [
        'TIME_IN'           => 'Time-in',
        'RELEASED'          => 'Released',
        'RELEASED_OVERRIDE' => 'Released (letter)',
        'DENIED'            => 'Denied',
    ][$a] ?? $a;
}
function action_class($a) {
    return [
        'TIME_IN' => 'b-blue', 'RELEASED' => 'b-green',
        'RELEASED_OVERRIDE' => 'b-amber', 'DENIED' => 'b-red',
    ][$a] ?? 'b-gray';
}

function state_badge($state)
{
    $map = ['on_campus' => ['b-blue', 'On campus'], 'released' => ['b-green', 'Released'], 'absent' => ['b-gray', 'No time-in today']];
    [$c, $l] = $map[$state] ?? $map['absent'];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}

/** Inline SVG icons (stroke style). Usage: <?= icon('check') ?> */
function icon($name, $size = 20)
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'gate'      => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
        'students'  => '<path d="M22 9 12 4 2 9l10 5 10-5Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v6"/>',
        'logs'      => '<rect x="5.5" y="4" width="13" height="17" rx="2"/><path d="M9 4h6v2.5H9zM9 11h6M9 15h6"/>',
        'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert'     => '<path d="M12 4 2.5 20h19L12 4Z"/><path d="M12 10v4M12 17.3v.2"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/>',
        'printer'   => '<path d="M7 9V3.5h10V9"/><rect x="3.5" y="9" width="17" height="8" rx="2"/><path d="M7 14h10v6.5H7z"/>',
        'download'  => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M4.5 19.5h15"/>',
        'camera'    => '<path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.5"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
        'home'      => '<path d="M3.5 11 12 4l8.5 7"/><path d="M5.5 10v10h13V10"/><path d="M10 20v-5h4v5"/>',
        'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1.5 1.5 0 0 1-1.6 1.5C10 20 4 14 3.5 5.6A1.5 1.5 0 0 1 5 4Z"/>',
        'logout'    => '<path d="M9 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H9M16 8l4 4-4 4M20 12H9.5"/>',
        'shield'    => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8 7.5 9.5 4.3-1.5 7.5-5 7.5-9.5V6L12 3Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'refresh'   => '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/>',
        'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/>',
        'back'      => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'forward'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'ban'       => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'trash'     => '<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10 11v5M14 11v5"/>',
        'letter'    => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'users'     => '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 5.3a3.5 3.5 0 0 1 0 6.4M18.5 14.3c1.8.8 3 2.7 3 5.7"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="i" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function greeting()
{
    $h = (int)date('G');
    return $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
}
