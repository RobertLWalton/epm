<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jan  8 03:26:38 EST 2020

    // Display and edit user information in:
    //
    //		admin/email/*
    //		admin/user{$uid}.info
    //
    // Also assigns $uid and creates
    //
    //		users/user{$uid}
    //	        admin/user{$uid}.info
    //
    // if $_SESSION['EPM_USER_ID'] not set (i.e., if the
    // user is a new user).
    //
    // Does this by using a form to collect the follow-
    // ing information:
    //
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
    $epm_debug = true;

    $lock_desc = NULL;
    function shutdown ()
    {
        global $lock_desc;
	if ( isset ( $lock_desc ) )
	    flock ( $lock_desc, LOCK_UN );
    }
    register_shutdown_function ( 'shutdown' );
    function lock()
    {
        global $lock_desc, $epm_data;
        $lock_desc =
	    fopen ( "$epm_data/admin/email/lock", "w" );
	flock ( $lock_desc, LOCK_EX );
    }
    function unlock()
    {
        global $lock_desc;
	flock ( $lock_desc, LOCK_UN );
	fclose ( $lock_desc );
	$lock_desc = NULL;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method == 'GET' )
	$_SESSION['EPM_USER_EDIT_DATA'] = [
	    'uid' => -1,
	    'emails' => [],
	    'full_name' => '',
	    'organization' => '',
	    'location' => ''];
    elseif ( $method != 'POST' )
        exit ( "UNACCEPTABLE HTTP METHOD $method" );

    $data = & $_SESSION['EPM_USER_EDIT_DATA'];

    $uid = & $data['uid'];
    $emails = & $data['emails'];
    $full_name = & $data['full_name'];
    $organization = & $data['organization'];
    $location = & $data['location'];

    $email = $_SESSION['EPM_EMAIL'];
    $new_user =
        ( ! isset ( $_SESSION['EPM_USER_ID'] ) );
    $edit = false;

    if ( $method == 'GET' )
    {
        if ( ! $new_user )
	    $uid = $_SESSION['EPM_USER_ID'];

	// Set $emails to the emails in admin/email
	// that point at the current $uid and are NOT
	// equal to $email.  Set $max_uid to the maximum
	// user id seen among admin/email files.
	//
	$max_uid = 0;
	$d = "admin/email";
	lock();
	$desc = opendir ( "$epm_data/$d" );
	if ( $desc === false )
	    ERROR ( "cannot open $d" );

	while ( true )
	{
	    $value = readdir ( $desc );
	    if ( ! $value )
	    {
		closedir ( $desc );
		break;
	    }
	    if ( preg_match ( '/^\.\.*$/', $value ) )
		continue;
	    if ( $value == 'lock' )
	        continue;
	    $f = "admin/email/$value";
	    $c = file_get_contents ( "$epm_data/$f" );
	    if ( $c === false )
	    {
		WARN ( "cannot read $f" );
		continue;
	    }
	    $c = trim ( $c );
	    $item = explode ( ' ', $c );
	    if ( count ( $item ) < 1
		 ||
		 ! preg_match
		       ( '/^[1-9][0-9]*$/', $item[0] ) )
	    {
		WARN ( "bad value $c in $f" );
		continue;
	    }
	    $max_uid = max ( $max_uid, $item[0] );
	    $vemail = rawurldecode ( $value );
	    if ( $item[0] == $uid && $vemail != $email )
		$emails[] = $vemail;
	}
	unlock();
	if ( $new_user ) $uid = $max_uid + 1;
	                 // This is lower bound on new
			 // user id, and not necessarily
			 // the actual new user id.
	sort ( $emails );

	if ( ! $new_user )
	{
	    $f = "admin/user{$uid}.info";
	    $c = file_get_contents ( "$epm_data/$f" );
	    if ( $c === false )
		ERROR ( "cannot read $f" );
	    $c = preg_replace
		     ( '#(\R|^)\h*//.*#', '', $c );
		// Get rid of `//...' comments.
	    $user_admin = json_decode ( $c, true );
	    if ( $user_admin === NULL )
	    {
		$m = json_last_error_msg();
		ERROR ( "cannot decode json in $f:\n" .
			"    $m" );
	    }
	    foreach ( ['full_name',
		       'organization',
		       'location'] as $key )
		$data[$key] = $user_admin[$key];
	}
    }
    $max_emails = max ( $epm_max_emails,
                        1 + count ( $emails ) );


    // Error messages and indicators for POSTs.
    //
    $errors = [];  // List of post error messages.

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
	        "$form_name is too short; re-enter";
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
	
    if ( $method == 'GET' )
    {
	// Do nothing.  Get or display user info.
    }
    elseif ( isset ( $_POST['edit'] ) )
        $edit = true;
    elseif ( isset ( $_POST['new_email'] ) )
    {
    	if ( sanitize_email
	         ( $e, $_POST['new_email'] ) )
	{
	    lock();
	    $re = rasurlencode ( $e );
	    $f = "admin/email/$re";
	    if ( is_readable ( "$epm_data/$f" )
	         ||
		     array_search ( $e, $emails, true )
		 !== false )
	    {
	        $errors[] =
		    "email address $e is already" .
		    " assigned to some user" .
		    " (maybe you)";
	    }
	    else if ( ! new_user )
	    {
	        $item = [ $uid,
		          $_SESSION['EPM_SESSION_TIME'],
			  0 ];
		file_put_contents
		    ( "$epm_data/$f",
		      implode ( ' ', $item ) );
	        $emails[] = $e;
	    }
	    else
	        $emails[] = $e;
	    unlock();
	}
    }
    elseif ( isset ( $_POST['delete_email'] ) )
    {
    	if ( sanitize_email
	         ( $e, $_POST['delete_email'] ) )
        {
	    lock();
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
	    elseif ( $new_user )
		array_splice ( $emails, $k, 1 );
	    else
	    {
	        $c = file_get_contents
		    ( "$epm_data/$f" );
		if ( $c !== false )
		{
		    $c = trim ( $c );
		    $item = explode ( ' ', $c );
		    if ( $item[0] != $uid )
			WARN ( "UID $uid trying to" .
			       " delete $f which" .
			       " belongs to UID" .
			       " {$item[0]}" );
		    else
			unlink ( "$epm_data/$f" );
		}
		array_splice ( $emails, $k, 1 );
	    }
	    unlock();
	}
    }
    elseif ( isset ( $_POST['data'] ) )
    {
	// Read and check all the form data.
	//
	sanitize
	    ( $full_name, 'full_name',
	                  'Full Name', 5 );
	sanitize
	    ( $organization, 'organization',
	                     'Organization', 3 );
	sanitize
	    ( $location, 'location',
	                 'Location', 6 );
    }
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( count ( $errors ) != 0
         ||
	 $full_name == ''
	 ||
	 $organization == ''
	 ||
	 $location == '' )
    {
        $edit = true;
    }
    else
    {
        // We are done; copy data to files.
	//
	$user_admin = [];
	$user_admin['full_name'] = $full_name;
	$user_admin['organization'] = $organization;
	$user_admin['location'] = $location;
	$j = json_encode
	    ( $user_admin, JSON_PRETTY_PRINT );

	lock();

	if ( $new_user )
	{
	    umask ( 06 );

	    if ( ! is_dir ( "$epm_data/users" ) )
	        mkdir ( "$epm_data/users", 0771 );

	    while ( ! mkdir ( $epm_data .
	                      "/users/user$uid",
	                      0771 ) )
	        ++ $uid;

	    umask ( 07 );

	    $_SESSION['EPM_USER_ID'] = $uid;
	    $item = [ $uid,
	              $_SESSION['EPM_SESSION_TIME'],
		      1];
	    $c = implode ( ' ', $item );
	    foreach ( array_merge ( [$email], $emails )
	              as $e )
	    {
		$re = rawurlencode ( $e );
		$f = "admin/email/$re";
		if ( is_readable ( "$epm_data/$f" ) )
		    WARN ( "$f exists when it should" .
		           " not" );
		else
		{
		    $r = file_put_contents
			     ( "$epm_data/$f", $c );
		    if ( $r === false )
			ERROR ( "could not write $f" );
		}
	    }
	}

	$f = "admin/user{$uid}.info";
	$r = file_put_contents ( "$epm_data/$f", $j );
	if ( $r === false )
	    ERROR ( "count not write $f" );
	$new_user = false;
	unlock();
    }

