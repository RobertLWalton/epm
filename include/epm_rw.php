<?php

    // File:	epm_rw.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Jul 31 10:29:14 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Upon receiving a POST with rw='MODE', require
    // this file.  If mode change cannot be
    // accomplished, appends messages to $errors.
    //
    // WARNING: This file may release any existing LOCK.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $_SESSION['EPM_AID'] ) )
	exit ( 'ACCESS ERROR: EPM_AID not set' );
    if ( ! isset ( $_SESSION['EPM_RW'] ) )
	exit ( 'ACCESS ERROR: EPM_RW not set' );
    if ( ! isset ( $_POST['rw'] ) )
	exit ( 'ACCESS ERROR: POST rw not set' );

    $new_rw = $_POST['rw'];
    if ( ! in_array ( $new_rw, ['rw','ro'], true ) )
	exit ( 'UNACCEPTABLE HTTP POST: RW' );

    if ( $new_rw == $_SESSION['EPM_RW'] )
        /* Do Nothing */;
    elseif ( ! $is_team )
    {
	$_SESSION['EPM_RW'] = $new_rw;
        $rw = $new_rw;
    }
    else
    {
        $d = "admin/team/" . $_SESSION['EPM_AID'];
	if ( ! is_dir ( "$epm_data/$d" ) )
	    exit ( 'UNACCEPTABLE HTTP POST: RW AID' );
	LOCK ( $d, LOCK_EX );
	$f = "$d/+read-write+";
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false ) $c = '';
	else $c = trim ( $c );

	if ( $new_rw == 'ro' )
	{
	    if ( $c == $_SESSION['EPM_UID'] )
	    {
	        $r = @file_put_contents
		    ( "$epm_data/$f", '' );
	        if ( $r === false )
		    ERROR ( "cannot write $f";
	    }
	    $rw = false;
	    $_SESSION['EPM_RW'] = $rw;
	    $RW_BUTTON = $RW_BUTTON_RW;
	}
	elseif ( $c != ''
	         &&
		 $c != $_SESSION['EPM_UID'] )
	{
	    $m = @filemtime ( "$epm_data/$f" );
	    if ( $m === false )
		ERROR ( "cannot stat $f" );
	    $errors[] = "cannot switch to read-write"
	              . " mode";
	    $errors[] = "    user $c has held"
	              . " read-write mode since "
		      . strftime
		          ( $epm_time_format, $m );
	    $errors[] = "    to force mode change"
	              . " use User Page";
	}
	else
	{
	    $r = @file_put_contents
		( "$epm_data/$f",
		  $_SESSION['EPM_UID'] );
	    if ( $r === false )
		ERROR ( "cannot write $f" );
	    $rw = true;
	    $_SESSION['EPM_RW'] = $rw;
	    $RW_BUTTON = $RW_BUTTON_RO;
	}
    }

?>
