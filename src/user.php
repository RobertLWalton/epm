<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Nov  7 04:02:15 EST 2019

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
	header ( "Location: /src/index.php" );
	exit;
    }

    echo 'SESSION: '; print_r ( $_SESSION ); echo '<br><br>';
    echo 'REQUEST: '; print_r ( $_REQUEST ); echo '<br><br>';
    echo 'SERVER: '; print_r ( $_SERVER ); echo '<br><br>';


    $email = $_SESSION['email'];
    $userid = $_SESSION['userid'];
    $ipaddr = $_SESSION['ipaddr'];
    $confirmation_time = $_SESSION['confirmation_time'];

    $method = $_SERVER['REQUEST_METHOD'];
   
    $users = [];
    $users_file = 'admin/user_index.json';
    if ( is_writable ( $users_file ) )
    {
	$users = file_get_contents ( $users_file );
	$users = json_decode ( $users, true );
	if ( ! $users ) $users = [];
    }
   
    $user = [];
    if ( is_int ( $userid ) )
    {
	$user_file = 'admin/user{$userid}.json';
	if ( is_writable ( $user_file ) )
	{
	    $user = file_get_contents ( $user_file );
	    $user = json_decode ( $user, true );
	    if ( ! $user ) $users = [];
	}
    }
    else
    {
	$user['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user['full_name'] = "";
	$user['organization'] = "Self";
	$user['address'] = "";
    }
    $full_name = $user['full_name'];
    $organization = $user['organization'];
    $address = $user['address'];

    exit ( 'user.php not finished yet' );

    $emails = [];
    $max_id = 0;
    foreach ( $users as $key => $value )
    {
	$max_id = max ( $max_id, $value );
	if (    $value == $userid 
	     && $key != $email )
	    $emails[] = $key;
    }

    $is_new_user = ( $userid == 'NEW' );

    sort ( $emails );

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
	// Delete emails that are to be deleted.
	//
	$full_name = sanitize
	    ( 'full_name', 'Full Name', 5 );
	$organization = sanitize
	    ( 'organization', 'Organization', 3 );
	$location = sanitize
	    ( 'location', 'Location', 6 );

	$emails = [];
	for ( $i = 0; $i < $max_emails; $i++ )
	{
	    if ( ! isset ( $_POST["email$i"] ) )
	    {
	        $field_missing = true;
	        continue;
	    }
	    if ( isset ( $_POST["delete$i"] )
	        continue;
	    $value =  trim ( $_POST["email$i"] );
	    if ( $value == "" )
	        continue;
	    $svalue = filter_var
	        ( $value, FILTER_SANITIZE_EMAIL );
	    if ( $value != $svalue )
	    {
	        errors[] = 'Email '
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
	        errors[] = 'Email ' . $hvalue
		         . ' contains HTML special'
			 . ' characters.';
		$value = $hvalue;
	    }
	    if ( ! filter_var
		      ( $value,
			FILTER_VALIDATE_EMAIL ) )
	        errors[] = 'Email ' . $value
		         . ' is not a valid email'
			 . ' address.';
	    if ( $value != $email )
		emails[] = $value;
	}

	if ( $field_missing )
	    error ( 'UNKNOWN HTTP POST' );
    }

    if ( $method == 'POST' && isset ( $_POST['submit' )
                           && count ( $errors ) == 0 )
    {
        // We are done; copy data to files.

// TBD

        // Record current time as last confirmation
	// time for the user and ip address.
	//
	$confirmation_time =
	    $_SESSION['confirmation_time'];
	$ipaddr = $_SESSION['ipaddr'];
	$last_confirmation_file =
	    "admin/user{$userid}_" .
	    "last_confirmation.json";
	$last_confirmation_json = file_get_contents
	    ( $last_confirmation_file );
	$last_confirmation = json_decode
	    ( $last_confirmation_json, true );
	if ( ! $last_confirmation )
	    $last_confirmation = NULL;
	$last_confirmation[$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$last_confirmation_json = json_encode
	    ( $last_confirmation );
	file_put_contents
	    ( $last_confirmation_file,
	      $last_confirmation_json );
    }

    if (    ! isset ( $confirmation_time )
         && is_int ( $userid ) )
    {
	// Check if we can auto-confirm for this
	// user and ip address.
	//
	$user_json = file_get_contents
	    ( "admin/user{$userid}.json" );
	$user = json_decode ( $user_json, true );
	if (    $user
	     && isset ( $user[$ipaddr] ) )
	{
	    $ctime = strtotime ( $user[$ipaddr] );
	    if (   time()
	         < $ctime + $confirmation_interval )
	    {
	        $confirmation_time = $ctime;
		$_SESSION['confirmation_time'] =
		    $confirmation_time;
	    }
	}
    }

    if ( isset ( $confirmation_time ) )
    		// implies $userid and $email set
    {
	if ( ! is_int ( $userid ) )
	    header ( "Location: /src/user.php" );
	else
	    header ( "Location: /src/problems.php" );
	exit;
    }
    else if ( isset ( $email ) )
	$_SESSION['confirm'] =
	    bin2hex ( random_bytes ( 8 ) );

?>

<html>
<body>


<?php 

    $begin_form =
	'<form method="post" action="' .
	$_SERVER['PHP_SELF'] . '">';
    $end_form = '<input type="hidden"' .
                ' name="dialog"' .
		' value="' . $dialog . '></form>';

    if ( ! isset ( $email ) )
    {
	if ( $bad_email )
	    echo '<mark>EMAIL ADDRESS WAS' .
		 ' MALFORMED; TRY AGAIN</mark>' .
		 '<br><br>';

	echo $begin_form;
	echo '<h2>Login:</h2>';
	echo 'Email Address:' .
	     ' <input type="email" name="email">';
	echo $end_form;
    }
    else
    {
	if ( $bad_confirm )
	{
	    echo '<mark>CONFIRMATION NUMBER WAS' .
		 ' WRONG; TRY AGAIN</mark><br>';
	    echo 'A <mark>new</mark>';
	}
	else
	    echo 'A';

	echo ' confirmation number has been mailed'
	     . ' to your email address.<br><br>';
	echo 'Email Address: ' . $_SESSION['email']
	     . '&nbsp;&nbsp;/&nbsp;&nbsp;';
	echo 'IP Address: ' . $_SESSION['ipaddr']
	     . '<br><br>';
	echo $begin_form;
	echo 'Confirmation Number:' .
	     ' <input type="text" name="confirm">'
	     . "<br>";
	echo $end_form;
	echo $begin_form;
	echo '<button name="email" value="">' .
	     'Enter New Email Address</button>';
	echo $end_form;
	echo '<br>Confirmation Number is ' . 
	     $_SESSION["confirm"];

    }
?>

</body>
</html>
