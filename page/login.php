<?php

    // File:	login.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Aug  2 15:35:02 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Handles login for an EPM session.

    // Browser Identification Protocol
    // -------------------------------
    //
    // The browser runs the protocol using javascript
    // XMLHttpRequest to POST requests.  When the
    // browser is notified of success, it is given
    // a page to go to (project.php or for new users
    // user.php).
    //
    // The protocol is:
    //
    // BEGIN:
    //	   * Set PATH = location.pathname.
    //     * Get LNAME from user.
    //	   * Clear Messages
    //	   * Get TICKET =
    //           localStorage.getItem(PATH\0LNAME)
    //	   * If TICKET != null:
    //          * go to AUTO_ID with stored TICKET
    // MANUAL_ID:
    //     * Send 'op=MANUAL&value=LNAME'
    //     * Receive one of:
    //           'BAD_AID':
    //           'BAD_EMAIL':
    //           'BLOCKED_EMAIL':
    //           'NO_TEAM':
    //           'NO_USER':
    //		     goto LNAME_ERROR
    //           'NEW': go to CONFIRM
    // AUTO_ID:
    //     * Send 'op=AUTO&value=TICKET'
    //	   * Receive one of:
    //           'EXPIRED':
    //               goto CONFIRM with Ticket Message
    //           'BAD_TICKET':
    //           'NO_TICKET':
    //               go to TICKET_ERROR
    //           'BLOCKED_EMAIL':
    //           'NO_TEAM':
    //           'NO_USER':
    //		 'USER_NOT_ON_TEAM':
    //		     goto LNAME_ERROR
    //           'RENEW TICKET NEXT_PAGE':
    //		     go to FINISH
    // CONFIRM:
    //     * Write Ticket Message if any.
    //     * Clear confirmation number
    //     * Get TICKET = confirmation number from user.
    //	   * Clear Messages
    //     * go to AUTO_ID with confirmation number
    // FINISH:
    //     * localStorage.setItem(PATH\0LNAME,TICKET)
    //     * Issue GET to NEXT_PAGE
    // TICKET_ERROR:
    //	   * output Ticket Message
    //     * goto MANUAL_ID
    // LNAME_ERROR:
    //	   * output Lname Message
    //     * goto BEGIN

    $epm_page_type = '+main+';
    $epm_ID_init = true;
        // This causes index.php to require
	// epm_random.php for GET but NOT for POST.
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method == 'GET' )
    {
	if ( isset ( $_SESSION['EPM_AID'] ) )
	{
	    header ( "location:" .
	             " $epm_root/page/project.php" .
		     "?id=$ID" );
	    exit;
	}

	$_SESSION['EPM_TIME'] =
	    strftime ( $epm_time_format,
	               $_SERVER['REQUEST_TIME'] );
	$_SESSION['EPM_IPADDR'] =
	    $_SERVER['REMOTE_ADDR'];
    }
    else
    {
        require "$epm_home/include/epm_random.php";
	DEBUG ( 'POST ' . json_encode ( $_POST ) );
    }

    // Values read from EMAIL-FILE if that exists and
    // has been read.
    //
    $uid = NULL;
    $acount = NULL;
    $atime = NULL;

    $STIME = $_SESSION['EPM_TIME'];

    LOCK ( "admin", LOCK_EX );

    // Reply to POST from xhttp.
    //
    function reply ( $reply )
    {
        global $ID;
	echo ( "$ID $reply" );
	DEBUG ( "REPLY $ID $reply" );
	exit;
    }

    // Check admin/+blocking+ file to determine whether
    // email is blocked.
    //
    function is_blocked ( $email )
    {
        global $epm_data;
	$f = 'admin/+blocking+';
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false ) return false;
	$c = explode ( "\n", $c );
	$line_re = '/^(\+|\-)\s+(\S+)$/';
	foreach ( $c as $line )
	{
	    $line = trim ( $line );
	    if ( $line == '' ) continue;
	    if ( $line[0] == '#' ) continue;
	    if ( ! preg_match
	               ( $line_re, $line, $matches ) )
	        ERROR ( "bad $f line: $line" );
	    $sign = $matches[1];
	    $re   = $matches[2];
	    $r = preg_match ( "/^($re)\$/", $email );
	    if ( $r === false )
	        ERROR ( "bad $f RE: $line" );
	    if ( $r == 0 ) continue;
	    return ( $sign == '-' );
	}
	return false;
    }

    // Read $epm_data/admin/email/$email(encoded) if it
    // exists and if read set $uid, $acount, $atime.
    // Return true if the auto-login period has expired
    // and confirmation is needed, and false otherwise.
    //
    // More specifically, if the email file does NOT
    // exist, or if it contains only a blank lines or
    // if its first item is '-', then this function
    // just returns false. 
    //
    // Otherwise the email file is read into $uid, etc.
    // Then this function calculates whether the auto-
    // login period has expired using $STIME.  If yes,
    // then if $t == 'c' the email file is updated to
    // have a new auto-login period beginning at $STIME,
    // and this function return false, but if $t != 'c'
    // this function just returns true.  If not expired,
    // this function returns false.
    //
    function read_email_file ( $t, $email )
    {
	global $epm_data, $uid, $acount, $atime,
	       $STIME, $epm_expiration_times;

	$uid = NULL;
	$acount = NULL;
	$atime = NULL;

	$efile = "admin/email/"
	       . rawurlencode ( $email );

	if ( ! is_readable ( "$epm_data/$efile" ) )
	    return false;

	$c = @file_get_contents ( "$epm_data/$efile" );
	if ( $c === false )
	    ERROR ( "failed to read readable" .
		    " file $efile" );
	$c = trim ( $c );
	if ( $c == '' ) return false;
	$items = explode ( ' ', $c );
	if ( $items[0] == '-' ) return false;

	if ( count ( $items ) != 3 )
	    ERROR ( "$efile value '$c' badly" .
		    " formatted" );
	$uid = $items[0];
	$acount = $items[1];
	$atime = $items[2];

	$etimes = & $epm_expiration_times;
	$n = count ( $etimes );
	$m = ( $acount >= $n ? $n - 1 : $acount );
	$atime = strtotime ( $atime );
	$stime = strtotime ( $STIME );
	if ( $stime > $atime + $etimes[$m] )
	{
	    if ( $t != 'c' ) return true;
	    ++ $acount;
	    $r = file_put_contents
		( "$epm_data/$efile",
		  "$uid $acount $STIME" );
	    if ( $r === false )
		ERROR ( "cannot write $efile" );
	}
	return false;
    }

    // Create new TICKET and its TICKET-FILE and return
    // new bid.
    //
    function new_ticket ( $t, $aid, $email )
    {
	global $epm_data;

	while ( true )
	{
	    $ticket =
	        bin2hex ( random_16_bytes() );
	    $bdir = "admin/browser";
	    if ( ! is_dir ( "$epm_data/$bdir" ) 
	         &&
		 ! @mkdir ( "$epm_data/$bdir",
		            02770, true ) )
	        ERROR ( "cannot make $bdir" );
	    $tfile = "$bdir/$ticket";
	    if ( is_readable ( "$epm_data/$tfile" ) )
	    {
	        WARN ( 'THIS SHOULD NEVER HAPPEN' );
	        continue;
	    }
	    file_put_contents
	        ( "$epm_data/$tfile",
	          "$t $aid $email" . PHP_EOL );
	    return $ticket;
	}
    }

    // Output NEW or EXPIRED response, creating
    // confirmation number and TICKET-FILE, and mailing
    // confirmation number.  $op is NEW or EXPIRED.
    //
    function confirmation_reply ( $aid, $email, $op )
    {
        global $epm_root;

	$ticket = new_ticket ( 'c', $aid, $email );

	$sname = $_SERVER['SERVER_NAME']
	       . $epm_root;
	$lname = $email;
	if ( $aid != '-' ) $lname = "$aid:$email";
	$r = mail ( $email,
	       "Your EPM Confirmation Number",
	       "Your EPM $sname confirmation" .
	       " number\r\n" .
	       "     for $lname is:\r\n" .
	       "\r\n" .
	       "     $ticket\r\n",
	       "From: no_reply@$sname" );
	if ( $r === false )
	    ERROR ( "mailer failed" );

	reply ( "$op" );
    }

    $op = NULL;
    if ( isset ( $_POST['op'] ) )
	$op = $_POST['op'];

    // Process POSTs from xhttp.
    //
    if ( $op == 'MANUAL' )
    {
	$lname = trim ( $_POST['value'] );
	if ( preg_match ( '/^([^:]+):(.+)$/',
	                  $lname, $matches ) )
	    // We do not allow $aid or $email
	    // to be empty.
	{
	    $aid = $matches[1];
	    $email = $matches[2];
	}
	else
	{
	    $aid = '-';
	    $email = $lname;
	}
	DEBUG ( "LNAME $aid $email" );

	$e = filter_var
	    ( $email, FILTER_SANITIZE_EMAIL );

	if ( $e != $email
	     ||
	     ! filter_var
		      ( $email,
			FILTER_VALIDATE_EMAIL ) )
	    reply ( 'BAD_EMAIL' );
	elseif ( is_blocked ( $email ) )
	    reply ( 'BLOCKED_EMAIL' );

	if ( $aid != '-' )
	{
	    if ( ! preg_match ( $epm_name_re,
	                        $aid ) )
		reply ( 'BAD_AID' );
	    $dir = "admin/teams/$aid";
	    if ( ! is_dir ( "$epm_data/$dir" ) )
	        reply ( 'NO_TEAM' );
	    read_email_file ( 'a', $email );
	    if ( ! isset ( $uid ) )
	        reply ( 'NO_USER' );
	}
	confirmation_reply ( $aid, $email, 'NEW' );
    }
    elseif ( $op == 'AUTO' )
    {
	$ticket = trim ( $_POST['value'] );
	if ( ! preg_match ( '/^[a-fA-F0-9]{32}$/',
			    $ticket ) ) 
	    reply ( 'BAD_TICKET' );
	$tfile = "admin/browser/$ticket";
	if ( ! is_readable ( "$epm_data/$tfile" ) )
		reply ( 'NO_TICKET' );

	$c = @file_get_contents
	    ( "$epm_data/$tfile" );
	if ( $c === false )
	    ERROR ( "cannot read readable file" .
		    " $tfile" );
	@unlink ( "$epm_data/$tfile" );

	$c = trim ( $c );
	list ( $t, $aid, $email ) = explode ( ' ', $c );
	if ( is_blocked ( $email ) )
	    reply ( 'BLOCKED_EMAIL' );

	if ( read_email_file ( $t, $email ) )
	    confirmation_reply
	        ( $aid, $email, 'EXPIRED' );
		// Does not return

	if ( $aid != '-' )
	{
	    $dir = "admin/teams/$aid";
	    if ( ! is_dir ( "$epm_data/$dir" ) )
	        reply ( 'NO_TEAM' );
	    if ( ! isset ( $uid ) )
	        reply ( 'NO_USER' );
	    if ( ! is_readable
	               ( "$epm_data/$dir/$uid.login" ) )
	        reply ( 'USER_NOT_ON_TEAM' );
	    $_SESSION['EPM_AID'] = $aid;
	}
	elseif ( ! isset ( $uid ) )
	{
	    $_SESSION['EPM_EMAIL'] = $email;
	    $ticket = new_ticket ( 'a', $aid, $email );
	    reply ( "RENEW $ticket user.php" );
	}
	else
	{
	    $dir = "admin/users/$uid/";
	    $_SESSION['EPM_AID'] = $uid;
	}

	$_SESSION['EPM_UID'] = $uid;
	$_SESSION['EPM_EMAIL'] = $email;
	$_SESSION['EPM_RW'] = ( $aid == '-' );
	$_SESSION['EPM_IS_TEAM'] = ( $aid != '-' );

	$log = "$dir/$uid.login";
	$IPADDR = $_SESSION['EPM_IPADDR'];
	$browser = $_SERVER['HTTP_USER_AGENT'];
	$browser = preg_replace
	    ( '/\s*\([^\)]*\)\s*/', ' ', $browser );
	$browser = preg_replace
	    ( '/\s+/', ';', $browser );
	if ( ! is_dir ( "$epm_data/$dir" )
	     &&
	     ! @mkdir ( "$epm_data/$dir",
	                02770, true ) )
	    ERROR ( "could not make directory $dir" );
	$r = @file_put_contents
	    ( "$epm_data/$log",
	      "$STIME $email $IPADDR $browser" .
	      PHP_EOL,
	      FILE_APPEND );
	if ( $r === false )
	    ERROR ( "could not write $log" );

	$mtime = @filemtime ( "$epm_data/$log" );
	if ( $mtime === false )
	    ERROR ( "cannot stat $log" );
	$_SESSION['EPM_ABORT'] = [$log,$mtime];

	$_SESSION['EPM_EMAIL'] = $email;
	$ticket = new_ticket ( 'a', $aid, $email );
	reply ( "RENEW $ticket project.php" );
    }
    elseif ( $epm_method == 'POST' )
	exit ( "UNACCEPTABLE HTTP POST" );

    // Else load html and script.

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    button, input, mark, span, pre {
        font-size: var(--large-font-size);
    }
    #get_lname, #show_lname {
	background-color: #96F9F3;
	padding: var(--indent);
        font-size: var(--large-font-size);
    }
    #get_cnum {
	background-color: #80FFCC;
	padding: var(--indent);
        font-size: var(--large-font-size);
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

