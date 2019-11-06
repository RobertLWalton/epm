<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Nov  6 07:40:21 EST 2019

    // Edits admin/user{$userid}.json file containing
    // information about user.  Also sets up directories
    // for new user, and system directories for first
    // user.

    session_start();

    if ( ! isset ( $_SESSION['confirm'] ) )
    {
	header ( "Location: /src/index.php" );
	exit;
    }

    echo 'SESSION: '; print_r ( $_SESSION ); echo '<br><br>';
    echo 'REQUEST: '; print_r ( $_REQUEST ); echo '<br><br>';
    echo 'SERVER: '; print_r ( $_SERVER ); echo '<br><br>';

    exit ( 'user.php not finished yet' );


    $email = $_SESSION['email'];
    $userid = $_SESSION['userid'];
    $method = $_SERVER['REQUEST_METHOD'];
	    
    $users = file_get_contents
	( 'admin/user_index.json' );
    $users = json_decode ( $users, true );
    if ( ! $users ) users = [];

    $emails = [];
    $max_id = 0;
    foreach ( $users as $key => $value )
    {
	$max_id = max ( $max_id, $value );
	if ( $value == $userid )
	    $emails[] = $key;
    }

    $is_new_user = ( $userid == 'NEW' );
    if ( $is_new_user )
    {
        $userid = $max_id + 1;
	$emails[] = $email;
    }

    if ( $method == 'GET' )
        $dialog = bin2hex ( random_bytes ( 8 ) );

    elseif ( $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    else // $method == 'POST'
    {
	if ( isset ( $_POST['confirm'] ) )
	{
	    if ( ! isset ( $_SESSION['email'] ) )
	        exit ( 'BAD POST; IGNORED' ); 
		// If session email set, so is
		// session userid.
	    else if (    $_SESSION['confirm']
	              == $_POST['confirm'] )
		$confirmation_time = time();
	    else
		$bad_confirm = true;

	    $userid = $_SESSION['userid'];
	    $email = $_SESSION['email'];
	}
	else if ( isset ( $_POST['email'] ) )
	{
	    // Enter a New Email Address button sends
	    // `email' == "".
	    //
	    $email = filter_var
	        ( $_POST['email'],
		  FILTER_SANITIZE_EMAIL );

	    if ( $email == "" )
	        $email = NULL;
		// "" email sent by
		// `Enter New Email Address'
		// button or user typing just
		// carriage return.
	    else if ( ! filter_var
		          ( $email,
	                    FILTER_VALIDATE_EMAIL ) )
	    {
	        $bad_email = true;
		$email = NULL;
	    }
	    else
	    {
		$users = file_get_contents
		    ( 'admin/user_index.json' );
		$users = json_decode ( $users, true );
		if (    $users
		     && isset ( $users[$email] ) )
		    $userid = $users[$email];
		else
		    $userid = 'NEW';
		$_SESSION['userid'] = $userid;
		$_SESSION['email'] = $email;
	    }
	}
    }

    if ( $is_new_user )
    {
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
