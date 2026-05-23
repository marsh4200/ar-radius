<?php
/**
 * AR Radius - NAS REST API
 *
 * GET    /api/nas.php           - list all
 * GET    /api/nas.php?id=N      - fetch one
 * POST   /api/nas.php           - create (body: {shortname, nasname, secret, type, description})
 * PUT    /api/nas.php?id=N      - update
 * DELETE /api/nas.php?id=N      - delete
 *
 * All mutations require CSRF token and write to ar_audit_log.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- GET --------------------------------------------------------------
if ($method === 'GET') {
    if ($id > 0) {
        $row = DB::one("SELECT * FROM nas WHERE id = ?", [$id]);
        if (!$row) json_response(['error' => 'NAS not found'], 404);
        json_response(['nas' => $row]);
    }
    $rows = DB::all("SELECT * FROM nas ORDER BY shortname ASC");
    json_response(['nas' => $rows]);
}

// All mutating operations require CSRF
Auth::requireCsrf();

$body = json_input();

// ---------- helpers ----------------------------------------------------------
function valid_nas_name(string $s): bool {
    // IP or DNS hostname, 1-128 chars, allow IPv4/IPv6/hostname chars
    return (bool)preg_match('/^[A-Za-z0-9._:\-]{1,128}$/', $s);
}

function valid_shortname(string $s): bool {
    return (bool)preg_match('/^[A-Za-z0-9._\-]{1,32}$/', $s);
}

function clean(array $body, string $key, int $max = 200): string {
    return substr(trim((string)($body[$key] ?? '')), 0, $max);
}

// ---------- POST (create) ----------------------------------------------------
if ($method === 'POST') {
    $shortname = clean($body, 'shortname', 32);
    $nasname   = clean($body, 'nasname',   128);
    $secret    = clean($body, 'secret',    60);
    $type      = clean($body, 'type',      30) ?: 'other';
    $desc      = clean($body, 'description', 200);

    if (!valid_shortname($shortname)) json_response(['error' => 'Invalid short name (letters, numbers, . _ - only, max 32)'], 400);
    if (!valid_nas_name($nasname))    json_response(['error' => 'Invalid IP/hostname'], 400);
    if (strlen($secret) < 4)          json_response(['error' => 'Shared secret must be at least 4 characters'], 400);

    // Prevent duplicate nasname
    $existing = DB::one("SELECT id FROM nas WHERE nasname = ?", [$nasname]);
    if ($existing) json_response(['error' => 'A NAS with that IP/hostname already exists'], 409);

    DB::exec(
        "INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)",
        [$nasname, $shortname, $type, $secret, $desc]
    );
    $newId = DB::lastId();

    Auth::audit(Auth::user(), 'nas.create', $shortname, $_SERVER['REMOTE_ADDR'] ?? null, "id=$newId ip=$nasname type=$type");
    json_response(['ok' => true, 'id' => $newId]);
}

// ---------- PUT (update) -----------------------------------------------------
if ($method === 'PUT') {
    if ($id <= 0) json_response(['error' => 'Missing id'], 400);

    $existing = DB::one("SELECT * FROM nas WHERE id = ?", [$id]);
    if (!$existing) json_response(['error' => 'NAS not found'], 404);

    $shortname = clean($body, 'shortname', 32);
    $nasname   = clean($body, 'nasname',   128);
    $secret    = clean($body, 'secret',    60);
    $type      = clean($body, 'type',      30) ?: 'other';
    $desc      = clean($body, 'description', 200);

    if (!valid_shortname($shortname)) json_response(['error' => 'Invalid short name'], 400);
    if (!valid_nas_name($nasname))    json_response(['error' => 'Invalid IP/hostname'], 400);
    if (strlen($secret) < 4)          json_response(['error' => 'Shared secret must be at least 4 characters'], 400);

    // Check duplicates (but allow keeping our own nasname)
    $dup = DB::one("SELECT id FROM nas WHERE nasname = ? AND id != ?", [$nasname, $id]);
    if ($dup) json_response(['error' => 'Another NAS already uses that IP/hostname'], 409);

    DB::exec(
        "UPDATE nas SET nasname = ?, shortname = ?, type = ?, secret = ?, description = ? WHERE id = ?",
        [$nasname, $shortname, $type, $secret, $desc, $id]
    );

    Auth::audit(Auth::user(), 'nas.update', $shortname, $_SERVER['REMOTE_ADDR'] ?? null, "id=$id ip=$nasname");
    json_response(['ok' => true]);
}

// ---------- DELETE -----------------------------------------------------------
if ($method === 'DELETE') {
    if ($id <= 0) json_response(['error' => 'Missing id'], 400);

    $existing = DB::one("SELECT * FROM nas WHERE id = ?", [$id]);
    if (!$existing) json_response(['error' => 'NAS not found'], 404);

    DB::exec("DELETE FROM nas WHERE id = ?", [$id]);

    Auth::audit(Auth::user(), 'nas.delete', $existing['shortname'], $_SERVER['REMOTE_ADDR'] ?? null, "id=$id ip=" . $existing['nasname']);
    json_response(['ok' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