<div class='errors' id='ticket_error'
     style='display:none'>
<pre id='ticket_message'></pre>
</div>

<div class='errors' id='lname_error'
     style='display:none'>
<pre id='lname_message'></pre>
</div>

<div id='get_lname' style.display='none'>
<table style='width:100%'>
<tr><td style='width:90%'>
<input type='text' id='lname_in'
       placeholder=
	'Enter Login Name:   [Account ID:]Email Address'
       autofocus
       size='40'
       title='User-Email-Address
or Team-ID:Member-User-Email-Address
or User-ID:Coach-User-Email-Address'>
<button type='button' onclick='GOT_LNAME()'>
    Submit</button>
<button type='button'
        onclick='CLEAR_LNAME()'>
    Clear</button>
</td><td style='width:10%;text-align:right'>
<strong>Help &rarr;</strong>
<button type='button' onclick='HELP("login-page")'>
?</button>
</td></tr></table>
<br>
<strong>New Users -
        <button type=button onclick='AUX_WINDOW
	    ( "+help+", "guide.html",
	      -800, 0, 800, 800 )'>
        See Guide</button>
</div>

<div id='show_lname' style.display='none'>
<table style='width:100%'>
<tr><td style='width:90%'>
<strong>Login Name:<pre>   </pre>
<span id='lname_out'></span></strong>
</td><td style='width:10%;text-align:right'>
<button type='button' onclick='HELP("login-page")'>
?</button>
</td></tr></table>
<br>
<button type='button'
        onclick='location.assign("login.php")'>
