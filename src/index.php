<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Nov 10 01:11:33 EST 2019

    // To set up a epm instance home directory H, given
    // that this directory is S, execute:
    //
    //		cd H
    //		cp S/index.php .
    //		ln -s S src
    //		<edit parameters in H/index.php>


    session_start();

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
    }
    header ( 'Location: src/login.php' );
    exit;
?>
