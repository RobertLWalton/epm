<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov  5 02:43:11 EST 2019

    session_start();

    $userid = "";
    $confirmed = false;
    $bad_email = false;
    $bad_confirm = false;

    $remote_addr = $_SERVER['REMOTE_ADDR'];
    if ( ! array_key_exists ( 'ipaddr', $_SESSION ) )
        $_SESSION['ipaddr'] = $remote_addr;
    else if ( $_SESSION['ipaddr'] != $remote_addr )
        exit ( 'ERROR: SESSION IP ADDRESS CHANGED;' .
	       ' RESTART BROWSER' );

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ( array_key_exists
	              ( 'confirm', $_REQUEST ) )
	{
	    if (    $_SESSION['confirm']
	         == $_REQUEST['confirm'] )
	        $confirmed = true;
	    else
	    {
		$bad_confirm = true;
		$_SESSION['confirm'] = 
		    bin2hex ( random_bytes ( 8 ) );
	    }
	}
	else if ( array_key_exists
	              ( 'email', $_REQUEST ) )
	{
	    // Enter a New Email Address button sends
	    // `email' == "".
	    //
	    $email = filter_var
	        ( $_REQUEST['email'],
		  FILTER_SANITIZE_EMAIL );
	    if (    $email != ""
	         && ! filter_var
		          ( $email,
	                    FILTER_VALIDATE_EMAIL ) )
	        $bad_email = true;
	    else
	    {
		$_SESSION['email'] = $email;
		$_SESSION['confirm'] = 
		    bin2hex ( random_bytes ( 8 ) );
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
	    }
	}
    }

    $userid = $_SESSION['userid'];

    if ( $confirmed && $userid != "" )
    {
        if ( $userid == 'NEW' )
	{
	    $users = file_get_contents
		( 'admin/user_index.json' );
	    $users = json_decode ( $users, true );
	    if ( ! $users )
	    {
	        $users = NULL;
		$userid = 1;
	    }
	    else
	    {
	        $userid = 0;
		foreach ( $users as $value )
		    $userid = max ( $userid, $value );
		$userid ++;
	    }
	    $_SESSION['userid'] = $userid;
	}

	$last_confirmation_file =
	    "admin/user${userid}_" .
	    "last_confirmation.json";
	$last_confirmation_json = file_get_contents
	    ( $last_confirmation_file );
	$last_confirmation = json_decode
	    ( $last_confirmation_json, true );
	if ( ! $last_confirmation )
	    $last_confirmation = NULL;
	$last_confirmation[$remoteaddr] =
	    strftime ( '%FT%T%z', time() );
	$last_confirmation_json = json_encode
	    ( $last_confirmation );
	file_put_contents
	    ( $last_confirmation_file,
	      $last_confirmation_json );
    }

    if ( ! $confirmed && $userid != ""
                      && $userid != 'NEW' )
    {
	$last_confirmation_file =
	    "admin/user${userid}_" .
	    "last_confirmation.json";
	$last_confirmation_json = file_get_contents
	    ( $last_confirmation_file );
	$last_confirmation = json_decode
	    ( $last_confirmation_json, true );
	if ( $last_confirmation
	     &&
	     array_key_exists
		 ( $remoteaddr, $last_confirmation ) )
	{
	    $lastdate = $last_confirmation[$remoteaddr];
	    if ( time() < strtotime ( $lastdate )
	                  + 60 * 60 * 24 * 30 )
	        $confirmed = true;
	}
    }

    if ( $confirmed )
    {
	if ( $userid != "" )
	    header ( "Location: /src/problems.php" );
	else
	    header ( "Location: /src/setup.php" );
	exit;
    }

?>

<html>
<body>


<?php 

    $begin_form =
	'<form method="post" action="' .
	$_SERVER['PHP_SELF'] . '">';
    $end_form = '</form>';

    if ( $_SESSION['email'] == "" )
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
