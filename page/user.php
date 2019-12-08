<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Dec  8 05:11:46 EST 2019

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
    umask ( 07 );

    if ( ! isset ( $_SESSION['epm_userid'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }

    $epm_data = $_SESSION['epm_data'];

    // include 'include/debug_info.php';

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( ! is_writable
               ( "$epm_data/admin/email_index" ) )
    {
	header ( "Location: first_user.php" );
	exit;
    }

    $userid = $_SESSION['epm_userid'];
    $email = $_SESSION['epm_email'];

    // Set $emails to the emails in admin/email_index
    // that point at $userid.
    //
    $emails = [];

    $desc = opendir ( "$epm_data/admin/email_index" );
    if ( ! $desc )
        exit ( 'SYSTEM ERROR: cannot open' .
	        " $epm_data/admin/email_index" );
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
	    "$epm_data/admin/email_index/$value";
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

    $user_file = "$epm_data/admin/user{$userid}.json";
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

    if ( isset ( $_GET['done'] ) )
        echo "<mark>" .
	     "Profile Edit Finished" .
	     "</mark>\n";

    echo "<h3>User Profile</h3>\n";
    echo "<b>Email Addresses:</b><br>\n";
    echo '<table style="margin-left:20px">' . "\n";
    foreach ( $emails as $value )
	echo "<tr><td>$value</td></tr>\n";
    echo "</table>\n";
    

    echo <<<EOT
    <table>
    <tr><td><b>Full Name:</b></th><td> {$user['full_name']}<td><tr>
    <tr><td><b>Organization:</b></th><td> {$user['organization']}<td><tr>
    <tr><td><b>Location:</b></th><td> {$user['location']}<td><tr>
EOT

?>

<form>
<table><tr>
<td><input type="submit" formaction="user_edit.php"
       value="Edit"></td>
<td><input type="submit" formaction="problem.php"
       value="Go To Problem"</input></td>
</tr></table>
</form>

</body>
</html>
