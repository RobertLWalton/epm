<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 13:52:15 EST 2019

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
    if ( ! isset ( $_SESSION['epm_data'] ) )
        exit ( 'SYSTEM ERROR: epm_data not set' );
    $data = $_SESSION['epm_data'];

    include 'include/debug_info.php';

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }
    if ( ! is_writable ( "$data/admin/email_index" ) )
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

    $desc = opendir ( "$data/admin/email_index" );
    if ( ! $desc )
        exit ( 'SYSTEM ERROR: cannot open' .
	        " $data/admin/email_index" );
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
	    "$data/admin/email_index/$value";
	$i = file_get_contents ( $email_file );
	if ( ! preg_match
		   ( '/^[1-9][0-9]*$/', $i ) )
	{
	    $sysalert = "bad value in $email_file";
	    include 'include/sysalert.php';
	    continue;
	}
	if ( $i == $userid )
	    $emails[] = $value;
    }

    $user_file = "$data/admin/user{$userid}.json";
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

    echo "Email Addresses:<br>\n";
    echo '<ul style="list-style-type:none">' . "\n";
    foreach ( $emails as $value )
	echo "<li>$value</li>\n";
    echo "</ul>\n";
    

    echo <<<EOT
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
