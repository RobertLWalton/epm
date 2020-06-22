<?php

    // File:	pdf_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Jun  6 05:53:38 EDT 2020

    // The authors have this file in the public domain;
    // they make no warranty and accept no liability for
    // this file.

    // Show the PDF file $_GET['filename'] for
    // problem $_GET['problem'].  File may be in
    // problem directory or a temporary in its
    // +work+ subdirectory.

    $epm_page_type = '+init+';
    $epm_pdf = true;
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    elseif ( ! isset ( $_SESSION['EPM_UID'] ) )
	exit ( "ACCESS: illegal GET to pdf_show.php" );
    elseif ( ! isset ( $_GET['problem'] ) )
	exit ( "ACCESS: illegal GET to pdf_show.php" );
    elseif ( ! isset ( $_GET['filename'] ) )
	exit ( "ACCESS: illegal GET to pdf_show.php" );

    $uid = $_SESSION['EPM_UID'];
    $problem = $_GET['problem'];
    $probdir = "users/$uid/$problem";
    $filename = $_GET['filename'];

    $ext = pathinfo ( $filename, PATHINFO_EXTENSION );
    if ( ! isset ( $display_file_type[$ext] )
         ||
	 $display_file_type[$ext] != 'pdf' )
        exit ( "ACCESS: illegal GET to pdf_show.php" );

    $f = "$probdir/$filename";
    if ( ! is_readable ( "$epm_data/$f" ) )
        exit ( "ACCESS: illegal GET to pdf_show.php" );

    header ( 'Content-Type: application/pdf' );
    header ( 'Content-Disposition: inline;' .
             'filename=' . $filename );
    header ( 'Content-Transfer-Encoding: binary' );
    header ( 'Content-Length: ' .
             filesize ( "$epm_data/$f" ) );
    header ( 'Accept-Ranges: bytes' );
    @readfile ( "$epm_data/$f" );

?>
