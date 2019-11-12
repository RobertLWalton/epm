<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 02:27:41 EST 2019

    // To set up a epm instance home directory H, given
    // that this directory is S, execute:
    //
    //	        mkdir H
    //		chgrp WEB-SERVERS-GROUP H
    //		chmod g+s H
    //		chgrp WEB-SERVERS-GROUP `find S`
    //		cd H
    //		cp S/index.php .
    //		ln -s S src
    //		<edit parameters in H/index.php>


    session_start();

    $src = "src";
	// Directory containing page sources.
	// May be relative epm_home.


    if ( ! isset ( $_SESSION['epm_home'] ) )
    {
	$_SESSION['epm_home'] = getcwd();

	// Parameters:
	//
        $_SESSION['confirmation_interval'] =
	    30 * 24 * 60 * 60;
	    // Interval in seconds that confirmation
	    // will be valid for a given email address
	    // and ip address.  Default, 30 days.

        $_SESSION['max_emails'] = 3;
	    // Maximum number of emails a user may have.

	header ( "Location: $src/login.php" );
    }
    else
	header ( "Location: user.php" );

    exit;
?>
