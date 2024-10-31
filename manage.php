<?php

    include('header.php');

    $admin = new Admin();
    if (!IsAdminLoggedIn()) {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> You do not have access to this page.</p>
        </div>
        </div>
        </div>";
        die();
    }

    $reban = false;
    $edit = false;
    $add = false;

    if (isset($_GET['reban'])) {
        $reban = true;
        $oldid = $_GET['oldid'];
        $Eban = new Eban();
        $info = $Eban->getEbanInfoFromID(intval($oldid));
    }

    if (isset($_GET['edit'])) {
        $edit = true;
        $oldid = $_GET['oldid'];
        $Eban = new Eban();
        $info = $Eban->getEbanInfoFromID(intval($oldid));

        $timestamp_issued = $info['timestamp_issued'];
        $duration = $info['duration'];
        if ($duration == 0) {
            // Do nothing
        } else {
            if ($timestamp_issued >= 1 && time() > $timestamp_issued) {
                echo "<div class='container'>
                <div class='error-box'>
                <p><i class='fa-solid fa-triangle-exclamation'></i> Cannot edit an old Eban!</p>
                </div>
                </div>
                </div>";
                die();
            }
        }
    }

    if (isset($_GET['add'])) {
        $add = true;
    }

    if ($add || $reban) {
        echo "<script>setActive(3);</script>";
    }
?>

<!DOCTYPE html>

<html>
    <?php
    $text = ($edit == true) ? "Edit Eban" : "Add Eban";
    $formHeader = ($edit == true) ? "<i class='fa-regular fa-pen-to-square'></i>" : "<i class='fas fa-user-times'></i>";
    $formHeader .= " $text";
    $icon = ($edit == false) ? "<i class='fas fa-user-times'></i>" : "<i class='fa-regular fa-pen-to-square'></i>";
    ?>
    <div class="container">
        <div class="container-header">
            <h1><?php echo "$icon $text"; ?></h1>
            <div class="breadcrumb">
<i class="fas fa-angle-right"></i> <a href="index.php?all">Home</a>
<i class="fas fa-angle-right"></i> <a href="manage.php?add"><?php echo $text ?></a>
</div>
        </div>
        <div class="container-box2">
            <div class="Eban-form">
                <div class="header">
                    <p><?php echo $formHeader; ?></p>
                </div>

                <div class="error">

                </div>

                <?php
                    $val = "";
                    if ($edit == true || $reban == true) {
                        $val = $info['client_name'];
                    }
                ?>

                <div class="input-group">
                    <label for="name">Player Name</label>
                    <input id="playerName" type="text" class="input Eban-input" max="32" value=<?php echo "\"$val\""; ?>>
                </div>

                <?php
                    $val = "";
                    if ($edit == true || $reban == true) {
                        $val = $info['client_steamid'];
                    }
                ?>

                <div class="input-group">
                    <label for="steamid">Steam ID</label>
                    <?php if (empty($val)) { ?>
                        <input id="playerSteamID" type="text" class="input Eban-input">
                    <?php } else { ?>
                        <input id="playerSteamID" type="text" class="input Eban-input" value=<?php echo "\"$val\""; ?> title="Why the f*ck do you want to edit the SteamID? Just add a new Eban nigger" disabled>
                    <?php } ?>
                </div>

                <?php
                    $val = "";
                    if ($edit == true || $reban == true) {
                        $val = $info['reason'];
                    }
                ?>

                <div class="input-group">
                    <label for="reason">Reason</label>
                    <input id="reason" type="text" class="input Eban-input" max="120" value=<?php echo "\"$val\""; ?>>
                </div>

                <?php if ($add == true) { ?>
                <div class="input-group">
                    <label for="length">Duration</label>
                    <?php GetEbanLengths(); ?>
                </div>
                <?php } ?>

                <?php if ($add == false) {
                    $timestamp_issued = $info['timestamp_issued'];
                    $duration = $info['duration'];
                    $val = 0;

                    if ($duration == 0) {
                        $val = 0;
                    } elseif ($duration <= -1) {
                        $val = -1;
                    } else {
                        $val = intval($duration);
                    }
                ?>
                <div class="input-group">
                    <label for="length"> Duration </label>
                    <p style="font-style: italic; color: var(--theme-text_light); margin-top: 5px;">Enter 0 minutes for a permanent ban</p>
                    <input id="length-edit" type="text" class="input Eban-input" value=<?php echo "\"$val\""; ?>style="width: 110px; display: inline-block;">
                    <?php GetEbanLengthTypes(); ?>
                </div>
                <?php } ?>

                <?php
                $buttonID = ($edit == false) ? "add-button" : "edit-button";
                $buttonTextValidate = ($edit == false) ? "Add Eban" : "Save Changes";
                if (!isset($_GET['oldid'])) {
                    $oldid = "";
                }

                echo "<button class='button button-success Eban-form-button' style='width: 80%; margin-left: 10%;' data-oldid='$oldid' id='$buttonID' type='submit'> $buttonTextValidate</button>";
                ?>
            </div>
        </div>
    </div>
    <?php include('footer.php'); ?>
</div>
</body>
    <script>
        $(function() {
            function verifyAndConvertSteamID(steamID, callback) {
                $.ajax({
                    url: 'verify_steamid.php',
                    type: 'POST',
                    data: { steamid: steamID },
                    success: function(response) {
                        const result = JSON.parse(response);
                        callback(result);
                    },
                    error: function() {
                        alert('Error verifying SteamID.');
                    }
                });
            }

        $('#add-button').on('click', function() {
            let playerName = $('#playerName').val();
            let playerSteamID = $('#playerSteamID').val();
            let reason = $('#reason').val();
            let length = 30;

            <?php if ($add == true) { ?>
                length = $('#add-select').val();
            <?php } else { ?>
                length = $('#length-edit').val();
                let select = $('#edit-select').val();

                if (select == 2) {
                    length *= 60;
                } else if (select == 3) {
                    length *= 60 * 60;
                } else if (select == 4) {
                    length *= 60 * 60 * 24;
                } else if (select == 5) {
                    length *= 60 * 60 * 24 * 7;
                } else if (select == 6) {
                    length *= 60 * 60 * 24 * 30;
                }
            <?php } ?>

            verifyAndConvertSteamID(playerSteamID, function(response) {
                if (response.success) {
                    addNewEban(playerName, response.steamID2, length, reason);
                } else {
                    alert('Invalid SteamID: ' + response.error);
                }
            });
        });

        $('#edit-button').on('click', function() {
            let id = $(this).attr('data-oldid');
            let playerName = $('#playerName').val();
            let playerSteamID = $('#playerSteamID').val();
            let reason = $('#reason').val();
            let length = $('#length-edit').val();
            let select = $('#edit-select').val();

            if (select == 2) {
                length *= 60;
            } else if (select == 3) {
                length *= 60 * 60;
            } else if (select == 4) {
                length *= 60 * 60 * 24;
            } else if (select == 5) {
                length *= 60 * 60 * 24 * 7;
            } else if (select == 6) {
                length *= 60 * 60 * 24 * 30;
            }

            verifyAndConvertSteamID(playerSteamID, function(response) {
                if (response.success) {
                    EditEban(id, playerName, response.steamID2, length, reason);
                } else {
                    alert('Invalid SteamID: ' + response.error);
                }
            });
        });
    });
</script>