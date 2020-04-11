<?php

    // File:	login.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Apr 11 12:54:41 EDT 2020

    // Handles login for a session.
    //
    // Data:
    //
    //    BDIR	    Browser data directory:
    //                $epm_data/admin/browser
    //
    //    EDIR	    Email data directory:
    //                $epm_data/admin/email
    //
    //    EMAIL	    User email address.  Used as the
    //              user name.
    //
    //    UID	    User ID.  Name containing only
    //		    letters, digits, _, and - and
    //		    beginning and ending with a letter.
    //
    //    BID	    Browser ID.  A 32-hex-digit random
    //		    number generated by the server to
    //		    identify the browser, and stored
    //		    in the TICKET.
    //
    //    KEYA	    Two encryption Keys.  32-hex-digit
    //	  KEYB      random numbers generated by the
    //		    server for use in a handshake
    //		    protocol that certifies browser
    //		    identity.  Stored in the TICKET.
    //
    //	  CNUM	    Confirmation Number.  32-hex-digit
    //		    random number generated by the
    //		    server and emailed to the user, who
    //		    enters it into the browser during
    //		    the protocol that creates the
    //		    broswer local memory TICKET.  Only
    //		    exists if browser identification
    //		    protocol is MANUAL, and not AUTO.
    //
    //		    This confirmation number is not
    //		    saved by either the server or the
    //		    browser, but is only used during
    //		    TICKET creation.
    //
    //     STIME    Session Time.  The time of the
    //		    request that created the current
    //		    session, as stored in $_SESSION
    //		    ['EPM_SESSION_TIME'] in $epm_time_
    //		    format.
    //
    //     CTIME    TICKET confirmation Time.  STIME for
    //		    the session that created the TICKET.
    //
    //     BID-FILE The file BDIR/BID that contains just
    //		    the line:
    //
    //			EMAIL KEYA KEYB CTIME
    //
    //		    File is written by server when it
    //		    successfully completes the MANUAL
    //		    browser identification protocol that
    //		    uses a confirmation number (CNUM).
    //
    //	   TICKET   Browser Local Memory Item, stored
    //		    using EMAIL as the item key when
    //		    the browser is notified that the
    //              MANUAL browser id protocol has been
    //              successfully completed.  Just the
    //              single line:
    //
    //			BID KEYA KEYB CTIME
    //
    //		    in the browser local memory.
    //
    //      FTIME   STIME of first time an email was
    //		    issued a TICKET.
    //
    //      TCOUNT  Number of times an email has been
    //              issued a TICKET.
    //
    //      TTIME   STIME of last time an email was
    //		    issued a TICKET.  'NONE' if TCOUNT
    //		    is 0.
    //
    //      ECOUNT  Number of times an email has been
    //              issued a TICKET because a previous
    //		    ticket had expired.
    //
    //      ETIME   STIME of last time an email was
    //		    issued a TICKET because a previous
    //		    ticket had expired.  'NONE' if
    //		    ECOUNT is 0.
    //
    //   EMAIL-FILE The file EDIR/EMAIL (where here
    //		    EMAIL is encoded by rawurlencode)
    //              that contains just the line:
    //
    //		    UID FTIME TCOUNT TTIME ECOUNT ETIME
    //
    //		    File is initialized by user.php
    //		    when user first logs in, after
    //		    confirmation, or when a user adds
    //		    an email to his account.  This file
    //		    is updated by this page when issuing
    //		    a new TICKET for the email.
    //
    //    HANDSHAKE A 32-hex-digit random number gener-
    //		    ated by the server and sent to the
    //		    browser as part of the handshake
    //		    that verifies that the browser
    //		    possesses the TICKET.  Encrypted by
    //		    KEYA before being sent by server.
    //		    Decrypted by KEYA and re-encrypted
    //		    by KEYB before being sent by the
    //		    browser back to the server.
    //
    // When the browser identification protocol
    // successfully completes, the server sets the
    // the following values in $_SESSION:
    //
    //    EPM_EMAIL => EMAIL
    //    EPM_BID => BID
    //    EPM_UID => UID, but not set by this
    //                    program for a new user.
    //
    // Next page is page/problem.php if user is NOT new
    // and page/user.php otherwise.  In the case of a
    // new user, this last page determines UID and sets
    // EPM_UID.
    //
    // During the execution of the browser identifica-
    // tion protocol, $_SESSION['EPM_LOGIN_DATA']
    // is used to hold BID, EMAIL, KEYA, KEYB, CTIME,
    // CNUM, UID, FTIME, TCOUNT, TTIME, ECOUNT, and
    // ETIME.  During the protocol the browser stores
    // BID, EMAIL, CTIME, KEYA, KEYB, and CNUM in var's
    // of the same name.
    //
    // Each successful execution of the browser identi-
    // fication protocol (i.e., each successful login)
    // is logged separately to the file:
    //
    //		$epm_data/login.log
    //
    // The format of this file is
    //
    //	 // comment
    //   UID EMAIL IPADDR STIME BID CTIME ECOUNT ETIME
    //
    // where IPADDR is $_SESSION['EPM_IPADDR'] and for
    // a new user UID is 'NEW', ECOUNT is 0, and ETIME
    // is NONE.


    // Browser Identification Protocol
    // -------------------------------
    //
    // The browser runs the protocol using javascript
    // XMLHttpRequest to POST requests.  When the
    // browser is notified of success, it is given
    // a page to go to (page/problem.php or for new
    // users page/user.php).
    //
    // The protocol is:
    //
    // BEGIN:
    //	   * AUTO_RETRY = 0
    //     * Get EMAIL from user.
    //	   * Get TICKET = localStorage.getItem(EMAIL)
    //	   * If TICKET != null:
    //		* Parse TICKET to get BID, KEYA, KEYB
    //          * MANUAL = no
    //          * go to AUTO_ID
    // MANUAL_ID:
    //	   * MANUAL = yes
    //     * Send 'op=MANUAL&value=EMAIL'
    //     * Receive one of:
    //           'BAD_EMAIL': FAIL
    //                        reload login.php
    //           'NEW BID EKEYA EKEYB CTIME' where
    //                    EKEYA, EKEYB are KEYA, KEYB
    //                    encrypted using CNUM.
    // EXPIRED:
    //     * Get CNUM from user.
    //     * Decrypt EKEYA and EKEYB using CNUM to get
    //	     KEYA, KEYB.
    // AUTO_ID:
    //     * AUTO_RETRY += 1
    //     * If AUTO_RETRY > 2: FAIL
    //                          reload login.php
    //     * Send 'op=AUTO&value=BID'
    //	   * Receive one of:
    //           'EXPIRED BID EKEYA EKEYB CTIME':
    //		     MANUAL = yes
    //               go to EXPIRED
    //           'FAIL':  (means BID not recognized)
    //               go to AUTO_ID
    //		 'SHAKE HANDSHAKE':
    //               continue with following
    //     * Decrypt HANDSHAKE using KEYA and encrypt
    //       result using KEYB to get SHAKEHAND.
    //     * Send 'op=SHAKE&value=SHAKEHAND'
    //     * Receive one of:
    //           'FAIL':
    //               go to AUTO_ID
    //           'DONE NEXT_PAGE':
    //               continue with following
    //     * If MANUAL:
    //		* TICKET = 'BID KEYA KEYB CTIME'
    //          * localStorage.setItem(EMAIL,TICKET)
    //     * Issue GET to NEXT_PAGE
    //
    // Encryption is by AES-128-CRC with zero initial
    // vector.  All items being encrypted are 128 bit
    // random numbers, but their encryptions are 256
    // bit random numbers.
    //
    // Repeated invocations of MANUAL for the same
    // session with the same EMAIL will use the same
    // CNUM, so as not to confuse the user.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";
    // if ( ! isset ( $_POST['op'] )
    //      &&				// xhttp
    //      ! isset ( $_POST['value'] ) )
    //     require "$epm_home/include/debug_info.php";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET'
         &&
         $method != 'POST' )
	exit ( "UNACCEPTABLE HTTP METHOD $method" );

    if ( ! isset ( $_SESSION['EPM_LOGIN_DATA'] ) )
	$_SESSION['EPM_LOGIN_DATA'] = [];
    $data = & $_SESSION['EPM_LOGIN_DATA'];
    $STIME = $_SESSION['EPM_SESSION_TIME'];

    // Reply to POST from xhttp.
    //
    function reply ( $reply )
    {
	echo ( $reply );
	exit;
    }

    // $data['UID'] is set iff EMAIL-FILE exists and
    // has been read.
    //
    // $data['CNUM'] is set when data to create a new
    // TICKET is sent to the browser, and is unset
    // if and when $data['EMAIL'] is changed. 

    // Read $epm_data/admin/email/$email(encoded) if it
    // exists and if read set $data UID, FTIME, TCOUNT,
    // TTIME, ECOUNT, ETIME.
    //
    function read_email_file ( $email )
    {
	global $epm_data, $data;

	$efile = "admin/email/"
	       . rawurlencode ( $email );

	if ( ! is_readable ( "$epm_data/$efile" ) )
	    return;

	$c = @file_get_contents ( "$epm_data/$efile" );
	if ( $c === false )
	    ERROR ( "failed to read readable" .
		    " file $efile" );
	$c = trim ( $c );
	$items = explode ( ' ', $c );
	if ( count ( $items ) != 6 )
	    ERROR ( "$efile value '$c' badly" .
		    " formatted" );
	$data['UID'] = $items[0];
	$data['FTIME'] = $items[1];
	$data['TCOUNT'] = $items[2];
	$data['TTIME'] = $items[3];
	$data['ECOUNT'] = $items[4];
	$data['ETIME'] = $items[5];
    }

    // Output NEW or EXPIRED response, creating CNUM is
    // necessary.  $op is NEW or EXPIRED.
    //
    // Note: CNUM is set iff a new TICKET is being
    // created, and its bid-file should be written and
    // EMAIL-FILE TCOUNT and maybe ECOUNT should be
    // incremented upon successful handshake.
    //
    function new_ticket_reply ( $email, $op )
    {
	global $epm_data, $data, $STIME;

	if ( ! isset ( $data['EMAIL'] )
	     ||
	     $data['EMAIL'] != $email )
	{
	    $data['EMAIL'] = $email;
	    unset ( $data['CNUM'] );
	    read_email_file ( $email );
	}

	if ( ! isset ( $data['CNUM'] ) )
	    $data['CNUM'] =
		bin2hex ( random_bytes ( 16 ) );

	$sname = $_SERVER['SERVER_NAME'];
	mail ( $data['EMAIL'],
	       "Your EPM Confirmation Number",
	       "Your EPM $sname confirmation number" .
	       " is:\r\n" .
	       "\r\n" .
	       "     {$data['CNUM']}\r\n",
	       ["From" => "no_reply@$sname"] );

	$data['BID'] = bin2hex ( random_bytes ( 16 ) );
	$data['KEYA'] = bin2hex ( random_bytes ( 16 ) );
	$data['KEYB'] = bin2hex ( random_bytes ( 16 ) );
	$data['CTIME'] = $STIME;

	$iv = hex2bin
	    ( "00000000000000000000000000000000" );
	$ekeyA = bin2hex ( openssl_encrypt
	    ( hex2bin ( $data['KEYA'] ), "aes-128-cbc",
	      hex2bin ( $data['CNUM'] ),
	      OPENSSL_RAW_DATA, $iv ) );
	$ekeyB = bin2hex ( openssl_encrypt
	    ( hex2bin ( $data['KEYB'] ), "aes-128-cbc",
	      hex2bin ( $data['CNUM'] ),
	      OPENSSL_RAW_DATA, $iv ) );

	reply ( "$op {$data['BID']} $ekeyA $ekeyB" .
	        " {$data['CTIME']}" );
    }

    // Create admin and users directories if they do not
    // exist.
    //
    if ( ! is_dir ( "$epm_data/admin" ) )
    {
        $m = umask ( 06 );

	if ( ! is_dir ( $epm_data ) )
	    @mkdir ( $epm_data, 0771 );

	@mkdir ( "$epm_data/admin", 0770 );
	@mkdir ( "$epm_data/admin/email", 0770 );
	@mkdir ( "$epm_data/admin/browser", 0770 );
	@mkdir ( "$epm_data/admin/users", 0770 );
	@mkdir ( "$epm_data/users", 0771 );
	@mkdir ( "$epm_data/projects", 0771 );

	if ( ! is_dir ( "$epm_data/admin" ) )
	     ERROR
		 ( 'cannot make admin directory' );

	if ( ! is_dir ( "$epm_data/users" ) )
	     ERROR
		 ( 'cannot make users directory' );

	if ( ! is_dir ( "$epm_data/projects" ) )
	     ERROR
		 ( 'cannot make projects directory' );

	umask ( $m );
    }

    $op = NULL;
    if ( isset ( $_POST['op'] ) )
	$op = $_POST['op'];

    // Process POSTs from xhttp.
    //
    if ( $op == 'MANUAL' )
    {
	$email = trim ( $_POST['value'] );
	$e = filter_var
	    ( $email, FILTER_SANITIZE_EMAIL );

	if ( $e != $email
	     ||
	     ! filter_var
		      ( $email,
			FILTER_VALIDATE_EMAIL ) )
	    reply ( 'BAD_EMAIL' );
	else
	    new_ticket_reply ( $email, 'NEW' );
    }
    elseif ( $op == 'AUTO' )
    {
	$bid = trim ( $_POST['value'] );
	if ( ! preg_match ( '/^[a-fA-F0-9]{32}$/',
			    $bid ) ) 
	    reply ( 'FAIL' );
	$bfile = "admin/browser/$bid";
	if ( ! is_readable ( "$epm_data/$bfile" ) )
	{
	    if ( ! isset ( $data['EMAIL'] ) )
		reply ( 'FAIL' );
	    // Else this is part of MANUAL login.
	}
	else
	{
	    $c = @file_get_contents
		( "$epm_data/$bfile" );
	    if ( $c === false )
		ERROR ( "cannot read readable file" .
			" $bfile" );
	    $c = trim ( $c );
	    $items = explode ( ' ', $c );
	    if ( count ( $items ) != 4 )
		ERROR ( "$bfile value '$c' badly" .
			" formatted" );
	    $email = $items[0];
	    $ctime = strtotime ( $items[3] );
	    if ( ! isset ( $data['EMAIL'] )
		 ||
		 $email != $data['EMAIL'] )
	    {
		$data['EMAIL'] = $email;
		unset ( $data['CNUM'] );
		read_email_file ( $email );
	    }

	    if ( isset ( $data['ECOUNT'] ) )
	    {
		$ecount = $data['ECOUNT'];
		$now = time();

		$etimes = & $epm_expiration_times;
		$n = count ( $etimes );
		if ( $ecount >= $n )
		    $ecount = $n - 1;
		if (   $ctime + $etimes[$ecount]
		     < $now )
		{
		    // Ticket has expired.
		    //
		    @unlink ( "$epm_data/$bfile" );
		    unset ( $data['BID'] );
		    unset ( $data['KEYA'] );
		    unset ( $data['KEYB'] );
		    unset ( $data['CTIME'] );
		    if ( $data['ETIME'] != $STIME )
		    {
			$data['ECOUNT'] += 1;
			$data['ETIME'] = $STIME;
		    }
		    new_ticket_reply
			( $email, 'EXPIRED' );
		}
	    }
	     
	    $data['BID'] = $bid;
	    $data['KEYA'] = $items[1];
	    $data['KEYB'] = $items[2];
	    $data['CTIME'] = $items[3];
	}

	$data['HANDSHAKE'] =
	    bin2hex ( random_bytes ( 16 ) );
	$iv = hex2bin
	    ( "00000000000000000000000000000000" );
	$handshake = bin2hex ( openssl_encrypt
	    ( hex2bin ( $data['HANDSHAKE'] ),
	      "aes-128-cbc",
	      hex2bin ( $data['KEYA'] ),
	      OPENSSL_RAW_DATA, $iv ) );

	reply ( "SHAKE $handshake" );
    }
    elseif ( $op == 'HAND' )
    {
	$shakehand = trim ( $_POST['value'] );
	if ( ! preg_match ( '/^[a-fA-F0-9]{64}$/',
	                    $shakehand ) ) 
	    reply ( 'FAIL' );
	$iv = hex2bin
	    ( "00000000000000000000000000000000" );
	$handshake = bin2hex ( openssl_decrypt
	    ( hex2bin ( $shakehand ), "aes-128-cbc",
	      hex2bin ( $data['KEYB'] ),
	      OPENSSL_RAW_DATA, $iv ) );

	if ( $handshake != $data['HANDSHAKE'] )
	{
	    LOG ( "Decrypted handshake = $handshake" .
	          " != {$data['HANDSHAKE']}" .
		  " = HANDSHAKE" );
	    reply ( 'FAIL' );
	}
	else
	{
	    $next_page = 'user.php';
	    if ( isset ( $data['UID'] ) )
	    {
		$next_page = 'problem.php';
		$_SESSION['EPM_UID'] = $data['UID'];

		if ( isset ( $data['CNUM'] ) )
		{
		    $efile = "admin/email/"
			   . rawurlencode
			       ( $data['EMAIL'] );
		    $data['TCOUNT'] += 1;
		    $data['TTIME'] = $data['CTIME'];
		    $items = [ $data['UID'],
			       $data['FTIME'],
			       $data['TCOUNT'],
			       $data['TTIME'],
			       $data['ECOUNT'],
			       $data['ETIME']];
		    $r = @file_put_contents
			( "$epm_data/$efile",
			  implode ( ' ', $items ) );
		    if ( $r === false )
		        ERROR ( "could not write" .
			        " $efile" );
		}
	    }

	    if ( isset ( $data['CNUM'] ) )
	    {
		$bfile = "admin/browser/"
		       . $data['BID'];
		$items = [ $data['EMAIL'],
			   $data['KEYA'],
			   $data['KEYB'],
			   $data['CTIME'] ];
		$r = @file_put_contents
		    ( "$epm_data/$bfile",
		      implode ( ' ', $items ) );
		if ( $r === false )
		    ERROR ( "could not write $bfile" );
	    }

	    $_SESSION['EPM_BID'] = $data['BID'];
	    $_SESSION['EPM_EMAIL'] = $data['EMAIL'];

	    if ( isset ( $data['UID'] ) )
		$items = [ $data['UID'],
			   $data['EMAIL'],
			   $_SESSION['EPM_IPADDR'],
			   $STIME,
			   $data['BID'],
			   $data['CTIME'],
			   $data['ECOUNT'],
			   $data['ETIME'] ];
	    else
		$items = [ 'NEW',
			   $data['EMAIL'],
			   $_SESSION['EPM_IPADDR'],
			   $STIME,
			   $data['BID'],
			   $data['CTIME'],
			   0,
			   'NONE' ];
	    $r = @file_put_contents
		( "$epm_data/login.log",
		  implode ( ' ', $items ) . PHP_EOL,
		  FILE_APPEND );
	    if ( $r === false )
		ERROR ( "could not write login.log" );

	    unset ( $_SESSION['EPM_LOGIN_DATA'] );
	    reply ( "DONE $next_page" );
	}
    }
    elseif ( $method == 'POST' )
	exit ( "UNACCEPTABLE HTTP POST" );

    // Else load html and script.