Change Login Name
</button>
</div>

<div id='get_cnum' style.display='none'>
A Confirmation Number has been sent
to the Email Address.
<br>
<br>
Please <input type='text' size='40' id='cnum_in'
       placeholder='Enter Confirmation Number'>
</div>


<?php
    $f = "admin/motd.html";
    if ( file_exists ( "$epm_data/$f" ) )
    {
	echo "<div class='terms'>";
        readfile ( "$epm_data/$f" );
	echo "</div>";
    }
?>
<div class='terms'>
<?php require "$epm_home/include/epm_terms.html"; ?>
</div>

<script>

var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

let ID = '<?php echo $ID; ?>';
let xhttp = new XMLHttpRequest();
let storage = window.localStorage;

let ticket_error =
    document.getElementById("ticket_error");
let ticket_message =
    document.getElementById("ticket_message");
let lname_error =
    document.getElementById("lname_error");
let lname_message =
    document.getElementById("lname_message");

let get_lname = document.getElementById("get_lname");
let lname_in = document.getElementById("lname_in");
let lname_out = document.getElementById("lname_out");
let show_lname = document.getElementById("show_lname");
let get_cnum = document.getElementById("get_cnum");
let cnum_in = document.getElementById("cnum_in");

// Call for internal errors, like bad xhttp response
// code or response message format.
//
function FAIL ( message )
{
    alert ( message );
    location.assign ( 'login.php' );
}

