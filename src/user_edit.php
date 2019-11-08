<?php

    // File:	user_edit.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Nov  8 03:02:30 EST 2019

    // Edits files:
    //
    //		admin/users
    //		admin/user{$userid}.json
    //
    // containing information about user.  Also creates
    // directories:
    //
    //		admin
    //		user{$userid}
    //
    // if they are needed and do not exist.
    //
    // Does this by using a form to collect the follow-
    // ing information:
    //
    //     emails	List of the user's emails.
    //	   full_name	Use's full name.
    //	   organization Use's organization.
    //     location     Town, state, country of
    //			organization.

    session_start();
    clearstatcache();

    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: /src/login.php" );
	exit;
    }
    if ( ! is_writable ( "admin/email_index" ) )
    {
	header ( "Location: /src/first_user.php" );
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
    // $userid, or to [] if $userid == 'NEW'.  May
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
    elseif ( is_int ( $userid ) )
    {
        $desc = opendir ( 'admin/email_index' );
	if ( $desc ) while ( true )
	{
	    $value = readdir ( $desc );
	    if ( ! $value )
	    {
	        closedir ( $desc );
		break;
	    }
	    $i = file_get_contents
	        ( "admin/email_index/$value" );
	    if ( ! is_int ( $i ) ) continue;
	    $max_id = max ( $max_id, $i );
	    if ( $value == $email ) continue;
	    if ( $i == $userid )
	        $emails[] = $value;
	}
	$_SESSION['emails'] = $emails;
	$_SESSION['max_id'] = $max_id;
    }

    $method = $_SERVER['REQUEST_METHOD'];
   
    $user = NULL;
    if ( is_int ( $userid ) )
    {
	$user_file = 'admin/user{$userid}.json';
	if ( is_writable ( $user_file ) )
	{
	    $user = file_get_contents ( $user_file );
	    $user = json_decode ( $user, true );
	    if ( ! $user ) $users = NULL;
	}
    }

    if ( $user == NULL )
    {
	$user['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user['full_name'] = "";
	$user['organization'] = "";
	$user['address'] = "";
    }
    $full_name = $user['full_name'];
    $organization = $user['organization'];
    $address = $user['address'];

    $max_emails = count ( $emails ) + 1;
    $max_emails = max ( $max_emails, 3 );
 
    $errors = [];  // List of submit error messages.
    $field_missing = false;
    		   // Set if form field missing from
		   // post.

    // Sanitize non-email form entries.  Return new
    // value.  If error, add to $errors and return
    // new form value.
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
	if ( grapheme_strlen ( $value ) < $min_length )
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
        /* do nothing; we set form variables above */;

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
	    {
	        $field_missing = true;
	        continue;
	    }
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
	    $error ( 'UNKNOWN HTTP POST' );
    }

    if ( $method == 'POST' && isset ( $_POST['submit'] )
                           && count ( $errors ) == 0 )
    {
        // We are done; copy data to files.
	//
	if ( ! is_int ( $user_id ) )
	{
	    $userid = $max_id + 1;
	    while ( ! mkdir ( "users/user$userid",
	                      0750 ) )
	        ++ $userid;
	    $_SESSION['userid'] = $userid;
	}
	$user_emails[] = $email;
	foreach ( $user_emails as $value )
	    file_put_contents
		( "admin/email_index/$value", $userid );
	$user_json = json_encode ( $user );
	file_put_contents
	    ( "admin/user{$userid}.json", $user_json );
	foreach ( $emails as $value )
	{
	    if ( ! array_search
	               ( $value, $user_mails, true ) )
		unlink ( "admin/email_index/$value" );
	}
	header ( "Location: /src/user.php?done=yes" );
	exit;
    }

// TBD

    exit ( 'user_edit.php not finished yet' );
	        
?>

<html>
<body>

<form method="post" action="/src/user_edit.php">

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<mark>\n";
        echo '<h2>ERRORS:</h2><br>' . "\n";
	foreach ( $errors as $value )
	    echo "$value<br>\n";
	echo '</mark><br><br>' . "\n";
    }
    echo '<h2>Email Addresses:</h2><br>' . "\n";
    for ( $i = 0; $i < $max_emails; ++ $i )
    {
        if ( ! isset ( $user_emails[$i] ) )
	    echo '<input name=email' . $i .
	         ' type="text" value="">';
	elseif ( $user_emails[$i] == $email )
	    echo "$email";
	else
	    echo "$user_emails[$i]&nbsp;&nbsp;&nbsp;" .
	         '<input type="submit" name="delete' .
		 $i . ' value = "delete">';
	echo "<br>\n";
    }
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
	 placeholder="Town, State Country of Organization">
EOT
?>

</form>
</body>
</html>
