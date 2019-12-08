<?php

    // File:	user_edit.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Dec  8 04:39:22 EST 2019

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
    //     user_emails	List of the user's emails.
    //	   full_name	Use's full name.
    //	   organization Use's organization.
    //     location     Town, state, country of
    //			organization.

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

    $epm_data = $_SESSION['epm_data'];
    if ( ! is_writable
               ( "$epm_data/admin/email_index" ) )
    {
	header ( "Location: first_user.php" );
	exit;
    }

    $admin_params = $_SESSION['epm_admin_params'];

    // include 'include/debug_info.php';

    $email = $_SESSION['epm_email'];
    $ipaddr = $_SESSION['epm_ipaddr'];
    $confirmation_time =
        $_SESSION['epm_confirmation_time'];

    $userid = NULL;
    if ( isset ( $_SESSION['epm_userid'] ) )
        $userid = $_SESSION['epm_userid'];

    // Set $emails to the emails in admin/email_index
    // that point at the current $userid, or to [] if
    // $userid not set.  Set $max_id to the maximum
    // user id seen among admin/email_index files.
    //
    $emails = [];
    $max_id = 0;
    $d = "$epm_data/admin/email_index";
    $desc = opendir ( $d );
    if ( $desc === false )
    {
        $sysfail = "user_edit: cannot open $d";
	include 'include/sysalert.php';
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
	    include 'include/sysalert.php';
	    $max_id = NULL;
	    continue;
	}
	if ( ! preg_match
		   ( '/^[1-9][0-9]*$/', $i ) )
	{
	    $sysalert = "user_edit: bad value $i in $f";
	    include 'include/sysalert.php';
	    $max_id = NULL;
	    continue;
	}
	if ( isset ( $max_id ) )
	    $max_id = max ( $max_id, $i );
	if ( $i == $userid )
	    $emails[] = $value;
    }
    sort ( $emails );
    $max_emails = max ( $admin_params['max_emails'],
                        count ( $emails ) );

    // Set $user to admin/user$userid.json contents,
    // or NULL if this file is not readable or does
    // not exist.
    //
    $user = NULL;
    if ( isset ( $userid ) )
    {
	$sysalert = NULL;
	$user_file =
	    "$epm_data/admin/user{$userid}.json";
	$user = file_get_contents ( $user_file );
	if ( ! $user )
	    $sysalert = "cannot read $user_file";
	else
	{
	    $user = json_decode ( $user, true );
	    if ( ! $user )
		$sysalert = "cannot decode $user_file";
	}
	if ( isset ( $sysalert ) )
	{
	    include 'include/sysalert.php';
	    $user = NULL;
	}
    }

    if ( $user == NULL )
    {
	$user['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user['full_name'] = "";
	$user['organization'] = "";
	$user['location'] = "";
    }
    $full_name = $user['full_name'];
    $organization = $user['organization'];
    $location = $user['location'];

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
	    $errors[] = 'Must set ' . $form_name . '.';
	    return '';
	}
	$value = trim ( $_POST[$name] );
	if ( $value == '' )
	{
	    $errors[] = 'Must set ' . $form_name . '.';
	    return '';
	}
	if (   strlen ( utf8_decode ( $value ) )
	     < $min_length )
	     // Note, grapheme_strlen is not available
	     // because we do not assume intl extension.
	{
	    $errors[] = $form_name
	              . ' is too short; re-enter.';
	    return '';
	}
	$value = htmlspecialchars ( $value );
	if ( $value == '' )
	{
	    $errors[] = $form_name
	              . ' contained illegal characters;'
		      . ' re-enter.';
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
	$svalue = filter_var
	    ( $value, FILTER_SANITIZE_EMAIL );
	if ( $value != $svalue )
	{
	    $errors[] = 'Email '
		      . htmlspecialchars
			  ( $value )
		      . ' contains characters'
		      . ' illegal in an email'
		      . ' address.';
	    return '';
	}
	$hvalue = htmlspecialchars ( $value );
	if ( $value != $hvalue )
	{
	    $errors[] = 'Email ' . $hvalue
		      . ' contains HTML special'
		      . ' characters.';
	    return '';
	}
	if ( ! filter_var
		  ( $value,
		    FILTER_VALIDATE_EMAIL ) )
	{
	    $errors[] = 'Email ' . $value
		      . ' is not a valid email'
		      . ' address.';
	    return '';
	}
	return $value;
    }
	
    $method = $_SERVER['REQUEST_METHOD'];
   
    if ( $method == 'GET' )
    {
	$user_emails = $emails;
        if ( ! isset ( $userid ) ) 
	    $user_emails[] = $email;
        $_SESSION['user_emails'] = $user_emails;
    }

    elseif ( $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    else // $method == 'POST'
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
	if ( ! isset ( $_SESSION['user_emails'] ) )
	    exit ( 'UNKNOWN HTTP POST' );
	$user_emails = $_SESSION['user_emails'];
	for ( $i = 0; $i < $max_emails; $i++ )
	{
	    if ( isset ( $_POST["delete$i"] ) )
	        unset ( $user_emails[$i] );
	    else if ( isset ( $_POST["email$i"] ) )
	    {
	        $value = sanitize_email
		    ( $_POST["email$i"] );
		if ( $value == "" ) continue;
		if ( false !== array_search
		       ( $value, $user_emails, true ) )
		    $errors[] = "$value is a duplicate";
		else
		    $user_emails[$i] = $value;
	    }
	}
    }
    $user_emails = array_values ( $user_emails );
    sort ( $user_emails );
    $_SESSION['user_emails'] = $user_emails;

    $add  = [];
    $sub  = [];
    $keep = [];
    $i = 0;
    $j = 0;
    $max_i = count ( $emails );
    $max_j = count ( $user_emails );
    while ( $i < $max_i || $j < $max_j )
    {
	if ( $i >= $max_i )
	    $add[] = $user_emails[$j++];
	elseif ( $j >= $max_j )
	    $sub[] = $emails[$i++];
	elseif ( $emails[$i] < $user_emails[$j] )
	    $sub[] = $emails[$i++];
	elseif ( $emails[$i] > $user_emails[$j] )
	    $sub[] = $user_emails[$j++];
	else
	{
	    $keep[] = $emails[$i++];
	    ++ $j;
	}
    }

    $h = "$epm_data/admin/email_index";
    foreach ( $add as $e )
    {
        if ( is_readable ( "$h/$e" ) )
	    $errors[] =
	        "$e is assigned to another user";
    }

    if ( $method == 'POST' && isset ( $_POST['update'] )
                           && count ( $errors ) == 0 )
    {
        // We are done; copy data to files.
	//
	if ( ! isset ( $userid ) )
	{
	    if ( ! isset ( $max_id ) )
	    {
	        $sysfail =
		    "could not compute new userid" .
		    " because of previous errors";
		include 'include/sysalert.php';
	    }
	    $userid = $max_id + 1;
	    while ( ! mkdir ( $epm_data .
	                      "/users/user$userid",
	                      0770 ) )
	        ++ $userid;
	    $_SESSION['epm_userid'] = $userid;
	}


	$user['full_name'] = $full_name;
	$user['organization'] = $organization;
	$user['location'] = $location;
	$user_json = json_encode
	    ( $user, JSON_PRETTY_PRINT );
	file_put_contents
	    ( "$epm_data/admin/user{$userid}.json",
	       $user_json );

	foreach ( $add as $e )
	{
	    if ( ! file_put_contents
		      ( "$h/$e", "$userid" ) )
	    {
		$sysalert = "could not write $h/$e";
		include 'include/sysalert.php';
	    }
	}
	foreach ( $sub as $e )
	{
	    if ( ! unlink ( "$h/$e" ) )
	    {
		$sysalert = "could not unlink $h/$e";
		include 'include/sysalert.php';
	    }
	}

	//unset ( $_SESSION['user_emails'] );
	header ( "Location: user.php?done=yes" );
	exit;
    }

