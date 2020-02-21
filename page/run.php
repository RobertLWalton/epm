<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Feb 21 12:01:30 EST 2020

    // Starts and monitors problem runs.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( ! isset ( $_SERVER['EPM_PROBLEM'] ) )
    {
	header ( 'Location: /page/problem.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_USER_ID'];
    $email = $_SESSION['EPM_EMAIL'];
    $problem = $_SESSION['EPM_PROBLEM'];
    $probdir = "users/user$uid/$problem";

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
	if ( unset ( $local_file_cache[$f] ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );
	start_run ( $f, "$probdir/+run+", false,
	            $errors );
	$post_processed = true;
    }
    elseif ( isset ( $_POST['submit_run'] ) )
    {
	$f = $_POST['submit_run'];
	if ( unset ( $remote_file_cache[$f] ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );
	start_run ( $f, "$probdir/+run+", true,
	            $errors );
	$post_processed = true;
    }

    // Do this after execute or submit but before
    // update and reload.
    //
    if ( isset ( $_SESSION['EPM_RUNRESULT'] )
         &&
	 $_SESSION['EPM_RUNRESULT'] === true )
         &&
	 update_run_results() !== true )
    {
        finish_run ( $errors );
    }

    $runbase = NULL;
    $rundir = NULL;
    $runsubmit = NULL;
    if ( isset ( $_SESSION['EPM_RUNBASE'] ) )
    {
        $runbase = $_SESSION['EPM_RUNBASE'];
        $rundir = $_SESSION['EPM_RUNDIR'];
        $runsubmit = $_SESSION['EPM_RUNSUBMIT'];
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
	if ( ! isset ( $_SESSION['EPM_RUNRESULT'] )
	     ||
	     $_SESSION['EPM_RUNRESULT'] !== true )
	{
	    echo 'RELOAD';
	    exit;
	}
	else
	{
	    $f = "$rundir/$runbase.stat";
	    $contents = @file_get_contents
	        ( "$epm_data/$f" );
	    if ( $contents !== false )
	        echo $contents;
	    exit;
	}
    }

    if ( $method == 'POST' && ! $post_processed ))
        exit ( 'UNACCEPTABLE HTTP POST' );


    $debug = ( $epm_debug != ''
               &&
	       preg_match ( $epm_debug, $php_self ) );
	// True to enable javascript logging.

    $local_run_files = [];
    foreach ( $local_file_cache as $fname => $fdir )
    {
        if ( preg_match ( '/^.+\.run$/', $fname ) )
	    $local_run_files[] = $fname;
    }
    sort ( $local_run_files );

?>

<html>
<style>
    h5 {
        font-size: 14pt;
	margin: 0 0 0 0;
	display:inline;
    }
    pre, b, button, input, select, u {
        font-size: 12pt;
	display:inline;
    }
    pre.red {
        color: #BB0000;
    }
    div.left {
	background-color: #96F9F3;
	width: 47%;
	float: left;
    }
    iframe.right {
	width: 9in;
	float: right;
	height: 99%;
    }
    div.errors {
	background-color: #F5F81A;
    }
    div.warnings {
	background-color: #FFC0FF;
    }
    div.run_display {
	background-color: #C0FFC0;
    }
    div.indented {
	margin-left: 20px;
    }
</style>

<script>
    var iframe;

    function runshow ( runfile ) {
	if ( iframe != undefined ) iframe.remove();

	iframe = document.createElement("IFRAME");
	iframe.className = 'right';
	iframe.name = runfile;
	iframe.src =
	    '/page/utf8_show.php?filename=' +
	    runfile;
	document.body.appendChild ( iframe );
    }
</script>
<body>

<div class='left'>

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
    <form style='display:inline'
          action='user.php' method='GET'>
    <h5>User:</h5> <input type='submit' value='$email'
                    title='click to see user profile'>
    </form>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <form style='display:inline'
          action='problem.php' method='GET'>
    <button type='submit'>Go To Problem Page</button>
    </form>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <h5>Current Problem:</h5>&nbsp;
    <pre>$problem</pre></b>
EOT;

    if ( count ( $local_run_files ) > 0 )
    {
	echo "<form action='run.php' method='POST'>" .
	     "<h5>Current Problem Run Files</h5>" .
	     "<table style='display:block'>";
	foreach ( $local_run_files as $fname )
	{
	    echo "<tr>";
	    echo "<td style='text-align:right'>" .
	         "<button type='button' onclick=" .
		 "'runshow(\"$fname\")'>" .
		 $fname . "</button></td>";
	    echo "<td><button type='submit'" .
	         " name='execute_run' value='$fname'>" .
		 "Run</button></td>";
	    echo "</tr>";
	}
	echo "</table></form>";
    }

    if ( isset ( $runbase ) )
    {
	$r = $_SESSION['EPM_RUNRESULT'];
	if ( $r === true )
	    $h = 'Currently Executing Run';
	else
	    $h = 'Last Completed Run';
	$c = file_get_contents
	    ( "$epm_data/$rundir/$runbase.stat" );
	if ( $c === false )
	    $c = '(no status available)';
	echo <<<EOT
	<div class='run_display'>
	<h5>$h&nbsp;-&nbsp;$runbase.run:</h5>
	<div class='indented'>
	<pre id='status'>$c</pre>
EOT;
	if ( $r === false )
	    echo "<br><pre class='red'>Run Died" .
	         " Unexpectedly<pre>" . PHP_EOL;
	elseif ( $r !== true && $r != ['D',0] )
	    echo "<br><pre class='red'>Run Terminated" .
	         " Prematurely With Exit Code" .
		 " {$r[1]}<pre>" .
		 PHP_EOL;
	echo "</div>" . PHP_EOL;
    }
?>

</div>

<form action='run.php' method='POST' id='reload'>
<input type='hidden'
       name='reload' value='reload'>
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
        if ( response == 'RELOAD' )
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
	if ( isset ( $workbase )
	     &&
	     isset ( $_SESSION['EPM_CONTROL'] ) )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
