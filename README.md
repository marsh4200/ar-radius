# AR Radius NAS Fix Pack

Three files that fix the 500 error on the NAS Devices page.

## What was wrong

- Used `DB::instance()` which doesn't exist (DB class is static — use `DB::all()` etc).
- Called `Auth::audit()` with the wrong argument order.

## Files to replace on GitHub

| File path             | Action                  |
|-----------------------|-------------------------|
| `web/nas.php`         | Replace existing        |
| `web/api/nas.php`     | Replace existing        |
| `web/api/reload.php`  | Replace existing        |

After committing, on the server run **System → Run Update** in the GUI
(or `sudo /opt/ar-radius/update.sh` from terminal) and refresh your browser.
