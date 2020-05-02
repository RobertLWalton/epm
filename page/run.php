<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat May  2 02:27:06 EDT 2020

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


    // Compute
    //
    //   $map[$base][EXT] => CONTENTS
    //
    // where EXT is one of 'loc', 'run', 'rout', or
    // 'rerr'.  If EXT != 'loc', CONTENTS is the
    // contents of the file $base.EXT.  For 'loc',
    // CONTENTS is instead either 'local' or 'remote'
    // and tells whether the $base.run file was found
    // in the $local_file_cache or $remote_file_cache.
    //
    // These entries are defined for a given $base iff
    // $base.run is found.  If one of the other files
    // $base.EXT does not exist, its CONTENTS is
    // set === false.
    //
    // If $base.run can be found both locally and
    // remotely, the local version is used.
    //
    // If $base.rout can be found both in the $rundir
    // and locally, the $rundir version is used.
    //
    // However $base.rerr can only be found in $rundir.
    //
    // $rundir may be NULL if it does not exist.
    //
    // A .rout or .rerr file whose mod-time is after
    // $base.run's mod-time is treated as non-existant.
    //
    // The $map is sorted by the TIME associated with
    // each $base, most recent first.  The TIME
    // associated is the most recent mod-time of any
    // of the $base.EXT files.
    //
    // This function begins by calling load_file_caches.
    //
    function compute_run_map ( & $map, $rundir )
    {
        global $epm_data, $local_file_cache,
	       $remote_file_cache;
	load_file_caches();

	// Build $fmap containing 'loc' and 'run' but
	// with CONTENTS for 'run' replaced by directory
	// containing $base.run.
	//
        $fmap = [];
	foreach ( ['remote','local'] as $loc )
	{
	    if ( $loc == 'remote' )
	        $cache = & $remote_file_cache;
	    else
	        $cache = & $local_file_cache;
	    foreach ( $cache as $fname => $fdir )
	    {
		$ext = pathinfo
		    ( $fname, PATHINFO_EXTENSION );
		if ( $ext == 'run' )
		{
		    $base = pathinfo
		        ( $fname, PATHINFO_FILENAME );
		    $fmap[$base]['run'] = $fdir;
		    $fmap[$base]['loc'] = $loc;
		}
	    }
	}

	// Complete each $fmap entry, and also build
	// $tmap[$base] => TIME, where TIME is the
	// latest mod-time of any file with given $base.
	//
	$tmap = [];
	foreach ( $fmap as $base => & $entry )
	{
	    $d = $entry['run'];
	    $f = "$d/$base.run";
	    $c = @file_get_contents ( "$epm_data/$f" );
	    if ( $c === false )
	        ERROR ( "cannot read $f" );
	    $runtime = @filemtime ( "$epm_data/$f" );
	    if ( $runtime === false )
	        ERROR ( "can read but not stat $f" );
	    $entry['run'] = $c;
	    $entry['rout'] = false;
	    $entry['rerr'] = false;
	    $time = $runtime;
	    if ( $rundir != NULL )
	    {
	        foreach ( ['rout','rerr'] as $rxxx )
		{
		    $f = "$rundir/$base.$rxxx";
		    $c = @file_get_contents
		        ( "$epm_data/$f" );
		    if ( $c === false ) continue;
		    $t = @filemtime
		        ( "$epm_data/$f" );
		    if ( $t === false )
			ERROR ( "can read but not" .
			        " stat $f" );

		    if ( $t < $runtime ) continue;

		    $entry[$rxxx] = $c;
		    if ( $time < $t ) $time = $t;
		}
	    }
	    if ( $entry['rout'] === false
	         &&
		 isset ( $local_file_cache
		             ["$base.rout"] ) )
	    {
		$d = $local_file_cache["$base.rout"];
		$f = "$d/$base.rout";
		$c = @file_get_contents
		    ( "$epm_data/$f" );
		if ( $c === false )
		    ERROR ( "cannot read $f" );
		$t = @filemtime ( "$epm_data/$f" );
		if ( $t === false )
		    ERROR ( "can read but not stat" .
		            " $f" );
		if ( $t >= $runtime )
		{
		    $entry['rout'] = $c;
		    if ( $time < $t ) $time = $t;
		}
	    }

	    $tmap[$base] = $time;
	}

	arsort ( $tmap, SORT_NUMERIC );
	$map = [];
	foreach ( $tmap as $base => $time )
	    $map[$base] = $fmap[$base];
    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    pre.red {
        color: #BB0000;
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
    div.file-name {
	background-color: #B3E6FF;
    }
    div.file-contents {
	background-color: #C0FFC0;
	margin-left: var(--indent);
    }
</style>

<script>

    function TOGGLE ( s, c )
    {
	var SWITCH = document.getElementById ( s );
	var CONTENTS = document.getElementById ( c );
	if ( CONTENTS.style.display == 'none' )
	{
	    SWITCH.innerHTML = "&uarr;";
	    CONTENTS.style.display = 'block';
	}
	else
	{
	    SWITCH.innerHTML = "&darr;";
	    CONTENTS.style.display = 'none';
	}
    }
</script>

</head>
<body>

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>" .  PHP_EOL;
	echo "<strong>Errors:</strong>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>" .  PHP_EOL;
	echo "<strong>Warnings:</strong>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }

    $run_help = HELP ( 'run-page' );
    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:</strong>
    <input type='submit' value='$email'
           formaction='user.php'
           title='click to see user profile'>
    </td>
    <td style='padding-left:50px'>
    <button type='submit'
            formaction='problem.php'>Go To Problem Page
    </button>
    </td>
    <td style='padding-left:50px'>
    <strong>Current Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td>
    <td style='text-align:right'>$run_help</td>
    </tr>
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
	<strong>$h&nbsp;-&nbsp;$runbase.run:</strong>
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
		 " {$runresult[1]};" .
		 " see .rerr file<pre>" .  PHP_EOL;
	echo "</div>" . PHP_EOL;
    }

    compute_run_map ( $local_map, $rundir );

    $n = 0;
    $display_list = [];
    $initially_display = [];
    if ( $local_map != [] )
    {
    	echo <<<EOT
	<div class='run_list'>
    	<form action='run.php' method='POST'>
	<table>
EOT;
	$td = [ 'run' => "<td>",
	        'rout' =>
		    "<td style='padding-left:40px'>",
	        'rerr' =>
		    "<td style='padding-left:40px'>" ];
	foreach ( $local_map as $base => $entry )
	{
	    ++ $n;

	    echo "<tr>";
	    foreach ( ['run','rout','rerr'] as $rxxx )
	    {
	        if ( $entry[$rxxx] === false ) continue;
		if (    $entry[$rxxx] == ''
		     && $rxxx != 'run' )
		    continue;

		$fname = "$base.$rxxx";
		echo $td[$rxxx];
		$display_list[] =
		    ["$rxxx$n",
		     "$base.$rxxx", $entry[$rxxx]];
		echo <<<EOT
		     <button type='button'
		             id='s_$rxxx$n'
		             onclick='TOGGLE
			       ("s_$rxxx$n","$rxxx$n")'
			>&darr;</button>
		     <pre>$fname</pre>
EOT;
		if ( $rxxx != 'run' )
		{
		    if ( $n == 1 )
		        $initially_display[] =
			    "$rxxx$n";
		    continue;
		}

		if ( $entry['loc'] == 'local' )
		     echo <<<EOT
		     <button type='submit'
			     name='execute_run'
			     value='$fname'
			 >Run</button>
EOT;
		else
		     echo <<<EOT
		     <button type='submit'
			     name='submit_run'
			     value='$fname'
			 >Submit</button>
EOT;
		echo "</td>";
	    }
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
	    <div style='display:none' id='$id'
	         class='file-name'>
	    <strong>$fname:</strong><br>
	    <div class='file-contents'>
	    <pre>$fcontents</pre>
	    </div></div>
EOT;
	}
    }
    if ( isset ( $runbase ) && $runresult !== true )
        foreach ( $initially_display as $rxxxN )
	    echo "<script>" .
		 "TOGGLE('s_$rxxxN','$rxxxN')" .
		 "</script>";
?>

<form action='run.php' method='POST' id='reload'>
<input type='hidden' name='reload' value='reload'>
</form>

<script>
    var LOG = function(message) {};
    <?php if ( $epm_debug )
              echo "LOG = console.log;" . PHP_EOL;
    ?>

    var xhttp = new XMLHttpRequest();

    function FAIL ( message )
    {
	// Alert must be scheduled as separate task.
	//
	LOG ( "call to FAIL: " + message );
    <?php
	if ( $epm_debug )
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
