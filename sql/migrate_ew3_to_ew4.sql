-- ---------------------------------------------------------------------------
--  EntWatch 3  ->  EntWatch 4  data migration
-- ---------------------------------------------------------------------------
--
--  EntWatch 3 stored ebans in two tables and moved a row from one to the other
--  once the eban stopped being enforced:
--
--      EntWatch_Current_Eban   enforced ebans
--      EntWatch_Old_Eban       expired / lifted ebans
--
--  EntWatch 4 keeps every eban in a single table, `EntWatch_Ebans`, and marks
--  the ones that are no longer enforced with `unbanned_at`.
--
--  Column mapping
--  --------------------------------------------------------------------------
--    client_name           ->  client_name
--    client_steamid        ->  client_steamid
--    admin_name            ->  admin_name
--    admin_steamid         ->  admin_steamid
--    duration              ->  duration_minutes
--    timestamp_issued      ->  issued_at   +   expires_at    (see below)
--    reason                ->  reason
--    timestamp_unban       ->  unbanned_at
--    reason_unban          ->  unban_reason
--    admin_name_unban      ->  unban_admin_name
--    admin_steamid_unban   ->  unban_admin_steamid
--    server                ->  (dropped, EntWatch 4 has no per-server column)
--    id                    ->  (not preserved, see "Row identifiers" below)
--
--  Timestamps
--  --------------------------------------------------------------------------
--  This is the one conversion that is not a straight copy. EntWatch 3 does not
--  store the issue time of a timed eban: for `duration > 0` the plugin writes
--  `GetTime() + duration * 60` into `timestamp_issued`, so the column actually
--  holds the *expiry*. For permanent ebans (`duration = 0`) nothing is added,
--  so the column holds the real issue time.
--
--      duration > 0    issued_at  = timestamp_issued - duration * 60
--                      expires_at = timestamp_issued
--
--      duration = 0    issued_at  = timestamp_issued      (permanent)
--      duration = -1   issued_at  = timestamp_issued      (session)
--                      expires_at = NULL
--
--  `expires_at IS NULL` therefore means "permanent or session"; the two are
--  told apart by `duration_minutes` exactly as EntWatch 4 does it.
--
--  Row identifiers
--  --------------------------------------------------------------------------
--  `id` is not carried over: the two source tables have overlapping id ranges,
--  so they cannot both keep theirs. New ids are assigned in chronological
--  order. Nothing outside the eban tables references an eban id (`web_logs`
--  only stores SteamIDs), so this is safe.
--
--  Running it
--  --------------------------------------------------------------------------
--    1. Back up the database:
--         mysqldump -u USER -p DBNAME > ew3-backup.sql
--    2. Run the pre-flight checks in section 0 and read their output.
--    3. Run this script:
--         mysql -u USER -p DBNAME < sql/migrate_ew3_to_ew4.sql
--    4. Run the verification queries in section 4.
--    5. Once the panel and the plugin are both happy, drop the old tables
--       manually (section 5, deliberately left commented out).
--
--  The script is idempotent: re-running it does not duplicate rows.
--
--  To roll back, drop `EntWatch_Ebans` and restore the EntWatch 3 plugin. The
--  source tables are never modified, so nothing else has to be undone.
-- ---------------------------------------------------------------------------


-- ---------------------------------------------------------------------------
--  0. Pre-flight checks (read-only, run these first)
-- ---------------------------------------------------------------------------
--
--  0.a  How many rows are we about to move?
--
--    SELECT 'current' AS source, COUNT(*) FROM `EntWatch_Current_Eban`
--    UNION ALL
--    SELECT 'old', COUNT(*) FROM `EntWatch_Old_Eban`;
--
--  0.b  Timed ebans whose computed issue time lands before the Source engine
--       existed. A non-zero count means some rows were written with an issue
--       time instead of an expiry in `timestamp_issued`, and the conversion
--       below would push their start date into the past. Inspect them before
--       migrating.
--
--    SELECT `id`, `client_steamid`, `duration`, `timestamp_issued`,
--           FROM_UNIXTIME(`timestamp_issued` - `duration` * 60) AS computed_issued_at
--    FROM `EntWatch_Current_Eban`
--    WHERE `duration` > 0 AND `timestamp_issued` - `duration` * 60 < 1000000000
--    UNION ALL
--    SELECT `id`, `client_steamid`, `duration`, `timestamp_issued`,
--           FROM_UNIXTIME(`timestamp_issued` - `duration` * 60)
--    FROM `EntWatch_Old_Eban`
--    WHERE `duration` > 0 AND `timestamp_issued` - `duration` * 60 < 1000000000;
--
--  0.c  If the EntWatch 3 database was shared by several servers, list them.
--       EntWatch 4 has no `server` column, so every row below ends up in one
--       flat list. Use the `WHERE` clauses marked "server filter" in section 2
--       if you only want one of them.
--
--    SELECT `server`, COUNT(*) FROM `EntWatch_Current_Eban` GROUP BY `server`
--    UNION ALL
--    SELECT `server`, COUNT(*) FROM `EntWatch_Old_Eban` GROUP BY `server`;


