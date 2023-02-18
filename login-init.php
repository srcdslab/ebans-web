<?php
    $server_host_url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
    $login_url_params = [
        'openid.ns'         => 'http://specs.openid.net/auth/2.0',
        'openid.mode'       => 'checkid_setup',
        'openid.return_to'  => $server_host_url . '/login-process.php',
        'openid.realm'      => $server_host_url,
        'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ];

    $steam_login_url = 'https://steamcommunity.com/openid/login'.'?'.http_build_query($login_url_params, '', '&');

    header("location: $steam_login_url");
    exit();
?>
