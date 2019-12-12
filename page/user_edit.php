<?php

    // File:	user_edit.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Dec 12 04:01:01 EST 2019

    // Edits files:
    //
    //		admin/email_index/*
    //		admin/user{$userid}.json
    //
    // containing information about user.  Also
    // assigns $userid and creates
    //
    //		users/user$userid
    //	        admin/user$userid.json
    //
    // if $_SESSION['epm_userid'] not set.
    //
    // Does this by using a form to collect the follow-
    // ing information:
    //
    //	   full_name	Use's full name.
    //	   organization Use's organization.
    //     location     Town, state, country of
    //			organization.
    //
    // and allows emails to be added and emails other
    // than the login email to be deleted.

    session_start();
    clearstatcache();
    umask ( 07 );
    if ( ! isset ( $_SESSION['epm_data'] ) )
    {
	header ( "Location: index.php" );
	exit;
    }
    if ( ! isset
              ( $_SESSION['epm_confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }
    if (    $_SESSION['epm_ipaddr']
	 != $_SERVER['REMOTE_ADDR'] )
        exit ( 'UNACCEPTABLE IPADDR CHANGE' );

    $epm_data = $_SESSION['epm_data'];
    $epm_root = $_SESSION['epm_root'];
    $include = "$epm_root/include";

    if ( ! is_writable
               ( "$epm_data/admin/email_index" ) )
    {
	header ( "Location: first_user.php" );
	exit;
    }

    // require "$include/debug_info.php";

    $admin_params = $_SESSION['epm_admin_params'];

    $email = $_SESSION['epm_email'];
    $ipaddr = $_SESSION['epm_ipaddr'];
    $confirmation_time =
        $_SESSION['epm_confirmation_time'];

    $userid = NULL;
    if ( isset ( $_SESSION['epm_userid'] ) )
        $userid = $_SESSION['epm_userid'];
    $new_user = ( ! isset ( $userid ) );

    // Set $emails to the emails in admin/email_index
    // that point at the current $userid and are NOT
    // equal to $email.  Set $max_id to the maximum
    // user id seen amoung admin/email_index files.
    //
    $emails = [];
    $max_id = 0;
        // Set to NULL if cannot be computed.
    $d = "$epm_data/admin/email_index";
    $desc = opendir ( $d );
    if ( $desc === false )
    {
        $sysfail = "user_edit: cannot open $d";
	require "$include/sysalert.php";
    }
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
	$f = "$epm_data/admin/email_index/$value";
	$i = file_get_contents ( $f );
	if ( $i === false )
	{
	    $sysalert = "user_edit: cannot read $f";
	    require "$include/sysalert.php";
	    $max_id = NULL;
	    continue;
	}
	if ( ! preg_match
		   ( '/^[1-9][0-9]*$/', $i ) )
	{
	    $sysalert = "user_edit: bad value $i in $f";
	    require "$include/sysalert.php";
	    $max_id = NULL;
	    continue;
	}
	if ( isset ( $max_id ) )
	    $max_id = max ( $max_id, $i );
	if ( $i == $userid && $value != $email )
	    $emails[] = $value;
    }
    sort ( $emails );
    $max_emails = max ( $admin_params['max_emails'],
                        1 + count ( $emails ) );

    // Set $user_admin to admin/user$userid.json
    // contents, or to initial value if $userid is NULL.
    //
    if ( isset ( $userid ) )  // Not new user
    {
	$f = "$epm_data/admin/user{$userid}.json";
	$c = file_get_contents ( $f );
	if ( $c === false )
	{
	    $sysfail = "cannot read readable $f";
	    require "$include/sysalert.php";
	}
	$user_admin = json_decode ( $c, true );
	if ( $user_admin === NULL )
	{
	    $m = json_last_error_msg();
	    $sysfail =
	        "cannot decode json in $f:\n    $m";
	    require "$include/sysalert.php";
	}
    }
    else // New user
    {
	$user_admin['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user_admin['full_name'] = "";
	$user_admin['organization'] = "";
	$user_admin['location'] = "";
    }
    $full_name = $user_admin['full_name'];
    $organization = $user_admin['organization'];
    $location = $user_admin['location'];

    // Sanitize non-email form entries.  If error, add
    // to $errors and return ''.  Otherwise return
    // htmlspecialchars of value.
    //
    // $name is as in $_POST[$name], $form_name is as in
    // text of form, $min_length is min UNICODE charac-
    // ters in value.
    //
    $errors = [];  // List of post error messages.
    $field_missing = false;
       // Set if form field missing from post.
    function sanitize
        ( $name, $form_name, $min_length )
    {
        global $errors, $field_missing;

        if ( ! isset ( $_POST[$name] ) )
	{
	    $field_missing = true;
	    $errors[] = "must set $form_name";
	    return '';
	}
	$value = trim ( $_POST[$name] );
	if ( $value == '' )
	{
	    $errors[] = "must set $form_name";
	    return '';
	}
	if (   strlen ( utf8_decode ( $value ) )
	     < $min_length )
	     // Note, grapheme_strlen is not available
	     // because we do not assume intl extension.
	{
	    $errors[] =
	        "$form_name is too short; re-enter";
	    return '';
	}
	return $value;
    }

    // Check that $value can be legally added as an
    // email.  Return '' if its not legal OR if its
    // empty.
    //
    function sanitize_email ( $value )
    {
        global $errors;

	$value =  trim ( $value );
	if ( $value == "" )
	    return '';
	$hvalue = htmlspecialchars ( $value );
	$svalue = filter_var
	    ( $value, FILTER_SANITIZE_EMAIL );
	if ( $value != $svalue )
	{
	    $errors[] =
	        "Email $hvalue contains characters" .
		" illegal in an email address";
	    return '';
	}
	if ( ! filter_var
		  ( $value,
		    FILTER_VALIDATE_EMAIL ) )
	{
	    $errors[] =
	        "Email $hvalue is not a valid email" .
		" address";
	    return '';
	}
	return $value;
    }
	
    $method = $_SERVER['REQUEST_METHOD'];
   
    if ( $method == 'GET' )
    {
	// Do nothing.  Get info from user.
    }

    elseif ( $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    elseif ( isset ( $_POST['new_email'] ) )
    {
	if ( ! isset ( $userid ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
    	$e = sanitize_email ( $_POST['new_email'] );
	if ( $e != '' )
	{
	    $f = "$epm_data/admin/email_index/$e";
	    if ( is_readable ( $f ) )
	    {
	        $he = htmlspecialchars ( $e );
	        $errors[] =
		    "email address $he is already" .
		    " assigned to some user" .
		    " (maybe you)";
	    }
	    else
	    {
	        $r = file_put_contents ( $f, $userid );
		if ( $r === false )
		{
		    $sysfail = "could not write $f";
		    require "$include/sysalert.php";
		}
		$emails[] = $e;
	    }
	}
    }
    elseif ( isset ( $_POST['delete_email'] ) )
    {
	if ( ! isset ( $userid ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
    	$e = sanitize_email ( $_POST['delete_email'] );
	if ( $e != '' )
        {
	    $f = "$epm_data/admin/email_index/$e";
	    $k = array_search ( $e, $emails, true );
	    if ( $e == $email )
	    {
	        $he = htmlspecialchars ( $e );
	        $errors[] =
		    "trying to delete email address" .
		    "$he that you used to log in";
	    }
	    elseif ( $k === false )
	    {
	        $he = htmlspecialchars ( $e );
	        $errors[] =
		    "trying to delete email address" .
		    "$he that is NOT assigned to your";
	    }
	    elseif ( ! unlink ( $f ) )
	    {
	        $sysfail = "cannot unlink $f";
		require "$include/sysalert.php";
	    }
	    else
		array_splice ( $emails, $k, 1 );
	}
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	// Read and check all the form data.
	// Skip emails that are to be deleted.
	// List of emails other than $email is
	// put in $user_emails.
	//
	$full_name = sanitize
	    ( 'full_name', 'Full Name', 5 );
	$organization = sanitize
	    ( 'organization', 'Organization', 3 );
	$location = sanitize
	    ( 'location', 'Location', 6 );

	if ( $field_missing )
	    exit ( 'UNKNOWN HTTP POST' );
    }
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( $method == 'POST' && isset ( $_POST['update'] )
                           && count ( $errors ) == 0 )
    {
        // We are done; copy data to files.
	//
	if ( $new_user )
	{
	    if ( ! isset ( $max_id ) )
	    {
	        $sysfail =
		    "could not compute new userid" .
		    " because of previous errors";
		require "$include/sysalert.php";
	    }
	    $userid = $max_id + 1;
	    umask ( 06 );
	    while ( ! mkdir ( $epm_data .
	                      "/users/user$userid",
	                      0771 ) )
	        ++ $userid;
	    umask ( 07 );
	    $_SESSION['epm_userid'] = $userid;
	    $f = "$epm_data/admin/email_index/$email";
	    $r = file_put_contents ( $f, $userid );
	    if ( $r === false )
	    {
		$sysfail = "could not write $f";
		require "$include/sysalert.php";
	    }
	}


	$user_admin['full_name'] = $full_name;
	$user_admin['organization'] = $organization;
	$user_admin['location'] = $location;
	$j = json_encode ( $user_admin,
	                   JSON_PRETTY_PRINT );
	$f = "$epm_data/admin/user{$userid}.json";
	$r = file_put_contents ( $f, $j );
	if ( $r === false )
	{
	    $sysfail = "could not write $f";
	    require "$include/sysalert.php";
	}

	if ( $_POST['update'] == 'Done' )
	{
	    header ( "Location: user.php?done=yes" );
	    exit;
	}
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
	    echo "<mark>$value<\mark><br>\n";
	echo '</div>' . "\n";
    }

    if (    count ( $emails ) == 0
         && isset ( $userid )
         && count ( $errors ) == 0 )
        echo "<mark>Its a good idea to add a second" .
	     " email address.</mark><br>\n";

    echo "<h3>Edit User Profile:</h3>\n";

    echo "<b>Email Addresses:</b>\n";
    echo "<div style='margin-left:20px'>\n";
    $h = htmlspecialchars ( $email );
    echo "$h&nbsp;&nbsp;&nbsp;&nbsp;" .
         "(used for this login)<br>\n";
    foreach ( $emails as $e )
    {
	$h = htmlspecialchars ( $e );
	echo "<form style='display:inline'".
	     " method='POST'" .
	     " action='user_edit.php'>\n" .
	     "$h&nbsp;&nbsp;&nbsp;&nbsp;" .
	     "<button type='submit'" .
	     " name='delete_email'" .
	     " value='$e'>Delete</button><br>\n" .
	     "</form>\n";
    }
    if ( isset ( $userid )
         &&
	 count ( $emails ) + 1 < $max_emails )
	echo "<form style='display:inline'".
	     " method='POST'" .
	     " action='user_edit.php'>\n" .
	     "<input type='email' name='new_email'" .
	     " value='' size='40' placeholder=" .
	     "'Another Email Address'>" .
	     "&nbsp;&nbsp;&nbsp;&nbsp;" .
	     "<input type='submit' name='add_email'" .
	     " value='Add'><br>\n" .
	     "</form>\n";

    if ( isset ( $userid ) && count ( $errors ) == 0 )
        $update = 'Done';
    else
        $update = 'Update';

    echo "</div>\n";

    $location_placeholder =
	 "Town, State and Country of Organization";
    echo <<<EOT
    <form  method='POST' action='user_edit.php'>
    <table>
    <tr><td><b>Full Name:</b></td>
        <td> <input type='text' size='40'
              name='full_name'
              value='$full_name'
	      placeholder='John Doe'></td></tr>
    <tr><td><b>Organization:</b></td><td>
        <input type='text' size='40'
	 name='organization' value='$organization'
	 placeholder='University, Company, or Self'>
	 </td></tr>
    <tr><td><b>Location:</b></td><td>
        <input type='text' size='40'
	 name='location' value='$location'
	 placeholder='$location_placeholder'>
	 </td></tr>
    <tr><td>
        <input type='submit' name='update'
	       value='$update'></td></tr>
    </table></form>
EOT
?>

</body>
</html>
