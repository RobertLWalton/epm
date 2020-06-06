<?php

// File:    debug_info.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Jun  6 04:55:23 EDT 2020

// require this to print system info for debugging.

if ( ! isset ( $_POST['xhttp'] ) )
{
    echo "SESSION: "; var_dump ( $_SESSION );
    echo "<br><br>";
    echo "REQUEST: "; print_r ( $_REQUEST );
    echo "<br>";
    echo "POST: "; print_r ( $_POST );
    echo "<br>";
    echo "GET: "; print_r ( $_GET );
    echo "<br>";
    echo "FILES: "; print_r ( $_FILES );
    echo "<br>";
    echo "COOKIE: "; print_r ( $_COOKIE );
    echo "<br>";
    $__server = [];
    $__server['PHP_SELF'] = $_SERVER['PHP_SELF'];
    $__server['DOCUMENT_ROOT'] =
        $_SERVER['DOCUMENT_ROOT'];
    echo "SERVER: "; print_r ( $__server ); echo "<br>";
    echo "UMASK: "; printf ( '0%o', umask() ); echo "<br>";
}

?>
