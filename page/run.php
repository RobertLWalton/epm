<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jun 10 13:55:14 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Starts and monitors problem runs and displays
    // results.

    $epm_page_type = '+problem+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_REQUEST['problem'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to run.php" );
    elseif ( ! isset ( $_SESSION['EPM_UID'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to run.php" );
    elseif ( ! isset ( $_SESSION['EPM_EMAIL'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to run.php" );

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];
    $problem = $_REQUEST['problem'];
    $probdir = "users/$uid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
        exit ( "problem $problem no longer exists" );

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
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_run ( "$probdir/+work+", $f,
	            $lock, "$probdir/+run+", false,
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
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_run ( "$probdir/+work+", $f,
	            $lock, "$probdir/+run+", true,
	            $errors );
	$post_processed = true;
    }

    // Do this after execute or submit but before
    // update and reload.
    //
    if ( isset ( $run['RESULT'] )
         &&
	 $run['RESULT'] === true
         &&
	 update_run_results() !== true )
    {
        finish_run ( $errors );
    }

    $runbase = NULL;
    $rundir = NULL;
    $runsubmit = NULL;
    $runresult = NULL;
    if ( isset ( $run['BASE'] ) )
    {
        $runbase = $run['BASE'];
        $rundir = $run['DIR'];
        $runsubmit = $run['SUBMIT'];
        $runresult = $run['RESULT'];
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
	elseif ( $_POST['update'] == 'abort' )
	{
	    abort_dir ( $run['DIR'] );
	    usleep ( 100000 ); // 0.1 second
	    if ( update_run_results ( 0 ) !== true )
	    {
		echo "$ID\$RELOAD";
		exit;
	    }
	}

	usleep ( 500000 );
	$f = "$rundir/$runbase.stat";
	$contents = @file_get_contents
	    ( "$epm_data/$f" );
	if ( $contents !== false )
	    echo "$ID\$$contents";
	else
	    echo "$ID\$(no status available)";
	exit;
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
    div.abort-switch {
        display: inline-block;
	width: calc(10*var(--large-font-size));
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

    echo <<<EOT
    <div class='manage' id='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:&nbsp;$email</strong>
    </td>
    <td style='padding-left:50px'>
    <strong>Go To</strong>
    <form method='GET'>
    <input type='hidden'
           name='problem' value='$problem'>
    <input type='hidden' id='id1'
           name='id' value='$ID'>
    <button type='submit'
            formaction='problem.php'>Problem
    </button>
    <button type='submit'
            formaction='option.php'>Option
    </button>
    </form>
    <strong>Page</strong>
    </td>
    <td style='padding-left:50px'>
    <strong>Current Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td>
    <td style='text-align:right'>
    <button type='button'
            onclick='HELP("run-page")'>
	?</button>
    </td>
    </tr>
    </table>
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
	<pre>    </pre>
	<div id='abort-switch' class='abort-switch'
	     style='visibility:hidden'>
	<div id='abort-checkbox' class='checkbox'
	     onclick='ABORT_CLICK()'></div>
	<strong id='abort-label' style='color:red'>
	     Abort</strong>
	</div>
	<div class='indented'>
	<pre id='status'>$c</pre>
EOT;
	if ( $runresult === false )
	    echo "<br><pre class='red'>Run Died" .
	         " Unexpectedly<pre>" . PHP_EOL;
	elseif ( $runresult !== true
	         &&
		 $runresult != ['D',0] )
	{
	    $rerrsize = filesize
	        ( "$epm_data/$rundir/$runbase.rerr" );
	    $rerrexists = ( $rerrsize !== false
	                    &&
			    $rerrsize > 0 );
	    $r = ( $rerrexists ?
	           '; see .rerr file' : '' );
	    $m = get_exit_message ( $runresult[1] );
	    echo "<br><pre class='red'>Run Terminated" .
	         " Prematurely With Exit Code" .
		 " {$runresult[1]}; $m$r</pre>" .
		 PHP_EOL;
	}
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
	<input type='hidden' id='id2'
	       name='id' value='$ID'>
	<input type='hidden'
	       name='problem' value='$problem'>
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

    echo <<<EOT
    <form action='run.php' method='POST' id='reload'>
    <input type='hidden' id='id3'
           name='id' value='$ID'>
    <input type='hidden'
           name='problem' value='$problem'>
    <input type='hidden' name='reload' value='reload'>
    </form>
EOT;

?>

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

    let manage = document.getElementById("manage");
    let run_list = document.getElementById("run-list");
    let reload = document.getElementById("reload");
    let reload_id =
        document.getElementById("reload-id");
    let problem = '<?php echo $problem; ?>';
    let abort_switch =
        document.getElementById("abort-switch");
    let abort_checkbox =
        document.getElementById("abort-checkbox");
    let abort_label =
        document.getElementById("abort-label");
    let on = 'black';
    let off = 'white';

    var ID = '<?php echo $ID; ?>';

    function ABORT_CLICK()
    {
        if (    abort_checkbox.style.backgroundColor
	     == on )
	{
	    abort_checkbox.style.backgroundColor = off;
	    abort_label.innerText = 'Abort';
	}
	else
	{
	    abort_checkbox.style.backgroundColor = on;
	    abort_label.innerText = 'Aborting';
	}
    }

    let ids = document.getElementsByName ( 'id' );
    let obsolete = [ document.getElementById ( 'id1' ),
                document.getElementById ( 'id2' ),
                document.getElementById ( 'id3' )];
		// Some of these may be null.

    function PROCESS_RESPONSE ( response )
    {
	item = response.trim().split ( '$' );
	ID = item[0];
	for ( var i = 0; i < ids.length; ++ i )
	{
	    // if ( ids[i] == null ) continue;
	    ids[i].value = ID;
	}

        if ( item[1].trim() == 'RELOAD' )
	{
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
	manage.style.display = 'none';
	run_list.style.display = 'none';
	    // These keep buttons from being clicked
	    // while waiting for xhttp response and
	    // its updated ID.
	abort_switch.style.visibility = 'visible';
	    // This permits abort.

	let abort =
	    (    abort_checkbox.style.backgroundColor
	      == on );
	var data = ( abort ? 'update=abort' :
	                     'update=yes' );
	data = data + '&xhttp=yes&id=' + ID
	     + '&problem=' + problem;
	LOG ( 'xhttp sent: ' + data );
	xhttp.send ( data );
    }
    <?php
	if ( $runresult === true )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
