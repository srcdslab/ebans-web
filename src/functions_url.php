<?php
    include_once('connect.php');
    include_once('functions_global.php');
    
    if(isset($_GET['id']) && !isset($_GET['reban']) && !isset($_GET['edit'])) {
        $id = $_GET['id'];
        showEbanInfo($id);
    }

    if(isset($_GET['oldid']) && !isset($_GET['reban']) && !isset($_GET['edit'])) {
        if(!isset($_COOKIE['steamID'])) {
            die();
        }

        $admin = new Admin();
        $admin->UpdateAdminInfo($_COOKIE['steamID']);

        $id = $_GET['oldid'];
        
        $Eban = new Eban();
        $info = $Eban->getEbanInfoFromID($id);
        if(!IsAdminLoggedIn() || (!$admin->DoesHaveFullAccess() && $info['admin_steamid'] != $admin->adminSteamID)) {
            die();
        }
        
        $reason = $_GET['reason'];
        $Eban = new Eban();
        if(!$Eban->UnbanByID($id, $reason)) {
            die();
        } 

        die();
    }

    function showEbanInfo(int $id) {
       GetRowInfo($id);
    }

    if(isset($_GET['add']) && isset($_GET['playerName'])) {
        if(!IsAdminLoggedIn()) {
            die();
        }

        $playerName = $_GET['playerName'];
        $playerSteamID = $_GET['playerSteamID'];
        $length = $_GET['length'];
        $reason = $_GET['reason'];

        $icon = "<i class='fa-solid fa-xmark'></i>&nbsp";
        if(empty($playerName)) {
            echo "<p>$icon Player name cannot be empty!</p>";
            die();
        }

        if(empty($playerSteamID)) {
            echo "<p>$icon Player SteamID cannot be empty!</p>";
            die();
        }

        if(!preg_match("/^STEAM_[0-5]:[01]:\d+$/", $playerSteamID)) {
            echo "<p>$icon Invalid SteamID Format</p>";
            die();
        }

        if(empty($reason)) {
            echo "<p>$icon Reason cannot be empty!</p>";
            die();
        }

        if(str_contains($playerName, "'") || str_contains($playerName, "\"")) {
            echo "<p>$icon ' and \" characters cannot be used for Player Name!</p>";
            die();
        }

        if(str_contains($reason, "'") || str_contains($reason, "\"")) {
            echo "<p>$icon ' and \" characters cannot be used for Reason!</p>";
            die();
        }

        $Eban = new Eban();
        if($Eban->IsSteamIDAlreadyBanned($playerSteamID)) {
            echo "<p>$icon $playerSteamID is already Ebanned!</p>";
            die();
        }

        $Eban->addNewEban($playerName, $playerSteamID, $length, $reason);
    }

    if(isset($_GET['edit']) && isset($_GET['playerName'])) {
        if(!isset($_COOKIE['steamID'])) {
            die();
        }

        $id = $_GET['id'];
        $playerName = $_GET['playerName'];
        $playerSteamID = $_GET['playerSteamID'];
        $length = $_GET['length'];
        $reason = $_GET['reason'];

        $icon = "<i class='fa-solid fa-xmark'></i>&nbsp";
        if(empty($playerName)) {
            echo "<p>$icon Player name cannot be empty!</p>";
            die();
        }

        if(empty($playerSteamID)) {
            echo "<p>$icon Player SteamID cannot be empty!</p>";
            die();
        }

        if(!preg_match("/^STEAM_[0-5]:[01]:\d+$/", $playerSteamID)) {
            echo "<p>$icon Invalid SteamID Format</p>";
            die();
        }

        if(empty($reason)) {
            echo "<p>$icon Reason cannot be empty!</p>";
            die();
        }

        if(str_contains($playerName, "'") || str_contains($playerName, "\"")) {
            echo "<p>$icon ' and \" characters cannot be used for Player Name!</p>";
            die();
        }

        if(str_contains($reason, "'") || str_contains($reason, "\"")) {
            echo "<p>$icon ' and \" characters cannot be used for Reason!</p>";
            die();
        }
        
        $admin = new Admin();
        $admin->UpdateAdminInfo($_COOKIE['steamID']);

        if($length == 0 && !$admin->DoesHaveFullAccess()) {
            echo "<p>$icon You do not have permission for Permanent bans!</p>";
            die();
        }

        if(empty($reason)) {
            $reason = "NO REASON";
        }

        if(empty($length)) {
            $length = 0;
        }
        
        if($length < 0) {
            $length = 60;
        }

        $Eban = new Eban();

        $info = $Eban->getEbanInfoFromID($id);

        if(!IsAdminLoggedIn() || (!$admin->DoesHaveFullAccess() && $info['admin_steamid'] != $admin->adminSteamID)) {
            die();
        }

        if($playerName == $info['client_name'] && $playerSteamID == $info['client_steamid'] && $reason == $info['reason'] && $length == ($info['duration'] * 60)) {
            echo "<p>$icon Cannot detect any changes to edit!</p>";
            die();
        }

        if(!$Eban->IsSteamIDAlreadyBanned($playerSteamID)) {
            echo "<p>$icon The edited steamid is alread Eunbanned and cannot be edited from here</p>";
            die();
        }

        $timestamp_issued = (($info['timestamp_issued'] - ($info['duration'] * 60)) + $length);
        if($timestamp_issued < time()) {
            echo "<p>$icon Invalid Duration! Expected a duration that will last in the future but got a one that has already ended.</p>";
            die();
        }

        $Eban->EditEban($id, $playerName, $playerSteamID, $length, $reason);
    }

    if(isset($_GET['delete'])) {
        if(!isset($_COOKIE['steamID'])) {
            die();
        }
        
        $admin = new Admin();
        $admin->UpdateAdminInfo($_COOKIE['steamID']);
        if(!IsAdminLoggedIn() || !$admin->DoesHaveFullAccess()) {
            die();
        }

        $id = $_GET['deleteid'];
        $Eban = new Eban();
        $Eban->RemoveEbanFromDB($id);
        die();
    }
?>