?>

<html>
<body>

<form method="post" action="user_edit.php">

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<mark>\n";
        echo '<h2>ERRORS:</h2><br>' . "\n";
	foreach ( $errors as $value )
	    echo "$value<br>\n";
	echo '</mark><br><br>' . "\n";
    }
    echo "<h3>Edit User Profile</h3>\n";
    echo "<b>Email Addresses:</b>\n";
    echo "<table style='margin-left:20px'>\n";
    for ( $i = 0; $i < $max_emails; ++ $i )
    {
        if ( ! isset ( $user_emails[$i] ) )
	{
	    echo '<tr><td><input name="email' . $i .
	         '" type="text" value=""' .
		 ' size="40" placeholder=' .
		 '"Another Email Address"a</td></tr>';
	    break;
	}
	elseif ( $user_emails[$i] == $email )
	    echo "<tr><td>$email" .
	         '&nbsp;&nbsp;&nbsp;' .
		 '(used for this login)' .
	         '</td></tr>';
	else
	    echo "<tr><td>$user_emails[$i]" .
	         '&nbsp;&nbsp;&nbsp;' .
	         '<input type="submit" name="delete' .
		 $i . '" value = "delete"></td></tr>';
    }
    echo "</table>\n";
    $location_placeholder =
	 "Town, State and Country of Organization";
    echo <<<EOT
    <table>
    <tr><td><b>Full Name:</b></td><td> <input type="text" size="40"
                      name="full_name"
                      value="$full_name"
	              placeholder="John Doe"></td></tr>
    <tr><td><b>Organization:</b></td><td>
        <input type="text" size="40"
	 name="organization" value="$organization"
	 placeholder="University, Company, or Self"></td></tr>
    <tr><td><b>Location:</b></td><td>
        <input type="text" size="40"
	 name="location" value="$location"
	 placeholder="$location_placeholder"></td></tr>
    <tr><td>
        <input type="submit" name="update"
	       value="Update"></td></tr>
    </table>
EOT
?>

</form>
</body>
</html>
