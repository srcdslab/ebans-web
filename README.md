# EntWatch Web Panel

This branch contains the web interface for EntWatch 4 (DZ).

It is compatible with the SourcePawn plugin available here:
https://github.com/srcdslab/sm-plugin-entwatch-4

# Installation
1. Copy the contents of the `site` folder to your web hosting.
2. Edit `config.example.php` with your settings, then rename it to `config.php`.
3. If needed, change the number of records displayed per page in `connect.php` with the `$per_page` variable.

# Upgrading from EntWatch 3
EntWatch 4 replaced the `EntWatch_Current_Eban` / `EntWatch_Old_Eban` pair with a
single `EntWatch_Ebans` table, so the existing records have to be converted before
this panel can read them.

1. Back up the database: `mysqldump -u USER -p DBNAME > ew3-backup.sql`
2. Run `sql/migrate_ew3_to_ew4.sql` against the eban database.
3. Follow the pre-flight and verification queries documented inside that file.

The script never modifies the EntWatch 3 tables, so it can be re-run and the old
tables can be kept around for as long as you may want to roll back.

# Features
- Displays EntWatch database records on a website.
- Supports desktop and mobile layouts.
- Supports SteamID search.
- Includes multilingual support.
- Supports SourceBans login.
- Keeps logs for eban removal.
- Allows eban duration editing.
- Supports multiple themes.
