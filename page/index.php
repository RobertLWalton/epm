<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Dec 27 03:31:30 EST 2019

    // To set up a epm instance you need the following
    // directories:
    //
    //	   R	Root directory of server.
    //	   R/W	Place you will put epm's index.html
    //	   H	The `epm' home directory containing
    //          `page', `template', etc subdirectories.
    //          Must NOT be a subdirectory of R.
    //	   D	Directory that will contain data.  This
    //		must NOT be a subdirectory of R.  Also,
    //	   	o+x permissions must be set on this dir-
    //		ectory and all its parents, because
    //		running JAVA in epm_sandbox requires
    //		that the path to the JAVA .class file
    //		be traversable by `others'.  Because of
    //		this, the last component of the name D
    //		should have a 12 digit random number in
    //		it that is unique to your installation,
    //		and the parent of this last component
    //		should have o-r permissions so the name
    //		D acts like an impenatrable password.
    //
    // You also need to put the UNIX account you are
    // using in the web server's UNIX group, denoted
    // below by `WEB-SERVERS-GROUP'.  All the files and
    // directories will be in this group, and will
    // be shared between your current account and the
    // web server.
    //
    // We assume only your account, and not the web
    // server, will have write permissions on R/W and H.
    //
    // Then to install, after populating H and creating
    // R, R/W, and D:
    //
    //		chgrp WEB-SERVERS-GROUP \
    //		      R/W `find H` `find D`
    //		chmod g+s \
    //		      R/W `find H -type d` \
    //                    `find D -type d`
    //		chmod g-w R/W `find H`
    //
    //		cd R/W
    //		cp -p H/page/index.php .
    //		chmod u+w index.php
    //		ln H/page .
    //		<edit parameters in R/W/index.php>

    $script_name = $_SERVER['SCRIPT_FILENAME'];
    $script_dir = dirname ( $script_name );

    if ( basename ( $script_dir ) == 'page' )
    {
        // This is the unedited index.html and
	// we should go to the edited version.

	$root = $_SERVER['DOCUMENT_ROOT'];
	$host = $_SERVER['HTTP_HOST'];
	$n = strlen ( $root );
	$check = substr ( $script_dir, 0, $n );
	if ( $check != $root )
	{
	    echo ( "SCRIPT_NAME: $script_name<br>\n" );
	    echo "SCRIPT_DIR: $script_dir<br>\n";
	    echo "ROOT: $root<br>\n";
	    echo "HOST: $host<br>\n";
	    echo "N: $n<br>\n";
	    echo "CHECK: $check<br>\n";
	    exit ( 'WRONG PAGE; RETYPE' );
	}
	$tail = substr ( $script_dir, $n );
	$tail = dirname ( $tail );

	$_SESSION = array();
	session_destroy();
	header ( "Location: http://$host/$tail" );
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
	// WARNING: this is only a test setting;
	//          reset this to D above.

    $_SESSION['epm_home'] =
	dirname ( $_SERVER['DOCUMENT_ROOT'] );
	// WARNING: this is only a test setting;
	//          reset this to E above.

    header ( "Location: page/login.php" );
    exit;
?>
