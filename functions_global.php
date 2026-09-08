<?php

    include_once('steam.php');
    class Utility {
        public static function sanitizeInput($input) {
            $replacements = array("'", '"', "\\", ";", "`", "--", "#", "=", ">", "<", "&", "%", "|", "^", "~", "(", ")");
            return str_replace($replacements, "", $input);
        }
    }

    class Admin {
        public $adminID = -1;
        public $adminGroupID = -1;
        public $adminSteamID = "";
        public $adminUser = "";
        
        public function GetAdminNameFromSteamID($steamID) {
            if (!str_contains($steamID, "STEAM")) {
                return "CONSOLE";
            }

            $admins = sbpp_table('admins');
        $sql = "SELECT * FROM `$admins` WHERE `authid`=?";
            $stmt = $GLOBALS['SBPP']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryResult = $stmt->get_result();
            $stmt->close();

            $results = $queryResult->fetch_all(MYSQLI_ASSOC);
            foreach ($results as $result) {
                return $result['user'];
            }

            return "<i>Admin Deleted</i>";
    }
        
        public function IsLoginValid($steamID, $secret_key, $bInitialVerification) {
         if (empty($steamID) || empty($secret_key) || $secret_key !== $GLOBALS['SECRET_KEY']) {
            return false;
        }

        $admins = sbpp_table('admins');
        $sql = "SELECT aid FROM `$admins` WHERE authid = ?";
        $stmt = $GLOBALS['SBPP']->prepare($sql);
        $stmt->bind_param("s", $steamID);
        $stmt->execute();
        $queryResult = $stmt->get_result();
        $stmt->close();

        // Fetch the result from the query
        $row = $queryResult->fetch_assoc();
        $sbppaid = $row['aid'];

        // Compare the cookie 'aid' with the result from the query
        if (!$bInitialVerification && $sbppaid != $_COOKIE['aid']) {
            return false;
        }

        $admins = sbpp_table('admins');
        $sql = "SELECT * FROM `$admins` WHERE `authid`=?";
        $stmt = $GLOBALS['SBPP']->prepare($sql);
        $stmt->bind_param("s", $steamID);
        $stmt->execute();
        $queryResult = $stmt->get_result();
        $stmt->close();

        if ($queryResult->num_rows <= 0) {
            return false;
        }

        $acceptableGroups = array_merge(GID_STAFF, GID_ADMIN);
        $resultsAAA = $queryResult->fetch_all(MYSQLI_ASSOC);
        foreach ($resultsAAA as $result) {
            $gid = $result['gid'];
            if (!in_array($gid, $acceptableGroups) || $gid == -1) {
                return false;
            }
        }

        return true;
    }

        public function UpdateAdminInfo($steamID) {
            $secret_key = $_COOKIE['secret_key'];
            if (!$this->IsLoginValid($steamID, $secret_key, false)) {
                return false;
            }

            $admins = sbpp_table('admins');
            $sql = "SELECT `aid`, `gid`, `authid`, `user` FROM `$admins` WHERE `authid`=?";
            $stmt = $GLOBALS['SBPP']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryResult = $stmt->get_result();

            if ($queryResult->num_rows <= 0) {
                $stmt->close();
                return false;
            }

            $result = $queryResult->fetch_assoc();
            $stmt->close();

            $this->adminID = $result['aid'];
            $this->adminGroupID = $result['gid'];
            $this->adminSteamID = $result['authid'];
            $this->adminUser = $result['user'];

            return true;
        }

        /* Full access -- delete an eban, read the web logs, manage an eban
           somebody else issued. `GID_STAFF` grants login and management of
           one's own ebans; this is the additional tier on top of it. */
        public function DoesHaveFullAccess() {
            if (!isset($_COOKIE['steamID'])) {
                return false;
            }

            return in_array($this->adminGroupID, GID_ADMIN);
        }

    }

    class Eban {
        /* EntWatch 4 keeps every eban in one table, so the status of a row is
           derived from `unbanned_at` / `expires_at` instead of from the table
           it happens to live in. Returns "active", "expired" or "removed". */
        public function GetStatus($eban) {
            if (!empty($eban['unbanned_at'])) {
                $unbanSteamID = $eban['unban_admin_steamid'];
                /* The plugin closes expired rows itself, crediting SERVER. */
                return (empty($unbanSteamID) || $unbanSteamID == "SERVER") ? "expired" : "removed";
            }

            /* The plugin only runs its expiry cleanup on map start, so a row
               can be past its expiry and still be open. */
            if (!empty($eban['expires_at']) && $eban['expires_at'] <= time()) {
                return "expired";
            }

            return "active";
        }

        public function IsEbanActive($eban) {
            return ($this->GetStatus($eban) == "active");
        }

        /* Duration is stored in minutes: 0 is permanent, -1 is session. */
        public function FormatDuration($durationInMinutes) {
            if ($durationInMinutes == 0) {
                return "Permanent";
            }

            if ($durationInMinutes <= -1) {
                return "Session";
            }

            return $this->formatLength($durationInMinutes * 60);
        }

        public function UnbanByID($id, $reasonA) {
            if (!isset($_COOKIE['steamID'])) { // This should never happen, but just to be safe
                return false;
            }
        
            if (empty($reasonA)) {
                $reasonA = "No Reason";
            }

            $reason = Utility::sanitizeInput($reasonA);
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $Eban = new Eban();
            $resultsB = $Eban->getEbanInfoFromID($id);
            $playerName = $resultsB['client_name'];
            $playerSteamID = $resultsB['client_steamid'];
            $length = $resultsB['duration_minutes'];

            /* Closing the eban is a single update now: EntWatch 4 has no
               separate table for lifted ebans. The `unbanned_at IS NULL` guard
               keeps a second unban from overwriting who lifted it first. */
            $ebans = eban_table('ebans');
            $sql = "UPDATE `$ebans` SET `unban_admin_name` = ?, `unban_admin_steamid` = ?, `unban_reason` = ?, `unbanned_at` = ? WHERE `id` = ? AND `unbanned_at` IS NULL";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $time_unban = time();
            $stmt->bind_param("sssii", $adminName, $adminSteamID, $reason, $time_unban, $id);
            $stmt->execute();
            $stmt->close();

            // Insert into web_logs statement
            $message = "Eban Removed (was $length minutes. Reason: $reason)";
            $logs = eban_table('web_logs');
            $sql = "INSERT INTO `$logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $time = time();
            $stmt->bind_param("sssssi", $message, $adminName, $adminSteamID, $playerName, $playerSteamID, $time);
            $stmt->execute();
            $stmt->close();

            echo "<script>showEbanWindowInfo(2, \"$playerName\", \"$playerSteamID\", \"$reason\");</script>";
            return true;
        }

        public function RemoveEbanFromDB($id) {
            $admin = new Admin();
            $adminSteamID = (isset($_COOKIE['steamID']) ? $_COOKIE['steamID'] : "");
            $admin->UpdateAdminInfo($adminSteamID);

            if (!IsAdminLoggedIn() || !$admin->DoesHaveFullAccess()) {
                return false;
            }

            $resultsC = $this->getEbanInfoFromID($id);
            $playerName = $resultsC['client_name'];
            $playerSteamID = $resultsC['client_steamid'];
            $length = $resultsC['duration_minutes'];
            $reason = $resultsC['reason'];

            $status = ucfirst($this->GetStatus($resultsC));

            $message = "Eban Deleted (Player Name: $playerName, Player SteamID: $playerSteamID, was $length minutes. Issued for: $reason. Eban was $status)";

            // Use prepared statement for DELETE
            $ebans = eban_table('ebans');
            $sql = "DELETE FROM `$ebans` WHERE `id` = ?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $adminName = $admin->adminUser;
            $time = time();

            // Use prepared statement for INSERT INTO web_logs
            $logs = eban_table('web_logs');
            $sql = "INSERT INTO `$logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssi", $message, $adminName, $adminSteamID, $playerName, $playerSteamID, $time);
            $stmt->execute();
            $stmt->close();

            echo "<script>showEbanWindowInfo(3, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$length minutes\", $id);</script>";
        }

        public function formatLength($seconds) {
            /* if less than one minute */
            if ($seconds == 0) {
                return "Permanent";
            }

            if ($seconds < 60) {
                return "$seconds Seconds";
            }

            /* if one minute or more */
            if ($seconds >= 60 && $seconds < 3600) {
                $minutes = ($seconds / 60);
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";
                return "$minutes $minutesPhrase";
            }

            /* If hour or more*/
            if ($seconds >= 3600 && $seconds < 86400) {
                $hours = intval(($seconds / 3600));
                $minutes = intval((($seconds / 60) % 60));
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";

                if ($minutes <= 0) {
                    return "$hours $hoursPhrase";
                }
                return "$hours $hoursPhrase, $minutes $minutesPhrase";
            }

            /* If day or more */
            if ($seconds >= 86400 && $seconds < 604800) {
                $days = intval(($seconds / 86400));
                $hours = intval((($seconds / 3600) % 24));
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";

                if ($hours <= 0) {
                    return "$days $daysPhrase";
                }
                return "$days $daysPhrase, $hours $hoursPhrase";
            }

            /* if week or more */
            if ($seconds >= 604800 && $seconds < 2592000) {
                $weeks = intval(($seconds / 604800));
                $days = intval((($seconds / 86400) % 7));
                $weeksPhrase = ($weeks > 1) ? "Weeks" : "Week";
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                
                if ($days <= 0) {
                    return "$weeks $weeksPhrase";
                }
                return "$weeks $weeksPhrase, $days $daysPhrase";
            }

            /* if month or more */
            if ($seconds >= 2592000) {
                $months = intval(($seconds / 2592000));
                $days = intval((($seconds / 86400) % 30));
                $monthsPhrase = ($months > 1) ? "Months" : "Month";
                $daysPhrase = ($days > 1) ? "Days" : "Day";

                if ($days <= 0) {
                    return "$months $monthsPhrase";
                }
                return "$months $monthsPhrase, $days $daysPhrase";
            }
        }

        public function getEbanInfoFromID($id) {
            $ebans = eban_table('ebans');
            $sql = "SELECT * FROM `$ebans` WHERE `id`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $query = $stmt->get_result();

            $result = $query->fetch_assoc();
            $query->free();
            $stmt->close();

            return $result;
        }

        public function GetEbansNumber($steamID) {
            $ebans = eban_table('ebans');
            $sql = "SELECT COUNT(*) AS `total` FROM `$ebans` WHERE `client_steamid`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryA = $stmt->get_result();
            $result = $queryA->fetch_assoc();
            $queryA->free();
            $stmt->close();

            return $result['total'];
        }

        /* Same count, minus the ebans an admin lifted early: those the player
           served out are still credited to them, the ones they were let off
           are not. The plugin writes 'Expired' when it closes a row itself. */
        public function GetRealEbansNumber($steamID) {
            $ebans = eban_table('ebans');
            $sql = "SELECT COUNT(*) AS `total` FROM `$ebans`
                    WHERE `client_steamid`=? AND (`unbanned_at` IS NULL OR `unban_reason` = 'Expired')";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryA = $stmt->get_result();
            $result = $queryA->fetch_assoc();
            $queryA->free();
            $stmt->close();

            return $result['total'];
        }

        public function addNewEban($playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;
            $adminID = $admin->adminID;

            $playerName = Utility::sanitizeInput($playerNameA);
            $reason = Utility::sanitizeInput($reasonA);
            $lengthInMinutes = ($length / 60);

            if ($length <= -1) {
                $lengthInMinutes = 30;
            } elseif ($length == 0) {
                $lengthInMinutes = 0;
            }

            /* EntWatch 4 records both ends of the eban: `expires_at` stays NULL
               for permanent and session ebans. */
            $issued_at = time();
            $expires_at = ($lengthInMinutes > 0) ? ($issued_at + ($lengthInMinutes * 60)) : null;

            if ($this->IsSteamIDAlreadyBanned($playerSteamID)) {
                die();
            }

            // Prepare and execute INSERT INTO EntWatch_Ebans
            $ebans = eban_table('ebans');
            $sql = "INSERT INTO `$ebans`
                    (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `reason`, `duration_minutes`, `issued_at`, `expires_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $GLOBALS['DB']->prepare($sql)) {
                $stmt->bind_param("sssssiii", $playerName, $playerSteamID, $adminName, $adminSteamID, $reason, $lengthInMinutes, $issued_at, $expires_at);
                if (!$stmt->execute()) {
                    error_log("Database error: " . $stmt->error);
                    die("Database error occurred.");
                }
                $stmt->close();
            } else {
                error_log("Database prepare error: " . $GLOBALS['DB']->error);
                die("Database prepare error occurred.");
            }

            // Construct message
            $message = "Eban Added (";
            if ($lengthInMinutes >= 1) {
                $message .= "$lengthInMinutes Minutes";
            } elseif ($lengthInMinutes == 0) {
                $message .= "Permanent";
            } else {
                $message .= "Session";
            }
            $message .= ")";

            $time = time();

            // Prepare and execute INSERT INTO web_logs
            $logs = eban_table('web_logs');
            $sql = "INSERT INTO `$logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`)
                    VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = $GLOBALS['DB']->prepare($sql)) {
                $stmt->bind_param("sssssi", $message, $adminName, $adminSteamID, $playerName, $playerSteamID, $time);
                if (!$stmt->execute()) {
                    error_log("Database error: " . $stmt->error);
                    die("Database error occurred.");
                }
                $stmt->close();
            } else {
                error_log("Database prepare error: " . $GLOBALS['DB']->error);
                die("Database prepare error occurred.");
            }

            echo "<script>showEbanWindowInfo(0, \"" . htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($playerSteamID, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "\", \"$lengthInMinutes minutes\");</script>";
        }        

        public function EditEban($id, $playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            // Escape single quotes by removing them
            $playerName = Utility::sanitizeInput($playerNameA);
            $reason = Utility::sanitizeInput($reasonA);
            $lengthInMinutes = ($length / 60);

            $info = $this->getEbanInfoFromID($id);

            if ($length <= -1) {
                $lengthInMinutes = 30;
            } elseif ($length == 0) {
                $lengthInMinutes = 0;
            }

            /* The eban keeps the date it was handed out; only its end moves. */
            $expires_at = ($lengthInMinutes > 0) ? ($info['issued_at'] + ($lengthInMinutes * 60)) : null;

            $time = time();
            if ($length >= 1) {
                if ($expires_at < $time) {
                    $this->UnbanByID($id, "Giving another chance");
                    echo "<script>window.location.replace('index.php?all');</script>";
                    die();
                }
            }

            // Update statement
            $ebans = eban_table('ebans');
            $sql = "UPDATE `$ebans` SET `client_name` = ?, `client_steamid` = ?, `reason` = ?, `duration_minutes` = ?, `expires_at` = ? WHERE `id` = ?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssiii", $playerName, $playerSteamID, $reason, $lengthInMinutes, $expires_at, $id);
            $stmt->execute();
            $stmt->close();

            $message = "Eban Edited (";
            if ($playerName != $info['client_name']) {
                $message .= " New Name: $playerName";
            }
            if ($playerSteamID != $info['client_steamid']) {
                $message .= " New SteamID: $playerSteamID";
            }
            if ($reason != $info['reason']) {
                $message .= " New Reason: $reason"; 
            }

            if ($lengthInMinutes != $info['duration_minutes']) {
                if ($lengthInMinutes >= 1) {
                    $message .= " New Length: $lengthInMinutes Minutes";
                } elseif ($lengthInMinutes == 0) {
                    $message .= " New Length: Permanent";
                } else {
                    $message .= " New Length: Session";
                }
            }

            $message .= " )";

            // Insert statement
            $logs = eban_table('web_logs');
            $sql = "INSERT INTO `$logs` (`message`, `admin_name`, `admin_steamid`, `client_name`, `client_steamid`, `time_stamp`) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssi", $message, $adminName, $adminSteamID, $playerName, $playerSteamID, $time);
            $stmt->execute();
            $stmt->close();

            echo "<script>showEbanWindowInfo(1, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$lengthInMinutes minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        /* Mirrors how the plugin decides a client is restricted: the eban has
           not been lifted and has either no expiry or one still ahead of us. */
        public function IsSteamIDAlreadyBanned($steamID) {
            $ebans = eban_table('ebans');
            $sql = "SELECT 1 FROM `$ebans`
                    WHERE `client_steamid`=? AND `unbanned_at` IS NULL
                      AND (`expires_at` IS NULL OR `expires_at` > ?)
                    LIMIT 1";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $now = time();
            $stmt->bind_param("si", $steamID, $now);
            $stmt->execute();
            $query = $stmt->get_result();
            $isBanned = ($query->num_rows > 0);
            $query->free();
            $stmt->close();

            return $isBanned;
        }
    }

    /* Renders a full, well-formed page for a request that is not allowed to
       continue: the error box, then the footer that closes `body_content`,
       `<body>` and `<html>` opened by header.php. */
    function renderAccessDenied($message = "You do not have access to this page.") {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> $message</p>
        </div>
        </div>";
        include(ROOT . 'footer.php');
        die();
    }

    function IsAdminLoggedIn() {
        if (!isset($_COOKIE['steamID']) || !isset($_COOKIE['secret_key'])) {
            return false;
        }

        $steamID = $_COOKIE['steamID'];
        $secret_key = $_COOKIE['secret_key'];

        $admin = new Admin();
        if ($admin->IsLoginValid($steamID, $secret_key, false)) {
            return true;
        }

        return false;
    }

    /* Maps the search modal's `m` value to a column name.
       Returns null for anything not on the list -- including the gaps at 0 and
       3, and any out-of-range value. Callers must treat null as "no search";
       indexing the old flat array with an unchecked `intval($_GET['m'])` raised
       "Undefined array key" and then built a query with an empty column name,
       which failed and took the page down with it. */
    function formatMethod(int $method): ?string {
        $methods = [
            1 => "client_steamid",
            2 => "client_name",
            4 => "admin_name",
            5 => "admin_steamid",
        ];

        return $methods[$method] ?? null;
    }

    /* The `m` value from the query string, or 0 when absent or not an integer
       (`?m[]=1` included). 0 is not a valid method, so it reads as "no search". */
    function searchMethodFromRequest(): int {
        $method = filter_input(INPUT_GET, 'm', FILTER_VALIDATE_INT);

        return is_int($method) ? $method : 0;
    }

    /* A positive page number from the query string, or 1.
       `?page=abc` used to be a fatal TypeError ("Unsupported operand types:
       string - int") and `?page=99999999999999999999` produced
       `LIMIT 2.0E+21, 20`, an SQL syntax error. */
    function currentPageFromRequest(): int {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'default' => 1],
        ]);

        return is_int($page) ? $page : 1;
    }

    /* real_escape_string() does not neutralise the LIKE metacharacters, so a
       search term was being matched as a pattern rather than as text. The value
       is bound either way -- this is about matching the right rows, not safety.
       The backslashes are doubled because MySQL parses escapes in the pattern
       itself as well as in the string literal. */
    function escapeLikeOperand(string $value): string {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /* Runs a prepared SELECT and hands back the result, so the page templates
       do not each have to repeat the prepare/bind/execute dance. */
    function dbSelect(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_result {
        $stmt = $db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }

    function GetRowInfo($id, $result2 = null) {
        $admin = new Admin();
        $Eban = new Eban();
        
        if ($id != 0) {
            $result2 = $Eban->getEbanInfoFromID($id);
        } else {
            $id = $result2['id'];
        }

        $clientName         = $result2['client_name'];
        $clientSteamID      = $result2['client_steamid'];
        $adminSteamID       = $result2['admin_steamid'];
        $reason             = $result2['reason'];
        $issued_at          = $result2['issued_at'];
        $expires_at         = $result2['expires_at'];
        $duration           = $result2['duration_minutes'];
        $unbanned_at        = $result2['unbanned_at'];
        $adminNameRemoved   = $result2['unban_admin_name'];
        $unban_reason       = $result2['unban_reason'];

        $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);

        $ebanStatus = $Eban->GetStatus($result2);
        $isRemoved = ($ebanStatus == "removed");

        $length = $Eban->FormatDuration($duration);
        $status = "Eban " . ucfirst($ebanStatus);

        echo "<div class='Eban-buttons'>";

        $href = "ViewPlayerHistory(\"$clientSteamID\", 1);";

        echo "<button onclick='$href' class='button button-light' title='View History'><i class='fa-solid fa-clock-rotate-left'></i>&nbspView History</button>";
    
        if (IsAdminLoggedIn()) {
            $admin->UpdateAdminInfo($_COOKIE['steamID']);

            if ($ebanStatus == "active") {
                if ($admin->DoesHaveFullAccess() || $adminSteamID == $admin->adminSteamID) {
                    $editFunction = "EditFromID(\"$id\")";
                    echo "<button class='button button-primary' title='Edit' onclick='$editFunction'><i class='fa-regular fa-pen-to-square'></i>&nbspEdit Details</button>";
                    $unbanFunction = "ConfirmUnban($id, \"$clientName\", \"$clientSteamID\");";
                    echo "<button class='button button-important' title='Unban' onclick='$unbanFunction'><i class='fas fa-undo fa-lg'></i>&nbspUnban</button>";
                }
            } else {
                if (!$Eban->IsSteamIDAlreadyBanned($clientSteamID)) {
                    $reBanFunction = "RebanFromID(\"$id\");";
                    echo "<button class='button button-important' title='Reban' onclick='$reBanFunction'><i class='fas fa-redo fa-lg'></i>&nbspReban</button>";
                }
            }
        }

        if ($admin->DoesHaveFullAccess()) {
            $deleteFunction = "RemoveEbanFromDBCheck($id);";
            echo "<button class='button button-important' title='Delete' onclick='$deleteFunction'><i class='fa-solid fa-trash'></i>&nbspDelete Eban</button>";
        }

        if (!IsAdminLoggedIn()) {
            $href = "Login();";
            echo "<button onclick='$href' class='button button-success' title='Sign in'>Admin? Sign in</button>";
        }

        echo "</div>";

        $date = new DateTime("now", new DateTimeZone(DATE_TIME_ZONE));
        $date->setTimestamp($issued_at);
        $startDate  = $date->format(DATE_TIME_FORMAT);

        /* Permanent and session ebans both have no expiry timestamp. */
        if ($duration == 0) {
            $endDate = "Never";
        } elseif ($duration <= -1) {
            $endDate = "Temporary";
        } else {
            $date->setTimestamp($expires_at);
            $endDate = $date->format(DATE_TIME_FORMAT);
        }

        echo "<ul class='Eban_details'>";

        echo "<li>";
        echo "<span><i class='fas fa-user'></i> Player</span>";
        echo "<span>$clientName</span>";
        echo "</li>";

        $steam = new Steam();
        $clientSteamID3 = $steam->SteamID_To_SteamID3($clientSteamID);
        $clientSteamID64 = $steam->SteamID_To_SteamID64($clientSteamID);
        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam ID</span>";
        echo "<span>$clientSteamID</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam3 ID</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/$clientSteamID64' target='_blank'>$clientSteamID3</a></span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam Community</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/$clientSteamID64' target='_blank'>$clientSteamID64</a></span>";
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

        if ($isRemoved) {
            $date->setTimestamp($unbanned_at);
            $removedDate = $date->format(DATE_TIME_FORMAT);

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
            echo "<span>$unban_reason</span>";
            echo "</li>";
        }
        
        echo "</ul>";
        
    }

    function GetEbanLengths() {
        echo "<select id='add-select' class='select add-select'>";
        echo "<optgroup label='Minutes'>";
        for ($second = 1; $second < 3600; $second++) {
            /* we want 10, 30, and 50 minutes */
            if ($second == (10*60) || $second == (30*60) || $second == (50*60)) {
                $minutes = ($second / 60);
                $minutesToSeconds = ($minutes * 60);
                if ($second == $minutesToSeconds) {
                    echo "<option value='$second'>$minutes Minutes</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Hours'>";
        for ($second = 1; $second < (3600 * 24); $second++) {
            /* we want 1, 2, 4, 8, and 16 hours */
            if ($second == (1*60*60) || $second == (2*60*60) || $second == (4*60*60) ||
                $second == (8*60*60) || $second == (16*60*60)) {
                $hours = ($second / (60 * 60));
                $hoursToSeconds = ($hours * (60 * 60));
                if ($second == $hoursToSeconds) {
                    echo "<option value='$second'>$hours Hours</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Days'>";

        for ($second = 1; $second <= (3600 * 24 * 3); $second++) {
            /* we want 1, 2, 3 days */
            if ($second == (1*60*60*24) || $second == (2*60*60*24) || $second == (3*60*60*24)) {
                $days = ($second / (60 * 60 * 24));
                $daysToSeconds = ($days * (60 * 60 * 24));
                if ($second == $daysToSeconds) {
                    echo "<option value='$second'>$days Days</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Weeks'>";

        for ($second = 1; $second <= (3600 * 24 * 7 * 3); $second++) {
            /* we want 1, 2, 3 weeks */
            if ($second == (1*60*60*24*7) || $second == (2*60*60*24*7) || $second == (3*60*60*24*7)) {
                $weeks = ($second / (60 * 60 * 24 * 7));
                $weeksToSeconds = ($weeks * (60 * 60 * 24 * 7));
                if ($second == $weeksToSeconds) {
                    echo "<option value='$second'>$weeks Weeks</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Months'>";
        for ($second = 1; $second <= (3600 * 24 * 30 * 3); $second++) {
            /* we want 1, 2, 3 months */
            if ($second == (1*60*60*24*30) || $second == (2*60*60*24*30) || $second == (3*60*60*24*30)) {
                $months = ($second / (60 * 60 * 24 * 30));
                $monthsToSeconds = ($months * (60 * 60 * 24 * 30));
                if ($second == $monthsToSeconds) {
                    echo "<option value='$second'>$months Months</option>";
                }
            }
        }

        echo "</optgroup>";

        echo "<optgroup label='Others'>";
        echo "<option value='0'>Permanent</option>";
        echo "</optgroup>";

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
