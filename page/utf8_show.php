<?php

    // File:	utf8_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Dec 27 03:32:31 EST 2019

    // Show the UTF-8 file $_GET['filename'].
    // File may be in current problem directory
    // or a temporary in its +work+ subdirectory.

?>

<html>
<body>

<div style="background-color:#ffe6ee">

<?php

    session_start();
    clearstatcache();
    umask ( 07 );

    if ( ! isset ( $_SESSION['epm_userid'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }
    if (    $_SESSION['epm_ipaddr']
	 != $_SERVER['REMOTE_ADDR'] )
        exit ( 'UNACCEPTABLE IPADDR CHANGE' );

    if ( ! isset ( $_SESSION['epm_problem'] ) )
    {
	header ( "Location: problem.php" );
	exit;
    }

    $userid = $_SESSION['epm_userid'];
    $epm_data = $_SESSION['epm_data'];
    $epm_home = $_SESSION['epm_home'];
    $problem = $_SESSION['epm_problem'];

    // require "$epm_home/include/debug_info.php";

    $problem_dir = "users/user$userid/$problem";

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
    echo "<h3 style='margin-bottom:0em'>$printname</h3><br>\n";
    echo "<div style='background-color:#d0fbd1;'>" .
         "<table>\n";
    foreach ( $lines as $line )
    {
	++ $count;
	echo "<tr><td style='" .
	     "background-color:#b3e6ff;" .
	     "text-align:right;'>\n" .
	     "<pre>$count:</pre></td>" .
	     "<td><pre>  $line</pre></td></tr>\n";
    }
    echo "</table></div>\n";

?>

</div>
</body>
</html>
