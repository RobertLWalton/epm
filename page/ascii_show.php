<?php

    // File:	ascii_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Dec 12 03:55:07 EST 2019

    // Show the ASCII file $_GET['filename'].

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
    $epm_root = $_SESSION['epm_root'];
    $problem = $_SESSION['epm_problem'];

    // require "$epm_root/include/debug_info.php";

    $problem_dir =
        "$epm_data/users/user$userid/$problem";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );
    if ( ! isset ( $_GET['filename'] ) )
	exit
	    ( "ACCESS: illegal GET to ascii_show.php" );

    $filename = $_GET['filename'];
    $f = "$problem_dir/$filename";
    if ( ! is_readable ( "$f" ) )
	exit
	    ( "ACCESS: illegal GET to ascii_show.php" );

    $t = exec ( "file $f" );
    if ( ! preg_match ( '/ASCII/', $t ) )
	exit
	    ( "ACCESS: illegal GET to ascii_show.php" );

    $c = file_get_contents ( $f );
    if ( $c === false )
	exit
	    ( "SYSTEM ERROR: cannot read readable $f" ); 

    $lines = explode ( "\n", $c );
    if ( array_slice ( $lines, -1, 1 ) == [""] )
	array_splice ( $lines, -1, 1 );
    $count = 0;
    echo "<u>$filename</u>:<br>\n";
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
