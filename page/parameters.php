<?php

// Parameters that you need to edit in R/index.php:
//
$epm_data = dirname ( $epm_web ) . '/epm_658746537635';
    // WARNING:
    //   This is only a test setting; reset this to
    //   D above (and UNLIKE the test setting, be
    //   sure D is not a descendant of R).
    //
    //   Include a NON-PUBLIC SITE-SPECIFIC 12 digit
    //   random number as part of the LAST COMPONENT
    //   of the name of D.

$epm_home = dirname ( $epm_web );
    // WARNING:
    //   This is only a test setting; reset this to H
    //   above (and UNLIKE the test setting, be sure
    //   sure H is not a descendant of R).

$epm_session_name = "EPM_859036254367";
    // Reset 12 digit number to NON-PUBLIC SITE-
    // SPECIFIC 12 digit random number.

$epm_debug = preg_match
    ( '/(login|user|problem|run)/', $epm_self );
    // True to turn debugging on; false for off.

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

$epm_supported_browsers = ['Chrome', 'Firefox'];
    // Add to this list after testing on indicated
    // browsers.

?>
