<?php

    // File:	login.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Dec  9 00:15:43 EST 2019

    // Handles login for a session.  Sets $_SESSION:
    //
    //    epm_userid
    //	      This is set iff the user is completely
    //	      logged in.
    //    epm_email
    //	      This is set after the user has supplied
    //        their email address.
    //    epm_confirmation_time
    //	      This is set after epm_mail has been
    //        confirmed (it may be auto-confirmed);
    //    epm_login_time
    //    epm_ipaddr
    //        These are set when login.php is first
    //        invoked for a session.
    //
    // The userid is not set if this is a new user which
    // has not yet been assigned a user id.  Otherwise
    // it is a natural number (1, 2, ...).  If userid is
    // not set, it is set to a natural number by the
    // user_edit.php page.
    //
    // Confirmation attempts and auto-logins are logged
    // to the file
    //
    //		admin/login.log
    //
    // if that file exists and is writeable. The file
    // format is CVS with:
    //
    //	   *,comment
    //	   email,ipaddr,login_time,confirmation_time
    //
    // where times are in '%FT%T%z' (ISO 8601) format,
    // and confirmation_time is 'FAILED' if confirmation
    // failed.  Each confirmation attempt or auto-login
    // is logged separately.

    session_start();
    clearstatcache();
    umask ( 07 );

    // We come here from other pages if
    // $_SESSION['epm_userid'] is not set.

    if ( ! isset ( $_SESSION['epm_root'] )
         ||
	 ! isset ( $_SESSION['epm_data'] ) )
    {
        // User saved page/login.php and is trying to
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

    if ( ! isset ( $_SESSION['epm_login_time'] ) )
        $_SESSION['epm_login_time'] = time();
    if ( ! isset ( $_SESSION['epm_ipaddr'] ) )
	$_SESSION['epm_ipaddr'] =
	    $_SERVER['REMOTE_ADDR'];

    // Get and decode json file, which must be
    // readable.  It is a fatal error if the
    // file cannot be read or decoded.
    //
    function get_json ( $filename )
    {
	$f = $filename;
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
	        "cannot decode json in $f:\n    $m";
	    include 'include/sysalert.php';
	}
	return $j;
    }

    // Get default administrative parameters.
    //
    $f = "$epm_root/src/default_admin.params";
    if ( ! is_readable ( $f ) )
    {
        $sysfail = "cannot read $f";
	include 'include/sysalert.php';
    }
    $admin_params = get_json ( $f );

    // Get local administrative parameter overrides.
    //
    $f = "$epm_data/admin/admin.params";
    if ( is_readable ( $f ) )
    {
        $j = get_json ( $f );
	foreach ( $j as $key => $value )
	    $admin_params[$key] = $value;

    }
    $_SESSION['epm_admin_params'] = $admin_params;
        // User overrides are added when userid is set.

    $confirmation_interval =
        $admin_params['confirmation_interval'];

    // Data set by GET and POST requests.
    //
    $method = $_SERVER["REQUEST_METHOD"];
    $email = NULL;
        // User must be asked for an email address iff
	// this remains NULL.
    if ( isset ( $_SESSION['epm_email'] ) )
        $email = $_SESSION['epm_email'];
    $bad_email = NULL;
        // If this becomes non-NULL, it is a user given
	// email address that is rejected.
    $confirmation_time = NULL;
        // User must be asked for confirmation number,
	// or must be auto-confirmed, if this remains
	// NULL and email is not NULL.  This must be
	// NULL if email is NULL.
    if ( isset ( $_SESSION['epm_confirmation_time'] ) )
        $confirmation_time =
	    $_SESSION['epm_confirmation_time'];
    $bad_confirm = false;
        // If this becomes true, confirmation number
	// given by the user was invalid, and new number
	// must be asked for.
    $userid = NULL;
        // This is set when userid is found using email
	// address.  For new users, it is never set
	// (it will be set by user_edit.php).
	// This must be NULL if $email is NULL.
	//
	// NOTE: setting this does NOT set $_SESSION
	// ['epm_userid'], which is not set until
	// after confirmation.
    if ( isset ( $_SESSION['epm_userid'] ) )
        $userid = $_SESSION['epm_userid'];
    $user_admin = NULL;
        // User admin parameters if they exist.
	// Is NULL if $userid is NULL.

    // Set userid and $user_admin according to $email.
    // Does nothing for new user.  
    //
    function set_userid()
    {
        global $email, $epm_data, $userid, $user_admin;

	$f = "$epm_data/admin/email_index/$email";
	if ( is_readable ( $f ) )
	{
	    $u = file_get_contents ( $f );
	    if ( ! preg_match
		      ( '/^[1-9][0-9]*$/', $u ) )
	    {
		$sysfail = "$f has value $u";
		include 'include/sysalert.php';
	    }
	    $userid = $u;
	    $f = "$epm_data/admin/user{$userid}.json";
	    if ( is_readable ( $f ) )
		$user_admin = get_json ( $f );
	}
    }

    // Log confirmation attempt or auto-confirmation.
    //
    function log_confirmation()
    {
    	global $confirmation_time, $email, $epm_data;
	$date_format = "%FT%T%z";

	$f = "$epm_data/admin/login.log";
	if ( is_writable ( $f ) )
	{
	    $desc = fopen ( "$f", 'a' );
	    if ( $desc === false )
	    {
		$sysfail =
		    "cannot append to writable $f";
		include 'include/sysalert.php';
	    }
	    $ipaddr = $_SESSION['epm_ipaddr'];
	    $log_login_time =
		strftime ( $date_format,
			   $_SESSION
			     ['epm_login_time'] );
	    if ( isset ( $confirmation_time ) )
		$log_confirmation_time = strftime
		    ( $date_format,
		      $confirmation_time );
	    else
		$log_confirmation_time = 'FAILED';
	    fputcsv
		( $desc,
		  [ $email,
		    $ipaddr,
		    $log_login_time,
		    $log_confirmation_time ] );
	    fclose ( $desc );
	}
    }

    if ( $method == 'GET' )
    {
        // Users is trying to initiate or continue
	// login.
	//
	if ( isset ( $userid ) )
	{
	    header ( "Location: problem.php" );
	    exit;
	}
	elseif ( isset ( $confirmation_time ) )
	{
	    header ( "Location: user_edit.php" );
	    exit;
	}
	// else fall through to continue login.
    }

    elseif ( $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    elseif ( isset ( $confirmation_time ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    elseif ( isset ( $userid ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    elseif ( isset ( $_POST['email'] ) )
    {
	// User answer to request for email address.
	// May be request to change email.

	$new_email = $_POST['email'];
	$e = filter_var
	    ( $new_email, FILTER_SANITIZE_EMAIL );

	if ( $new_email == "" ) /* Do nothing */;
	    // "" sent by by user typing just
	    // carriage return.
	else if ( ! filter_var
		      ( $e,
			FILTER_VALIDATE_EMAIL ) )
	    $bad_email = $new_email;
	else
	{
	    $email = $e;
	    $_SESSION['epm_email'] = $email;
	    $userid = NULL;
	    $user_admin = NULL;
	    set_userid();
	}
    }
    else if ( isset ( $_POST['confirm_tag'] ) )
    {
	if ( ! isset ( $email ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	elseif (    $_SESSION['confirm_tag']
		  == $_POST['confirm_tag'] )
	{
	    $confirmation_time = time();
	    $_SESSION['epm_confirmation_time'] =
		$confirmation_time;
	}
	else
	    $bad_confirm = true;

	log_confirmation();
    }

    if (    isset ( $confirmation_time )
         && isset ( $user_admin ) )
    {
        // Record current time as last confirmation
	// time for the user and ip address.
	//
	$ipaddr = $_SESSION['epm_ipaddr'];
	$user_admin['confirmation_time'][$ipaddr] =
	    strftime ( '%FT%T%z', $confirmation_time );
	$j = json_encode
	    ( $user_admin, JSON_PRETTY_PRINT );
	$f = "$epm_data/admin/user{$userid}.json";
	$r = file_put_contents ( $f, $j );
	if ( $r === false )
	{
	    $sysfail = "cannot write $f";
	    include 'include/sysalert.php';
	}
    }

    // This must be done after recording confirmation_
    // time as it may set $confirmation_time to an old
    // value.
    //
    if (    ! isset ( $confirmation_time )
         && isset ( $user_admin ) )
    {
	// Check if we can auto-confirm for this
	// user and ip address.
	//
	$ipaddr = $_SESSION['epm_ipaddr'];
	if ( isset ( $user_admin['confirmation_time']
	                        [$ipaddr] ) )
	{
	    $ctime = strtotime
		( $user_admin['confirmation_time']
		             [$ipaddr] );
	    if (   time()
	         < $ctime + $confirmation_interval )
	    {
	        $confirmation_time = $ctime;
		$_SESSION['epm_confirmation_time'] =
		    $confirmation_time;
		log_confirmation();
	    }
	}
    }

    if ( isset ( $confirmation_time ) )
    {
	if ( ! isset ( $userid ) )
	    header ( "Location: user_edit.php" );
	else
	{
	    if ( isset ( $userid ) )
	        $_SESSION['epm_userid'] = $userid;
	    header ( "Location: problem.php" );
	}
	exit;
    }
    else if ( isset ( $email ) )
	$_SESSION['confirm_tag'] =
	    bin2hex ( random_bytes ( 8 ) );

?>

<html>
<body>


<?php 

    $begin_form =
	"<form method='post' action='login.php';>";
    $end_form = "</form>";

    if ( ! isset ( $email ) )
    {
	// Ask for Email Address.
	//
	if ( isset ( $bad_email ) )
	    echo "<mark>EMAIL ADDRESS $bad_email WAS" .
		 " MALFORMED; TRY AGAIN</mark>" .
		 "<br>";

	echo $begin_form;
	echo "Enter:&nbsp;<input type='email'" .
	     " name='email'" .
	     " placeholder='Email Address'>";
	echo $end_form;
    }
    else
    {
	// Ask for Confirmation Number
	//.
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
	echo "Email Address: $email" .
	     "&nbsp;&nbsp;/&nbsp;&nbsp;";
	echo "IP Address: {$_SESSION['epm_ipaddr']}" .
	     "<br><br>";
	echo $begin_form;
	echo "Enter:&nbsp;<input type='text'" .
	     " name='confirm_tab'" .
	     " placeholder='Confirmation Number'>";
	echo $end_form;
	echo $begin_form;
	echo "Or Enter:&nbsp;<input type='email'" .
	     " name='email'" .
	     " placeholder='New Email Address'>";
	echo $end_form;
	echo '<br>Confirmation Number is ' . 
	     $_SESSION["confirm_tag"];

    }
?>

</body>
</html>