function TICKET_ERROR ( message )
{
    ticket_error.style.display = 'block';
    ticket_message.innerText = message;
    MANUAL_ID();
}

function LNAME_ERROR ( message )
{
    lname_error.style.display = 'block';
    lname_message.innerText = message;
    BEGIN();
}

var REQUEST_IN_PROGRESS = false;
var RESPONSE = '';  // Saved here for error messages.
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
	RESPONSE = this.responseText;
	item = RESPONSE.trim().split ( ' ' );
	ID = item.shift();
	if ( ID == undefined )
	    FAIL ( 'empty response' );
	callback ( item );
    };
    xhttp.open ( 'POST', "login.php", true );
    xhttp.setRequestHeader
        ( "Content-Type",
	  "application/x-www-form-urlencoded" );
    REQUEST_IN_PROGRESS = true;
    data += '&xhttp=yes&id=' + ID;
    LOG ( 'xhttp sent: ' + data );
    xhttp.send ( data );
}
function MALFORMED_RESPONSE ( when )
{
    FAIL ( "malformed response `" + RESPONSE +
           "' " + when );
}

let PATH = location.pathname;
var LNAME, AID, EMAIL, TICKET;
let lname_re = /^([^:]+):(.+)$/;
    // We do not allow AID or EMAIL to be empty.

