<?php

    // File:	download.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Jul 21 09:06:25 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Downloads users/$aid/+download+ using
    // $GET['content-type'] as the content type
    // and $_GET['filename'] as the file name.
    // Then deletes users/$aid/+download+.

    $epm_page_type = '+download+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";
    // exit;  // Must exit after printing debug stuff.

    $aid = $_SESSION['EPM_AID'];

    if ( ! isset ( $_GET['content-type'] ) )
        exit ( "UNACCEPTABLE HTTP $epm_method" );
    if ( ! isset ( $_GET['filename'] ) )
        exit ( "UNACCEPTABLE HTTP $epm_method" );
    $content_type = $_GET['content-type'];
    $filename = $_GET['filename'];

    $f = "$epm_data/users/$aid/+download+";
    $filesize = @filesize ( $f );
    if ( $filesize === false )
        exit ( "UNACCEPTABLE HTTP $epm_method" );
    $fp = fopen ( $f, "r" );
    if ( $fp === false )
        exit ( "UNACCEPTABLE HTTP $epm_method" );

    header ( "Content-type: $content_type" );
    header ( "Content-Disposition: attachment;" .
             " filename=$filename" );
    header ( "Content-Transfer-Encoding: binary" );
    header ( "Content-Length: $filesize" );
    fpassthru ( $fp );
    fclose ( $fp );
    unlink ( $f );
?>
