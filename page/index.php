<?php

// File:    index.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Mar 12 03:12:52 EDT 2020

// Per web site EPM parameters.  An edited version of
// this file located in the $_SERVER['DOCUMENT_ROOT']
// directory is `required' at the beginning of all EPM
// pages via:
//
//    require "{$_SERVER['DOCUMENT_ROOT']}/index.html"

$php_self = $_SERVER['PHP_SELF'];
if ( $php_self == '/page/index.php' )
{
    // This is the unedited version of the file, and
    // we need to go to the edited version.
    //
    header ( 'Location: index.php' );
    exit;
}

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

// Parameters that you need to edit in R/index.php:
//
$epm_data = dirname ( $_SERVER['DOCUMENT_ROOT'] )
          . '/epm_658746537635';
    // WARNING:
    //   This is only a test setting; reset this to
    //   D above (and UNLIKE the test setting, be
    //   sure D is not a descendant of R).
    //
    //   Include a NON-PUBLIC SITE-SPECIFIC 12 digit
    //   random number as part of the LAST COMPONENT
    //   of the name of D.

$epm_home = dirname ( $_SERVER['DOCUMENT_ROOT'] );
    // WARNING:
    //   This is only a test setting; reset this to H
    //   above (and UNLIKE the test setting, be sure
    //   sure H is not a descendant of R).

session_name ( "EPM_859036254367" );
    // Reset 12 digit number to NON-PUBLIC SITE-
    // SPECIFIC 12 digit random number.

$epm_debug = '';
$epm_debug = '/(login|user|problem|run)/';
    // If not '', this must be a regular expression
    // which when matched to $php_self enables the
    // DEBUG function to write to $epm_data/debug.log.
    // Set to '' to disable DEBUG function.

$epm_max_emails = 3;
    // Max number of email addresses a user may have.

$epm_expiration_times =
	[ 2*24*60*60, 7*24*60*60, 30*24*60*60];
    // [2, 7, 30] days; ticket expiration times
    // for 1st, 2nd, and >= 3rd tickets.

$epm_file_maxsize = 16*1024*1024;  // 16 megabytes.
    // Maximum size any file.

$epm_upload_maxsize = 256*1024;  // 256 kilobytes.
    // Maximum size of uploaded file.

$epm_shell_timeout = 3;
    // Number of seconds to wait for the shell to
    // startup and execute initialization commands
    // for a .sh script.

$epm_name_re =
    '/^[A-Za-z][-_A-Za-z0-9]*[A-Za-z]$/';
    // Regular expression matching only legal EPM
    // names, which have only letters, digits,
    // underline(_), and dash(-), and begin and end
    // with a letter.

$epm_filename_re =
    '/^[A-Za-z0-9](|[-_A-Za-z0-9]*[A-Za-z0-9])' .
    '(|\.[A-Za-z0-9](|[-_A-Za-z0-9]*[A-Za-z0-9]))$/';
    // Regular expression matching only legal EPM
    // public file names (not matching +XXX+ names
    // used internally).  These names can contain
    // only letters, digits, dash(-), and underline(_),
    // except for a single dot(.) introducing the
    // extension, and dash(-) and underline(_) must
    // not be the first or last character of the file
    // base name or extension.

$upload_target_ext = [
    // If file YYYY.EEE is uploadable, then
    // $upload_target_ext['EEE'] = 'FFF' must be
    // defined and after YYYY.EEE is uploaded, the
    // file YYYY.FFF must be makeable (i.e., there must
    // be a template YYYY.EEE:YYYY.FFF:....tmpl).
    //
    "c" => "",
    "cc" => "",
    "java" => "class",
    "py" => "pyc",
    "tex" => "pdf",
    "in" => "sout",
    "run" => "run" ];

$display_file_type = [
    // To be listed as a problem file, and thence be
    // `displayable', a file must have extension EEE
    // such that $display_file_type['EEE'] == TTT
    // exists.  If display_file_map[TTT] = GGGG then the
    // web page /page/GGGG may be used to display the
    // file.  Otherwise TTT is the file type and only
    // that is displayed.
    //
    // WARNING: the UNIX file(1) command CANNOT be
    //          reliably used to determine whether
    //          a file is ASCII, UTF-8, or PDF.
    //
    "c" => "utf8",
    "cc" => "utf8",
    "java" => "utf8",
    "py" => "utf8",
    "tex" => "utf8",
    "" => "Compiled Binary Executable",
    "class" => "Compiled JAVA Executable",
    "pyc" => "Compiled PYTHON Executable",
    "run" => "utf8",
    "pdf" => "pdf",
    "in" => "utf8",
    "sin" => "utf8",
    "test" => "utf8",
    "ftest" => "utf8",
    "cout" => "utf8",
    "sout" => "utf8",
    "dout" => "utf8",
    "fout" => "utf8",
    "rout" => "utf8",
    "cerr" => "utf8",
    "gerr" => "utf8",
    "g1err" => "utf8",
    "g2err" => "utf8",
    "err" => "utf8",
    "serr" => "utf8",
    "derr" => "utf8",
    "ferr" => "utf8",
    "rerr" => "utf8",
    "log" => "utf8",
    "fls" => "utf8",
    "score" => "utf8"
    ];

$display_file_map = [
    // See display_file_type.
    //
    "utf8" => "utf8_show.php",
    "pdf"  => "pdf_show.php" ];

$epm_supported_browsers = ['Chrome', 'Firefox'];
    // Add to this list after testing on indicated
    // browsers.

// The rest of this file is code that is not to be
// changed.  This code is effectively included at the
// start of all EPM PHP pages.

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
    if ( $php_self != "/page/login.php" )
    {
	header ( 'Location: /page/login.php' );
	exit;
    }
}
else if ( ! isset ( $_SESSION['EPM_UID'] ) )
{
    if ( $php_self != "/page/user.php" )
    {
	header ( 'Location: /page/user.php' );
	exit;
    }
}
else if ( $php_self == "/index.php" )
{
    header ( 'Location: /page/problem.php' );
    exit;
}

if ( $epm_debug != ''
     &&
     preg_match ( $epm_debug, $php_self ) )
{
    $epm_debug_desc = fopen
	( "$epm_data/debug.log", 'a' );
    $epm_debug_base = pathinfo
	( $php_self, PATHINFO_BASENAME );

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
        exit ( $message );

    return true;
        // Returning true suppresses normal error
	// handling.
}

set_error_handler ( 'EPM_ERROR_HANDLER' );

// Returns HTML for a help button that goes to the
// specified item in the help.html file.  Within
// HTML call this with:
//
//     <?php echo ( HELP ( '...' ) ); ?>
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
