<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::require();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

// --- Stats ---
$totalUsers = (int)DB::scalar(
    "SELECT COUNT(DISTINCT username) FROM radcheck
     WHERE attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')"
);

$threshold = ar_online_threshold_minutes();

$activeSessions = (int)DB::scalar(
    "SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL"
);

$onlineUsers = (int)DB::scalar(
    "SELECT COUNT(DISTINCT username) FROM radacct
     WHERE acctstoptime IS NULL
       AND acctupdatetime >= (NOW() - INTERVAL ? MINUTE)",
    [$threshold]
);

$authsToday = (int)DB::scalar(
    "SELECT COUNT(*) FROM radpostauth WHERE DATE(authdate) = CURDATE()"
);

$authSuccessToday = (int)DB::scalar(
    "SELECT COUNT(*) FROM radpostauth WHERE DATE(authdate) = CURDATE() AND reply = 'Access-Accept'"
);

$authFailToday = $authsToday - $authSuccessToday;

$recentLogins = DB::all(
    "SELECT username, reply, authdate
     FROM radpostauth
     ORDER BY id DESC
     LIMIT 10"
);

$recentSessions = DB::all(
    "SELECT username, nasipaddress, framedipaddress, acctstarttime, acctsessiontime,
            acctinputoctets, acctoutputoctets, acctstoptime
     FROM radacct
     ORDER BY radacctid DESC
     LIMIT 10"
);

require __DIR__ . '/includes/layout.php';
?>

<div class="ar-stats">
    <div class="ar-stat">
        <div class="ar-stat-label">Total Users</div>
        <div class="ar-stat-value"><?= number_format($totalUsers) ?></div>
        <div class="ar-stat-sub">Registered accounts</div>
    </div>
    <div class="ar-stat success">
        <div class="ar-stat-label">Online Users</div>
        <div class="ar-stat-value"><?= number_format($onlineUsers) ?></div>
        <div class="ar-stat-sub">Active in last <?= $threshold ?> min</div>
    </div>
    <div class="ar-stat accent">
        <div class="ar-stat-label">Active Sessions</div>
        <div class="ar-stat-value"><?= number_format($activeSessions) ?></div>
        <div class="ar-stat-sub">Open (no stop time)</div>
    </div>
    <div class="ar-stat">
        <div class="ar-stat-label">Auths Today</div>
        <div class="ar-stat-value"><?= number_format($authsToday) ?></div>
        <div class="ar-stat-sub">
            <span class="text-success"><?= $authSuccessToday ?> ok</span>
            <?php if ($authFailToday): ?>
                · <span class="text-danger"><?= $authFailToday ?> failed</span>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col col-md-6">
        <div class="ar-card">
            <div class="ar-card-header">
                <h3 class="ar-card-title">Recent Sessions</h3>
                <a href="/sessions.php" class="ar-btn ar-btn-sm">View all</a>
            </div>
            <div class="ar-table-wrap" style="border:0;">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th class="text-end">Traffic</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentSessions): ?>
                        <tr><td colspan="4" class="text-muted text-center">No sessions yet.</td></tr>
                    <?php endif ?>
                    <?php foreach ($recentSessions as $s): ?>
                        <tr>
                            <td><strong><?= e($s['username']) ?></strong></td>
                            <td class="small"><?= e($s['acctstarttime'] ?? '—') ?></td>
                            <td>
                                <?php if (empty($s['acctstoptime'])): ?>
                                    <span class="ar-badge online"><span class="ar-dot"></span>Online</span>
                                <?php else: ?>
                                    <span class="ar-badge offline">Closed</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end small">
                                ↓ <?= e(fmt_bytes($s['acctinputoctets'])) ?><br>
                                ↑ <?= e(fmt_bytes($s['acctoutputoctets'])) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col col-md-6">
        <div class="ar-card">
            <div class="ar-card-header">
                <h3 class="ar-card-title">Recent Authentication</h3>
            </div>
            <div class="ar-table-wrap" style="border:0;">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Result</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentLogins): ?>
                        <tr><td colspan="3" class="text-muted text-center">No auth events yet.</td></tr>
                    <?php endif ?>
                    <?php foreach ($recentLogins as $r): ?>
                        <tr>
                            <td><strong><?= e($r['username']) ?></strong></td>
                            <td>
                                <?php if ($r['reply'] === 'Access-Accept'): ?>
                                    <span class="text-success">✓ Accept</span>
                                <?php else: ?>
                                    <span class="text-danger">✗ Reject</span>
                                <?php endif ?>
                            </td>
                            <td class="small"><?= e($r['authdate']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
