<?php

// File:    epm_params.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sun Dec 29 18:35:24 EST 2019

// Per web site EPM parameters.  An edited version of
// this file located in the $_SERVER['DOCUMENT_ROOT']
// directory is `required' at the beginning of all EPM
// pages.

// To set up a epm instance you need the following
// directories:
//
//     R	$_SERVER['DOCUMENT_ROOT'].  Directory
//		in which you place an edited copy of
//		this file.
//     H	The `epm' home directory containing
//           	`page', `template', etc subdirectories.
//           	Must NOT be a subdirectory of R.
//     D	Directory that will contain data.  This
//		must NOT be a subdirectory of R.  Also,
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
// your current account and the web server.
//
// We assume only your account, and not the web server,
// will have write permissions on R and H.
//
// Then to install, after populating H and creating
// R and D:
//
//	chgrp WEB-SERVERS-GROUP \
//	      R `find H` `find D`
//	chmod g+s \
//	      R `find H -type d` \
//              `find D -type d`
//	chmod g-w R `find H`
//
//	cd R
//	ln H/page .
//	ln H/page/index.html .
//	cp -p H/include/epm_params.php .
//	chmod u+w epm_params.php
//	<edit parameters in R/epm_params.php>

// Parameters that you need to edit in R/epm_param.php:
//
$epm_data = dirname ( $_SERVER['DOCUMENT_ROOT'] )
          . '/epm_658746537635';
    // WARNING: this is only a test setting;
    //          reset this to D above.
    //          Include 12 digit random number.

$_SESSION['epm_home'] =
	dirname ( $_SERVER['DOCUMENT_ROOT'] );
    // WARNING: this is only a test setting;
    //          reset this to E above.

session_name ( "EPM_859036254367" );
    // Reset 12 digit number to web site specific
    // 12 digit random number.

$epm_max_emails = 3;
    // Max number of email addresses a user may have.

$confirmation_interval = 2592000;
    // 30 * 24 * 60 * 60 = 30 days
    // Time in seconds between requests for a user to
    // confirm.

$epm_upload_maxsize = 262144;  // 256 kilobytes.
    // Maximum size of uploaded file.

$upload_target_ext = [
    // If file YYYY.EEE is uploadable, then
    // $upload_target.ext['EEE'] = 'FFF' must be
    // defined and after YYYY.EEE is uploaded, the
    // file YYYY.FFF must be makeable (there must
    // be a template YYYY.EEE:YYYY.FFF:....tmpl.
    //
    "c" => "",
    "cc" => "",
    "java" => "class",
    "py" => "pyc",
    "tex" => "pdf",
    "in" => "sout",
    "run" => "run" ];

$display_file_ext = [
    // To be listed as a problem file, and thence be
    // `displayable' (even if only the file type is
    // displayed, as for .class), a file must have
    // extension EEE such that $display_file_ext['EEE']
    // exists (and is the displayable file type).
    //
    "c" => "utf8",
    "cc" => "utf8",
    "java" => "utf8",
    "py" => "utf8",
    "tex" => "utf8",
    "" => "Compiled Binary Executable",
    "class" => "Compiled JAVA Executable",
    "pyc" => "Compiled PYTHON Executable",
    "pdf" => "pdf",
    "in" => "utf8",
    "sin" => "utf8",
    "test" => "utf8",
    "ftest" => "utf8",
    "run" => "utf8",
    "out" => "utf8",
    "sout" => "utf8",
    "fout" => "utf8",
    "rout" => "utf8",
    "err" => "utf8",
    "gerr" => "utf8",
    "serr" => "utf8",
    "ferr" => "utf8",
    "rerr" => "utf8",
    "log" => "utf8",
    "fls" => "utf8",
    "score" => "utf8"
    ];
}

$display_file_map = [
    // Do be displayable as something other than a file
    // type, a displayable file must have a type TTT
    // such that $display_file_map['TTT'] == 'PAGE.php'
    // exists and the file can be displayed by using
    // the URL "page/PAGE.php?FILENAME", provided the
    // named file is in the problem directory or its
    // +work+ subdirectory.
    //
    "utf8" => "utf8_show.php",
    "pdf"  => "pdf_show.php" ];
?>
