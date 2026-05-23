# AR Radius — NAS Management Update

Drop these 5 files into your repo. The paths inside this folder mirror the
exact paths they need to live at on GitHub.

## Files in this folder

| File path                          | Action on GitHub                    |
|------------------------------------|-------------------------------------|
| `web/nas.php`                      | **NEW** — create file               |
| `web/api/nas.php`                  | **NEW** — create file               |
| `web/api/reload.php`               | **NEW** — create file               |
| `web/includes/layout.php`          | **REPLACE** the existing file       |
| `web/assets/css/app.css`           | **REPLACE** the existing file       |

## What it adds

- New **NAS Devices** page in the sidebar — add/edit/delete routers from the
  web GUI, no SSH required.
- **Generate** button to create strong shared secrets in one click.
- **Reload FreeRADIUS** button — applies NAS changes on demand.
- Per-NAS active session counter.

## After committing to GitHub

On your live server, open the GUI → **System → Run Update**.
Or from terminal: `sudo /opt/ar-radius/update.sh`

Then refresh your browser. The new **NAS Devices** entry appears in the sidebar.

## How to use

1. Click **NAS Devices** in the sidebar.
2. Click **+ Add NAS**.
3. Fill in:
   - **Short Name** — internal label (e.g. `main-router`).
   - **IP/Hostname** — your router's LAN IP (e.g. `192.168.1.1`).
   - **Shared Secret** — click **Generate**, copy it, paste it into your
     router's RADIUS shared-secret field. Must match exactly.
   - **Type** — pick the closest match for your router.
4. Save.
5. Click **Reload FreeRADIUS** at the top.
6. Connect a device with a user you created in AR Radius.
