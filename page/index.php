<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Fri May  1 03:12:40 EDT 2020

// See page/parameters.php for EPM server setup
// instructions.

// The following is included by all pages using:
//
//    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";
//
// DO NOT edit his page.  Edit
//
//    {$_SERVER['DOCUMENT_ROOT']}/parameters.php
//
// instead, which is included by this page.

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
        strftime ( $epm_time_format,
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
else if ( isset ( $_SESSION['EPM_RUN']['RESULT'] )
         &&
	 $_SESSION['EPM_RUN']['RESULT'] === true
	 &&
         $epm_self != "/page/run.php" )
{
    // Run still running.
    //
    header ( 'Location: /page/run.php' );
    exit;
}
else if ( $epm_self == "/index.php" )
{
    header ( 'Location: /page/problem.php' );
    exit;
}

// The rest of this file consists of functions that
// most pages need to be defined.

// Do what PHP symlink should do, but PHP symlink is
// known to fail sometimes for no good reason (see
// comments on PHP documentation site; this behavior has
// also been observed in EPM testing).
//
function symbolic_link ( $target, $link )
{
    return exec ( "ln -s $target $link 2>&1" ) == '';
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
