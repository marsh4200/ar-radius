<?php
/**
 * AR Radius - Update API
 *   GET  /api/update.php?action=check   -> compare local VERSION with remote
 *   POST /api/update.php?action=run     -> runs sudo /opt/ar-radius/update.sh
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();

$cfg    = require __DIR__ . '/../includes/config.php';
$action = $_GET['action'] ?? 'check';

if ($action === 'check') {
    api_check($cfg);
} elseif ($action === 'run') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'POST required'], 405);
    }
    Auth::requireCsrf();
    api_run();
} else {
    json_response(['error' => 'unknown action'], 400);
}

// ---------------------------------------------------------------------------
function api_check(array $cfg): void
{
    $local = ar_version();

    // Build raw URL for remote VERSION file
    $repo   = rtrim((string)$cfg['app']['repo'], '/');
    $branch = (string)$cfg['app']['branch'];
    // Convert https://github.com/owner/repo[.git] -> raw URL
    $repo = preg_replace('/\.git$/', '', $repo);
    $repo = preg_replace('#^https?://github\.com/#', 'https://raw.githubusercontent.com/', $repo);
    $remoteUrl = $repo . '/' . $branch . '/VERSION';

    $remote = '';
    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'AR-Radius-Updater/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        json_response([
            'error'        => 'could not reach GitHub (HTTP ' . $code . ')',
            'remote_url'   => $remoteUrl,
            'curl_error'   => $err,
            'local'        => $local,
        ], 502);
    }

    $remote = trim((string)$body);
    json_response([
        'local'            => $local,
        'remote'           => $remote,
        'update_available' => version_compare($remote, $local, '>'),
    ]);
}

function api_run(): void
{
    $cmd = 'sudo -n /opt/ar-radius/update.sh 2>&1';
    $output = [];
    $rc = 0;
    exec($cmd, $output, $rc);

    Auth::audit(
        Auth::user(),
        'update_run',
        null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        'rc=' . $rc
    );

    json_response([
        'success' => $rc === 0,
        'rc'      => $rc,
        'output'  => implode("\n", $output),
    ]);
}
