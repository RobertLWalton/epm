<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Mar 11 07:18:55 EDT 2020

    // Display and edit user information in:
    //
    //		admin/email/*
    //		admin/users/$uid.info
    //
    // If $_SESSION['EPM_UID'] not set (i.e., if the
    // user is a new user), also assigns $uid and
    // creates:
    //
    //		users/$uid
    //	        admin/users/$uid.info
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

    $uid_regexp = '/[A-Za-z][-_A-Za-z0-9]*[A-Za-z]/';

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
    function lock()
    {
        global $lock_desc, $epm_data;
	$f = "admin/email/+lock+";
        $lock_desc =
	    fopen ( "$epm_data/$f", "w" );
	if ( $lock_desc === false )
	    ERROR ( "cannot open $f for writing" );
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
	    'uid' => '',
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
    $new_user = ( ! isset ( $_SESSION['EPM_UID'] ) );
    $edit = false;
    $STIME = $_SESSION['EPM_SESSION_TIME'];

    if ( $method == 'GET' && ! $new_user )
    {
	$uid = $_SESSION['EPM_UID'];

	// Set $emails to the names of EMAIL-FILEs in
	// admin/email that point at the current $uid
	// and are NOT equal to $email.
	//
	$d = "admin/email";
	lock();
	$efiles = @scandir ( "$epm_data/$d" );
	if ( $efiles === false )
	    ERROR ( "cannot open $d" );

	foreach ( $efiles as $efile )
	{
	    if ( preg_match ( '/^\.\.*$/', $efile ) )
		continue;
	    if ( $efile == '+lock+' )
	        continue;
	    $f = "admin/email/$efile";
	    $c = @file_get_contents ( "$epm_data/$f" );
	    if ( $c === false )
	    {
		WARN ( "cannot read $f" );
		continue;
	    }
	    $c = trim ( $c );
	    $items = explode ( ' ', $c );
	    if ( count ( $items ) < 1
		 ||
		 ! preg_match
		       ( $uid_regexp, $items[0] ) )
	    {
		WARN ( "bad value $c in $f" );
		continue;
	    }
	    if ( $items[0] == $uid )
	    {
		$vemail = rawurldecode ( $efile );
	        if ( $vemail != $email )
		    $emails[] = $vemail;
	    }
	}
	unlock();
	sort ( $emails );

	$f = "admin/users/$uid.info";
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false )
	    ERROR ( "cannot read $f" );
	$c = preg_replace
		 ( '#(\R|^)\h*//.*#', '', $c );
	    // Get rid of `//...' comments.
	$user_info = json_decode ( $c, true );
	if ( $user_info === NULL )
	{
	    $m = json_last_error_msg();
	    ERROR ( "cannot decode json in $f:" .
		    PHP_EOL . "    $m" );
	}
	foreach ( ['full_name',
		   'organization',
		   'location'] as $key )
	    $data[$key] = $user_info[$key];
    }

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
    elseif ( isset ( $_POST['add_email'] )
             &&
	     isset ( $_POST['new_email'] ) )
    {
	if ( count ( $emails ) + 1 >= 
	     $epm_max_emails )
	    $errors[] = "you already have the maximum"
	              . " limit of $epm_max_emails"
		      . " email address";
    	elseif ( sanitize_email
	         ( $e, $_POST['new_email'] ) )
	{
	    lock();
	    $re = rawurlencode ( $e );
	    $f = "admin/email/$re";
	    if ( is_readable ( "$epm_data/$f" )
	         ||
		 in_array ( $e, $emails )
		 ||
		 $e == $email )
	    {
	        $errors[] =
		    "email address $e is already" .
		    " assigned to some user" .
		    " (maybe you)";
	    }
	    else if ( ! $new_user )
	    {
	        $items = [ $uid, $STIME, 0, 'NONE',
		                         0, 'NONE' ];
		$r = @file_put_contents
		    ( "$epm_data/$f",
		      implode ( ' ', $items ) );
	        if ( $r === false )
		    ERROR ( "could not write $f" );
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
	    }
	    unlock();
	}
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	// Read and check all the form data.
	//
	if ( $new_user )
	{
	    sanitize ( $uid, 'uid', 'User ID', 4 );
	    if ( count ( $errors ) == 0
	         &&
		 ! preg_match ( $uid_regexp, $uid ) )
	        $errors[] = "$uid is not a properly"
		          . " formatted user id";
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
	$user_info = [];
	$user_info['full_name'] = $full_name;
	$user_info['organization'] = $organization;
	$user_info['location'] = $location;
	$j = json_encode
	    ( $user_info, JSON_PRETTY_PRINT );

	lock();

	if ( $new_user )
	{
	    $m = umask ( 06 );

	    if ( ! is_dir ( "$epm_data/users" ) )
	         ERROR
		     ( 'cannot open users directory' );

	    if ( ! mkdir ( "$epm_data/users/$uid",
	                   0771 ) )
	    {
	        $errors[] = "user id $uid is already"
		          . "in use by someone else";
		$uid = '';
	    }

	    umask ( $m );
	}

	if ( $new_user && $uid != '' )
	{
	    $_SESSION['EPM_UID'] = $uid;
	    $items = [ $uid, $STIME, 1, $STIME,
				     0, 'NONE' ];
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
		    $items[2] = 0;
		    $items[3] = 'NONE';
		        // For emails other than the one
			// logged in with which is first
			// in the merged list.
		    $c = implode ( ' ', $items );
		    $r = @file_put_contents
			     ( "$epm_data/$f", $c );
		    if ( $r === false )
			ERROR ( "could not write $f" );
		}
	    }
	}

	$f = "admin/users/$uid.info";
	$r = @file_put_contents ( "$epm_data/$f", $j );
	if ( $r === false )
	    ERROR ( "count not write $f" );
	$new_user = false;
	unlock();
    }

    $max_emails = max ( $epm_max_emails,
                        1 + count ( $emails ) );

