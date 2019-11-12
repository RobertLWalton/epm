<?php

    // File:	first_user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 12:03:41 EST 2019

    // Asks user if they are the first user.  If yes
    // makes the following directories and then goes
    // to user_edit.php.
    //
    //		admin/
    //		admin/email_index/
    //		users/

    session_start();
    clearstatcache();
    if ( ! isset ( $_SESSION['epm_data'] ) )
        exit ( 'SYSTEM ERROR: epm_data not set' );
    $data = $_SESSION['epm_data'];

    if (    is_writable ( "$data/admin" )
         && is_writable ( "$data/admin/email_index" )
         && is_writable ( "$data/users" ) )
    {
        header ( 'Location: user.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ( $method == 'POST' )
    {
        if ( isset ( $_POST['is_administrator'] ) )
	{
	    $is_administrator =
	        $_POST['is_administrator'];
	    if ( $is_administrator == 'yes' )
	    {
		mkdir ( "$data/admin", 0770 );
		mkdir ( "$data/admin/email_index",
		        0770 );
		mkdir ( "$data/users", 0770 );
		header
		    ( 'Location: user_edit.php' );
		exit;
	    }
	    exit ( 'SYSTEM ERROR: admin directories' .
	            ' not set up;' .
		    ' contact administrator' );
	}
	exit ( 'UNACCEPTABLE POST' );
    }
    else if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

?>

<html>
<body>

Are you the administrator of this system?
&nbsp;&nbsp;&nbsp;&nbsp;
    <form method='POST' action='first_user.php'>
    <button type="submit"
	    name="is_administrator"
	    value="no">NO</button>
&nbsp;&nbsp;&nbsp;&nbsp;
    <button type="submit"
	    name="is_administrator"
	    value="yes">YES</button>
    </form>
</body>
</html>
