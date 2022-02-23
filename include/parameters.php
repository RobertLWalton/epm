<?php

// File:    parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed Feb 23 13:45:46 EST 2022

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// Per web site EPM parameters.  An edited version of
// this file located in $epm_web.  At the beginning of
// all pages is in epm/pages is
//
//    require __DIR__ . '/index.php';
//
// Index.php computes $epm_root == /ROOT and sets
// $epm_web =$_SERVER['DOCUMENT_ROOT']/ROOT.  It then
// executes:
//
//    require "$epm_web/parameters.php";

// This file is also included by bin/epm_run and similar
// programs via:
//
//    $epm_self='bin/PROGRAM-NAME';
//    require "$epm_web/parameters.php";
//
// where $epm_web is computed by searching the current
// directories and its ancestors for a +web+ link to
// $epm_web.  This file is included by bin/epm in some
// cases in which $epm_web is given as an argument to
// bin/epm.

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

$epm_home = dirname ( $epm_web ) . '/epm';
    // WARNING:
    //   This is only a test setting; reset this to H
    //   above (and UNLIKE the test setting, be sure
    //   sure H is not a descendant of R).

$epm_session_name = "EPM_859036254367";
    // Reset 12 digit number to NON-PUBLIC, SITE-
    // SPECIFIC 12 digit random number.

date_default_timezone_set ( 'America/New_York' );
    // Timezone used for $epm_time_format (see below).

$epm_check_ipaddr = true;
    // If true a session is not allowed to change its
    // IP address.  Set to true if server is not a
    // secure server (running SSL with a certificate).

$epm_debug = preg_match
    ( '/(XXX|YYY|ZZZ)/',
      $epm_self );
    // True to turn debugging on; false for off.

// Parameters you may like to edit:

// Throttle Parameters:
//
//	long_c = 0.9772372	long_n = 100
//	short_c = 0.7943282	short_n = 10
//
//	dt = time since last request for session
//	s = dt + c * s = dt[0] + c*dt[1]
//             + c^2*dt[2] + ...
//
//	if ( (1-c) * s < DT && dt < DT ) delay DT - dt
//
//      c is chosen so c^n = 0.1 and 90% of s
//      is in dt[0] + c*dt[1] + ... + c^(n-1)*dt[n-1]
//
//	limit = DT / (1-c) so s < limit is the same
//	as (1-c) * s < DT
//
$epm_long_time_constant = 0.9772372;
$epm_short_time_constant = 0.7943282;
$epm_long_delay = 1.0;
$epm_short_delay = 0.1;
$epm_long_limit = 43.931374;
$epm_short_limit = 0.48621157;

$epm_max_members = 3;
    // Max number of members a team may have.

$epm_max_guests = 10;
    // Max number of guest entries a user may have.
    // Note: if you are restricting individual guests,
    // one guest may have more than one entry.

$epm_max_emails = 3;
    // Max number of email addresses a user may have.

$epm_expiration_times =
	[ 2*24*60*60, 7*24*60*60, 30*24*60*60];
    // [2, 7, 30] days; ticket expiration times
    // for 1st, 2nd, and >= 3rd tickets.

$epm_file_maxsize = 32*1024*1024;  // 32 megabytes.
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

$epm_max_display_lines = 2000;
    // Maximum number of lines displayed when a text
    // file is being displayed.   See look.php.

$epm_time_format = "%FT%T%Z";
    // Format for times, as per strftime.
    // Format as per date function would be
    //		"Y-m-d\Th:i:se"  [not tested]
    // strftime is being deprecated in php 8.1

$epm_name_re =
    '/^[A-Za-z][-_A-Za-z0-9]*[A-Za-z0-9]$/';
    // Regular expression matching only legal EPM
    // names, which have only letters, digits,
    // underline(_), and dash(-), begin with a letter,
    // and end with a letter or digit.

$epm_problem_name_re =
    '/^[A-Za-z][_A-Za-z0-9]*[A-Za-z0-9]$/';
    // Regular expression matching only legal EPM
    // problem names.  Same as $epm_name_re but does
    // not allow characters that cannot be in a JAVA
    // class name (e.g., `-').

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
    '#^\.\./\.\./\.\./(projects/(([^/]+)/([^/]+)))$#';
    // Regular expression matching target directory of
    // +parent+ link.  The matches are:
    //     [1]	projects/PROJECT/PROBLEM
    //     [2]	PROJECT/PROBLEM
    //     [3]	PROJECT
    //     [4]	PROBLEM

$epm_root_privs =
    ['owner','create-project','block'];
$epm_project_privs =
    ['owner','push-new','pull-new','re-pull',
     'view','show','copy-to','copy-from','block',
     'download', 'first-failed','publish-all',
     'publish-own', 'unpublish-all','unpublish-own'];
