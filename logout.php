<?php
    include('functions_global.php');
    echo "Please wait....";

    setcookie("steamID", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
    setcookie("secret_key", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
    setcookie("aid", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
    header("Location: index.php?all");
    die();
?>
