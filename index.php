<?php
    include('header.php');

    if(!isset($_GET['all']) && !isset($_GET['active']) && !isset($_GET['expired'])) {
        echo "<script>window.location.replace('index.php?all');</script>";
        die();
    }

    if(isset($_GET['page'])) {
        $currentPage = ($_GET['page'] <= 0) ? 1 : $_GET['page'];
    } else {
        $currentPage = 1;
    }

    $resultsPerPage = 20;
    $resultsStart = (($currentPage - 1) * $resultsPerPage);

    $pageType = "all";
    $sql = 'SELECT * FROM `EntWatch_Current_Eban` UNION ALL SELECT * FROM `EntWatch_Old_Eban`';
    if(isset($_GET['active'])) {
        $sql = 'SELECT * FROM `EntWatch_Current_Eban` ';
        $pageType = "active";
    } else if(isset($_GET['expired'])) {
        $pageType = "expired";
        $sql = 'SELECT * FROM `EntWatch_Old_Eban` ';
    }

    if(isset($_GET['s'])) {
        $input = $_GET['s'];
        $method = formatMethod(intval($_GET['m']));
        if($method == "client_steamid" || $method == "admin_steamid") {
            if(!str_contains($input, "STEAMID")) {
                if(str_contains($input, " ")) {
                    $input = str_replace(" ", "", $input);
                }
            }
        }

        if($pageType == "all") {
            $sql = "SELECT * FROM `EntWatch_Current_Eban` WHERE `$method` LIKE '%$input%' UNION ALL SELECT * FROM `EntWatch_Old_Eban` WHERE `$method` LIKE '%$input%' ";
        } else {
            $sql .= "WHERE `$method` LIKE '%$input%' ";
        }
    }
    
    $sql_query = $GLOBALS['DB']->query($sql);
    $resultsCount = $sql_query->num_rows;
    $totalPages = ceil(($resultsCount / $resultsPerPage));

    $sql_query->free();
    if($totalPages != 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    
    $pageActiveNum = 2;
    if($pageType == "all") {
        $pageActiveNum = 0;
        $pageName = "Eban List";
        $icon = "<i class='fa-solid fa-house'></i>";
    } else if($pageType == "active") {
        $pageActiveNum = 1;
        $pageName = "Active Eban";
        $icon = "<i class='fa-solid fa-hourglass-half'></i>";
    } else if($pageType == "expired") {
        $pageActiveNum = 2;
        $pageName = "Expired Eban";
        $icon = "<i class='fa-solid fa-hourglass-end'></i>";
    }

    echo "<script>setActive($pageActiveNum); setModalSearch(\"$pageType\");</script>";
?>

<!DOCTYPE html>
<html lang="en">
    <?php
        $query = $GLOBALS['DB']->query($sql . "ORDER BY timestamp_issued-duration*60 DESC LIMIT $resultsStart, $resultsPerPage");
        $results1 = $query->fetch_all(MYSQLI_ASSOC);
        $resultsRealCount = $query->num_rows;
        $query->free();

        $url = $_SERVER['REQUEST_URI'];
        if(str_contains($url, '&page')) {
            $url = substr($url, 0, strpos($url, '&page'));
        }
    ?>
        <div class="container">
            <div class="container-header">
                <h1><?php echo "$icon $pageName"; ?></h1>
            </div>
            <div class="breadcrumb">
<i class="fas fa-angle-right"></i> <a href="index.php?all">Home</a>
<i class="fas fa-angle-right"></i> <a href="index.php?all"><?php echo "$pageName"; ?></a>
</div>
            <div class="container-search">
                <div class="search-button search-modal-btn-open" id="search-button" data-page=<?php echo "\"$pageType\""; ?>>
                    <p><strong>Advanced Search (Click)</strong></p>
                </div>
            </div>
            <div class="container-box1">
                <div class="order1">
                    <p id="totalText" results=<?php echo "$resultsCount";?>>&nbsp Total Ebans: <?php echo $resultsCount; ?></p>
                </div>
                <div class="order2">
                    <?php
                        $resultsEnd = $resultsStart + $resultsRealCount;
                    ?>
                    <p id="displaying-text" results=<?php echo "$resultsStart"; ?> totalresults=<?php echo "$resultsEnd" ?>>displaying <?php echo "$resultsStart - $resultsEnd"; ?> of <?php echo $resultsCount; ?> results |
                    <?php
                        $nextPage = $currentPage + 1;
                        $previousPage = $currentPage - 1;

                        if($previousPage > 0) {
                            $href = $url . "&page=$previousPage";
                            echo "<a href='$href'><i class='fa fa-arrow-circle-left'></i> previous</a> |";
                        }

                        if($nextPage > 0 && $nextPage <= $totalPages) {
                            $href = $url . "&page=$nextPage";
                            echo "&nbsp;<a href='$href'>next <i class='fa fa-arrow-circle-right'></i></a>";
                        }

                        echo "&nbsp;<select class='select_' style='width: 60px;' data-href='$url'>";
                        for($i = 1; $i <= $totalPages; $i++) {
                            if($currentPage == $i) {
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
                                <th style="width: 3%;">Game</th>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 15%;">Player</th>
                                <th style="width: 5%; padding: 0;"></th>
                                <th style="width: 25%;">Reason</th>
                                <th style="width: 15%;">Admin</th>
                                <th style="width: 20%;">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $Eban = new Eban();
                                    $admin = new Admin();
                                    $dateA = new DateTime("now", new DateTimeZone(DATE_TIME_ZONE));
                                    foreach($results1 as $result1) {
                                        $id                 = $result1['id'];
                                        $clientName         = $result1['client_name'];
                                        $clientSteamID      = $result1['client_steamid'];
                                        $adminSteamID       = $result1['admin_steamid'];
                                        $reason             = $result1['reason'];
                                        $duration           = $result1['duration'];
                                        $timestamp_issued   = $result1['timestamp_issued'];
                                        $timestamp_unban     = $result1['timestamp_unban'];
                                        $adminNameRemoved   = $result1['admin_name_unban'];
                                        $adminSteamIDRemoved = $result1['admin_steamid_unban'];

                                        $isExpired = ($adminSteamIDRemoved == "SERVER" && ($timestamp_issued) < time()) ? true : false;
                                        $isRemoved = ($adminSteamIDRemoved != "" && $adminSteamIDRemoved != "SERVER") ? true : false;
                                        
                                        $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);

                                        $length = $Eban->formatLength($duration * 60);
                                        if($duration == 0) {
                                            $length = "Permanent";
                                        } else if($duration <= -1) {
                                            $length = "Session";
                                        }

                                        if(($isExpired == true && $isRemoved == false)) {
                                            $length .= ' (Expired)';
                                            if(isset($_GET['active'])) {
                                                continue;
                                            }
                                        }

                                        if($isRemoved == true) {
                                            $length .= ' (Removed)';
                                        }

                                        $class = "row-expired";
                                        if(!$isExpired && !$isRemoved) {
                                            $class = "row-active";
                                        }

                                        if($duration == 0) { // permanent ban
                                            $class = "row-permanent";
                                        }

                                        if($isRemoved) {
                                            $class = "row-expired";
                                        }

                                        $count = 0;
                                        $realcount = 0;
                                        $count = $Eban->GetEbansNumber($clientSteamID);
                                        $realcount = $Eban->GetRealEbansNumber($clientSteamID);

                                        $dateA->setTimestamp(($timestamp_issued - ($duration * 60)));
                                        $dateB = $dateA->format(DATE_TIME_FORMAT);
                                        echo "<tr class='$class' id-data='$id' id='diva-tr-$id'>";
                                        
                                        echo "<td style='background-color: transparent; align-items: center;'><img src='./images/games/csource.png' border='0' align='absmiddle' alt='css'></td>";
                                        echo "<td>$dateB</td>";
                                        if(empty($clientName)) {
                                            echo "<td><i>No nickname present</i></td>";
                                        } else {
                                            echo "<td>$clientName</td>";
                                        }
                                        if($count >= 2) {
                                            if ($count == $realcount) {
                                                echo "<td style='color: var(--theme-text); padding: 0;' class='count' id='$id-count' count='$count' steamid='$clientSteamID'><i class='fa-solid fa-ban'></i> <b>$realcount</b></td>";
                                            } else {
                                                echo "<td style='color: var(--theme-text); padding: 0;' class='count' id='$id-count' count='$count' steamid='$clientSteamID'><i class='fa-solid fa-ban'></i> <b>$realcount</b> ($count)</td>";
                                            }
                                        } else {
                                            echo "<td></td>";
                                        }
                                        echo "<td>$reason</td>";
                                        echo "<td>$adminName</td>";
                                        echo "<td class='row-length' id='length-$id'>$length</td>";
                                    
                                        echo "</tr>";

                                        echo "<tr id='diva-$id-tr' style='display: none; width: 100%; height: 100%;'>";
                                        echo "<td colspan='15'>";
                                        echo "<div id='diva-$id' class='row-block' is_slided='0'>";
                                        GetRowInfo(0, $result1);
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</body>
<script>
    $(function() {
        var allRows = [
            ".row-expired",
            ".row-permanent",
            ".row-active"
        ];

        for(var i = 0; i < allRows.length; i++) {
            $(allRows[i]).on('click', function() {
                showEbanInfo(this);
            });
        }

        $('.select_').on('change', function() {
            let value = $(this).val();
            let href = $(this).attr('data-href');
            href += '&page='+value;
            window.location.replace(href);
        });
    });
</script>
