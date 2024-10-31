<?php
include('steam.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $steamid = $_POST['steamid'];
    $steam = new Steam();
    $result = $steam->verifyAndConvertSteamID($steamid);
    echo json_encode($result);
}
?>