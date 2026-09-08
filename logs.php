<?php

    include('header.php');

    if (!IsAdminLoggedIn()) {
        renderAccessDenied();
    }

    $admin = new Admin();
    $admin->UpdateAdminInfo($_COOKIE['steamID']);
    if (!$admin->DoesHaveFullAccess()) {
        renderAccessDenied();
    }

    $resultsPerPage = 20;
    $currentPage = currentPageFromRequest();

    $logs = eban_table('web_logs');

    /* The search value is bound; the only part of the clause that varies is
       the column name, which comes from formatMethod()'s allowlist.

       This used to read `WHERE `$method`=$input` with $input taken raw from
       the query string -- unquoted, unescaped and unbound. Besides being
       injectable, it meant the feature never worked: `WHERE client_name=Bob`
       asks MySQL for a column named Bob. Matching now uses LIKE, the same way
       the eban list does, so the shared search modal behaves the same on both
       pages. */
    $where = '';
    $types = '';
    $params = array();

    $method = formatMethod(searchMethodFromRequest());
    if (isset($_GET['s']) && is_string($_GET['s']) && $method !== null) {
        $where = " WHERE `$method` LIKE ?";
        $types = 's';
        $params[] = '%' . escapeLikeOperand($_GET['s']) . '%';
    }

    /* COUNT(*), not SELECT * plus num_rows. */
    $countQuery = dbSelect($GLOBALS['DB'], "SELECT COUNT(*) AS `total` FROM `$logs`$where", $types, $params);
    $resultsCount = (int) $countQuery->fetch_assoc()['total'];
    $totalPages = (int) ceil($resultsCount / $resultsPerPage);

    $countQuery->free();
    if ($totalPages != 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    /* After the clamp, so a `?page=` past the end lands on the last page
       instead of an empty one. */
    $resultsStart = ($currentPage - 1) * $resultsPerPage;

    echo "<script>setActive(4); setModalSearch(\"web\");</script>";
?>

    <?php
    $query = dbSelect(
        $GLOBALS['DB'],
        "SELECT * FROM `$logs`$where ORDER BY `time_stamp` DESC LIMIT ?, ?",
        $types . 'ii',
        array_merge($params, [$resultsStart, $resultsPerPage])
    );
    $results1 = $query->fetch_all(MYSQLI_ASSOC);
    $resultsRealCount = $query->num_rows;
    $query->free();

    /* One lookup for the page instead of one per row. */
    Admin::primeAdminNames(array_column($results1, 'admin_steamid'));

    $url = e($_SERVER['REQUEST_URI']);
    if (str_contains($url, '&page')) {
        $url = substr($url, 0, strpos($url, '&page'));
    }
    ?>
    <div class="container">
        <div class="container-header">
            <h1><i class="fa-regular fa-hard-drive"></i>Web Logs</h1>
            </div>
            <div class="breadcrumb">
<i class="fas fa-angle-right"></i> <a href="index.php?all">Home</a>
<i class="fas fa-angle-right"></i> <a href="logs.php?web">Web Logs</a>
</div>
        <div class="container-search">
            <div class="search-button search-modal-btn-open" id="search-button" data-page="web">
                <p><strong>Advanced Search (Click)</strong></p>
            </div>
        </div>
        <div class="container-box1">
            <div class="order1">
                <i>&nbsp Total Logs: <?php echo $resultsCount; ?></i>
            </div>
            <div class="order2">
                <?php
                    $resultsEnd = $resultsStart + $resultsRealCount;
                ?>
                <p>displaying <?php echo "$resultsStart - $resultsEnd"; ?> of <?php echo $resultsCount; ?> results |
                <?php
                    $nextPage = $currentPage + 1;
                    $previousPage = $currentPage - 1;

                    if ($previousPage > 0) {
                        $href = $url . "&page=$previousPage";
                        echo "<a href='$href'><i class='fa fa-arrow-circle-left'></i> previous</a> |";
                    }

                    if ($nextPage > 0 && $nextPage <= $totalPages) {
                        $href = $url . "&page=$nextPage";
                        echo "&nbsp;<a href='$href'>next <i class='fa fa-arrow-circle-right'></i></a>";
                    }

                    echo "&nbsp;<select class='select_' style='width: 60px;' data-href='$url'>";
                    for($i = 1; $i <= $totalPages; $i++) {
                        if ($currentPage == $i) {
                            echo "<option value='$i' selected>$i</option>";
                        } else {
                            echo "<option value='$i'>$i</option>";
                        }
                    }

                    echo "</select>";
                ?>
                </p>
            </div>
        </div>
        <div class="container-box2">
            <div class="container-box2-table">
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 25%;">Player</th>
                                <th style="width: 25%;">Admin</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $admin = new Admin();
                                $date = new DateTime("now", new DateTimeZone(DATE_TIME_ZONE));
                                foreach ($results1 as $result1) {
                                    $clientName         = $result1['client_name'];
                                    $clientSteamID      = $result1['client_steamid'];
                                    $adminSteamID       = $result1['admin_steamid'];
                                    $message            = $result1['message'];
                                    $time_stamp         = $result1['time_stamp'];
                                    
                                    $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);


                                    $date->setTimestamp($time_stamp);
                                    $dateFormated = $date->format(DATE_TIME_FORMAT);

                                    echo "<tr class='row-expired'>";
                                    echo "<td>" . e($dateFormated) . "</td>";
                                    if (empty($clientName)) {
                                        echo "<td><i>No nickname present</i> (" . e($clientSteamID) . ")</td>";
                                    } else {
                                        echo "<td>" . e($clientName) . " (" . e($clientSteamID) . ")</td>";
                                    }
                                    echo "<td>" . e($adminName) . "</td>";
                                    echo "<td>" . e($message) . "</td>";
                                    echo "</tr>";
                                }
                            ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    $(function() {
        $('.select_').on('change', function() {
            let value = $(this).val();
            let href = $(this).attr('data-href');
            href += '&page='+value;
            window.location.replace(href);
        });
    });
</script>
<?php include('footer.php'); ?>