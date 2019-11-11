<?php

    // File:	user_edit.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Nov 10 20:03:06 EST 2019

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
    // if $_SESSION['userid'] == 'NEW'.
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
    if ( ! isset ( $_SESSION['epm_home'] ) )
        exit ( 'SYSTEM ERROR: epm_home not set' );
    $home = $_SESSION['epm_home'];

    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }
    if ( ! is_writable ( "$home/admin/email_index" ) )
    {
	header ( "Location: first_user.php" );
	exit;
    }

    echo 'SESSION: '; print_r ( $_SESSION ); echo '<br><br>';
    echo 'REQUEST: '; print_r ( $_REQUEST ); echo '<br><br>';
    echo 'SERVER: '; print_r ( $_SERVER ); echo '<br><br>';


    $email = $_SESSION['email'];
    $userid = $_SESSION['userid'];
    $ipaddr = $_SESSION['ipaddr'];
    $confirmation_time = $_SESSION['confirmation_time'];
    $login_time = $_SESSION['login_time'];

    // Set $_SESSION['emails'] to the emails in
    // admin/email_index that point at the current
    // $userid, or to [] if $userid == 'NEW'.  Then
    // add $email if it is not already included.  May
    // have been previously set for the session.
    // $_SESSION['max_id'] is set to the maximum
    // userid observed when $_SESSION['emails'] is
    // set.
    //
    $emails = [];
    $max_id = 0;
    if ( isset ( $_SESSION['emails'] ) )
    {
        $emails = $_SESSION['emails'];
	$max_id = $_SESSION['max_id'];
    }
    elseif ( $userid != 'NEW' )
    {
        $desc = opendir ( "$home/admin/email_index" );
	if ( $desc ) while ( true )
	{
	    $value = readdir ( $desc );
	    if ( ! $value )
	    {
	        closedir ( $desc );
		break;
	    }
	    $i = (int) file_get_contents
	        ( "$home/admin/email_index/$value" );
	    if ( $i == 0 ) continue;
	        // TBD: alert admin
	    $max_id = max ( $max_id, $i );
	    if ( $value == $email ) continue;
	    if ( $i == $userid )
	        $emails[] = $value;
	}
	$emails[] = $email;
	$_SESSION['max_id'] = $max_id;
    }
    else
        $emails[] = $email;

    $_SESSION['emails'] = $emails;

    $method = $_SERVER['REQUEST_METHOD'];
   
    // Set $user to admin/user$userid.json contents,
    // or NULL if this file is not readable.
    //
    $user = NULL;
    if ( $userid != 'NEW' )
    {
	$sysalert = NULL;
	$user_file = "$home/admin/user{$userid}.json";
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
	    include ( "sysalert.php" );
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

    $max_emails = max ( count ( $emails ), 3 );
 
    $errors = [];  // List of submit error messages.
    $field_missing = false;
    		   // Set if form field missing from
		   // post.

    // Sanitize non-email form entries.  If error, add
    // to $errors and return ''.  Otherwise return
    // htmlspecialchars of value.
    //
    // $name is as in $_POST[$name], $form_name is as in
    // text of form, $min_length is min UNICODE charac-
    // ters in value.
    //
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
	

    if ( $method == 'GET' )
        $user_emails = $emails;

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

	$user_emails = [];
	for ( $i = 0; $i < $max_emails; $i++ )
	{
	    if ( ! isset ( $_POST["email$i"] ) )
	        continue;
	    if ( isset ( $_POST["delete$i"] ) )
	        continue;
	    $value =  trim ( $_POST["email$i"] );
	    if ( $value == "" )
	        continue;
	    $svalue = filter_var
	        ( $value, FILTER_SANITIZE_EMAIL );
	    if ( $value != $svalue )
	    {
	        $errors[] = 'Email '
			  . htmlspecialchars
			      ( $value )
		          . ' contained characters'
			  . ' illegal in an email'
			  . ' address.';
		$value = $svalue;
	    }
	    $hvalue = htmlspecialchars ( $value );
	    if ( $value != $hvalue )
	    {
	        $errors[] = 'Email ' . $hvalue
		          . ' contains HTML special'
			  . ' characters.';
		$value = $hvalue;
	    }
	    if ( ! filter_var
		      ( $value,
			FILTER_VALIDATE_EMAIL ) )
	        $errors[] = 'Email ' . $value
		          . ' is not a valid email'
			  . ' address.';
	    if ( $value != $email )
		$user_emails[] = $value;
	}

	if ( $field_missing )
	    exit ( 'UNKNOWN HTTP POST' );
    }

    if ( $method == 'POST' && isset ( $_POST['submit'] )
                           && count ( $errors ) == 0 )
    {
        // We are done; copy data to files.
	//
	if ( $userid == 'NEW' )
	{
	    $userid = $max_id + 1;
	    while ( ! mkdir ( "$home/users/user$userid",
	                      0750 ) )
	        ++ $userid;
	    $_SESSION['userid'] = $userid;
	}
	$user_emails[] = $email;
	foreach ( $user_emails as $value )
	    file_put_contents
		( "$home/admin/email_index/$value",
		  "$userid" );
	$user['full_name'] = $full_name;
	$user['organization'] = $organization;
	$user['location'] = $location;
	$user_json = json_encode
	    ( $user, JSON_PRETTY_PRINT );
	file_put_contents
	    ( "$home/admin/user{$userid}.json",
	       $user_json );
	foreach ( $emails as $value )
	{
	    if ( ! array_search
	               ( $value, $user_mails, true ) )
		unlink
		  ( "$home/admin/email_index/$value" );
	}
	$_SESSION['emails'] = $user_emails;
	header ( "Location: user_edit.php?done=yes" );
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
    echo "<h2>Email Addresses:</h2><br>\n";
    for ( $i = 0; $i < $max_emails; ++ $i )
    {
        if ( ! isset ( $user_emails[$i] ) )
	    echo '<input name="email' . $i .
	         '" type="text" value=""' .
		 ' placeholder="Email Address">';
	elseif ( $user_emails[$i] == $email )
	    echo "$email";
	else
	    echo "$user_emails[$i]&nbsp;&nbsp;&nbsp;" .
	         '<input type="submit" name="delete' .
		 $i . '" value = "delete">';
	echo "<br>\n";
    }
    $location_placeholder =
	 "Town, State and Country of Organization";
    echo <<<EOT
    <br><br>
    Full Name: <input type="text" maxlength="80"
                      name="full_name"
                      value="$full_name"
	              placeholder="John Doe">
    <br><br>
    Organization:
        <input type="text" maxlength="80"
	 name="organization" value="$organization"
	 placeholder="University, Company, or Self">
    <br><br>
    Location:
        <input type="text" maxlength="80"
	 name="location" value="$location"
	 placeholder="$location_placeholder">
    <br><br>
        <input type="submit" name="submit"
	       value="Update">
EOT
?>

</form>
</body>
</html>
