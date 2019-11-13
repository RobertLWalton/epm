<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 19:01:00 EST 2019

    // To set up a epm instance you need the following
    // directories:
    //
    //		R	Root directory of server.
    //		R/W	Place you will put epm's
    //			index.html
    //		S	This directory containing
    //			page .php files.
    //		D	Directory that will contain
    //			data.  This must NOT be a
    //			subdirectory of R.
    //
    // You also need to put the UNIX account you are
    // using in the web server's UNIX group, denoted
    // below by `WEB-SERVERS-GROUP'.  All the files and
    // directories will be in this group, and will
    // be shared between your current account and the
    // web server.
    //
    // We assume only your account, and not the web
    // server, will have write permissions on R/W and S.
    //
    // Then to install, populate S with the epm source
    // files and execute:
    //
    //		chgrp WEB-SERVERS-GROUP \
    //		      R/W `find S` `find D`
    //		chmod g+s \
    //		      R/W `find S -type d` \
    //                    `find D -type d`
    //		chmod g-w R/W `find S`
    //
    //		cd R/W
    //		cp -p S/index.php .
    //		ln -s S src
    //		<edit parameters in H/index.php>

    // The directory containing the page sources
    // MUST be linked to R/W/src.

    $script_name = $_SERVER['SCRIPT_NAME'];

    if ( basename ( $script_name ) == 'src' )
    {
        // This is the unedited index.html and
	// we should go to the edited version.

	header ( "Location: ../index.php" );
	exit;
    }

    session_start();
    foreach ( array_keys ( $_SESSION ) as $key )
        unset ( $_SESSION[$key] );

    // Parameters:
    //
    $_SESSION['epm_data'] =
	dirname ( $_SERVER['DOCUMENT_ROOT'] ) .
	'/data';

    $_SESSION['epm_confirmation_interval'] =
	30 * 24 * 60 * 60;
	// Interval in seconds that confirmation
	// will be valid for a given email address
	// and ip address.  Default, 30 days.

    $_SESSION['epm_max_emails'] = 3;
	// Maximum number of emails a user may have.

    header ( "Location: src/login.php" );
    exit;
?>
