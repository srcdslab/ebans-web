<?php
    include('functions_global.php');
    echo "Please wait....";

    setcookie("steamID", "");
    header("Location: index.php?all");
    die();
?>