var GET_LNAME_ENABLED = false;
var GET_CNUM_ENABLED = false;
    // These are set to true to enable callback, and
    // set false just before making callback, to avoid
    // spurious callbacks.

var CNUM_SENT = false;
    // This is set true if AUTO_ID sent CNUM and
    // not stored TICKET.

function BEGIN()
{
    GET_LNAME_ENABLED = true;
    get_lname.style.display = 'block';
    show_lname.style.display = 'none';
    get_cnum.style.display = 'none';
}
BEGIN();

function LNAME_KEYDOWN ( event )
{
    if ( event.code == 'Enter'
         &&
	 GET_LNAME_ENABLED )
        GOT_LNAME();
}
lname_in.addEventListener ( 'keydown', LNAME_KEYDOWN );

function CLEAR_LNAME()
{
    lname_in.value = '';
    ticket_error.style.display = 'none';
    lname_error.style.display = 'none';
}

function GOT_LNAME()
{
    ticket_error.style.display = 'none';
    lname_error.style.display = 'none';
    lname = lname_in.value.trim();
    if ( /^\S+@\S+\.\S+$/.test(lname) )
	GET_LNAME_ENABLED = false;
    else
    {
	if ( lname != '' )
	    alert ( lname + " is badly formed" +
			    " login name" );
	return;
    }

    LNAME = lname;
    AID = '';
    EMAIL = LNAME;
    let matches = lname.match ( lname_re );
    if ( matches != null )
    {
        AID = matches[1];
	EMAIL = matches[2];
    }
    get_lname.style.display = 'none';
    lname_out.innerText = LNAME;
    show_lname.style.display = 'block';
    TICKET = storage.getItem(PATH + '\0' + LNAME);
    if ( TICKET == null )
        MANUAL_ID();
    else
	AUTO_ID ( false );
}

function MANUAL_ID()
{
    SEND ( "op=MANUAL&value="
           + encodeURIComponent ( LNAME ),
           MANUAL_RESPONSE,
	   'sending ' + LNAME + ' to server' );
}

function NO_USER_ERROR()
{
    LNAME_ERROR
         ( EMAIL + ' is not an email address of'
		 + ' an existing personal account;'
		 + ' before you can log into the team'
		 + ' you must do the following:'
		 + ' if you have an account, log'
		 + ' into it and add ' + EMAIL
		 + ' to it; otherwise use ' + EMAIL
		 + ' by itself as a login name and'
		 + ' create a new personal account'
		 + ' for yourself' );
}

