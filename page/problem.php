<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Feb 25 11:36:22 EST 2020

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
    elseif ( isset ( $_POST['selected_problem'] ) )
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
	$_SESSION['EPM_PROBLEM'] = $problem;

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

    // Data Set by GET and POST Requests:
    //
    $show_file = NULL;  // File to be shown to right.
    $show_files = [];   // Files to be shown to left.
    $workbase = NULL;
        // Non-NULL if there are commands to be
	// displayed.
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
    // most recent first.
    //
    $problem_file_names = NULL;
        // Cache of problem_file_names().
    function problem_file_names()
    {
        global $epm_data, $probdir,
	       $problem_file_names, $display_file_type;

	if ( isset ( $problem_file_names ) )
	    return $problem_file_names;

	if ( ! isset ( $probdir ) )
	{
	    $problem_file_names = [];
	    return $problem_file_names;
	}

	clearstatcache();
	$map = [];

	foreach ( scandir ( "$epm_data/$probdir" )
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
	    $f = "$probdir/$fname";
	    $map[$fname] =
	        filemtime ( "$epm_data/$f" );
	}
	arsort ( $map, SORT_NUMERIC );
	    // Note, keys cannot be floating point and
	    // files often share modification times.
	foreach ( $map as $key => $value )
	    $problem_file_names[] = $key;

	return $problem_file_names;
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
		     ( $f, problem_file_names(),
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
	$problem_file_names = NULL;
	    // Clear cache.
    }

    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( ! isset ( $probdir ) )
	/* Do Nothing */;
    elseif ( isset ( $_POST['make'] ) )
    {
	require "$epm_home/include/epm_make.php";
	    // Do this first as it may change $f, etc.

        $m = $_POST['make'];
	if ( ! preg_match ( '/^([^:]+):([^:]+)$/', $m,
	                    $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$src = $matches[1];
	$des = $matches[2];
		 	    
	if ( array_search
	         ( $src, problem_file_names(),
		         true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	start_make_file
	    ( $src, $des, NULL /* no condition */,
	      true, "$probdir/+work+",
	      NULL, NULL /* no upload, upload_tmp */,
	      $errors );
	if ( isset
	         ( $_SESSION['EPM_WORK']['CONTROL'] ) )
	{
	    $workbase = $_SESSION['EPM_WORK']['BASE'];
	    $problem_file_names = NULL; // Clear cache.
	}
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
	{
	    require "$epm_home/include/epm_make.php";
		// Do this first as it may change $f,
		// etc.

	    process_upload
		( $upload_info, "$probdir/+work+",
		  $warnings, $errors );
	    if ( isset ( $_SESSION['EPM_WORK']
	                          ['CONTROL'] ) )
	    {
		$workbase =
		    $_SESSION['EPM_WORK']['BASE'];
		$problem_file_names = NULL;
		    // Clear cache.
	    }
	}
	else
	    $errors[] = "no file selected for upload";
    }
    elseif ( isset ( $_POST['run'] ) )
    {
	require "$epm_home/include/epm_make.php";
	    // Do this first as it may change $f, etc.

        $f = $_POST['run'];
	if ( ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
		 	    
	if ( array_search
	         ( $f, problem_file_names(),
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
	require "$epm_home/include/epm_make.php";
	    // Do this first as it may change $f, etc.

        $workbase = $_SESSION['EPM_WORK']['BASE'];
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	require "$epm_home/include/epm_make.php";
	    // Do this first as it may change $f, etc.

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

    if ( isset ( $workbase )
         &&
	 isset ( $_SESSION['EPM_WORK']['CONTROL'] )
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
    div.problem_display {
	background-color: #F2D9D9;
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
	color: #99003D;
	text-align: right;
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
	    '/page/' + page + '?filename=' + filename;
	document.body.appendChild ( iframe );
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
	echo "<tr>" . PHP_EOL;
	echo "<form action='problem.php'" .
	     " method='POST'" .
	     " style='display:inline'>" . PHP_EOL;
	echo "<td style='text-align:right'>" .
	     "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'></td>" . PHP_EOL;
        echo "<td><select name='selected_problem'" .
	     " title='problem to go to'>" .  PHP_EOL;
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>" . PHP_EOL;
        echo "</select></td>" . PHP_EOL;
        echo "</form>" . PHP_EOL;
	echo "<form action='run.php'" .
	     " method='GET'" .
	     " style='display:inline'>" . PHP_EOL;
	echo "<td><button type='submit'>" .
	     "Go to Run Page</button></td></form>" .
	     PHP_EOL;
	echo "</tr>" . PHP_EOL;
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
        echo <<<'EOT'
	<div class='problem_display'>
	<form action='problem.php'
	      enctype='multipart/form-data'
	      method='POST'
	      id='execute_form'>
	<input id='delete_files'
	       name='delete_files' value=''
	       type='hidden'>
EOT;
        $count = 0;
	foreach ( problem_file_names() as $fname )
	{
	    if ( ++ $count == 1 )
	        echo "<h5>Current Problem Files" .
		     " (most recent first):</h5>" .
		     "<table style='display:block'>";
	    echo "<tr>";
	    echo "<td style='text-align:right'>";
	    $fext = pathinfo ( $fname, 
			       PATHINFO_EXTENSION );
	    $ftype = $display_file_type[$fext];
	    if ( isset ( $display_file_map[$ftype] ) )
	    {
	        $fpage = $display_file_map[$ftype];
	        echo "<button type='button'" .
		     " onclick='CREATE_IFRAME" .
		     "(\"$fpage\",\"$fname\")'>" .
		     "<pre id='file$count'>" .
	             $fname . "</pre></button></td>";
	    }
	    else
	        echo "<pre id='file$count'>" .
	             $fname . "</pre></td>";
	    echo "<td><button type='button'" .
	         " onclick='TOGGLE_DELETE" .
		 "($count, \"$fname\")'" .
		 " title='Delete(X) or Un-Delete(+)" .
		 " $fname'>" .
		 "<pre id='delete$count'>" .
		 "&Chi;</pre></button></td>";
	    if ( preg_match ( '/^(.+)\.in$/', $fname,
	                      $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.sin" .
		     " from $fname'" .
		     " value='$fname:$b.sin'>" .
		     "&rArr;.sin</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.sout" .
		     " from $fname'" .
		     " value='$fname:$b.sout'>" .
		     "&rArr;.sout</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.score" .
		     " from $fname'" .
		     " value='$fname:$b.score'>" .
		     "&rArr;.score</button></td>";
	    }
	    elseif ( preg_match ( '/^(.+)\.sout$/',
	                          $fname, $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.fout" .
		     " from $fname'" .
		     " value='$fname:$b.fout'>" .
		     "&rArr;.fout</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.score" .
		     " from $fname'" .
		     " value='$fname:$b.score'>" .
		     "&rArr;.score</button></td>";
	    }
	    elseif ( preg_match ( '/^(.+)\.fout$/',
	                          $fname, $matches ) )
	    {
		$b = $matches[1];
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.score" .
		     " from $fname'" .
		     " value='$fname:$b.score'>" .
		     "&rArr;.score</button></td>";
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $b.ftest" .
		     " from $fname'" .
		     " value='$fname:$b.ftest'>" .
		     "&rArr;.ftest</button></td>";
	    }
	    elseif ( preg_match ( '/\.run$/', $fname ) )
	    {
		echo "<td><button type='submit'" .
		     " name='run'" .
		     " value='$fname'>" .
		     "Run</button></td>";
	    }
	    if ( ! isset ( $display_file_map[$ftype] ) )
	    {
		echo "<td colspan='10'>" .
		     "<pre>$ftype</pre></td>";
	    }
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
	       value="Execute Deletions">
	</form></div>
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
	if ( isset ( $workbase )
	     &&
	     isset (
	         $_SESSION['EPM_WORK']['CONTROL'] ) )
	    echo "REQUEST_UPDATE();" . PHP_EOL;
    ?>

</script>

</body>
</html>
