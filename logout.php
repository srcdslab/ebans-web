<?php

    include_once('functions_global.php');

    /* Nothing may be written to the response before this point.
       This file used to `echo "Please wait...."` first, which flushed the body
       and made every setcookie() below -- and the redirect -- fail with
       "Cannot modify header information - headers already sent". Clicking
       Logout left the browser fully authenticated, looking at a page that said
       "Please wait....". */
    destroyAdminSession();

    /* Also clear the pre-session login cookies, so a browser upgrading from
       the old scheme does not keep carrying them around. */
    clearLoginCookies();

    header("Location: index.php?all");
    die();
