<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Dec 22 00:38:57 EST 2019

    // Displays files:
    //
    //		admin/email_index/*
    //		admin/user{$userid}.info
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
    if (    $_SESSION['epm_ipaddr']
	 != $_SERVER['REMOTE_ADDR'] )
        exit ( 'UNACCEPTABLE IPADDR CHANGE' );

    $epm_data = $_SESSION['epm_data'];
    $epm_root = $_SESSION['epm_root'];
    $include = "$epm_root/include";

    // require "$include/debug_info.php";

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
    $emails[] = $email;

    $d = "admin/email_index";
    $desc = opendir ( "$epm_data/$d" );
    if ( ! $desc )
        exit ( 'SYSTEM ERROR: cannot open $d' );
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
	$f = "admin/email_index/$value";
	$i = file_get_contents ( "$epm_data/$f" );
	if ( ! preg_match
		   ( '/^[1-9][0-9]*$/', $i ) )
	{
	    $sysalert = "bad value $i in $f";
	    require "$include/sysalert.php";
	    continue;
	}
	if ( $i == $userid && $value != $email )
	    $emails[] = $value;
    }

    $f = "admin/user{$userid}.info";
    $c = file_get_contents ( "$epm_data/$f" );
    if ( $c === false )
	exit ( "SYSTEM ERROR: cannot read $f" );
    $c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	// Get rid of `//...' comments.
    $user_admin = json_decode ( $c, true );
    if ( $user_admin == NULL )
    {
	$m = json_last_error_msg();
	$sysfail =
	    "cannot decode json in $f:\n    $m";
	require "$include/sysalert.php";
    }
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
    

    $full_name = $user_admin['full_name'];
    $organization = $user_admin['organization'];
    $location = $user_admin['location'];
    echo <<<EOT
    <table>
    <tr><td><b>Full Name:</b></td>
        <td>$full_name<td><tr>
    <tr><td><b>Organization:</b></td>
        <td>$organization<td><tr>
    <tr><td><b>Location:</b></td>
        <td>$location<td><tr>
    </table>
EOT;

?>

<form>
<input type="submit" formaction="user_edit.php"
       value="Edit">&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" formaction="problem.php"
       value="Go To Problem"</input>
</form>

</body>
</html>
