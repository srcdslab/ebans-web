<?php
    include_once('Steam.php');
    class Admin {
        public $adminID = -1;
        public $adminGroupID = -1;
        public $adminSteamID = "";
        public $adminUser = "";
        public $adminPassword = "";

        public function GetAdminIDFromName($name) {
            $query = "SELECT `aid` FROM `sb_admins` WHERE `user` LIKE '%$name%'";
            $queryHndl = $GLOBALS['SBPP']->query($query);
            $resultsD = $queryHndl->fetch_all(MYSQLI_ASSOC);
            foreach($resultsD as $result) {
                $queryHndl->free();
                return $result['aid'];
            }

            $queryHndl->free();
            return -1;
        }

        public function GetAdminNameFromID($id) {
            $query = "SELECT `user` FROM `sb_admins` WHERE `aid`=$id";
            $queryHndl = $GLOBALS['SBPP']->query($query);
            $resultsD = $queryHndl->fetch_all(MYSQLI_ASSOC);
            foreach($resultsD as $result) {
                $queryHndl->free();
                return $result['user'];
            }

            $queryHndl->free();
            return "Deleted Admin";
        }
        
        public function IsLoginValid($steamID) {
            if($steamID == "") {
                return false;
            }

            $sql = "SELECT * FROM `sb_admins` WHERE `authid`='$steamID'";
            $query = $GLOBALS['SBPP']->query($sql);
            $resultsAAA = $query->fetch_all(MYSQLI_ASSOC);
            if($query->num_rows <= 0) {
                $query->free();
                return false;
            } else {
                $query->free();

                $groups = array(0, 1, 3, 4, 5);
                foreach($resultsAAA as $result) {
                    $gid = $result['gid'];
                    if(!in_array($gid, $groups)) {
                        return false;
                    }
                }

                return true;
            }
        }

        public function UpdateAdminInfo($steamID) {
            if(!$this->IsLoginValid($steamID)) {
                return false;
            }

            $sql = "SELECT * FROM `sb_admins` WHERE `authid`='$steamID'";
            $query = $GLOBALS['SBPP']->query($sql);
            $results = $query->fetch_all(MYSQLI_ASSOC);
            $query->free();

            foreach($results as $result) {
                $this->adminID          = $result['aid'];
                $this->adminGroupID     = $result['gid'];
                $this->adminSteamID     = $result['authid'];
                $this->adminUser        = $result['user'];
                return true;
            }
        }

        public function GetAdminNameFromSteamID($steamID) {
            $sql = "SELECT * FROM `sb_admins` WHERE `authid`='$steamID'";
            $query = $GLOBALS['SBPP']->query($sql);
            $results = $query->fetch_all(MYSQLI_ASSOC);
            $query->free();

            foreach($results as $result) {
                return $result['user'];
            }

            return "Deleted Admin";
        }

        public function DoesHaveFullAccess() {
            if(!isset($_COOKIE['steamID'])) {
                return false;
            }

            // acceptatable group ids
            $groups = array(1, 3, 4);
            if(in_array($this->adminGroupID, $groups)) {
                return true;
            }

            return false;
        }

    }

    class Eban {
        public function UnbanByID($id, $reasonA) {
            if(!isset($_COOKIE['steamID'])) { // this should never happen, but just to be safe
                return false;
            }
            
            if(empty($reasonA)) {
                $reasonA = "No Reason";
            }

            $reason = str_replace("'", "", $reasonA);
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $Eban = new Eban();
            $resultsB = $Eban->getEbanInfoFromID($id);
            $playerName = $resultsB['client_name'];
            $playerSteamID = $resultsB['client_steamid'];

            $length = $resultsB['duration'];

            $time_unban = time();
            $GLOBALS['DB']->query("UPDATE `EntWatch_Current_Eban` SET `admin_name_unban`='$adminName', `admin_steamid_unban`='$adminSteamID', `reason_unban`='$reason', `timestamp_unban`=$time_unban WHERE `id`=$id");

            $GLOBALS['DB']->query("INSERT INTO `EntWatch_Old_Eban` (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `server`, `duration`, `timestamp_issued`, `reason`, `reason_unban`, `admin_name_unban`, `admin_steamid_unban`, `timestamp_unban`)
                        SELECT `client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `server`, `duration`, `timestamp_issued`, `reason`, `reason_unban`, `admin_name_unban`, `admin_steamid_unban`, `timestamp_unban` FROM `EntWatch_Current_Eban` WHERE `id`=$id");

            GetRowInfo($id);
            $GLOBALS['DB']->query("DELETE FROM `EntWatch_Current_Eban` WHERE `id`=$id");

            $message = "Eban Removed (was $length minutes. Reason: $reason)";

            $time = time();

            $sql = "INSERT INTO `web_logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)";
            $sql .= "VALUES ('$message', '$adminName', '$adminSteamID', '$playerName', '$playerSteamID', $time)";
            $GLOBALS['DB']->query($sql);

            echo "<script>showEbanWindowInfo(2, \"$playerName\", \"$playerSteamID\", \"$reason\");</script>";
            return true;
        }

        public function RemoveEbanFromDB($id) {
            $admin = new Admin();
            $adminSteamID = (isset($_COOKIE['steamID']) ? $_COOKIE['steamID'] : "");
            $admin->UpdateAdminInfo($adminSteamID);
            if(!IsAdminLoggedIn() || !$admin->DoesHaveFullAccess()) {
                return false;
            }

            $adminID = $admin->adminID;
            $resultsC = $this->getEbanInfoFromID($id);
            $playerName = $resultsC['client_name'];
            $playerSteamID = $resultsC['client_stemaid'];
            $length = $resultsC['duration'];
            $reason = $resultsC['reason'];
            $isExpired = ($resultsC['admin_steamid_unban'] == "SERVER" && $resultsC['timestamp_issued'] < time()) ? true : false;
            $isRemoved = ($resultsC['admin_steamid_unban'] != "" && $resultsC['admin_steamid_unban'] != "SERVER") ? true : false;

            $status = "Active";
            if($isExpired && !$isRemoved) {
                $status = "Expired";
            }

            if($isRemoved) {
                $status = "Removed";
            }

            $message = "Eban Deleted (Player Name: $playerName, Player SteamID: $playerSteamID, was $length minutes. Issued for: $reason. Eban was $status)";
            $dbTable = (!$isExpired && !$isRemoved) ? "EntWatch_Current_Eban" : "EntWatch_Old_Eban";
            $GLOBALS['DB']->query("DELETE FROM `$dbTable` WHERE `id`=$id");

            $adminName = $admin->adminUser;

            $time = time();

            $sql = "INSERT INTO `web_logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)";
            $sql .= "VALUES ('$message', '$adminName', '$adminSteamID', '$playerName', '$playerSteamID', $time)";
            $GLOBALS['DB']->query($sql);

            echo "<script>showEbanWindowInfo(3, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$length minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function formatLength($seconds) {
            /* if less than one minute */
            if($seconds == 0) {
                return "Permanent";
            }

            if($seconds < 60) {
                return "$seconds Seconds";
            }

            /* if one minute or more */
            if($seconds >= 60 && $seconds < 3600) {
                $minutes = ($seconds / 60);
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";
                return "$minutes $minutesPhrase";
            }

            /* If hour or more*/
            if($seconds >= 3600 && $seconds < 86400) {
                $hours = intval(($seconds / 3600));
                $minutes = intval((($seconds / 60) % 60));
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";

                if($minutes <= 0) {
                    return "$hours $hoursPhrase";
                }
                return "$hours $hoursPhrase, $minutes $minutesPhrase";
            }

            /* If day or more */
            if($seconds >= 86400 && $seconds < 604800) {
                $days = intval(($seconds / 86400));
                $hours = intval((($seconds / 3600) % 24));
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";

                if($hours <= 0) {
                    return "$days $daysPhrase";
                }
                return "$days $daysPhrase, $hours $hoursPhrase";
            }

            /* if week or more */
            if($seconds >= 604800 && $seconds < 2592000) {
                $weeks = intval(($seconds / 604800));
                $days = intval((($seconds / 86400) % 7));
                $weeksPhrase = ($weeks > 1) ? "Weeks" : "Week";
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                
                if($days <= 0) {
                    return "$weeks $weeksPhrase";
                }
                return "$weeks $weeksPhrase, $days $daysPhrase";
            }

            /* if month or more */
            if($seconds >= 2592000) {
                $months = intval(($seconds / 2592000));
                $days = intval((($seconds / 86400) % 30));
                $monthsPhrase = ($months > 1) ? "Months" : "Month";
                $daysPhrase = ($days > 1) ? "Days" : "Day";

                if($days <= 0) {
                    return "$months $monthsPhrase";
                }
                return "$months $monthsPhrase, $days $daysPhrase";
            }
        }

        public function getEbanInfoFromID($id) {
            $sql = "SELECT * FROM `EntWatch_Current_Eban` WHERE `id`='$id' UNION ALL SELECT * FROM `EntWatch_Old_Eban` WHERE `id`='$id'";
            $query = $GLOBALS['DB']->query($sql);

            $results = $query->fetch_all(MYSQLI_ASSOC);
            $query->free();

            foreach($results as $result) {
                return $result;
            }
        }

        public function GetEbansNumber($steamID) {
            $search = $steamID;
            $searchMethod = "client_steamid";
            
            $queryA = $GLOBALS['DB']->query("SELECT * FROM `EntWatch_Current_Eban` WHERE `$searchMethod`='$search' UNION ALL SELECT * FROM `EntWatch_Old_Eban` WHERE `$searchMethod`='$search'");
            $rows = $queryA->num_rows;
            $queryA->free();
            return $rows;
        }

        public function addNewEban($playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;
            $adminID = $admin->adminID;

            $playerName = str_replace("'", "", $playerNameA);
            $reason = str_replace("'", "", $reasonA);
            $lengthInMinutes = ($length / 60);

            if($length <= -1) {
                $lengthInMinutes = 30;
            } else if($length == 0) {
                $lengthInMinutes = 0;
            }

            $timestamp_issued = (time() + ($lengthInMinutes * 60));

            if($this->IsSteamIDAlreadyBanned($playerSteamID)) {
                die();
            }

            $serverName = $GLOBALS["SERVER_NAME"];

            $sql = "INSERT INTO `EntWatch_Current_Eban` (";
			$sql .=	"`client_name`, `client_steamid`,";
			$sql .=	"`admin_name`, `admin_steamid`, `reason`,";
			$sql .=	"`duration`, `timestamp_issued`, `server`)";
			$sql .= "VALUES (";
            $sql .= "'$playerName', '$playerSteamID', '$adminName', '$adminSteamID',";
            $sql .= "'$reason', $lengthInMinutes, $timestamp_issued, '$serverName')";

            $GLOBALS['DB']->query($sql);

            $message = "Eban Added (";
            if($lengthInMinutes >= 1) {
                $message .= "$lengthInMinutes Minutes";
            } else if($lengthInMinutes == 0) {
                $message .= "Permanent";
            } else {
                $message .= "Session";
            }

            $message .= ")";

            $ip = $GLOBALS['ip'];

            $time = time();
            
            $sql = "INSERT INTO `web_logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)";
            $sql .= "VALUES ('$message', '$adminName', '$adminSteamID', '$playerName', '$playerSteamID', $time)";

            $GLOBALS['DB']->query($sql);

            echo "<script>showEbanWindowInfo(0, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$lengthInMinutes minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function EditEban($id, $playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $playerName = str_replace("'", "", $playerNameA);
            $reason = str_replace("'", "", $reasonA);
            $lengthInMinutes = ($length / 60);
            
            $info = $this->getEbanInfoFromID($id);

            $timestamp_issued = (($info['timestamp_issued'] - ($info['duration'] * 60)) + $length);
            if($length <= -1) {
                $lengthInMinutes = 30;
            } else if($length == 0) {
                $lengthInMinutes = 0;
            }

            $time = time();
            if($length >= 1) {
                if($timestamp_issued < $time) {
                    $this->UnbanByID($id, "Giving another chance");
                    echo "<script>window.location.replace('index.php?all');</script>";
                    die();
                }
            }
 
            $sql = "UPDATE `EntWatch_Current_Eban` SET `client_name`='$playerName', `client_steamid`='$playerSteamID', `reason`='$reason', `duration`=$lengthInMinutes, `timestamp_issued`=$timestamp_issued WHERE `id`=$id";
            $GLOBALS['DB']->query($sql);

            $message = "Eban Edited (";
            if($playerName != $info['client_name']) {
                $message .= " New Name: $playerName";
            }
            if($playerSteamID != $info['client_steamid']) {
                $message .= " New SteamID: $playerSteamID";
            }
            if($reason != $info['reason']) {
                $message .= " New Reason: $reason"; 
            }

            if($lengthInMinutes != $info['duration']) {
                if($lengthInMinutes >= 1) {
                    $message .= " New Length: $lengthInMinutes Minutes";
                } else if($lengthInMinutes == 0) {
                    $message .= " New Length: Permanent";
                } else {
                    $message .= " New Length: Session";
                }
            }

            $message .= " )";

            $sql = "INSERT INTO `web_logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)";
            $sql .= "VALUES ('$message', '$adminName', '$adminSteamID', '$playerName', '$playerSteamID', $time)";

            $GLOBALS['DB']->query($sql);

            echo "<script>showEbanWindowInfo(1, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$lengthInMinutes minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function IsSteamIDAlreadyBanned($steamID) {
            $queryB = $GLOBALS['DB']->query("SELECT * FROM `EntWatch_Current_Eban` WHERE `client_steamid`='$steamID'");
            if($queryB->num_rows >= 1) {
                return true;
            }

            return false;
        }
    }

    function IsAdminLoggedIn() {
        if(!isset($_COOKIE['steamID'])) {
            return false;
        }

        $steamID = $_COOKIE['steamID'];

        $admin = new Admin();
        if($admin->IsLoginValid($steamID)) {
            return true;
        }

        return false;
    }

    function formatMethod(int $method) {
        if($method <= 0) {
            return "";
        }

        switch($method) {
            case 1: {
                return "client_steamid";
            }

            case 2: {
                return "client_name";
            }

            case 4: {
                return "admin_name";
            }

            default: {
                return "admin_steamid";
            }
        }
    }

    function GetRowInfo($id, $result2 = null) {
        $admin = new Admin();
        $Eban = new Eban();
        
        if($id != 0) {
            $result2 = $Eban->getEbanInfoFromID($id);
        } else {
            $id = $result2['id'];
        }

        $clientName         = $result2['client_name'];
        $clientSteamID      = $result2['client_steamid'];
        $adminSteamID       = $result2['admin_steamid'];
        $reason             = $result2['reason'];
        $timestamp_issued   = $result2['timestamp_issued'];
        $duration           = $result2['duration'];
        $timestamp_unban     = $result2['timestamp_unban'];
        $adminNameRemoved   = $result2['admin_name_unban'];
        $adminSteamIDRemoved = $result2['admin_steamid_unban'];
        $timestamp_unban = $result2['timestamp_unban'];
        $reason_unban     = $result2['reason_unban'];

        $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);

        $isExpired = ($adminSteamIDRemoved == "SERVER" && ($timestamp_issued) < time()) ? true : false;
        $isRemoved = ($adminSteamIDRemoved != "" && $adminSteamIDRemoved != "SERVER") ? true : false;

        $length = $Eban->formatLength($duration * 60);
        if($duration == 0) {
            $length = "Permanent";
        } else if($duration <= -1) {
            $length = "Session";
        }

        $status = "Eban Active";
        if($isExpired && !$isRemoved) {
            $status = "Eban Expired";
        }

        if($isRemoved) {
            $status = "Eban Removed";
        }

        echo "<div class='Eban-buttons'>";

        $href = "ViewPlayerHistory(\"$clientSteamID\", 1);";

        echo "<button onclick='$href' class='button button-light' title='View History'><i class='fa-solid fa-clock-rotate-left'></i>&nbspView History</button>";
    
        if(IsAdminLoggedIn()) {
            $admin->UpdateAdminInfo($_COOKIE['steamID']);

            if(($timestamp_issued < 1 && $isRemoved == false && $isExpired == false) || ($timestamp_issued >= 1 && time() < $timestamp_issued && $isRemoved == false && $isExpired == false)) {
            
                if($admin->DoesHaveFullAccess() || $adminSteamID == $admin->adminSteamID) {
					$editFunction = "EditFromID(\"$id\")";
                    echo "<button class='button button-primary' title='Edit' onclick='$editFunction'><i class='fa-regular fa-pen-to-square'></i>&nbspEdit Details</button>";
                    $unbanFunction = "ConfirmUnban($id, \"$clientName\", \"$clientSteamID\");";
                    echo "<button class='button button-important' title='Unban' onclick='$unbanFunction'><i class='fas fa-undo fa-lg'></i>&nbspUnban</button>";
                }
            } else {
                if(!$Eban->IsSteamIDAlreadyBanned($clientSteamID)) {
                    $reBanFunction = "RebanFromID(\"$id\");";
                    echo "<button class='button button-important' title='Reban' onclick='$reBanFunction'><i class='fas fa-redo fa-lg'></i>&nbspReban</button>";
                }
            }
        }

        if($admin->DoesHaveFullAccess()) {
            $deleteFunction = "RemoveEbanFromDBCheck($id);";
            echo "<button class='button button-important' title='Delete' onclick='$deleteFunction'><i class='fa-solid fa-trash'></i>&nbspDelete Eban</button>";
        }

        if(!IsAdminLoggedIn()) {
            $href = "Login();";
            echo "<button onclick='$href' class='button button-success' title='Sign in'>Admin? Sign in</button>";
        }

        echo "</div>";

        $date = new DateTime("now", new DateTimeZone("GMT+1"));
        $date->setTimestamp(($timestamp_issued - ($duration * 60)));
        $startDate  = $date->format("Y-m-d h:i:s");

        $date->setTimestamp($timestamp_issued);
        $endDate    = $date->format("Y-m-d h:i:s");

        echo "<ul class='Eban_details'>";

        echo "<li>";
        echo "<span><i class='fas fa-user'></i> Player</span>";
        echo "<span>$clientName</span>";
        echo "</li>";

        $steam = new Steam();
        $clientSteamID64 = $steam->SteamID_To_SteamID64($clientSteamID);
        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam ID</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/$clientSteamID64' target='_blank'>$clientSteamID</a></span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-play'></i> Invoked on</span>";
        echo "<span>$startDate</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-hourglass-half'></i> Eban Duration</span>";
        echo "<span>$length</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-clock'></i> Expires on</span>";
        echo "<span>$endDate</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-question'></i> Reason</span>";
        echo "<span>$reason</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-ban'></i> Banned by Admin</span>";
        echo "<span>$adminName</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fa-solid fa-circle-exclamation'></i> Eban Status</span>";
        echo "<span>$status</span>";
        echo "</li>";

        if($isRemoved) {
            $date->setTimestamp($timestamp_unban);
            $removedDate = $date->format("Y-m-d h:i:s");

            echo "<li>";
            echo "<span><i class='fas fa-play'></i> Unbanned on</span>";
            echo "<span>$removedDate</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-ban'></i> Unbanned By Admin</span>";
            echo "<span>$adminNameRemoved</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-question'></i> Unban Reason</span>";
            echo "<span>$reason_unban</span>";
            echo "</li>";
        }
        
        echo "</ul>";
        
    }

    function GetEbanLengths() {
        echo "<select id='add-select' class='select add-select'>";
        echo "<optgroup label='Minutes'>";
        for($second = 1; $second < 3600; $second++) {
            /* we want 10, 30, and 50 minutes */
            if($second == (10*60) || $second == (30*60) || $second == (50*60)) {
                $minutes = ($second / 60);
                $minutesToSeconds = ($minutes * 60);
                if($second == $minutesToSeconds) { //
                    echo "<option value='$second'>$minutes Minutes</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Hours'>";
        for($second = 1; $second < (3600 * 24); $second++) {
            /* we want 1, 2, 4, 8, and 16 hours */
            if($second == (1*60*60) || $second == (2*60*60) || $second == (4*60*60) ||
                $second == (8*60*60) || $second == (16*60*60)) {
                $hours = ($second / (60 * 60));
                $hoursToSeconds = ($hours * (60 * 60));
                if($second == $hoursToSeconds) { //
                    echo "<option value='$second'>$hours Hours</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Days'>";

        for($second = 1; $second <= (3600 * 24 * 3); $second++) {
            /* we want 1, 2, 3 days */
            if($second == (1*60*60*24) || $second == (2*60*60*24) || $second == (3*60*60*24)) {
                $days = ($second / (60 * 60 * 24));
                $daysToSeconds = ($days * (60 * 60 * 24));
                if($second == $daysToSeconds) { //
                    echo "<option value='$second'>$days Days</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Weeks'>";
        
        for($second = 1; $second <= (3600 * 24 * 7 * 3); $second++) {
            /* we want 1, 2, 3 weeks */
            if($second == (1*60*60*24*7) || $second == (2*60*60*24*7) || $second == (3*60*60*24*7)) {
                $weeks = ($second / (60 * 60 * 24 * 7));
                $weeksToSeconds = ($weeks * (60 * 60 * 24 * 7));
                if($second == $weeksToSeconds) { //
                    echo "<option value='$second'>$weeks Weeks</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Months'>";
        for($second = 1; $second <= (3600 * 24 * 30 * 3); $second++) {
            /* we want 1, 2, 3 months */
            if($second == (1*60*60*24*30) || $second == (2*60*60*24*30) || $second == (3*60*60*24*30)) {
                $months = ($second / (60 * 60 * 24 * 30));
                $monthsToSeconds = ($months * (60 * 60 * 24 * 30));
                if($second == $monthsToSeconds) { //
                    echo "<option value='$second'>$months Months</option>";
                }
            }
        }
        
        echo "</optgroup>";
        $admin = new Admin();
        $admin->UpdateAdminInfo($GLOBALS['steamID']);
        if($admin->DoesHaveFullAccess()) {
            echo "<optgroup label='Others'>";
            echo "<option value='0'>Permanent</option>";
            echo "</optgroup>";
        }

        echo "</select>";
    }

    function GetEbanLengthTypes() {
        echo "<select id='edit-select' class='select edit-select'>";
        echo "<option value='2' selected>Minutes</option>";
        echo "<option value='3'>Hours</option>";
        echo "<option value='4'>Days</option>";
        echo "<option value='5'>Weeks</option>";
        echo "<option value='6'>Months</option>";
        echo "</select>";
    }
?>
