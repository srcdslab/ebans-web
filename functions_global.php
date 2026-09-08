<?php

    include_once('steam.php');

    /* HTML-escape a value on its way into the page.
       Everything the panel renders out of the database goes through this. The
       EntWatch plugin writes `client_name`, `reason` and `admin_name` straight
       from the game server, so a player nickname is fully attacker-controlled
       and never passes through Utility::sanitizeInput(). Input-side stripping
       is not the control here; output escaping is. */
    function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* A value crossing into JavaScript. json_encode() produces the complete
       literal, quotes included, and the JSON_HEX_* flags keep it inert inside
       an inline <script>. Wrap the result in e() as well when it sits inside an
       on* attribute, because the attribute is parsed as HTML first. */
    function js($value): string {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
    }

    /* How long a login lasts.
       This used to be `time() * 30` -- a multiplication where an addition was
       meant, which put the expiry in the year 3670:

           php > echo date("Y-m-d", time() * 30);
           3670-08-10

       so the credential never expired and there was no way to age it out. */
    define('LOGIN_COOKIE_LIFETIME', 12 * 60 * 60);

    /* One place that decides how the login cookies are attributed, so
       login-process.php, logout.php and header.php cannot drift apart.

       `secure` stays on unconditionally: the panel is meant to be served over
       HTTPS, and quietly downgrading it here would hand the credential to
       anyone on the wire. See the Requirements section in README.md.

       `samesite` was absent, which left the browser default of Lax. Lax is
       still sent on top-level GET navigation -- which is exactly what the
       write endpoints are -- so it is set explicitly here and tightened when
       those endpoints move to POST. */
    function loginCookieOptions(int $expires): array {
        return [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'] ?? '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /* Clears the pre-session login cookies. Kept so that a browser holding a
       set from before this change is cleaned up on the next logout. */
    function clearLoginCookies(): void {
        foreach (['steamID', 'secret_key', 'aid'] as $name) {
            setcookie($name, '', loginCookieOptions(time() - 3600));
        }
    }

    /* Starts the session the login lives in. Must run before any output.

       The login used to be three cookies whose only secret was SECRET_KEY --
       one static constant, identical for every user, handed to every admin who
       signed in. Nothing in it proved the browser had ever completed the Steam
       OpenID flow, so anyone holding that one value could re-issue the cookie
       set for any other admin. Both remaining values are public: admin
       SteamIDs are enumerable through the panel's own Advanced Search, and
       `aid` is a small sequential integer.

       The session id is now a server-issued secret that is not derived from
       anything the client knows. */
    function startPanelSession(): void {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        /* Reject a session id the server never issued, so an attacker cannot
           fix a victim's id in advance and inherit the session they create. */
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            /* A browser-session cookie; the server enforces the real deadline
               through $_SESSION['login_time']. */
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'] ?? '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('ebans_session');
        session_start();
    }

    startPanelSession();

    /* Records a completed Steam OpenID login. */
    function establishAdminSession(string $steamID, array $adminRow): void {
        /* A fresh id for the authenticated session, so an id that existed
           before the login cannot be used after it. */
        session_regenerate_id(true);

        $_SESSION['steamid']    = $steamID;
        $_SESSION['aid']        = (int) $adminRow['aid'];
        $_SESSION['gid']        = (int) $adminRow['gid'];
        $_SESSION['user']       = $adminRow['user'];
        $_SESSION['login_time'] = time();
    }

    function destroyAdminSession(): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', loginCookieOptions(time() - 3600));
        }

        session_destroy();
    }

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

        /* Request-scoped memos. Every one of these answers a question that the
           listing page used to ask again for each row it rendered. */
        private static $adminNameCache = [];
        private static $adminRowCache = [];

        /* Resolve the admin names for a whole page in one query.
           Call this with the page's admin_steamid column before rendering;
           GetAdminNameFromSteamID() then answers from memory. */
        public static function primeAdminNames(array $steamIDs) {
            $wanted = [];
            foreach ($steamIDs as $steamID) {
                if (is_string($steamID) && str_contains($steamID, "STEAM")
                    && !array_key_exists($steamID, self::$adminNameCache)) {
                    $wanted[$steamID] = true;
                }
            }

            if (empty($wanted)) {
                return;
            }

            $ids = array_keys($wanted);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $admins = sbpp_table('admins');

            $result = dbSelect(
                $GLOBALS['SBPP'],
                "SELECT `authid`, `user` FROM `$admins` WHERE `authid` IN ($placeholders)",
                str_repeat('s', count($ids)),
                $ids
            );

            foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
                self::$adminNameCache[$row['authid']] = $row['user'];
            }
            $result->free();

            /* Whatever is still missing was removed from SourceBans. Record
               that too, so it is not looked up again row after row. */
            foreach ($ids as $id) {
                if (!array_key_exists($id, self::$adminNameCache)) {
                    self::$adminNameCache[$id] = "Admin Deleted";
                }
            }
        }

        public function GetAdminNameFromSteamID($steamID) {
            if (!is_string($steamID) || !str_contains($steamID, "STEAM")) {
                return "CONSOLE";
            }

            if (!array_key_exists($steamID, self::$adminNameCache)) {
                self::primeAdminNames([$steamID]);
            }

            return self::$adminNameCache[$steamID];
    }
        
        /* The admin row for a SteamID, but only if that SteamID may sign in.
           Returns null otherwise.

           Membership is re-read on every request rather than trusted from
           whatever the browser sent, so removing an admin from SourceBans or
           moving them out of an allowed group takes effect immediately.
           Memoised for the request: GetRowInfo() alone used to ask this four
           times per rendered row. */
        public static function lookupEligibleAdmin(string $steamID): ?array {
            if (array_key_exists($steamID, self::$adminRowCache)) {
                return self::$adminRowCache[$steamID];
            }

            $admins = sbpp_table('admins');
            $result = dbSelect(
                $GLOBALS['SBPP'],
                "SELECT `aid`, `gid`, `authid`, `user` FROM `$admins` WHERE `authid` = ? LIMIT 1",
                's',
                [$steamID]
            );
            $row = $result->fetch_assoc();
            $result->free();

            $acceptableGroups = array_merge(GID_STAFF, GID_ADMIN);
            if ($row === null || $row['gid'] == -1 || !in_array($row['gid'], $acceptableGroups)) {
                return self::$adminRowCache[$steamID] = null;
            }

            return self::$adminRowCache[$steamID] = $row;
        }

        /* The SteamID of the signed-in admin, or null.

           This is the whole credential check now: a server-side session the
           client cannot forge, plus a deadline the server owns. */
        public static function sessionSteamID(): ?string {
            $steamID = $_SESSION['steamid'] ?? null;
            $since   = $_SESSION['login_time'] ?? null;

            if (!is_string($steamID) || $steamID === '' || !is_int($since)) {
                return null;
            }

            /* The session cookie alone would last as long as the browser stays
               open, so the deadline is enforced here. */
            if ((time() - $since) > LOGIN_COOKIE_LIFETIME) {
                return null;
            }

            return $steamID;
        }

        /* Populates this object from the signed-in admin, or from an explicit
           SteamID during login, when there is no session yet. */
        public function UpdateAdminInfo(?string $steamID = null) {
            $steamID = $steamID ?? self::sessionSteamID();
            if (!is_string($steamID) || $steamID === '') {
                return false;
            }

            $row = self::lookupEligibleAdmin($steamID);
            if ($row === null) {
                return false;
            }

            $this->adminID = $row['aid'];
            $this->adminGroupID = $row['gid'];
            $this->adminSteamID = $row['authid'];
            $this->adminUser = $row['user'];

            return true;
        }

        /* Full access -- delete an eban, read the web logs, manage an eban
           somebody else issued. `GID_STAFF` grants login and management of
           one's own ebans; this is the additional tier on top of it. */
        public function DoesHaveFullAccess() {
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
            if (!IsAdminLoggedIn()) { // This should never happen, but just to be safe
                return false;
            }
        
            if (empty($reasonA)) {
                $reasonA = "No Reason";
            }

            $reason = Utility::sanitizeInput($reasonA);
            $admin = new Admin();
            $admin->UpdateAdminInfo();
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

            echo "<script>showEbanWindowInfo(2, " . js($playerName) . ", " . js($playerSteamID) . ", " . js($reason) . ");</script>";
            return true;
        }

        public function RemoveEbanFromDB($id) {
            $admin = new Admin();
            $admin->UpdateAdminInfo();
            $adminSteamID = $admin->adminSteamID;

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

            echo "<script>showEbanWindowInfo(3, " . js($playerName) . ", " . js($playerSteamID) . ", " . js($reason) . ", " . js("$length minutes") . ", " . (int) $id . ");</script>";
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

        /* Both eban counters for a whole page of rows, in one grouped query.
           These used to be two separate COUNT(*) queries per row.

           `total` is every eban the SteamID has ever had. `real` leaves out
           the ones an admin lifted early: an eban the player served out still
           counts against them, one they were let off does not. The plugin
           writes 'Expired' when it closes a row itself.

           Returns [steamid => ['total' => int, 'real' => int]]. */
        public function GetEbanCountsFor(array $steamIDs) {
            $ids = array_values(array_unique(array_filter($steamIDs, 'is_string')));
            if (empty($ids)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $ebans = eban_table('ebans');

            $result = dbSelect(
                $GLOBALS['DB'],
                "SELECT `client_steamid`,
                        COUNT(*) AS `total`,
                        SUM(`unbanned_at` IS NULL OR `unban_reason` = 'Expired') AS `real_total`
                 FROM `$ebans`
                 WHERE `client_steamid` IN ($placeholders)
                 GROUP BY `client_steamid`",
                str_repeat('s', count($ids)),
                $ids
            );

            $counts = [];
            foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
                $counts[$row['client_steamid']] = [
                    'total' => (int) $row['total'],
                    'real'  => (int) $row['real_total'],
                ];
            }
            $result->free();

            return $counts;
        }

        public function addNewEban($playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo();
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

            echo "<script>showEbanWindowInfo(0, " . js($playerName) . ", " . js($playerSteamID) . ", " . js($reason) . ", " . js("$lengthInMinutes minutes") . ");</script>";
        }        

        public function EditEban($id, $playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo();
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

            echo "<script>showEbanWindowInfo(1, " . js($playerName) . ", " . js($playerSteamID) . ", " . js($reason) . ", " . js("$lengthInMinutes minutes") . ");</script>";
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
        <p><i class='fa-solid fa-triangle-exclamation'></i> " . e($message) . "</p>
        </div>
        </div>";
        include(ROOT . 'footer.php');
        die();
    }

    function IsAdminLoggedIn(): bool {
        $steamID = Admin::sessionSteamID();

        return $steamID !== null && Admin::lookupEligibleAdmin($steamID) !== null;
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

        $href = e("ViewPlayerHistory(" . js($clientSteamID) . ", 1);");

        echo "<button onclick='$href' class='button button-light' title='View History'><i class='fa-solid fa-clock-rotate-left'></i>&nbspView History</button>";
    
        if (IsAdminLoggedIn()) {
            $admin->UpdateAdminInfo();

            if ($ebanStatus == "active") {
                if ($admin->DoesHaveFullAccess() || $adminSteamID == $admin->adminSteamID) {
                    $editFunction = e("EditFromID(" . js((string) $id) . ")");
                    echo "<button class='button button-primary' title='Edit' onclick='$editFunction'><i class='fa-regular fa-pen-to-square'></i>&nbspEdit Details</button>";
                    $unbanFunction = e("ConfirmUnban(" . (int) $id . ", " . js($clientName) . ", " . js($clientSteamID) . ");");
                    echo "<button class='button button-important' title='Unban' onclick='$unbanFunction'><i class='fas fa-undo fa-lg'></i>&nbspUnban</button>";
                }
            } else {
                if (!$Eban->IsSteamIDAlreadyBanned($clientSteamID)) {
                    $reBanFunction = e("RebanFromID(" . js((string) $id) . ");");
                    echo "<button class='button button-important' title='Reban' onclick='$reBanFunction'><i class='fas fa-redo fa-lg'></i>&nbspReban</button>";
                }
            }
        }

        if ($admin->DoesHaveFullAccess()) {
            $deleteFunction = "RemoveEbanFromDBCheck(" . (int) $id . ");";
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
        echo "<span>" . e($clientName) . "</span>";
        echo "</li>";

        $steam = new Steam();
        $clientSteamID3 = $steam->SteamID_To_SteamID3($clientSteamID);
        $clientSteamID64 = $steam->SteamID_To_SteamID64($clientSteamID);
        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam ID</span>";
        echo "<span>" . e($clientSteamID) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam3 ID</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/" . e($clientSteamID64) . "' target='_blank' rel='noopener'>" . e($clientSteamID3) . "</a></span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam Community</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/" . e($clientSteamID64) . "' target='_blank' rel='noopener'>" . e($clientSteamID64) . "</a></span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-play'></i> Invoked on</span>";
        echo "<span>" . e($startDate) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-hourglass-half'></i> Eban Duration</span>";
        echo "<span>" . e($length) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-clock'></i> Expires on</span>";
        echo "<span>" . e($endDate) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-question'></i> Reason</span>";
        echo "<span>" . e($reason) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-ban'></i> Banned by Admin</span>";
        echo "<span>" . e($adminName) . "</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fa-solid fa-circle-exclamation'></i> Eban Status</span>";
        echo "<span>" . e($status) . "</span>";
        echo "</li>";

        if ($isRemoved) {
            $date->setTimestamp($unbanned_at);
            $removedDate = $date->format(DATE_TIME_FORMAT);

            echo "<li>";
            echo "<span><i class='fas fa-play'></i> Unbanned on</span>";
            echo "<span>" . e($removedDate) . "</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-ban'></i> Unbanned By Admin</span>";
            echo "<span>" . e($adminNameRemoved) . "</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-question'></i> Unban Reason</span>";
            echo "<span>" . e($unban_reason) . "</span>";
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