-- ---------------------------------------------------------------------------
--  1. Target table, identical to the one EntWatch 4 creates on first start
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `EntWatch_Ebans` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_name`         VARCHAR(32)  NOT NULL,
    `client_steamid`      VARCHAR(64)  NOT NULL,
    `admin_name`          VARCHAR(32)  NOT NULL,
    `admin_steamid`       VARCHAR(64)  NOT NULL,
    `duration_minutes`    INT          NOT NULL,
    `issued_at`           INT          NOT NULL,
    `expires_at`          INT          NULL,
    `reason`              VARCHAR(64)  NULL,
    `unbanned_at`         INT          NULL,
    `unban_reason`        VARCHAR(64)  NULL,
    `unban_admin_name`    VARCHAR(32)  NULL,
    `unban_admin_steamid` VARCHAR(64)  NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_client`  (`client_steamid`),
    INDEX `idx_active`  (`unbanned_at`, `expires_at`),
    INDEX `idx_expires` (`expires_at`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
--  2. Copy the EntWatch 3 rows over
-- ---------------------------------------------------------------------------
--
--  A staging table gives us one place to sort both sources chronologically
--  before they are handed to AUTO_INCREMENT, and one place to deduplicate on
--  re-runs.

DROP TEMPORARY TABLE IF EXISTS `ew3_migration_staging`;

CREATE TEMPORARY TABLE `ew3_migration_staging` (
    `client_name`         VARCHAR(32)  NOT NULL,
    `client_steamid`      VARCHAR(64)  NOT NULL,
    `admin_name`          VARCHAR(32)  NOT NULL,
    `admin_steamid`       VARCHAR(64)  NOT NULL,
    `duration_minutes`    INT          NOT NULL,
    `issued_at`           INT          NOT NULL,
    `expires_at`          INT          NULL,
    `reason`              VARCHAR(64)  NULL,
    `unbanned_at`         INT          NULL,
    `unban_reason`        VARCHAR(64)  NULL,
    `unban_admin_name`    VARCHAR(32)  NULL,
    `unban_admin_steamid` VARCHAR(64)  NULL,
    INDEX `idx_dedup` (`client_steamid`, `issued_at`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

--  2.a  Enforced ebans. `timestamp_unban` is normally NULL here; a row that
--       does carry one was lifted by the web panel and keeps that record.

INSERT INTO `ew3_migration_staging`
    (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`,
     `duration_minutes`, `issued_at`, `expires_at`, `reason`,
     `unbanned_at`, `unban_reason`, `unban_admin_name`, `unban_admin_steamid`)
SELECT
    `client_name`,
    `client_steamid`,
    `admin_name`,
    `admin_steamid`,
    `duration`,
    CASE WHEN `duration` > 0
         THEN GREATEST(`timestamp_issued` - `duration` * 60, 0)
         ELSE GREATEST(`timestamp_issued`, 0)
    END,
    CASE WHEN `duration` > 0 THEN `timestamp_issued` ELSE NULL END,
    NULLIF(`reason`, ''),
    NULLIF(`timestamp_unban`, 0),
    NULLIF(`reason_unban`, ''),
    NULLIF(`admin_name_unban`, ''),
    NULLIF(`admin_steamid_unban`, '')
FROM `EntWatch_Current_Eban`;
--  server filter: append   WHERE `server` = 'YOUR_SERVER_NAME'

--  2.b  Ebans that were already over. Every row here is inactive, so it must
--       end up with a non-NULL `unbanned_at`: EntWatch 4 reads a row with no
--       `unbanned_at` and no `expires_at` as a live permanent eban, and a
--       permanent eban that had been lifted under EntWatch 3 would otherwise
--       come back to life. Rows missing the unban bookkeeping (older plugin
--       builds did not always write it) are closed at their expiry, or at
--       their issue time when there is no expiry, and attributed to the
--       server the way the EntWatch 4 expiry cleanup does it.

INSERT INTO `ew3_migration_staging`
    (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`,
     `duration_minutes`, `issued_at`, `expires_at`, `reason`,
     `unbanned_at`, `unban_reason`, `unban_admin_name`, `unban_admin_steamid`)
SELECT
    `client_name`,
    `client_steamid`,
    `admin_name`,
    `admin_steamid`,
    `duration`,
    CASE WHEN `duration` > 0
         THEN GREATEST(`timestamp_issued` - `duration` * 60, 0)
         ELSE GREATEST(`timestamp_issued`, 0)
    END,
    CASE WHEN `duration` > 0 THEN `timestamp_issued` ELSE NULL END,
    NULLIF(`reason`, ''),
    COALESCE(NULLIF(`timestamp_unban`, 0), GREATEST(`timestamp_issued`, 0)),
    COALESCE(NULLIF(`reason_unban`, ''), 'Expired'),
    COALESCE(NULLIF(`admin_name_unban`, ''), 'Console'),
    COALESCE(NULLIF(`admin_steamid_unban`, ''), 'SERVER')
FROM `EntWatch_Old_Eban`;
--  server filter: append   WHERE `server` = 'YOUR_SERVER_NAME'

--  2.c  Move the staged rows in, oldest first, skipping anything a previous
--       run already inserted. `client_steamid` + `issued_at` identifies an
--       eban: EntWatch never issues two ebans to one SteamID in the same
--       second.

INSERT INTO `EntWatch_Ebans`
    (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`,
     `duration_minutes`, `issued_at`, `expires_at`, `reason`,
     `unbanned_at`, `unban_reason`, `unban_admin_name`, `unban_admin_steamid`)
SELECT
    s.`client_name`, s.`client_steamid`, s.`admin_name`, s.`admin_steamid`,
    s.`duration_minutes`, s.`issued_at`, s.`expires_at`, s.`reason`,
    s.`unbanned_at`, s.`unban_reason`, s.`unban_admin_name`, s.`unban_admin_steamid`
FROM `ew3_migration_staging` s
WHERE NOT EXISTS (
    SELECT 1 FROM `EntWatch_Ebans` e
    WHERE e.`client_steamid` = s.`client_steamid`
      AND e.`issued_at`      = s.`issued_at`
)
ORDER BY s.`issued_at` ASC;

DROP TEMPORARY TABLE `ew3_migration_staging`;


-- ---------------------------------------------------------------------------
--  3. Web panel table
-- ---------------------------------------------------------------------------
--
--  `web_logs` belongs to the panel, not to the plugin, and its layout is
--  unchanged. It is created here only so that a fresh install has it.

CREATE TABLE IF NOT EXISTS `web_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message`        VARCHAR(512) NOT NULL,
    `admin_name`     VARCHAR(32)  NOT NULL,
    `admin_steamid`  VARCHAR(64)  NOT NULL,
    `client_name`    VARCHAR(32)  NOT NULL,
    `client_steamid` VARCHAR(64)  NOT NULL,
    `time_stamp`     INT          NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_client` (`client_steamid`),
    INDEX `idx_time`   (`time_stamp`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
--  4. Verification (read-only, run these after the migration)
-- ---------------------------------------------------------------------------
--
--  4.a  Row counts must add up.
--
--    SELECT
--      (SELECT COUNT(*) FROM `EntWatch_Current_Eban`)
--    + (SELECT COUNT(*) FROM `EntWatch_Old_Eban`)   AS ew3_rows,
--      (SELECT COUNT(*) FROM `EntWatch_Ebans`)      AS ew4_rows;
--
--  4.b  Enforced ebans before and after. The two numbers should match, give or
--       take the ebans that expired between the pre-flight check and now.
--
--    SELECT COUNT(*) AS ew3_active FROM `EntWatch_Current_Eban`
--    WHERE (`admin_steamid_unban` IS NULL OR `admin_steamid_unban` = '')
--      AND (`duration` = 0 OR `timestamp_issued` > UNIX_TIMESTAMP());
--
--    SELECT COUNT(*) AS ew4_active FROM `EntWatch_Ebans`
--    WHERE `unbanned_at` IS NULL
--      AND (`expires_at` IS NULL OR `expires_at` > UNIX_TIMESTAMP());
--
--  4.c  Nothing may be internally inconsistent.
--
--    SELECT COUNT(*) AS bad_rows FROM `EntWatch_Ebans`
--    WHERE `issued_at` <= 0
--       OR (`expires_at` IS NOT NULL AND `expires_at` <= `issued_at`)
--       OR (`duration_minutes` > 0 AND `expires_at` IS NULL)
--       OR (`duration_minutes` <= 0 AND `expires_at` IS NOT NULL);
--
--  4.d  Eyeball the ten most recent ebans.
--
--    SELECT `id`, `client_name`, `client_steamid`, `duration_minutes`,
--           FROM_UNIXTIME(`issued_at`)   AS issued,
--           FROM_UNIXTIME(`expires_at`)  AS expires,
--           FROM_UNIXTIME(`unbanned_at`) AS unbanned,
--           `unban_reason`
--    FROM `EntWatch_Ebans` ORDER BY `issued_at` DESC LIMIT 10;


-- ---------------------------------------------------------------------------
--  5. Cleanup -- only once the panel and the plugin have both been verified
-- ---------------------------------------------------------------------------
--
--  Deliberately left commented out. Keep the EntWatch 3 tables around for as
--  long as you might still want to roll back.
--
--    DROP TABLE `EntWatch_Current_Eban`;
--    DROP TABLE `EntWatch_Old_Eban`;
