<?php

    // File:	look.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Sep 10 15:28:24 EDT 2020

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
    //
    //		+home+		    downloads/FILENAME
    //				    document/FILENAME
    //
    //		+temp+		    FILENAME
    //
    // where PROBLEM names a problem in accounts/$aid
    // and FILENAME satisfies $epm_filename_re and its
    // extension has $display_file_type utf8 or pdf,
    // except that for +temp+ FILENAME may have the
    // extension .tgz.
    // 
    // In document/FILENAME only .pdf files are allowed
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

    if ( ! in_array ( $disposition, ['show',
                                     'download'] ) )
	exit ( "UNACCEPTABLE HTTP POST: DISPOSITION" );

    $fext = pathinfo ( $filename, PATHINFO_EXTENSION );
    $fname = pathinfo ( $filename, PATHINFO_BASENAME );
    $fdir = pathinfo ( $filename, PATHINFO_DIRNAME );

    if ( ! preg_match ( $epm_filename_re, $fname ) )
	exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

    $ftype = '';
    if ( isset ( $display_file_type[$fext] ) )
       $ftype = $display_file_type[$fext];
    elseif ( $fext == 'tgz' )
       $ftype = 'tgz';
    if ( ! in_array ( $ftype, ['utf8','pdf','tgz'] ) )
	exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

    if ( $location == '+temp+' )
    {
        if ( $disposition != 'download' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " DISPOSITION" );
        if ( $fdir != '.' )
	    exit ( "UNACCEPTABLE HTTP POST: FILENAME" );
	    
        $d = $epm_data;
	$f = "accounts/$aid/+download-$uid+";
    }
    elseif ( $location == '+home+' )
    {
	if ( $ftype == 'tgz' )
	     exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

        if ( $fdir == 'documents' )
	{
	    if ( $ftype != 'pdf' )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " FILENAME" );
	    if ( $disposition != 'show' )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " DISPLOSITION" );
	}
        elseif ( $fdir != 'downloads' )
	    exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

        $d = $epm_home;
	$f = $filename;
    }
    else // $location = PROBLEM
    {
	$problem = $location;
        $f = "accounts/$aid/$problem";

        if ( ! preg_match ( $epm_name_re, $problem ) )
	    exit ( "UNACCEPTABLE HTTP POST: LOCATION" );
        if ( ! is_dir ( "$epm_data/$f" ) )
	     exit ( "UNACCEPTABLE HTTP POST: LOCATION" );

	if ( $ftype == 'tgz' )
	     exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

	if ( ! in_array ( $fdir, ['.','+work+'] ) )
	     exit ( "UNACCEPTABLE HTTP POST: FILENAME" );

	$f .= "/$filename";
	$d = $epm_data;
    }

    if ( ! is_readable ( "$d/$f" ) )
	 exit ( "UNACCEPTABLE HTTP POST: FILENAME" );
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

    $count = 0;
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
    foreach ( $lines as $line )
    {
	++ $count;
	$hline = htmlspecialchars ( $line );
	echo "<tr><td class='linenumber'>" . PHP_EOL .
	     "<pre>$count:</pre></td>" .
	     "<td><pre>  $hline</pre></td></tr>" .
	     PHP_EOL;
    }

?>
</table></div>
</div>
</body>
</html>
