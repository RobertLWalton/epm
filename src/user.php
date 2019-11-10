<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Nov 10 01:10:35 EST 2019

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

    if ( ! is_int ( $userid ) )
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
	$i = file_get_contents
	    ( "$home/admin/email_index/$value" );
	if ( ! is_int ( $i ) ) continue;
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
        echo '<h2>Profile Edit Finished</h2><br><br>' .
	     "\n";

    echo '<h2>Email Addresses:</h2><br>' . "\n";
    foreach ( $emails as $value )
	echo "$value<br>\n";

    echo <<<EOT
    <br><br>
    Full Name: {$user['full_name']}<br><br>
    Organization: {$user['organization']}<br>br>
    Location: {$user['location']}<br>br>
    <button action="src/user_edit.php">Edit</button>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <button action="src/problem.php">
        Go To Problem</button>
EOT

?>

</body>
</html>
