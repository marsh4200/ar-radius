<?php
/**
 * AR Radius - Disconnect user API
 * POST /api/disconnect.php { session_id, username }
 *
 * Attempts a RADIUS Disconnect-Message via `radclient` to the NAS recorded
 * in the radacct row. Falls back to just marking the session closed in the DB
 * if radclient is unavailable or the NAS does not accept the request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}
Auth::requireCsrf();

$in        = json_input();
$sessionId = trim((string)($in['session_id'] ?? ''));
$username  = trim((string)($in['username']   ?? ''));

if ($sessionId === '' || $username === '') {
    json_response(['error' => 'session_id and username required'], 400);
}

$row = DB::one(
    "SELECT acctsessionid, acctuniqueid, nasipaddress, username, framedipaddress
     FROM radacct
     WHERE acctsessionid = ? AND username = ? AND acctstoptime IS NULL
     LIMIT 1",
    [$sessionId, $username]
);
if (!$row) {
    json_response(['error' => 'open session not found'], 404);
}

$disconnectAttempted = false;
$radclientOk = false;
$radclientOut = '';

// Find NAS secret
$nasSecret = DB::scalar(
    "SELECT secret FROM nas WHERE nasname = ? OR shortname = ? LIMIT 1",
    [$row['nasipaddress'], $row['nasipaddress']]
);

if ($nasSecret && is_executable('/usr/bin/radclient')) {
    $disconnectAttempted = true;
    $packet  = "User-Name = " . $row['username'] . "\n";
    $packet .= "Acct-Session-Id = " . $row['acctsessionid'] . "\n";
    if (!empty($row['framedipaddress']) && $row['framedipaddress'] !== '0.0.0.0') {
        $packet .= "Framed-IP-Address = " . $row['framedipaddress'] . "\n";
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ar_dm_');
    file_put_contents($tmp, $packet);

    $cmd = sprintf(
        '/usr/bin/radclient -x -t 2 -r 1 %s disconnect %s < %s 2>&1',
        escapeshellarg($row['nasipaddress'] . ':3799'),
        escapeshellarg($nasSecret),
        escapeshellarg($tmp)
    );
    $output = [];
    $rc = 0;
    exec($cmd, $output, $rc);
    @unlink($tmp);
    $radclientOut = implode("\n", $output);
    $radclientOk = ($rc === 0);
}

// Mark session closed in DB regardless (so the UI reflects state)
DB::exec(
    "UPDATE radacct
     SET acctstoptime = NOW(),
         acctterminatecause = 'Admin-Reset',
         acctsessiontime = TIMESTAMPDIFF(SECOND, acctstarttime, NOW())
     WHERE acctsessionid = ? AND username = ? AND acctstoptime IS NULL",
    [$sessionId, $username]
);

Auth::audit(
    Auth::user(),
    'session_disconnect',
    $username,
    $_SERVER['REMOTE_ADDR'] ?? null,
    "sid={$sessionId}; radclient_ok=" . ($radclientOk ? '1' : '0')
);

$msg = $disconnectAttempted
    ? ($radclientOk ? 'Disconnect-Request sent and session closed.' : 'Disconnect-Request failed; session marked closed in DB only.')
    : 'NAS secret unknown or radclient missing; session marked closed in DB only.';

json_response([
    'ok'        => true,
    'message'   => $msg,
    'attempted' => $disconnectAttempted,
    'success'   => $radclientOk,
    'output'    => $radclientOut,
]);
