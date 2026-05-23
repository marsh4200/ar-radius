<?php
/**
 * AR Radius - Reload FreeRADIUS
 *
 * POST /api/reload.php
 *
 * Runs `sudo -n systemctl restart freeradius` via the sudoers allowlist
 * set up by the installer. Returns success/failure + service status.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();
Auth::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Restart FreeRADIUS (allowlisted in /etc/sudoers.d/ar-radius)
$output = [];
$rc = 0;
exec('sudo -n /usr/bin/systemctl restart freeradius 2>&1', $output, $rc);

// Pause briefly then check status so we can report whether it actually came back up
usleep(800000); // 0.8s
$statusOutput = [];
$statusRc = 0;
exec('systemctl is-active freeradius 2>&1', $statusOutput, $statusRc);
$active = trim(implode("\n", $statusOutput));

if ($rc !== 0) {
    Auth::audit('freeradius.reload', 'failure', "rc=$rc output=" . substr(implode("\n", $output), 0, 200));
    json_response([
        'error'   => 'Restart command failed',
        'rc'      => $rc,
        'output'  => implode("\n", $output),
        'active'  => $active,
    ], 500);
}

if ($active !== 'active') {
    Auth::audit('freeradius.reload', 'failed-to-start', "active=$active");
    json_response([
        'error'   => 'FreeRADIUS restarted but is not active. Check `sudo journalctl -u freeradius -n 50` on the server.',
        'active'  => $active,
    ], 500);
}

Auth::audit('freeradius.reload', 'success', 'active=active');
json_response([
    'ok'      => true,
    'active'  => $active,
    'message' => 'FreeRADIUS reloaded successfully. New NAS rules are now active.',
]);
