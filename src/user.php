<?php

    // File:	user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov  5 06:50:30 EST 2019

    // Edit admin/user{$userid}.json file containing
    // information about user.  Also set up directories
    // for new user, and system directories for first
    // user.

    session_start();

    if ( ! array_key_exists ( 'userid', $_SESSION ) )
    {
	header ( "Location: /src/index.php" );
	exit;
    }

    exit ( 'user.php not finished yet' );

    $userid = $_SESSION['userid'];
    $email = $_SESSION['email'];
    $ipaddr = $_SESSION['ipaddr'];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
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
    }
?>

<html>
<body>


</body>
</html>
