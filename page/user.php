<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Jul 23 14:15:43 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Display and edit user information in:
    //
    //		admin/email/*
    //		admin/users/$uid/*
    //
    // If $_SESSION['EPM_UID'] not set (i.e., if the
    // user is a new user), also assigns $uid and
    // creates:
    //
    //		accounts/$uid
    //	        admin/users/$uid/$uid.info
    //
    //
    // Does this by using a form to collect the follow-
    // ing information:
    //
    //	   uid		Use's ID (short name).
    //	   full_name	Use's full name.
    //	   organization Use's organization.
    //     location     Town, state, country of
    //			organization.
    //
    // and allows emails to be added to the user's
    // account and emails other than the login email
    // to be deleted.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_user.php";

    LOCK ( "admin", LOCK_EX );

    $email = $_SESSION['EPM_EMAIL'];
    $new_user = ( ! isset ( $_SESSION['EPM_UID'] ) );
    $STIME = $_SESSION['EPM_TIME'];
    $IPADDR = $_SESSION['EPM_IPADDR'];
    $edit = ( $new_user ? 'uid-profile' : NULL );
        // One of: NULL (just view), 'emails', 
	// 'uid-profile', 'members', or 'tid-profile'.
	// Set here for GET processing; changed below
	// by POST processing.
    $errors = [];
        // List of error messages to be displayed.
    $post_processed = false;

    // Data:
    //
    //     EPM_USER UID
    //          Currently selected UID.
    //
    //     EPM_USER TID
    //          Currently selected TID.
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
    //	        .info file contents containing:
    //
    //		tid		string
    //		manager		string
    //		members		list of:
    //				  [string,string]
    //				   (uid)  (email)
    //		team_name	string
    //		organization	string
    //		location	string
    //
    //	   EPM_DATA LAST_EDIT
    //		Value of $edit for the last page
    //		served.
    //
    if ( ! isset ( $_SESSION['EPM_USER'] ) )
	$_SESSION['EPM_USER'] = ['UID' => NULL,
	                         'TID' => NULL];
    $user = & $_SESSION['EPM_USER'];
    if ( ! isset ( $user['UID'] ) && ! $new_user )
        $user['UID'] = $_SESSION['EPM_UID'];

    if ( $epm_method == 'GET' )
    {
	$_SESSION['EPM_DATA'] = [];
	$data = & $_SESSION['EPM_DATA'];
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
	        ( 'user', $user['UID'] );

	    $manager_tids = read_tids ( 'manager' );
	    $member_tids = read_tids ( 'member' );
	    $tid = & $user['TID'];
	    if ( ! isset ( $tid )
		 &&
		 count ( $manager_tids ) > 0 )
		$tid = $manager_tids[0];
	    if ( isset ( $tid ) )
		$data['TID-INFO'] = read_info
		    ( 'team', $tid );
	}
    }
    elseif ( isset ( $_POST['user'] )
             &&
	     $_POST['user'] != $user['UID'] )
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
	    $user['UID'] = $new_uid;
	    $data = & $_SESSION['EPM_DATA'];
	    $data['UID-INFO'] = read_info
	        ( 'user', $user['UID'] );
	    $post_processed = true;
	}
    }
    elseif ( isset ( $_POST['team'] )
             &&
	     $_POST['team'] != $user['TID'] )
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
	    $user['TID'] = $new_tid;
	    $data = & $_SESSION['EPM_DATA'];
	    $data['TID-INFO'] = read_info
	        ( 'team', $user['TID'] );
	    $post_processed = true;
	}
    }
    else
	$data = & $_SESSION['EPM_DATA'];

    $uid_info = & $data['UID-INFO'];
    $uid = & $uid_info['uid'];
    $emails = & $uid_info['emails'];

    $uid_editable =
        ( $new_user
	  ||
	  $uid == $_SESSION['EPM_UID'] );

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

    // Check that value can be legally added as an email
    // and set variable to new value.  Return true if
    // value is legal and false if not, and if false is
    // returned, add to $errors and do not set variable.
    //
    function sanitize_email ( & $variable, $value )
    {
        global $errors;

	$value =  trim ( $value );
	if ( $value == "" )
	    return false;
	$svalue = filter_var
	    ( $value, FILTER_SANITIZE_EMAIL );
	if ( $value != $svalue )
	{
	    $errors[] =
	        "Email $value contains characters" .
		" illegal in an email address";
	    return false;
	}
	if ( ! filter_var
		  ( $value,
		    FILTER_VALIDATE_EMAIL ) )
	{
	    $errors[] =
	        "Email $value is not a valid email" .
		" address";
	    return false;
	}
	$variable = $value;
	return true;
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
	    $d = "admin/users/$uid";
	    if ( $uid == '' )
	        /* Do Nothing */;
	    elseif ( ! preg_match
	                ( $epm_name_re, $uid ) )
	        $errors[] = "$uid is not a properly"
		          . " formatted user id";
	    elseif ( is_dir ( "$epm_data/$d" ) )
	        $errors[] = "another account is already"
		          . " using $uid as a User ID";
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
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	@mkdir ( "$epm_data/admin", 02770 );
	@mkdir ( "$epm_data/admin/users", 02770 );
	@mkdir ( "$epm_data/admin/users/$uid",
		 02770 );
	@mkdir ( "$epm_data/admin/email", 02770 );
	$m = umask ( 06 );
	@mkdir ( "$epm_data/accounts", 02771 );
	@mkdir ( "$epm_data/accounts/$uid", 02771 );
	umask ( $m );

	$d = "admin/users/$uid";
	$re = rawurlencode ( $email );
	$f = "admin/email/$re";
	if ( file_exists ( "$epm_data/$f" ) )
	    WARN ( "$f exists when it should not" );
	$items = [ $uid, 0, $STIME ];
	$r = @file_put_contents
	    ( "$epm_data/$f",
	      implode ( ' ', $items ) );
	if ( $r === false )
	    ERROR ( "could not write $f" );
	write_info ( $uid_info );

	$f = "admin/users/$uid/session_id";
	$r = file_put_contents
	    ( "$epm_data/$f", session_id() );
	if ( $r === false )
	    ERROR ( "could not write $f" );
	$fmtime = @filemtime ( "$epm_data/$f" );
	if ( $fmtime === false )
	    ERROR ( "could not stat $f" );
	$_SESSION['EPM_ABORT'] = [$f,$fmtime];

	$r = @file_put_contents
	    ( "$epm_data/login.log",
	      "$uid $email $IPADDR $STIME" .
	      PHP_EOL,
	      FILE_APPEND );
	if ( $r === false )
	    ERROR ( "could not write login.log" );

	$_SESSION['EPM_UID'] = $uid;
	$_SESSION['EPM_AID'] = $uid;
	    // Do this last as it certifies
	    // the EMAIL and .info files exist.
	$edit = NULL;
	$new_user = false;
    }
    elseif ( isset ( $_POST['NO-new-uid'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'new-uid' )
	    exit ( "UNACCEPTABLE HTTP POST" );
	$edit = 'uid-profile';
    }
    elseif ( isset ( $_POST['add_email'] )
             &&
	     isset ( $_POST['new_email'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'emails' )
	    exit ( "UNACCEPTABLE HTTP POST" );

	if ( count ( $emails ) >= 
	     $epm_max_emails )
	    $errors[] = "you already have the maximum"
	              . " limit of $epm_max_emails"
		      . " email address";
    	elseif ( sanitize_email
	         ( $e, $_POST['new_email'] ) )
	{
	    $re = rawurlencode ( $e );
	    $f = "admin/email/$re";
	    if ( is_readable ( "$epm_data/$f" )
	         ||
		 in_array ( $e, $emails ) )
	    {
	        $errors[] =
		    "email address $e is already" .
		    " assigned to some user" .
		    " (maybe you)";
	    }
	    else
	    {
	        $items = [ $uid, 0, $STIME ];
		$r = @file_put_contents
		    ( "$epm_data/$f",
		      implode ( ' ', $items ) );
	        if ( $r === false )
		    ERROR ( "could not write $f" );
	        $emails[] = $e;
		write_info ( $uid_info );
	    }
	}
	$edit = 'emails';
    }
    elseif ( isset ( $_POST['delete_email'] ) )
    {
        if ( ! $uid_editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        if ( $data['LAST_EDIT'] != 'emails' )
	    exit ( "UNACCEPTABLE HTTP POST" );

    	if ( sanitize_email
	         ( $e, $_POST['delete_email'] ) )
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
    div.user-header {
	background-color: var(--bg-dark-green);
	padding: 10px 0px 0px 0px;
	text-align: center;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.email-addresses {
	background-color: var(--bg-green);
	padding: 10px 0px 0px 0px;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.email-addresses * {
	padding-top: var(--pad);
	padding-bottom: var(--pad);
        font-size: var(--large-font-size);
    }
    div.email-addresses button {
        font-size: var(--font-size);
	padding-top: 2px;
	padding-bottom: 2px;
    }
    div.user-profile {
	background-color: var(--bg-dark-green);
	padding: 10px 0px 0px 0px;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    div.user-profile * {
        font-size: var(--large-font-size);
	padding: 5px;
    }
    div.user-profile th {
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
</head>
<body>
<div style='background-color:orange;
	    text-align:center'>
<strong>This Page is Under Re-Construction.</strong>
</div>

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo <<<EOT
	<div class='errors'>
	<strong>
        Errors:
	<div class='indented'>
EOT;
	foreach ( $errors as $value )
	{
	    $hvalue = htmlspecialchars ( $value );
	    echo "<mark>$hvalue</mark><br>";
	}
	echo '</strong></div></div>';
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
    	echo <<<EOT
	<strong>Your Info</strong>
	<br>
	<button type="submit"
	        formmethod='GET'>
		Finish Editing</button>
EOT;
    else
    {
	$aid = $uid;  // To keep epm_list.php happy.
	require "$epm_home/include/epm_list.php";
        $users = read_accounts ( 'user' );
	$options = values_to_options ( $users, $uid );
	echo <<<EOT
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

    if ( $uid_editable ) $uname = 'Your';
    else $uname = $uid;

    if ( $edit == 'emails' )
    {
	echo <<<EOT
	<div class='email-addresses'>
	<strong>Edit Your Email Addresses:</strong>
	<div class='indented'>
EOT;
	$break = '';
	foreach ( $emails as $e )
	{
	    echo $break;
	    $break = '<br>';
	    $he = htmlspecialchars ( $e );
	    if ( $e == $email )
	    {
	        echo ( "<pre>$email</pre>" .
		       " (used for current login)" );
		continue;
	    }
	    echo <<<EOT
	    <form method='POST' action='user.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <pre>$he</pre>
	    <pre>    </pre>
	    <button type='submit'
		    name='delete_email'
		    value='$he'>Delete</button>
	    </form>
EOT;
	}
	if ( count ( $emails ) < $epm_max_emails )
	{
	    $new_email_title =
		 "Add another email address to the" .
		 " account";
	    echo <<<EOT
	    <br>
	    <form method='POST' action='user.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='email' name='new_email'
		   value='' size='40'
		   placeholder='Another Email Address'
		   title='$new_email_title'>
	    <pre>    </pre>
	    <input type='submit'
		   name='add_email' value='Add'>
	    </form>
EOT;
	}

	echo "</div></div>";
    }
    else
    {
	$addresses = emails_to_lines
	    ( $emails, $email );
	echo <<<EOT
	<div class='email-addresses'>
	<strong>$uname Email Addresses:</strong>
	<div class='indented'>
	$addresses
	</div></div>
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
EOT;
?>

</div>
<div style='clear:both'></div>
<div class='terms'>
<?php require "$epm_home/include/epm_terms.html"; ?>
</div>

</body>
</html>
