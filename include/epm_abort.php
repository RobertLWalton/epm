<?php

// File:	abort.php
// Author:	Robert L Walton <walton@acm.org>
// Date:	Tue Apr  5 04:14:36 EDT 2022

$our_time = date ( $epm_time_format, $our_time );
$cur_time = date ( $epm_time_format, $cur_time );
$aid = $_SESSION['EPM_AID'];
$email = $_SESSION['EPM_EMAIL'];

echo <<<EOT
    <html>
    <body>
    <div style='background-color:#FFAAAA'>
    <h1>This Session has been Aborted</h1>
    <h2>You should close all tabs and
        windows of this session!</h2>
    <h2>
    This session for $aid:$email,
    <br>
    started about $our_time,
    <br>
    has been aborted by a new session for $aid:$email,
    <br>
    started about $cur_time.
    </h2>
    <h2>
    You cannot have two simutaneous sessions for
    $aid:$email.
    </h2>
    <h2>
    If you want to start a new un-aborted session,
    you must first close ALL your
    browser windows and then restart your browser.
    </h2>

    </div>
    </body>
    </html>
EOT;
exit;

?>
