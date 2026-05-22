<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::require();

$pageTitle = 'Sessions';
$activeNav = 'sessions';

$filter = $_GET['filter'] ?? 'active';
$where = '';
$params = [];

switch ($filter) {
    case 'active':
        $where = 'WHERE acctstoptime IS NULL';
        break;
    case 'closed':
        $where = 'WHERE acctstoptime IS NOT NULL';
        break;
    case 'all':
    default:
        $where = '';
        break;
}

$sessions = DB::all("
    SELECT radacctid, acctsessionid, acctuniqueid, username,
           nasipaddress, framedipaddress, callingstationid,
           acctstarttime, acctupdatetime, acctstoptime,
           acctsessiontime, acctinputoctets, acctoutputoctets,
           acctterminatecause
    FROM radacct
    $where
    ORDER BY radacctid DESC
    LIMIT 200
", $params);

require __DIR__ . '/includes/layout.php';
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:8px;">
    <div class="d-flex gap-2">
        <a href="?filter=active" class="ar-btn <?= $filter === 'active' ? 'ar-btn-primary' : '' ?>">
            Active
        </a>
        <a href="?filter=closed" class="ar-btn <?= $filter === 'closed' ? 'ar-btn-primary' : '' ?>">
            Closed
        </a>
        <a href="?filter=all" class="ar-btn <?= $filter === 'all' ? 'ar-btn-primary' : '' ?>">
            All
        </a>
    </div>
    <div class="text-muted small">
        Showing <?= count($sessions) ?> session<?= count($sessions) === 1 ? '' : 's' ?> (max 200)
    </div>
</div>

<div class="ar-table-wrap">
    <table class="ar-table">
        <thead>
            <tr>
                <th>User</th>
                <th>NAS</th>
                <th>Framed IP</th>
                <th>Calling Station</th>
                <th>Start</th>
                <th>Duration</th>
                <th class="text-end">Traffic</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$sessions): ?>
            <tr><td colspan="9" class="text-muted text-center" style="padding:30px;">
                No sessions found for this filter.
            </td></tr>
        <?php endif ?>
        <?php foreach ($sessions as $s): ?>
            <?php $isOpen = empty($s['acctstoptime']); ?>
            <tr>
                <td><strong><?= e($s['username']) ?></strong></td>
                <td class="small"><?= e($s['nasipaddress']) ?></td>
                <td class="small"><?= e($s['framedipaddress']) ?></td>
                <td class="small"><?= e($s['callingstationid']) ?></td>
                <td class="small"><?= e($s['acctstarttime'] ?? '—') ?></td>
                <td class="small">
                    <?= e(fmt_duration((int)$s['acctsessiontime'])) ?>
                </td>
                <td class="text-end small">
                    ↓ <?= e(fmt_bytes($s['acctinputoctets'])) ?><br>
                    ↑ <?= e(fmt_bytes($s['acctoutputoctets'])) ?>
                </td>
                <td>
                    <?php if ($isOpen): ?>
                        <span class="ar-badge online"><span class="ar-dot"></span>Online</span>
                    <?php else: ?>
                        <span class="ar-badge offline"><?= e($s['acctterminatecause'] ?: 'Closed') ?></span>
                    <?php endif ?>
                </td>
                <td class="text-end">
                    <?php if ($isOpen): ?>
                        <button class="ar-btn ar-btn-sm ar-btn-danger btn-disconnect"
                                data-session-id="<?= e($s['acctsessionid']) ?>"
                                data-username="<?= e($s['username']) ?>">
                            Disconnect
                        </button>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.btn-disconnect').forEach(btn => {
    btn.addEventListener('click', async () => {
        const sid = btn.dataset.sessionId;
        const user = btn.dataset.username;
        if (!ARconfirm('Disconnect session for ' + user + '?\n\nThis attempts a RADIUS Disconnect-Message via radclient and marks the session closed.')) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="ar-spinner"></span>';
        try {
            const result = await ARapi('/api/disconnect.php', {
                method: 'POST',
                body: { session_id: sid, username: user },
            });
            ARtoast(result.message || 'Disconnect sent', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (e) {
            ARtoast('Disconnect failed: ' + e.message);
            btn.disabled = false;
            btn.textContent = 'Disconnect';
        }
    });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
