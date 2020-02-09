<?php

    // File:	utf8_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Feb  8 20:33:33 EST 2020

    // Show the UTF-8 file $_GET['filename'].
    // File may be in current problem directory
    // or a temporary in its +work+ subdirectory.

?>

<html>
<body>

<div style="background-color:#ffe6ee">

<?php

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_USER_ID'];
    $problem = $_SESSION['EPM_PROBLEM'];

    $problem_dir = "users/user$uid/$problem";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );
    if ( ! isset ( $_GET['filename'] ) )
	exit
	    ( "ACCESS: illegal GET to utf8_show.php" );

    $filename = $_GET['filename'];
    $printname = "<u>$filename</u>:";
    $f = "$problem_dir/$filename";
    $g = "$problem_dir/+work+/$filename";
    if ( ! is_readable ( "$epm_data/$f" ) )
    {
        if ( ! is_readable ( "$epm_data/$g" ) )
	    exit ( "ACCESS: illegal GET to" .
	           " utf8_show.php" );
	$f = $g;
	$printname .=
	    '&nbsp;&nbsp;&nbsp;&nbsp;(temporary)';
    }

    $t = exec ( "file $epm_data/$f" );
    if ( ! preg_match ( '/(ASCII|UTF-8)/', $t ) )
	exit
	    ( "ACCESS: illegal GET to utf8_show.php" );

    $c = file_get_contents ( "$epm_data/$f" );
    if ( $c === false )
	exit
	    ( "SYSTEM ERROR: cannot read readable $f" ); 

    $lines = explode ( "\n", $c );
    if ( array_slice ( $lines, -1, 1 ) == [""] )
	array_splice ( $lines, -1, 1 );
    $count = 0;
    echo "<h2 style='margin-bottom:0'>" .
         "<pre>$printname</pre></h2>" . PHP_EOL;
    echo "<div style='background-color:#d0fbd1;'>" .
         "<table style='font-size:12pt'>" . PHP_EOL;
    foreach ( $lines as $line )
    {
	++ $count;
	$hline = htmlspecialchars ( $line );
	echo "<tr><td style='" .
	     "background-color:#b3e6ff;" .
	     "text-align:right;'>" . PHP_EOL .
	     "<pre>$count:</pre></td>" .
	     "<td><pre>  $hline</pre></td></tr>" .
	     PHP_EOL;
    }
    echo "</table></div>" . PHP_EOL;

?>

</div>
</body>
</html>
