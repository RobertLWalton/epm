<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jul 29 05:31:55 EDT 2020

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

    require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_user.php";

    LOCK ( "admin", LOCK_EX );

    $email = $_SESSION['EPM_EMAIL'];
    $new_user = ( ! isset ( $_SESSION['EPM_UID'] ) );
    $edit = ( $new_user ? 'uid-profile' : NULL );
        // One of: NULL (just view), 'emails', 
	// 'uid-profile', 'members', or 'tid-profile'.
	// Set here for GET processing; changed below
	// by POST processing.
    $errors = [];
    $warnings = [];
        // Lists of error and warning messages to be
	// displayed.

    if ( ! $new_user )
    {
	$aid = $_SESSION['EPM_AID'];
	require "$epm_home/include/epm_list.php";
        $users = read_accounts ( 'user' );
    }

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
    //		  'manager' => manager tids of UID
    //		  'member' => member tids of UID
    //
    //	   EPM_DATA UID-INFO
    //	        .info file contents containing:
    //
    //		uid		string
    //		emails		list of strings
    //		full_name	string
    //		organization	string
    //		location	string
    //
    //	   EPM_DATA TID-INFO
    //		NULL if no team
    //	        .info file contents containing:
    //
    //		tid		string
    //		manager		string
    //		members		uid(email)
    //		team_name	string
    //		organization	string
    //		location	string
    //
    //	   EPM_DATA LAST_EDIT
    //		Value of $edit for the last page
    //		served.
 
    // Set up $user.
    //
    if ( ! isset ( $_SESSION['EPM_USER'] ) )
	$_SESSION['EPM_USER'] = ['UID' => NULL,
	                         'TID' => NULL,
				 'TID-LIST' => 'all'];
    $user = & $_SESSION['EPM_USER'];
    $uid = & $user['UID'];
    $tid = & $user['TID'];
    $tid_list = & $user['TID-LIST'];
    if ( ! isset ( $uid ) && ! $new_user )
        $uid = $_SESSION['EPM_UID'];

    // Set up $data.
    //
    if ( $epm_method == 'GET' )
	$_SESSION['EPM_DATA'] = [];
    $data = & $_SESSION['EPM_DATA'];
    $post_processed = true;
    if ( $epm_method == 'GET' )
    {
        if ( $new_user )
	{
	    $data['UID-INFO'] = [
		'uid' => '',
		'emails' => [$email],
		'full_name' => '',
		'organization' => '',
		'location' => ''];
	}
	else
	{
	    $data['UID-INFO'] = read_info
	        ( 'user', $uid );
	    if ( isset ( $tid ) )
		$data['TID-INFO'] = read_info
		    ( 'team', $tid );
	}
    }
    elseif ( isset ( $_POST['user'] )
             &&
	     $_POST['user'] != $uid )
    {
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $new_user )
	    exit ( "UNACCEPTABLE HTTP POST" );

        $new_uid = $_POST['user'];
	$f = "admin/users/$new_uid/$new_uid.info";
	if ( ! is_readable ( "$epm_data/$f" ) )
	    $errors[] =
	        "$new_uid is no longer a user id";
	else
	{
	    $uid = $new_uid;
	    $data['UID-INFO'] = read_info
	        ( 'user', $uid );
	}
    }
    elseif ( isset ( $_POST['team'] )
             &&
	     $_POST['team'] != $tid )
    {
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $new_user )
	    exit ( "UNACCEPTABLE HTTP POST" );

        $new_tid = $_POST['team'];
	$f = "admin/teams/$new_tid/$new_tid.info";
	if ( ! is_readable ( "$epm_data/$f" ) )
	    $errors[] =
	        "$new_tid is no longer a team id";
	else
	{
	    $tid = $new_tid;
	    $data['TID-INFO'] = read_info
	        ( 'team', $tid );
	}
    }
    elseif ( isset ( $_POST['tid-list'] )
             &&
	     $_POST['tid-list'] != $tid_list )
    {
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $new_user )
	    exit ( "UNACCEPTABLE HTTP POST" );

        $new_tid_list = $_POST['tid-list'];
	if ( ! in_array ( $new_tid_list,
	                  ['all','member','manager'],
			  true ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$tid_list = $new_tid_list;
	$tid = NULL;
	$data['TID-INFO'] = NULL;
    }
    elseif ( isset ( $_POST['create-tid'] ) )
    {
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $new_user )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$data['TID-INFO'] = [
	    'tid' => '',
	    'manager' => $aid,
	    'members' => [],
	    'team_name' => '',
	    'organization' => '',
	    'location' => ''];
	$tid = NULL;
	$edit = 'tid-profile';
    }
    else
	$post_processed = false;

    // The above establishes $uid_info, $tid_info,
    // and $tid-list before the following is executed.

    $uid_info = & $data['UID-INFO'];
    $tid_info = & $data['TID-INFO'];
    $emails = & $uid_info['emails'];

    $uid_editable = ( $new_user || $uid == $aid );

    $no_team = ( ! isset ( $tid_info ) );
    $new_team = ( ! isset ( $tid ) && ! $no_team );

    // Compute list of teams in $tid_list.
    //
    function compute_tids ( $tid_list )
    {
        $uid = $_SESSION['EPM_UID'];
	switch ( $tid_list )
	{
	case 'all':
	    return read_accounts ( 'team' );
	case 'manager':
	    return read_tids ( $uid, 'manager' );
	case 'member':
	    return read_tids ( $uid, 'member' );
	}
    }

    if ( ! $new_user && ! $new_team )
    {
	$tids = compute_tids ( $tid_list );

	if ( $no_team
	     &&
	     count ( $tids ) > 0 )
	{
	    $tid = $tids[0];
	    $tid_info = read_info ( 'team', $tid );
	    $no_team = false;
	}
    }

    if ( $epm_method == 'GET' && ! $new_user )
    {
        if ( $uid != $user['UID'] )
	    ERROR ( "bad {$user['UID']} info uid" );

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
	    if ( $uid_editable )
		write_info ( $uid_info );
	}
    }

    if ( $epm_method == 'GET' )
    {
	// Do nothing.  Display user info or edit
	// new user profile.
    }
    elseif ( isset ( $_POST['edit'] ) )
    {
        $edit = $_POST['edit'];
	if ( ! in_array
	          ( $edit, ['emails','uid-profile',
		            'members', 'tid-profile'],
	                   true ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
    }
    elseif ( isset ( $_POST['uid-update'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'uid-profile' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$old_uid = $uid;
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
	elseif ( $uid != $old_uid )
	    exit ( "UNACCEPTABLE HTTP POST: UID" );

	if ( count ( $errors ) > 0 )
	    $edit = 'uid-profile';
	elseif ( $new_user )
	    $edit = 'new-uid';
	else
	{
	    write_info ( $uid_info );
	    $edit = NULL;
	}
    }
    elseif ( isset ( $_POST['new-uid'] ) )
    {
        if ( $data['LAST_EDIT'] != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$uid = $uid_info['uid'];
	@mkdir ( "$epm_data/admin", 02770 );
	@mkdir ( "$epm_data/admin/users", 02770 );
	@mkdir ( "$epm_data/admin/users/$uid",
		 02770 );
	@mkdir ( "$epm_data/admin/email", 02770 );
	$m = umask ( 06 );
	@mkdir ( "$epm_data/accounts", 02771 );
	@mkdir ( "$epm_data/accounts/$uid", 02771 );
	umask ( $m );

	$STIME = $_SESSION['EPM_TIME'];
	$IPADDR = $_SESSION['EPM_IPADDR'];

	if ( ! init_email ( $uid, $email ) )
	    ERROR ( "new user init_email" .
	            " ( $uid, $email ) failed" );
	write_info ( $uid_info );

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

	$_SESSION['EPM_UID'] = $uid;
	$_SESSION['EPM_AID'] = $uid;
	$_SESSION['EPM_IS_TEAM'] = false;
	$_SESSION['EPM_RW'] = true;
	    // Do this last as it certifies
	    // the EMAIL and .info files exist.
	$edit = NULL;
	$new_user = false;
	$aid = $_SESSION['EPM_AID'];
	require "$epm_home/include/epm_list.php";
        $users = read_accounts ( 'user' );
	$tids = compute_tids ( $tid_list );
    }
    elseif ( isset ( $_POST['NO-new-uid'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$edit = 'uid-profile';
    }
    elseif ( isset ( $_POST['add-email'] )
             &&
	     isset ( $_POST['new-email'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'emails' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$e = trim ( $_POST['new-email'] );
	if ( count ( $emails ) >= 
	     $epm_max_emails )
	    $errors[] = "you already have the maximum"
	              . " limit of $epm_max_emails"
		      . " email address";
    	elseif ( validate_email ( $e, $errors ) )
	{
	    if ( in_array ( $e, $emails, true )
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
	$edit = 'emails';
    }
    elseif ( isset ( $_POST['delete-email'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'emails' )
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
	$edit = 'emails';
    }
    elseif ( isset ( $_POST['tid-update'] ) )
    {
        if ( $data['LAST_EDIT'] != 'tid-profile' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$old_tid = $tid_info['tid'];
	$old_manager = $tid_info['manager'];
	if ( $old_manager != $aid )
	    exit ( "UNACCEPTABLE HTTP POST" );

	copy_info ( 'team', $_POST, $tid_info );
	scrub_info ( 'team', $tid_info, $errors );

	$new_tid = $tid_info['tid'];
	$new_manager = $tid_info['manager'];
	if ( ! $new_team && $new_tid != $old_tid )
	    exit ( "UNACCEPTABLE HTTP POST" );
	if ( $new_team && $new_manager != $aid )
	    exit ( "UNACCEPTABLE HTTP POST" );
	    
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
	    $edit = 'tid-profile';
	elseif ( $new_team )
	    $edit = 'new-tid';
	elseif ( $new_manager != $old_manager )
	    $edit = 'new-manager';
	else
	{
	    write_info ( $tid_info );
	    $edit = NULL;
	}

    }
    elseif ( isset ( $_POST['new-tid'] ) )
    {
        if ( $data['LAST_EDIT'] != 'new-tid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $tid_info['manager'] != $aid )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$tid = $tid_info['tid'];
	$no_team = false;
	$new_team = false;

	@mkdir ( "$epm_data/admin", 02770 );
	@mkdir ( "$epm_data/admin/teams", 02770 );
	@mkdir ( "$epm_data/admin/teams/$tid",
		 02770 );
	$m = umask ( 06 );
	@mkdir ( "$epm_data/accounts", 02771 );
	@mkdir ( "$epm_data/accounts/$tid", 02771 );
	umask ( $m );

	write_info ( $tid_info );

	$items = read_tids ( $aid, 'manager' );
	$items[] = $tid;
	write_tids ( $items, $aid, 'manager' );

	$tid_list = 'manager';
	$tids = compute_tids ( $tid_list );
	$edit = NULL;
    }
    elseif ( isset ( $_POST['new-manager'] ) )
    {
        if ( $data['LAST_EDIT'] != 'new-manager' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	$tid = $tid_info['tid'];

	write_info ( $tid_info );

	$items = read_tids ( $aid, 'manager' );
	$pos = array_search ( $tid, $items );
	if ( $pos !== false )
	    array_splice ( $items, $pos, 1 );
	write_tids ( $items, $aid, 'manager' );

	$mid = $tid_info['manager'];
	$items = read_tids ( $mid, 'manager' );
	$items[] = $tid;
	write_tids ( $items, $mid, 'manager' );

	$tid_list = 'all';
	$tids = compute_tids ( $tid_list );
	$edit = NULL;
    }
    elseif ( isset ( $_POST['NO-new-tid'] ) )
    {
        if ( $data['LAST_EDIT'] != 'new-tid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $tid_info['manager'] != $aid )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$edit = 'tid-profile';
    }
    elseif ( isset ( $_POST['NO-new-manager'] ) )
    {
        if ( $data['LAST_EDIT'] != 'new-manager' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        $tid_info['manager'] = $aid;
	$edit = 'tid-profile';
    }
    elseif ( isset ( $_POST['add-member'] )
             &&
	     isset ( $_POST['new-member'] ) )
    {
        if ( $data['LAST_EDIT'] != 'members' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $tid_info['manager'] != $aid )
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
		    if ( ! in_array
		              ( $tid, $items, true ) )
		    {
			$items[] = $tid;
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
	elseif ( count ( $members ) >= 6 )
	    $errors[] = "you already have the maximum"
	              . " limit of 6 members";
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

		$d = "admin/teams/$tid";
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
		$items[] = $tid;
		write_tids ( $items, $m, 'member' );
	    }

	    write_info ( $tid_info );
	}
	$edit = 'members';
    }
    elseif ( isset ( $_POST['delete-member'] ) )
    {
        if ( $data['LAST_EDIT'] != 'members' )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $tid_info['manager'] != $aid )
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
	    $d = "admin/teams/$tid";
	    $fl = "$d/$mid.login";
	    $fi = "$d/$mid.inactive";
	    if ( ! file_exists ( "$epm_data/$fl" ) )
	        ERROR ( "$fl does not exist" );
	    rename ( $fl, $fi );

	    $items = read_tids ( $mid, 'member' );
	    $p = array_search ( $tid, $items );
	    if ( $p !== false )
		array_splice ( $items, $p, 1 );
	    write_tids ( $items, $mid, 'member' );
	}
	array_splice ( $members, $c, 1 );
	write_info ( $tid_info );

	$edit = 'members';
    }
    elseif ( ! $post_processed )
	exit ( 'UNACCEPTABLE HTTP POST' );

    $data['LAST_EDIT'] = $edit;

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
    div.email-addresses, div.members {
	padding: var(--pad) 0px 0px 0px;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.email-addresses {
	background-color: var(--bg-green);
    }
    div.members {
	background-color: var(--bg-tan);
    }
    div.email-addresses td {
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
<div style='background-color:orange;
	    text-align:center'>
<strong>This Page is Under Re-Construction.</strong>
</div>

<?php 

    $editing_user = false;
    if ( $uid_editable ) $uname = 'Your';
    else $uname = $uid;

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

    if ( $edit == 'new-uid' )
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

    if ( $edit == 'new-manager' )
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

    if ( $edit == 'new-tid' )
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

    if ( $uid_editable && count ( $emails ) == 1
                       && ( ! isset ( $edit )
		            ||
			    $edit == 'emails' ) )
        echo <<<EOT
	<div class='warnings'>
        <strong>Its a good idea to add a
	        second email address.
	</div>
EOT;

    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
EOT;
    if ( ! isset ( $edit ) )
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
	<pre>   </pre>
	<button type="submit"
	        formaction='logout.php'>
	    Logout</button>
	</td>
EOT;
    echo <<<EOT
    <td style='text-align:right'>
    <button type='button'
	    onclick='VIEW("view.php")'>
	View Users, Projects, and Problems</button>
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
    if ( $edit == 'uid-profile' )
    {
        $editing_user = true;
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
    elseif ( $edit == 'emails' )
    {
        $editing_user = true;
    	echo <<<EOT
	<strong>Your Info</strong>
	<br>
	<button type="submit"
	        formmethod='GET'>
		Finish Editing</button>
EOT;
    }
    elseif ( isset ( $edit ) )
    	echo <<<EOT
	<strong>$uname Info</strong>
EOT;
    else
    {
	$options = values_to_options ( $users, $uid );
	echo <<<EOT
	<strong>User</strong>
	<select name='user'
		onchange='document.getElementById
			    ("user-form").submit()'>
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
EOT;
    }
    echo <<<EOT
    </form>
    </div>
EOT;

    if ( $edit == 'emails' )
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
	<strong>$uname Your Email Addresses:</strong>
	<table class='indented'>
	$rows
	</table></div>
EOT;
    }

    $exclude = NULL;
    if ( $new_user ) $exclude = [];
    elseif ( $edit == 'uid-profile' )
	$exclude = ['uid'];

    $rows = info_to_rows ( $uid_info, $exclude );
    $h = ( $edit == 'uid-profile' ?
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
    </div>
EOT;

    // Team Section

    if ( $new_user )
	/* Do Nothing */;
    elseif ( $editing_user && $no_team )
	/* Do Nothing */;
    else
    {
	echo <<<EOT
	<div class='teams'>
	<div class='team-header'>
EOT;
	if ( isset ( $edit ) || $new_team )
	{
	    $tname = ( $new_team ? 'New' : $tid );
	    echo <<<EOT
	    <strong>$tname Team Info</strong>
EOT;
	}
	else
	{
	    $all_select = '';
	    $manager_select = '';
	    $member_select = '';
	    $tid_editable =
		( ! $no_team
		  &&
		  $tid_info['manager'] == $aid );

	    switch ( $tid_list )
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

	    echo <<<EOT
	    <form method='POST' action='user.php'
		  id='tid-list-form'>
	    <input type='hidden' name='id' value='$ID'>
	    <strong>Select Team List:</strong>
	    <select name='tid-list'
		    onchange='document.getElementById
			("tid-list-form").submit()'>
	    <option value='all' $all_select>
		all teams</option>
	    <option value='manager' $manager_select>
		teams of which you are the manager
		</option>
	    <option value='member' $member_select>
		teams on which you are a member</option>
	    </select>
	    </form>
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
		    values_to_options ( $tids, $tid );
		echo <<<EOT
		<form method='POST' action='user.php'
		      id='team-form'>
		<input type='hidden' name='id'
		       value='$ID'>
		<select
		     name='team'
		     onchange='document.getElementById
				("team-form").submit()'>
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
	    echo <<<EOT
	    <br>
	    <form method='POST' action='user.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <button type='submit' name='create-tid'>
		Create a New Team</button>
	    </form>
EOT;
	}

	if ( $edit == 'tid-profile' )
	{
	    $style = '';
	    if ( $new_team )
		$style =
		    'style="background-color:yellow"';
	    $tname = ( isset ( $tid ) ? $tid : 'New' );
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
	elseif ( $edit == 'members' )
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
	    if ( $edit == 'members' )
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
		if ( count ( $members ) < 6 )
		{
		    $title =
			 "Add another member to" .
			 " the team";
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
	    if ( $new_team )
		$exclude = ['manager'];
	    elseif ( $edit == 'tid-profile' )
		$exclude = ['tid'];

	    $rows = info_to_rows
		( $tid_info, $exclude );
	    $h = ( $new_team ?
		       'Edit New Team Profile' :
		   $edit == 'tid-profile' ?
		       "Edit $tid Profile" :
		   "$tid Profile" );

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
