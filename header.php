<?php
    include_once('connect.php'); 
    include_once('functions_global.php');

    $GLOBALS['steamID'] = "";
    if(isset($_COOKIE['steamID'])) {
        $GLOBALS['steamID'] = $_COOKIE['steamID'];
        $admin = new Admin();
        $admin->UpdateAdminInfo($_COOKIE['steamID']);
        $adminName = $admin->adminUser;

        $steam = new Steam();
        $steamID64 = $steam->SteamID_To_SteamID64($GLOBALS['steamID']);
        $adminURL = "https://steamcommunity.com/profiles/$steamID64";
    }
?>

<head>
    <title>EntWatch Bans</title>
    <link rel="icon" href="images/favicon.ico" />
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css?family=Inter:300,300i,400,400i,500,700,700i" rel="stylesheet" referrerpolicy="origin">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/mustache.js/3.0.0/mustache.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
	<script src="js/ebans.js"></script>
</head>
<body>
    <div class="hide" style="display: none;"></div>
    <div class="Eban-action-window">
        <div class="header">
            <p id="action-header-text"></p>
        </div>
        <div class="info">
            <ul class="Eban_details">

            </ul>
        </div>
        <div class="info-footer">
            <button class="button button-important" onclick="CloseWindow();">Close <i class='fa-solid fa-xmark'></i></button>
            <button class="button button-success" onclick="GoHome();">Go To Eban List <i class='fa-solid fa-house'></i></button>
        </div>
    </div>
    <div class="search-modal-body">
        <div class='search-modal'>
            <div class='header'>
                <p><i class='fa-solid fa-magnifying-glass'></i> Advanced Search</p>
                <p class='search-modal-btn-close'><i class='fa-solid fa-xmark'></i></p>
            </div>
            <form method="GET">
                <input id='hideInput' type='text' style='display: none;' name='' value='true'>
                <div class='input-group'>
                    <label for='s'>Input</label>
                    <input class='input search-modal-input' type='text' name='s' required>
                </div>
                <div class='input-group'>
                    <label for='m'>Method</label>
                    <select name='m' class='select search-modal-input'>
                        <?php 
                        if(!isset($_GET['web'])) { ?>
                            <option value='1'>Player SteamID</option>
                            <option value='2'>Player Name</option>
                        <?php } ?>
                        
                        <option value='4'>Admin Name</option>
                        <?php
                        if(!isset($_GET['web'])) { ?>
                            <option value='5'>Admin SteamID</option>
                        <?php } ?>
                    </select>
                </div>
                <button type='submit' class='button button-primary Eban-form-button' style='width: 90%; margin-left: 5%;'><i class='fa-solid fa-magnifying-glass'></i> Search</button>
            </form>
        </div>
    </div>
<header>
    <div class="header1">
        <div class="header1-icons">
            <?php
				echo '<a id="steam_group" target="_blank" href="'. $GLOBALS['STEAM_GROUP'] .'" rel="noopener" title="Our Steam Group">'
			?>
                <i class="fab fa-steam-symbol"></i>
            </a>
            <a id="discord" target="_blank" href="https://discord.gg/XhByCBg" rel="noopener" data-ipstooltip="" _title="Join us on Discord">
                <i class="fab fa-discord"></i>
            </a>
        </div>
        <div class="login">
            <?php
            if(IsAdminLoggedIn()) {
            ?>
            <p>Welcome, &nbsp;<a href=<?php echo "\"$adminURL\""; ?> target="_blank"><i class='fa-solid fa-user'></i> <?php echo $adminName; ?></a>&nbsp; &nbsp;
            <a class="button button-important" href='logout.php'><i class='fas fa-sign-out-alt'></i>
                Logout</a></p>
            <?php } else { ?>
            <a class="button button-success" href='login-init.php'>Existing user? Sign In</a>
            <?php } ?>
        </div>
    </div>
    <div class="header2">
        <?php
			echo '<a class="logo" href="'. $GLOBALS['SERVER_FORUM_URL'] .'">'
        ?>
            <img src="images/banner.png" alt="logo">
        </a>
        <div class="search_input">
            <form method="GET" action="index.php">
                <input type='text' style='display: none;' name='all' value='true'>
                <input type="text" class="input input-header-search" placeholder="Search SteamID..." name='s' required>
                <input type='text' style='display: none;' name='m' value='1'>
                <button id="search_icon"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
    </div>
</header>
<div class="body_content">
    <div class="header3">
        <ul>
            <li><a class="not-active" id="allEbans" href="index.php?all"><i class="fa-solid fa-house"></i> &nbspAll Ebans</a></li>
            <li><a class="not-active" id="activeEbans"  href="index.php?active"><i class="fa-solid fa-hourglass-half"></i> &nbspActive Ebans</a></li>
            <li><a class="not-active"  id="expiredEbans" href="index.php?expired"><i class="fa-solid fa-hourglass-end"></i> &nbspExpired Ebans</a></li>
            <?php
                if(IsAdminLoggedIn()) {
                    echo "<li><a id='addEban' class='not-active' id='add' href='manage.php?add'><i class='fas fa-user-times'></i> &nbspAdd Eban</a></li>"; 
                    $admin = new Admin();
                    $admin->UpdateAdminInfo($_COOKIE['steamID']);
                    if($admin->DoesHaveFullAccess()) {
            ?>
            <li><a class="not-active" id="weblogs" href="logs.php?web"><i class='far fa-hdd'></i> &nbspWeb Logs</a></li>
            <?php } ?>
            <?php } ?>
        </ul>
    </div>
