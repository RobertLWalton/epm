<?php

    // File:	epm_rw.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Sep  6 09:57:15 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Upon receiving a POST with rw='MODE', require
    // this file.  If mode change cannot be
    // accomplished, appends messages to $errors.
    //
    // Upon receiving a POST with force-rw=..., require
    // this file.  Mode, which must be read-only,
    // will be changed to read-write and $errors will
    // be left untouched.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $aid ) )
	exit ( 'ACCESS ERROR: $aid not set' );
    if ( ! isset ( $rw ) )
	exit ( 'ACCESS ERROR: $rw not set' );
    if ( ! isset ( $is_team ) )
	exit ( 'ACCESS ERROR: $is_team not set' );

    // WARNING: $uid may not be EPM_UID because this is
    //		called by user.php.

    if ( isset ( $_POST['rw'] ) )
	$new_rw = $_POST['rw'];
    elseif ( isset ( $_POST['force-rw'] ) )
        $new_rw = 'rw';
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( ! $is_team )
	exit ( 'UNACCEPTABLE HTTP POST: NOT TEAM' );

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
	register_shutdown_function ( 'rw_unlock' );

	$u = trim ( fread ( $rw_handle, 1000 ) );
	if ( $u == '' || isset ( $_POST['force-rw'] ) )
	{
	    rewind ( $rw_handle );
	    ftruncate ( $rw_handle, 0 );
	    fwrite ( $rw_handle, $uid );
	    $rw = true;
	    $RW_BUTTON = $RW_BUTTON_RO;
	}
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
	}
    }

?>
