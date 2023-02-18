<?php
    include('header.php');

    if(!IsAdminLoggedIn()) {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> You do not have access to this page.</p>
        </div>
        </div>
        </div>";
        die();
    }
    
    $admin = new Admin();
    $admin->UpdateAdminInfo($_COOKIE['steamID']);
    if(!$admin->DoesHaveFullAccess()) {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> You do not have access to this page.</p>
        </div>
        </div>
        </div>";
        die();
    }

    if(isset($_GET['page'])) {
        $currentPage = ($_GET['page'] <= 0) ? 1 : $_GET['page'];
    } else {
        $currentPage = 1;
    }

    $resultsPerPage = 20;
    $resultsStart = (($currentPage - 1) * $resultsPerPage);

    $sql = "SELECT * FROM ";
    $sql .= "`logs`";

    if(isset($_GET['s'])) {
        $input = $_GET['s'];
        $method = formatMethod(intval($_GET['m']));

        if($method == 'admin_name') {
            $method = "aid";
            $adminID = (new Admin())->GetAdminIDFromName($input);
            $input = $adminID;
        }

        $sql .= " WHERE `$method`=$input ";
    }

    $sql_query = $GLOBALS['DB']->query($sql);
    $resultsCount = $sql_query->num_rows;
    $totalPages = ceil(($resultsCount / $resultsPerPage));

    $sql_query->free();
    if($totalPages != 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    echo "<script>setActive(4); setModalSearch(\"web\");</script>";
?>

<!DOCTYPE html>
<html>
    <?php
    $query = $GLOBALS['DB']->query($sql . "ORDER BY created DESC LIMIT $resultsStart, $resultsPerPage");
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
			<h1><i class="fa-regular fa-hard-drive"></i> Web Logs</h1>
            </div>
			<div class="breadcrumb">
<i class="fas fa-angle-right"></i> <a href="index.php?all">Home</a>
<i class="fas fa-angle-right"></i> <a href="logs.php?web">Web Logs</a>
</div>
        <div class="container-search">
            <div class="search-button search-modal-btn-open" id="search-button" data-page=<?php echo "\"web\""; ?>>
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
                                <th style="width: 15%;">Date</th>
                                <th style="width: 15%;">Admin</th>
                                <th style="width: 15%;">Host</th>
                                <th style="width: 10%;">Action</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $admin = new Admin();
                                $date = new DateTime("now", new DateTimeZone("GMT+1"));
                                foreach($results1 as $result1) {
                                    $aid                = $result1['aid'];
                                    $action             = $result1['title'];
                                    $host               = $result1['host'];
                                    $message            = $result1['message'];
                                    $time_stamp         = $result1['created'];
                                    
                                    $adminName = $admin->GetAdminNameFromID($aid);


                                    $date->setTimestamp($time_stamp);
                                    $dateFormated = $date->format("Y-m-d h:i:s");

                                    echo "<tr class='row-expired'>";
                                    echo "<td>$dateFormated</td>";
                                    echo "<td>$adminName</a></td>";
                                    echo "<td>$host</td>";
                                    echo "<td>$action</td>";
                                    echo "<td>$message</td>";
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
</div>
