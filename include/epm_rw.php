<?php

    // File:	epm_rw.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Aug  4 19:48:58 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Upon receiving a POST with rw='MODE', require
    // this file.  If mode change cannot be
    // accomplished, appends messages to $errors.
    // If the requirer wants to change mode anyway,
    // just call force_rw() which is defined in this
    // code.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $aid ) )
	exit ( 'ACCESS ERROR: $aid not set' );
    if ( ! isset ( $rw ) )
	exit ( 'ACCESS ERROR: $rw not set' );
    if ( ! isset ( $is_team ) )
	exit ( 'ACCESS ERROR: $is_team not set' );
    if ( ! isset ( $_POST['rw'] ) )
	exit ( "ACCESS ERROR: POST['rw'] not set" );

    if ( ! $is_team )
	exit ( 'UNACCEPTABLE HTTP POST: NOT TEAM' );

    $new_rw = $_POST['rw'];
    if ( $rw && $new_rw != 'ro' )
	exit ( 'UNACCEPTABLE HTTP POST: BAD RW' );
    if ( ! $rw && $new_rw != 'rw' )
	exit ( 'UNACCEPTABLE HTTP POST: BAD RW' );

    // So we need to toggle $rw.

    if ( $rw )
    {
        ftruncate ( $rw_handle, 0 );
	    // We leave $rw_handle locked because it
	    // will be unlocked by shutdown and we do
	    // not wish to code to allow rw_unlock to
	    // be called twice.
	$rw = false;
	$RW_BUTTON = $RW_BUTTON_RW;
    }
    else
    {
        $rw_handle = fopen
	    ( "$epm_data/$rw_file", "c+" );
	flock ( $rw_handle, LOCK_EX );
	$u = fread ( $rw_handle, 1000 );

	function force_rw()
	{
	    global $rw_handle, $rw, $uid,
	           $RW_BUTTON, $RW_BUTTON_RO;

	    ftruncate ( $rw_handle, 0 );
	    fwrite ( $rw_handle, $uid );
	    register_shutdown_function ( 'rw_unlock' );
	    $rw = true;
	    $RW_BUTTON = $RW_BUTTON_RO;
	}
	        
	if ( $u == '' ) force_rw();
	else
	{
	    $errors[] = "cannot switch to read-write"
	              . " mode;";
	    $errors[] = "    user $u holds read-write"
	              . " mode;";
	    $m = @filemtime
	        ( "$epm_data/accounts/$aid/" .
		  "+read-write+" );
	    if ( $m === false )
		$errors[] = "    but has never used it";
	    else
	    {
	        $t = time() - $m;
		$errors[] = "    and last used it $t"
		          . " seconds ago";
	    }

	    // The requirer of this file can force
	    // RW by calling force_rw().
	}
    }

?>
