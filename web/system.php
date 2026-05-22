<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::require();

$pageTitle = 'System';
$activeNav = 'system';

$version = ar_version();
$config = require __DIR__ . '/includes/config.php';

// Service status checks (best-effort)
function svc_status(string $name): string {
    $out = [];
    $rc  = 0;
    @exec('systemctl is-active ' . escapeshellarg($name) . ' 2>/dev/null', $out, $rc);
    return trim($out[0] ?? 'unknown');
}

$svcApache = svc_status('apache2');
$svcMysql  = svc_status('mariadb');
$svcRad    = svc_status('freeradius');

// DB stats
$dbStats = [
    'radcheck'    => (int)DB::scalar("SELECT COUNT(*) FROM radcheck"),
    'radacct'     => (int)DB::scalar("SELECT COUNT(*) FROM radacct"),
    'radpostauth' => (int)DB::scalar("SELECT COUNT(*) FROM radpostauth"),
    'nas'         => (int)DB::scalar("SELECT COUNT(*) FROM nas"),
];

require __DIR__ . '/includes/layout.php';
?>

<div class="row">
    <div class="col col-md-6">
        <div class="ar-card">
            <div class="ar-card-header">
                <h3 class="ar-card-title">Update System</h3>
            </div>
            <div class="ar-form-group">
                <div class="ar-label">Installed Version</div>
                <div style="font-size:20px;font-weight:600;">v<?= e($version) ?></div>
            </div>
            <div class="ar-form-group">
                <div class="ar-label">GitHub Repository</div>
                <div class="small"><?= e($config['app']['repo']) ?> (<?= e($config['app']['branch']) ?>)</div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button id="btnCheckUpdate" class="ar-btn">Check for Updates</button>
                <button id="btnRunUpdate" class="ar-btn ar-btn-primary">Run Update</button>
            </div>
            <div id="updateResult" class="mt-3" style="display:none;"></div>
            <div id="updateLog" class="mt-3" style="display:none;
                background:#0a0e13;border:1px solid var(--ar-border);
                border-radius:6px;padding:12px;font-family:monospace;font-size:12px;
                max-height:300px;overflow-y:auto;white-space:pre-wrap;"></div>
        </div>
    </div>

    <div class="col col-md-6">
        <div class="ar-card">
            <div class="ar-card-header">
                <h3 class="ar-card-title">Services</h3>
            </div>
            <div class="ar-table-wrap" style="border:0;">
                <table class="ar-table">
                    <tr>
                        <td>Apache2</td>
                        <td><span class="ar-badge <?= $svcApache === 'active' ? 'online' : 'expired' ?>"><?= e($svcApache) ?></span></td>
                    </tr>
                    <tr>
                        <td>MariaDB</td>
                        <td><span class="ar-badge <?= $svcMysql === 'active' ? 'online' : 'expired' ?>"><?= e($svcMysql) ?></span></td>
                    </tr>
                    <tr>
                        <td>FreeRADIUS</td>
                        <td><span class="ar-badge <?= $svcRad === 'active' ? 'online' : 'expired' ?>"><?= e($svcRad) ?></span></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="ar-card">
            <div class="ar-card-header">
                <h3 class="ar-card-title">Database</h3>
            </div>
            <div class="ar-table-wrap" style="border:0;">
                <table class="ar-table">
                    <?php foreach ($dbStats as $k => $v): ?>
                        <tr>
                            <td><code><?= e($k) ?></code></td>
                            <td class="text-end"><?= number_format($v) ?> row<?= $v === 1 ? '' : 's' ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const btnCheck = document.getElementById('btnCheckUpdate');
const btnRun   = document.getElementById('btnRunUpdate');
const result   = document.getElementById('updateResult');
const log      = document.getElementById('updateLog');

btnCheck.addEventListener('click', async () => {
    btnCheck.disabled = true;
    btnCheck.innerHTML = '<span class="ar-spinner"></span> Checking...';
    result.style.display = 'block';
    result.className = 'ar-alert ar-alert-info';
    result.textContent = 'Contacting GitHub...';
    try {
        const data = await ARapi('/api/update.php?action=check');
        if (data.update_available) {
            result.className = 'ar-alert ar-alert-info';
            result.innerHTML = '🔔 <strong>Update available</strong><br>' +
                'Local: <code>' + data.local + '</code><br>' +
                'Remote: <code>' + data.remote + '</code>';
        } else {
            result.className = 'ar-alert ar-alert-success';
            result.textContent = '✓ You are up to date (' + data.local + ').';
        }
    } catch (e) {
        result.className = 'ar-alert';
        result.textContent = 'Check failed: ' + e.message;
    }
    btnCheck.disabled = false;
    btnCheck.textContent = 'Check for Updates';
});

btnRun.addEventListener('click', async () => {
    if (!ARconfirm('Run update now?\n\nThis pulls the latest code from GitHub and runs update.sh.')) return;
    btnRun.disabled = true;
    btnCheck.disabled = true;
    btnRun.innerHTML = '<span class="ar-spinner"></span> Updating...';
    log.style.display = 'block';
    log.textContent = 'Starting update...\n';
    try {
        const data = await ARapi('/api/update.php?action=run', { method: 'POST' });
        log.textContent += (data.output || '') + '\n';
        if (data.success) {
            log.textContent += '\n✓ Update completed.\nReloading in 2s...';
            setTimeout(() => location.reload(), 2000);
        } else {
            log.textContent += '\n✗ Update reported failure.';
            btnRun.disabled = false;
            btnCheck.disabled = false;
            btnRun.textContent = 'Run Update';
        }
    } catch (e) {
        log.textContent += '\n✗ Error: ' + e.message;
        btnRun.disabled = false;
        btnCheck.disabled = false;
        btnRun.textContent = 'Run Update';
    }
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
