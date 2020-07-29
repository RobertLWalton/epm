<?php

    // File:	login.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jul 29 17:08:05 EDT 2020

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
    // a page to go to (page/project.php or for new
    // users page/user.php).
    //
    // The protocol is:
    //
    // BEGIN:
    //	   * Set PATH = location.pathname.
    //     * Get EMAIL from user.
    //	   * Get BID = localStorage.getItem(PATH\0EMAIL)
    //	   * If BID != null:
    //          * go to AUTO_ID
    // MANUAL_ID:
    //     * Send 'op=MANUAL&value=EMAIL'
    //     * Receive one of:
    //           'BAD_TID': go to FAIL
    //           'BAD_EMAIL': go to FAIL
    //           'BLOCKED_EMAIL': go to FAIL
    //           'NO_TEAM': go to FAIL
    //           'NO_USER': go to FAIL
    //           'NEW': go to CONFIRM
    // AUTO_ID:
    //     * Send 'op=AUTO&value=BID'
    //	   * Receive one of:
    //           'EXPIRED':
    //		     tell user reconfirmation needed
    //               go to CONFIRM
    //           'BLOCKED_EMAIL': go to FAIL
    //           'BAD_TICKET': go to MANUAL_ID
    //           'NO_TICKET': go to MANUAL_ID
    //           'NO_TEAM': go to FAIL
    //           'NO_USER': go to FAIL
    //		 'USER_NOT_ON_TEAM': go to FAIL
    //           'RENEW BID NEXT_PAGE':
    //		     go to FINISH
    // CONFIRM:
    //     * Get BID = confirmation number from user.
    //     * go to AUTO_ID
    // FINISH:
    //     * localStorage.setItem(PATH\0EMAIL,BID)
    //     * Issue GET to NEXT_PAGE
    // FAIL:
    //     * Output message
    //     * reload

    $epm_page_type = '+main+';
    $epm_page_init = true;
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
    // this function just returns true.  If no, this
    // function returns false.
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

    // Create new BID and its BID-FILE and return
    // new bid.
    //
    function new_bid ( $t, $tid, $email )
    {
	global $epm_data;

	while ( true )
	{
	    $bid = bin2hex ( random_16_bytes ( 16 ) );
	    $bdir = "admin/browser";
	    if ( ! is_dir ( "$epm_data/$bdir" ) 
	         &&
		 ! @mkdir ( "$epm_data/$bdir",
		            02770, true ) )
	        ERROR ( "cannot make $bdir" );
	    $bfile = "$bdir/$bid";
	    if ( is_readable ( "$epm_data/$bfile" ) )
	    {
	        WARN ( 'THIS SHOULD NEVER HAPPEN' );
	        continue;
	    }
	    file_put_contents
	        ( "$epm_data/$bfile",
	          "$t $tid $email" . PHP_EOL );
	    return $bid;
	}
    }

    // Output NEW or EXPIRED response, creating
    // confirmation number and BID-FILE, and mailing
    // confirmation number.  $op is NEW or EXPIRED.
    //
    function confirmation_reply ( $tid, $email, $op )
    {
        global $epm_root;

	$bid = new_bid ( 'c', $tid, $email );

	$sname = $_SERVER['SERVER_NAME']
	       . $epm_root;
	$r = mail ( $email,
	       "Your EPM Confirmation Number",
	       "Your EPM $sname confirmation number" .
	       " is:\r\n" .
	       "\r\n" .
	       "     $bid\r\n",
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
	    // We do not allow $tid or $email
	    // to be empty.
	{
	    $tid = $matches[1];
	    $email = $matches[2];
	}
	else
	{
	    $tid = '-';
	    $email = $lname;
	}
	DEBUG ( "LNAME $tid $email" );

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

	if ( $tid != '-' )
	{
	    if ( ! preg_match ( $epm_name_re,
	                        $tid ) )
		reply ( 'BAD_TID' );
	    $dir = "admin/teams/$tid";
	    if ( ! is_dir ( "$epm_data/$dir" ) )
	        reply ( 'NO_TEAM' );
	    read_email_file ( 'a', $email );
	    if ( ! isset ( $uid ) )
	        reply ( 'NO_USER' );
	}
	confirmation_reply ( $tid, $email, 'NEW' );
    }
    elseif ( $op == 'AUTO' )
    {
	$bid = trim ( $_POST['value'] );
	if ( ! preg_match ( '/^[a-fA-F0-9]{32}$/',
			    $bid ) ) 
	    reply ( 'BAD_TICKET' );
	$bfile = "admin/browser/$bid";
	if ( ! is_readable ( "$epm_data/$bfile" ) )
		reply ( 'NO_TICKET' );

	$c = @file_get_contents
	    ( "$epm_data/$bfile" );
	if ( $c === false )
	    ERROR ( "cannot read readable file" .
		    " $bfile" );
	@unlink ( "$epm_data/$bfile" );

	$c = trim ( $c );
	list ( $t, $tid, $email ) = explode ( ' ', $c );
	if ( is_blocked ( $email ) )
	    reply ( 'BLOCKED_EMAIL' );

	if ( read_email_file ( $t, $email ) )
	    confirmation_reply
	        ( $tid, $email, 'EXPIRED' );
		// Does not return

	if ( $tid != '-' )
	{
	    $dir = "admin/teams/$tid";
	    if ( ! is_dir ( "$epm_data/$dir" ) )
	        reply ( 'NO_TEAM' );
	    if ( ! isset ( $uid ) )
	        reply ( 'NO_USER' );
	    if ( ! is_readable
	               ( "$epm_data/$dir/$uid.login" ) )
	        reply ( 'USER_NOT_ON_TEAM' );
	    $_SESSION['EPM_AID'] = $tid;
	}
	elseif ( ! isset ( $uid ) )
	{
	    $_SESSION['EPM_EMAIL'] = $email;
	    $bid = new_bid ( 'a', $tid, $email );
	    reply ( "RENEW $bid user.php" );
	}
	else
	{
	    $dir = "admin/users/$uid/";
	    $_SESSION['EPM_AID'] = $uid;
	}

	$_SESSION['EPM_UID'] = $uid;
	$_SESSION['EPM_EMAIL'] = $email;
	$_SESSION['EPM_IS_TEAM'] = ( $tid != '-' );
	$_SESSION['EPM_RW'] = ( $tid == '-' );

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
	$bid = new_bid ( 'a', $tid, $email );
	reply ( "RENEW $bid project.php" );
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

<div id='get_lname' style.display='none'>
<table style='width:100%'>
<tr><td style='width:90%'>
<input type='text' id='lname_in'
       placeholder=
           'Enter Login Name:   [Team ID:]Email Address'
       autofocus
       size='40'
       title='[Team-ID:]Email-Address'>
<button type='button' onclick='GOT_LNAME()'>
    Submit</button>
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

var ID = '<?php echo $ID; ?>';
var xhttp = new XMLHttpRequest();
var storage = window.localStorage;
var get_lname = document.getElementById("get_lname");
var lname_in = document.getElementById("lname_in");
var lname_out = document.getElementById("lname_out");
var show_lname = document.getElementById("show_lname");
var get_cnum = document.getElementById("get_cnum");
var cnum_in = document.getElementById("cnum_in");


// Output message to user asynchronously while at the
// same time continuing by returning from this function.
// 
function ALERT ( message )
{
    // Alert must be scheduled as separate task.
    //
    setTimeout ( function () { alert ( message ); } );
}

function FAIL ( message )
{
    alert ( message );
    location.assign ( 'login.php' );
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
var LNAME, TID, EMAIL, BID;
let lname_re = /^([^:]+):(.+)$/;
    // We do not allow TID or EMAIL to be empty.

var GET_LNAME_ENABLED = false;
var GET_CNUM_ENABLED = false;
    // These are set to true to enable callback, and
    // set false just before making callback, to avoid
    // spurious callbacks.

// BEGIN:
//
GET_LNAME_ENABLED = true;
get_lname.style.display = 'block';
show_lname.style.display = 'none';
get_cnum.style.display = 'none';

function LNAME_KEYDOWN ( event )
{
    if ( event.code == 'Enter'
         &&
	 GET_LNAME_ENABLED )
        GOT_LNAME();
}
lname_in.addEventListener ( 'keydown', LNAME_KEYDOWN );

function GOT_LNAME()
{
    lname = lname_in.value.trim();
    if ( /^\S+@\S+\.\S+$/.test(lname) )
	GET_LNAME_ENABLED = false;
    else
    {
	if ( lname != '' )
	    ALERT ( lname + " is badly formed" +
			    " login name" );
	return;
    }

    LNAME = lname;
    TID = '';
    EMAIL = LNAME;
    let matches = lname.match ( lname_re );
    if ( matches != null )
    {
        TID = matches[1];
	EMAIL = matches[2];
    }
    get_lname.style.display = 'none';
    lname_out.innerText = LNAME;
    show_lname.style.display = 'block';
    BID = storage.getItem(PATH + '\0' + LNAME);
    if ( BID == null )
        MANUAL_ID();
    else
	AUTO_ID();
}

function MANUAL_ID()
{
    SEND ( "op=MANUAL&value="
           + encodeURIComponent ( LNAME ),
           MANUAL_RESPONSE,
	   'sending ' + LNAME + ' to server' );
}

function NO_USER_FAIL()
{
    FAIL ( EMAIL + ' is not an email address of'
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
    else if ( item[0] == 'BAD_TID' )
        FAIL ( TID + ' is badly formatted team ID' );
    else if ( item[0] == 'BAD_EMAIL' )
        FAIL ( EMAIL + ' is baddly formatted email'
	             + ' address' );
    else if ( item[0] == 'BLOCKED_EMAIL' )
        FAIL ( EMAIL + ' is a blocked email address' );
    else if ( item[0] == 'NO_TEAM' )
        FAIL ( TID + ' does not name an existing'
	           + ' team' );
    else if ( item[0] == 'NO_USER' )
        NO_USER_FAIL();
    else if ( item[0] == 'NEW' )
        CONFIRM();
    else
        MALFORMED_RESPONSE
	    ( 'after sending ' + LNAME + ' to server' );
};

function CONFIRM()
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
    BID = cnum;
    AUTO_ID();
}

function AUTO_ID()
{
    storage.removeItem ( PATH + '\0' + LNAME );
    SEND ( 'op=AUTO&value=' + BID,
           AUTO_RESPONSE, 'auto-login' );
}

function AUTO_RESPONSE ( item )
{
    if ( item[0] == 'RENEW' && item.length == 3 )
    {
	BID = item[1];
	if ( ! /^[a-fA-F0-9]{32}$/.test(BID) )
	    MALFORMED_RESPONSE ( 'to auto-login' );
	storage.setItem ( PATH + '\0' + LNAME, BID );

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
        ALERT ( 'you must re-confirm because your' +
	        ' auto-login period has expired' );
        CONFIRM();
    }
    else if ( item[0] == 'BAD_TICKET' )
    {
        ALERT ( 'malformed ticket; you must' +
	        ' re-confirm because your' +
	        ' auto-login failed' );
        MANUAL_ID();
    }
    else if ( item[0] == 'NO_TICKET' )
    {
        ALERT ( 'ticket not found; you must' +
	        ' re-confirm because your' +
	        ' auto-login failed' );
        MANUAL_ID();
    }
    else if ( item[0] == 'NO_TEAM' )
        FAIL ( TID + ' does not name an existing'
	           + ' team' );
    else if ( item[0] == 'NO_USER' )
        NO_USER_FAIL();
    else if ( item[0] == 'USER_NOT_ON_TEAM' )
        FAIL ( 'User ' + EMAIL + ' is not on team '
	               + TID );
    else if ( item[0] == 'BLOCKED_EMAIL' )
        FAIL ( EMAIL + ' is a blocked email address' );
    else
	MALFORMED_RESPONSE ( 'to auto-login' );
}
    
</script>


</body>
</html>
