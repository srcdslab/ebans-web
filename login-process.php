<?php

    include_once('connect.php');
    include_once('functions_global.php');

    $params = [
        'openid.assoc_handle' => $_GET['openid_assoc_handle'],
        'openid.signed'       => $_GET['openid_signed'],
        'openid.sig'          => $_GET['openid_sig'],
        'openid.ns'           => 'http://specs.openid.net/auth/2.0',
        'openid.mode'         => 'check_authentication',
    ];

    $signed = explode(',', $_GET['openid_signed']);
        
    foreach ($signed as $item) {
        $val = $_GET['openid_'.str_replace('.', '_', $item)];
        $params['openid.'.$item] = stripslashes($val);
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
        ],
    ]);

    //get the data
    $result = file_get_contents('https://steamcommunity.com/openid/login', false, $context);

    if (preg_match("#is_valid\s*:\s*true#i", $result)){
        preg_match('#^https://steamcommunity.com/openid/id/([0-9]{17,25})#', $_GET['openid_claimed_id'], $matches);
        $steamID64 = is_numeric($matches[1]) ? $matches[1] : 0;
    } else {
        echo 'error: unable to validate your request';
        exit();
    }

    $steam_api_key = $GLOBALS['STEAM_API_KEY'];

    $response = file_get_contents('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='.$steam_api_key.'&steamids='.$steamID64);
    $response = json_decode($response,true);


    $userData = $response['response']['players'][0];

    $steamID64 = $userData['steamid'];
    $steam = new Steam();
    $steamID32 = $steam->SteamID64_To_SteamID($steamID64);

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
