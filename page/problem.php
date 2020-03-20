<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Mar 20 05:37:55 EDT 2020

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

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    $user_dir = "users/$uid";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $post_processed = false;
    		     // Set true when POST recognized.

    // The $_SESSION state particular to this page is:
    //
    //     $_SESSION['EPM_PROBLEM'] current problem name
    //		or unset if none
    //     $_SESSION['EPM_WORK'] information on last
    //		group of commands run in +work+ sub-
    //		directory

    // Set $problem to current problem, or NULL if none.
    // Also set $probdir to the problem directory if
    // $problem not NULL and the problem directory
    // exists.  If $problem is not NULL but the problem
    // directory does not exist, the problem has been
    // deleted by another session (so set $problem and
    // $probdir to NULL).
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
        $post_processed = true;
        $problem = trim ( $_POST['new_problem'] );
	$d = "$epm_data/$user_dir/$problem";
	if ( $problem == '' )
	{
	    // User hit carriage return on empty
	    // field.
	    $problem = NULL;
	}
	elseif ( ! preg_match ( $epm_name_re,
	                        $problem ) )
	{
	    $errors[] =
	        "problem name $problem contains an" .
		" illegal character or does not" .
		" begin and end with a letter";
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
	    if ( ! @mkdir ( "$d", 0771 ) )
	    {
		$errors[] =
		    "trying to create $problem which" .
		    " already exists";
		$problem = NULL;
	    }
	    umask ( $m );
	}
    }
    elseif ( isset ( $_POST['goto_problem'] )
             &&
             isset ( $_POST['selected_problem'] ) )
    {
        $post_processed = true;
        $problem = trim ( $_POST['selected_problem'] );
	if ( ! preg_match
	           ( $epm_name_re , $problem ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	else
	if ( ! is_dir
	         ( "$epm_data/$user_dir/$problem" ) )
	{
	    $errors[] =
	        "trying to select problem that no" .
		" longer exists: $problem";
	    $problem = NULL;
	}
    }
    elseif ( isset ( $_POST['delete_problem'] ) )
    {
        $post_processed = true;
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
        $post_processed = true;
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
        $post_processed = true;
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
	{
	    flock ( $lock_desc, LOCK_UN );
	    fclose ( $lock_desc );
	    $lock_desc = NULL;
	}
    }
    register_shutdown_function ( 'shutdown' );

    if ( isset ( $problem ) )
    {
	$probdir = "users/$uid/$problem";
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

    // Set $problems to list of available problems.
    //
    $problems = [];

    $subdirs = @scandir ( "$epm_data/$user_dir" );
    if ( $subdirs === false )
         ERROR ( "cannot open $user_dir" );
    foreach ( $subdirs as $subdir )
    {
	if ( preg_match ( $epm_name_re, $subdir ) )
	    $problems[] = $subdir;
    }

    // Return DISPLAYABLE problem file names, sorted
    // most recent first, that are in the given
    // directory.  Only the last component of each
    // name is returned.  The directory is relative
    // to $epm_data.
    //
    function problem_file_names ( $dir )
    {
        global $epm_data, $display_file_type,
	       $epm_filename_re;

	clearstatcache();
	$map = [];

	foreach ( scandir ( "$epm_data/$dir" )
	          as $fname )
	{
	    if ( ! preg_match
	               ( $epm_filename_re, $fname ) )
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

    // Get information about a file.  Return
    //
    // 	    list ( FILE-EXTENSION,
    //		   FILE-TYPE,
    //		   FILE-DISPLAY,
    //		   FILE-COMMENT )
    //
    // where FILE-TYPE is the $display_file_type of
    // the FILE-EXTENSION, FILE-DISPLAY is true iff
    // the file is UTF-8 and can be displayed in a
    // <pre>FILE-CONTENTS</pre>, and FILE-COMMENT
    // is a comment to be printed at the end of the
    // file line in the file table of contents.
    //
    // For example, an empty file has FILE-DISPLAY
    // false and FILE-COMMENT `(Empty)'.
    //
    // If FILE-DISPLAY is set to true, the element
    //
    //	  [$count, $fname, FILE-CONTENTS]
    //
    // is appended to $display_list.  Note that
    // here FILE-CONTENTS is truncated if necessary
    // to a maximum of $epm_file_maxsize bytes.
    //
    // FILE-DISPLAY is set false if the file is a UTF8
    // file with more than 200 lines.
    //
    function file_info
            ( $dir, $fname, $count, & $display_list )
    {
        global $epm_data, $display_file_type,
	       $display_file_map, $epm_file_maxsize;

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
	     && isset ( $fsize ) )
	{
	    $fcontents = "$fname contents could"
		       . " not be read\n";
	    $fcontents = @file_get_contents
	        ( $f, false, NULL, 0,
		  $epm_file_maxsize );
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
			  . rtrim ( $fcontents )
			  . '}';
	    elseif ( isset ( $flines ) )
	    {
		$fcomment = "($flines Lines)";
		if ( $flines < 200 )
		{
		    $display_list[] =
			[$count, $fname, $fcontents];
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

	return [ $fext, $ftype, $fdisplay, $fcomment ];
    }

    // Data Set by GET and POST Requests:
    //
    $make_ftest = NULL;
        // Set to ask if $make_ftest .ftest file should
	// be made from .fout file.

    // Remaining POSTs require $problem and $probdir
    // to be non-NULL.
    //
    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( ! isset ( $probdir ) )
	/* Do Nothing */;
    elseif ( isset ( $_POST['delete_files'] ) )
    {
        $post_processed = true;

        // Process file deletions for other posts.
	//
	$files = $_POST['delete_files'];
	$files = explode ( ',', $files );
	$fnames = problem_file_names ( $probdir );
	foreach ( $files as $f )
	{
	    if ( $f == '' ) continue;
	    if ( ! in_array ( $f, $fnames ) )
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
        $post_processed = true;
        $m = $_POST['make'];
	if ( ! preg_match ( '/^([^:]+):([^:]+)$/', $m,
	                    $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$src = $matches[1];
	$des = $matches[2];
		 	    
	if ( ! in_array
		 ( $src, problem_file_names
			   ( $probdir ) ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	if ( preg_match ( '/\.ftest$/', $des ) )
	    $make_ftest = $des;
	else
	    start_make_file
		( $src, $des, NULL /* no condition */,
		  true, "$probdir/+work+",
		  NULL, NULL /* no upload */,
		  $errors );
    }
    elseif ( isset ( $_POST['make_ftest_yes'] ) )
    {
        $post_processed = true;
        $m = $_POST['make_ftest_yes'];
	$base = pathinfo ( $m, PATHINFO_FILENAME );
	$ext = pathinfo ( $m, PATHINFO_EXTENSION );
	if ( $ext != 'ftest' )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$src = "$base.fout";
	$des = "$base.ftest";
		 	    
	if ( ! in_array
		 ( $src, problem_file_names
			   ( $probdir ) ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	start_make_file
	    ( $src, $des, NULL /* no condition */,
	      true, "$probdir/+work+",
	      NULL, NULL /* no upload */,
	      $errors );
    }
    elseif ( isset ( $_POST['upload'] ) )
    {
        $post_processed = true;
	if ( isset ( $_FILES['uploaded_file']
	                     ['name'] ) )
	{
	    $upload_info = $_FILES['uploaded_file'];

	    process_upload
		( $upload_info, "$probdir/+work+",
		  $warnings, $errors );
	}
	else
	    $errors[] = "no file selected for upload";
    }
    elseif ( isset ( $_POST['run'] ) )
    {
        $post_processed = true;
        $f = $_POST['run'];
	if ( ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
		 	    
	if ( ! in_array
		 ( $f, problem_file_names
			   ( $probdir ) ) )
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
        $post_processed = true;

	/* Do Nothing */
    }
    elseif ( isset ( $_POST['update'] ) )
    {
        $post_processed = true;
	$count = 0;
	while ( true )
	{
	    $r = update_work_results ( 0 );
	    if ( $r !== true || $count == 50 )
	    			// 5 seconds
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
	    usleep ( 100000 ); // 0.1 second
	    $count += 1;
	}
    }

    if ( ! $post_processed && $method == 'POST' )
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( isset ( $_SESSION['EPM_WORK']['CONTROL'] )
         &&
	 update_work_results() !== true )
        finish_make_file ( $warnings, $errors );

?>

<html>
<style>
    @media screen and ( max-width: 1281px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	}
    }
    @media screen and ( min-width: 1280px ) {
	:root {
	    --font-size: 16px;
	    --large-font-size: 20px;
	}
	div.left {
	    width: 1280px;
	    font-size: var(--font-size);
	    overflow: scroll;
	}
    }
    .indented {
	margin-left: 20px;
    }
    .no-margin {
	margin: 0 0 0 0;
    }
    h5 {
        font-size: var(--large-font-size);
	margin: 0 0 0 0;
	display:inline;
    }
    pre, b, button, input, select, u {
	display:inline;
        font-size: var(--font-size);
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    pre.red {
        color: #BB0000;
    }
    div.manage {
	background-color: #96F9F3;
    }
    pre.problem {
        color: #CC00FF;
        font-size: 20px;
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
    div.file-name {
	background-color: #B3E6FF;
    }
    div.file-contents {
	background-color: #C0FFC0;
    }
    td.time {
	color: #99003D;
	text-align: right;
    }

</style>

<script>
    function NEW_WINDOW ( page, filename ) {
	var src = '/page/' + page + '?filename='
		+ encodeURIComponent ( filename );
	var x = screen.width - 1280;
	var y = screen.height - 800;
	window.open
	    ( src, filename,
	      'height=800px,width=1280px,' +
	      'screenX=' + x + 'px,' +
	      'screenY=' + y + 'px' );
    }

    function TOGGLE_BODY ( name, thing )
    {
	var BUTTON = document.getElementById
		( name + '_button' );
	var MARK = document.getElementById
		( name + '_mark' );
	var BODY = document.getElementById
		( name + '_body' );
	if ( BODY.hidden )
	{
	    MARK.innerHTML = "&uarr;";
	    BUTTON.title = "Hide " + thing;
	    BODY.hidden = false;
	}
	else
	{
	    MARK.innerHTML = "&darr;";
	    BUTTON.title = "Show " + thing;
	    BODY.hidden = true;
	}
    }

    var DELETE_LIST = [];

    function TOGGLE_DELETE ( count, fname )
    {
	var FILE = document.getElementById
	               ("file" + count);
	var BUTTON = document.getElementById
	               ("button" + count);
	var MARK = document.getElementById
	               ("mark" + count);
        let i = DELETE_LIST.findIndex
	            ( x => x == fname );
	if ( i == -1 )
	{
	    DELETE_LIST.push ( fname );
	    MARK.innerHTML = "+";
	    BUTTON.title = "Cancel " + fname +
	                   " Deletion Mark";
	    FILE.style = 'text-decoration:line-through';

	}
	else
	{
	    DELETE_LIST.splice ( i, 1 );
	    MARK.innerHTML = "&Chi;";
	    BUTTON.title = "Mark " + fname +
	                   " For Deletion";
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
    else if ( $make_ftest )
    {
	echo "<div style='background-color:#F5F81A'>" .
	     PHP_EOL;
	echo "<form method='POST'" .
	     " style='display:inline'" .
	     " action=problem.php>";
	echo "Do you really want to make $make_ftest" .
	     " (this will force score to be" .
	     " `Completely Correct')?";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='make_ftest_yes'" .
	     " value='$make_ftest'>" .
	     "YES</button>";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='make_ftest_no'" .
	     " value='$make_ftest'>" .
	     "NO</button>";
	echo "</form></div>" . PHP_EOL;
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
    $problem_page_help = HELP ( 'problem-page' );
    echo <<<EOT
    <div class='manage'>
    <form action='problem.php' method='POST'
          style='margin:0 0 1vh 0'>
    <table style='width:100%'>
    <tr>
    <td style='width:30%'>
    <h5>User:</h5> <input type='submit' value='$email'
		    formaction='user.php'
		    formmethod='GET'
                    title='click to see user profile'>
    </td>
    <td style='width:35%'>
    <h5>Current Problem:</h5>&nbsp;
    <pre class='problem'>$current_problem</pre></b>
    </td>
    <td style='width:30%'>
EOT;
    if ( isset ( $problem ) )
        echo "<button type='submit'" .
	     " name='delete_problem'" .
	     " value='$problem'>" .
	     "Delete Current Problem</button>";
    echo <<<EOT
    </td>
    <td style='width:5%;text-align:right'>
    {$problem_page_help}
    </td>
    </tr>
EOT;

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
	    echo <<<EOT
	         <td><h5>Go To:</h5>
		 <button type='submit'
			 formaction='run.php'
			 formmethod='GET'>
			 Run Page</button>
		 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	         <button type='submit'
			formaction='option.php'
			formmethod='GET'>
			Option Page</button>
		 </td>
EOT;
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
	$current_problem_files_help =
	    HELP ( 'current-problem-files' );
	$show_map = [];
        echo <<<EOT
	<div class='problem_display'>
	<table style='width:100%'>
	<tr>
	<td style='width:90%'>
	<button type='button'
		id='problems_button'
		onclick='TOGGLE_BODY
		     ("problems",
		      "Current Problem Files")'
		title='Hide Current Problem Files'>
		<pre id='problems_mark'>&uarr;</pre>
		</button>
	<h5>Current Problem Files (most recent first):</h5>
	</td>
	<td style='width:10%;text-align:right'>
	{$current_problem_files_help}
	</td>
	</tr>
	</table>
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
	        $show_map[$fname] = $count;
	        $fpage = $display_file_map[$ftype];
	        echo <<<EOT
		    <button type='button'
		       id='show$count'
		       title='Show $fname at Right'
		       onclick='NEW_WINDOW
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
	    {
	        $show_map[$fname] = $count;
		echo <<<EOT
		    <td><button type='button'
		         id='file{$count}_button'
			 onclick='TOGGLE_BODY
			     ("file$count",
			      "$fname Below")'
			 title='Show $fname Below'>
		    <pre id='file{$count}_mark'
		        >&darr;</pre>
		    </td>
EOT;
	    }
	    else
	        echo "<td></td>";

	    echo <<<EOT
		<td><button type='button'
		     id='button$count'
		     onclick='TOGGLE_DELETE
			($count, "$fname")'
		     title='Mark $fname For Deletion'>
		<pre id='mark$count'>&Chi;</pre>
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
	    elseif ( $fext == 'sin' )
	    {
		echo "<td><button type='submit'" .
		     " name='make'" .
		     " title='Make $fbase.dout" .
		     " from $fname'" .
		     " value='$fname:$fbase.dout'>" .
		     "&rArr;.dout</button></td>";
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
		     " style='background-color:red'" .
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
	    $result = $_SESSION['EPM_WORK']['RESULT'];
	    $kept = $_SESSION['EPM_WORK']['KEPT'];
	    if (    is_array ( $result )
	         && $result == ['D',0] )
	        $r = 'commands succeeded: ';
	    else
	        $r = 'commands failed: ';
	    if ( count ( $kept ) == 1 )
	        $r .= '1 file kept';
	    else
		$r .= count ( $kept ) . ' files kept';
	    echo "<div class='command_display'>" .
		 PHP_EOL;
	    get_commands_display ( $display );
	    echo <<<EOT
	    <button type='button'
	    	    id='commands_button'
		    onclick='TOGGLE_BODY
			 ("commands",
			  "Commands Last Executed")'
		    title='Show Commands Last Executed'>
		    <pre id='commands_mark'>&darr;</pre>
		    </button>
	    <h5>Commands Last Executed:</h5>&nbsp;
	    <pre>($r)</pre>
	    <div id='commands_body' hidden>
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
		    id='working_button'
		    onclick='TOGGLE_BODY
			 ("working",
			  "Current Working Files")'
		    title='Show Current Working Files'>
		    <pre id='working_mark'>&darr;</pre>
		    </button>
		<h5>Working Files of Last Executed
		    Commands
		    (most recent first):</h5>
		<div id='working_body' hidden>
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

		    if ( isset ( $display_file_map[$ftype] )
		         &&
			 $fcomment != '(Empty)' )
		    {
			$show_map[$fname] = $count;
			$fpage = $display_file_map[$ftype];
			echo <<<EOT
			    <button type='button'
			       id='show$count'
			       title='Show $fname at Right'
			       onclick='NEW_WINDOW
				  ("$fpage","+work+/$fname")'>
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
		    {
			$show_map[$fname] = $count;
			echo <<<EOT
			    <td><button type='button'
			         id='file{$count}_button'
				 onclick='TOGGLE_BODY
				     ("file$count",
				      "$fname Below")'
				 title='Show $fname Below'>
			    <pre id='file{$count}_mark'
			        >&darr;</pre>
			    </button></td>
EOT;
		    }
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
		<div hidden
		     id='file{$count}_body'
		     class='file-name'>
		<h5>$fname:</h5><br>
		<div class='file-contents'>
		<div class='indented'>
		<pre>$fcontents</pre>
		</div></div></div>
EOT;
	    }
	}

	if ( isset ( $_SESSION['EPM_WORK']['SHOW'] ) )
	{
	    $show_files = $_SESSION['EPM_WORK']['SHOW'];
	    $files = [];

	    foreach ( $show_files as $fname )
	    {
		$fname = pathinfo
		    ( $fname, PATHINFO_BASENAME );
	        if ( isset ( $show_map[$fname] ) )
		    $files[] = $fname;
	    }
	    if ( count ( $files ) > 0 )
	    {
	        $c = $show_map[$files[0]];
		echo "<script>document" .
		     ".getElementById('show$c')" .
		     ".click();" .
		     "</script>";
	    }
	    if ( count ( $files ) > 1 )
	    {
	        $c = $show_map[$files[1]];
		echo "<script>document" .
		     ".getElementById" .
		     "('file{$c}_button')" .
		     ".click();" .
		     "</script>";
	    }
	}
    }
?>

</div>

<form action='problem.php' method='POST' id='reload'>
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
	if ( isset ( $_SESSION['EPM_WORK']['RESULT'] ) )
	{
	    $r = $_SESSION['EPM_WORK']['RESULT'];
	    if ( $r === true )
		echo "REQUEST_UPDATE();" . PHP_EOL;
	    if ( ! is_array ( $r ) || $r != ['D',0] )
	    {
		 echo "document.getElementById" .
		      "('commands_button').click();";
		 echo "document.getElementById" .
		      "('working_button').click();";
	    }
	}
    ?>

</script>

</body>
</html>