?>

<html>
<head>
<style>
    @media screen and ( max-width: 1279px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	}
    }
    @media screen and ( min-width: 1280px ) {
	:root {
	    --font-size: 16px;
	    --large-font-size: 20px;
	    width: 1280px;
	    font-size: var(--font-size);
	    overflow: scroll;
	}
    }
    button, input, mark, span {
	display:inline;
        font-size: var(--font-size);
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
</style>
</head>

<body>
<!-- body elements must be BEFORE script so that
     getElementById can be used to set global vars -->

<?php

    $agent = $_SERVER['HTTP_USER_AGENT'];
    $ok = false;
    foreach ( $epm_supported_browsers as $b )
    {
        if ( preg_match ( "/$b/i", $agent ) )
	{
	    $ok = true;
	    break;
	}
    }
    if ( ! $ok )
    {
        $ok_browsers =
	    implode ( ",", $epm_supported_browsers );
	echo <<<EOT
	<mark>$agent is an untested browser type.<br>
	      If it does not work use one of:
	         $ok_browsers.</mark><br><br>
EOT;
    }
?>

<div id='get_email' style.display='none'>
<input type='text' id='email_in'
       placeholder='Enter Email Address'
       autofocus
       title='address (to be) associated with account'>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?php echo ( HELP('login-page') )?>
</div>

<div id='show_email' style.display='none'>
Email:&nbsp;<span id='email_out'></span>
<br>
<button onclick="window.location.reload(true)">
Change Email Address
</button>
&nbsp;&nbsp;&nbsp;&nbsp;
<button onclick="RESET_EMAIL()">
Get New Ticket
</button>
&nbsp;&nbsp;&nbsp;&nbsp;
<button onclick="RESET_BROWSER()">
Delete All Tickets
</button>
&nbsp;&nbsp;&nbsp;&nbsp;
<?php echo ( HELP('browser-ticket') )?>
</div>

<div id='get_cnum' style.display='none'>
A Confirmation Number has been sent
to the above Email Address.
<br>
Please <input type='text' size='40' id='cnum_in'
       placeholder='Enter Confirmation Number'>
</div>

<script>

var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

var xhttp = new XMLHttpRequest();
var storage = window.localStorage;
var get_email = document.getElementById("get_email");
var email_in = document.getElementById("email_in");
var email_out = document.getElementById("email_out");
var show_email = document.getElementById("show_email");
var get_cnum = document.getElementById("get_cnum");
var cnum_in = document.getElementById("cnum_in");

function FAIL ( message )
{
    // Alert must be scheduled as separate task.
    //
    LOG ( "call to FAIL: " + message );
<?php
    if ( $epm_debug )
        echo <<<'EOT'
	    setTimeout ( function () {
		alert ( message );
		window.location.reload ( true );
	    });
EOT;
    else
        echo <<<'EOT'
	    throw "CALL TO FAIL: " + message;
EOT;
?>
}

function RESET_EMAIL()
{
    storage.removeItem ( EMAIL );
    window.location.reload ( true );
}

function RESET_BROWSER()
{
    storage.clear();
    window.location.reload ( true );
}

function ALERT ( message )
{
    // Alert must be scheduled as separate task.
    //
    setTimeout ( function () { alert ( message ); } );
}

var REQUEST_IN_PROGRESS = false;
function SEND ( data, callback, error_message )
{
    xhttp.onreadystatechange = function() {
	LOG ( 'xhttp state changed to state '
	      + this.readyState );
	if ( this.readyState !== XMLHttpRequest.DONE
	     ||
	     ! REQUEST_IN_PROGRESS )
	    return;

	if ( this.status != 200 )
	    FAIL ( 'Bad response status ('
	           + this.status
	           + ') from server on '
	           + error_message );

	REQUEST_IN_PROGRESS = false;
	LOG ( 'xhttp response: '
	      + this.responseText );
	callback ( this.responseText );
    };
    xhttp.open ( 'POST', "login.php", true );
    xhttp.setRequestHeader
        ( "Content-Type",
	  "application/x-www-form-urlencoded" );
    REQUEST_IN_PROGRESS = true;
    LOG ( 'xhttp sent: ' + data );
    xhttp.send ( data );
}

var IMPORTKEY, ENCRYPT, DECRYPT;
var iv = new Uint8Array ( 16 );

function ArrayBuffer2hex ( buffer )
{
    return Array
        .from ( new Uint8Array(buffer) )
	.map ( b => b.toString(16).padStart(2,'0') )
        .join('');
}

function hex2ArrayBuffer ( hex )
{
    var length = hex.length / 2;
    var result = new ArrayBuffer ( length );
    var view = new Uint8Array ( result );
    for ( var i = 0; i < length; ++ i )
        view[i] =
	    parseInt ( hex.substr ( 2*i, 2 ), 16 );
    return result;
}

if ( window.msCrypto !== undefined )
{

    IMPORTKEY = function ( key, callback )
    {
	window.msCrypto.subtle.importKey
	    ( "raw", hex2ArrayBuffer ( key ),
	      "AES-CBC", true, ["encrypt", "decrypt"] )
	    .oncomplete
	        ( function(r)
		  { callback ( r.target.result ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'key import failed' ) ) } );
    }

    ENCRYPT = function ( cryptokey, data, callback )
    {
	window.msCrypto.subtle.encrypt
	    ( { name: "AES-CBC", iv }, cryptokey,
	      hex2ArrayBuffer ( data ) )
	    .oncomplete
	        ( function(r)
		  { callback
		      ( ArrayBuffer2hex
		          ( r.target.result ) ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'encryption failed' ) ) } );
    }

    DECRYPT = function ( cryptokey, data, callback )
    {
	window.msCrypto.subtle.decrypt
	    ( { name: "AES-CBC", iv }, cryptokey,
	      hex2ArrayBuffer ( data ) )
	    .oncomplete
	        ( function(r)
		  { callback
		      ( ArrayBuffer2hex
		          ( r.target.result ) ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'decryption failed' ) ) } );
    }

} else {

    IMPORTKEY = function ( key, callback )
    {
	window.crypto.subtle.importKey
	    ( "raw", hex2ArrayBuffer ( key ),
	      "AES-CBC", true, ["encrypt", "decrypt"] )
	    .then ( callback )
	    .catch
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'key import failed' ) ) } );
    }

    ENCRYPT = function ( cryptokey, data, callback )
    {
	window.crypto.subtle.encrypt
	    ( { name: "AES-CBC", iv }, cryptokey,
	      hex2ArrayBuffer ( data ) )
	    .then
	        ( function(r)
		  { callback
		      ( ArrayBuffer2hex ( r ) ) } )
	    .catch ( callback );
    }

    DECRYPT = function ( cryptokey, data, callback )
    {
	window.crypto.subtle.decrypt
	    ( { name: "AES-CBC", iv }, cryptokey,
	      hex2ArrayBuffer ( data ) )
	    .then
	        ( function(r)
		  { callback
		      ( ArrayBuffer2hex ( r ) ) } )
	    .catch ( callback );
    }

}
    
