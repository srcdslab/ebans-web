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
define('SB_NEW_SALT', '$5$'); //Salt for passwords

define('SECRET_KEY', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Used to prevent cookies injection

define('DATE_TIME_ZONE', 'GMT+2');
define('DATE_TIME_FORMAT', 'Y-m-d H:i:s');

define('GID_STAFF', [2, 5, 7]); // Group ID of staff in sb_groups
define('GID_ADMIN', [11]); // Group ID of server admins in sb_groups

define('EBAN_DB_HOST', '127.0.0.1'); // The host/ip to your SQL server
define('EBAN_DB_USER', 'example'); // The username to connect with
define('EBAN_DB_PASSWORD', 'example'); // The password
define('EBAN_DB_NAME', 'example'); // Database name
define('EBAN_DB_PORT', '3306'); // The SQL port (Default: 3306)
define('EBAN_DB_CHARSET', 'utf8'); // The Database charset (Default: utf8)
define('EBAN_DB_PREFIX', ''); // The table prefix for EBans

?>