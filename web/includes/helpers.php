<?php
/**
 * AR Radius - Helper functions
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Escape HTML output */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return JSON and exit */
function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read JSON body */
function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Get current app version */
function ar_version(): string {
    $vf = '/opt/ar-radius/VERSION';
    return is_readable($vf) ? trim(file_get_contents($vf)) : 'dev';
}

/** Format bytes nicely */
function fmt_bytes($b): string {
    $b = (int)$b;
    if ($b < 1024) return $b . ' B';
    $u = ['KB','MB','GB','TB'];
    $i = -1;
    do { $b /= 1024; $i++; } while ($b >= 1024 && $i < count($u) - 1);
    return number_format($b, 2) . ' ' . $u[$i];
}

/** Format seconds to human friendly duration */
function fmt_duration($s): string {
    $s = (int)$s;
    if ($s < 60) return $s . 's';
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    if ($h > 0) return sprintf('%dh %dm', $h, $m);
    return $m . 'm';
}

/**
 * Define "online" = an open radacct row (acctstoptime IS NULL) AND
 * acctupdatetime within the configured threshold.
 */
function ar_online_threshold_minutes(): int {
    $v = DB::scalar(
        "SELECT setting_value FROM ar_settings WHERE setting_key = 'online_session_threshold_minutes'"
    );
    return $v !== null ? (int)$v : 15;
}

/** Validate username: 1-64 chars, [A-Za-z0-9._@-] */
function valid_username(string $u): bool {
    return (bool)preg_match('/^[A-Za-z0-9._@-]{1,64}$/', $u);
}
