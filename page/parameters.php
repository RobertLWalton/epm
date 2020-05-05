<?php

// File:    parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue May  5 01:56:18 EDT 2020

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
$epm_data = dirname ( $epm_web ) . '/epm_658746537635';
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

$epm_debug = preg_match
    ( '/(login|user|problem|run|project)/', $epm_self );
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

$epm_parent_re =
    '#^\.\./\.\./\.\./(projects/[^/]+/[^/]+)$#';
    // Regular expression to target directory of
    // +parent+ link.  The first match is the
    // target directory relative to $epm_data.

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
    "tout" => "utf8",
    "cerr" => "utf8",
    "gerr" => "utf8",
    "g1err" => "utf8",
    "g2err" => "utf8",
    "err" => "utf8",
    "serr" => "utf8",
    "derr" => "utf8",
    "ferr" => "utf8",
    "rerr" => "utf8",
    "terr" => "utf8",
    "log" => "utf8",
    "fls" => "utf8",
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
    // it is a (sub)map RE => V, then each RE is a
    // regular expression such that if YYYY matches
    // RE then V is to be used: it will be 'R' or 'L'.
    // In the RE, `PPPP' is replaced by the problem
    // name before the RE is used.
    //
    // Note: merge of .optn files is handled separately.
    //
    "c" => "R",
    "cc" => "R",
    "java" => "R",
    "py" => "R",
    "tex" => "R",
    "in" => ["00-\\d+-PPPP" => "L",
             "\\d+-\\d+-PPPP" => "R"],
    "ftest" => ["00-\\d+-PPPP" => "L",
                "\\d+-\\d+-PPPP" => "R"],
    "run" => "R",
    "pdf" => [ "PPPP" => "L" ],
    "" => [ "generate_PPPP" => "R",
            "filter_PPPP" => "R" ]
];

?>
