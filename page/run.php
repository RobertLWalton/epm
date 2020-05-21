<?php

    // File:	Wed May  6 15:34:41 EDT 2020
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed May 20 23:34:51 EDT 2020

    // Starts and monitors problem runs and displays
    // results.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( ! isset ( $_SESSION['EPM_PROBLEM'] ) )
    {
	header ( 'Location: /page/problem.php' );
	exit;
    }

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
	    echo "$ID\$RELOAD";
	    exit;
	}
	else
	{
	    usleep ( 500000 );
	    $f = "$rundir/$runbase.stat";
	    $contents = @file_get_contents
	        ( "$epm_data/$f" );
	    if ( $contents !== false )
	        echo "$ID\$$contents";
	    exit;
	}
    }

    if ( $epm_method == 'POST' && ! $post_processed )
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
    div.run_list {
	background-color: var(--bg-tan);
	clear: both;
    }
    div.run {
	background-color: var(--bg-green);
	clear: both;
    }
    div.file-name {
	background-color: var(--bg-blue);
    }
    div.file-contents {
	background-color: var(--bg-green);
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
    <div class='manage' id='manage'>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:</strong>
    <input type='submit' value='$email'
           formaction='user.php'
           title='Click to See User Profile'>
    </td>
    <td style='padding-left:50px'>
    <strong>Go To</strong>
    <button type='submit'
            formaction='problem.php'>Problem
    </button>
    <button type='submit'
            formaction='project.php'>Project
    </button>
    <button type='submit'
            formaction='option.php'>Option
    </button>
    <strong>Page</strong>
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
	<div class='run_list' id='run-list'>
    	<form action='run.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
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
<input type='hidden' name='id' id='reload-id'>
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

    var manage = document.getElementById("manage");
    var run_list = document.getElementById("run-list");
    var reload = document.getElementById("reload");
    var reload_id =
        document.getElementById("reload-id");
    var ID = '<?php echo $ID; ?>';

    function PROCESS_RESPONSE ( response )
    {
	item = response.trim().split ( '$' );
	ID = item[0];
        if ( item[1].trim() == 'RELOAD' )
	{
	    reload_id.value = ID;
	    reload.submit();
	    return;
	}
	let e = document.getElementById('status');
	e.innerText = item[1];
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
	manage.style.display = 'none';
	run_list.style.display = 'none';
	    // These keep buttons from being clicked
	    // while waiting for xhttp response and
	    // its updated ID.
	xhttp.send ( 'update=update&id=' + ID );
    }
    <?php
	if ( $runresult === true )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
