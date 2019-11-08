<?php

    // File:	first_user.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Nov  8 08:19:23 EST 2019

    // Asks user if they are the first user.  If yes
    // makes the following directories and then goes
    // to user_edit.php.
    //
    //		admin/
    //		admin/email_index/
    //		users/

    session_start();
    clearstatcache();

    if (    is_writable ( 'admin' )
         && is_writable ( 'admin/email_index' )
         && is_writable ( 'users' ) )
    {
        header ( 'Location: src/user.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ( $method == 'POST' )
    {
        if ( isset ( $_POST['is_administrator'] ) )
	{
	    $is_administrator = $_POST['is_administrator'];
	    if ( $is_administrator == 'yes' )
	    {
		mkdir ( 'admin', 0750 );
		mkdir ( 'admin/email_index', 0750 );
		mkdir ( 'users', 0750 );
		header
		    ( 'Location: src/user_edit.php' );
		exit;
	    }
	    error ( 'SYSTEM ERROR: admin directories' .
	            ' not set up' );
	}
	error ( 'UNACCEPTABLE POST' );
    }
    else if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

?>

<html>
<body>

Are you the administrator of this system?
&nbsp;&nbsp;&nbsp;&nbsp;
    <button type="submit"
            formmethod="post"
	    name="is_administrator"
	    value="no">NO</button>
&nbsp;&nbsp;&nbsp;&nbsp;
    <button type="submit"
            formmethod="post"
	    name="is_administrator"
	    value="yes">YES</button>
                      action
</body>
</html>
