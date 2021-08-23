<?php

    // File:	show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Aug 22 21:48:29 EDT 2021

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Display or download a PROBLEM.pdf file.

    // Parameters:
    //
    //	   $_GET['disposition']
    //
    //		show		Display the file.
    //		download	Download the file.
    //
    //	   $_GET['project']	    $_GET['problem']
    //
    //		PROJECT		    PROBLEM
    //
    //		-		    PROBLEM
    //
    // where if PROJECT is not '-', PROBLEM names a
    // problem in projects/PROJECT that must have 'show'
    // privilege for $aid, and if PROJECT is '-',
    // PROBLEM names a problem in accounts/$aid.
    // 
    // Then projects/PROJECT/PROBLEM/PROBLEM.pdf or
    // accounts/$aid/PROBLEM/PROBLEM.pdf is shown or
    // downloaded.
    // 
    // The file to be shown or downloaded must be
    // readable.

    $epm_page_type = '+download+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";
    // exit;
    // Must exit after require except when showing
    // UTF8 files.
            
    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    elseif ( ! isset ( $_GET['disposition'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );
    elseif ( ! isset ( $_GET['project'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );
    elseif ( ! isset ( $_GET['problem'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );

    $disposition = $_GET['disposition'];
    $project     = $_GET['project'];
    $problem     = $_GET['problem'];

    if ( ! in_array ( $disposition, ['show',
                                     'download'] ) )
	exit ( "UNACCEPTABLE HTTP POST: DISPOSITION" );

    if ( ! preg_match ( $problem_name_re, $problem ) )
	exit ( "UNACCEPTABLE HTTP POST: PROBLEM" );

    if ( $project == '-' )
        $fname = "accounts/$aid/$problem/$problem.pdf";
    else
    {
	if ( ! preg_match ( $name_re, $project ) )
	    exit ( "UNACCEPTABLE HTTP POST: PROJECT" );
	problem_priv_map
	    ( $pmap, $project, $problem );

	if ( ! isset ( $pmap['show'] )
	     ||
	     $pmap['show'] == '-' )
	    exit ( "YOU DO NOT HAVE `show' PRIVILEGE" .
	           " FOR PROJECT $project" .
		   " PROBLEM $problem" );
        $fname =
	    "projects/$project/$problem/$problem.pdf";
    }

    if ( ! is_readable ( "$epm_data/$fname" ) )
        exit ( "$fname IS NOT READABLE" );
    $fsize = @filesize ( "$epm_data/$fname" );
    if ( $fsize === false )
        ERROR ( "cannot stat readable $fname" );

    if ( $disposition == 'download' )
        $d = 'attachment';
    else
        $d = 'inline';
    header ( 'Content-type: application/pdf' );
    header ( "Content-Disposition: $d;" .
	     " filename=$problem.pdf" );
    header ( 'Content-Transfer-Encoding: binary' );
    header ( "Content-Length: $fsize" );
    $r = @readfile ( "$epm_data/$fname" );
    if ( $r === false )
	ERROR ( "cannot read readable $fname" );
?>
