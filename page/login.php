<?php

    // File:	login.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Dec  8 04:45:52 EST 2019

    // Handles login for a session.  Sets _SESSION:
    //
    //    epm_userid
    //    epm_email
    //    epm_ipaddr
    //    epm_confirmation_time
    //    epm_login_time
    //
    // Login for a session has completed if confirma-
    // tion_time has been set and userid is an integer.
    // Confirmation_time is the time of the last confir-
    // mation, which may be days before the current
    // time.
    //
    // The userid is not set if this is a new user which
    // has not yet been assigned a user id.  Otherwise
    // it is a natural number (1, 2, ...).  If userid is
    // not set, it is set to a natural number by the
    // user_edit.php page.
    //
    // Login_time is the time this page first accessed
    // for a session.  Once a session is logged in,
    // it stays logged in indefinitely.
    //
    // Login attempts are logged to the file
    //
    //		admin/login.log
    //
    // if that file exists and is writeable. The file
    // format is CVS with:
    //
    //		*,comment
    //		email,login_time,confirmation_time
    //
    // where times are in '%FT%T%z' (ISO 8601) format,
    // and confirmation_time is 'FAILED' if confirmation
    // failed.  Each confirmation attempt is logged
    // separately.

    $date_format = "%FT%T%z";

    session_start();
    clearstatcache();
    umask ( 07 );

    // We come here from other pages if
    // $_SESSION['epm_userid'] is not set.

    if ( ! isset ( $_SESSION['epm_root'] )
         ||
	 ! isset ( $_SESSION['epm_data'] ) )
    {
        // User saved src/login.php and is trying to
	// reuse it to login again and start another
	// session.  Go to index.php (which will go
	// to edited version).
	//
        header ( 'Location: index.php' );
	exit;
    }
    if ( ! isset ( $_SESSION['epm_userid'] )
         &&
	 isset ( $_SESSION['epm_confirmation_time'] ) )
    {
        // We have confirmed new user.
	//
        header ( 'Location: user_edit.php' );
	exit;
    }

    $epm_root = $_SESSION['epm_root'];
    $epm_data = $_SESSION['epm_data'];

    // Get default administrative parameters.
    //
    $f = "$epm_root/src/default_admin.params";
    $c = file_get_contents ( $f );
    if ( $c === false )
    {
        $sysfail = "cannot read $f";
	include 'include/sysalert.php';
    }
    $admin_params = json_decode ( $c, true );
    if ( $admin_params === NULL )
    {
	$m = json_last_error_msg();
        $sysfail = "cannot decode json $f:\n    $m";
	include 'include/sysalert.php';
    }

    // Get local administrative parameter overrides.
    //
    $f = "$epm_data/admin/admin.params";
    if ( is_readable ( $f ) )
    {
	$c = file_get_contents ( $f );
	if ( $c === false )
	{
	    $sysfail = "cannot read readable $f";
	    include 'include/sysalert.php';
	}
	$j = json_decode ( $c, true );
	if ( $j === NULL )
	{
	    $m = json_last_error_msg();
	    $sysfail =
	        "cannot decode json $f:\n    $m";
	    include 'include/sysalert.php';
	}
	foreach ( $j as $key => $value )
	    $admin_params[$key] = $value;

    }
    $_SESSION['epm_admin_params'] = $admin_params;

    $confirmation_interval =
        $admin_params['confirmation_interval'];

    $ipaddr = $_SERVER['REMOTE_ADDR'];
    if ( ! isset ( $_SESSION['epm_ipaddr'] ) )
        $_SESSION['epm_ipaddr'] = $ipaddr;
    else if ( $_SESSION['epm_ipaddr'] != $ipaddr )
        exit ( 'ERROR: SESSION IP ADDRESS CHANGED;' .
	       ' RESTART BROWSER' );

    $method = $_SERVER["REQUEST_METHOD"];

    $log_confirmation_time = NULL;
    $bad_confirm = false;
    if ( $method == 'GET' )
    {
	$userid = NULL;
	$email = NULL;
	$bad_email = false;
	$confirmation_time = NULL;
	if ( isset ( $_SESSION['epm_login_time'] ) )
	    $login_time = $_SESSION['epm_login_time'];
	else
	{
	    $login_time = time();
	    $_SESSION['epm_login_time'] = $login_time;
	}
    }

    elseif ( $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    else // $method == 'POST'
    {
	if ( isset ( $_POST['confirm'] ) )
	{
	    if ( ! isset ( $_SESSION['epm_email'] ) )
	        exit ( 'BAD POST; IGNORED' ); 
	    elseif ( isset
	               ( $_SESSION
	                 ['epm_confirmation_time'] ) )
	        exit ( 'ALREADY CONFIRMED; YOU BEAT' .
		       ' YOURSELF TO IT' ); 
	    elseif (    $_SESSION['confirm']
	              == $_POST['confirm'] )
	    {
		$confirmation_time = time();
		$_SESSION['epm_confirmation_time'] =
		    $confirmation_time;
		$log_confirmation_time = strftime
		    ( $date_format,
		      $confirmation_time );
	    }
	    else
	    {
		$bad_confirm = true;
		$log_confirmation_time = 'FAILED';
	    }

	    if ( isset ( $_SESSION['epm_userid'] ) )
		$userid = $_SESSION['epm_userid'];
	    $email = $_SESSION['epm_email'];
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
		$email_file = $epm_data
		            . '/admin/email_index/'
			    . $email;
		if ( is_readable ( $email_file ) )
		{
		    $userid = file_get_contents
			( $email_file );
		    if ( $userid == 0 )
		         $userid = NULL;
		}
		else
		    $userid = NULL;
		if ( isset ( $userid ) )
		    $_SESSION['epm_userid'] = $userid;
		$_SESSION['epm_email'] = $email;
	    }
	}
    }

    if (    isset ( $log_confirmation_time )
         && is_writable
	        ( "$epm_data/admin/login.log" ) )
    {
        $desc = fopen
	    ( "$epm_data/admin/login.log", 'a' );
	if ( $desc )
	{
	    fputcsv
		( $desc,
		  [ $email,
		    strftime ( $date_format,
		               $login_time ),
		    $log_confirmation_time ] );
	    fclose ( $desc );
	}
    }

    $user = NULL;
    if ( isset ( $userid ) )
    {
        $user_file =
	    "$epm_data/admin/user{$userid}.json";
	if ( is_writable ( $user_file ) )
	{
	    $user_json = file_get_contents
	        ( $user_file );
	    $user = json_decode ( $user_json, true );
	    if ( ! $user ) $user = NULL;
	}
    }

    if (    isset ( $confirmation_time )
         && isset ( $user ) )
    {
        // Record current time as last confirmation
	// time for the user and ip address.
	//
	$_SESSION['epm_confirmation_time'] =
	    $confirmation_time;
	$ipaddr = $_SESSION['epm_ipaddr'];
	$user['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$user_json = json_encode
	    ( $user, JSON_PRETTY_PRINT );
	file_put_contents
	    ( "$epm_data/admin/user{$userid}.json",
	       $user_json );
    }

    // This must be done after recording confirmation_
    // time as it may set $confirmation_time to an old
    // value.
    //
    if (    ! isset ( $confirmation_time )
         && isset ( $user ) )
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
		$_SESSION['epm_confirmation_time'] =
		    $confirmation_time;
	    }
	}
    }

    if ( isset ( $confirmation_time ) )
    {
	if ( ! isset ( $userid ) )
	    header ( "Location: user_edit.php" );
	else
	    header ( "Location: problem.php" );
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
	echo 'Email Address: ' . $_SESSION['epm_email']
	     . '&nbsp;&nbsp;/&nbsp;&nbsp;';
	echo 'IP Address: ' . $_SESSION['epm_ipaddr']
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
