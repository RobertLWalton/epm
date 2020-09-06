<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Sep  6 17:38:56 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Display and edit user information in:
    //
    //		admin/email/*
    //		admin/users/UID/*
    //		admin/teams/TID/*

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_user.php";

    LOCK ( "admin", LOCK_EX );

    $email = $_SESSION['EPM_EMAIL'];
    $new_user = ( ! isset ( $aid ) );
    $errors = [];
    $warnings = [];
        // Lists of error and warning messages to be
	// displayed.
    $force_rw = false;
        // Set by $_POST['rw'] to ask if RW should be
	// forced.

    if ( ! $new_user )
    {
	require "$epm_home/include/epm_list.php";
        $users = read_accounts ( 'user' );
    }
    elseif ( ! $rw )
        ERROR ( "new user and ! \$rw" );
	// Just checking on index.php.

    // Data:
    //
    //     EPM_USER UID
    //          Currently selected UID.
    //
    //     EPM_USER TID
    //          Currently selected TID.
    //		NULL if no team or new team.
    //
    //     EPM_USER TID-LIST
    //          Currently selected TID-LIST:
    //		  'all' => all tids
    //		  'manager' => tids with $UID as
    //			       a manager
    //		  'member' => tids with $UID as a member
    //
    //	   $data UID-INFO
    //		This is the info of EPM_USER UID,
    //		except when the latter is NULL during
    //		new_user processing, when it is to
    //		info of the not yet extant new user.
    //
    //	        .info file contents containing:
    //
    //		uid		string
    //		emails		list of strings
    //		full_name	string
    //		organization	string
    //		location	string
    //		guests		list of strings
    //
    //	   $data TID-INFO
    //		NULL if no team
    //	        .info file contents containing:
    //
    //		tid		string
    //		manager		string
    //		members		list of strings
    //		team_name	string
    //		organization	string
    //		location	string
    //
    //	   $state
    //		normal
    //		emails (edit $UID == $uid emails)
    //		uid-profile (edit $UID == $uid profile;
    //                       or new user profile)
    //		guests (edit $UID == $uid guests )
    //		members (edit $TID members)
    //		tid-profile (edit $TID profile)
    //		new-uid (ask if new user UID ok)
    //		new-tid (ask if new team TID ok)
    //		new-manager (ask if new team manager ok)
    //
    $uid_edit_states =
        [ 'uid-profile', 'emails', 'guests',
	  'new-uid' ];
    $tid_edit_states =
        [ 'tid-profile', 'members', 'new-tid',
	  'new-manager' ];
 
    // Set up $user.
    //
    if ( ! isset ( $_SESSION['EPM_USER'] ) )
	$_SESSION['EPM_USER'] = ['UID' => NULL,
	                         'TID' => NULL,
				 'TID-LIST' => 'all'];
    $user = & $_SESSION['EPM_USER'];
    $UID = & $user['UID'];
    $TID = & $user['TID'];
    $TID_LIST = & $user['TID-LIST'];
    if ( ! isset ( $UID ) && ! $new_user )
        $UID = $uid;

    // Set up $data, processing GET and those POSTs
    // that set $data.
    //
    $post_processed = true;
    if ( $epm_method == 'GET' )
    {
        if ( $state != 'normal' )
	    ERROR ( "\$state = '$state' != 'normal'" );
	    // Just checking up on index.php.
        if ( $new_user )
	{
	    $data['UID-INFO'] = [
		'uid' => '',
		'emails' => [$email],
		'guests' => [],
		'full_name' => '',
		'organization' => '',
		'location' => ''];
	    $state = 'uid-profile';
	}
	else
	{
	    $data['UID-INFO'] = read_info
	        ( 'user', $UID );
	    if ( isset ( $TID ) )
		$data['TID-INFO'] = read_info
		    ( 'team', $TID );
	}
    }
    elseif ( isset ( $_POST['user'] )
             &&
	     $_POST['user'] != $UID )
    {
        if ( in_array ( $state, $uid_edit_states ) )
	    exit ( "UNACCEPTABLE HTTP POST: UID EDIT" );
        $new_uid = $_POST['user'];
	$f = "admin/users/$new_uid/$new_uid.info";
	if ( ! is_readable ( "$epm_data/$f" ) )
	    $errors[] =
	        "$new_uid is no longer a user id";
	else
	{
	    $UID = $new_uid;
	    $data['UID-INFO'] = read_info
	        ( 'user', $UID );
	}
    }
    elseif ( isset ( $_POST['team'] )
             &&
	     $_POST['team'] != $TID )
    {
        if ( in_array ( $state, $tid_edit_states ) )
	    exit ( "UNACCEPTABLE HTTP POST: TID EDIT" );
        $new_tid = $_POST['team'];
	$f = "admin/teams/$new_tid/$new_tid.info";
	if ( ! is_readable ( "$epm_data/$f" ) )
	    $errors[] =
	        "$new_tid is no longer a team id";
	else
	{
	    $TID = $new_tid;
	    $data['TID-INFO'] = read_info
	        ( 'team', $TID );
	}
    }
    elseif ( isset ( $_POST['tid-list'] )
             &&
	     $_POST['tid-list'] != $TID_LIST )
    {
        $new_tid_list = $_POST['tid-list'];
	if ( ! in_array ( $new_tid_list,
	                  ['all','member','manager'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$TID_LIST = $new_tid_list;
	$TID = NULL;
	$data['TID-INFO'] = NULL;
    }
    elseif ( $state != 'normal' )
        $post_processed = false;
    elseif ( $new_user )
        ERROR ( "new user and normal state" );
	// Just checking
    elseif ( isset ( $_POST['rw'] ) )
    {
	require "$epm_home/include/epm_rw.php";
	if ( count ( $errors ) > 0 )
	    $force_rw = true;
    }
    elseif ( isset ( $_POST['force-rw'] ) )
    {
	require "$epm_home/include/epm_rw.php";
    }
    elseif ( isset ( $_POST['ignore-rw'] ) )
    {
	/* Do Nothing */
    }
    elseif ( isset ( $_POST['create-tid'] ) )
    {
	if ( $rw )
	{
	    $data['TID-INFO'] = [
		'tid' => '',
		'manager' => $aid,
		'members' => [],
		'team_name' => '',
		'organization' => '',
		'location' => ''];
	    $TID = NULL;
	    $state = 'tid-profile';
	}
    }
    else
	$post_processed = false;

    // The above establishes EPM_USER UID, TID,
    // TID-LIST, $TID, $UID, and $TID_LIST,  and
    // $data UID-INFO and TID-INFO before the
    // following is executed.

    $uid_info = & $data['UID-INFO'];
    $tid_info = & $data['TID-INFO'];
    $emails = & $uid_info['emails'];

    $no_team = ( ! isset ( $tid_info ) );
    $new_team = ( ! isset ( $TID ) && ! $no_team );

    // Do some state invariant checking.
    //
    if ( $new_user
         &&
	 $state != 'uid-profile'
	 &&
	 $state != 'new-uid' )
	ERROR ( "new user, bad \$state = $state" );
    if ( $new_team
         &&
	 $state != 'tid-profile'
	 &&
         $state != 'new-tid' )
	ERROR ( "new team, bad \$state = $state" );
    if ( $state != 'normal' )
    {
	if ( ! $new_user && $is_team )
	    ERROR
	        ( "is team and bad \$state = $state" );
	if ( ! $new_user && $uid != $aid )
	    ERROR
	        ( "is guest and bad \$state = $state" );
	if ( ! $rw ) 
	    ERROR
	        ( "! \$rw and bad \$state = $state" );
    }

    if ( ( $state == 'tid-profile' ||
           $state == 'members' ||
	   $state == 'new-tid' )
	 &&
	 ( ! isset ( $tid_info )
	   ||
	   $tid_info['manager'] != $uid ) )
	ERROR ( "not team manger and" .
	        " bad \$state = $state" );

    // Compute in $tids the list of teams in $TID_LIST.
    //
    $tids = [];
    function compute_tids()
    {
        global $tids, $TID_LIST, $UID, $TID,
	       $tid_info, $no_team;

	switch ( $TID_LIST )
	{
	case 'all':
	    $tids = read_accounts ( 'team' );
	    break;
	case 'manager':
	    $tids = read_tids ( $UID, 'manager' );
	    break;
	case 'member':
	    $tids = read_tids ( $UID, 'member' );
	    break;
	}

	if ( count ( $tids ) == 0 )
	{
	    $TID = NULL;
	    $tid_info = NULL;
	    $no_team = true;
	}
	elseif ( ! isset ( $TID )
	         ||
		 ! in_array ( $TID, $tids ) )
	{
	    $TID = $tids[0];
	    $tid_info = read_info ( 'team', $TID );
	    $no_team = false;
	}
    }

    if (    ! $new_user
         && ! in_array ( $state, $tid_edit_states ) )
	compute_tids();

    $uid_editable = false;
        // True if buttons to start editing $uid_info
	// are enabled.
    $tid_editable = false;
        // True if buttons to start editing $tid_info
	// are enabled.
    $tid_creatable = false;
        // True if buttons to create new team are
	// enabled.
    function compute_editable()
    {
        global $state, $rw, $is_team,
	       $UID, $uid, $aid,
	       $tid_info,
	       $uid_editable, $tid_editable,
	       $tid_creatable;
	$uid_editable = false;
	$tid_editable = false;
	$tid_creatable = false;
	if ( $state != 'normal' || ! $rw || $is_team )
	    return;
	if ( $UID == $uid && $aid == $uid )
	    $uid_editable = true;

	if ( isset ( $tid_info )
	     &&
	     $tid_info['manager'] == $uid
	     &&
	     $uid == $aid )
	    $tid_editable = true;

	if ( $uid == $aid )
	    $tid_creatable = true;
    }
    compute_editable();
        // Re-computed after POST processing done.

    // If $uid_editable, run a check that $uid's info
    // and admin/email match.  WARN if not.  Correct
    // if not.
    //
    if ( $epm_method == 'GET' && $uid_editable )
    {
	email_map ( $map );
	$actual = [];
	foreach ( $map as $e => $u )
	{
	    if ( $u == $uid )
	        $actual[] = $e;
	}
	if ( count ( array_diff ( $emails, $actual ) )
	     +
	     count ( array_diff ( $actual, $emails ) )
	     > 0 )
	{
	    WARN ( "$uid info emails !=" .
	           " admin/email emails" );
	    $emails = $actual;
	    write_info ( $uid_info );
	}
    }

    if ( $epm_method == 'GET' || ! $rw )
        /* Do nothing */;
    elseif ( $post_processed )
        /* Do nothing */;
    elseif ( isset ( $_POST['edit'] ) )
    {
	if ( $state != 'normal' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        $edit = $_POST['edit'];
	$check = [
	    'uid-profile' => $uid_editable,
	    'emails' => $uid_editable,
	    'guests' => $uid_editable,
	    'tid-profile' => $tid_editable,
	    'members' => $tid_editable ];
	if ( ! isset ( $check[$edit] )
	     ||
	     ! $check[$edit] )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$state = $edit;
    }
    elseif ( isset ( $_POST['uid-update'] ) )
    {
        if ( $state != 'uid-profile' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$old_uid = $uid_info['uid'];
	copy_info ( 'user', $_POST, $uid_info );
	scrub_info ( 'user', $uid_info, $errors );

	if ( $new_user )
	{
	    $new_uid = $uid_info['uid'];
	    $d = "accounts/$new_uid";
	    if ( $new_uid == '' )
	        /* Do Nothing */;
	    elseif ( ! preg_match
	                ( $epm_name_re, $new_uid ) )
	        $errors[] = "$new_uid is not a properly"
		          . " formatted user id";
	    elseif ( is_dir ( "$epm_data/$d" ) )
	        $errors[] = "another account is already"
		          . " using $new_uid as an"
			  . " Account ID";
	}
	elseif ( $old_uid != $uid_info['uid'] )
	    exit ( "UNACCEPTABLE HTTP POST: UID" );

	if ( count ( $errors ) > 0 )
	    $state = 'uid-profile';
	elseif ( $new_user )
	    $state = 'new-uid';
	else
	{
	    write_info ( $uid_info );
	    $state = 'normal';
	}
    }
    elseif ( isset ( $_POST['new-uid'] ) )
    {
        if ( $state != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$new_uid = $uid_info['uid'];
	if ( is_dir ( "$epm_data/accounts/$new_uid" ) )
	{
	    $errors[] = "another account has just"
	              . " started using $new_uid as an"
		      . " Account ID";
	    $state = 'uid-profile';
	    goto NEW_UID_EXIT;
	}

	@mkdir ( "$epm_data/admin", 02770 );
	@mkdir ( "$epm_data/admin/email", 02770 );
	if ( ! init_email ( $new_uid, $email ) )
	    exit ( "Another user just attached" .
	           " $email to their account;<br>" .
		   " start over with another e-mail" .
		   " address" );

	@mkdir ( "$epm_data/admin/users", 02770 );
	@mkdir ( "$epm_data/admin/users/$new_uid",
		 02770 );
	$m = umask ( 06 );
	@mkdir ( "$epm_data/accounts", 02771 );
	@mkdir ( "$epm_data/accounts/$new_uid", 02771 );
	umask ( $m );

	$STIME = $_SESSION['EPM_TIME'];
	$IPADDR = $_SESSION['EPM_IPADDR'];
	write_info ( $uid_info );
	$UID = $new_uid;
	$uid = $new_uid;

	$d = "admin/users/$uid";
	$log = "$d/$uid.login";
	$browser = $_SERVER['HTTP_USER_AGENT'];
	$browser = preg_replace
	    ( '/\s*\([^\)]*\)\s*/', ' ', $browser );
	$browser = preg_replace
	    ( '/\s+/', ';', $browser );
	$r = @file_put_contents
	    ( "$epm_data/$log",
	      "$STIME $email $IPADDR $browser" .
	      PHP_EOL,
	      FILE_APPEND );
	if ( $r === false )
	    ERROR ( "could not write $log" );

	$mtime = @filemtime ( "$epm_data/$log" );
	if ( $mtime === false )
	    ERROR ( "cannot stat $log" );
	$_SESSION['EPM_ABORT'] = [$log,$mtime];
	while ( time() == $mtime )
	    usleep ( 100000 );
	    // To be sure $mtime is unique.

	$_SESSION['EPM_UID'] = $uid;
	$_SESSION['EPM_AID'] = $uid;
	$_SESSION['EPM_IS_TEAM'] = false;
	    // Do this last as it certifies
	    // the EMAIL and .info files exist.
	$rw = true;
	$is_team = false;

	$new_user = false;
	$aid = $_SESSION['EPM_AID'];
	require "$epm_home/include/epm_list.php";
        $users = read_accounts ( 'user' );
	compute_tids();

	$state = 'normal';

	NEW_UID_EXIT:
	    // goto here on errors
    }
    elseif ( isset ( $_POST['NO-new-uid'] ) )
    {
        if ( $state != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$state = 'uid-profile';
    }
    elseif ( isset ( $_POST['add-email'] )
             &&
	     isset ( $_POST['new-email'] ) )
    {
        if ( $state != 'emails' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$e = trim ( $_POST['new-email'] );
	if ( count ( $emails ) >= 
	     $epm_max_emails )
	    $errors[] = "you already have the maximum"
	              . " limit of $epm_max_emails"
		      . " email address";
    	elseif ( validate_email ( $e, $errors ) )
	{
	    if ( in_array ( $e, $emails )
	         ||
	         ! init_email ( $uid, $e ) )
	    {
	        $errors[] =
		    "email address $e is already" .
		    " assigned to some user" .
		    " (maybe you)";
	    }
	    else
	    {
	        $emails[] = $e;
		write_info ( $uid_info );
	    }
	}
    }
    elseif ( isset ( $_POST['delete-email'] ) )
    {
        if ( $state != 'emails' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$e = trim ( $_POST['delete-email'] );
    	if ( validate_email ( $e, $errors ) )
        {
	    $re = rawurlencode ( $e );
	    $f = "admin/email/$re";
	    $k = array_search ( $e, $emails, true );
	    if ( $e == $email )
	    {
	        $errors[] =
		    "trying to delete email address" .
		    "$e that you used to log in";
	    }
	    elseif ( $k === false )
	    {
	        $errors[] =
		    "trying to delete email address" .
		    "$e that is NOT assigned to you";
	    }
	    else
	    {
	        $c = @file_get_contents
		    ( "$epm_data/$f" );
		if ( $c !== false )
		{
		    $c = trim ( $c );
		    $items = explode ( ' ', $c );
		    if ( $items[0] != $uid )
			WARN ( "UID $uid trying to" .
			       " delete $f which" .
			       " belongs to UID" .
			       " {$items[0]}" );
		    else
			unlink ( "$epm_data/$f" );
		}
		array_splice ( $emails, $k, 1 );
		write_info ( $uid_info );
	    }
	}
    }
    elseif ( isset ( $_POST['add-guest'] )
             &&
	     isset ( $_POST['new-guest'] ) )
    {
        if ( $state != 'guests' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$guests = & $uid_info['guests'];
	$g = trim ( $_POST['new-guest'] );
	if ( $g != '' )
	{
	    $items = explode ( ' ', $g );
	    $gid = $items[0];
	    $f = "admin/users/$gid";
	    if ( $gid == $uid )
	        $errors[] = "you cannot be a guest of"
		          . " yourself";
	    elseif ( ! is_dir ( "$epm_data/$f" ) )
	        $errors[] = "$gid is not a user UID";
	    elseif ( in_array ( $g, $guests ) )
		$errors[] = "`$g' is already listed"
		          . " as a guest";
	    elseif (    count ( $guests )
	             >= $epm_max_guests )
		$errors[] = "you already have the"
		          . " maximum limit of"
			  . " $epm_max_guests guest"
			  . " entries";
	    else
	    {
	        $guests[] = $g;

		$d = "admin/users/$uid";
		$fl = "$d/$gid.login";
		$fi = "$d/$gid.inactive";
		if ( file_exists ( "$epm_data/$fi" ) )
		    rename ( "$epm_data/$fi",
		             "$epm_data/$fl" );
		elseif ( ! file_exists
		               ( "$epm_data/$fl" ) )
		{
		    $r = @file_put_contents
		        ( "$epm_data/$fl", '',
			  FILE_APPEND );
		    if ( $r === false )
		        ERROR ( "cannot write $fl" );
		}
		write_info ( $uid_info );
	    }

	}
    }
    elseif ( isset ( $_POST['delete-guest'] ) )
    {
        if ( $state != 'guests' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$c = trim ( $_POST['delete-guest'] );
	if ( ! preg_match ( '/^\d+$/', $c ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$guests = & $uid_info['guests'];
	if ( $c >= count ( $guests ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$items = explode ( ' ', $guests[$c] );
	$gid = $items[0];
	array_splice ( $guests, $c, 1 );

	$found = false;
	foreach ( $guests as $g )
	{
	    $items = explode ( ' ', $g );
	    if ( $items[0] == $gid )
	    {
	        $found = true;
		break;
	    }
	}

	if ( ! $found )
	{
	    $d = "admin/users/$uid";
	    $fl = "$d/$gid.login";
	    $fi = "$d/$gid.inactive";
	    if ( ! file_exists ( "$epm_data/$fl" ) )
		ERROR ( "$fl does not exist" );
	    rename ( "$epm_data/$fl", "$epm_data/$fi" );
	}

	write_info ( $uid_info );
    }
    elseif ( isset ( $_POST['tid-update'] ) )
    {
        if ( $state != 'tid-profile' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$old_tid = $tid_info['tid'];
	$old_manager = $tid_info['manager'];

	copy_info ( 'team', $_POST, $tid_info );
	scrub_info ( 'team', $tid_info, $errors );

	$new_tid = $tid_info['tid'];
	$new_manager = $tid_info['manager'];
	if ( ! $new_team && $new_tid != $old_tid )
	    exit ( "UNACCEPTABLE HTTP POST: TID" );
	if ( $new_team && $new_manager != $aid )
	    exit ( "UNACCEPTABLE HTTP POST: MANAGER" );
	    
	if ( count ( $errors ) == 0
	     &&
	     $new_tid != $old_tid )
	{
	    $d = "accounts/$new_tid";
	    if ( $new_tid == '' )
	        $errors[] = 'missing team ID';
	    elseif ( ! preg_match
	                ( $epm_name_re, $new_tid ) )
	        $errors[] = "$new_tid is not a properly"
		          . " formatted team ID";
	    elseif ( is_dir ( "$epm_data/$d" ) )
	        $errors[] = "another account is already"
		          . " using $new_tid as an"
			  . " Account ID";
	}

	if ( count ( $errors ) == 0
	     &&
	     $new_manager != $old_manager )
	{
	    $d = "admin/users/$new_manager";
	    if ( $new_manager == '' )
	        $errors[] = 'missing team manager';
	    elseif ( ! preg_match
	                ( $epm_name_re, $new_manager ) )
	        $errors[] = "$new_manager is not a"
		          . " properly formatted user"
			  . " ID";
	    elseif ( ! is_dir ( "$epm_data/$d" ) )
	        $errors[] = "$new_manager is not the ID"
		          . " of a user";
	}

	if ( count ( $errors ) > 0 )
	    $state = 'tid-profile';
	elseif ( $new_team )
	    $state = 'new-tid';
	elseif ( $new_manager != $old_manager )
	    $state = 'new-manager';
	else
	{
	    write_info ( $tid_info );
	    $state = 'normal';
	}

    }
    elseif ( isset ( $_POST['new-tid'] ) )
    {
        if ( $state != 'new-tid' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$TID = $tid_info['tid'];
	$no_team = false;
	$new_team = false;

	@mkdir ( "$epm_data/admin", 02770 );
	@mkdir ( "$epm_data/admin/teams", 02770 );
	@mkdir ( "$epm_data/admin/teams/$TID",
		 02770 );
	$m = umask ( 06 );
	@mkdir ( "$epm_data/accounts", 02771 );
	@mkdir ( "$epm_data/accounts/$TID", 02771 );
	umask ( $m );

	write_info ( $tid_info );

	$items = read_tids ( $aid, 'manager' );
	$items[] = $TID;
	write_tids ( $items, $aid, 'manager' );

	$TID_LIST = 'manager';
	compute_tids();

	$state = 'normal';
    }
    elseif ( isset ( $_POST['new-manager'] ) )
    {
        if ( $state != 'new-manager' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	write_info ( $tid_info );

	$items = read_tids ( $aid, 'manager' );
	$pos = array_search ( $TID, $items );
	if ( $pos !== false )
	    array_splice ( $items, $pos, 1 );
	write_tids ( $items, $aid, 'manager' );

	$mid = $tid_info['manager'];
	$items = read_tids ( $mid, 'manager' );
	$items[] = $TID;
	write_tids ( $items, $mid, 'manager' );

	$TID_LIST = 'all';
	compute_tids();

	$state = 'normal';
    }
    elseif ( isset ( $_POST['NO-new-tid'] ) )
    {
        if ( $state != 'new-tid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$state = 'tid-profile';
    }
    elseif ( isset ( $_POST['NO-new-manager'] ) )
    {
        if ( $state != 'new-manager' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        $tid_info['manager'] = $aid;
	$state = 'tid-profile';
    }
    elseif ( isset ( $_POST['add-member'] )
             &&
	     isset ( $_POST['new-member'] ) )
    {
        if ( $state != 'members' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$members = & $tid_info['members'];
	$m = trim ( $_POST['new-member'] );
	$mmail = NULL;
	if ( strpos ( $m, '@' ) !== false )
	{
	    $mmail = $m;
	    $m = NULL;
	    if ( validate_email ( $mmail, $errors ) )
	    {
	        $uidof = uid_of_email ( $mmail );
		$found = NULL;
		foreach ( $members as $mem )
		{
		    list ( $memuid, $memmail ) =
		        split_member ( $mem );
		    if ( $uidof === $memuid
		         ||
			 $mmail == $memmail )
		    {
			$found = $mem;
			break;
		    }
		}

		if ( isset ( $found ) )
		{
		    if ( $uidof === false )
			$errors[] =
			    "($mmail) is already a" .
			    " member";
		    else
			$errors[] =
			    "$mmail is mail of $uidof" .
			    " and is already a member";
		}
		elseif ( $uidof === false )
		{
		    $items = read_email ( $mmail );
		    if ( count ( $items ) == 0 )
		        $items = ['-'];
		    if ( ! in_array ( $TID, $items ) )
		    {
			$items[] = $TID;
			write_email ( $items, $mmail );
		    }
		}
		else
		   $m = $uidof;
	    }
	}
	elseif ( $m != '' )
	{
	    $f = "admin/users/$m";
	    if ( ! is_dir ( "$epm_data/$f" ) )
	        $errors[] = "$m is not a user UID";
	    else
	    {
		foreach ( $members as $mem )
		{
		    list ( $memuid, $memmail ) =
		        split_member ( $mem );
		    if ( $memuid == $m )
		    {
			$errors[] = "$mem is already"
			          . " a member";
			break;
		    }
		}
	    }
	}

	if ( $m === '' )
	    /* Do Nothing */;
	elseif ( count ( $errors ) > 0 )
	    /* Do Nothing */;
	elseif (    count ( $members )
	         >= $epm_max_members )
	    $errors[] = "you already have the maximum"
	              . " limit of $epm_max_members"
		      . " members";
	else
	{
	    if ( ! isset ( $m ) )
	    {
		$warnings[] = "no user yet has $mmail"
		            . " as an email";
		$members[] = "($mmail)";
	    }
	    else
	    {
		if ( isset ( $mmail ) )
		    $members[] = "$m($mmail)";
		else
		    $members[] = "$m";

		$d = "admin/teams/$TID";
		$fl = "$d/$m.login";
		$fi = "$d/$m.inactive";
		if ( file_exists ( "$epm_data/$fi" ) )
		    rename ( "$epm_data/$fi",
		             "$epm_data/$fl" );
		else
		{
		    $r = @file_put_contents
		        ( "$epm_data/$fl", '',
			  FILE_APPEND );
		    if ( $r === false )
		        ERROR ( "cannot write $fl" );
		}

		$items = read_tids ( $m, 'member' );
		$items[] = $TID;
		write_tids ( $items, $m, 'member' );
	    }

	    write_info ( $tid_info );
	}
    }
    elseif ( isset ( $_POST['delete-member'] ) )
    {
        if ( $state != 'members' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$c = trim ( $_POST['delete-member'] );
	if ( ! preg_match ( '/^\d+$/', $c ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$members = & $tid_info['members'];
	if ( $c >= count ( $members ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	list ( $mid, $mail ) =
	    split_member ( $members[$c] );
	if ( $mid != '' )
	{
	    $d = "admin/teams/$TID";
	    $fl = "$d/$mid.login";
	    $fi = "$d/$mid.inactive";
	    if ( ! file_exists ( "$epm_data/$fl" ) )
	        ERROR ( "$fl does not exist" );
	    rename ( "$epm_data/$fl", "$epm_data/$fi" );

	    $items = read_tids ( $mid, 'member' );
	    $p = array_search ( $TID, $items );
	    if ( $p !== false )
		array_splice ( $items, $p, 1 );
	    write_tids ( $items, $mid, 'member' );
	}
	array_splice ( $members, $c, 1 );
	write_info ( $tid_info );
    }
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    compute_editable();
?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.users, div.teams {
        width: 50%;
	float: left;
	padding: 0px;
    }
    div.user-header, div.team-header {
	padding: var(--pad) 0px 0px 0px;
	text-align: center;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.user-header {
	background-color: var(--bg-dark-green);
    }
    div.team-header {
	background-color: var(--bg-dark-tan);
    }
    div.email-addresses, div.guests, div.members {
	padding: var(--pad) 0px 0px 0px;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.email-addresses, div.guests {
	background-color: var(--bg-green);
    }
    div.members {
	background-color: var(--bg-tan);
    }
    div.email-addresses td, div.guests td,
                            div.members td {
        font-size: var(--large-font-size);
	padding: 2px;
    }
    div.email-addresses button {
        font-size: var(--font-size);
	padding: 2px;
    }
    div.user-profile, div.team-profile {
	padding: var(--pad) 0px 0px 0px;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.user-profile {
	background-color: var(--bg-dark-green);
    }
    div.team-profile {
	background-color: var(--bg-dark-tan);
    }
    div.user-profile *, div.team-profile * {
        font-size: var(--large-font-size);
	padding: 5px;
    }
    div.user-profile th, div.team-profile th {
	text-align: right;
    }
    td {
	font-family: "Courier New", Courier, monospace;
    }

    div.terms {
	border-radius: var(--radius);
	border-collapse: collapse;
    }

</style>

<script>
function KEY_DOWN ( event, id )
{
    if ( event.code == 'Enter' )
    {
        event.preventDefault();
        document.getElementById(id).click();
    }
}
</script>

</head>
<body>

<?php 

    if ( $new_user || $UID == $uid ) $uname = 'Your';
    else $uname = $UID;

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>";
	echo "<strong>Errors:</strong>";
	echo "<div class='indented'>";
	foreach ( $errors as $e )
	{
	    $he = htmlspecialchars ( $e );
	    echo "<pre>$he</pre><br>";
	}
	echo "<br></div></div>";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<strong>Warnings:</strong>";
	echo "<div class='indented'>";
	foreach ( $warnings as $e )
	{
	    $he = htmlspecialchars ( $e );
	    echo "<pre>$he</pre><br>";
	}
	echo "<br></div></div>";
    }

    if ( $force_rw )
        echo <<<EOT
	<div class='errors'>
	<strong>Do you want to force RW
	        (read-write mode)?</strong>
	<form action='user.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit' name='ignore-rw'>
	    NO</button>
	<button type='submit' name='force-rw'>
	    YES</button>
	</form>
	</div>
EOT;

    if ( $state == 'new-uid' )
        echo <<<EOT
	<div class='errors'>
	<strong>You are about to save your user info
	        for the first time.  After doing so,
		you may <b>NOT</b> change your User
		ID, the short name by which others
		will know you.  Do you want to save
		your user info now?</strong>
	<form action='user.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit' name='NO-new-uid'>
	    NO</button>
	<button type='submit' name='new-uid'>
	    YES</button>
	</form>
	</div>
EOT;

    if ( $state == 'new-manager' )
        echo <<<EOT
	<div class='errors'>
	<strong>You are about save team info that
	        changes the manager of this team so
		you will no longer be manager.  You
		will lose the ability to edit team
		info and members.  Do you want to
		save this info now?</strong>
	<form action='user.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit' name='NO-new-manager'>
	    NO</button>
	<button type='submit' name='new-manager'>
	    YES</button>
	</form>
	</div>
EOT;

    if ( $state == 'new-tid' )
        echo <<<EOT
	<div class='errors'>
	<strong>You are about to save the team's info
	        for the first time.  After doing so,
		you may <b>NOT</b> change the team's
		ID, the short name by which others
		will know the team.  Do you want to save
		the team's info now?</strong>
	<form action='user.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit' name='NO-new-tid'>
	    NO</button>
	<button type='submit' name='new-tid'>
	    YES</button>
	</form>
	</div>
EOT;

    if ( count ( $emails ) == 1
	 && ( $uid_editable || $state == 'emails' ) )
        echo <<<EOT
	<div class='warnings'>
        <strong>Its a good idea to add a
	        second email address.</strong>
	</div>
EOT;

    echo <<<EOT
    <div class='manage'>
    <form method='GET' action='user.php'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
EOT;
    if ( ! $new_user )
        echo <<<EOT
	<td>
	<strong title='Login Name'>$lname</strong>
	</td>
EOT;
    if ( $state == 'normal' )
        echo <<<EOT
	<td>
	<strong>Go To</strong>
	<button type="submit"
		formaction="project.php">
		Project</button>
	<button type="submit"
		formaction="manage.php">
		Manage</button>
	<strong>Page</strong>
	</td>
EOT;
    echo <<<EOT
    <td style='text-align:right'>
    <button type='button'
	    onclick='VIEW("view.php")'>
	View Actions</button>
EOT;
    if ( $state == 'normal' )
        echo <<<EOT
	<button type="submit"
	        formaction='logout.php'>
	    Logout</button>
	$RW_BUTTON
	<button type='button' id='refresh'
		onclick='location.replace
		    ("user.php?id=$ID")'>
	    &#8635;</button>
EOT;
    echo <<<EOT
    <button type='button'
            onclick='HELP("user-page")'>
	?</button>
    </td>
    </tr>
    </table>
    </form>
    </div>
EOT;
    echo <<<EOT
    <div class='users'>
    <div class='user-header'>
    <form method='POST' action='user.php'
          id='user-form'>
    <input type='hidden' name='id' value='$ID'>
EOT;
    if ( $state == 'uid-profile' )
    {
        $style = '';
	if ( $new_user )
	    $style = 'style="background-color:yellow"';
    	echo <<<EOT
	<strong>Your Info</strong>
	<br>
	<button type='button'
		onclick='document.getElementById
		    ("uid-profile-update").submit()'
		$style>
		Finish Editing</button>
EOT;
	if ( ! $new_user )
	    echo <<<EOT
	    <button type="submit"
		    formmethod="GET">
		    Cancel Edit</button>
EOT;
    }
    elseif ( $state == 'emails'
             ||
	     $state == 'guests' )
    	echo <<<EOT
	<strong>Your Info</strong>
	<br>
	<button type="submit"
	        formmethod='GET'>
		Finish Editing</button>
EOT;
    elseif ( $state == 'new-uid' )
    	echo <<<EOT
	<strong>Your Info</strong>
EOT;
    else
    {
	$options = values_to_options ( $users, $UID );
	$title = 'Select user whose info you wish'
	       . ' to view';
	echo <<<EOT
	<strong>User</strong>
	<select name='user'
		onchange='document.getElementById
			    ("user-form").submit()'
		title='$title'>
	$options
	</select>
	<strong>Info</strong>
EOT;
	if ( $uid_editable )
	    echo <<<EOT
	    <br>
	    <button type="submit"
		    name='edit' value='uid-profile'>
		    Edit Profile</button>
	    <button type="submit"
		    name='edit' value='emails'>
		    Edit Emails</button>
	    <button type="submit"
		    name='edit' value='guests'>
		    Edit Guests</button>
EOT;
    }
    echo <<<EOT
    </form>
    </div>
EOT;

    if ( $state == 'emails' )
    {
	$rows = emails_to_rows
	    ( $emails, $email, 'delete' );
	echo <<<EOT
	<div class='email-addresses'>
	<form method='POST' action='user.php'>
	<input type='hidden' name='id' value='$ID'>
	<strong>Edit Your Email Addresses:</strong>
	<table class='indented'>
	$rows
EOT;
	if ( count ( $emails ) < $epm_max_emails )
	{
	    $new_email_title =
		 "Add another email address to the" .
		 " account";
	    echo <<<EOT
	    <tr><td>
	    <input type='email' name='new-email'
		   value='' size='40'
		   placeholder='Another Email Address'
		   title='$new_email_title'
		   onkeydown=
		       'KEY_DOWN(event,"add-email")'>
	    <pre>    </pre>
	    <button type='submit'
		    name='add-email'
		    id='add-email'>Add</button>
	    </td></tr>
EOT;
	}

	echo <<<EOT
	</table>
	</form>
	</div>
EOT;
    }
    else
    {
	$act = NULL;
	if ( ! $uid_editable ) $act = 'strip';
	$rows = emails_to_rows
	    ( $emails, $email, $act );
	echo <<<EOT
	<div class='email-addresses'>
	<strong>$uname Email Addresses:</strong>
	<table class='indented'>
	$rows
	</table></div>
EOT;
    }

    $exclude = NULL;
    if ( $new_user && $state != 'new-uid' )
        $exclude = [];
    elseif ( $state == 'uid-profile' )
	$exclude = ['uid'];

    $rows = info_to_rows ( $uid_info, $exclude );
    $h = ( $state == 'uid-profile' ?
	   'Edit Your Profile' :
	   "$uname Profile" );

    if ( $new_user )
	$h = "<strong style='background-color:red'>"
	   . "WARNING:</strong>"
	   . "<mark><strong>"
	   . "You can never change your User ID,"
	   . " the short name by which you will be"
	   . " known, after you acknowledge your"
	   . " initial profile."
	   . "</strong></mark>"
	   . "<br><br><strong>$h:</strong>";
    else
	$h = "<strong>$h:</strong>";

    echo <<<EOT
    <div class='user-profile'>
    <form method='POST' action='user.php'
	  id='uid-profile-update'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='uid-update'>
    $h<br>
    <table>
    $rows
    </table>
    </form>
    </div>
EOT;

    $guests = & $uid_info['guests'];
    if ( $state == 'guests' )
    {
	$rows = guests_to_rows ( $guests, 'delete' );
	echo <<<EOT
	<div class='guests'>
	<form method='POST'
	      action='user.php'>
	<input type='hidden'
	       name='id' value='$ID'>
	<strong>Edit Your Guests:</strong>
	<table class='indented'>
	$rows
EOT;
	if ( count ( $guests ) < $epm_max_guests )
	{
	    echo <<<EOT
	    <tr><td>
	    <input type='text'
		   name='new-guest'
		   value='' size='40'
		   placeholder='New guest UID'
		   title='Give user ID of another guest'
		   onkeydown=
		       'KEY_DOWN
			  (event,"add-guest")'>
	    <pre>    </pre>
	    <button type='submit'
		    name='add-guest'
		    id='add-guest'>
		    Add</button>
	    </td></tr>
EOT;
	}
	echo <<<EOT
	</table>
	</form>
	</div>
EOT;
    }
    elseif ( count ( $guests ) > 0 )
    {
	$rows = guests_to_rows ( $guests );
	echo <<<EOT
	<div class='guests '>
	<strong>Guests:</strong>
	<table class='indented'>
	$rows
	</table>
	</div>
EOT;
    }

    echo <<<EOT
    </div>
EOT;

    // Team Section

    if ( ! $new_user )
    {
	echo <<<EOT
	<div class='teams'>
	<div class='team-header'>
EOT;
	if ( in_array ( $state, $tid_edit_states ) )
	{
	    $tname = ( $new_team ? 'New' : $TID );
	    echo <<<EOT
	    <strong>$tname Team Info</strong>
EOT;
	}
	else
	{
	    $all_select = '';
	    $manager_select = '';
	    $member_select = '';

	    switch ( $TID_LIST )
	    {
	    case 'all':
		$all_select = 'selected';
		break;
	    case 'manager':
		$manager_select = 'selected';
		break;
	    case 'member':
		$member_select = 'selected';
		break;
	    }

	    $title = 'Select list from which you will'
	           . ' select a team';
	    echo <<<EOT
	    <form method='POST' action='user.php'
		  id='tid-list-form'>
	    <input type='hidden' name='id' value='$ID'>
	    <strong>Select Team List:</strong>
	    <select name='tid-list'
		    onchange='document.getElementById
			("tid-list-form").submit()'
		    title='$title'>
	    <option value='all' $all_select>
		all teams</option>
	    <option value='manager' $manager_select>
		teams of which user is the manager
		</option>
	    <option value='member' $member_select>
		teams on which user is a member</option>
	    </select>
	    </form>
	    <div style='float:right'>
	    <button type='button'
		    onclick='HELP("teams")'>
		?</button>

	    </div>
	    <br>
EOT;
	    if ( count ( $tids ) == 0 )
		echo <<<EOT
		<strong>There are NO teams in this
			team list.</strong>
EOT;
	    else
	    {
		$tid_options =
		    values_to_options ( $tids, $TID );
		$title = 'Select team whose info you'
		       . ' wish to view';
		echo <<<EOT
		<strong>Team</strong>
		<form method='POST' action='user.php'
		      id='team-form'>
		<input type='hidden' name='id'
		       value='$ID'>
		<select
		    name='team'
		    onchange='document.getElementById
				("team-form").submit()'
		    title='$title'>
		$tid_options
		</select>
		<strong>Info</strong>
EOT;
		if ( $tid_editable )
		    echo <<<EOT
		    <br>
		    <button type="submit"
			    name='edit'
			    value='tid-profile'>
			    Edit Profile</button>
		    <button type="submit"
			    name='edit' value='members'>
			    Edit Members</button>
EOT;
		echo <<<EOT
		</form>
EOT;
	    }
	    if ( $tid_creatable )
		echo <<<EOT
		<br>
		<form method='POST' action='user.php'>
		<input type='hidden'
		       name='id' value='$ID'>
		<button type='submit' name='create-tid'>
		    Create a New Team</button>
		</form>
EOT;
	}

	if ( $state == 'tid-profile' )
	{
	    $style = '';
	    if ( $new_team )
		$style =
		    'style="background-color:yellow"';
	    $tname = ( isset ( $TID ) ? $TID : 'New' );
	    echo <<<EOT
	    <form method='POST' action='user.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <br>
	    <button type='button'
		    onclick='document.getElementById
			("tid-profile-update").submit()'
		    $style>
		    Finish Editing</button>
	    <button type="submit"
		    formmethod="GET">
		    Cancel Edit</button>
	    </form>
EOT;
	}
	elseif ( $state == 'members' )
	    echo <<<EOT
	    <form method='POST' action='user.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <br>
	    <button type="submit"
		    formmethod='GET'>
		    Finish Editing</button>
	    </form>
EOT;
	echo <<<EOT
	</div>
EOT;
	if ( isset ( $tid_info ) )
	{
	    $members = & $tid_info['members'];
	    if ( $state == 'members' )
	    {
		$rows = members_to_rows
		    ( $members, 'delete' );
		echo <<<EOT
		<div class='members'>
		<form method='POST'
		      action='user.php'>
		<input type='hidden'
		       name='id' value='$ID'>
		<strong>Edit Your Members:</strong>
		<table class='indented'>
		$rows
EOT;
		if (   count ( $members )
		     < $epm_max_members )
		{
		    $title = 'Give email or user ID of'
		           . ' new team member;' . "\n"
			   . 'You can give email even'
			   . ' if team member does not'
			   . ' yet have an account';

		    $holder =
			 "New member UID or EMAIL";
		    echo <<<EOT
		    <tr><td>
		    <input type='text'
			   name='new-member'
			   value='' size='40'
			   placeholder='$holder'
			   title='$title'
			   onkeydown=
			       'KEY_DOWN
			          (event,"add-member")'>
		    <pre>    </pre>
		    <button type='submit'
			    name='add-member'
			    id='add-member'>
			    Add</button>
		    </td></tr>
EOT;
		}
		echo <<<EOT
		</table>
		</form>
		</div>
EOT;
	    }
	    elseif ( count ( $members ) > 0 )
	    {
		$rows = members_to_rows
		    ( $members );
		echo <<<EOT
		<div class='members '>
		<strong>Members:</strong>
		<table class='indented'>
		$rows
		</table>
		</div>
EOT;
	    }
	    else
		echo <<<EOT
		<div class='members '>
		<strong>Members:</strong>
		<div class='indented'>
		<strong>To Be Determined</strong>
		</div>
		</div>
EOT;
	    $exclude = NULL;
	    if ( $new_team && $state != 'new-tid' )
		$exclude = ['manager'];
	    elseif ( $state == 'tid-profile' )
		$exclude = ['tid'];

	    $rows = info_to_rows
		( $tid_info, $exclude );
	    $h = ( $new_team ?
		       'Edit New Team Profile' :
		   $state == 'tid-profile' ?
		       "Edit $TID Profile" :
		   "$TID Profile" );

	    if ( $new_team )
		$h = "<strong"
		   . " style="
		   . "   'background-color:red'>"
		   . "WARNING:</strong>"
		   . "<mark><strong>"
		   . "You can never change the"
		   . " Team ID, the short name by"
		   . " which the team will be"
		   . " known, after you"
		   . " acknowledge the team's"
		   . " initial profile."
		   . "</strong></mark>"
		   . "<br><br><strong>$h:</strong>";
	    else
		$h = "<strong>$h:</strong>";

	    echo <<<EOT
	    <div class='team-profile'>
	    <form method='POST' action='user.php'
		  id='tid-profile-update'>
	    <input type='hidden'
		    name='id' value='$ID'>
	    <input type='hidden' name='tid-update'>
	    $h<br>
	    <table>
	    $rows
	    </table>
	    </form>
	    </div>
EOT;
	}

	echo <<<EOT
	</div>
EOT;
    }

?>

<div style='clear:both'></div>
<div class='terms'>
<?php require "$epm_home/include/epm_terms.html"; ?>
</div>

</body>
</html>
