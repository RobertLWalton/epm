<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Feb 28 20:22:44 EST 2020

    // Selects user problem.  Displays and uploads
    // problem files.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( isset ( $_SESSION['EPM_RUN']['RESULT'] )
         &&
	 $_SESSION['EPM_RUN']['RESULT'] === true )
    {
	// Run still running.
	//
	header ( 'Location: /page/run.php' );
	exit;
    }

    // if ( ! isset ( $_POST['update'] ) ) // xhttp
    //     require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_USER_ID'];
    $email = $_SESSION['EPM_EMAIL'];

    $user_dir = "users/user$uid";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    // The only $_SESSION state particular to this page
    // is $_SESSION['EPM_PROBLEM'].  The rest of the
    // state is in the file system.

    // Set $problem to current problem, or NULL if none.
    // Also set $probdir to the problem directory if
    // $problem not NULL and the problem directory
    // exists.  If $problem is not NULL but the problem
    // directory does not exist, the problem has been
    // deleted by another session.
    //
    // Also lock the problem directory for the duration
    // of the execution of this page.
    //
    $problem = NULL;
    $probdir = NULL;
    $delete_problem = false;
        // True to ask whether current problem is to be
	// deleted.
    $deleted_problem = NULL;
        // Set to announce that $deleted_problem has
	// been deleted.
    if ( isset ( $_POST['new_problem'] ) )
    {
        $problem = trim ( $_POST['new_problem'] );
	$d = "$epm_data/$user_dir/$problem";
	if ( $problem == '' )
	{
	    // User hit carriage return on empty
	    // field.
	    $problem = NULL;
	}
	elseif ( ! preg_match ( '/^[-_A-Za-z0-9]+$/',
	                        $problem )
	         ||
	         ! preg_match ( '/[A-Za-z]/', $problem )
	       )
	{
	    $errors[] =
	        "problem name $problem contains an" .
		" illegal character or" .
		" does not contain a letter";
	    $problem = NULL;
	}
	else
	if ( is_dir ( "$d" ) )
	{
	    $errors[] =
	        "trying to create $problem which" .
		" already exists";
	    $problem = NULL;
	}
	else
	{
	    $m = umask ( 06 );
	    if ( ! mkdir ( "$d", 0771 ) )
		ERROR ( "cannot make" .
		        " $user_dir/$problem" );
	    umask ( $m );
	}
    }
    elseif ( isset ( $_POST['goto_problem'] )
             &&
             isset ( $_POST['selected_problem'] ) )
    {
        $problem = trim ( $_POST['selected_problem'] );
	if ( ! preg_match
	           ( '/^[-_A-Za-z0-9]+$/', $problem ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	else
	if ( ! is_dir
	         ( "$epm_data/$user_dir/$problem" ) )
	{
	    $errors[] =
	        "trying to select non-existant" .
		" problem: $problem";
	    $problem = NULL;
	}
    }
    elseif ( isset ( $_POST['delete_problem'] ) )
    {
	$prob = $_POST['delete_problem'];
	if ( ! isset ( $_SESSION['EPM_PROBLEM'] )
	     ||
	     $prob != $_SESSION['EPM_PROBLEM'] )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$delete_problem = true;
    }
    elseif ( isset ( $_POST['delete_problem_yes'] ) )
    {
	$prob = $_POST['delete_problem_yes'];
	if ( ! isset ( $_SESSION['EPM_PROBLEM'] )
	     ||
	     $prob != $_SESSION['EPM_PROBLEM'] )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	unset ( $_SESSION['EPM_PROBLEM'] );
	$_SESSION['EPM_WORK'] = [];
	$_SESSION['EPM_RUN'] = [];
	$d = "$epm_data/$user_dir/$prob";
	exec ( "rm -rf $d" );
	$deleted_problem = $prob;
    }
    else if ( isset ( $_POST['delete_problem_no'] ) )
    {
	$prob = $_POST['delete_problem_no'];
	if ( ! isset ( $_SESSION['EPM_PROBLEM'] )
	     ||
	     $prob != $_SESSION['EPM_PROBLEM'] )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
    }

    if (    ! isset ( $problem )
         && isset ( $_SESSION['EPM_PROBLEM'] ) )
        $problem = $_SESSION['EPM_PROBLEM'];
    elseif ( isset ( $problem ) )
    {
	$_SESSION['EPM_PROBLEM'] = $problem;
	$_SESSION['EPM_WORK'] = [];
	$_SESSION['EPM_RUN'] = [];
    }

    $lock_desc = NULL;
    function shutdown ()
    {
        global $lock_desc;
	if ( isset ( $lock_desc ) )
	    flock ( $lock_desc, LOCK_UN );
    }
    register_shutdown_function ( 'shutdown' );

    if ( isset ( $problem ) )
    {
	$probdir =
	    "users/user$uid/$problem";
	if ( ! is_dir ( "$epm_data/$probdir" ) )
	{
	    $errors[] = "problem $problem has been"
	             . " deleted by another session";
	    $probdir = NULL;
	    $problem = NULL;
	    unset ( $_SESSION['EPM_PROBLEM'] );
	    $_SESSION['EPM_WORK'] = [];
	    $_SESSION['EPM_RUN'] = [];
	}
	else
	{
	    $lock_desc =
		fopen ( "$epm_data/$probdir/+lock+",
		        "w" );
	    flock ( $lock_desc, LOCK_EX );
	}

    }
    else
	$probdir = NULL;

    if ( isset ( $problem ) )
	require "$epm_home/include/epm_make.php";
        // Do this after setting $problem and $probdir,
	// unless this is a GET and not a POST.

    // Data Set by GET and POST Requests:
    //
    $show_file = NULL;  // File to be shown to right.
    $show_files = [];   // Files to be shown to left.
    $uploaded_file = NULL;
        // 'name' of uploaded file, if any file was
	// uploaded.

    // Set $problems to list of available problems.
    //
    $problems = [];

    $desc = opendir ( "$epm_data/$user_dir" );
    if ( $desc === false )
         ERROR ( "cannot open $user_dir" );
    while ( true )
    {
	$value = readdir ( $desc );
	if ( ! $value )
	{
	    closedir ( $desc );
	    break;
	}
	if ( preg_match
	         ( '/^[-_A-Za-z0-9]+$/', $value ) )
	    $problems[] = $value;
    }

    // Return DISPLAYABLE problem file names, sorted
    // most recent first, that are in the given
    // directory.  Only the last component of each
    // name is returned.  The directory is relative
    // to $epm_data.
    //
    function problem_file_names ( $dir )
    {
        global $epm_data, $display_file_type;

	clearstatcache();
	$map = [];

	foreach ( scandir ( "$epm_data/$dir" )
	          as $fname )
	{
	    if ( preg_match ( '/^\./', $fname ) )
	        continue;
	    if ( ! preg_match ( '/^[_\-.A-Za-z0-9]+$/',
	                        $fname ) )
	        continue;
	    $ext = pathinfo
	        ( $fname, PATHINFO_EXTENSION );
	    if ( ! isset ( $display_file_type[$ext] ) )
		continue;
	    $map[$fname] =
	        filemtime ( "$epm_data/$dir/$fname" );
	}
	arsort ( $map, SORT_NUMERIC );
	    // Note, keys cannot be floating point and
	    // files often share modification times.
	$names = [];
	foreach ( $map as $key => $value )
	    $names[] = $key;

	return $names;
    }

    function file_info
            ( $dir, $fname, $count, & $display_list )
    {
        global $epm_data, $display_file_type,
	       $display_file_map;

	$fext = pathinfo ( $fname, 
			   PATHINFO_EXTENSION );
	$ftype = $display_file_type[$fext];

	$f = "$epm_data/$dir/$fname";
	$fsize = NULL;
	$fsize = @filesize ( $f );
	$fcontents = NULL;
	$flines = NULL;
	$fdisplay = false;
	if (    $ftype == 'utf8'
	     && isset ( $fsize )
	     && $fsize <= 8000 )
	{
	    $fcontents = "$fname contents could"
		       . " not be read\n";
	    $fcontents = @file_get_contents ( $f );
	    $flines =
		count ( explode
			    ( "\n", $fcontents ) )
		- 1;
	}
	if ( isset ( $fsize ) && $fsize == 0 )
	    $fcomment = '(Empty)';
	elseif ( $ftype == 'utf8' )
	{
	    if (    $flines == 1
		 && strlen ( $fcontents ) <= 31 )
		$fcomment = '{'
			  . trim ( $fcontents )
			  . '}';
	    elseif ( isset ( $flines ) )
	    {
		$fcomment = "($flines Lines)";
		if ( $flines <= 200 )
		{
		    $display_list[] =
			[$count, $fname,
			 $fcontents];
		    $fdisplay = true;
		}
	    }
	    elseif ( isset ( $fsize ) )
		$fcomment = "($fsize Bytes)";
	    else
		$fcomment = "";
	}
	elseif ( isset ( $display_file_map
			       [$ftype] ) )
	{
	    if ( isset ( $fsize ) )
		$fcomment = "($fsize Bytes)";
	    else
		$fcomment = "";
	}
	else
	{
	    if ( is_link ( $f ) )
		$fcomment = "Link to $ftype";
	    else 
		$fcomment = $ftype;
	}

	return [$fext, $ftype, $fdisplay, $fcomment];
    }

    // Remaining POSTs require $problem and $probdir
    // to be non-NULL.
    //
    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( ! isset ( $probdir ) )
	/* Do Nothing */;
    elseif ( isset ( $_POST['delete_files'] ) )
    {
        // Process file deletions for other posts.
	//
	$files = $_POST['delete_files'];
	$files = explode ( ',', $files );
	foreach ( $files as $f )
	{
	    if ( $f == '' ) continue;
	    if ( array_search
		     ( $f, problem_file_names
		     		( $probdir ),
			   true ) === false )
		exit ( "ACCESS: illegal POST to" .
		       " problem.php" );
	}
	foreach ( $files as $f )
	{
	    if ( $f == '' ) continue;
	    $g = "$probdir/$f";
	    if ( ! unlink ( "$epm_data/$g" ) )
		$errors[] = "could not delete $g";
	}
    }

    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( ! isset ( $probdir ) )
	/* Do Nothing */;
    elseif ( isset ( $_POST['make'] ) )
    {
        $m = $_POST['make'];
	if ( ! preg_match ( '/^([^:]+):([^:]+)$/', $m,
	                    $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$src = $matches[1];
	$des = $matches[2];
		 	    
	if ( array_search
	         ( $src, problem_file_names
		 	     ( $probdir ),
		         true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	start_make_file
	    ( $src, $des, NULL /* no condition */,
	      true, "$probdir/+work+",
	      NULL, NULL /* no upload, upload_tmp */,
	      $errors );
    }
    elseif ( isset ( $_POST['upload'] ) )
    {
	if ( isset ( $_FILES['uploaded_file']
	                     ['name'] ) )
	{
	    $upload_info = $_FILES['uploaded_file'];
	    $uploaded_file = $upload_info['name'];
	}
	else
	    $uploaded_file = '';

	if ( $uploaded_file != '' )
	    process_upload
		( $upload_info, "$probdir/+work+",
		  $warnings, $errors );
	else
	    $errors[] = "no file selected for upload";
    }
    elseif ( isset ( $_POST['run'] ) )
    {
        $f = $_POST['run'];
	if ( ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
		 	    
	if ( array_search
	         ( $f, problem_file_names
		 	   ( $probdir ),
		       true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	start_run
	    ( "$probdir/+work+", $f, "$probdir/+run+",
	      false, $errors );
        if ( isset ( $_SESSION['EPM_RUN']['RESULT'] ) )
	{
	    header ( 'Location: /page/run.php' );
	    exit;
	}
    }
    elseif ( isset ( $_POST['reload'] )
             &&
	     isset ( $_SESSION['EPM_WORK']['BASE'] ) )
    {
	/* Do Nothing */
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	$count = 0;
	while ( true )
	{
	    $r = update_work_results ( 0 );
	    if ( $r !== true || $count == 50 )
	    {
	        echo 'RELOAD';
		exit;
	    }
	    $r = update_workmap();
	    if ( count ( $r ) > 0 )
	    {
		$workmap =
		    & $_SESSION['EPM_WORK']['MAP'];
	        foreach ( $r as $n )
		{
		    $e = $workmap[$n];
		    echo "TIME $n {$e[2]}\n";
		}
		exit;
	    }
	    usleep ( 100000 );
	    $count += 1;
	}
    }

    if ( isset ( $_SESSION['EPM_WORK']['CONTROL'] )
         &&
	 update_work_results() !== true )
    {
        finish_make_file ( $warnings, $errors );
	$show_files = $_SESSION['EPM_WORK']['SHOW'];
    }

    if ( count ( $show_files ) > 0 )
    {
        if ( ! function_exists ( "find_show_file" ) )
	    ERROR ( "problem.php:" .
	            " failed to load epm_make.php" .
		    " while setting show_files" );
        $show_file = find_show_file ( $show_files );
    }

    $debug = ( $epm_debug != ''
               &&
	       preg_match ( $epm_debug, $php_self ) );
	// True to enable javascript logging.

?>

<html>
<style>
    .no-margin {
	margin: 0 0 0 0;
    }
    h5 {
        font-size: 1vw;
	margin: 0 0 0 0;
	display:inline;
    }
    pre, b, button, input, select, u {
	display:inline;
        font-size: 0.8vw;
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    pre.red {
        color: #BB0000;
    }
    div.left {
	width: 50%;
	float: left;
        font-size: 0.8vw;
	height: 99%;
	overflow: scroll;
    }
    div.manage {
	background-color: #96F9F3;
    }
    div.problem_display {
	background-color: #F2D9D9;
    }
    div.command_display {
	background-color: #C0FFC0;
    }
    div.work_display {
	background-color: #F2D9D9;
    }
    div.indented {
	margin-left: 20px;
    }
    td.time {
	color: #99003D;
	text-align: right;
    }
    iframe.right {
	width: 48%;
	float: right;
	height: 99%;
    }

</style>

<script>
    var iframe;

    function CREATE_IFRAME ( page, filename ) {
	if ( iframe != undefined ) iframe.remove();

	iframe = document.createElement("IFRAME");
	iframe.className = 'right';
	iframe.name = filename;
	iframe.src =
	    '/page/' + page + '?filename='
	             + encodeURIComponent ( filename );
	document.body.appendChild ( iframe );
    }

    function TOGGLE_BODY ( toggle, body )
    {
	var TOGGLE = document.getElementById ( toggle );
	var BODY = document.getElementById ( body );
	if ( BODY.hidden )
	{
	    TOGGLE.innerHTML = "&uarr;";
	    BODY.hidden = false;
	}
	else
	{
	    TOGGLE.innerHTML = "&darr;";
	    BODY.hidden = true;
	}
    }

    var DELETE_LIST = [];

    function TOGGLE_DELETE ( count, fname )
    {
	var FILE = document.getElementById
	               ("file" + count);
	var DELETE = document.getElementById
	               ("delete" + count);
        let i = DELETE_LIST.findIndex
	            ( x => x == fname );
	if ( i == -1 )
	{
	    DELETE_LIST.push ( fname );
	    DELETE.innerHTML = "+";
	    FILE.style = 'text-decoration:line-through';

	}
	else
	{
	    DELETE_LIST.splice ( i, 1 );
	    DELETE.innerHTML = "&Chi;";
	    FILE.style = 'text-decoration:';
	}
	var DELETE_FILES = document.getElementById
			       ("delete_files");
	DELETE_FILES.value = DELETE_LIST.toString();
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
    <div class='manage'>
    <form action='problem.php' method='POST'
          style='margin:0 0 1vh 0'>
    <table style='width:100%'>
    <tr>
    <td style='width:33%'>
    <h5>User:</h5> <input type='submit' value='$email'
		    formaction='user.php'
		    formmethod='GET'
                    title='click to see user profile'>
    </td>
    <td style='width:33%'>
    <h5>Current Problem:</h5>&nbsp;
    <pre>$current_problem</pre></b>
    </td>
EOT;
    if ( isset ( $problem ) )
        echo "<td style='width:33%'>" .
	     "<button type='submit'" .
	     " name='delete_problem'" .
	     " value='$problem'>" .
	     "Delete Current Problem</button></td>";
    echo "</tr>";
    if ( count ( $problems ) > 0 )
    {
	echo "<tr><td></td><td>" . PHP_EOL;
	echo "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'>" . PHP_EOL;
        echo "<select name='selected_problem'" .
	     " title='problem to go to'>" .  PHP_EOL;
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>" . PHP_EOL;
        echo "</select></td>" . PHP_EOL;
	if ( isset ( $problem ) )
	    echo "<td><input type='submit' .
			     formaction='run.php' .
			     formmethod='GET' .
			     value='Go to Run Page'></td>" .
		 PHP_EOL;
	echo "</tr>" . PHP_EOL;
    }
    echo <<<EOT
    </table></form>
    <form action='problem.php' method='POST'
          class='no-margin'>
    <h5>or Create New Problem:<h5>
    <input type="text" size="32" name="new_problem"
           placeholder="New Problem Name" id="create">
    </form>
    <br><pre style='font-size:1vh'>   </pre>
    </div>
EOT;

    if ( isset ( $probdir ) )
    {
        echo <<<EOT
	<div class='problem_display'>
	<button type='button'
		onclick='TOGGLE_BODY
		     ("problems_toggle",
		      "problems_body")'
		title='(Un)Show Problems'>
		<pre id='problems_toggle'>&uarr;</pre>
		</button>
	<h5>Current Problem Files (most recent first):</h5>
	<div id='problems_body'>
	<form action='problem.php'
	      enctype='multipart/form-data'
	      method='POST'
	      id='execute_form'
	      class='no-margin'>
	<input id='delete_files'
	       name='delete_files' value=''
	       type='hidden'>
EOT;
        $count = 0;
	$display_list = [];
	foreach ( problem_file_names( $probdir )
	          as $fname )
	{
	    if ( ++ $count == 1 )
	        echo "<table style='display:block'>";
	    echo "<tr>";
	    echo "<td style='text-align:right'>";
	    list ( $fext, $ftype, $fdisplay, $fcomment )
	        = file_info ( $probdir, $fname, $count,
		              $display_list );
	    $fbase = pathinfo ( $fname, 
			        PATHINFO_FILENAME );

	    if ( isset ( $display_file_map[$ftype] ) )
	    {
	        $fpage = $display_file_map[$ftype];
	        echo <<<EOT
		    <button type='button'
		       title='Show $fname at Right'
		       onclick='CREATE_IFRAME
		          ("$fpage","$fname")'>
		     <pre id='file$count'>$fname</pre>
		     </button></td>
EOT;
	    }
	    else
	        echo <<<EOT
		    <pre id='file$count'>$fname</pre>
		    </td>
EOT;
	    if ( $fdisplay )
		echo <<<EOT
		    <td><button type='button'
			 onclick='TOGGLE_BODY
			     ("show$count",
			      "contents$count")'
			 title='(Un)Show $fname Below'>
		    <pre id='show$count'>&darr;</pre>
		    </td>
EOT;
	    else
	        echo "<td></td>";

	    echo <<<EOT
		<td><button type='button'
		     onclick='TOGGLE_DELETE
			($count, "$fname")'
		     title='(Un)Delete $fname'>
		<pre id='delete$count'>&Chi;</pre>
		</button></td>
EOT;
	    if ( $fext == 'in' )
	    {
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.sin" .
		     " from $fname'" .
		     " value='$fname:$fbase.sin'>" .
		     "&rArr;.sin</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.sout" .
		     " from $fname'" .
		     " value='$fname:$fbase.sout'>" .
		     "&rArr;.sout</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.score" .
		     " from $fname'" .
		     " value='$fname:$fbase.score'>" .
		     "&rArr;.score</button></td>";
	    }
	    elseif ( $fext == 'sout' )
	    {
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.fout" .
		     " from $fname'" .
		     " value='$fname:$fbase.fout'>" .
		     "&rArr;.fout</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.score" .
		     " from $fname'" .
		     " value='$fname:$fbase.score'>" .
		     "&rArr;.score</button></td>";
	    }
	    elseif ( $fext == 'fout' )
	    {
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.score" .
		     " from $fname'" .
		     " value='$fname:$fbase.score'>" .
		     "&rArr;.score</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.ftest" .
		     " from $fname'" .
		     " value='$fname:$fbase.ftest'>" .
		     "&rArr;.ftest</button></td>";
	    }
	    elseif ( $fext == 'run' )
	    {
		echo "<td><button type='submit'" .
		     " name='run'" .
		     " value='$fname'>" .
		     "Run</button></td>";
	    }
	    echo "<td colspan='10'>" .
		 "<pre>$fcomment</pre></td>";
	    echo "</tr>";
	}
	if ( $count > 0 ) echo "</table>";

        echo <<<EOT

	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$epm_upload_maxsize">
	<input type="submit" name="upload"
	       value="Upload File:">
	<input type="file" name="uploaded_file"
	       title="file to upload">
	<pre>          </pre>
	<input type="submit" name="execute_deletes"
	       value="Delete Marked (Over-Struck) Files">
	</form></div></div>
EOT;

	if ( isset ( $_SESSION['EPM_WORK']['DIR'] ) )
	{
	    $workdir = $_SESSION['EPM_WORK']['DIR'];
	    echo "<div class='command_display'>" .
		 PHP_EOL;
	    get_commands_display ( $display );
	    echo <<<EOT
	    <button type='button'
		    onclick='TOGGLE_BODY
			 ("command_toggle",
			  "command_body")'
		    title='(Un)Show Commands'>
		    <pre id='command_toggle'>&uarr;</pre>
		    </button>
	    <h5>Commands Last Executed:</h5>
	    <div id='command_body'>
EOT;
	    echo "<div class='indented'>" . PHP_EOL;
	    echo $display . PHP_EOL;
	    echo "</div>" . PHP_EOL;
	    $kept = $_SESSION['EPM_WORK']['KEPT'];
	    if ( count ( $kept ) > 0 )
	    {
		echo "<h5>Kept:</h5>" . PHP_EOL;
		echo "<div class='indented'>" . PHP_EOL;
		foreach ( $kept as $e )
		    echo "<pre>$e</pre><br>" . PHP_EOL;
		echo "<br></div>" . PHP_EOL;
	    }
	    echo "</div>" . PHP_EOL;

	    $working_files =
	        problem_file_names( $workdir );

	    if ( count ( $working_files ) > 0 )
	    {
		echo <<<EOT
		<div class='work_display'>
		<button type='button'
			onclick='TOGGLE_BODY
			     ("working_toggle",
			      "working_body")'
			title='(Un)Show Problems'>
			<pre id='working_toggle'>&uarr;</pre>
			</button>
		<h5>Current Working Files (most recent first):</h5>
		<div id='working_body'>
		<table style='display:block'>
EOT;

		$workdir = $_SESSION['EPM_WORK']['DIR'];
		$count_first = $count + 1;
		foreach ( problem_file_names( $workdir )
			  as $fname )
		{
		    $f = "$epm_data/$workdir/$fname";
		    if ( is_link ( $f ) )
			continue;

		    ++ $count;
		    echo "<tr>";
		    echo "<td style='text-align:right'>";
		    list ( $fext, $ftype, $fdisplay, $fcomment )
			= file_info ( $workdir, $fname, $count,
				      $display_list );

		    if ( $fdisplay )
		    {
			$fpage = $display_file_map[$ftype];
			echo <<<EOT
			<button type='button'
			        title='Show $fname at Right'
			         onclick='CREATE_IFRAME
				  ("utf8_show.php",
				   "+work+/$fname")'>
			     <pre id='file$count'>$fname</pre>
			     </button></td>
EOT;
		    }
		    else
			echo <<<EOT
			    <pre id='file$count'>$fname</pre>
			    </td>
EOT;
		    if ( $fdisplay )
			echo <<<EOT
			    <td><button type='button'
				 onclick='TOGGLE_BODY
				     ("show$count",
				      "contents$count")'
				 title='(Un)Show $fname Below'>
			    <pre id='show$count'>&darr;</pre>
			    </button></td>
EOT;
		    else
			echo "<td></td>";

		    echo "<td colspan='10'>" .
			 "<pre>$fcomment</pre></td>";
		    echo "</tr>";
		}
		echo "</table></div>";
		echo "</div>";
	    }
	}

	if ( count ( $display_list ) > 0 )
	{
	    foreach ( $display_list as $pair )
	    {
		$count = $pair[0];
	        $fname = $pair[1];
		$fcontents = $pair[2];
		$fcontents = htmlspecialchars
		    ( $fcontents );
		echo <<<EOT
		<div hidden id='contents$count'>
		<h5>$fname:</h5><br>
		<div class='indented'>
		<pre>$fcontents</pre>
		</div></div>
EOT;
	    }
	}
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
<script>CREATE_IFRAME ( '$page', '$base' );</script>
EOT;
    }
?>

</div>

<form action='problem.php' method='POST' id='reload'>
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
	if ( isset (
	         $_SESSION['EPM_WORK']['CONTROL'] ) )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
