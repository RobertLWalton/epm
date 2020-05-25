<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon May 25 04:24:12 EDT 2020

    // Display and edit user information in:
    //
    //		admin/email/*
    //		admin/users/$uid/$uid.info
    //
    // If $_SESSION['EPM_UID'] not set (i.e., if the
    // user is a new user), also assigns $uid and
    // creates:
    //
    //		users/$uid
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

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET'
         &&
         $epm_method != 'POST' )
        exit ( "UNACCEPTABLE HTTP METHOD $epm_method" );

    require "$epm_home/include/epm_user.php";

    $email = $_SESSION['EPM_EMAIL'];
    $new_user = ( ! isset ( $_SESSION['EPM_UID'] ) );
    $STIME = $_SESSION['EPM_SESSION_TIME'];
    $edit = ( $new_user ? 'profile' : NULL );
        // One of: NULL (just view), 'emails', or
	// 'profile'.  Set here for GET processing;
	// changed below by POST processing.
    $errors = [];
        // List of error messages to be displayed.

    // Data:
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
	    $data['INFO'] = read_uid_info
	        ( $_SESSION['EPM_UID'] );
    }
    else
	$data = & $_SESSION['EPM_DATA'];
    $info = & $data['INFO'];
    $uid = & $info['uid'];
    $emails = & $info['emails'];
    $full_name = & $info['full_name'];
    $organization = & $info['organization'];
    $location = & $info['location'];

    // The following lock prevents others from
    // creating/deleting users and emails, but
    // NOT browser tickets.  Even if we are not
    // editing, we read all the emails to check
    // if they match the emails recorded in the
    // UID.info file.

    $lock_desc = NULL;
    function shutdown ()
    {
	global $lock_desc;
	if ( isset ( $lock_desc ) )
	{
	    flock ( $lock_desc, LOCK_UN );
	    fclose ( $lock_desc );
	}
    }
    register_shutdown_function ( 'shutdown' );

    $f = "admin/+lock+";
    $lock_desc = fopen ( "$epm_data/$f", "w" );
    if ( $lock_desc === false )
	ERROR ( "cannot open $f for writing" );
    $r = flock ( $lock_desc, LOCK_EX );
    if ( $r === false )
	ERROR ( "cannot lock $f" );

    if ( $epm_method == 'GET' && ! $new_user )
    {
	$uid = $_SESSION['EPM_UID'];

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
	    $emails = $actual;
	    write_info();
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

    function write_info()
    {
        global $epm_data, $epm_time_format,
	       $uid, $info;

	$changes = '';
	$time = strftime ( $epm_time_format );
	$h = "$time $uid info";

	$f = "admin/users/$uid/$uid.info";
	$old = @file_get_contents ( "$epm_data/$f" );
	if ( $old === false )
	{
	    foreach ( $info as $key => $value )
	    {
	        if ( $key != 'email' )
		    $changes .= "$h $key = $value"
			      . PHP_EOL;
		else foreach ( $value as $email )
		    $changes .= "$h email + $email"
			      . PHP_EOL;
	    }
	}
	else
	{
	    $old = json_decode ( $old, true );
	    echo ( 'OLD ' . json_encode ( $old ) . '<BR>' );
	    if ( $old == NULL )
	        ERROR ( "badly formatted old $f" );
	    foreach ( $info as $key => $value )
	    {
		echo ( "KEY $key " . json_encode ( $value ) . '<BR>' );
		echo ( "OLD $key " . json_encode ( $old[$key] ) . '<BR>' );
		if ( $key != 'emails' )
		{
		    if ( ! isset ( $old[$key] )
		         ||
			 $old[$key] != $info[$key] )
		        $changes .= "$h $key = $value"
			          . PHP_EOL;
		}
		elseif ( ! is_array ( $old['emails'] ) )
		    ERROR ( "badly formatted old $f" );
		else
		{
		    $adds = array_diff
		        ( $info['emails'],
			  $old['emails'] );
		    foreach ( $adds as $email )
			$changes .= "$h email + $email"
			          . PHP_EOL;
		    $subs = array_diff
		        ( $old['emails'],
			  $info['emails'] );
		    foreach ( $subs as $email )
			$changes .= "$h email - $email"
			          . PHP_EOL;
		}
	    }
	    foreach ( $old as $key => $value )
	    {
	        if ( isset ( $info[$key] ) ) continue;
		$changes .= "$h $key -" . PHP_EOL;
	    }
	}


	$c = json_encode ( $info, JSON_PRETTY_PRINT );
	if ( $c === false )
	    ERROR ( 'cannot json_encode $info' );
	$r = @file_put_contents ( "$epm_data/$f", $c );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$f = "admin/users/$uid/+changes+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot append to $f" );
	$f = "admin/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot append to $f" );
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

	echo ( "UPDATE $update" );
	if (    $update != 'finish'
	     || count ( $errors ) > 0 )
	    $edit = 'profile';
	elseif ( $new_user )
	{
	    $d = "admin/users/$uid";
	    if ( ! mkdir ( "$epm_data/$d", 0770,
	                   true ) )
	        ERROR ( "cannot make $d" );

	    $re = rawurlencode ( $email );
	    $f = "admin/email/$re";
	    if ( file_exists ( "$epm_data/$f" ) )
	        WARN ( "$f exists when it should not" );
	    $items = [ $uid, $STIME, 1, $STIME,
				     0, 'NONE' ];
	    $r = @file_put_contents
		( "$epm_data/$f",
		  implode ( ' ', $items ) );
	    if ( $r === false )
		ERROR ( "could not write $f" );
	    write_info();

	    $f = "admin/users/$uid/session_id";
	    $r = file_put_contents
	        ( "$epm_data/$f", session_id() );
	    if ( $r === false )
		ERROR ( "could not write $f" );
	    $fmtime = filemtime ( "$epm_data/$f" );
	    if ( $fmtime === false )
		ERROR ( "could not stat $f" );
	    $_SESSION['EPM_SESSION'] = [$f,$fmtime];

	    $_SESSION['EPM_UID'] = $uid;
	        // Do this last as it certifies
		// the EMAIL and .info files exist.
	    $edit = NULL;
	}
	else
	{
	    write_info();
	    $edit = NULL;
	}

    }
    elseif ( isset ( $_POST['add_email'] )
             &&
	     isset ( $_POST['new_email'] ) )
    {
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
	        $items = [ $uid, $STIME, 0, 'NONE',
		                         0, 'NONE' ];
		$r = @file_put_contents
		    ( "$epm_data/$f",
		      implode ( ' ', $items ) );
	        if ( $r === false )
		    ERROR ( "could not write $f" );
	        $emails[] = $e;
		write_info();
	    }
	}
	$edit = 'emails';
    }
    elseif ( isset ( $_POST['delete_email'] ) )
    {
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
		write_info();
	    }
	}
	$edit = 'emails';
    }
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    $data['LAST_EDIT'] = $edit;

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.email-addresses {
	background-color: var(--bg-green);
	padding: 10px 0px 0px 0px;
    }
    div.email-addresses * {
        font-size: var(--large-font-size);
	padding: 5px;
	text-align: left;
    }
    div.user-profile {
	background-color: var(--bg-tan);
	padding: 10px 0px 0px 0px;
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

</style>
</head>
<body>

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

    if ( $edit != 'profile' && count ( $emails ) == 1 )
        echo "<mark><strong>Its a good idea to add a" .
	     " second email address.</strong></mark><br>";

    $user_help = HELP ( 'user-page' );
    echo <<<EOT
    <div class='manage'>
    <form method='POST' action='user.php'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>Your User Email Addresses and Profile
    </strong>
EOT;
    if ( $edit == 'profile' )
    {
        $style = '';
	if ( $new_user )
	    $style = 'style="background-color:yellow"';
    	echo <<<EOT
	<button type='button'
		onclick='UPDATE("finish")'
		$style>
		Finish</button>
	<button type='button'
		onclick='UPDATE("check")'>
		Check</button>
	<button type="submit"
	        formmethod='GET'>
		Cancel</button>
EOT;
    }
    elseif ( $edit == 'emails' )
    	echo <<<EOT
	<button type="submit"
	        formmethod='GET'>
		Finish</button>
EOT;
    else
    	echo <<<EOT
	<button type="submit"
		name='edit' value='profile'>
		Edit Profile</button>
	<button type="submit"
		name='edit' value='emails'>
		Edit Emails</button>
	</td><td>
	<strong>Go To</strong>
	<button type="submit"
		formaction="problem.php"
		formmethod='GET'>Problem</button>
	<button type="submit"
		formaction="project.php"
		formmethod='GET'>Project</button>
	<strong>Page</strong>
EOT;
    echo <<<EOT
    </td>
    <td style='text-align:right'>$user_help</td>
    </tr>
    </table>
    </form>
    </div>
EOT;

    if ( $edit == 'emails' )
    {
	echo <<<EOT
	<div class='email-addresses'>
	<strong>Edit User Email Addresses:</strong>
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
		    value='$he'>Delete</button><br>
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
	<strong>User Email Addresses:</strong>
	<div class='indented'>
	$addresses
	</div></div>
EOT;
    }

    $location_placeholder =
	 "Town, State (and Country) of Organization";
    $hfull_name = htmlspecialchars ( $full_name );
    $horganization = htmlspecialchars ( $organization );
    $hlocation = htmlspecialchars ( $location );
    echo <<<EOT
    <div class='user-profile'>
EOT;
    if ( $edit == 'profile' )
    {
	echo <<<EOT
	<form method='POST' action='user.php'
	      id='profile-update'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='update' id='update'>
	<strong>Edit Your User Profile:</strong><br>
	<table>
EOT;
	if ( $new_user )
	    echo <<<EOT
	    <tr><th><mark>WARNING:</mark</th>
	    <td>
	    <mark><strong>
	    You can never change your User ID after you
	    hit the Finish button for the first time.
	    </strong></mark></td><tr>
	    <tr><th>User ID:</th>
		<td> <input type='text' size='20'
		      name='uid' value='$uid'
		      title='Your User ID (Short Name)'
		      placeholder='User Id (Short Name)'
		      >
	     <pre>  </pre>
	     (Short name by which you will be known
	      to other users.)
	     </td></tr>
EOT;
	else
	    echo <<<EOT
	    <tr><th>User ID:</th>
		<td>$uid</td></tr>
EOT;
	echo <<<EOT
	<tr><th>Full Name:</th>
	    <td> <input type='text' size='40'
		  name='full_name'
		  value='$hfull_name'
		  title='Your Full Name'
		  placeholder='John Doe'></td></tr>
	<tr><th>Organization:</th><td>
	    <input type='text' size='40'
	     name='organization' value='$horganization'
	     title='University, Company, or Self'
	     placeholder='University, Company, or Self'>
	     </td></tr>
	<tr><th>Location:</th><td>
	    <input type='text' size='40'
	     name='location' value='$hlocation'
	     title='$location_placeholder'
	     placeholder='$location_placeholder'>
	     </td></tr>
	</table>
	</form>
EOT;
    }
    else
    {
        $rows = user_info_to_rows ( $info );
	echo <<<EOT
	<strong>Your User Profile:</strong>
	<table>
	$rows
	</table>
EOT;
    }
?>

</div>
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
