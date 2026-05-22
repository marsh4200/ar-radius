<?php
/**
 * AR Radius - Stats API for dashboard polling.
 *   GET /api/stats.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();

$threshold = ar_online_threshold_minutes();

$stats = [
    'total_users'  => (int)DB::scalar("
        SELECT COUNT(DISTINCT username) FROM radcheck
        WHERE attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')
    "),
    'online_users' => (int)DB::scalar("
        SELECT COUNT(DISTINCT username) FROM radacct
        WHERE acctstoptime IS NULL
          AND acctupdatetime >= (NOW() - INTERVAL ? MINUTE)
    ", [$threshold]),
    'active_sessions' => (int)DB::scalar("
        SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL
    "),
    'auths_today_total'   => (int)DB::scalar(
        "SELECT COUNT(*) FROM radpostauth WHERE DATE(authdate) = CURDATE()"
    ),
    'auths_today_success' => (int)DB::scalar(
        "SELECT COUNT(*) FROM radpostauth WHERE DATE(authdate) = CURDATE() AND reply = 'Access-Accept'"
    ),
    'online_threshold_minutes' => $threshold,
    'version'      => ar_version(),
    'server_time'  => date('c'),
];

json_response($stats);
