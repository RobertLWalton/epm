<?php

    // File:	run.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Feb 20 10:29:52 EST 2020

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
    $runsubmit = false;
    if ( isset ( $_SESSION['EPM_RUNBASE'] ) )
    {
        $runbase = $_SESSION['EPM_RUNBASE'];
        $rundir = $_SESSION['EPM_RUNDIR'];
        $runsubmit = $_SESSION['EPM_RUNSUBMIT'];
    }

// TBD

    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( isset ( $_POST['execute_run'] ) )
    {
	$f = $_POST['execute_run'];
	if ( unset ( $local_file_cache[$f] ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );

	// TBD
    }
    elseif ( isset ( $_POST['submit_run'] ) )
    {
	$f = $_POST['submit_run'];
	if ( unset ( $remote_file_cache[$f] ) )
	    exit ( "ACCESS: illegal POST to" .
	           " run.php" );

	// TBD
    }
    elseif ( isset ( $_POST['reload'] )
             &&
	     isset ( $runbase ) )
    {
        // Do nothing here.
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	if ( ! isset ( $_SESSION['EPM_RUNRESULT'] )
	     !!
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

    $debug = ( $epm_debug != ''
               &&
	       preg_match ( $epm_debug, $php_self ) );
	// True to enable javascript logging.

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
    div.command_display {
	background-color: #C0FFC0;
    }
    div.show {
	background-color: #E5C4E7;
    }
    div.indented {
	margin-left: 20px;
    }
    td.time {
	color: #0052CC;
    }
</style>

<script>
    var iframe;

    function create_iframe ( page, filename ) {
	if ( iframe != undefined ) iframe.remove();

	iframe = document.createElement("IFRAME");
	iframe.className = 'right';
	iframe.name = filename;
	iframe.src =
	    '/page/' + page + '?filename=' + filename;
	document.body.appendChild ( iframe );
    }
</script>
<body>

<div class='left'>
<?php 

    if ( $delete_problem )
    {
	echo "<div style='background-color:#F5F81A'>" .
	     PHP_EOL;
	echo "<form method='POST'" .
	     " style='display:inline'" .
	     " action=problem.php>";
	echo "Do you really want to delete current" .
	     " problem $problem?";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='delete_problem_yes'" .
	     " value='$problem'>" .
	     "YES</button>";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='delete_problem_no'" .
	     " value='$problem'>" .
	     "NO</button>";
	echo "</form></div>" . PHP_EOL;
    }
    else if ( isset ( $deleted_problem ) )
    {
	echo "<div style='background-color:#F5F81A'>" .
	     PHP_EOL;
	echo "Problem $deleted_problem has been" .
	     " deleted!<br>";
	echo "</div>" . PHP_EOL;
    }
    if ( count ( $errors ) > 0 )
    {
	echo "<div style='background-color:#F5F81A'>" .
	     PHP_EOL;
	echo "<h5>Errors:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div style='background-color:#ffc0ff'>" .
	     PHP_EOL;
	echo "<h5>Warnings:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }

    $current_problem = ( isset ( $problem ) ?
                                 $problem :
			         "none selected" );
    echo <<<EOT
    <form style='display:inline'
          action='user.php' method='GET'>
    <h5>User:</h5> <input type='submit' value='$email'
                    title='click to see user profile'>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <h5>Current Problem:</h5>&nbsp;
    <pre>$current_problem</pre></b>
    </form>
EOT;
    if ( isset ( $problem ) )
        echo "&nbsp;&nbsp;&nbsp;&nbsp;" .
	     "<form style='display:inline'" .
	     " action='problem.php' method='POST'>" .
             " <button type='submit'" .
	     " name='delete_problem'" .
	     " value='$problem'>" .
	     "Delete Current Problem</button>" .
	     "</form>";
    echo "<br>";
    echo "<table><form action='problem.php'" .
         " method='POST'>";
    if ( count ( $problems ) > 0 )
    {
	echo "<form action='problem.php'" .
	     " method='POST'" .
	     " style='display:inline'>" . PHP_EOL;
	echo "<tr><td style='text-align:right'>" .
	     "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'></td>" . PHP_EOL;
        echo "<td><select name='selected_problem'
	           title='problem to go to'>" .
	     PHP_EOL;
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>" . PHP_EOL;
        echo "</select></td></tr></form>" . PHP_EOL;
    }
    echo <<<EOT
    <form action='problem.php' method='POST'
	  style='display:inline'>
    <tr><td style='text-align:right'>
    <h5>or Create New Problem:<h5></td><td>
    <input type="text" size="32" name="new_problem"
           placeholder="New Problem Name" id="create">
    </td></tr></table></form>
EOT;

    if ( isset ( $problem ) )
    {
        $count = 0;
	foreach ( problem_file_names() as $fname )
	{
	    if ( ++ $count == 1 )
	        echo "<form action='problem.php'" .
		     " method='POST'>" .
		     "<h5>Current Problem Files" .
		     " (most recent first):</h5>" .
		     "<table style='display:block'>";
	    echo "<tr>";
	    echo "<td style='text-align:right'>" .
	         "<button type='submit'" .
	         " name='show_file' value='$fname'>" .
		 $fname . "</button></td>";
	    echo "<td><button type='submit'" .
	         " name='delete_file' value='$fname'>" .
		 "Delete</button></td>";
	    if ( preg_match ( '/^(.+)\.in$/', $fname,
	                      $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.sin'>" .
		     "Make .sin</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.sout'>" .
		     "Make .sout</button></td>";
	    }
	    elseif ( preg_match ( '/^(.+)\.sout$/',
	                          $fname, $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.fout'>" .
		     "Make .fout</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.score'>" .
		     "Make .score</button></td>";
	    }
	    elseif ( preg_match ( '/^(.+)\.fout$/',
	                          $fname, $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.score'>" .
		     "Make .score</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " value='$fname:$b.ftest'>" .
		     "Make .ftest</button></td>";
	    }
	    echo "</tr>";
	}
	if ( $count > 0 ) echo "</table></form>";

        echo <<<EOT

	<form enctype="multipart/form-data"
	      action="problem.php" method="post">
	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$epm_upload_maxsize">
	<input type="submit" name="upload"
	       value="Upload File:">
	<input type="file" name="uploaded_file"
	       title="file to upload">
	</form>
EOT;
    }

    if ( $workbase )
    {
	echo "<div class='command_display'>" .
	     PHP_EOL;
	get_commands_display ( $display );
	echo "<h5>Commands:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	echo $display . PHP_EOL;
	echo "</div>" . PHP_EOL;
        if ( count ( $kept ) > 0 )
	{
	    echo "<h5>Kept:</h5>" . PHP_EOL;
	    echo "<div class='indented'>" . PHP_EOL;
	    foreach ( $kept as $e )
	        echo "<pre>$e</pre><br>" . PHP_EOL;
	    echo "<br></div>" . PHP_EOL;
	}
	echo "</div>" . PHP_EOL;
    }

    if ( count ( $show_files ) > 0 )
    {
	echo "<br><div class='show'>" . PHP_EOL;
	foreach ( $show_files as $f )
	{
	    $f = "$epm_data/$f";
	    $b = basename ( $f );
	    if ( filesize ( $f ) == 0 )
	    {
		echo "<u><pre>$b</pre></u>" .
		     " is empty<br>" . PHP_EOL;
		continue;
	    }
	    $ext = pathinfo ( $f, PATHINFO_EXTENSION );
	    if ( ! isset ( $display_file_type[$ext] ) )
	        continue;
	    $type = $display_file_type[$ext];
	    if ( $type == 'pdf' ) $type = 'PDF file';

	    if ( $type == 'utf8' )
	    {
		echo "<u><pre>$b:</pre></u>" . PHP_EOL;
		$c = file_get_contents ( $f );
		$hc = htmlspecialchars ( $c );
		echo "<br><div" .
		     " style='margin-left:20px'>" .
		     PHP_EOL;
		echo "<pre>$hc</pre>" .  PHP_EOL;
		echo "</div>" .  PHP_EOL;
	    }
	    else
	    {
		$t = exec ( "file -h $f" );
		$t = explode ( ":", $t );
		$t = $t[1];
		if ( preg_match
		         ( '/symbolic link/', $t ) )
		{
		    $t = trim ( $t );
		    $t .= "\n    which is $type";
		}
		else
		    $t = $type;
		echo "<pre><u>$b</u> is $t</pre><br>" .
		     PHP_EOL;
	    }
	}
	echo "</div>" . PHP_EOL;
    }

    if ( isset ( $show_file ) )
    {
	$base = pathinfo ( $show_file, 
	                   PATHINFO_BASENAME );
	$ext = pathinfo ( $show_file, 
	                  PATHINFO_EXTENSION );
	$type = $display_file_type[$ext];
	$page = $display_file_map[$type];
	if ( $page != NULL ) echo <<<EOT
<script>create_iframe ( '$page', '$base' );</script>
EOT;
    }
?>

</div>

<form action='problem.php' method='POST' id='reload'>
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
        response = response.trim().split( "\n" );
	for ( i = 0; i < response.length; ++ i )
	{
	    let item = response[i].trim().split( ' ' );
	    if ( item.length == 0 ) continue;
	    if ( item[0] == '' )
	        continue;
	    else if ( item[0] == 'RELOAD' )
	    {
	    	reload.submit();
		return;
	    }
	    try {
		if ( item[0] == 'TIME'
			  &&
			  item.length == 3 )
		{
		    let n = "stat_time" + item[1];
		    let e = document.getElementById(n);
		    e.innerText = item[2] + 's';
		}
		else
		    FAIL ( 'bad response item: ' +
			   response[i] );
	    }
	    catch ( err )
	    {
		FAIL ( 'bad response item: ' +
		       response[i] + "\n    " +
		       err.message );
	    }
	}
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
	xhttp.open ( 'POST', "problem.php", true );
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

