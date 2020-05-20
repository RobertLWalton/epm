<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed May 20 02:35:28 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// See page/parameters.php for EPM server setup
// instructions.

// The following is included by all EPM pages using:
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
    // This is the wrong location for this file, and
    // we need to go to the right location.
    //
    header ( 'Location: index.php' );
    exit;
}

$epm_method = $_SERVER['REQUEST_METHOD'];
if ( $epm_method != 'GET'
     &&
     $epm_method != 'POST' )
    exit ( "UNACCEPTABLE HTTP METHOD $epm_method" );

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

if ( $epm_self == '/page/login.php'
     &&
     $epm_method == 'GET' )
{
    unset ( $_SESSION['EPM_BID'] );
    unset ( $_SESSION['EPM_UID'] );
    unset ( $_SESSION['EPM_RUN'] );

    $_SESSION['EPM_ID_GEN'] =
        [ random_bytes ( 16 ), random_bytes ( 16 ),
	  bin2hex
	      ( '00000000000000000000000000000000' )];
    $ID = bin2hex ( $_SESSION['EPM_ID_GEN'][0] );
}
elseif ( $epm_self != '/index.php' )
{
    $id_gen = & $_SESSION['EPM_ID_GEN'];
    $id_gen[0] = substr
        ( @openssl_encrypt
          ( $id_gen[0], 'aes-128-cbc', $id_gen[1],
	    OPENSSL_RAW_DATA, $id_gen[2] ),
	  0, 16 );
	// The @ suppresses the warning about the empty
	// iv.  openssl_encrypt returns 32 bytes, the
	// last 16 of which are an encryption of the
	// first 16.
    $ID = bin2hex ( $id_gen[0] );
}

if ( ! isset ( $_SESSION['EPM_BID'] ) )
{
    if ( $epm_self != "/page/login.php" )
    {
	header ( "Location: /page/login.php" );
	exit;
    }
}
else if ( ! isset ( $_SESSION['EPM_UID'] ) )
{
    if ( $epm_self != "/page/user.php" )
    {
	header ( "Location: /page/user.php?id=$ID" );
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
    header ( "Location: /page/run.php?id=$ID" );
    exit;
}
else if ( $epm_self == "/index.php" )
{
    header ( "Location: /page/problem.php?id=$ID" );
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