function MANUAL_RESPONSE ( item )
{
    if ( item.length != 1 )
        MALFORMED_RESPONSE
	    ( 'after sending ' + LNAME + ' to server' );
    else if ( item[0] == 'BAD_AID' )
        LNAME_ERROR
	    ( AID + ' is badly formatted team ID' );
    else if ( item[0] == 'BAD_EMAIL' )
        LNAME_ERROR ( EMAIL + ' is badly formatted'
	             + ' email address' );
    else if ( item[0] == 'BLOCKED_EMAIL' )
        LNAME_ERROR
	    ( EMAIL + ' is a blocked email address' );
    else if ( item[0] == 'NO_TEAM' )
        LNAME_ERROR
	    ( AID + ' does not name an existing team' );
    else if ( item[0] == 'NO_USER' )
        NO_USER_ERROR();
    else if ( item[0] == 'NEW' )
        CONFIRM();
    else
        MALFORMED_RESPONSE
	    ( 'after sending ' + LNAME + ' to server' );
};

function CONFIRM ( message = '' )
{
    if ( message != '' )
    {
	ticket_error.style.display = 'block';
	ticket_message.innerText = message;
    }
    GET_CNUM_ENABLED = true;
    get_cnum.style.display = 'block';
    cnum_in.value = '';
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
	    alert ( value + ' is not a valid' +
	            ' confirmation number' );
    }
}
cnum_in.addEventListener ( 'keydown', CNUM_KEYDOWN );

function GOT_CNUM ( cnum )
{
    ticket_error.style.display = 'none';
    lname_error.style.display = 'none';
    TICKET = cnum;
    AUTO_ID ( true );
}

function AUTO_ID ( cnum_sent )
{
    CNUM_SENT = cnum_sent;
    storage.removeItem ( PATH + '\0' + LNAME );
    SEND ( 'op=AUTO&value=' + TICKET,
           AUTO_RESPONSE, 'auto-login' );
}

function AUTO_RESPONSE ( item )
{
    if ( item[0] == 'RENEW' && item.length == 3 )
    {
	TICKET = item[1];
	if ( ! /^[a-fA-F0-9]{32}$/.test(TICKET) )
	    MALFORMED_RESPONSE ( 'to auto-login' );
	storage.setItem ( PATH + '\0' + LNAME, TICKET );

	try {
	    window.location.assign
	        ( item[2] + '?id=' + ID );
	} catch ( e ) {
	    FAIL
	       ( 'could not access page ' + item[2] );
	       // On retry login.php will go to
	       // correct page.
	}
    }
    else if ( item.length !== 1 )
	MALFORMED_RESPONSE ( 'to auto-login' );
    else if ( item[0] == 'EXPIRED' )
    {
        CONFIRM ( 'you must re-confirm because your' +
	          ' auto-login period has expired' );
    }
    else if ( item[0] == 'BAD_TICKET' )
    {
        if ( CNUM_SENT )
	    TICKET_ERROR
	        ( 'malformed confirmation number;' +
	          ' a new confirmation number' +
	          ' has been sent' );
	else
	    TICKET_ERROR
	        ( 'malformed ticket; you must' +
	          ' re-confirm because your' +
	          ' auto-login failed' );
    }
    else if ( item[0] == 'NO_TICKET' )
    {
        if ( CNUM_SENT )
	    TICKET_ERROR
	        ( 'bad confirmation number;' +
	          ' a new confirmation number' +
	          ' has been sent' );
	else
	    TICKET_ERROR
	        ( 'ticket not found; you must' +
	          ' re-confirm because your' +
	          ' auto-login failed' );
    }
    else if ( item[0] == 'NO_TEAM' )
        LNAME_ERROR ( AID + ' does not name an existing'
	                  + ' team' );
    else if ( item[0] == 'NO_USER' )
        NO_USER_ERROR();
    else if ( item[0] == 'USER_NOT_ON_TEAM' )
        LNAME_ERROR ( 'User ' + EMAIL + ' is not on' +
		      ' team ' + AID );
    else if ( item[0] == 'BLOCKED_EMAIL' )
        LNAME ( EMAIL + ' is a blocked email address' );
    else
	MALFORMED_RESPONSE ( 'to auto-login' );
}
    
</script>


</body>
</html>