var AUTO_RETRY, EMAIL, TICKET, BID, KEYA, KEYB, CTIME,
    MANUAL, CNUM, EKEYA, EKEYB, CRYPTOKEY, HANDSHAKE,
    SHAKEHAND;

var GET_EMAIL_ENABLED = false;
var GET_CNUM_ENABLED = false;
    // These are set to true to enable callback, and
    // set false just before making callback, to avoid
    // spurious callbacks.

// BEGIN:
//
AUTO_RETRY = 0;
GET_EMAIL_ENABLED = true;
get_email.style.display = 'block';
show_email.style.display = 'none';
get_cnum.style.display = 'none';

function EMAIL_KEYDOWN ( event )
{
    if ( event.code == 'Enter'
         &&
	 GET_EMAIL_ENABLED )
    {
	var value = email_in.value.trim();
	if ( /^\S+@\S+\.\S+$/.test(value) )
	{
	    GET_EMAIL_ENABLED = false;
	    GOT_EMAIL ( value );
	}
	else if ( value != '' )
	    ALERT ( value + " is not a valid" +
	            " email address" );
    }
}
email_in.addEventListener ( 'keydown', EMAIL_KEYDOWN );

function GOT_EMAIL ( email )
{
    EMAIL = email;
    get_email.style.display = 'none';
    email_out.innerText = EMAIL;
    show_email.style.display = 'block';
    TICKET = storage.getItem(EMAIL);
    if ( TICKET == null )
        MANUAL_ID();
    else
    {
        var ITEM = TICKET.trim().split ( ' ' );
	BID = ITEM[0];
	KEYA = ITEM[1];
	KEYB = ITEM[2];
	MANUAL = false;
	AUTO_ID();
    }
}

