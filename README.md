# AR Radius NAS Fix Pack #2

## What was broken

All save / edit / delete / reload calls were being sent as GET requests
instead of POST / PUT / DELETE because the JavaScript helper `ARapi()`
only accepts an options object, but the code passed method as a positional
argument. The wrong method silently fell through and returned the LIST
endpoint as if it were a success — so the success toast appeared, but
nothing actually saved, and Reload returned "Method not allowed".

## Files to push to GitHub

| File             | Action          |
|------------------|-----------------|
| `web/nas.php`    | Replace         |
| `VERSION`        | Replace (1.0.2) |

After committing:
1. On server: `sudo /opt/ar-radius/update.sh` (or System → Run Update)
2. Refresh browser
3. NAS Devices page should now actually save NAS entries
