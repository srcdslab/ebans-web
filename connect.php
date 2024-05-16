<?php

	// ---------------------------------------------------
	//  Directories
	// ---------------------------------------------------
	define('ROOT', dirname(__FILE__) . "/");

	if (!file_exists(ROOT.'/config.php')) {
		die('Missing config.php.');
	}
	require_once(ROOT.'/config.php');

	$GLOBALS['DB'] = mysqli_connect(EBAN_DB_HOST,
						EBAN_DB_USER,
						EBAN_DB_PASSWORD,
						EBAN_DB_NAME
						);

	// Check Eban DB connection
	if (!$GLOBALS['DB']) {
        die('Main Database Connection error: ' . mysqli_connect_error());
    }


    $GLOBALS['SBPP'] = mysqli_connect( SBPP_DB_HOST,
								SBPP_DB_USER,
                                SBPP_DB_PASSWORD,
                                SBPP_DB_NAME);

	// Check SBPP DB connection
	if (!$GLOBALS['SBPP']) {
        die('SBPP Database Connection error: ' . mysqli_connect_error());
    }

	$GLOBALS['SERVER_FORUM_NAME'] = SERVER_FORUM_NAME;
	$GLOBALS['SERVER_FORUM_URL'] = SERVER_FORUM_URL;
	$GLOBALS['SERVER_NAME'] = SERVER_NAME;
	$GLOBALS['STEAM_API_KEY'] = STEAM_API_KEY;
	$GLOBALS['STEAM_GROUP'] = STEAM_GROUP;
	$GLOBALS['SECRET_KEY'] = SECRET_KEY;

	/* TIME ZONE */
	date_default_timezone_set('UTC');
?>
