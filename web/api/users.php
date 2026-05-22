<?php
/**
 * AR Radius - Users REST API
 *   GET    /api/users.php                  -> list users
 *   GET    /api/users.php?username=foo     -> get one user
 *   POST   /api/users.php                  -> create user
 *   PUT    /api/users.php?username=foo     -> update user
 *   DELETE /api/users.php?username=foo     -> delete user
 *
 * All mutating requests require X-CSRF-Token header.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::require();

$method = $_SERVER['REQUEST_METHOD'];
$user   = $_GET['username'] ?? null;

try {
    switch ($method) {
        case 'GET':
            $user ? api_get_one($user) : api_list();
            break;
        case 'POST':
            Auth::requireCsrf();
            api_create();
            break;
        case 'PUT':
        case 'PATCH':
            Auth::requireCsrf();
            if (!$user) json_response(['error' => 'username required'], 400);
            api_update($user);
            break;
        case 'DELETE':
            Auth::requireCsrf();
            if (!$user) json_response(['error' => 'username required'], 400);
            api_delete($user);
            break;
        default:
            json_response(['error' => 'method not allowed'], 405);
    }
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}

// ---------------------------------------------------------------------------

function api_list(): void
{
    $rows = DB::all("
        SELECT
            rc.username,
            MAX(CASE WHEN rc.attribute = 'Simultaneous-Use' THEN rc.value END) AS simul_use,
            MAX(CASE WHEN rc.attribute = 'Expiration'       THEN rc.value END) AS expiration,
            m.full_name, m.email, m.expiry,
            (SELECT COUNT(*) FROM radacct a
                WHERE a.username = rc.username AND a.acctstoptime IS NULL) AS open_sessions
        FROM radcheck rc
        LEFT JOIN ar_user_meta m ON m.username = rc.username
        GROUP BY rc.username
        ORDER BY rc.username ASC
    ");
    json_response(['users' => $rows, 'count' => count($rows)]);
}

function api_get_one(string $username): void
{
    if (!valid_username($username)) {
        json_response(['error' => 'invalid username'], 400);
    }
    $row = DB::one("
        SELECT
            rc.username,
            MAX(CASE WHEN rc.attribute IN
                ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')
                THEN rc.value END) AS password,
            MAX(CASE WHEN rc.attribute = 'Simultaneous-Use' THEN rc.value END) AS simul_use,
            MAX(CASE WHEN rc.attribute = 'Expiration'       THEN rc.value END) AS expiration,
            m.full_name, m.email, m.expiry, m.notes
        FROM radcheck rc
        LEFT JOIN ar_user_meta m ON m.username = rc.username
        WHERE rc.username = ?
        GROUP BY rc.username
    ", [$username]);
    if (!$row) json_response(['error' => 'not found'], 404);
    json_response(['user' => $row]);
}

function api_create(): void
{
    $in = json_input();
    $username = trim((string)($in['username'] ?? ''));
    $password = (string)($in['password'] ?? '');
    $simul    = $in['simul_use'] ?? null;
    $expiry   = $in['expiry']   ?? null;
    $fullName = $in['full_name'] ?? null;
    $email    = $in['email'] ?? null;

    if (!valid_username($username)) {
        json_response(['error' => 'invalid username'], 400);
    }
    if ($password === '') {
        json_response(['error' => 'password required'], 400);
    }

    // Check uniqueness
    $exists = DB::scalar("SELECT 1 FROM radcheck WHERE username = ? LIMIT 1", [$username]);
    if ($exists) json_response(['error' => 'user already exists'], 409);

    $pdo = DB::pdo();
    $pdo->beginTransaction();
    try {
        // Cleartext-Password is what FreeRADIUS default config expects.
        DB::exec(
            "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)",
            [$username, $password]
        );

        if ($simul !== null && $simul !== '') {
            DB::exec(
                "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Simultaneous-Use', ':=', ?)",
                [$username, (string)(int)$simul]
            );
        }

        $expForRadcheck = null;
        if (!empty($expiry)) {
            // Convert "YYYY-MM-DDTHH:MM" to FreeRADIUS Expiration format ("DD Month YYYY HH:MM:SS")
            $ts = strtotime((string)$expiry);
            if ($ts !== false) {
                $expForRadcheck = date('d F Y H:i:s', $ts);
                DB::exec(
                    "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)",
                    [$username, $expForRadcheck]
                );
            }
        }

        // Meta row
        $metaExpiry = !empty($expiry) ? date('Y-m-d H:i:s', strtotime((string)$expiry)) : null;
        DB::exec("
            INSERT INTO ar_user_meta (username, full_name, email, expiry)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email     = VALUES(email),
                expiry    = VALUES(expiry)
        ", [$username, $fullName, $email, $metaExpiry]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Auth::audit(Auth::user(), 'user_create', $username, $_SERVER['REMOTE_ADDR'] ?? null);
    json_response(['ok' => true, 'username' => $username], 201);
}

function api_update(string $username): void
{
    if (!valid_username($username)) {
        json_response(['error' => 'invalid username'], 400);
    }
    $exists = DB::scalar("SELECT 1 FROM radcheck WHERE username = ? LIMIT 1", [$username]);
    if (!$exists) json_response(['error' => 'not found'], 404);

    $in = json_input();
    $password = (string)($in['password'] ?? '');
    $simul    = $in['simul_use'] ?? null;
    $expiry   = $in['expiry']    ?? null;
    $fullName = $in['full_name'] ?? null;
    $email    = $in['email']     ?? null;

    $pdo = DB::pdo();
    $pdo->beginTransaction();
    try {
        // Password (if provided)
        if ($password !== '') {
            $hasPw = DB::scalar(
                "SELECT 1 FROM radcheck
                 WHERE username = ?
                   AND attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')
                 LIMIT 1",
                [$username]
            );
            if ($hasPw) {
                DB::exec(
                    "UPDATE radcheck SET value = ?, attribute = 'Cleartext-Password', op = ':='
                     WHERE username = ?
                       AND attribute IN ('Cleartext-Password','MD5-Password','SHA-Password','Crypt-Password','User-Password')",
                    [$password, $username]
                );
            } else {
                DB::exec(
                    "INSERT INTO radcheck (username, attribute, op, value)
                     VALUES (?, 'Cleartext-Password', ':=', ?)",
                    [$username, $password]
                );
            }
        }

        // Simultaneous-Use
        DB::exec("DELETE FROM radcheck WHERE username = ? AND attribute = 'Simultaneous-Use'", [$username]);
        if ($simul !== null && $simul !== '') {
            DB::exec(
                "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Simultaneous-Use', ':=', ?)",
                [$username, (string)(int)$simul]
            );
        }

        // Expiration
        DB::exec("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'", [$username]);
        $metaExpiry = null;
        if (!empty($expiry)) {
            $ts = strtotime((string)$expiry);
            if ($ts !== false) {
                DB::exec(
                    "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)",
                    [$username, date('d F Y H:i:s', $ts)]
                );
                $metaExpiry = date('Y-m-d H:i:s', $ts);
            }
        }

        DB::exec("
            INSERT INTO ar_user_meta (username, full_name, email, expiry)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email     = VALUES(email),
                expiry    = VALUES(expiry)
        ", [$username, $fullName, $email, $metaExpiry]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Auth::audit(Auth::user(), 'user_update', $username, $_SERVER['REMOTE_ADDR'] ?? null);
    json_response(['ok' => true, 'username' => $username]);
}

function api_delete(string $username): void
{
    if (!valid_username($username)) {
        json_response(['error' => 'invalid username'], 400);
    }

    $pdo = DB::pdo();
    $pdo->beginTransaction();
    try {
        DB::exec("DELETE FROM radcheck     WHERE username = ?", [$username]);
        DB::exec("DELETE FROM radreply     WHERE username = ?", [$username]);
        DB::exec("DELETE FROM radusergroup WHERE username = ?", [$username]);
        DB::exec("DELETE FROM ar_user_meta WHERE username = ?", [$username]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Auth::audit(Auth::user(), 'user_delete', $username, $_SERVER['REMOTE_ADDR'] ?? null);
    json_response(['ok' => true]);
}
