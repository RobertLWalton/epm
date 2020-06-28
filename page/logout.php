<?php

    // File:	logout.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Jun 28 04:08:58 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // Return true if process with $pid is still
    // running, and false otherwise.
    //
    function is_running ( $pid )
    {
	exec ( "kill -s 0 $pid 2>/dev/null",
	       $kill_output, $kill_status );
	//
	// Sending signal 0 does not actually send a
	// signal but returns status 0 if the process is
	// still running and status non-0 otherwise.
	// Note that the posix_kill function is not
	// available in vanilla PHP.

	return ( $kill_status == 0 );
    }

    // For each problem background process still
    // running, put a warning message in $warnings and
    // its PID in $pids.
    //
    $warnings = [];
    $pids = [];
    foreach ( ['EPM_RUN','EPM_WORK'] as $type )
    {
	if ( ! isset ( $_SESSION[$type] ) )
	    continue;
	foreach ( $_SESSION[$type]
		  as $problem => $value )
	{
	    if ( ! isset ( $value['RESULT'] ) )
		continue;
	    if ( $value['RESULT'] !== true )
		continue;
	    if ( ! isset ( $value['DIR'] ) )
		continue;
	    if ( ! isset ( $value['BASE'] ) )
		continue;
	    $dir = $value['DIR'];
	    $base = $value['BASE'];
	    $c = @file_get_contents
		( "$epm_data/$dir/$base.shout" );
	    if ( $c === false ) continue;
	    if ( ! preg_match ( '/^(\d+) PID\n/',
				$c, $matches ) )
		continue;
	    $pid = $matches[1];
	    if ( ! is_running ( $pid ) )
	        continue;
	    $warnings[] = "$base.sh is still running";
	    $pids[] = $pid;
	}
    }

    if ( isset ( $_GET['answer'] )
	 &&
	 $_GET['answer'] == 'YES' )
    {
        foreach ( $pids as $pid )
	    exec ( "kill -s KILL -$pid" .
	           " >/dev/null 2>&1" );
	if ( count ( $pids ) > 0 ) usleep ( 1000000 );
	session_unset();
	header ( "Location: $epm_root/page/login.php" );
	exit;
    }

?>
<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>
<style>
button.marked {
    background-color: yellow;
}
</style>
</head>
<body>

<?php
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<strong>Warnings:</strong>";
	echo "<div class='indented'>";
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }
?>

<div class='manage'>
<table style='width:100%'>
<td style='text-align:left'>
Do you really want to log out?
<button class='marked' type='button' onclick='NO()'>
    NO</button>
<button class='marked' type='button' onclick='YES()'>
    YES</button>
</td>
<td style='text-align:right'>
<button type='button'
	onclick='HELP("logout-page")'>
    ?</button>
</td>
</tr>
</table>

<?php
    $r = '<script>let problems=[';
    $s = '';
    foreach ( $_SESSION['EPM_ID_GEN']
              as $key => $value )
    {
        if ( preg_match ( $epm_name_re, $key ) )
	{
	    $r .= "$s'$key'";
	    $s = ',';
	}
    }
    $r .= '];</script>';
    echo $r;

    echo <<<EOT
    <script>
    let ID = '$ID';
    function NO()
    {
	location.assign
	    ( '$epm_root/page/project.php' +
	      '?id=' + ID );
    }
    function YES()
    {
	window.open ( '', '+help+', '' ).close();
	window.open ( '', '+view+', '' ).close();
	for ( i = 0; i < problems.length; ++ i )
	    window.open ( '', problems[i], '' ).close();
	location.assign
	    ( '$epm_root/page/logout.php?' +
	      'answer=YES&id=' + ID );
    }
    </script>
EOT
?>
</body>
</html>
