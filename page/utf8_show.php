<?php

    // File:	utf8_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Sep  9 15:51:43 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // If $_GET['problem'] is set, show the UTF-8 file
    // $_GET['filename'], where there filename is
    // relative to problem directory.
    //
    // Otherwise show the UTF-8 file $_GET['filename']
    // that is in the $epm_home/pages/downloads
    // directory.
    //
    // Check that file exists and names are properly
    // formatted.

?>

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

    $epm_page_type = '+no-post+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    elseif ( ! isset ( $_GET['filename'] ) )
	exit ( "UNACCEPTABLE HTTP POST: UTF8" );

    $filename = $_GET['filename'];
    $ext = pathinfo ( $filename, PATHINFO_EXTENSION );
    $fname = pathinfo ( $filename, PATHINFO_BASENAME );
    $fdir = pathinfo ( $filename, PATHINFO_DIRNAME );

    if ( ! isset ( $display_file_type[$ext] )
         ||
	 $display_file_type[$ext] != 'utf8' )
	exit ( "UNACCEPTABLE HTTP POST: UTF8" );
    if ( ! preg_match ( $epm_filename_re, $filename ) )
	exit ( "UNACCEPTABLE HTTP POST: UTF8" );

    if ( isset ( $_GET['problem'] ) )
    {
	if ( $fdir == '.' )
	    $title = "$fname";
	elseif ( $fdir == '+work+' )
	    $title = "[working directory]$fname";
	else
	    exit ( "UNACCEPTABLE HTTP POST: UTF8" );

	$problem = $_GET['problem'];
	if ( ! preg_match ( $epm_name_re, $problem ) )
	    exit ( "UNACCEPTABLE HTTP POST: UTF8" );

	$f = "accounts/$aid/$problem/$filename";
	if ( ! is_readable ( "$epm_data/$f" ) )
	    exit ( "UNACCEPTABLE HTTP POST: UTF8" );

	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false )
	    exit ( "SYSTEM ERROR: cannot read" .
	           " readable $f" );
    }
    else
    {
        if ( $fdir != '.' )
	    exit ( "UNACCEPTABLE HTTP POST: UTF8" );
	$title = "$fname";

	$f = "page/downloads/$filename";
	if ( ! is_readable ( "$epm_home/$f" ) )
	    exit ( "UNACCEPTABLE HTTP POST: UTF8" );

	$c = @file_get_contents ( "$epm_home/$f" );
	if ( $c === false )
	    exit ( "SYSTEM ERROR: cannot read" .
	           " readable $f" );
    }

    $lines = explode ( "\n", $c );
    if ( array_slice ( $lines, -1, 1 ) == [""] )
	array_splice ( $lines, -1, 1 );
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