$epm_problem_privs =
    ['owner','re-push','copy-from','block','download',
     'first-failed'];
    // Privileges allowed in projects/+priv+, projects/
    // PROJECT/+priv+, or projects/PROJECT/PROBLEM/
    // +priv+ respectively.

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
    "java" => "jar",
    "py" => "pyc",
    "tex" => "pdf",
    "in" => "sout",
    "run" => "run",
    "txt" => "txt" ];

$display_file_type = [
    // To be listed as a problem file, and thence be
    // `displayable', a file must have extension EEE
    // such that $display_file_type['EEE'] == 'TTT'
    // exists.  If TTT is in $displayable_types, the
    // web page /page/look.php may be used to display
    // the file.  Otherwise TTT describes the file
    // type and only TTT is displayed.
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
    "jar" => "Compiled JAVA Executable",
    "pyc" => "Compiled PYTHON Executable",
    "run" => "utf8",
    "txt" => "utf8",
    "pdf" => "pdf",
    "disp" => "utf8",
    "in" => "utf8",
    "sin" => "utf8",
    "ftest" => "utf8",
    "cout" => "utf8",
    "mout" => "utf8",
    "sout" => "utf8",
    "dout" => "utf8",
    "fout" => "utf8",
    "rout" => "utf8",
    "tout" => "utf8",
    "cerr" => "utf8",
    "d1err" => "utf8",
    "d2err" => "utf8",
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

$displayable_types = ['utf8','pdf'];
    // Types displayable by look.php.

$push_file_map = [
    // If file YYYY.EEE is to be pushed then
    // $push_file_map['EEE'] must be set.  If it is
    // 'R' then the file should exist in the remote
    // (push destination) directory.  If it is 'L',
    // the file should exist in the remote directory
    // and be linked into the local directory.  If
    // it is 'S' then the file should exist in the
    // +sources+ subdirectory of the remote directory.
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
    "in" => ["00-.+-PPPP" => "L",
             ".+-PPPP" => "R"],
    "ftest" => ["00-.+-PPPP" => "L",
                ".+-PPPP" => "R"],
    "run" => ["sample-PPPP" => "L",
              "sample-.+-PPPP" =>"L",
              ".+-PPPP" =>"R"],
    "txt" => "L",
    "pdf" => [ "PPPP" => "L" ],
    "" => [ "generate-PPPP" => "L",
            "filter-PPPP" => "L",
            "monitor-PPPP" => "L" ]
];

$executable_ext = ['','jar','pyc'];
    // Extensions of executable files.

$linkable_ext = ['','c','cc','jar','java','pyc','py'];
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
// The lock is released on shutdown.  LOCK also releases
// any previous lock (there can be at most one lock).
//
$epm_lock = NULL;
function LOCK ( $dir, $type )
{
    global $epm_data, $epm_lock;

    if ( isset ( $epm_lock ) )
    {
        flock ( $epm_lock, LOCK_UN );
	@fclose ( $epm_lock );
    }
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

// Read/write a file atomically.  File names should be
// absolute.
//
// It is assumed that the file size will never be larger
// than $epm_file_maxsize.  Errors result in false being
// returned but no error messages.  This happens in
// particular if a file being read does not exist.
//
$epm_atomic = NULL;
function ATOMIC_READ ( $filename )
{
    global $epm_atomic, $epm_file_maxsize;
    $epm_atomic = @fopen ( $filename, 'r' );
    if ( $epm_atomic === false ) return false;
    flock ( $epm_atomic, LOCK_SH );
    $c = @fread ( $epm_atomic, $epm_file_maxsize );
    flock ( $epm_atomic, LOCK_UN );
    @fclose ( $epm_atomic );
    $epm_atomic = NULL;
    return $c;
}
function ATOMIC_WRITE ( $filename, $contents )
{
    global $epm_atomic, $epm_file_maxsize;
    $epm_atomic = @fopen ( $filename, 'w' );
    if ( $epm_atomic === false ) return false;
    flock ( $epm_atomic, LOCK_EX );
    $c = @fwrite ( $epm_atomic, $contents,
                   $epm_file_maxsize );
    flock ( $epm_atomic, LOCK_UN );
    @fclose ( $epm_atomic );
    $epm_atomic = NULL;
    return $c;
}

function LOCK_SHUTDOWN()
{
    global $epm_lock, $epm_atomic;

    if ( isset ( $epm_lock ) )
	@flock ( $epm_lock, LOCK_UN );
    if ( isset ( $epm_atomic ) )
	@flock ( $epm_atomic, LOCK_UN );
}
register_shutdown_function ( 'LOCK_SHUTDOWN' );


?>