?>

<html>
<body>

<?php 

    if ( count ( $errors ) > 0 )
    {
        echo '<h3>Errors:</h3>' . "\n";
	echo "<div style='margin-left:20px'>\n";
	foreach ( $errors as $value )
	{
	    $hvalue = htmlspecialchars ( $value );
	    echo "<mark>$hvalue<\mark><br>\n";
	}
	echo '</div>' . "\n";
    }

    if ( count ( $emails ) == 0 )
        echo "<mark>Its a good idea to add a second" .
	     " email address.</mark><br>\n";

    if ( $edit )
	echo "<h3>Edit User Profile:</h3>\n";
    else
	echo "<h3>User Profile:</h3>\n";

    echo "<b>Email Addresses:</b>\n";
    echo "<div style='margin-left:20px'>\n";
    $hemail = htmlspecialchars ( $email );
    echo "$hemail&nbsp;&nbsp;&nbsp;&nbsp;" .
         "(used for this login)<br>\n";
    foreach ( $emails as $e )
    {
	$he = htmlspecialchars ( $e );
	if ( $edit )
	    echo "<form style='display:inline'".
		 " method='POST'" .
		 " action='user.php'>\n" .
		 "$he" .
		 "&nbsp;&nbsp;&nbsp;&nbsp;" .
		 "<button type='submit'" .
		 " name='delete_email'" .
		 " value='$he'>Delete</button><br>\n" .
		 "</form>\n";
	else
	    echo "$he<br>\n";
    }
    if ( $edit
         &&
	 count ( $emails ) + 1 < $max_emails )
	echo "<form style='display:inline'".
	     " method='POST'" .
	     " action='user.php'>\n" .
	     "<input type='email' name='new_email'" .
	     " value='' size='40' placeholder=" .
	     "'Another Email Address'" .
	     " title='Add another email address" .
	     " to the account'>" .
	     "</form>\n";

    echo "</div>\n";

    $location_placeholder =
	 "Town, State (and Country) of Organization";
    $hfull_name = htmlspecialchars ( $full_name );
    $horganization = htmlspecialchars ( $organization );
    $hlocation = htmlspecialchars ( $location );
    if ( $edit )
	echo <<<EOT
	<form  method='POST' action='user.php'>
	<table>
	<tr><td><b>Full Name:</b></td>
	    <td> <input type='text' size='40'
		  name='full_name'
		  value='$hfull_name'
		  title='Your Full Name'
		  placeholder='John Doe'></td></tr>
	<tr><td><b>Organization:</b></td><td>
	    <input type='text' size='40'
	     name='organization' value='$horganization'
	     title='University, Company, or Self'
	     placeholder='University, Company, or Self'>
	     </td></tr>
	<tr><td><b>Location:</b></td><td>
	    <input type='text' size='40'
	     name='location' value='$hlocation'
	     title='$location_placeholder'
	     placeholder='$location_placeholder'>
	     </td></tr>
	<tr><td>
	    <input type='submit' name='data'
		   value='Update'></td></tr>
	</table></form>
EOT;
    else
	echo <<<EOT
	<table>
	<tr><td><b>Full Name:</b></td>
	    <td>$hfull_name</td></tr>
	<tr><td><b>Organization:</b></td>
	    <td>$horganization</td></tr>
	<tr><td><b>Location:</b></td>
	    <td>$hlocation</td></tr>
	</table><br>
	<form>
	<input type="submit" formaction="user.php"
	       formmethod='POST' name='edit'
	       value="Edit">
        &nbsp;&nbsp;&nbsp;&nbsp;
	<input type="submit" formaction="problem.php"
	       formmethod='GET'
	       value="Go To Problem"</input>
	</form>
EOT;
?>

</body>
</html>
