<?php

// File:	abort.php
// Author:	Robert L Walton <walton@acm.org>
// Date:	Tue Jul 21 12:10:25 EDT 2020

$our_time = strftime ( $epm_time_format, $our_time );
$cur_time = strftime ( $epm_time_format, $cur_time );
$aid = $_SESSION['EPM_AID'];

echo <<<EOT
    <html>
    <body>
    <div style='background-color:#FFAAAA'>
    <h1>This Session has been Aborted</h1>
    <h2>You should close all tabs and
        windows of this session!</h2>
    <h2>
    This session for $aid,
    <br>
    started about $our_time,
    <br>
    has been aborted by a new session for $aid,
    <br>
    started about $cur_time.
    </h2>
    <h2>
    You cannot have two simutaneous sessions for $aid.
    </h2>

    </div>
    </body>
    </html>
EOT;
exit;

?>
