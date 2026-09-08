<?php

    include_once('connect.php');
    include_once('functions_global.php');

    /* Every one of these was read without a guard, so a request that is not a
       Steam OpenID callback produced a run of "Undefined array key" warnings
       and then `explode(',', null)`, deprecated since 8.1. */
    function openidParam(string $name): string {
        $value = $_GET[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    $signedList = openidParam('openid_signed');
    if ($signedList === '' || openidParam('openid_sig') === '') {
        echo 'error: unable to validate your request';
        exit();
    }

    $params = [
        'openid.assoc_handle' => openidParam('openid_assoc_handle'),
        'openid.signed'       => $signedList,
        'openid.sig'          => openidParam('openid_sig'),
        'openid.ns'           => 'http://specs.openid.net/auth/2.0',
        'openid.mode'         => 'check_authentication',
    ];

    $signed = explode(',', $signedList);
        
    foreach ($signed as $item) {
        $params['openid.'.$item] = stripslashes(openidParam('openid_'.str_replace('.', '_', $item)));
    }

    $data = http_build_query($params);
    //data prep
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept-language: en\r\n".
            "Content-type: application/x-www-form-urlencoded\r\n".
            'Content-Length: '.strlen($data)."\r\n",
            'content' => $data,
            /* Without this the request can hang for default_socket_timeout,
               holding a worker for every stalled login. */
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    //get the data
    $result = file_get_contents('https://steamcommunity.com/openid/login', false, $context);

    /* On failure file_get_contents() returns false, and passing that to
       preg_match() is a deprecated null/bool-to-string conversion before it
       is a logic error. */
    if (!is_string($result) || !preg_match("#is_valid\s*:\s*true#i", $result)) {
        echo 'error: unable to validate your request';
        exit();
    }

    /* $matches[1] was read whether or not the pattern matched. Anchored at
       both ends now, so a claimed_id with a suffix cannot slip through. */
    if (!preg_match('#^https://steamcommunity.com/openid/id/([0-9]{17,25})$#', openidParam('openid_claimed_id'), $matches)) {
        echo 'error: unable to validate your request';
        exit();
    }

    $steamID64 = $matches[1];

    /* Steam has already confirmed who this is; the profile call only checks
       the account exists. A failure there must not read as a login: on a failed
       call `$response['response']['players'][0]` warned twice and then carried
       on with an empty SteamID. */
    $steam_api_key = $GLOBALS['STEAM_API_KEY'];
    $apiContext = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $response = file_get_contents(
        'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.urlencode($steam_api_key).'&steamids='.urlencode($steamID64),
        false,
        $apiContext
    );

    $decoded = is_string($response) ? json_decode($response, true) : null;
    $confirmedID = $decoded['response']['players'][0]['steamid'] ?? null;
    if (is_string($confirmedID) && $confirmedID !== '') {
        $steamID64 = $confirmedID;
    }

    $steamID32 = Steam::SteamID64_To_SteamID($steamID64);

    /* Steam has confirmed the identity above. Record it server-side; nothing
       about the login is handed to the browser except the session id. */
    $adminRow = ($steamID32 === false) ? null : Admin::lookupEligibleAdmin($steamID32);
    if ($adminRow !== null) {
        establishAdminSession($steamID32, $adminRow);
    }

    $server_host_url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
    header("Location: ". $server_host_url);
    die();
?>
