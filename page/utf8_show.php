<?php

    // File:	utf8_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Feb 26 20:16:07 EST 2020

    // Show the UTF-8 file $_GET['filename'].
    // Filename is relative to problem directory.

?>

<html>
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
        font-size: 1.6vw;
    }
    td.linenumber {
	background-color:#b3e6ff;
	text-align:right;
    }
</style>
<body>

<div class='body'>

<?php

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_USER_ID'];
    $problem = $_SESSION['EPM_PROBLEM'];

    $probdir = "users/user$uid/$problem";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );
    if ( ! isset ( $_GET['filename'] ) )
	exit ( "ACCESS: illegal GET to utf8_show.php" );

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
	$printname = "<u>$fbase</u>:";
    elseif ( $fdir == '+work+' )
	$printname = "<u>[working directory]$fbase</u>:";
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
    echo "<h2 class='filename'>" .
         "<pre>$printname</pre></h2>";
    echo "<div class='filecontents'>" .
         "<table class='filecontents'>" . PHP_EOL;
    foreach ( $lines as $line )
    {
	++ $count;
	$hline = htmlspecialchars ( $line );
	echo "<tr><td class='linenumber'>" . PHP_EOL .
	     "<pre>$count:</pre></td>" .
	     "<td><pre>  $hline</pre></td></tr>" .
	     PHP_EOL;
    }
    echo "</table></div>" . PHP_EOL;

?>

</div>
</body>
</html>
