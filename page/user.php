<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jul 22 13:22:16 EDT 2020

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

    $email = $_SESSION['EPM_EMAIL'];
    $new_user = ( ! isset ( $_SESSION['EPM_UID'] ) );
    $STIME = $_SESSION['EPM_TIME'];
    $IPADDR = $_SESSION['EPM_IPADDR'];
    $edit = ( $new_user ? 'profile' : NULL );
        // One of: NULL (just view), 'emails', or
	// 'profile'.  Set here for GET processing;
	// changed below by POST processing.
    $errors = [];
        // List of error messages to be displayed.
    $post_processed = false;

    // Data:
    //
    //     EPM_USER UID
    //          Currently selected UID.
    //
    //	   EPM_DATA INFO
    //	        .info file contents containing:
    //
    //		uid		string
    //		emails		list of strings
    //		full_name	string
    //		organization	string
    //		location	string
    //
    //	   EPM_DATA EDIT
    //		Value of $edit for the last page
    //		served.
    //
    if ( ! isset ( $_SESSION['EPM_USER'] ) )
	$_SESSION['EPM_USER']['UID'] = NULL;
    $user = & $_SESSION['EPM_USER'];
    if ( ! isset ( $user['UID'] ) && ! $new_user )
        $user['UID'] = $_SESSION['EPM_UID'];

    if ( $epm_method == 'GET' )
    {
	$_SESSION['EPM_DATA'] = [];
	$data = & $_SESSION['EPM_DATA'];
        if ( $new_user )
	{
	    $data['INFO'] = [
		'uid' => '',
		'emails' => [$email],
		'full_name' => '',
		'organization' => '',
		'location' => ''];
	}
	else
	    $data['INFO'] = read_info
	        ( 'user', $user['UID'] );
    }
    elseif ( isset ( $_POST['user'] )
             &&
	     $_POST['user'] != $user['UID'] )
    {
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
	    $data['INFO'] = read_info
	        ( 'user', $user['UID'] );
	    $post_processed = true;
	}
    }
    else
	$data = & $_SESSION['EPM_DATA'];

    $info = & $data['INFO'];
    $uid = & $info['uid'];
    $emails = & $info['emails'];
    $full_name = & $info['full_name'];
    $organization = & $info['organization'];
    $location = & $info['location'];

    $editable =
        ( $new_user
	  ||
	  $uid == $_SESSION['EPM_UID'] );

    LOCK ( "admin", LOCK_EX );

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
	    if ( $editable )
		write_info ( $info );
	}
    }

    // Sanitize non-email form entries and set variable
    // to new value.  If error, add to $errors and do
    // not change variable.
    //
    // $name is as in $_POST[$name], $form_name is as in
    // text of form, $min_length is min UNICODE charac-
    // ters in value.
    //
    function sanitize
        ( & $variable, $name, $form_name, $min_length )
    {
        global $errors;

        if ( ! isset ( $_POST[$name] ) )
	{
	    $errors[] = "must set $form_name";
	    return;
	}
	$value = trim ( $_POST[$name] );
	if ( $value == '' )
	{
	    $errors[] = "must set $form_name";
	    return;
	}
	if (   strlen ( utf8_decode ( $value ) )
	     < $min_length )
	     // Note, grapheme_strlen is not available
	     // because we do not assume intl extension.
	{
	    $errors[] =
	        "$form_name is too short; retry";
	    return;
	}
	$variable = $value;
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
	if ( ! in_array ( $edit, ['emails','profile'],
	                  true ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
	if ( isset ( $data['LAST_EDIT'] ) )
	    exit ( "UNACCEPTABLE HTTP POST" );
    }
    elseif ( isset ( $_POST['update'] ) )
    {
        if ( ! $editable )
	    exit ( "UNACCEPTABLE HTTP POST" );
        $update = $_POST['update'];
	if ( ! in_array ( $update, ['check','finish'],
	                  true ) )
	    exit ( "UNACCEPTABLE HTTP POST" );

	// Read and check all the form data.
	//
	if ( $new_user )
	{
	    sanitize ( $uid, 'uid', 'User ID', 4 );
	    $d = "admin/users/$uid";
	    if ( count ( $errors ) == 0
	         &&
		 ! preg_match ( $epm_name_re, $uid ) )
	        $errors[] = "$uid is not a properly"
		          . " formatted user id";
	    elseif ( count ( $errors ) == 0
	             &&
		     is_dir ( "$epm_data/$d" ) )
	        $errors[] = "another account is already"
		          . " using $uid as a User ID";
	}
	sanitize
	    ( $full_name, 'full_name',
	                  'Full Name', 5 );
	sanitize
	    ( $organization, 'organization',
	                     'Organization', 3 );
	sanitize
	    ( $location, 'location',
	                 'Location', 6 );

	if (    $update != 'finish'
	     || count ( $errors ) > 0 )
	    $edit = 'profile';
	elseif ( $new_user )
	{
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
	    write_info ( $info );

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
	}
	else
	{
	    write_info ( $info );
	    $edit = NULL;
	}

    }
    elseif ( isset ( $_POST['add_email'] )
             &&
	     isset ( $_POST['new_email'] ) )
    {
        if ( ! $editable )
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
		write_info ( $info );
	    }
	}
	$edit = 'emails';
    }
    elseif ( isset ( $_POST['delete_email'] ) )
    {
        if ( ! $editable )
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
		write_info ( $info );
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

    if ( $editable && $edit != 'profile'
                   && count ( $emails ) == 1 )
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
    if ( $edit == 'profile' )
    {
        $style = '';
	if ( $new_user )
	    $style = 'style="background-color:yellow"';
    	echo <<<EOT
	<strong>Your Info</strong>
	<br>
	<button type='button'
		onclick='UPDATE("finish")'
		$style>
		Finish Editing</button>
	<button type='button'
		onclick='UPDATE("check")'>
		Check New Profile</button>
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
	if ( $editable )
	    echo <<<EOT
	    <br>
	    <button type="submit"
		    name='edit' value='profile'>
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

    if ( $editable ) $uname = 'Your';
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

    $exclude = ( $new_user ?          [] :
                 $edit == 'profile' ? ['uid'] :
		                      NULL );
    $rows = info_to_rows ( $info, $exclude );
    $h = ( $edit == 'profile' ?
           'Edit Your Profile' :
	   "$uname Profile" );

    if ( $new_user )
        $h = "<strong style='background-color:red'>"
	   . "WARNING:</strong>"
	   . "<mark><strong>"
	   . "You can never change your User ID,"
	   . "the short name by which you will be"
	   . " known, after you acknowledge your"
	   . "initial profile."
	   . "</strong></mark><strong>$h:</strong>";
    else
        $h = "<strong>$h:</strong>";

    echo <<<EOT
    <div class='user-profile'>
    <form method='POST' action='user.php'
	  id='profile-update'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='update' id='update'>
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

<script>
let profile_update = document.getElementById
    ( 'profile-update' );
let update = document.getElementById
    ( 'update' );
function UPDATE ( value )
{
    update.value = value;
    profile_update.submit();
}
</script>

</body>
</html>
