# EntWatch Web Panel

This branch contains the web interface for EntWatch 4 (DZ).

It is compatible with the SourcePawn plugin available here:
https://github.com/srcdslab/sm-plugin-entwatch-4

# Requirements
- PHP 8.3 or newer, with the `mysqli` and `bcmath` extensions enabled
  (`bcmath` is used for the 64-bit SteamID arithmetic in `steam.php`).
- MySQL 5.7 / MariaDB 10.3 or newer.
- A SourceBans++ database for admin login.
- **HTTPS.** The login cookies are issued with the `Secure` attribute, so a
  browser will not store them over plain HTTP and signing in silently does
  nothing.

# Installation
1. Copy the contents of this repository to your web hosting. The panel runs from
   the document root; there is no build step.
2. Copy `config.example.php` to `config.php` and fill in your settings.
   `config.php` is deliberately excluded by `.gitignore` — never commit it.
3. If needed, change the number of records displayed per page with the
   `$resultsPerPage` variable in `index.php` and `logs.php`.

# Upgrading from EntWatch 3
EntWatch 4 replaced the `EntWatch_Current_Eban` / `EntWatch_Old_Eban` pair with a
single `EntWatch_Ebans` table, so the existing records have to be converted before
this panel can read them.

1. Back up the database: `mysqldump -u USER -p DBNAME > ew3-backup.sql`
2. Run `sql/migrate_ew3_to_ew4.sql` against the eban database.
3. Follow the pre-flight and verification queries documented inside that file.

The script never modifies the EntWatch 3 tables, so it can be re-run and the old
tables can be kept around for as long as you may want to roll back.

# Upgrade note: sign-in

The panel signs admins in with a server-side PHP session. It previously used
three cookies whose only secret was `SECRET_KEY`, a single constant shared by
every user, which meant anyone holding that one value could present themselves
as any other admin.

`SECRET_KEY` is no longer used and has been removed from `config.example.php`;
you can delete it from your `config.php`. Everyone signed in at the time of the
upgrade is signed out once and has to sign in again. Sessions now expire 12
hours after sign-in.

# Upgrade note: full-access groups

The "full access" tier -- delete an eban, read the Web Logs, manage an eban
somebody else issued -- used to be a hardcoded list of group IDs (`1, 3, 4`)
that no configuration setting could reach. It now comes from `GID_ADMIN`.

Before upgrading, check which groups should hold that tier and list them in
`GID_ADMIN`. If your admins were previously in `GID_STAFF`, moving them to
`GID_ADMIN` is what preserves their access.

# Features
- Displays EntWatch database records on a website.
- Supports desktop and mobile layouts.
- Supports SteamID search.
- Includes multilingual support.
- Supports SourceBans login.
- Keeps logs for eban removal.
- Allows eban duration editing.
- Supports multiple themes.
