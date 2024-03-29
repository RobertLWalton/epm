<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Feb  9 07:02:46 EST 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Starts and monitors problem runs and displays
    // results.

    $epm_page_type = '+problem+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_REQUEST['problem'] ) )
	exit ( 'UNACCEPTABLE HTTP ' . $epm_method );

    $problem = $_REQUEST['problem'];
    $probdir = "accounts/$aid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
        exit ( "problem $problem no longer exists" );

    require "$epm_home/include/epm_make.php";
    require "$epm_home/include/epm_list.php";

    // Session Data:
    //
    //	  $run = & $_SESSION['EPM_RUN'][$problem]
    //		data for recent Run Page run; see
    //		include/epm_make.php; set when
    //		include/epm_make.php loaded.  May
    //		be set to [] if no data exist.
    //
    //    $state (see index.php)
    //		normal
    //		executing (run via epm_run)
    //		    (means $run['RESULT'] is set
    //		     and $run['FINISHED'] is set false)
    //
    // POSTs:
    //
    //    execute_run=FILENAME
    //		execute start_run; if no errors,
    //		set state to executing
    //
    //    submit_run=FILENAME
    //		execute start_run; if no errors,
    //		set state to executing
    //		
    //
    // xhttp POSTs:
    //
    //    These are recognized in the executing state.
    //
    //    reload
    //		finish execution and reload page
    //
    //    update=  update=abort
    //		read status produced by epm_run
    //		(every 0.5 seconds) and return
    //		contents of status file; if abort
    //		given, abort the run first

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $post_processed = false;
    if ( $state == 'normal' )
	$blocked = blocked_parent ( $problem, $errors );
    else
	$blocked = false;

    if ( $epm_method == 'GET'
         &&
	 isset ( $run['RESULT'] )
	 &&
	 ! $run['FINISHED'] )
    {
	// Handle hand-off from problems.php
	// which starts run.
	//
	$state = 'executing';
    }

    if ( $blocked )
    {
	if ( isset ( $run['DIR'] ) )
	{
	    abort_dir ( $run['DIR'] );
	    usleep ( 2000000 ); // 2.0 second
	}
	$run['RESULT'] = NULL;
    }
    else
	load_file_caches();

    // Handle requests to start a run.
    //
    if ( $epm_method != 'POST'
         ||
	 ! $rw
	 ||
	 $state != 'normal'
	 ||
	 $blocked )
	/* Do Nothing */;
    elseif ( isset ( $_POST['execute_run'] ) )
    {
	$f = $_POST['execute_run'];
	if ( ! preg_match ( $epm_filename_re, $f )
	     ||
	     ! isset ( $local_file_cache[$f] )
	     ||
	     substr ( $f, -4 ) != '.run' )
	    exit ( 'UNACCEPTABLE HTTP POST:' .
	           ' execute_run' );
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_run ( "$probdir/+work+", $f,
	            $lock, "$probdir/+run+", false,
	            $errors );
	if ( count ( $errors ) == 0 )
	    $state = 'executing';
	$post_processed = true;
    }
    elseif ( isset ( $_POST['submit_run'] ) )
    {
	$f = $_POST['submit_run'];
	if ( ! preg_match ( $epm_filename_re, $f )
	     ||
	     ! isset ( $remote_file_cache[$f] )
	     ||
	     substr ( $f, -4 ) != '.run' )
	    exit ( 'UNACCEPTABLE HTTP POST' .
	           ' submit_run' );
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_run ( "$probdir/+work+", $f,
	            $lock, "$probdir/+run+", true,
	            $errors );
	if ( count ( $errors ) == 0 )
	    $state = 'executing';
	$post_processed = true;
    }


    $runbase = NULL;
    $rundir = NULL;
    $runresult = NULL;
    if ( isset ( $run['RESULT'] ) )
    {
        $runbase = $run['BASE'];
        $rundir = $run['DIR'];
        $runresult = $run['RESULT'];
    }

    // State checks.
    //
    if ( ( isset ( $run['RESULT'] )
	   &&
	   ! $run['FINISHED'] )
	 != ( $state == 'executing' ) )
        ERROR ( "executing state error" );
    if ( isset ( $runresult ) != isset ( $rundir )
         ||
	 isset ( $runresult ) != isset ( $runbase ) )
        ERROR ( "\$run{result,dir,base} error" );

    // handle xhttp POSTs.
    //
    if ( $epm_method != 'POST'
         ||
	 $post_processed
         ||
	 ! $rw
	 ||
	 $state != 'executing'
	 ||
	 $blocked )
    	/* Do Nothing */;
    else if ( isset ( $_POST['reload'] ) )
    {
        // State will be reset to normal below.
	$post_processed = true;
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	if ( $_POST['update'] == 'abort' )
	    abort_dir ( $run['DIR'] );

	usleep ( 2000000 ); // 2.0 second
	$runresult = update_run_results();
	if ( ! isset ( $runresult )
	     ||
	     $runresult !== true )
	{
	    echo "$ID\$RELOAD";
	    exit;
	}

	$f = "$rundir/$runbase.stat";
	$contents = @file_get_contents
	    ( "$epm_data/$f" );
	if ( $contents !== false )
	    echo "$ID\$$contents";
	else
	    echo "$ID\$(no status available yet)";
	exit;
    }

    if ( $epm_method == 'POST' && ! $post_processed
                               && ! $blocked )
    {
        if ( ! $rw && $state == 'normal' )
	     $errors[] =
	         'you are no longer in read-write mode';
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );
    }

    if ( $state == 'executing' )
    {
	if ( $runresult === true )
	    $runresult = update_run_results();

	if ( $runresult !== true
	     &&
	     ! $run['FINISHED'] )
	{
	    finish_run ( $warnings, $errors );
	    if ( isset ( $run['FIRST-FAILED'] ) )
	    {
	        // Can only happen on submit, so
		// parent must exist.
		$d = "$probdir/+parent+";
		if ( ! is_link ( "$epm_data/$d" ) )
		    ERROR ( "$d is not link" );
		$t = @readlink ( "$epm_data/$d" );
		if ( $t === false )
		    ERROR ( "cannot read link $d" );
		if ( ! preg_match ( $epm_parent_re,
				    $t, $matches ) )
		    ERROR ( "link $d has bad target" .
		            " $t" );
		$project = $matches[3];
		problem_priv_map
		    ( $pmap, $project, $problem,
		             $errors );

		$ff = $run['FIRST-FAILED'];
		if ( count ( $errors ) == 0
		     &&
		     isset ( $pmap['first-failed'] )
		     &&
		     $pmap['first-failed'] == '+' )
		    link_test_case ( $ff, $warnings );

		// Its not an error to not have
		// first-failed privileges.
	    }
	    reload_file_caches();
	    $state = 'normal';
	}
    }

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

    var problem = '<?php echo $problem; ?>';
    var highlight = '/^(Errors |Score:|'
		  + 'First-Failed-Test-Case:)/';

    function LOOK ( event, filename ) {

	var name = problem + '/' + filename;
	var disposition = 'show';
	if ( event.ctrlKey )
	{
	    name = '_blank';
	    disposition = 'download';
	}
	var src = 'look.php'
	        + '?disposition=' + disposition
	        + '&location='
	        + encodeURIComponent ( problem )
		+ '&filename='
		+ encodeURIComponent ( filename )
		+ '&highlight='
		+ encodeURIComponent ( highlight );
	if ( disposition == 'download' )
	    window.open ( src, '_blank' );
	else
	    AUX ( event, src, name );
    }

    function CLICK ( s )
    {
	var SWITCH = document.getElementById ( s );
	SWITCH.click();
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

    if ( $state == 'normal' )
    {
	$refresh = "run.php?problem=$problem"
		 . "&id=$ID";
	echo <<<EOT
	<div class='manage'>
	<table style='width:100%'>
	<tr>
	<td>
	<strong title='Login Name'>$lname</strong>
	</td>
	<td style='padding-left:50px'>
	<strong>Go To</strong>
	<form method='GET'>
	<input type='hidden'
	       name='problem' value='$problem'>
	<input type='hidden' name='id' value='$ID'>
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
	<button type='button' id='refresh'
		onclick='location.replace ("$refresh")'>
	    &#8635;</button>
	<button type='button'
		onclick='HELP("run-page")'>
	    ?</button>
	</td>
	</tr>
	</table>
	</div>
EOT;
    }

    if ( $blocked ) exit;
        // Page has nothing but top line if blocked.

    if ( isset ( $runresult ) )
    {
	$y = "";
	if ( $runresult === true )
	{
	    $h = 'Currently Executing Run';
	    $y = " yet";
	}
	else
	    $h = 'Last Completed Run';
	$c = @file_get_contents
	    ( "$epm_data/$rundir/$runbase.stat" );
	if ( $c === false )
	    $c = "(no status available$y)";
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

    if ( $state == 'normal' )
    {
	compute_run_map ( $local_map, $rundir );
	    // $rundir may be NULL if it does not exist

	$n = 0;
	$initially_display = NULL;
	if ( $local_map != [] )
	{
	    echo <<<EOT
	    <div class='run_list'>
	    <form action='run.php' method='POST'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden'
		   name='problem' value='$problem'>
	    <table>
EOT;
	    $td = [ 'run' => "<td>",
		    'rout' =>
		      "<td style='padding-left:40px'>",
		    'rerr' =>
		      "<td style='padding-left:40px'>"
		  ];
	    foreach ( $local_map as $base => $entry )
	    {
		++ $n;

		echo "<tr>";
		foreach ( ['run','rout','rerr']
		          as $rxxx )
		{
		    if ( $entry[$rxxx] === false )
		        continue;
		    if (    $entry[$rxxx] == ''
			 && $rxxx != 'run' )
			continue;

		    $fname = "$base.$rxxx";
		    $d = "$epm_data/$probdir";
		    if ( $rxxx == 'run' )
		    {
			// Local version takes
			// precedence over remote
			// version.
			//
		        if ( is_readable
			       ( "$d/$fname" ) )
			    $f = $fname;
		        elseif ( is_readable
			           ( "$d/+parent+/" .
				     "$fname" ) )
			    $f = "+parent+/$fname";
			else
			    continue;
		    }
		    else
		    {
		        if ( is_readable
			       ( "$d/+run+/$fname" ) )
			    $f = "+run+/$fname";
		        elseif ( is_readable
			           ( "$d/$fname" ) )
			    $f = $fname;
			else
			    continue;
		    }
		    echo $td[$rxxx];
		    echo <<<EOT
			 <button type='button'
				 id='s_$rxxx$n'
				 onclick='LOOK
				   (event,"$f")'
			    >$fname</button>
EOT;
		    if ( $rxxx != 'run' )
		    {
		        if ( $n == 1 )
			    $initially_display =
			        "$rxxx$n";
		    }

		    elseif ( $rw
		             &&
			     $entry['loc'] == 'local' )
			 echo <<<EOT
			 <button type='submit'
				 name='execute_run'
				 value='$fname'
			     >Run</button>
EOT;
		    elseif ( $rw )
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

	if ( isset ( $runresult )
	     &&
	     $runresult !== true
	     &&
	     isset ( $initially_display ) )
	    echo "<script>" .
	         "CLICK('s_$initially_display');" .
		 "</script>";

    }

    echo <<<EOT
    <form action='run.php' method='POST' id='reload'>
    <input type='hidden' name='id' value='$ID'>
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
	alert ( message );
	window.close();
	location.assign ( 'illegal.html' );
    }

    function ALERT ( message )
    {
	// Alert must be scheduled as separate task.
	//
	setTimeout
	    ( function () { alert ( message ); } );
    }

    let reload = document.getElementById("reload");
    let reload_id =
        document.getElementById("reload-id");
    // let problem = '<?php echo $problem; ?>';
    // Declared above.
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

    var RESPONSE = ''; // Saved here for error messages.
    let response_re = /^[a-fA-F0-9]{32}\$[^$]*$/;
    function PROCESS_RESPONSE ( response )
    {
        if ( ! response_re.test ( response ) )
	    FAIL ( 'Bad response: ' + response );

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
	    RESPONSE = this.responseText;
	    LOG ( 'xhttp response: ' + RESPONSE );
	    PROCESS_RESPONSE ( RESPONSE );
	};
	xhttp.open ( 'POST', "run.php", true );
	xhttp.setRequestHeader
	    ( "Content-Type",
	      "application/x-www-form-urlencoded" );
	REQUEST_IN_PROGRESS = true;
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
	if ( $state == 'executing' )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
