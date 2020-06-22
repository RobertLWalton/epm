<?php

// File:    parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Mon Jun 22 17:40:18 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// Per web site EPM parameters.  An edited version of
// this file located in the $_SERVER['DOCUMENT_ROOT']
// directory is included at the beginning of all pages
// via:
//
//    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";
//
// Index.php contains `require "parameters.php';'.

// This file is also included by bin/epm_run and similar
// programs via:
//
//    $epm_self='bin/PROGRAM-NAME';
//    $epm_web='DOCUMENT-ROOT';
//    require "$epm_web/parameters.php";

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
//	ln -s H/page/index.php .
//	cp -p H/page/parameters.php .
//	chmod u+w parameters.php
//	<edit R/parameters.php>


// Parameters that you NEED to edit:
//
$epm_data = dirname ( $epm_web ) . '/epm_028746537635';
    // WARNING:
    //   This is only a test setting; reset this to
    //   D above (and UNLIKE the test setting, be
    //   sure D is not a descendant of R).
    //
    //   Include a NON-PUBLIC, SITE-SPECIFIC 12 digit
    //   random number as part of the LAST COMPONENT
    //   of the name of D.

$epm_home = dirname ( $epm_web );
    // WARNING:
    //   This is only a test setting; reset this to H
    //   above (and UNLIKE the test setting, be sure
    //   sure H is not a descendant of R).

$epm_session_name = "EPM_859036254367";
    // Reset 12 digit number to NON-PUBLIC, SITE-
    // SPECIFIC 12 digit random number.

$epm_check_ipaddr = true;
    // If true a session is not allowed to change its
    // IP address.  Set to true if server is not a
    // secure server (running SSL with a certificate).

$epm_debug = preg_match
    ( '/(login|user|problem|run|project|list)/',
      $epm_self );
    // True to turn debugging on; false for off.


// Parameters you may like to edit:

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


// Parameters you probably do NOT want to edit.
// Be aware that changing these may conflict with
// EPM code.

$epm_supported_browsers = ['Chrome', 'Firefox'];
    // Add to this list after testing on indicated
    // browsers.

$epm_shell_timeout = 3;
    // Number of seconds to wait for the shell to
    // startup and execute initialization commands
    // for a .sh script.

$epm_time_format = "%FT%T%z";
    // Format for times, as per strftime.

$epm_name_re =
    '/^[A-Za-z][-_A-Za-z0-9]*[A-Za-z0-9]$/';
    // Regular expression matching only legal EPM
    // names, which have only letters, digits,
    // underline(_), and dash(-), begin with a letter,
    // and end with a letter or digit.

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

$epm_parent_re =
    '#^\.\./\.\./\.\./(projects/[^/]+/[^/]+)$#';
    // Regular expression to target directory of
    // +parent+ link.  The first match is the
    // target directory relative to $epm_data.

$epm_specials =
    ['generate','filter','display','monitor'];
    // Files with names SPECIAL-PROBLEM are executable
    // that perform SPECIAL actions for PROBLEM.

$epm_score_file_written = 119;
    // epm_sandbox exit code if it writes score file.

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
    "mout" => "utf8",
    "sout" => "utf8",
    "dout" => "utf8",
    "fout" => "utf8",
    "rout" => "utf8",
    "tout" => "utf8",
    "cerr" => "utf8",
    "gerr" => "utf8",
    "g1err" => "utf8",
    "g2err" => "utf8",
    "merr" => "utf8",
    "serr" => "utf8",
    "derr" => "utf8",
    "ferr" => "utf8",
    "rerr" => "utf8",
    "terr" => "utf8",
    "log" => "utf8",
    "fls" => "utf8",
    "txt" => "utf8",
    "score" => "utf8"
    ];

$display_file_map = [
    // See display_file_type.
    //
    "utf8" => "utf8_show.php",
    "pdf"  => "pdf_show.php" ];

$push_file_map = [
    // If file YYYY.EEE is to be pushed then
    // $push_file_map['EEE'] must be set.  If it is
    // 'R' then the file should exist in the remote
    // (push destination) directory.  If it is 'L',
    // the file should exist in the remote directory
    // and be linked into the local directory.  If
    // it is 'S' then the file should exist in the
    // +solutions+ subdirectory of the remote directory.
    // If it is a (sub)map RE => V, then each RE is a
    // regular expression such that if YYYY matches
    // RE then V is to be used: it will be 'R' or 'L'.
    // In the RE, `PPPP' is replaced by the problem
    // name before the RE is used.
    //
    // Note: merge of .optn files is handled separately.
    //
    "c" => "S",
    "cc" => "S",
    "java" => "S",
    "py" => "S",
    "tex" => "S",
    "in" => ["00-\\d+-PPPP" => "L",
             "\\d+-\\d+-PPPP" => "R"],
    "ftest" => ["00-\\d+-PPPP" => "L",
                "\\d+-\\d+-PPPP" => "R"],
    "run" => ["sample-PPPP" => "L", ".*-PPPP" =>"R"],
    "pdf" => [ "PPPP" => "L" ],
    "" => [ "generate-PPPP" => "R",
            "filter-PPPP" => "R" ]
];

$executable_ext = ['','class','pyc'];
    // Extensions of executable files.

$linkable_ext = ['','c','cc','class','java','pyc','py'];
    // If EEE is a $linkable_ext then files with
    // a name of the form YYYY-PPPP.EEE can be the
    // targets of a link PPPP.EEE, and similarly files
    // with names YYYY-ZZZZ-PPPP.EEE can be targets of
    // ZZZZ-PPPP.EEE if ZZZZ is `generate', `filter',
    // or `monitor'.

// The following are functions shared with bin/epm_run
// and others.  They are not parameters and should not
// be changed.

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

// Locks directory.  For LOCK_EX lock, stores microtime
// into directory/+lock+ and returns the previous value
// of the lock (0 if none).  For LOCK_SH lock, read
// directory/+lock+ and returns its value (0 if there
// is no lock).  The microtime is stored as a floating
// point string.
//
// The lock is released by UNLOCK or on shutdown.  LOCK
// also releases any previous lock (there can be at most
// one lock).
//
$epm_lock = NULL;
function LOCK ( $dir, $type )
{
    global $epm_data, $epm_lock;

    if ( isset ( $epm_lock ) ) UNLOCK();
    $f = "$dir/+lock+";
    $epm_lock = fopen ( "$epm_data/$f", 'w+' );
    if ( $epm_lock === false )
        ERROR ( "cannot open $f" );
    $r = flock ( $epm_lock, $type );
    if ( $r === false )
        ERROR ( "cannot lock $f" );
    $value = fread ( $epm_lock, 100 );
    if ( $value == '' ) $value = '0';
    elseif ( floatval ( $value ) == 0 )
	ERROR ( "bad value `$value' read from $f" );
    if ( $type == LOCK_EX )
    {
        $time = strval ( microtime ( true ) );
	fwrite ( $epm_lock, $time );
    }
    return $value;
}

function UNLOCK()
{
    global $epm_lock;

    if ( ! isset ( $epm_lock ) ) return;
    flock ( $epm_lock, LOCK_UN );
    $epm_lock = NULL;
}
register_shutdown_function ( 'UNLOCK' );

?>
