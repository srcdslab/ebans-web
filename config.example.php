<?php

define('SERVER_FORUM_NAME', 'mywebsite');
define('SERVER_FORUM_URL', 'https://mywebsite.com');
define('SERVER_NAME', 'My server');
define('STEAM_GROUP', 'https://steamcommunity.com/groups/mysteamgroup');
define('STEAM_API_KEY', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

define('SBPP_DB_HOST', 'example'); // The host/ip to your SQL server
define('SBPP_DB_USER', 'example'); // The username to connect with
define('SBPP_DB_PASSWORD', 'example'); // The password
define('SBPP_DB_NAME', 'example'); // Database name
define('SBPP_DB_PREFIX', 'sb'); // The table prefix for SourceBans
define('SBPP_DB_PORT', '3306'); // The SQL port (Default: 3306)
define('SBPP_DB_CHARSET', 'utf8mb4'); // The Database charset (Default: utf8)


define('DATE_TIME_ZONE', 'GMT+2');
define('DATE_TIME_FORMAT', 'Y-m-d H:i:s');

// Access tiers, by group ID in sb_groups.
//
//   GID_STAFF  may sign in, add an eban, and edit or lift an eban they issued.
//   GID_ADMIN  everything above, plus deleting ebans, reading the Web Logs,
//              and managing ebans issued by somebody else.
//
// Members of GID_ADMIN do not need to be repeated in GID_STAFF. A group listed
// in neither cannot sign in at all.
define('GID_STAFF', [2, 5, 7]);
define('GID_ADMIN', [11]);

define('EBAN_DB_HOST', '127.0.0.1'); // The host/ip to your SQL server
define('EBAN_DB_USER', 'example'); // The username to connect with
define('EBAN_DB_PASSWORD', 'example'); // The password
define('EBAN_DB_NAME', 'example'); // Database name
define('EBAN_DB_PORT', '3306'); // The SQL port (Default: 3306)
define('EBAN_DB_CHARSET', 'utf8mb4'); // The Database charset (EntWatch 4 uses utf8mb4)
define('EBAN_DB_PREFIX', ''); // The table prefix for EBans

?>