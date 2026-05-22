<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::require();

$pageTitle = 'Users';
$activeNav = 'users';

$threshold = ar_online_threshold_minutes();

// All RADIUS users (unique usernames from radcheck password rows)
$rows = DB::all("
    SELECT
        rc.username,
        MAX(CASE WHEN rc.attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')
                 THEN rc.value END) AS password,
        MAX(CASE WHEN rc.attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')
                 THEN rc.attribute END) AS pw_attr,
        MAX(CASE WHEN rc.attribute = 'Simultaneous-Use' THEN rc.value END) AS simul_use,
        MAX(CASE WHEN rc.attribute = 'Expiration'       THEN rc.value END) AS expiration,
        m.full_name,
        m.email,
        m.expiry AS meta_expiry,
        (SELECT COUNT(*) FROM radacct a
            WHERE a.username = rc.username AND a.acctstoptime IS NULL) AS open_sessions
    FROM radcheck rc
    LEFT JOIN ar_user_meta m ON m.username = rc.username
    GROUP BY rc.username
    ORDER BY rc.username ASC
");

require __DIR__ . '/includes/layout.php';
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:8px;">
    <div class="text-muted">
        <?= count($rows) ?> user<?= count($rows) === 1 ? '' : 's' ?> total
    </div>
    <button class="ar-btn ar-btn-primary" data-modal-open="userModal" id="btnAddUser">
        + Add User
    </button>
</div>

<div class="ar-table-wrap">
    <table class="ar-table" id="usersTable">
        <thead>
            <tr>
                <th>Username</th>
                <th>Simul-Use</th>
                <th>Status</th>
                <th>Sessions</th>
                <th>Expiry</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-muted text-center" style="padding:30px;">
                No users yet. Click <strong>+ Add User</strong> to create one.
            </td></tr>
        <?php endif ?>
        <?php foreach ($rows as $u): ?>
            <?php
                $online = (int)$u['open_sessions'] > 0;
                $expDisplay = $u['meta_expiry'] ?? $u['expiration'] ?? null;
                $isExpired = false;
                if ($expDisplay) {
                    $ts = strtotime((string)$expDisplay);
                    if ($ts && $ts < time()) { $isExpired = true; }
                }
            ?>
            <tr data-username="<?= e($u['username']) ?>">
                <td>
                    <strong><?= e($u['username']) ?></strong>
                    <?php if (!empty($u['full_name'])): ?>
                        <div class="small text-muted"><?= e($u['full_name']) ?></div>
                    <?php endif ?>
                </td>
                <td><?= $u['simul_use'] !== null ? e($u['simul_use']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php if ($online): ?>
                        <span class="ar-badge online"><span class="ar-dot"></span>Online</span>
                    <?php elseif ($isExpired): ?>
                        <span class="ar-badge expired">Expired</span>
                    <?php else: ?>
                        <span class="ar-badge offline">Offline</span>
                    <?php endif ?>
                </td>
                <td><?= (int)$u['open_sessions'] ?></td>
                <td class="small">
                    <?= $expDisplay ? e($expDisplay) : '<span class="text-muted">Never</span>' ?>
                </td>
                <td class="text-end">
                    <button class="ar-btn ar-btn-sm btn-edit"
                            data-user='<?= e(json_encode($u)) ?>'>Edit</button>
                    <button class="ar-btn ar-btn-sm ar-btn-danger btn-delete"
                            data-username="<?= e($u['username']) ?>">Delete</button>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<!-- User Modal -->
<div class="ar-modal-backdrop" id="userModal">
    <div class="ar-modal">
        <div class="ar-modal-header">
            <h4 class="ar-modal-title" id="userModalTitle">Add User</h4>
            <button class="ar-modal-close" data-modal-close>×</button>
        </div>
        <form id="userForm">
            <div class="ar-modal-body">
                <input type="hidden" id="userMode" value="add">
                <input type="hidden" id="userOriginalName" value="">

                <div class="ar-form-group">
                    <label class="ar-label" for="fUsername">Username *</label>
                    <input type="text" id="fUsername" class="ar-input" required
                           pattern="[A-Za-z0-9._@\-]{1,64}"
                           title="1-64 chars: letters, digits, . _ @ -">
                </div>

                <div class="ar-form-group">
                    <label class="ar-label" for="fPassword">
                        Password <span class="text-muted small" id="fPasswordHint">*</span>
                    </label>
                    <input type="text" id="fPassword" class="ar-input"
                           placeholder="Leave blank to keep existing (edit mode)">
                </div>

                <div class="ar-form-group">
                    <label class="ar-label" for="fSimul">Simultaneous-Use</label>
                    <input type="number" id="fSimul" class="ar-input" min="0" value="1"
                           placeholder="e.g. 1">
                    <div class="small text-muted mt-2">
                        Maximum concurrent sessions. 1 = single login.
                    </div>
                </div>

                <div class="ar-form-group">
                    <label class="ar-label" for="fExpiry">Expiry (optional)</label>
                    <input type="datetime-local" id="fExpiry" class="ar-input">
                </div>

                <div class="ar-form-group">
                    <label class="ar-label" for="fFullName">Full Name (optional)</label>
                    <input type="text" id="fFullName" class="ar-input" maxlength="128">
                </div>

                <div class="ar-form-group">
                    <label class="ar-label" for="fEmail">Email (optional)</label>
                    <input type="email" id="fEmail" class="ar-input" maxlength="128">
                </div>
            </div>
            <div class="ar-modal-footer">
                <button type="button" class="ar-btn" data-modal-close>Cancel</button>
                <button type="submit" class="ar-btn ar-btn-primary" id="userSubmitBtn">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = 'userModal';
    const form  = document.getElementById('userForm');
    const title = document.getElementById('userModalTitle');
    const modeI = document.getElementById('userMode');
    const origN = document.getElementById('userOriginalName');
    const fUser = document.getElementById('fUsername');
    const fPass = document.getElementById('fPassword');
    const fHint = document.getElementById('fPasswordHint');
    const fSim  = document.getElementById('fSimul');
    const fExp  = document.getElementById('fExpiry');
    const fName = document.getElementById('fFullName');
    const fMail = document.getElementById('fEmail');
    const subm  = document.getElementById('userSubmitBtn');

    function resetForm() {
        form.reset();
        modeI.value = 'add';
        origN.value = '';
        fUser.disabled = false;
        fHint.textContent = '*';
        fPass.required = true;
        title.textContent = 'Add User';
    }

    document.getElementById('btnAddUser').addEventListener('click', resetForm);

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            resetForm();
            const u = JSON.parse(btn.dataset.user);
            modeI.value = 'edit';
            origN.value = u.username;
            fUser.value = u.username;
            fUser.disabled = true;
            fPass.required = false;
            fHint.textContent = '(leave blank to keep)';
            fSim.value  = u.simul_use ?? '';
            fName.value = u.full_name ?? '';
            fMail.value = u.email ?? '';
            const exp = u.meta_expiry ?? u.expiration ?? '';
            if (exp) {
                // Convert "YYYY-MM-DD HH:MM:SS" to local datetime-local format
                const m = exp.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);
                fExp.value = m ? (m[1] + 'T' + m[2]) : '';
            }
            title.textContent = 'Edit User: ' + u.username;
            ARmodal.open(modal);
        });
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const username = btn.dataset.username;
            if (!ARconfirm('Delete user "' + username + '"? This removes all radcheck/radreply rows.')) return;
            try {
                await ARapi('/api/users.php?username=' + encodeURIComponent(username), { method: 'DELETE' });
                ARtoast('User deleted', 'success');
                setTimeout(() => location.reload(), 600);
            } catch (e) {
                ARtoast('Delete failed: ' + e.message);
            }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const isEdit = modeI.value === 'edit';
        const payload = {
            username: fUser.value.trim(),
            password: fPass.value,
            simul_use: fSim.value === '' ? null : parseInt(fSim.value, 10),
            expiry: fExp.value || null,
            full_name: fName.value.trim() || null,
            email: fMail.value.trim() || null,
        };
        subm.disabled = true;
        subm.innerHTML = '<span class="ar-spinner"></span> Saving...';
        try {
            if (isEdit) {
                await ARapi('/api/users.php?username=' + encodeURIComponent(origN.value), {
                    method: 'PUT', body: payload,
                });
                ARtoast('User updated', 'success');
            } else {
                await ARapi('/api/users.php', { method: 'POST', body: payload });
                ARtoast('User created', 'success');
            }
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            ARtoast('Save failed: ' + err.message);
            subm.disabled = false;
            subm.textContent = 'Save';
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
