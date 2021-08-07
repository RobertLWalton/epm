<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Aug  7 16:16:15 EDT 2021

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// See page/maintenance_parameters.php for EPM server
// setup instructions.

// The following is included by all EPM pages using:
//
//    require __DIR__ . '/index.php';
//
// DO NOT edit his page.  Edit
//
//    $epm_web/parameters.php
//
// instead, which is included by this page.


$epm_method = $_SERVER['REQUEST_METHOD'];
if ( $epm_method != 'GET'
     &&
     $epm_method != 'POST' )
    exit ( "UNACCEPTABLE HTTP METHOD $epm_method" );


$epm_self = '';
$epm_root = $_SERVER['PHP_SELF'];
if ( preg_match ( '#^(.*)(/page/.*)$#',
                  $epm_root, $matches ) )
{
    $epm_root = $matches[1];
    $epm_self = $matches[2];
}
elseif ( preg_match ( '#^(.*)(/index\.php)$#',
                      $epm_root, $matches ) )
{
    $epm_root = $matches[1];
    $epm_self = $matches[2];
}
$epm_web = $_SERVER['DOCUMENT_ROOT'];
$epm_web .= $epm_root;

// Redirect GETs to this page using either of its names
// to login.php.
//
if ( $epm_self == "/index.php"
     ||
     $epm_self == '/page/index.php' )
{
    if ( $epm_method == 'POST' )
	exit ( "UNACCEPTABLE HTTP POST" );

    header ( "Location: $epm_root/page/login.php" );
    exit;
}

if ( ! is_readable ( "$epm_web/parameters.php" ) )
    exit ( "UNACCEPTABLE HTTP $epm_method: PARAM" );

require "$epm_web/parameters.php";

// The rest of this file is code that is included at the
// start of *ALL* EPM PHP pages for both GETs and POSTs.

session_name ( $epm_session_name );
session_start();
clearstatcache();
umask ( 07 );
header ( 'Cache-Control: no-store' );

$epm_begin_time = microtime ( true );

if ( isset ( $_SESSION['EPM_THROTTLE'] ) )
{
    // See parameters.php for documentation.
    //
    $d = & $_SESSION['EPM_THROTTLE'];
    $t = & $d[0];
    $s_short = & $d[1];
    $s_long = & $d[2];
    $dt = $epm_begin_time - $t;
    $t = $epm_begin_time;
    $s_long =
        $dt + $epm_long_time_constant * $s_long;
    $s_short =
        $dt + $epm_short_time_constant * $s_short;

    $delay = 0;
    if ( $s_long < $epm_long_limit )
        $delay = $epm_long_delay;
    if ( $s_short < $epm_short_limit
         &&
	 $delay < $epm_short_delay )
        $delay = $epm_short_delay;
    if ( $delay > $dt )
        usleep ( (int) ( 1000000 * ( $delay - $dt ) ) );

    // These need to be cleared least they be set by
    // pages.
    //
    unset ( $d );
    unset ( $t );
    unset ( $s_long );
    unset ( $s_short );
}

// A session cannot change its IP address if
// $epm_check_ipaddr is true (see parameters.php).
//
if ( $epm_check_ipaddr
     &&
     isset ( $_SESSION['EPM_IPADDR'] )
     &&
        $_SESSION['EPM_IPADDR']
     != $_SERVER['REMOTE_ADDR'] )
    exit ( "UNACCEPTABLE HTTP $epm_method: IP" );

// Check that we have not skipped proper login,
// and set parameter variables if we have done
// login.
//
if ( ! isset ( $_SESSION['EPM_AID'] ) )
{
    if ( $epm_self != "/page/login.php"
         &&
	 $epm_self != "/page/user.php" )
	exit ( "UNACCEPTABLE HTTP $epm_method: SKIP" );

    $rw = true;
    $RW_BUTTON = '';
        // Settings suitable for new user login.
	// These are ignored by login.php, but may be
	// used by user.php.
}
else
{
    $aid = $_SESSION['EPM_AID'];
    $uid = $_SESSION['EPM_UID'];
    $lname = $_SESSION['EPM_EMAIL'];
    if ( $aid != $uid ) $lname = "$aid:$lname";

    $is_team = $_SESSION['EPM_IS_TEAM'];
    if ( $is_team )
    {
	$rw_file = "admin/teams/$aid/+rw+";
        $rw_handle = fopen
	    ( "$epm_data/$rw_file", "c+" );
	flock ( $rw_handle, LOCK_EX );
        $c = trim ( fread ( $rw_handle, 1000 ) );
	    // For some reason we need to
	    // trim this.
        $rw = ( $c == $uid );
	function rw_unlock()
	{
	    global $rw_handle;
	    flock ( $rw_handle, LOCK_UN );
	    fclose ( $rw_handle );
	}
	if ( $rw )
	    register_shutdown_function
	        ( 'rw_unlock' );
		// The lock will be held till the
		// end of the transaction.
	else
	    rw_unlock();
    }
    else
        $rw = ( $aid == $uid );


    // $RW_BUTTON must be inside a form with action set
    // to the appropriate page.
    // 
    $RW_BUTTON_RO = <<<EOT
    <button type='submit' name='rw' value='ro'
	    id='rw-button'
	    formmethod='POST'
	    title='current mode is read-write;
click to change to read-only'>
	    RO</button>
EOT;
    $RW_BUTTON_RW = <<<EOT
    <button type='submit' name='rw' value='rw'
	    id='rw-button'
	    formmethod='POST'
	    title='current mode is read-only;
click to change to read-write'>
	    RW</button>
EOT;

    if ( ! $is_team )
        $RW_BUTTON = '';
    elseif ( $rw )
	$RW_BUTTON = $RW_BUTTON_RO;
    else
	$RW_BUTTON = $RW_BUTTON_RW;

}

