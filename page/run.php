<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Feb 24 05:19:42 EST 2020

    // Starts and monitors problem runs.

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
	if ( ! isset ( $local_file_cache[$f] ) )
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
	if ( ! isset ( $remote_file_cache[$f] ) )
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

    $local_run_files = [];
    $rout_files = [];
    foreach ( $local_file_cache as $fname => $fdir )
    {
        if ( preg_match ( '/^.+\.run$/', $fname ) )
	    $local_run_files[] = $fname;
        elseif ( preg_match ( '/^.+\.rout$/', $fname ) )
	    $rout_files[] = $fname;
    }
    sort ( $local_run_files );
    sort ( $rout_files );

?>

<html>
<style>
    h5 {
        font-size: 14pt;
	margin: 0 0 0 0;
	display:inline;
    }
    th {
        font-size: 14pt;
	text-align: center;
    }
    pre, b, button, input, select, u, td {
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
    div.file_left {
	float: left;
    }
    div.file_right {
	float: right;
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
	clear: both;
    }
    div.indented {
	margin-left: 20px;
    }
</style>

<script>
    var iframe;

    function SHOW ( runfile ) {
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

    $lc = count ( $local_run_files );
    $rc = count ( $rout_files );
    if ( $lc + $rc > 0 )
    {
        echo "<div>" . PHP_EOL;
	if ( $lc > 0 )
	{
	    echo "<div class='file_left'>" . PHP_EOL;
	    echo "<form action='run.php'" .
	         "      method='POST'>" .
	         "<table style='display:block'>";
	    echo "<tr><th colspan='2'>Run Files" .
	         "</th></tr>" . PHP_EOL;
	    foreach ( $local_run_files as $runf )
	    {
		echo "<tr>" .
		     "<td style='text-align:right'>" .
		     "<button type='button' onclick=" .
		     "'SHOW(\"$runf\")'>" .
		     $runf . "</button></td>";
		echo "<td><button type='submit'" .
		     " name='execute_run'" .
		     " value='$runf'>" .
		     "Run</button></td></tr>" . PHP_EOL;
	    }
	    echo "</table></form></div>" . PHP_EOL;
	}
	if ( $rc > 0 )
	{
	    echo "<div class='file_right'>" .
	         "<table style='display:block'>" .
		 PHP_EOL;
	    echo "<tr><th>Output Files" .
	         "</th></tr>" . PHP_EOL;
	    foreach ( $rout_files as $routf )
	    {
		echo "<tr>" .
		     "<td style='text-align:right'>" .
		     "<button type='button' onclick=" .
		     "'SHOW(\"$routf\")'>" .
		     $routf . "</button></td>";
	    }
	    echo "</table></div>" . PHP_EOL;
	}
        echo "</div>" . PHP_EOL;
    }

    if ( isset ( $runbase ) )
    {
	if ( $runresult === true )
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
?>

</div>

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
	elseif ( isset ( $_SESSION['EPM_RUN']['OUT'] ) )
	{
	    $f = $_SESSION['EPM_RUN']['OUT'];
	    echo "SHOW('$f');" . PHP_EOL;
	}
    ?>

</script>

</body>
</html>