function MANUAL_ID()
{
    MANUAL = true;
    SEND ( "op=MANUAL&value="
           + encodeURIComponent ( EMAIL ),
           MANUAL_RESPONSE,
	   'sending ' + EMAIL + ' to server' );
}

function MANUAL_RESPONSE ( response )
{
    var r = response.trim().split ( ' ' );
    if ( r[0] == 'BAD_EMAIL' )
        FAIL ( EMAIL +
	       ' is not a valid email address' );
    else if ( r[0] != 'NEW'
	      ||
	      r.length != 5 )
        FAIL ( 'Response from server on sending '
	       + EMAIL + ' to server is malformed' );
    else
    {
	BID = r[1];
	EKEYA = r[2];
	EKEYB = r[3];
	CTIME = r[4];
	EXPIRED();
    }
};

function EXPIRED()
{
    GET_CNUM_ENABLED = true;
    get_cnum.style.display = 'block';
}

function CNUM_KEYDOWN ( event )
{
    if ( event.code == 'Enter'
         &&
	 GET_CNUM_ENABLED )
    {
	value = cnum_in.value.trim();
	if ( /^[a-fA-F0-9]{32}$/.test(value) )
	{
	    GET_CNUM_ENABLED = false;
	    GOT_CNUM ( value );
	}
	else if ( value != '' )
	    ALERT ( value + ' is not a valid' +
	            ' confirmation number' );
    }
}
cnum_in.addEventListener ( 'keydown', CNUM_KEYDOWN );

