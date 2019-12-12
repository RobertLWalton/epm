<html>
<body>

<div style="background-color:#96F9F3;width:50%;float:left">

<?php

    // File:	ascii_show.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Dec 11 20:33:54 EST 2019

    // Show an ASCII file.

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

    include 'include/debug_info.php';

    $userid = $_SESSION['epm_userid'];
    $epm_data = $_SESSION['epm_data'];
    $problem = $_SESSION['epm_problem'];

    $problem_dir =
        "$epm_data/users/user$userid/$problem";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );
    if ( ! isset ( $_GET['filename' ) )
	exit
	    ( "ACCESS: illegal get to ascii_show.php" );

    $f = $_GET['filename'];
    $f = "$problem_dir/$f";
    if ( ! is_readable ( "$f" ) )
	exit
	    ( "ACCESS: illegal get to ascii_show.php" );

    $t = exec ( "file $f" );
    if ( ! preg_match ( '/ASCII/', $t ) )
	exit
	    ( "ACCESS: illegal get to ascii_show.php" );

    $c = file_get_contents ( $f );
    if ( $c === false )
	exit
	    ( "SYSTEM ERROR: cannot read readable $f" ); 

    $lines = explode ( "\n", $c );
    if ( array_slice ( $lines, -1, 1 ) == [""] )
	array_splice ( $lines, -1, 1 );
    $count = 0;
    echo "<div style='background-color:" .
	 "#d0fbd1;width:50%;" .
	 "float:right;overflow:scroll;" .
	 "height:100%'>\n";
    echo "<u style='" .
	 "background-color:#ffe6ee'>\n" .
	 "$b</u>:<br><table>\n";
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
