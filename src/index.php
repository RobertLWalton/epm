<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Nov  7 02:27:46 EST 2019

    // Handles login for a session.  Sets _SESSION:
    //
    //    userid
    //    email
    //    ipaddr
    //    confirmation_time
    //
    // Login for a session has completed if confirma-
    // tion_time has been set and userid is an integer.
    // Confirmation_time is the time of the last confir-
    // mation, which may be days before the current
    // time.
    //
    // The userid == 'NEW' if this is a new user which
    // has not yet been assigned a user id.  Otherwise
    // it is a natural number (1, 2, ...).  NEW is
    // changed to a natural number by the user.php
    // page.

    $confirmation_interval = 30 * 24 * 60 * 60;
        // Interval in seconds that confirmation will
	// be valid for a given email address and
	// ip address.  Default, 30 days.

    session_start();
    clearstatcache();

    $ipaddr = $_SERVER['REMOTE_ADDR'];
    if ( ! isset ( $_SESSION['ipaddr'] ) )
        $_SESSION['ipaddr'] = $ipaddr;
    else if ( $_SESSION['ipaddr'] != $ipaddr )
        exit ( 'ERROR: SESSION IP ADDRESS CHANGED;' .
	       ' RESTART BROWSER' );

    $method = $_SERVER["REQUEST_METHOD"];

    if ( $method == 'GET' )
    {
	$userid = NULL;
	$email = NULL;
	$bad_email = false;
	$bad_confirm = false;
	$confirmation_time = NULL;
    }

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
	    elseif ( isset ( $_SESSION
	                     ['confirmation_time'] ) )
	        exit ( 'ALREADY CONFIRMED; YOU BEAT' .
		       ' YOURSELF TO IT' ); 
	    elseif (    $_SESSION['confirm']
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
		$users_file = 'admin/users.json';
		$users = [];
		if ( is_writable ( $users_file ) )
		{
		    $users_json = file_get_contents
			( $users_file );
		    $users = json_decode
		        ( $users_json, true );
		    if ( ! $users ) $users = [];
		}
		if ( isset ( $users[$email] ) )
		    $userid = $users[$email];
		else
		    $userid = 'NEW';
		$_SESSION['userid'] = $userid;
		$_SESSION['email'] = $email;
	    }
	}
    }

    if ( is_int ( $userid ) )
    {
        $user_file = "admin/user{$userid}.json";
	if ( is_writable ( $user_file ) )
	{
	    $user_json = file_get_contents
	        ( $user_file );
	    $user = json_decode ( $user_json, true );
	    if ( ! $user ) $user = [];
	}
    }
    else
        $user = [];

    if (    isset ( $confirmation_time )
         && is_int ( $userid ) )
    {
        // Record current time as last confirmation
	// time for the user and ip address.
	//
	$_SESSION['confirmation_time'] =
	    $confirmation_time;
	$ipaddr = $_SESSION['ipaddr'];
	$user['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user_json = json_encode ( $user );
	file_put_contents
	    ( "admin/user{$userid}.json", $user_json );
    }

    if (    ! isset ( $confirmation_time )
         && is_int ( $userid ) )
    {
	// Check if we can auto-confirm for this
	// user and ip address.
	//
	if ( isset ( $user['confirmation_time']
	                  [$ipaddr] ) )
	{
	    $ctime = strtotime
			 ( $user['confirmation_time']
			        [$ipaddr] );
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
    $end_form = '</form>';

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
