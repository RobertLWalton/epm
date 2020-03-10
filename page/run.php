<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Mar  9 22:39:41 EDT 2020

    // Starts and monitors problem runs and displays
    // results.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( ! isset ( $_SESSION['EPM_PROBLEM'] ) )
    {
	header ( 'Location: /page/problem.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];
    $problem = $_SESSION['EPM_PROBLEM'];
    $probdir = "users/$uid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
    {
	// Some other session deleted the problem;
	// let problem.php deal with it.
	//
	header ( 'Location: /page/problem.php' );
	exit;
    }

    $lock_desc = NULL;
    function shutdown ()
    {
        global $lock_desc;
	if ( isset ( $lock_desc ) )
	    flock ( $lock_desc, LOCK_UN );
    }
    register_shutdown_function ( 'shutdown' );
    $lock_desc =
	fopen ( "$epm_data/$probdir/+lock+", "w" );
    flock ( $lock_desc, LOCK_EX );

    require "$epm_home/include/epm_make.php";
    load_file_caches();

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $post_processed = false;

    if ( isset ( $_POST['execute_run'] ) )
    {
	$f = $_POST['execute_run'];
	if ( ! isset ( $local_file_cache[$f] )
	     ||
	     ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );
	start_run ( "$probdir/+work+", $f,
	            "$probdir/+run+", false,
	            $errors );
	$post_processed = true;
    }
    elseif ( isset ( $_POST['submit_run'] ) )
    {
	$f = $_POST['submit_run'];
	if ( ! isset ( $remote_file_cache[$f] )
	     ||
	     ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );
	start_run ( "$probdir/+work+", $f,
	            "$probdir/+run+", true,
	            $errors );
	$post_processed = true;
    }

    // Do this after execute or submit but before
    // update and reload.
    //
    if ( isset ( $_SESSION['EPM_RUN']['RESULT'] )
         &&
	 $_SESSION['EPM_RUN']['RESULT'] === true
         &&
	 update_run_results() !== true )
    {
        finish_run ( $errors );
    }

    $runbase = NULL;
    $rundir = NULL;
    $runsubmit = NULL;
    $runresult = NULL;
    if ( isset ( $_SESSION['EPM_RUN']['BASE'] ) )
    {
        $runbase = $_SESSION['EPM_RUN']['BASE'];
        $rundir = $_SESSION['EPM_RUN']['DIR'];
        $runsubmit = $_SESSION['EPM_RUN']['SUBMIT'];
        $runresult = $_SESSION['EPM_RUN']['RESULT'];
    }

    if ( isset ( $_POST['reload'] )
	 &&
	 isset ( $runbase ) )
    {
        // Do nothing here.
	$post_processed = true;
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	if ( ! isset ( $runresult )
	     ||
	     $runresult !== true )
	{
	    echo 'RELOAD';
	    exit;
	}
	else
	{
	    usleep ( 500000 );
	    $f = "$rundir/$runbase.stat";
	    $contents = @file_get_contents
	        ( "$epm_data/$f" );
	    if ( $contents !== false )
	        echo $contents;
	    exit;
	}
    }

    if ( $method == 'POST' && ! $post_processed )
        exit ( 'UNACCEPTABLE HTTP POST' );


    $debug = ( $epm_debug != ''
               &&
	       preg_match ( $epm_debug, $php_self ) );
	// True to enable javascript logging.

    // Compute $map[$base][$ext] => [$fname,$fdir]
    // where $fname is $base.$ext, $ext is one $exts,
    // $fdir is the directory containing $fname,
    // relative to $epm_data, and $map[.] is sorted
    // in order of modification time of $base....,
    // most recent first.  To compute modification
    // time $ext's are taken in preference order
    // rout, rerr, run.
    //
    $exts = ['run','rerr','rout'];
        // In reverse preference order.
    function compute_run_map ( & $map, $cache, $rundir )
    {
        global $epm_data, $exts;

        $fmap = [];
	if ( isset ( $cache ) )
	    foreach ( $cache as $fname => $fdir )
	{
	    $ext = pathinfo
	        ( $fname, PATHINFO_EXTENSION );
	    if ( ! in_array ( $ext, $exts ) ) continue;

	    $f = "$epm_data/$fdir/$fname";
	    if ( ! is_readable ( $f ) ) continue;
	    $ftime = @filemtime ( $f );
	    if ( $ftime === false ) continue;
	    $fcontents = @file_get_contents ( $f );
	    if ( $fcontents === false ) continue;
	    if ( $fcontents == '' ) continue;

	    $base = pathinfo
	        ( $fname, PATHINFO_FILENAME );
	    $fmap[$base][$ext] =
	        [$fname,$ftime,$fcontents];
	}
	
	if ( isset ( $rundir ) )
	{
	    $files = @scandir ( "$epm_data/$rundir" );
	    if ( $files === false )
	        ERROR ( "cannot read $rundir" );
	    foreach ( $files as $fname )
	    {
		$ext = pathinfo
		    ( $fname, PATHINFO_EXTENSION );
		if ( ! in_array ( $ext, $exts ) ) continue;
		if ( $ext == 'run' ) continue;

		$f = "$epm_data/$rundir/$fname";
		if ( ! is_readable ( $f ) ) continue;
		$ftime = @filemtime ( $f );
		if ( $ftime === false ) continue;
		$fcontents = @file_get_contents ( $f );
		if ( $fcontents === false ) continue;
		if ( $fcontents == '' ) continue;

		$base = pathinfo
		    ( $fname, PATHINFO_FILENAME );
		$fmap[$base][$ext] =
		    [$fname,$ftime,$fcontents];
	    }
	}

	$map = [];
	foreach ( $fmap as $key => $e )
	{
	    foreach ( $exts as $ext ) // rout is last
	    {
		if ( ! isset ( $e[$ext] ) ) continue;
		$map[$key] = $e[$ext][1];
	    }
	}
	arsort ( $map, SORT_NUMERIC );
	foreach ( $map as $key => $value )
	    $map[$key] = $fmap[$key];
    }

?>

<html>
<head>
<style>
    .no-margin {
	margin: 0 0 0 0;
    }
    h5 {
        font-size: 14pt;
	margin: 0 0 0 0;
	display:inline;
    }
    pre, b, button, input, select, u {
	display:inline;
        font-size: 12pt;
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    pre.red {
        color: #BB0000;
    }
    div.errors {
	background-color: #F5F81A;
    }
    div.warnings {
	background-color: #FFC0FF;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 20px;
    }
    pre.problem {
        color: #CC00FF;
        font-size: 14pt;
    }
    div.run_list {
	background-color: #F2D9D9;
	clear: both;
    }
    div.run {
	background-color: #C0FFC0;
	clear: both;
    }
    div.file {
	background-color: #C0FFC0;
	clear: both;
    }
    div.indented {
	margin-left: 20px;
    }
</style>

<script>

    function TOGGLE ( s, c )
    {
	var SWITCH = document.getElementById ( s );
	var CONTENTS = document.getElementById ( c );
	if ( CONTENTS.hidden )
	{
	    SWITCH.innerHTML = "&uarr;";
	    CONTENTS.hidden = false;
	}
	else
	{
	    SWITCH.innerHTML = "&darr;";
	    CONTENTS.hidden = true;
	}
    }
</script>

</head>
<body>

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>" .  PHP_EOL;
	echo "<h5>Errors:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>" .  PHP_EOL;
	echo "<h5>Warnings:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }

    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <table>
    <td>
    <h5>User:</h5> <input type='submit' value='$email'
                    formaction='user.php'
                    title='click to see user profile'>
    </td>
    <td style='padding-left:50px'>
    <button type='submit'
            formaction='problem.php'>Go To Problem Page
    </button>
    </td>
    <td style='padding-left:50px'>
    <h5>Current Problem:</h5>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td>
    </table>
    </form>
    </div>
EOT;

    if ( isset ( $runbase ) )
    {
	if ( $runresult === true )
	    $h = 'Currently Executing Run';
	else
	    $h = 'Last Completed Run';
	$c = @file_get_contents
	    ( "$epm_data/$rundir/$runbase.stat" );
	if ( $c === false )
	    $c = '(no status available)';
	echo <<<EOT
	<div class='run'>
	<h5>$h&nbsp;-&nbsp;$runbase.run:</h5>
	<div class='indented'>
	<pre id='status'>$c</pre>
EOT;
	if ( $runresult === false )
	    echo "<br><pre class='red'>Run Died" .
	         " Unexpectedly<pre>" . PHP_EOL;
	elseif ( $runresult !== true
	         &&
		 $runresult != ['D',0] )
	    echo "<br><pre class='red'>Run Terminated" .
	         " Prematurely With Exit Code" .
		 " {$runresult[1]}<pre>" .
		 PHP_EOL;
	echo "</div>" . PHP_EOL;
    }

    $n = 0;
    $display_list = [];
    compute_run_map
        ( $local_map, $local_file_cache, $rundir );
    if ( $local_map != [] )
    {
    	echo <<<EOT
	<div class='run_list'>
    	<form action='run.php' method='POST'
	      class='no-margin'>
	<table>
EOT;
	foreach ( $local_map as $fbase => $e )
	{
	    ++ $n;

	    echo "<tr>";
	    echo "<td>";
	    if ( isset ( $e['run'] ) )
	    {
	        list ( $fname, $ftime, $fcontents ) =
		    $e['run'];
		$display_list[] =
		    ["run$n",$fname,$fcontents];
		echo <<<EOT
		     <button type='button'
		             id='s_run$n'
		             onclick='TOGGLE
		                 ("s_run$n","run$n")'
			>&darr;</button>
		     <pre>$fname</pre>
		     <button type='submit'
			     name='execute_run'
			     value='$fname'
			 >Run</button>
EOT;
	    }
	    echo "</td>";

	    echo "<td style='padding-left:40px'>";
	    if ( isset ( $e['rout'] ) )
	    {
	        list ( $fname, $ftime, $fcontents ) =
		    $e['rout'];
		$display_list[] =
		    ["rout$n",$fname,$fcontents];
		echo <<<EOT
		     <button type='button'
		             id='s_rout$n'
		             onclick='TOGGLE
		                 ("s_rout$n","rout$n")'
			>&darr;</button>
		     <pre>$fname</pre>
EOT;
	    }
	    echo "</td>";

	    echo "<td style='padding-left:40px'>";
	    if ( isset ( $e['rerr'] ) )
	    {
	        list ( $fname, $ftime, $fcontents ) =
		    $e['rerr'];
		$display_list[] =
		    ["rerr$n",$fname,$fcontents];
		echo <<<EOT
		     <button type='button'
		             id='s_rerr$n'
		             onclick='TOGGLE
		                 ("s_rerr$n","rerr$n")'
			>&darr;</button>
		     <pre>$fname</pre>
EOT;
	    }
	    echo "</td>";
	    echo "</tr>";
	}

        echo "</table></form></div>";
    }

    if ( count ( $display_list ) > 0 )
    {
	foreach ( $display_list as $e )
	{
	    list ( $id, $fname, $fcontents ) = $e;
	    $fcontents = htmlspecialchars
		( $fcontents );
	    if ( preg_match ( '/\.rout$/', $fname ) )
		$fcontents =
		    preg_replace
		      ( '/(?m)^(Score|' .
			'First-Failed-Test-Case|' .
			'Number-of-Warning-Messages):' .
			'.*$/',
			'</pre><pre class="red">$0' .
			"\n</pre><pre>",
			$fcontents );
	    echo <<<EOT
	    <div hidden id='$id' class='file'>
	    <h5>$fname:</h5><br>
	    <div class='indented'>
	    <pre>$fcontents</pre>
	    </div></div>
EOT;
	}
    }
    if ( isset ( $runbase ) && $runresult !== true )
        echo "<script>" .
	     "TOGGLE('s_rout1','rout1')</script>";
?>

<form action='run.php' method='POST' id='reload'>
<input type='hidden' name='reload' value='reload'>
</form>

<script>
    var LOG = function(message) {};
    <?php if ( $debug )
              echo "LOG = console.log;" . PHP_EOL;
    ?>

    var xhttp = new XMLHttpRequest();

    function FAIL ( message )
    {
	// Alert must be scheduled as separate task.
	//
	LOG ( "call to FAIL: " + message );
    <?php
	if ( $debug )
	    echo <<<'EOT'
		setTimeout ( function () {
		    alert ( message );
		    window.location.reload ( true );
		});
EOT;
	else
	    echo <<<'EOT'
		throw "CALL TO FAIL: " + message;
EOT;
    ?>
    }


    function ALERT ( message )
    {
	// Alert must be scheduled as separate task.
	//
	setTimeout
	    ( function () { alert ( message ); } );
    }

    var reload = document.getElementById("reload");

    function PROCESS_RESPONSE ( response )
    {
        if ( response.trim() == 'RELOAD' )
	{
	    reload.submit();
	    return;
	}
	let e = document.getElementById('status');
	e.innerText = response;
	REQUEST_UPDATE();
    }

    var REQUEST_IN_PROGRESS = false;
    function REQUEST_UPDATE()
    {
	xhttp.onreadystatechange = function() {
	    LOG ( 'xhttp state changed to state '
		  + this.readyState );
	    if ( this.readyState != XMLHttpRequest.DONE
		 ||
		 ! REQUEST_IN_PROGRESS )
		return;

	    if ( this.status != 200 )
		FAIL ( 'Bad response status ('
		       + this.status
		       + ') from server on'
		       + ' update request' );

	    REQUEST_IN_PROGRESS = false;
	    LOG ( 'xhttp response: '
		  + this.responseText );
	    PROCESS_RESPONSE ( this.responseText );
	};
	xhttp.open ( 'POST', "run.php", true );
	xhttp.setRequestHeader
	    ( "Content-Type",
	      "application/x-www-form-urlencoded" );
	REQUEST_IN_PROGRESS = true;
	LOG ( 'xhttp sent: update' );
	xhttp.send ( 'update=update' );
    }
    <?php
	if ( $runresult === true )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
