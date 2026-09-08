<?php

// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', dirname(__FILE__) . "/");

if (!file_exists(ROOT.'/config.php')) {
    die('Missing config.php.');
}
require_once(ROOT.'/config.php');

$GLOBALS['DB'] = mysqli_connect(
                    EBAN_DB_HOST,
                    EBAN_DB_USER,
                    EBAN_DB_PASSWORD,
                    EBAN_DB_NAME,
                    (int) EBAN_DB_PORT
                    );

// Check Eban DB connection
if (!$GLOBALS['DB']) {
    die('Main Database Connection error: ' . mysqli_connect_error());
}

// EntWatch 4 creates its table as utf8mb4, so the connection has to match or
// player names outside the BMP come back mangled.
mysqli_set_charset($GLOBALS['DB'], EBAN_DB_CHARSET);


$GLOBALS['SBPP'] = mysqli_connect(
                            SBPP_DB_HOST,
                            SBPP_DB_USER,
                            SBPP_DB_PASSWORD,
                            SBPP_DB_NAME,
                            (int) SBPP_DB_PORT);

// Check SBPP DB connection
if (!$GLOBALS['SBPP']) {
    die('SBPP Database Connection error: ' . mysqli_connect_error());
}

// Same reasoning as the eban connection above: without this, admin names come
// back in whatever the server default happens to be.
mysqli_set_charset($GLOBALS['SBPP'], SBPP_DB_CHARSET);


/*
 * Table names.
 *
 * These come from configuration and never from a request, and the allowlist
 * makes that structural: a prefix can only ever be combined with one of the
 * fixed logical names below, so a table name can never become an injection
 * point no matter what a caller passes in.
 */
function eban_table(string $name): string
{
    $tables = [
        'ebans'    => 'EntWatch_Ebans',
        'web_logs' => 'web_logs',
    ];

    if (!isset($tables[$name])) {
        throw new InvalidArgumentException("Unknown eban table: $name");
    }

    return EBAN_DB_PREFIX . $tables[$name];
}

function sbpp_table(string $name): string
{
    $tables = [
        'admins' => '_admins',
    ];

    if (!isset($tables[$name])) {
        throw new InvalidArgumentException("Unknown SourceBans table: $name");
    }

    return SBPP_DB_PREFIX . $tables[$name];
}

$GLOBALS['SERVER_FORUM_NAME'] = SERVER_FORUM_NAME;
$GLOBALS['SERVER_FORUM_URL'] = SERVER_FORUM_URL;
$GLOBALS['SERVER_NAME'] = SERVER_NAME;
$GLOBALS['STEAM_API_KEY'] = STEAM_API_KEY;
$GLOBALS['STEAM_GROUP'] = STEAM_GROUP;

/* TIME ZONE */
date_default_timezone_set('UTC');
?>