// Each user can have only one session at a time.  When
// started, each session for a user aborts the previous
// session for the user.  As there is no way to know
// when a session has ended, there is no way to know if
// this session has aborted a previous session.  The
// previous session finds out here that it has been
// aborted.
//
// When a session EPM_AID is set, a login log entry is
// written to a log file S and the mod-time of S
// identifies the session.
//
if ( isset ( $_SESSION['EPM_ABORT'] ) )
{
    $epm_session = & $_SESSION['EPM_ABORT'];
        // This is [S,S-MOD-TIME].
    $our_time = $epm_session[1];
    $cur_time = filemtime
        ( "$epm_data/{$epm_session[0]}" );
    if ( $our_time != $cur_time )
        require "$epm_home/include/epm_abort.php";
	// This does not return.
}

// First functions that most pages need defined.

// Do what PHP symlink should do, but PHP symlink is
// known to fail sometimes for no good reason (see
// comments on PHP documentation site; this behavior has
// also been observed in EPM testing).
//
// Also unlink $link before remaking it (as per
// ln -snf).
//
function symbolic_link ( $target, $link )
{
    return exec ( "ln -snf $target $link 2>&1" ) == '';
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
    global $epm_data, $epm_self;

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
    if ( isset ( $_SESSION['EPM_AID'] ) )
        $m = $_SESSION['EPM_AID'];
    elseif ( isset ( $_SESSION['EPM_EMAIL'] ) )
        $m = $_SESSION['EPM_EMAIL'];
    else
        $m = 'UNKNOWN';
    if ( isset ( $_SESSION['EPM_TIME'] ) )
        $m .= '(' . $_SESSION['EPM_TIME'] . ')';
    $m = "$class $errno $epm_self $m" . PHP_EOL
       . "  $message" . PHP_EOL;
    foreach ( $stack as $line )
    {
	if ( ! isset ( $line['file'] ) ) continue;
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
        exit ( "<div style='background-color:yellow'>" .
	       "FATAL ERROR:<br>" .
	       "<pre>$message</pre>" .
	       "</div>" );

    return true;
        // Returning true suppresses normal error
	// handling.
}

set_error_handler ( 'EPM_ERROR_HANDLER' );

// DEBUG, LOCK, ATOMIC_READ, and ATOMIC_WRITE functions
// are in parameters.php because they are shared with
// bin/epm_run.

// If this is a download page, return to that
// page and skip the rest of this file.
//
if ( $epm_page_type == '+download+' ) return;

// Enforce session GET/POST request sequencing.
//
// The requests of a session are sequenced using $ID
// which steps through pseudo-random sequence numbers.
// A request out of sequence is rejected.
//
if ( ! in_array ( $epm_page_type,
                  ['+no-post+','+download+'] ) )
{
    if ( $epm_page_type == '+problem+' )
    {
        if ( ! isset ( $_REQUEST['problem'] ) )
	    exit ( "UNACCEPTABLE HTTP $epm_method:" .
	           " PROBLEM" );
	$id_type = $_REQUEST['problem'];
    }
    else
        $id_type = $epm_page_type;

    if ( $epm_method == 'GET' )
	$_SESSION['EPM_PAGE'][$id_type] =
	    [ "SELF" => $epm_self, "STATE" => "normal"];
    elseif ( ! isset ( $_SESSION['EPM_PAGE']
                                [$id_type]['SELF'] ) )
	exit ( "UNACCEPTABLE HTTP $epm_method:" .
	       " NO PAGE" );
    elseif (    $_SESSION['EPM_PAGE'][$id_type]['SELF']
             != $epm_self )
	exit ( "UNACCEPTABLE HTTP $epm_method: PAGE" );
    $data = & $_SESSION['EPM_PAGE'][$id_type];
    $state = & $data['STATE'];

    if ( isset ( $epm_ID_init )
         &&
         $epm_method == 'GET' )
    {
	require "$epm_home/include/epm_random.php";
	    $_SESSION['EPM_ID_GEN'][$id_type] =
		init_id_gen();
	    $ID = bin2hex
		( $_SESSION['EPM_ID_GEN']
		           [$id_type][0] );
    }
    elseif ( ! isset ( $_SESSION['EPM_ID_GEN']
			        [$id_type] ) )
	exit ( "UNACCEPTABLE HTTP $epm_method:" .
	       " ID_GEN" );
    else
    {
	$id_gen = & $_SESSION['EPM_ID_GEN'][$id_type];

	$ID = bin2hex ( $id_gen[0] );
	if ( ! isset ( $_REQUEST['id'] ) )
	{
	    WARN ( "$php_self is missing ID" );
	    exit ( "UNACCEPTABLE HTTP $epm_method:" .
		   " NO ID" );
	}
	elseif ( $_REQUEST['id'] != $ID )
	{
	    if ( isset ( $_POST['xhttp'] ) )
		exit ( 'this tab is orphaned;' .
		       ' close this tab' );
	    header
		( "Location:" .
		  " $epm_root/page/orphan.html" );
	    exit;
	}

	$id_gen[0] = substr
	    ( @openssl_encrypt
	      ( $id_gen[0], 'aes-128-cbc', $id_gen[1],
		OPENSSL_RAW_DATA, $id_gen[2] ),
	      0, 16 );
	    // The @ suppresses the warning about the
	    // empty iv.  openssl_encrypt returns 32
	    // bytes, the last 16 of which are an
	    // encryption of the first 16.

	$ID = bin2hex ( $id_gen[0] );
    }
}

// If this is an xhttp request, do not produce any
// html output.
//
if ( isset ( $_POST['xhttp'] ) ) return;

// If login has been done and this is not a +download+
// or xhttp request, setup statistics and buttons.
//
if ( isset ( $aid ) )
{
    // $epm_begin_time = microtime ( true );
    // as set above
    function shutdown_statistics()
    {
        global $epm_data, $aid, $uid, $rw, $epm_self,
	       $epm_begin_time;
	$epm_end_time = microtime ( true );
	if ( $rw )
	    $f = "accounts/$aid/+read-write+";
	else
	    $f = "accounts/$aid/+read-only+";
	$r = @file_put_contents
	    ( "$epm_data/$f",
	      sprintf
	          ( "%18.6f%18.6f %20s %s %s" . PHP_EOL,
	            $epm_begin_time, $epm_end_time,
                    $epm_self, $aid, $uid ),
	      FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );
    }
    register_shutdown_function
        ( 'shutdown_statistics' );

}

echo <<<EOT
<script>

document.onkeydown = KEY_DOWN;

function KEY_DOWN ( event )
{
    if ( event.code == 'F5'
         ||
	 ( event.code == 'KeyR'
	   &&
	   event.ctrlKey ) )
    {
	let refresh = document.getElementById
		( 'refresh' );
	    // Cannot compute this globally because
	    // id='refresh' button may not be loaded
	    // till after this code.

	event.preventDefault();
	if ( refresh == null ) return;
	    // Must not do this before preventDefault.

	// If ancestor has style.display == 'none'
	// ignore key press.
	//
	var e = refresh;
	while ( e != null )
	{
	    let display = e.style.display;
	    if ( display == 'none' ) return;
	    e = e.parentElement;
	}
	refresh.click();
    }
}

// See HELP and AUX below.
//
function AUX_WINDOW ( name, page, x, y, w, h )
{
    if ( x < 0 ) x += screen.availWidth;
    if ( y < 0 ) y += screen.availHeight;
    let features =
	  'height=' + h + 'px,' +
	  'width=' + w + 'px,' +
	  'screenX=' + x + 'px,' +
	  'screenY=' + y + 'px';
    let oldwin = window.open ( "", name, features );
    oldwin.close();
        // Closing old window allows focus to
	// work (at least in Chrome) when
	// old window exists.
    let win = window.open
	( '$epm_root' + '/page/' + page, name,
	   features );
    win.focus();
        // Focus needed for FireFox but not Chrome.
}

// Launches 'help' window in upper right corner.
//
function HELP ( reference, page = 'help.html' )
{
    AUX_WINDOW ( '+help-window+',
		 page + '#' + reference,
		 -800, 0, 800, 800 );
}
// Launches auxilary window in lower right corner.
//
var aux_offset = 0;
function AUX ( event, page, name )
{
    var offset = 0;
    if ( event != null && event.altKey )
    {
        aux_offset += 30;
	if ( aux_offset >= 10 * 30 )
	    aux_offset -= 9 * 30 - 10;
	offset = aux_offset;
    }
    else
        name = '+aux+';

    AUX_WINDOW ( name, page,
		 -1200 - offset,
		 -800 - offset
		      - ( offset > 0 ? 40 : 0 ),
		 1200, 800 );
}

function INDEX ( name )
{
    let offset = ( name == 'documents' ? 0 : 40 );
    AUX_WINDOW ( '+' + name + '+', name + '.php',
                 offset,
		 -400 - offset
		      - ( offset > 0 ? 40 : 0 ),
		 600, 400 );
}
   
</script>
EOT;

?>
