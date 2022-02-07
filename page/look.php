<?php

    // File:	look.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Feb  7 03:11:26 EST 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Display or download a file.

    // Parameters:
    //
    //	   $_GET['disposition']
    //
    //		show		Display the file.
    //		download	Download the file.
    //
    //	   $_GET['location']	    $_GET['filename']
    //
    //		PROBLEM		    FILENAME
    //				    +work+/FILENAME
    //				    +run+/FILENAME
    //				    +parent+/FILENAME
    //
    //		+home+		    downloads/FILENAME
    //				    documents/FILENAME
    //
    //		+temp+		    FILENAME
    //
    //     $_GET['highlight']
    //
    //          If present, a regular expression such
    //          that matching lines are highlighted
    //          in red.  E.g., '/^Score:/'.
    //
    // where PROBLEM names a problem in accounts/$aid
    // and FILENAME satisfies $epm_filename_re and its
    // extension has $display_file_type utf8 or pdf,
    // except that for +temp+ FILENAME may have the
    // extension .tgz.
    // 
    // In documents/FILENAME only .pdf files are allowed
    // and disposition must be 'show'.
    //
    // For +temp+, the the file itself is in
    //
    //		accounts/$aid/+download-$uid+
    //
    // the disposition must be 'download'; and the file
    // itself is unlinked after being downloaded.
    //		
    // The file to be displayed or downloaded must
    // be readable.
    //
    // If a non-PDF file to be displayed has more than
    // $epm_max_display_lines, lines in the middle will
    // be omitted, so only $epm_max_display_lines are
    // displayed.

    $epm_page_type = '+download+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";
    // exit;
    // Must exit after require except when showing
    // UTF8 files.
            
    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    elseif ( ! isset ( $_GET['disposition'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );
    elseif ( ! isset ( $_GET['location'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );
    elseif ( ! isset ( $_GET['filename'] ) )
	exit ( "UNACCEPTABLE HTTP POST" );

    $disposition = $_GET['disposition'];
    $location   = $_GET['location'];
    $filename   = $_GET['filename'];
    if ( isset ( $_GET['highlight'] ) )
	$highlight = $_GET['highlight'];
    else
	$highlight = NULL;

    if ( ! in_array ( $disposition, ['show',
                                     'download'] ) )
	exit ( "UNACCEPTABLE HTTP POST: DISPOSITION" );

    $fext = pathinfo ( $filename, PATHINFO_EXTENSION );
    $fname = pathinfo ( $filename, PATHINFO_BASENAME );
    $fdir = pathinfo ( $filename, PATHINFO_DIRNAME );

    if ( ! preg_match ( $epm_filename_re, $fname ) )
	exit ( "UNACCEPTABLE HTTP POST:" .
	       " FILENAME $fname" );

    $ftype = '';
    if ( isset ( $display_file_type[$fext] ) )
       $ftype = $display_file_type[$fext];
    elseif ( $fext == 'tgz' )
       $ftype = 'tgz';
    if ( ! in_array ( $ftype, ['utf8','pdf','tgz'] ) )
	exit ( "UNACCEPTABLE HTTP POST: FTYPE $ftype" );

    if ( $location == '+temp+' )
    {
        if ( $disposition != 'download' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " DISPOSITION != download" );
        if ( $fdir != '.' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " FDIR != ." );
	    
        $d = $epm_data;
	$f = "accounts/$aid/+download-$uid+";
    }
    elseif ( $location == '+home+' )
    {
	if ( $ftype == 'tgz' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " FTYPE == tgz" );

        if ( $fdir == 'documents' )
	{
	    if ( $ftype != 'pdf' )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " FTYPE != pdf" );
	    if ( $disposition != 'show' )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " DISPLOSITION != show" );
	}
        elseif ( $fdir != 'downloads' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " FDIR != downloads" );

        $d = $epm_home;
	$f = $filename;
    }
    else // $location = PROBLEM
    {
	$problem = $location;
        $f = "accounts/$aid/$problem";

        if ( ! preg_match ( $epm_name_re, $problem ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " PROBLEM $problem" );
        if ( ! is_dir ( "$epm_data/$f" ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " $f NOT DIR" );

	if ( $ftype == 'tgz' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " FTYPE == tgz" );

	if ( ! in_array
	           ( $fdir, ['.','+work+','+run+',
		             '+parent+'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " FDIR NOT ., +work+, +run+," .
		   " or +parent+" );

	$f .= "/$filename";
	$d = $epm_data;
    }

    if ( ! is_readable ( "$d/$f" ) )
	 exit ( "UNACCEPTABLE HTTP POST:" .
	        " UNREADABLE $f" );
    $fsize = @filesize ( "$d/$f" );
    if ( $fsize === false )
        ERROR ( "cannot stat readable $f" );

    if ( $disposition == 'download' )
    {
        if ( $ftype == 'utf8' )
	    $content_type = 'text/plain';
        elseif ( $ftype == 'pdf' )
	    $content_type = 'application/pdf';
	else
	    $content_type = 'application/x-gzip';

	header ( "Content-type: $content_type" );
	header ( "Content-Disposition: attachment;" .
		 " filename=$fname" );
	header ( "Content-Transfer-Encoding: binary" );
	header ( "Content-Length: $fsize" );
	$r = @readfile ( "$d/$f" );
	if ( $r === false )
	    ERROR ( "cannot read readable $f" );

	if ( $location == '+temp+' )
	{
	    $r = @unlink ( "$d/$f" );
	    if ( $r === false )
		ERROR ( "cannot unlink $f" );
	}
	exit;
    }
    elseif ( $ftype == 'pdf' )
    {
	header ( 'Content-Type: application/pdf' );
	header ( 'Content-Disposition: inline;' .
		 'filename=' . $fname );
	header ( 'Content-Transfer-Encoding: binary' );
	header ( "Content-Length: $fsize" );
	$r = @readfile ( "$d/$f" );
	if ( $r === false )
	    ERROR ( "cannot read readable $f" );
	exit;
    }
     
    // File is UTF8 to be shown.

    if ( $fdir == '+work+' )
	$title = "[working directory]$fname";
    elseif ( $fdir == '+run+' )
	$title = "[running directory]$fname";
    elseif ( $fdir == '+parent+' )
	$title = "[parent directory]$fname";
    else
	$title = "$fname";

    $c = @file_get_contents ( "$d/$f" );
    if ( $c === false )
        ERROR ( "cannot read readable $f" );
    $lines = explode ( "\n", $c );
    if ( array_slice ( $lines, -1, 1 ) == [''] )
	array_splice ( $lines, -1, 1 );
	// Handles missing EOL on last line.

?>

<!-- The rest of this file displays UTF8 files
     given $title and $lines.
  -->

<html>
<head>
<style>

    div.body {
        background-color:#FFE6EE;
    }
    h2.filename {
        margin-bottom: 0;
    }
    div.filecontents {
	background-color: #D0FBD1;
    }
    table.filecontents {
        font-size: 16px;
    }
    td.linenumber {
	background-color:#b3e6ff;
	text-align:right;
    }
    pre {
        margin: 0px;
    }
</style>

<?php

    echo <<<EOT
    <title>$title</title>
    </head>

    <body>

    <div class='body'>
    <h2 class='filename'>
    <pre><u>$title:</u></pre></h2>
    <div class='filecontents'>
    <table class='filecontents'>
EOT;

    $count = 0;
    $last = count ( $lines );
        // Last of beginning group of printed lines.
    $first = $last + 1;
        // First of ending group of lines.
    if ( $last > $epm_max_display_lines )
    {
        $last = intdiv ( $epm_max_display_lines, 2 );
	$first = $first
	       - ( $epm_max_display_lines - $last );
    }
    foreach ( $lines as $line )
    {
	++ $count;
	if ( $count == $last + 1 )
	{
	    $omitted = $first - $last - 1;
	    echo "<tr><td class='linenumber'></td>" .
	         "<td style='color:red'><strong>" .
		 "..... $omitted lines omitted ....." .
		 "</strong></td></tr>" . PHP_EOL;
	    continue;
	}
	elseif ( $count > $last && $count < $first )
	    continue;

	$hline = htmlspecialchars ( $line );
	echo "<tr><td class='linenumber'>" . PHP_EOL .
	     "<pre>$count:</pre></td>";
	if ( isset ( $highlight )
	     &&
	     preg_match ( $highlight, $line ) )
	    echo "<td style='color:red'>";
	else
	    echo "<td>";
	echo "<pre>  $hline</pre></td></tr>" .
	     PHP_EOL;
    }

?>
</table></div>
</div>
</body>
</html>
