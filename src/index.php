<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Nov  6 01:37:26 EST 2019

    // Handles login.  Sets _SESSION:
    //
    //    userid
    //    email
    //    ipaddr
    //    confirmation_time

    session_start();

    $userid = "";
        // == "" if email address not yet posted
	// == "NEW" if new user
	// == admin/user_index.json[$email] otherwise
    $email = "";
        // == "" if valid email address not yet posted
	// == valid (sanitized) posted email address
	//    otherwise
    $confirmed = false;
    $bad_email = false;
    $bad_confirm = false;
    $confirmation_time = NULL;

    $ipaddr = $_SERVER['REMOTE_ADDR'];
    if ( ! array_key_exists ( 'ipaddr', $_SESSION ) )
        $_SESSION['ipaddr'] = $ipaddr;
    else if ( $_SESSION['ipaddr'] != $ipaddr )
        exit ( 'ERROR: SESSION IP ADDRESS CHANGED;' .
	       ' RESTART BROWSER' );

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ( array_key_exists
	              ( 'confirm', $_POST ) )
	{
	    if ( ! array_key_exists
	            ( 'email', $_SESSION ) )
	        exit ( 'BAD POST; IGNORED' ); 
	    else if (    $_SESSION['confirm']
	              == $_POST['confirm'] )
	    {
	        $confirmed = true;
		$confirmation_time = time();
	    }
	    else
		$bad_confirm = true;

	    $userid = $_SESSION['userid'];
	    $email = $_SESSION['email'];
	}
	else if ( array_key_exists
	              ( 'email', $_POST ) )
	{
	    // Enter a New Email Address button sends
	    // `email' == "".
	    //
	    $email = filter_var
	        ( $_POST['email'],
		  FILTER_SANITIZE_EMAIL );
	    if (    $email != ""
	         && ! filter_var
		          ( $email,
	                    FILTER_VALIDATE_EMAIL ) )
	    {
	        $bad_email = true;
		$email = "";
	    }
	    else
	    {
		$users = file_get_contents
		    ( 'admin/user_index.json' );
		$users = json_decode ( $users, true );
		if ( $users
		     &&
		     array_key_exists
		         ( $email, $users ) )
		    $userid = $users[$email];
		else
		    $userid = 'NEW';
		$_SESSION['userid'] = $userid;
		$_SESSION['email'] = $email;
	    }
	}
    }

    if ( $confirmed && $userid != "NEW" )
    {
        // Record current time as last confirmation
	// time for the user and ip address.
	//
	$_SESSION['confirmation_time'] =
	    $confirmation_time;
	$ipaddr = $_SESSION['ipaddr'];
	$last_confirmation_file =
	    "admin/user${userid}_" .
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

    if ( ! $confirmed && $userid != ""
                      && $userid != 'NEW' )
    {
	// Check if we can auto-confirm for this
	// user and ip address.
	//
	$user_json = file_get_contents
	    ( "admin/user{$userid}.json" );
	$user = json_decode ( $user_json, true );
	if ( $user
	     &&
	     array_key_exists ( $ipaddr, $user ) )
	{
	    $confirmation_time =
	        strtotime ( $user[$ipaddr] );
	    if ( time() < $confirmation_time
	                  + 60 * 60 * 24 * 30 )
	    {
	        $confirmed = true;
		$_SESSION['confirmation_time'] =
		    $confirmation_time;
	    }
	}
    }

    if ( $confirmed ) // implies $userid != ""
    {
	if ( $userid == "NEW" )
	    header ( "Location: /src/user.php" );
	else
	    header ( "Location: /src/problems.php" );
	exit;
    }
    else if ( $userid != "" )
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

    if ( $email == "" )
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
