<?php

    // File:	pdf_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu May 21 04:57:12 EDT 2020

    // Show the PDF file $_GET['filename'].
    // File may be in current problem directory
    // or a temporary in its +work+ subdirectory.

    $epm_is_subwindow = true;
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $problem = $_SESSION['EPM_PROBLEM'];

    $probdir = "users/$uid/$problem";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    if ( ! isset ( $_GET['filename'] ) )
	exit
	    ( "ACCESS: illegal GET to pdf_show.php" );

    $filename = $_GET['filename'];
    $printname = "<u>$filename</u>:";
    $f = "$probdir/$filename";
    $g = "$probdir/+work+/$filename";
    if ( ! is_readable ( "$epm_data/$f" ) )
    {
        if ( ! is_readable ( "$epm_data/$g" ) )
	    exit ( "ACCESS: illegal GET to" .
	           " pdf_show.php" );
	$f = $g;
	$printname .=
	    '&nbsp;&nbsp;&nbsp;&nbsp;(temporary)';
    }

    $ext = pathinfo ( $filename, PATHINFO_EXTENSION ); 
    if ( $ext != 'pdf' )
	exit
	    ( "ACCESS: illegal GET to pdf_show.php" );

    header ( 'Content-Type: application/pdf' );
    header ( 'Content-Disposition: inline;' .
             'filename=' . $filename );
    header ( 'Content-Transfer-Encoding: binary' );
    header ( 'Content-Length: ' .
             filesize ( "$epm_data/$f" ) );
    header ( 'Accept-Ranges: bytes' );
    @readfile ( "$epm_data/$f" );

?>
