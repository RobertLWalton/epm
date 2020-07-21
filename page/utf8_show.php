<?php

    // File:	utf8_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Jul 21 09:40:28 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Show the UTF-8 file $_GET['filename'].
    // Filename is relative to problem directory.

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
</style>

<?php

    $epm_page_type = '+init+';

    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );
    elseif ( ! isset ( $_SESSION['EPM_AID'] ) )
	exit ( "ACCESS: illegal GET to utf8_show.php" );
    elseif ( ! isset ( $_GET['problem'] ) )
	exit ( "ACCESS: illegal GET to utf8_show.php" );
    elseif ( ! isset ( $_GET['filename'] ) )
	exit ( "ACCESS: illegal GET to utf8_show.php" );

    $uid = $_SESSION['EPM_AID'];
    $problem = $_GET['problem'];
    $probdir = "users/$uid/$problem";
    $filename = $_GET['filename'];
    $ext = pathinfo ( $filename, PATHINFO_EXTENSION );
    if ( ! isset ( $display_file_type[$ext] )
         ||
	 $display_file_type[$ext] != 'utf8' )
        exit ( "ACCESS: illegal GET to utf8_show.php" );

    $f = "$probdir/$filename";
    if ( ! is_readable ( "$epm_data/$f" ) )
        exit ( "ACCESS: illegal GET to utf8_show.php" );

    $fbase = pathinfo ( $filename, PATHINFO_BASENAME );
    $fdir = pathinfo ( $filename, PATHINFO_DIRNAME );
    if ( $fdir == '.' )
	$title = "$fbase";
    elseif ( $fdir == '+work+' )
	$title = "[working directory]$fbase";
    else
        exit ( "ACCESS: illegal GET to utf8_show.php" );

    $c = @file_get_contents ( "$epm_data/$f" );
    if ( $c === false )
	exit
	    ( "SYSTEM ERROR: cannot read readable $f" );

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
