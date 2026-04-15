# EntWatch Web Panel

This branch contains the web interface for EntWatch 3 (DZ).

It is compatible with the SourcePawn plugin available here:
https://github.com/srcdslab/sm-plugin-EntWatch

# Installation
1. Copy the contents of the `site` folder to your web hosting.
2. Edit `config.example.php` with your settings, then rename it to `config.php`.
3. If needed, change the number of records displayed per page in `connect.php` with the `$per_page` variable.

# Features
- Displays EntWatch database records on a website.
- Supports desktop and mobile layouts.
- Supports SteamID search.
- Includes multilingual support.
- Supports SourceBans login.
- Keeps logs for eban removal.
- Allows eban duration editing.
- Supports multiple themes.
