<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 00:33:38 EST 2019

    // Displays files:
    //
    //		admin/email_index/*
    //		admin/user{$userid}.json
    //
    // containing information about user.  Gives the
    // user the option of going to user_edit.php or
    // problem.php.

    session_start();
    clearstatcache();
    if ( ! isset ( $_SESSION['epm_home'] ) )
        exit ( 'SYSTEM ERROR: epm_home not set' );
    $home = $_SESSION['epm_home'];

    include '../include/debug_info.php';

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

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

    $userid = $_SESSION['userid'];
    $email = $_SESSION['email'];
    $confirmation_time = $_SESSION['confirmation_time'];

    if ( $userid == 'NEW' )
    {
	header ( "Location: user_edit.php" );
	exit;
    }

    // Set $emails to the emails in admin/email_index
    // that point at $userid.
    //
    $emails = [];

    $desc = opendir ( "$home/admin/email_index" );
    if ( ! $desc )
        exit ( 'SYSTEM ERROR: cannot open' .
	        " $home/admin/email_index" );
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
	$email_file =
	    "$home/admin/email_index/$value";
	$i = file_get_contents ( $email_file );
	if ( ! preg_match
		   ( '/^[1-9][0-9]*$/', $i ) )
	{
	    $sysalert = "bad value in $email_file";
	    include '../include/sysalert.php';
	    continue;
	}
	if ( $i == $userid )
	    $emails[] = $value;
    }

    $user_file = "$home/admin/user{$userid}.json";
    $user = file_get_contents ( $user_file );
    if ( ! $user )
	exit ( 'SYSTEM ERROR: cannot read ' .
		$user_file );
    $user = json_decode ( $user, true );
    if ( ! $user )
	exit ( 'SYSTEM ERROR: cannot parse ' .
		$user_file );

?>

<html>
<body>

<?php 

    if ( $_GET['done'] == "yes" )
        echo "<mark>" .
	     "Profile Edit Finished" .
	     "</mark><br><br>\n";

    echo 'Email Addresses:<br>' . "\n";
    foreach ( $emails as $value )
	echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .
	     "$value<br>\n";
    

    echo <<<EOT
    <br><br>
    Full Name: {$user['full_name']}<br>
    Organization: {$user['organization']}<br>
    Location: {$user['location']}<br><br>
EOT

?>

<form>
<input type="submit" formaction="user_edit.php"
       value="Edit">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" formaction="problem.php"
       value="Go To Problem"</input>
</form>

</body>
</html>
