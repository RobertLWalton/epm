<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Mar 26 01:22:17 EDT 2020

// Per web site EPM parameters.  An edited version of
// this file located in the $_SERVER['DOCUMENT_ROOT']
// directory is `required' at the beginning of all EPM
// pages via:
//
//    require "{$_SERVER['DOCUMENT_ROOT']}/index.html"

// To set up an EPM instance you need the following
// directories:
//
//     R	$_SERVER['DOCUMENT_ROOT'].  Directory
//		in which you place an edited copy of
//		this file.
//     H	The `epm' home directory containing
//           	`page', `template', etc subdirectories.
//           	Must NOT be a descendant of R.
//     D	Directory that will contain data.  This
//		must NOT be a descendant of R.  Also,
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
// You also need to put the UNIX account you are using
// in the web server's UNIX group, denoted below by
// `WEB-SERVERS-GROUP'.  All the files and directories
// will be in this group, and will be shared between
// your current account and the web server.  Ancestor
// directories for these files and directories must
// also be in this group and have g+x permission, unless
// they have a+x permission.
//
// Only your account, and not the web server, should
// have write permissions on R and H.
//
// Then to install, after populating H and creating
// R and D:
//
//	chgrp WEB-SERVERS-GROUP \
//	      R `find H` D
//	chmod g+s \
//	      R `find H -type d` D
//	chmod g-w R `find H`
//	chmod g+w D
//
//	cd R
//	ln -s H/page .
//	cp -p H/page/index.php .
//	chmod u+w index.php
//	<edit parameters in R/index.php>

$epm_web = $_SERVER['DOCUMENT_ROOT'];

$epm_self = $_SERVER['PHP_SELF'];
if ( $epm_self == '/page/index.php' )
{
    // This is the unedited version of the file, and
    // we need to go to the edited version.
    //
    header ( 'Location: index.php' );
    exit;
}

require "parameters.php";

// The rest of this file is code that is included at the
// start of all EPM PHP pages.

session_name ( $epm_session_name );
session_start();
clearstatcache();
umask ( 07 );
header ( 'Cache-Control: no-store' );

if ( ! isset ( $_SESSION['EPM_IPADDR'] ) )
{
    $_SESSION['EPM_IPADDR'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['EPM_SESSION_TIME'] =
        strftime ( "%FT%T%z",
	           $_SERVER['REQUEST_TIME'] );
    file_put_contents (
        "$epm_data/error.log",
	"NEW_SESSION {$_SESSION['EPM_SESSION_TIME']}" .
	" {$_SESSION['EPM_IPADDR']}" .
	" {$_SERVER['REMOTE_HOST']}" . PHP_EOL,
	FILE_APPEND );
}
else if (    $_SESSION['EPM_IPADDR']
          != $_SERVER['REMOTE_ADDR'] )
    error ( 'UNACCEPTABLE SESSION IPADDR CHANGE' );
    // A hacker who intercepts the session cookie can
    // try to hijack the session, but will likely not
    // have the same IP address as the session, so this
    // will stop the hack.  On the other hand, it might
    // disrupt laptops that are moving between wireless
    // cells.

if ( ! isset ( $_SESSION['EPM_BID'] ) )
{
    if ( $epm_self != "/page/login.php" )
    {
	header ( 'Location: /page/login.php' );
	exit;
    }
}
else if ( ! isset ( $_SESSION['EPM_UID'] ) )
{
    if ( $epm_self != "/page/user.php" )
    {
	header ( 'Location: /page/user.php' );
	exit;
    }
}
else if ( $epm_self == "/index.php" )
{
    header ( 'Location: /page/problem.php' );
    exit;
}

if ( $epm_debug )
{
    $epm_debug_desc = fopen
	( "$epm_data/debug.log", 'a' );
    $epm_debug_base = pathinfo
	( $epm_self, PATHINFO_BASENAME );

    function DEBUG ( $message )
    {
	global $epm_debug_desc, $epm_debug_base;
	fwrite ( $epm_debug_desc,
	         "$epm_debug_base: $message" .
		 PHP_EOL );
	// There is NO programmatic way to flush
	// the write buffer.  Best way is to
	// open another window on the server,
	// which tends to flush the buffers for
	// previously opened windows.
    }
}
else
{
    function DEBUG ( $message ) {}
}

function WARN ( $message )
{
    trigger_error ( $message, E_USER_WARNING );
}

// ERROR does NOT return (as per error handler below).
//
function ERROR ( $message )
{
    trigger_error ( $message, E_USER_ERROR );
}

function EPM_ERROR_HANDLER
	( $errno, $message, $file, $line )
{
    global $epm_data;

    if ( error_reporting() == 0 )
        return true;
	// Return if @ operator has suppressed all
	// error handling.  Returning true suppresses
	// normal error handling.

    if ( $errno & ( E_USER_NOTICE |
                    E_USER_WARNING ) )
        $class = 'USER';
    elseif ( $errno & E_USER_ERROR )
        $class = 'EPM';
    else
        $class = 'SYSTEM';

    $fatal = false;
    if ( $errno & ( E_WARNING |
                    E_USER_WARNING ) )
        $class .= '_WARNING';
    elseif ( $errno & ( E_NOTICE |
                        E_USER_NOTICE ) )
        $class .= '_NOTICE';
    else
    {
        $class .= '_ERROR';
	$fatal = true;
    }

    file_put_contents (
        "$epm_data/error.log",
	"$class $errno [$file:$line] $message" .
	PHP_EOL, FILE_APPEND );

    if ( $fatal )
        exit ( "<pre>$message</pre>" );

    return true;
        // Returning true suppresses normal error
	// handling.
}

set_error_handler ( 'EPM_ERROR_HANDLER' );

// Returns HTML for a help button that goes to the
// specified item in the help.html file.  Within
// HTML call this with:
//
function HELP ( $item )
{
    return "<button type='button'" .
           " onclick='window.open(" .
	   "\"/page/help.html#$item\"," .
	   "\"EPM HELP\"," .
	   "\"height=800px,width=800px\")'>" .
	   "?</button>";
}

?>