?>

<html>
<body>

<?php 

    if ( count ( $errors ) > 0 )
    {
        echo '<h3>Errors:</h3>' . PHP_EOL;
	echo "<div style='margin-left:20px'>" . PHP_EOL;
	foreach ( $errors as $value )
	{
	    $hvalue = htmlspecialchars ( $value );
	    echo "<mark>$hvalue</mark><br>" . PHP_EOL;
	}
	echo '</div>' . PHP_EOL;
    }

    if ( count ( $emails ) == 0 )
        echo "<mark>Its a good idea to add a second" .
	     " email address.</mark><br>" . PHP_EOL;

    if ( $edit )
	$h = "Edit User Email Addresses";
    else
	$h = "User Email Addresses";
    $help = HELP ( 'user-page' );
    echo <<<EOT
    <h3 style='display:inline'>$h</h3>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    $help<br><br>
EOT;

    echo "<div style='margin-left:20px'>" . PHP_EOL;
    $hemail = htmlspecialchars ( $email );
    echo "$hemail&nbsp;&nbsp;&nbsp;&nbsp;" .
         "(used for this login)<br>" . PHP_EOL;
    foreach ( $emails as $e )
    {
	$he = htmlspecialchars ( $e );
	if ( $edit )
	    echo "<form style='display:inline'".
		 " method='POST'" .
		 " action='user.php'>" . PHP_EOL .
		 "$he" .
		 "&nbsp;&nbsp;&nbsp;&nbsp;" .
		 "<button type='submit'" .
		 " name='delete_email'" .
		 " value='$he'>Delete</button><br>" .
		 PHP_EOL .
		 "</form>" . PHP_EOL;
	else
	    echo "$he<br>" . PHP_EOL;
    }
    if ( $edit
         &&
	 count ( $emails ) + 1 < $max_emails )
	echo "<form style='display:inline'".
	     " method='POST'" .
	     " action='user.php'>" . PHP_EOL .
	     "<input type='email' name='new_email'" .
	     " value='' size='40' placeholder=" .
	     "'Another Email Address'" .
	     " title='Add another email address" .
	     " to the account'>" . PHP_EOL .
	     "&nbsp;&nbsp;&nbsp;&nbsp;" . PHP_EOL .
	     "<input type='submit'" .
	     " name='add_email' value='Add'>" .
	     PHP_EOL;
	     "</form>" . PHP_EOL;

    echo "</div>" . PHP_EOL;

    $location_placeholder =
	 "Town, State (and Country) of Organization";
    $hfull_name = htmlspecialchars ( $full_name );
    $horganization = htmlspecialchars ( $organization );
    $hlocation = htmlspecialchars ( $location );
    if ( $edit )
    {
	echo <<<EOT
	<h3>Edit User Profile:</h3>
	<form  method='POST' action='user.php'>
	<table>
EOT;
	if ( $new_user ) echo <<<EOT
	    <tr><td><b>User ID:</b></td>
		<td> <input type='text' size='10'
		      name='uid'
		      title='Your User ID (Short Name)'
		      placeholder='User Id (Short Name)'
		      ></td></tr>
EOT;
	else echo <<<EOT
	    <tr><td><b>User ID:</b></td>
		<td>$uid</td></tr>
EOT;
	echo <<<EOT
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
	<tr><td></td><td style='text-align:right'>
	    <input type='submit' name='update'
		   value='update'></td></tr>
	</table></form>
EOT;
    }
    else
	echo <<<EOT
	<h3>User Profile:</h3>
	<table>
	<tr><td><b>User ID:</b></td>
	    <td>$uid</td></tr>
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
