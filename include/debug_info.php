<?php

// File:    debug_info.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Sep  5 12:20:35 EDT 2020

// require this to print system info for debugging.

if ( ! isset ( $_POST['xhttp'] ) )
{
    echo "REQUEST: " . json_encode ( $_REQUEST ) . "<br>";
    echo "POST: " . json_encode ( $_POST ) . "<br>";
    echo "GET: " . json_encode ( $_GET ) . "<br>";
    echo "FILES: " . json_encode ( $_FILES ) . "<br>";
    echo "COOKIE: " . json_encode ( $_COOKIE ) . "<br>";
    $__server = [];
    $__server['PHP_SELF'] = $_SERVER['PHP_SELF'];
    $__server['DOCUMENT_ROOT'] =
        $_SERVER['DOCUMENT_ROOT'];
    echo "SERVER: " . json_encode ( $__server ) . "<br>";
    if ( isset ( $data ) )
	echo "DATA: " . json_encode ( $data ) . "<br>";
    echo "<br>";
    echo "\$epm_method: $epm_method" .
	 "<pre>    </pre>\$epm_root: $epm_root" .
	 "<pre>    </pre>\$epm_self: $epm_self" .
	 "<br>";
    echo "\$epm_page_type: $epm_page_type";
    if ( isset ( $id_type ) )
        echo "<pre>    </pre>\$id_type: $id_type";
    if ( isset ( $epm_ID_init ) )
        echo "<pre>    </pre>\$epm_ID_init set";
    if ( isset ( $ID ) )
        echo "<pre>    </pre>\$ID: $ID";
    echo "<br>";
    if ( isset ( $uid ) )
        echo "\$aid: $aid" .
	     "<pre>    </pre>\$uid: $uid" .
	     "<pre>    </pre>\$is_team: " .
	     ( $is_team ? "true" : "false" ) . 
	     "<pre>    </pre>\$lname: $lname" .
	     "<pre>    </pre>\$rw: " .
	     ( $rw ? "true" : "false" ) . "<br>";
    echo "\$epm_web: $epm_web" .
         "<pre>    </pre>\$epm_home: $epm_home" .
         "<pre>    </pre>\$epm_data: $epm_data<br>";
}

?>
