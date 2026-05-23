<?php
/**
 * AR Radius - NAS (Router) Management
 *
 * Manage the RADIUS clients (routers / access points) that are allowed to
 * authenticate against this server.  Stored in the `nas` table which
 * FreeRADIUS reads on startup when `read_clients = yes`.
 */
$pageTitle = 'NAS Devices';
$activeNav = 'nas';
require __DIR__ . '/includes/layout.php';

// Pull all configured NAS devices, plus per-device session counters
$rows = DB::all("
    SELECT
        n.id, n.nasname, n.shortname, n.type, n.ports, n.secret,
        n.server, n.community, n.description,
        (SELECT COUNT(*) FROM radacct
            WHERE nasipaddress = n.nasname AND acctstoptime IS NULL) AS open_sessions
    FROM nas n
    ORDER BY n.shortname ASC, n.nasname ASC
");
?>

<div class="ar-page-header">
  <div>
    <h2 class="mb-1">NAS Devices</h2>
    <p class="text-muted mb-0">Routers and access points allowed to authenticate against this server.</p>
  </div>
  <div class="d-flex" style="gap:.5rem">
    <button class="ar-btn ar-btn-secondary" id="reloadBtn">
      <span class="ar-icon" data-icon="refresh"></span> Reload FreeRADIUS
    </button>
    <button class="ar-btn ar-btn-primary" onclick="openNasModal()">+ Add NAS</button>
  </div>
</div>

<div class="ar-card">
  <table class="ar-table">
    <thead>
      <tr>
        <th>Short Name</th>
        <th>IP / Hostname</th>
        <th>Type</th>
        <th>Description</th>
        <th>Active Sessions</th>
        <th style="width:180px">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" class="text-center text-muted" style="padding:2rem">
            No NAS devices configured yet.
            <br><span class="small">Click "+ Add NAS" above to register your first router.</span>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['shortname']) ?></strong></td>
            <td><code><?= e($r['nasname']) ?></code></td>
            <td><?= e($r['type'] ?: 'other') ?></td>
            <td><?= e($r['description'] ?: '—') ?></td>
            <td>
              <?php if ($r['open_sessions'] > 0): ?>
                <span class="ar-badge ar-badge-online"><?= (int)$r['open_sessions'] ?> active</span>
              <?php else: ?>
                <span class="ar-badge ar-badge-offline">0</span>
              <?php endif ?>
            </td>
            <td>
              <button class="ar-btn ar-btn-sm"
                      onclick='openNasModal(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Edit</button>
              <button class="ar-btn ar-btn-sm ar-btn-danger"
                      onclick="deleteNas(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['shortname'])) ?>')">Delete</button>
            </td>
          </tr>
        <?php endforeach ?>
      <?php endif ?>
    </tbody>
  </table>
</div>

<!-- Add / Edit modal -->
<div class="ar-modal" id="nasModal">
  <div class="ar-modal-backdrop" onclick="ARmodal.close('nasModal')"></div>
  <div class="ar-modal-dialog">
    <div class="ar-modal-header">
      <h3 id="nasModalTitle">Add NAS Device</h3>
      <button class="ar-modal-close" onclick="ARmodal.close('nasModal')">&times;</button>
    </div>
    <div class="ar-modal-body">
      <input type="hidden" id="nasId" value="">

      <label>Short Name <span class="text-muted small">(internal label, e.g. "main-router")</span></label>
      <input type="text" id="nasShortname" maxlength="32" required>

      <label>IP Address or Hostname <span class="text-muted small">(the IP the router uses when talking to this server)</span></label>
      <input type="text" id="nasName" maxlength="128" placeholder="192.168.1.1" required>

      <label>Shared Secret <span class="text-muted small">(must match the secret you set on the router)</span></label>
      <div class="d-flex" style="gap:.5rem">
        <input type="text" id="nasSecret" maxlength="60" required style="flex:1">
        <button type="button" class="ar-btn ar-btn-sm" onclick="genSecret()">Generate</button>
      </div>

      <label>Type</label>
      <select id="nasType">
        <option value="other">other (generic)</option>
        <option value="cisco">cisco</option>
        <option value="mikrotik">mikrotik</option>
        <option value="ubiquiti">ubiquiti</option>
        <option value="aruba">aruba</option>
        <option value="juniper">juniper</option>
      </select>

      <label>Description <span class="text-muted small">(optional)</span></label>
      <input type="text" id="nasDesc" maxlength="200" placeholder="Main office router">
    </div>
    <div class="ar-modal-footer">
      <button class="ar-btn" onclick="ARmodal.close('nasModal')">Cancel</button>
      <button class="ar-btn ar-btn-primary" onclick="saveNas()">Save</button>
    </div>
  </div>
</div>

<script>
function openNasModal(row) {
  document.getElementById('nasModalTitle').textContent = row ? 'Edit NAS Device' : 'Add NAS Device';
  document.getElementById('nasId').value        = row ? row.id        : '';
  document.getElementById('nasShortname').value = row ? row.shortname : '';
  document.getElementById('nasName').value      = row ? row.nasname   : '';
  document.getElementById('nasSecret').value    = row ? row.secret    : '';
  document.getElementById('nasType').value      = row ? (row.type || 'other') : 'other';
  document.getElementById('nasDesc').value      = row ? (row.description || '') : '';
  ARmodal.open('nasModal');
}

function genSecret() {
  // 24 random alphanumeric chars
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  let s = '';
  const arr = new Uint32Array(24);
  crypto.getRandomValues(arr);
  for (let i = 0; i < 24; i++) s += chars[arr[i] % chars.length];
  document.getElementById('nasSecret').value = s;
}

async function saveNas() {
  const id = document.getElementById('nasId').value;
  const payload = {
    shortname:   document.getElementById('nasShortname').value.trim(),
    nasname:     document.getElementById('nasName').value.trim(),
    secret:      document.getElementById('nasSecret').value.trim(),
    type:        document.getElementById('nasType').value,
    description: document.getElementById('nasDesc').value.trim()
  };

  if (!payload.shortname || !payload.nasname || !payload.secret) {
    ARtoast('Short name, IP/hostname and shared secret are all required.', 'error');
    return;
  }

  try {
    if (id) {
      await ARapi('/api/nas.php?id=' + encodeURIComponent(id), 'PUT', payload);
      ARtoast('NAS updated. Click "Reload FreeRADIUS" for changes to take effect.', 'success');
    } else {
      await ARapi('/api/nas.php', 'POST', payload);
      ARtoast('NAS added. Click "Reload FreeRADIUS" for changes to take effect.', 'success');
    }
    ARmodal.close('nasModal');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    ARtoast('Save failed: ' + err.message, 'error');
  }
}

async function deleteNas(id, name) {
  if (!confirm('Delete NAS "' + name + '"? The router will stop being able to authenticate users.')) return;
  try {
    await ARapi('/api/nas.php?id=' + encodeURIComponent(id), 'DELETE');
    ARtoast('NAS deleted. Click "Reload FreeRADIUS" for changes to take effect.', 'success');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    ARtoast('Delete failed: ' + err.message, 'error');
  }
}

document.getElementById('reloadBtn').addEventListener('click', async () => {
  const btn = document.getElementById('reloadBtn');
  btn.disabled = true;
  const original = btn.innerHTML;
  btn.innerHTML = 'Reloading…';
  try {
    const res = await ARapi('/api/reload.php', 'POST', {});
    ARtoast(res.message || 'FreeRADIUS reloaded.', 'success');
  } catch (err) {
    ARtoast('Reload failed: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
