<?php

// ---------------------------------------------------
//  Directories
// ---------------------------------------------------
define('ROOT', dirname(__FILE__) . "/");

if (!file_exists(ROOT.'/config.php')) {
    die('Missing config.php.');
}
require_once(ROOT.'/config.php');

/*
 * Since PHP 8.1 mysqli reports errors by throwing, not by returning false, so
 * the `if (!$GLOBALS['DB'])` checks that used to live here could never run.
 * What happened instead was an uncaught mysqli_sql_exception -- and with
 * display_errors on, its stack trace lists the mysqli_connect() arguments,
 * database password included.
 *
 * The mode is now set explicitly rather than relied on, the exception is
 * caught, the detail goes to the error log, and the browser gets a generic
 * message.
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function connectDatabase(string $label, string $host, string $user, string $password, string $name, int $port, string $charset): mysqli
{
    try {
        $connection = mysqli_connect($host, $user, $password, $name, $port);
        /* EntWatch 4 creates its table as utf8mb4, so the connection has to
           match or player names outside the BMP come back mangled. */
        mysqli_set_charset($connection, $charset);

        return $connection;
    } catch (mysqli_sql_exception $e) {
        error_log("$label database connection failed: " . $e->getMessage());
        http_response_code(503);
        die('The database is currently unavailable. Please try again later.');
    }
}

$GLOBALS['DB'] = connectDatabase(
    'Eban',
    EBAN_DB_HOST,
    EBAN_DB_USER,
    EBAN_DB_PASSWORD,
    EBAN_DB_NAME,
    (int) EBAN_DB_PORT,
    EBAN_DB_CHARSET
);

$GLOBALS['SBPP'] = connectDatabase(
    'SourceBans',
    SBPP_DB_HOST,
    SBPP_DB_USER,
    SBPP_DB_PASSWORD,
    SBPP_DB_NAME,
    (int) SBPP_DB_PORT,
    SBPP_DB_CHARSET
);


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