function GOT_CNUM ( cnum )
{
    CNUM = cnum;
    IMPORTKEY ( cnum, GOT_CNUM_KEY );
}

function CNUM_NOT_VALID ( error )
{
    LOG ( 'CNUM_NOT_VALID ERROR: ' . error );
    alert ( CNUM + ' is not valid;'
	    + ' enter different confirmation number'
	    + ' or change email address' );
    EXPIRED();
}

function GOT_CNUM_KEY ( cryptokey )
{
    if ( cryptokey instanceof Error )
    {
        CNUM_NOT_VALID ( cryptokey );
	return;
    }
    CRYPTOKEY = cryptokey;
    DECRYPT ( CRYPTOKEY, EKEYA, GOT_KEYA );
}

function GOT_KEYA ( keyA )
{
    if ( keyA instanceof Error )
    {
        CNUM_NOT_VALID ( keyA );
	return;
    }
    KEYA = keyA;
    DECRYPT ( CRYPTOKEY, EKEYB, GOT_KEYB );
}

function GOT_KEYB ( keyB )
{
    if ( keyB instanceof Error )
    {
        CNUM_NOT_VALID ( keyB );
	return;
    }
    KEYB = keyB;
    AUTO_ID();
}

function AUTO_ID()
{
    AUTO_RETRY += 1
    if ( AUTO_RETRY > 2 )
    {
        FAIL ( 'two attempts at handshake failed' );
	return;
    }
    SEND ( 'op=AUTO&value=' + BID,
           AUTO_RESPONSE,
	   'starting handshake with server' );
}

