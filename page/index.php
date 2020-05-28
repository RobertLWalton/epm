<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu May 28 11:32:06 EDT 2020

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


$epm_method = $_SERVER['REQUEST_METHOD'];
if ( $epm_method != 'GET'
     &&
     $epm_method != 'POST' )
    exit ( "UNACCEPTABLE HTTP METHOD $epm_method" );

if ( $epm_self == "/index.php"
     ||
     $epm_self == '/page/index.php' )
{
    if ( $epm_message == 'POST' )
	exit ( "UNACCEPTABLE HTTP POST" );

    // Redirect GETs to this page using either of
    // its names to login.php.
    //
    header ( "Location: /page/login.php" );
    exit;
}

require "parameters.php";

// The rest of this file is code that is included at the
// start of *ALL* EPM PHP pages for both GETs and POSTs.

session_name ( $epm_session_name );
session_start();
clearstatcache();
umask ( 07 );
header ( 'Cache-Control: no-store' );

// First functions that most pages need defined.

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

    $stack = debug_backtrace
        ( DEBUG_BACKTRACE_IGNORE_ARGS );
    $m = "$class $errno $message" . PHP_EOL;
    foreach ( $stack as $line )
    {
        $f = $line['file'];
	if ( $f == '' ) continue;
	if ( preg_match ( '#/index.php$#', $f ) )
	    continue;
        $m .= "    $f:{$line['line']}"
	    . PHP_EOL;
    }
    file_put_contents
        ( "$epm_data/error.log", $m, FILE_APPEND );

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

// Locks directory.  For LOCK_EX lock, stores microtime
// into directory/+lock+ and returns the microtime.
// For LOCK_SH lock, read directory/+lock+ and returns
// its value.  In this case returns 0 if directory/
// +lock+ does not exist.
//
// The lock is released by UNLOCK or on shutdown.
//
$epm_lock = NULL;
function LOCK ( $dir, $type )
{
    global $epm_data, $epm_lock;

    if ( isset ( $epm_lock ) )
        ERROR ( "double locking" );
    $f = "$dir/+lock+";
    $epm_lock = fopen ( "$epm_data/$f", 'w+' );
    if ( $epm_lock === false )
        ERROR ( "cannot open $f" );
    $r = flock ( $epm_lock, $type );
    if ( $r === false )
        ERROR ( "cannot lock $f" );
    if ( $type == LOCK_EX )
    {
        $time = strval ( microtime ( true ) );
	fwrite ( $epm_lock, $time );
    }
    else
    {
        $time = fread ( $epm_lock, 100 );
	if ( floatval ( $time ) == 0 )
	    ERROR ( "bad value `$time' read from $f" );
    }
    return $time;
}

function UNLOCK()
{
    global $epm_lock;

    if ( ! isset ( $epm_lock ) ) return;
    flock ( $epm_lock, LOCK_UN );
    $epm_lock = NULL;
}
register_shutdown_function ( 'UNLOCK' );



// Check that we have not skipped a page.
//
if ( ! isset ( $_SESSION['EPM_BID'] )
     &&
     $epm_self != "/page/login.php" )
    exit ( 'UNACCEPTABLE HTTP GET/POST' );
else if ( ! isset ( $_SESSION['EPM_UID'] )
	  &&
	  $epm_self != "/page/login.php"
	  &&
	  $epm_self != "/page/user.php" )
    exit ( 'UNACCEPTABLE HTTP GET/POST' );

// Enforce session GET/POST request sequencing.
//
// The requests of a session are sequenced using $ID
// which steps through pseudo-random sequence numbers.
// A request out of sequence is rejected.
//
if ( $epm_self == '/page/login.php'
     &&
     $epm_method == 'GET' )
{
    // Initialize session sequencing on login.php GET.

    session_unset();

    $_SESSION['EPM_ID_GEN'] =
        [ random_bytes ( 16 ), random_bytes ( 16 ),
	  bin2hex
	      ( '00000000000000000000000000000000' )];
    $ID = bin2hex ( $_SESSION['EPM_ID_GEN'][0] );

    $_SESSION['EPM_IPADDR'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['EPM_SESSION_TIME'] =
        strftime ( $epm_time_format,
	           $_SERVER['REQUEST_TIME'] );
    file_put_contents (
        "$epm_data/error.log",
	"NEW_SESSION {$_SESSION['EPM_SESSION_TIME']}" .
	" {$_SESSION['EPM_IPADDR']}" . PHP_EOL,
	FILE_APPEND );
}
elseif ( ! isset ( $epm_is_subwindow ) )
{
    // Check sequencing on everything BUT login.php GET
    // and read-only subwindows.

    $id_gen = & $_SESSION['EPM_ID_GEN'];
    $ID = bin2hex ( $id_gen[0] );
    if ( ! isset ( $_REQUEST['id'] ) )
	WARN ( "$epm_self: no id, ID=$ID" );
    elseif ( $_REQUEST['id'] != $ID )
	WARN ( "$epm_self id = {$_REQUEST['id']}" .
	       " != $ID = ID" );

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

// Each user can have only one session at a time.  When
// started, each session for a user aborts the previous
// session for the user.  As there is no way to know
// when a session has ended, there is no way to know if
// this session has aborted a previous session.  The
// previous session finds out here that it has been
// aborted.
//
// When a session EPM_UID is set, the session_id is
// written to S = "admin/users/UID/session_id"
// and the mod-time of S identifies the session.
//
if ( isset ( $_SESSION['EPM_SESSION'] ) )
{
    $epm_session = & $_SESSION['EPM_SESSION'];
        // This is [S,S-MOD-TIME].
    if ( $epm_session[1] != filemtime
             ( "$epm_data/{$epm_session[0]}" ) )
	exit ( 'SESSION ABORTED BY LATER SESSION' );
}

?>
