<?php

    include_once('connect.php');
    include_once('functions_global.php');

    function sanitizeString($input)
    {
        // Replace problematic characters with an empty string
        $replacements = array("'", '"', "\\", ";", "`", "--", "#", "=", ">", "<", "&", "%", "|", "^", "~", "(", ")");
        $sanitized = str_replace($replacements, "", $input);
        return htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8'); // Escape HTML entities
    }

    /* Reading a row back is a GET and stays one. Everything below this point
       changes state, so it is POST-only and carries a CSRF token. */
    if (isset($_GET['id']) && !isset($_GET['reban']) && !isset($_GET['edit'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
        showEbanInfo($id);
    }

    if (isset($_POST['oldid'])) {
        requireWriteRequest();

        if (!IsAdminLoggedIn()) {
            die();
        }

        $admin = new Admin();
        $admin->UpdateAdminInfo();

        $id = filter_input(INPUT_POST, 'oldid', FILTER_SANITIZE_NUMBER_INT);

        $Eban = new Eban();
        $info = $Eban->getEbanInfoFromID($id);
        if ($info === null) {
            die();
        }

        if (!$admin->DoesHaveFullAccess() && $info['admin_steamid'] != $admin->adminSteamID) {
            die();
        }

        /* $reason was undefined here: it was never read from the request, so
           sanitizeString() got null and every unban was logged as "No Reason"
           even though the browser prompts for one and sends it. */
        $reason = sanitizeString($_POST['reason'] ?? '');

        $Eban = new Eban();
        if (!$Eban->UnbanByID($id, $reason)) {
            die();
        }

        die();
    }

    function showEbanInfo(int $id) {
        GetRowInfo($id);
    }

    if (isset($_POST['add']) && isset($_POST['playerName'])) {
        requireWriteRequest();

        if (!IsAdminLoggedIn()) {
            die();
        }

        // Sanitize input
        $playerName = sanitizeString(filter_input(INPUT_POST, 'playerName', FILTER_SANITIZE_STRING));
        $playerSteamID = filter_input(INPUT_POST, 'playerSteamID', FILTER_SANITIZE_STRING);
        $reason = sanitizeString(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));

        $icon = "<i class='fa-solid fa-xmark'></i>&nbsp";
        if (empty($playerName)) {
            echo "<p>$icon Player name cannot be empty!</p>";
            die();
        }

        if (empty($playerSteamID)) {
            echo "<p>$icon Player SteamID cannot be empty!</p>";
            die();
        }

        if (!preg_match("/^STEAM_[0-5]:[01]:\d+$/", $playerSteamID)) {
            echo "<p>$icon Invalid SteamID Format</p>";
            die();
        }

        if (empty($reason)) {
            echo "<p>$icon Reason cannot be empty!</p>";
            die();
        }

        /* The Add path used to check nothing but IsAdminLoggedIn(): no name
           check, no bound on the duration, and no permission check for a
           permanent eban -- all of which the Edit path had. */
        $lengthInMinutes = requestedDurationMinutes($_POST['length'] ?? null);
        if ($lengthInMinutes === null) {
            echo "<p>$icon Invalid duration!</p>";
            die();
        }

        $admin = new Admin();
        $admin->UpdateAdminInfo();

        if ($lengthInMinutes === 0 && !$admin->DoesHaveFullAccess()) {
            echo "<p>$icon You do not have permission for Permanent bans!</p>";
            die();
        }

        $Eban = new Eban();
        if ($Eban->IsSteamIDAlreadyBanned($playerSteamID)) {
            echo "<p>$icon $playerSteamID is already Ebanned!</p>";
            die();
        }

        $Eban->addNewEban($playerName, $playerSteamID, $lengthInMinutes, $reason);
    }

    if (isset($_POST['edit']) && isset($_POST['playerName'])) {
        requireWriteRequest();

        if (!IsAdminLoggedIn()) {
            die();
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $playerName = sanitizeString(filter_input(INPUT_POST, 'playerName', FILTER_SANITIZE_STRING));
        $playerSteamID = filter_input(INPUT_POST, 'playerSteamID', FILTER_SANITIZE_STRING);
        $reason = sanitizeString(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));

        $icon = "<i class='fa-solid fa-xmark'></i>&nbsp";
        if (empty($playerName)) {
            echo "<p>$icon Player name cannot be empty!</p>";
            die();
        }

        if (empty($playerSteamID)) {
            echo "<p>$icon Player SteamID cannot be empty!</p>";
            die();
        }

        if (!preg_match("/^STEAM_[0-5]:[01]:\d+$/", $playerSteamID)) {
            echo "<p>$icon Invalid SteamID Format</p>";
            die();
        }

        if (empty($reason)) {
            echo "<p>$icon Reason cannot be empty!</p>";
            die();
        }

        /* FILTER_SANITIZE_NUMBER_INT returns a string, never null, whenever the
           parameter is present, so the old `$length === null` test fired only
           when `length` was absent -- and `length=0` is exactly what the form
           sends for a permanent ban. Any staff member could issue one through
           the normal UI. */
        $lengthInMinutes = requestedDurationMinutes($_POST['length'] ?? null);
        if ($lengthInMinutes === null) {
            echo "<p>$icon Invalid duration!</p>";
            die();
        }

        $admin = new Admin();
        $admin->UpdateAdminInfo();

        if ($lengthInMinutes === 0 && !$admin->DoesHaveFullAccess()) {
            echo "<p>$icon You do not have permission for Permanent bans!</p>";
            die();
        }

        $Eban = new Eban();

        $info = $Eban->getEbanInfoFromID($id);
        if ($info === null) {
            echo "<p>$icon That eban no longer exists.</p>";
            die();
        }

        if (!$admin->DoesHaveFullAccess() && $info['admin_steamid'] != $admin->adminSteamID) {
            die();
        }

        if ($playerName == $info['client_name'] && $playerSteamID == $info['client_steamid'] && $reason == $info['reason'] && $lengthInMinutes == $info['duration_minutes']) {
            echo "<p>$icon Cannot detect any changes to edit!</p>";
            die();
        }

        if (!$Eban->IsSteamIDAlreadyBanned($playerSteamID)) {
            echo "<p>$icon The edited steamid is already Eunbanned and cannot be edited from here</p>";
            die();
        }

        /* The eban keeps its original start date, so the new length is measured
           from `issued_at` and must still land in the future. */
        $expires_at = $info['issued_at'] + ($lengthInMinutes * 60);
        if ($lengthInMinutes > 0 && $expires_at < time()) {
            echo "<p>$icon Invalid Duration! Expected a duration that will last in the future but got one that has already ended.</p>";
            die();
        }

        $Eban->EditEban($id, $playerName, $playerSteamID, $lengthInMinutes, $reason);
    }

    if (isset($_POST['delete'])) {
        requireWriteRequest();

        if (!IsAdminLoggedIn()) {
            die();
        }

        $admin = new Admin();
        $admin->UpdateAdminInfo();
        if (!IsAdminLoggedIn() || !$admin->DoesHaveFullAccess()) {
            die();
        }

        $id = filter_input(INPUT_POST, 'deleteid', FILTER_SANITIZE_NUMBER_INT);
        $Eban = new Eban();
        $Eban->RemoveEbanFromDB($id);
        die();
    }
?>