function AUTO_RESPONSE ( item )
{
    item = item.trim().split ( ' ' );
    if ( item[0] == 'EXPIRED' )
    {
	storage.removeItem ( EMAIL );
        if ( item.length != 5 )
	    FAIL ( 'bad response from server during'
	           + ' handshake; re-confirmation'
		   + ' required' );
	BID   = item[1];
	EKEYA = item[2];
	EKEYB = item[3];
	CTIME = item[4];
	MANUAL = true;
	EXPIRED();
	return;
    }
    else if ( item[0] == 'FAIL' )
    {
	storage.removeItem ( EMAIL );
	ALERT ( 'browser/email ticket not known to'
	      + ' server; re-confirmation required' );
	GOT_EMAIL ( EMAIL );
    }
    else if ( item[0] == 'SHAKE'
              &&
	      item.length == 2 )
    {
        HANDSHAKE = item[1];
	IMPORTKEY ( KEYA, GOT_KEYA_FOR_SHAKE );
    }
    else
        FAIL ( 'Response from server on initiating'
	       + ' handshake is malformed' );
}

function GOT_KEYA_FOR_SHAKE ( cryptokey )
{
    if ( cryptokey instanceof Error )
    {
	LOG ( 'GOT_KEYA_FOR_SHAKE ERROR: ' .
	      cryptokey );
        AUTO_ID();  // Retry
	return;
    }
    LOG ( 'DECRYPT ' + HANDSHAKE + ' USING ' + KEYA );
    DECRYPT ( cryptokey, HANDSHAKE, GOT_SHAKEA );
}

