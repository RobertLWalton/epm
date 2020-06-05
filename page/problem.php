<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Jun  5 03:02:56 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Selects EPM user problem.  Displays and uploads
    // problem files.

    if ( $_SERVER['REQUEST_METHOD'] == 'GET'
         &&
	 ! isset ( $_SERVER['id'] ) )
        $epm_page_type = '+init+';
    else
        $epm_page_type = '+problem+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // if ( ! isset ( $_POST['xhttp'] ) )
    //     require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_REQUEST['problem'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to problem.php" );
    elseif ( ! isset ( $_SESSION['EPM_UID'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to problem.php" );
    elseif ( ! isset ( $_SESSION['EPM_EMAIL'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to problem.php" );

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];
    $problem = $_REQUEST['problem'];
    $probdir = "users/$uid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
    {
	// Some other session deleted the problem;
	// let project.php deal with it.
	//
	header ( "Location: /page/project.php" );
	exit;
    }

    require "$epm_home/include/epm_make.php";
    if ( $epm_page_type == '+init+' )
    {
	$work = [];
	$run  = [];
        require "$epm_home/include/epm_random.php";
        $_SESSION['EPM_ID_GEN'][$problem] =
	    init_id_gen();
	$ID = bin2hex
	    ( $_SESSION['EPM_ID_GEN'][$problem][0] );
    }

    // The $_SESSION state particular to this page is:
    //
    //	   $work = & $_SESSION['EPM_WORK'][$problem]
    //		// set when epm_make.php loaded.
    //	   $run = & $_SESSION['EPM_RUN'][$problem]
    //		// set when epm_make.php loaded.

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
	    $f = "$dir/$fname";
	    $mtime = @filemtime ( "$epm_data/$f" );
	    if ( $mtime === false )
	    {
	        if ( is_link ( "$epm_data/$f" ) )
		    WARN ( "dangling link $f" );
		else
		    WARN ( "stat failed for $f" );
	    }
	    else $map[$fname] = $mtime;
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
    //		   FILE-SHOW,
    //		   FILE-COMMENT )
    //
    // where FILE-TYPE is the $display_file_type of
    // the FILE-EXTENSION, FILE-DISPLAY if true iff
    //
    // 	  the file is UTF8
    //	  the file has <= $max_display_lines lines
    //	  the file is not displayed in FILE-COMMENT
    //
    // FILE-SHOW is true iff
    //
    //	  the file is UTF8 or PDF
    //    the file has >= $min_display_lines
    //	  the file is not displayed in FILE-COMMENT
    //
    // and FILE-COMMENT is:
    //
    //	   (Empty) iff the file is empty
    //     {FILE-CONTENTS} iff the file has 1 line
    //         that after being right trimmed has
    //	       <= 32 characters
    //	   (Lines ###) iff the above do not apply and
    //         the file is UTF8 with ### lines
    //
    //     If in addition the file is linked from
    //     PROJECT, `(Linked from PROJECT)' is appended.
    //
    // If FILE-DISPLAY is set to true, the element
    //
    //	  [$count, $fname, FILE-CONTENTS]
    //
    // is appended to $display_list.
    //
    // Note that here FILE-CONTENTS is truncated to a
    // maximum of $epm_file_maxsize bytes.
    //
    $max_display_lines = 40;
    $min_display_lines = 10;
    function file_info
            ( $dir, $fname, $count, & $display_list )
    {
        global $epm_data, $problem, $display_file_type,
	       $display_file_map, $epm_file_maxsize,
	       $max_display_lines, $min_display_lines;

	$fext = pathinfo ( $fname, 
			   PATHINFO_EXTENSION );
	$ftype = $display_file_type[$fext];

	$f = "$epm_data/$dir/$fname";
	$fsize = NULL;
	$fsize = @filesize ( $f );
	$fcontents = NULL;
	$flines = NULL;
	$fdisplay = false;
	$fshow = false;
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
		 &&    strlen ( rtrim ( $fcontents ) )
		    <= 32 )
		$fcomment = '{'
			  . rtrim ( $fcontents )
			  . '}';
	    elseif ( isset ( $flines ) )
	    {
		$fcomment = "($flines Lines)";
		if ( $flines <= $max_display_lines )
		{
		    $display_list[] =
			[$count, $fname, $fcontents];
		    $fdisplay = true;
		}
		if ( $flines >= $min_display_lines )
		    $fshow = true;
	    }
	    elseif ( isset ( $fsize ) )
		$fcomment = "($fsize Bytes)";
	    else
		$fcomment = "(Has Undetermined Size)";
	}
	elseif ( isset ( $display_file_map
			       [$ftype] ) )
	{
	    if ( isset ( $fsize ) )
		$fcomment = "($fsize Bytes)";
	    else
		$fcomment = "(Has Undetermined Size)";
	    $fshow = true;
	}
	else
	{
	    if ( is_link ( $f ) )
		$fcomment = "Link to $ftype";
	    else 
		$fcomment = $ftype;
	}

	if ( is_link ( $f ) )
	{
	    $r = @readlink ( $f );
	    $re = "#^\.\./\.\./\.\./projects/"
	        . "([^/]+)/$problem/$fname\$#";
	    if (    $r !== false
	         && preg_match ( $re, $r, $matches ) )
	        $fcomment .=
		    " (Linked from {$matches[1]})";
	}

	return [ $fext, $ftype,
	         $fdisplay, $fshow, $fcomment ];
    }

    // Data Set by GET and POST Requests:
    //
    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $make_ftest = NULL;
        // Set to ask if $make_ftest .ftest file should
	// be made from .fout file.
    $delete_problem = false;
        // True to ask whether current problem is to be
	// deleted.

    // Process POST requests.
    //
    if ( $epm_method != 'POST' ) /* Do Nothing */;
    elseif ( isset ( $_POST['delete_problem'] ) )
    {
	$prob = $_POST['delete_problem'];
	if ( $prob != $problem )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$delete_problem = true;
    }
    elseif ( isset ( $_POST['delete_problem_yes'] ) )
    {
	$prob = $_POST['delete_problem_yes'];
	if ( $prob != $problem )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$work = [];
	$run = [];
	exec ( "rm -rf $epm_data/$probdir" );
	echo <<<EOT
	<html><body><script>
	window.close();
	</script></body></html>
EOT;
	exit;
    }
    elseif ( isset ( $_POST['delete_problem_no'] ) )
    {
	$prob = $_POST['delete_problem_no'];
	if ( $prob != $problem )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
    }
    elseif ( isset ( $_POST['delete_files'] ) )
    {
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
    elseif ( isset ( $_POST['make'] ) )
    {
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
	{
	    $d = "$probdir/+parent+";
	    $lock = NULL;
	    if ( is_dir ( "$epm_data/$d" ) )
	        $lock = LOCK ( $d, LOCK_SH );
	    start_make_file
		( $src, $des, NULL /* no condition */,
		  true, $lock, "$probdir/+work+",
		  NULL, NULL /* no upload */,
		  $errors );
	}
    }
    elseif ( isset ( $_POST['make_ftest_yes'] ) )
    {
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
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_make_file
	    ( $src, $des, NULL /* no condition */,
	      true, $lock, "$probdir/+work+",
	      NULL, NULL /* no upload */,
	      $errors );
    }
    elseif ( isset ( $_POST['make_ftest_no'] ) )
    {
	// Do nothing.
    }
    elseif ( isset ( $_POST['upload'] ) )
    {
	if ( isset ( $_FILES['uploaded_file']
	                     ['name'] ) )
	{
	    $d = "$probdir/+parent+";
	    $lock = NULL;
	    if ( is_dir ( "$epm_data/$d" ) )
		$lock = LOCK ( $d, LOCK_SH );

	    process_upload
		( $_FILES['uploaded_file'],
		  $lock, "$probdir/+work+",
		  $errors );
	}
	else
	    $errors[] = "no file selected for upload";
    }
    elseif ( isset ( $_POST['run'] ) )
    {
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
        if ( isset ( $run['RESULT'] ) )
	{
	    header ( "Location: /page/run.php?" .
	             "id=$ID&problem=$problem" );
	    exit;
	}
    }
    elseif ( isset ( $_POST['reload'] )
             &&
	     isset ( $work['BASE'] ) )
    {
	/* Do Nothing */
    }
    elseif ( isset ( $_POST['update'] ) )
    {
	$count = 0;
	echo "ID $ID\n";
	while ( true )
	{
	    $r = update_work_results ( 0 );
	    if ( $r !== true || $count == 50 )
	    			// 5 seconds
	    {
	        echo "RELOAD\n";
		exit;
	    }
	    $r = update_workmap();
	    if ( count ( $r ) > 0 )
	    {
		$workmap = & $work['MAP'];
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
    else
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( isset ( $work['CONTROL'] )
         &&
	 update_work_results() !== true )
        finish_make_file ( $warnings, $errors );

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.problem_display {
	background-color: var(--bg-tan);
    }
    div.command_display {
	background-color: var(--bg-green);
    }
    div.work_display {
	background-color: var(--bg-violet);
    }
    div.file-name {
	background-color: var(--bg-blue);
    }
    div.file-contents {
	background-color: var(--bg-green);
    }
    td.time {
	color: var(--hl-purple);
	text-align: right;
	padding-left: var(--indent);
    }
    pre.error-message {
	color: var(--hl-red);
    }

</style>

<script>
    var show_window = null;
    var problem = '<?php echo $problem; ?>';

    function NEW_WINDOW ( page, filename ) {
	var src = '/page/' + page
	        + '?problem='
	        + encodeURIComponent ( problem )
		+ '&filename='
		+ encodeURIComponent ( filename );
	if ( show_window ) show_window.close();
	var x = screen.width - 1280;
	var y = screen.height - 800;
	show_window = window.open
	    ( src, 'epm-view',
	      'height=800px,width=1280px,' +
	      'screenX=' + x + 'px,' +
	      'screenY=' + y + 'px' );
	// show_window.focus() does not work.
    }

    function TOGGLE_BODY ( name, thing )
    {
	var BUTTON = document.getElementById
		( name + '_button' );
	var MARK = document.getElementById
		( name + '_mark' );
	var BODY = document.getElementById
		( name + '_body' );
	if ( BODY.style.display == 'none' )
	{
	    MARK.innerHTML = "&uarr;";
	    BUTTON.title = "Hide " + thing;
	    BODY.style.display = 'block';
	}
	else
	{
	    MARK.innerHTML = "&darr;";
	    BUTTON.title = "Show " + thing;
	    BODY.style.display = 'none';
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
	    BUTTON.title = "Cancel " + fname +
	                   " Deletion Mark";
	    FILE.style = 'text-decoration:line-through';

	}
	else
	{
	    DELETE_LIST.splice ( i, 1 );
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

<?php 

    if ( $delete_problem )
    {
        echo <<<EOT
	<div class='notices'>
	<form method='POST'
	      action=problem.php>
	<input type='hidden'
	       name= 'problem' value='$problem'>
	<input type='hidden' name='id' value='$ID'>
	Do you really want to delete current
	       problem $problem?
	<pre>   </pre>
	<button type='submit'
	        name='delete_problem_yes'
	        value='$problem'>
	     YES</button>
	<pre>   </pre>
	<button type='submit'
	        name='delete_problem_no'
	        value='$problem'>
	     NO</button>
	</form></div>
EOT;
    }
    else if ( $make_ftest )
    {
        $fout = pathinfo ( $make_ftest,
	                   PATHINFO_FILENAME )
	      . ".fout";
	echo <<<EOT
	<div class='notices'>
	<form method='POST'
	      action=problem.php>
	<input type='hidden'
	       name= 'problem' value='$problem'>
	<input type='hidden' name='id' value='$ID'>
	Do you really want to copy $fout to
	       $make_ftest (this will force score
	       to be `Completely Correct')?
	<pre>   </pre>
	<button type='submit'
	        name='make_ftest_yes'
	        value='$make_ftest'>
	     YES</button>
	<pre>   </pre>
	<button type='submit'
	        name='make_ftest_no'
	        value='$make_ftest'>
	     NO</button>
	</form></div>
EOT;
    }
    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>";
	echo "<strong>Errors:</strong>";
	echo "<div class='indented'>";
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<strong>Warnings:</strong>";
	echo "<div class='indented'>";
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }

    $problem_page_help = HELP ( 'problem-page' );
    echo <<<EOT
    <div class='manage' id='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:&nbsp;$email</strong>
    </td><td>

    <strong>Current Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre></b>
    <form action='problem.php' method='POST'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <button type='submit'
	    name='delete_problem'
	    value='$problem'
	    title='Delete Current Problem'>
    Delete</button>
    </form>

    </td><td>
    <strong>Go To</strong>
    <form method='GET'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <button type='submit'
	    formaction='run.php'>
	    Run</button>
    <button type='submit'
	    formaction='option.php'>
	    Option</button>
    </form>
    <strong>Page</strong>
    </td><td style='text-align:right'>
    $problem_page_help</td>
    </tr></table>
    </div>
EOT;

    $count = 0;
    $show_map = [];
	// $show_map[$fname] => id
	// maps file name last component to the
	// button id that identifies the button
	// to click to show the file.
    $display_list = [];

    if ( isset ( $work['DIR'] ) )
    {
	$workdir = $work['DIR'];
	$result = $work['RESULT'];
	$kept = $work['KEPT'];
	if (    is_array ( $result )
	     && $result == ['D',0] )
	    $r = 'commands succeeded: ';
	elseif ( $result === true )
	    $r = 'commands still running';
		// Should never happen as 'update'
		// POST exits above.
	else
	    $r = 'commands failed: ';
	if ( $result === true )
	    /* Do Nothing */;
	elseif ( count ( $kept ) == 1 )
	    $r .= '1 file kept';
	else
	    $r .= count ( $kept ) . ' files kept';
	echo "<div class='command_display'>";
	get_commands_display ( $display );
	$commands_help =
	    HELP ( 'problem-commands' );
	echo <<<EOT
	<table style='width:100%'><tr>
	<td>
	<button type='button'
		id='commands_button'
		onclick='TOGGLE_BODY
		     ("commands",
		      "Commands Last Executed")'
		title='Show Commands Last Executed'>
		<pre id='commands_mark'>&darr;</pre>
		</button>
	<strong>Commands Last Executed:</strong>
	&nbsp;
	<pre>($r)</pre>
	</td><td style='text-align:right'>
	$commands_help</td>
	</tr></table>
	<div id='commands_body'
	     style='display:none'>
EOT;
	echo "<div class='indented'>";
	echo $display;
	echo "</div>";
	if ( count ( $kept ) > 0 )
	{
	    echo "<strong>Kept:</strong>";
	    echo "<div class='indented'>";
	    foreach ( $kept as $e )
		echo "<pre>$e</pre><br>";
	    echo "<br></div>";
	}
	echo "</div>";

	$working_files =
	    problem_file_names( $workdir );

	if ( count ( $working_files ) > 0 )
	{
	    $working_help =
		HELP ( 'problem-working' );
	    echo <<<EOT
	    <div class='work_display'
		 id='work-display'>
	    <table style='width:100%'><tr>
	    <td>
	    <button type='button'
		id='working_button'
		onclick='TOGGLE_BODY
		     ("working",
		      "Current Working Files")'
		title='Show Current Working Files'>
		<pre id='working_mark'>&darr;</pre>
		</button>
	    <strong>Working Files of Last Executed
		Commands
		(most recent first):</strong>
	    </td><td style='text-align:right'>
	    $working_help</td>
	    </tr></table>
	    <div id='working_body'
		 style='display:none'>
	    <table style='display:block'>
EOT;

	    foreach ( $working_files as $fname )
	    {
		$f = "$epm_data/$workdir/$fname";
		if ( is_link ( $f ) )
		    continue;

		++ $count;
		echo "<tr>";
		echo "<td" .
		     " style='text-align:right'>";
		list ( $fext, $ftype, $fdisplay,
		       $fshow, $fcomment )
		    = file_info ( $workdir, $fname,
				  $count,
				  $display_list );

		if ( $fshow )
		{
		    $show_map[$fname] =
			"show$count";
		    $fpage =
			$display_file_map[$ftype];
		    echo <<<EOT
			<button type='button'
			   id='show$count'
			   title=
			     'Show $fname at Right'
			   onclick='NEW_WINDOW
			      ("$fpage",
			       "+work+/$fname")'>
			 <pre id='file$count'
			     >$fname</pre>
			 </button></td>
EOT;
		}
		else
		{
		    unset ( $show_map[$fname] );
			// $fname is a working file
			// so if an older version
			// is a current file we
			// do not want to show the
			// older version.
		    echo <<<EOT
			<pre id='file$count'
			    >$fname</pre>
			</td>
EOT;
		}

		if ( $fdisplay )
		{
		    $show_map[$fname] =
			"file{$count}_button";
		    echo <<<EOT
			<td><button type='button'
			     id=
			       'file{$count}_button'
			     onclick='TOGGLE_BODY
				 ("file$count",
				  "$fname Below")'
			     title=
			       'Show $fname Below'>
			<pre id='file{$count}_mark'
			    >&darr;</pre>
			</button></td>
EOT;
		}
		else
		    echo "<td></td>";

		echo "<td colspan='100'>" .
		     "<pre>$fcomment</pre></td>";
		echo "</tr>";
	    }
	    echo "</table></div>";
	    echo "</div>";
	}
    }

    $current_problem_files_help =
	HELP ( 'problem-marks' );

    echo <<<EOT
    <div class='problem_display'
	 id='problem-display'>
    <table style='width:100%'>
    <tr>
    <td>
    <button type='button'
	    id='problems_button'
	    onclick='TOGGLE_BODY
		 ("problems",
		  "Current Problem Files")'
	    title='Hide Current Problem Files'>
	    <pre id='problems_mark'>&uarr;</pre>
	    </button>
    <strong>Current Problem Files
	(most recent first):</strong>
    </td><td>
    <form action='problem.php' method='POST'
	  enctype='multipart/form-data'
	  id='upload-form'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <label>
    <strong>Upload a File:</strong>
    <input type='hidden' name='MAX_FILE_SIZE'
	   value='$epm_upload_maxsize'>
    <input type='hidden' name='upload' value='yes'>
    <input type='file' name='uploaded_file'
	   onchange='document.getElementById
		      ( "upload-form" ).submit()'
	   title='File to Upload'>
    </label>
    </form>
    <pre>    </pre>
    <form action='problem.php' method='POST'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <input id='delete_files'
	   name='delete_files' value=''
	   type='hidden'>
    <input type="submit" name="execute_deletes"
	   value=
	     "Delete Over-Struck Files">
    </form>
    </td><td style='text-align:right'>
    $current_problem_files_help</td>
    </tr>
    </table>
    <div id='problems_body'>
    <form action='problem.php' method='POST'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
EOT;
    function MAKE ( $fbase, $sext, $dext )
    {
	echo "<td><button type='submit'" .
	     " name='make'" .
	     " title='Make $fbase.$dext" .
	     " from $fbase.$sext'" .
	     " value='$fbase.$sext:$fbase.$dext'>" .
	     "&rArr;.$dext</button></td>";
    }
    echo "<table style='display:block'>";
    foreach ( problem_file_names( $probdir )
	      as $fname )
    {
	$count += 1;
	echo "<tr>";
	echo "<td style='text-align:right'>";
	list ( $fext, $ftype,
	       $fdisplay, $fshow, $fcomment )
	    = file_info ( $probdir, $fname, $count,
			  $display_list );
	$fbase = pathinfo ( $fname, 
			    PATHINFO_FILENAME );

	if ( $fshow )
	{
	    $show_map[$fname] = "show$count";
	    $fpage = $display_file_map[$ftype];
	    echo <<<EOT
		<button type='button'
		   id='show$count'
		   title='Show $fname in Window at
			  Right'
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
	    $show_map[$fname] =
		"file{$count}_button";
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
	    MAKE ( $fbase, 'in', 'sin' );
	    MAKE ( $fbase, 'in', 'sout' );
	    MAKE ( $fbase, 'in', 'score' );
	}
	elseif ( $fext == 'sin' )
	    MAKE ( $fbase, 'sin', 'dout' );
	elseif ( $fext == 'sout' )
	{
	    MAKE ( $fbase, 'sout', 'fout' );
	    MAKE ( $fbase, 'sout', 'score' );
	}
	elseif ( $fext == 'fout' )
	{
	    MAKE ( $fbase, 'fout', 'score' );
	    echo "<td><button type='submit'" .
		 " style='background-color:" .
			 "var(--hl-orange)'" .
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
	echo "<td colspan='100'>" .
	     "<pre>$fcomment</pre></td>";
	echo "</tr>";
    }
    echo "</table></form></div></div>";

    if ( count ( $display_list ) > 0 )
    {
	foreach ( $display_list as $triple )
	{
	    list ( $count, $fname, $fcontents ) =
		$triple;
	    $fcontents = htmlspecialchars
		( $fcontents );
	    echo <<<EOT
	    <div style='display:none'
		 id='file{$count}_body'
		 class='file-name'>
	    <strong>$fname:</strong><br>
	    <div class='file-contents indented'>
	    <pre>$fcontents</pre>
	    </div></div>
EOT;
	}
    }

    if ( isset ( $work['SHOW'] ) )
    {
	$show_files = $work['SHOW'];
	$files = [];

	foreach ( $show_files as $fname )
	{
	    if ( isset ( $show_map[$fname] ) )
		$files[] = $fname;
	}
	if ( count ( $files ) > 0 )
	{
	    $id = $show_map[$files[0]];
	    echo "<script>document" .
		 ".getElementById('$id')" .
		 ".click();" .
		 "</script>";
	}
	if ( count ( $files ) > 1 )
	{
	    $id = $show_map[$files[1]];
	    echo "<script>document" .
		 ".getElementById('$id')" .
		 ".click();" .
		 "</script>";
	}
    }

    echo <<<EOT
    <form action='problem.php'
	  method='POST' id='reload'>
    <input type='hidden' name='id' id='reload-id'>
    <input type='hidden' name='problem' value='$problem'>
    <input type='hidden' name='reload' value='reload'>
    </form>
EOT;
?>

<script>
    var LOG = function(message) {};
    <?php if ( $epm_debug )
              echo "LOG = console.log;";
    ?>
    var ID = '<?php echo $ID; ?>';

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
    var reload_id =
        document.getElementById("reload-id");
    var manage = document.getElementById("manage");
    var work_display =
        document.getElementById("work-display");
    var problem_display =
        document.getElementById("problem-display");

    function PROCESS_RESPONSE ( response )
    {
        response = response.trim().split( "\n" );
	for ( i = 0; i < response.length; ++ i )
	{
	    let item = response[i].trim().split( ' ' );
	    if ( item.length == 0 ) continue;
	    if ( item[0] == '' )
	        continue;
	    else if ( item[0] == 'ID'
	              &&
		      item.length == 2 )
	    {
	        ID = item[1];
		continue;
	    }
	    else if ( item[0] == 'RELOAD'
	              &&
		      item.length == 1 )
	    {
		reload_id.value = ID;
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
	manage.style.display = 'none';
	work_display.style.display = 'none';
	problem_display.style.display = 'none';
	    // These keep buttons from being clicked
	    // while an xhttp response is pending,
	    // is the ID needs to be updated by the
	    // response before a button is pressed.

	let data = 'update=update&xhttp=yes&id=' + ID
	         + '&problem=' + problem;
	LOG ( 'xhttp sent: ' + data );
	xhttp.send ( data );
    }
    <?php
	if ( isset ( $problem )
	     &&
	     isset ( $work['RESULT'] ) )
	{
	    $r = $work['RESULT'];
	    if ( $r === true )
		echo "REQUEST_UPDATE();";
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
