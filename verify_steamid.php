<?php

include('steam.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$steamid = $_POST['steamid'] ?? '';
if (!is_string($steamid) || $steamid === '') {
    echo json_encode(['success' => false, 'error' => 'SteamID cannot be empty']);
    exit();
}

echo json_encode(Steam::verifyAndConvertSteamID($steamid));