function GOT_SHAKEA ( shakeA )
{
    if ( shakeA instanceof Error )
    {
	LOG ( 'GOT_SHAKEA ERROR: ' .  shakeA );
        AUTO_ID();  // Retry
	return;
    }
    SHAKEHAND = shakeA;
    IMPORTKEY ( KEYB, GOT_KEYB_FOR_SHAKE );
}

function GOT_KEYB_FOR_SHAKE ( cryptokey )
{
    if ( cryptokey instanceof Error )
    {
	LOG ( 'GOT_KEYB_FOR_SHAKE ERROR: ' .
	      cryptokey );
        AUTO_ID();  // Retry
	return;
    }
    LOG ( 'ENCRYPT ' + SHAKEHAND + ' USING ' + KEYB );
    ENCRYPT ( cryptokey, SHAKEHAND, GOT_SHAKEB );
}

function GOT_SHAKEB ( shakeB )
{
    if ( shakeB instanceof Error )
    {
	LOG ( 'GOT_SHAKEB ERROR: ' .  shakeB );
        AUTO_ID();  // Retry
	return;
    }
    SHAKEHAND = shakeB;
    SEND ( 'op=HAND&value=' + SHAKEHAND,
           SHAKE_RESPONSE,
	   'sending handshake to server' );
}

function SHAKE_RESPONSE ( item )
{
    item = item.trim().split ( ' ' );
    if ( item[0] == 'FAIL' )
    {
        AUTO_ID();  // Retry
	return;
    }
    else if ( item[0] == 'DONE'
              &&
	      item.length == 2 )
    {
        if ( MANUAL )
	{
	    TICKET = [BID, KEYA, KEYB, CTIME].join(' ');
	    storage.setItem ( EMAIL, TICKET );
	}
	try {
	    window.location.assign ( item[1] );
	} catch ( e ) {
	    FAIL
	       ( 'could not access page ' + item[1] );
	       // On retry login.php will go to
	       // correct page.
	}
    }
    else
        FAIL ( 'Response from server ending handshake'
	       + ' is malformed' );
}
    
</script>


</body>
</html>
