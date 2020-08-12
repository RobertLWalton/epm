<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Aug 12 14:12:36 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Selects EPM user problem.  Displays and uploads
    // problem files.

    if ( $_SERVER['REQUEST_METHOD'] == 'GET'
         &&
	 ! isset ( $_GET['id'] ) )
        $epm_ID_init = true;
    $epm_page_type = '+problem+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_REQUEST['problem'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to problem.php" );

    $problem = $_REQUEST['problem'];
    $probdir = "accounts/$aid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
        exit ( "problem $problem no longer exists" );

    if ( ! isset ( $_SESSION['EPM_PROBLEM'] ) )
        $_SESSION['EPM_PROBLEM'] =
	    ['ORDER' => 'extension'];
    $order = & $_SESSION['EPM_PROBLEM']['ORDER'];

    require "$epm_home/include/epm_make.php";

    $parent = NULL;
    $d = "$probdir/+parent+";
    if ( is_link ( "$epm_data/$d" ) )
    {
	$t = @readlink ( "$epm_data/$d" );
	if ( $t === false )
	    ERROR ( "cannot read link $d" );
	$re = "#^\.\./\.\./\.\./projects/"
	    . "([^/]+)/$problem\$#";
	if ( ! preg_match ( $re, $t, $matches ) )
	    ERROR ( "link $d has bad target $t" );
	$parent = $matches[1];
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
    // to $epm_data.  Allow file names that are link
    // names only if $allow_links is true.
    //
    function problem_file_names
        ( $dir, $allow_links = true )
    {
        global $epm_data, $display_file_type,
	       $epm_filename_re, $order;

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
	    if ( ! $allow_links
	         &&
		 is_link ( "$epm_data/$f" ) )
	        continue;

	    switch ( $order )
	    {
	    case 'lexigraphic':
	        $value = $fname;
	        break;
	    case 'recent':
		$value = @filemtime ( "$epm_data/$f" );
		if ( $value === false )
		{
		    if ( is_link ( "$epm_data/$f" ) )
			WARN ( "dangling link $f" );
		    else
			WARN ( "stat failed for $f" );
		    continue 2;
		}
		break;
	    case 'extension':
	        $value = "$ext:$fname";
		}

	    $map[$fname] = $value;
	}
	$SORT = ( $order == 'recent' ? SORT_NUMERIC
	                             : SORT_STRING );
	arsort ( $map, $SORT );
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
    // FILE-LINKABLE is true iff
    //
    //	  the file has extension in $linkable_ext
    //    the file is not a link
    //    the file basename has the form *-PPPP
    //		(which includes -generate-PPPP, etc.)
    //
    // and FILE-COMMENT is:
    //
    //	   (Empty) iff the file is empty
    //     {FILE-CONTENTS} iff the file has 1 line
    //         that after being right trimmed has
    //	       <= 56 characters
    //	   (Lines ###) iff the above do not apply and
    //         the file is UTF8 with ### lines
    //
    //     If in addition the file is linked,
    //     `(Link to ...)' is appended, where ...
    //	   is a project name, local file name, or
    //     `default'.
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
        global $epm_data, $problem, $parent,
	       $display_file_type,
	       $display_file_map, $epm_file_maxsize,
	       $epm_filename_re, $linkable_ext,
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
	$flinkable = false;
	if (    $ftype == 'utf8'
	     && isset ( $fsize ) )
	{
	    $fcontents = @file_get_contents
	        ( $f, false, NULL, 0,
		  $epm_file_maxsize );
	    if ( $fcontents === false )
		$fcontents = "$fname contents could"
			   . " not be read";
	    $fexplode = explode ( "\n", $fcontents );
	    $flines = count ( $fexplode );
	    if ( $fexplode[$flines-1] == '' )
	        -- $flines;
	}
	if ( isset ( $fsize ) && $fsize == 0 )
	    $fcomment = '(Empty)';
	elseif ( $ftype == 'utf8' )
	{
	    if (    $flines <= 1
		 &&    strlen ( rtrim ( $fcontents ) )
		    <= 56 )
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
	    $fcomment = $ftype;


	if ( is_link ( $f ) )
	{
	    $t = @readlink ( $f );
	    if ( $t === false )
		ERROR ( "cannot read link" .
			" $dir/$fname" );
	    if ( $t == "+parent+/$fname" )
	    {
	        if ( ! isset ( $parent ) )
		    ERROR ( "bad link $t" );
	        $fcomment .=
		    " (Link to $parent project)";
	    }
	    elseif
	        ( $t == "+parent+/+sources+/$fname" )
	    {
	        if ( ! isset ( $parent ) )
		    ERROR ( "bad link $t" );
	        $fcomment .=
		    " (Link to $parent project" .
		    " sources)";
	    }
	    elseif ( preg_match ( $epm_filename_re,
		                  $t ) )
		$fcomment .= " (Link to $t)";
	    else
		$fcomment .= " (Link to default)";
	}
	elseif ( in_array ( $fext, $linkable_ext,
	                           true ) )
	{
	    $fbase = pathinfo
	        ( $fname, PATHINFO_FILENAME );
	    $re = '/^(generate|filter|monitor)-'
	        . "$problem\$/";
	    if ( preg_match ( "/-$problem\$/",
	                      $fbase )
		 &&
		 ! preg_match ( $re, $fbase ) )
	        $flinkable = true;
	}

	return [ $fext, $ftype,
	         $fdisplay, $fshow, $flinkable,
		 $fcomment ];
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

    if ( isset ( $epm_page_init ) )
    {
        if ( isset ( $work['DIR'] )
	     &&
	     ( ! isset ( $work['RESULT'] )
	       ||
	       $work['RESULT'] === true ) )
	{
	    $m = abort_dir ( $work['DIR'] );
	    if ( $m != '' ) $warnings[] = $m;
	}
        if ( isset ( $run['DIR'] )
	     &&
	     ( ! isset ( $run['RESULT'] )
	       ||
	       $run['RESULT'] === true ) )
	{
	    $m = abort_dir ( $run['DIR'] );
	    if ( $m != '' ) $warnings[] = $m;
	}

	if ( count ( $warnings ) > 0 )
	    usleep ( 3000000 ); // 3 seconds
	    // Let orphaned tab into schedule so it
	    // can process any last update POST and/or
	    // receive orphaned response
	$work = [];
	$run = [];
    }

    // Process file deletions for other posts.
    //
    if ( $rw && isset ( $_POST['delete_files'] ) )
    {
	$files = $_POST['delete_files'];
	$files = explode ( ',', $files );
	$fnames = problem_file_names ( $probdir );
	foreach ( $files as $f )
	{
	    if ( $f == '' ) continue;
	    if ( ! in_array ( $f, $fnames, true ) )
		exit ( "ACCESS: illegal POST to" .
		       " problem.php" );
	}
	foreach ( $files as $f )
	{
	    if ( $f == '' ) continue;
	    $g = "$probdir/$f";
	    if ( ! @unlink ( "$epm_data/$g" ) )
		$errors[] = "could not delete $g";
	}
    }

    // Process POST requests.
    //
    if ( $epm_method != 'POST' ) /* Do Nothing */;
    // xhttp posts are first and are done if ! $rw
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
	$r = update_work_results ( 0 );
	if (    $r === true
	     && $_POST['update'] == 'abort' )
	{
	    abort_dir ( $work['DIR'] );
	    usleep ( 100000 ); // 0.1 second
	    $r = update_work_results ( 0 );
	}
	while ( true )
	{
	    if ( $r !== true || $count == 10 )
	    			// 5 seconds
	    {
	        echo "RELOAD\n";
		exit;
	    }
	    usleep ( 1000000 ); // 1.0 second
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
	    $count += 1;
	    $r = update_work_results ( 0 );
	}
    }
    elseif ( isset ( $_POST['order'] ) )
    {
        $new_order = $_POST['order'];
	if ( ! in_array ( $new_order, ['lexigraphic',
	                               'recent',
				       'extension'] ) )
	    echo ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$order = $new_order;
    }
    elseif ( ! $rw )
        /* Do Nothing */;
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
    elseif ( isset ( $_POST['make'] ) )
    {
        $m = $_POST['make'];
	if ( ! preg_match ( '/^([^:]+):([^:]+)$/', $m,
	                    $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$src = $matches[1];
	$des = $matches[2];
		 	    
	$fnames = problem_file_names ( $probdir );
	if ( ! in_array ( $src, $fnames, true ) )
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
		 	    
	$fnames = problem_file_names ( $probdir );
	if ( ! in_array ( $src, $fnames, true ) )
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
    elseif ( isset ( $_POST['link'] ) )
    {
        $to = $_POST['link'];
	$fnames = problem_file_names ( $probdir );
	if ( ! in_array ( $to, $fnames, true ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$ext = pathinfo ( $to, PATHINFO_EXTENSION );
	if ( ! in_array ( $ext, $linkable_ext,
	                        true ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );

	$base = pathinfo ( $to, PATHINFO_FILENAME );
	$from = "$problem";
	$re = "/-(generate|filter|monitor)-$problem\$/";
	if ( preg_match ( $re, $base, $matches ) )
	    $from = "{$matches[1]}-$problem";

	if ( in_array ( $ext, $executable_ext, true ) )
	    foreach ( $executable_ext as $uext )
	    {
		if ( $uext != '' ) $uext = ".$uext";
		@unlink
		    ( "$epm_data/$probdir/$from$uext" );
	    }

	if ( $ext != '' ) $ext = ".$ext";
	if ( ! symbolic_link
	           ( $to,
		     "$epm_data/$probdir/$from$ext" ) )
	    ERROR ( "cannot link $from$ext to $to" );
    }
    elseif ( isset ( $_POST['run'] ) )
    {
        $f = $_POST['run'];
	if ( ! preg_match ( '/\.run$/', $f ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
		 	    
	$fnames = problem_file_names ( $probdir );
	if ( ! in_array ( $f, $fnames, true ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$d = "$probdir/+parent+";
	$lock = NULL;
	if ( is_dir ( "$epm_data/$d" ) )
	    $lock = LOCK ( $d, LOCK_SH );
	start_run
	    ( "$probdir/+work+", $f,
	      $lock, "$probdir/+run+",
	      false, $errors );
        if ( isset ( $run['RESULT'] ) )
	{
	    header
	        ( "Location: $epm_root/page/run.php?" .
	          "id=$ID&problem=$problem" );
	    exit;
	}
    }
    elseif ( isset ( $_POST['delete_working'] ) )
    {
        if ( $work['DIR'] )
	{
	    cleanup_dir ( $work['DIR'], $warnings );
	    $work = [];
	}
    }
    elseif ( isset ( $_POST['delete_files'] ) )
    {
    	// Work was done above.
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
    select.order {
        background-color: inherit;
	border: 1px black solid;
    }
    td.time {
	color: var(--hl-purple);
	text-align: right;
	padding-left: var(--indent);
    }
    pre.error-message {
	color: var(--hl-red);
    }
    div.abort-switch {
        display: inline-block;
	width: calc(10*var(--large-font-size));
    }
    tr.kept {
        background-color: var(--bg-dark-tan);
    }
    tr.show {
        background-color: var(--bg-yellow);
    }

</style>

<script>
    var problem = '<?php echo $problem; ?>';

    function NEW_WINDOW ( page, filename ) {
	var src = page
	        + '?problem='
	        + encodeURIComponent ( problem )
		+ '&filename='
		+ encodeURIComponent ( filename );
	VIEW ( src );
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

    let delete_files =
        document.getElementsByName ( 'delete_files' );
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
	    FILE.style.textDecoration =
	        'line-through red wavy';

	}
	else
	{
	    DELETE_LIST.splice ( i, 1 );
	    BUTTON.title = "Mark " + fname +
	                   " For Deletion";
	    FILE.style.textDecoration = 'none';
	}
	for ( var j = 0; j < delete_files.length; ++ j )
	    delete_files[j].value =
		DELETE_LIST.toString();
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
    else if ( isset ( $make_ftest ) )
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

    echo <<<EOT
    <div class='manage' id='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong title='Login Name'>$lname</strong>
    </td><td>

    <strong>Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre></b>
EOT;

    if ( isset ( $parent ) )
        echo <<<EOT
	<strong>(from project $parent)</strong>
EOT;
    echo <<<EOT
    </td><td>
EOT;

    if ( $rw )
        echo <<<EOT
	<form action='problem.php' method='POST'>
	<input type='hidden'
	       name= 'problem' value='$problem'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit'
		name='delete_problem'
		value='$problem'
		title='Delete this problem'>
	Delete Problem</button>
	</form>
EOT;

    $refresh = "problem.php?problem=$problem"
             . "&id=$ID";
    $title = 'View downloadable sample solutions'
           . ' and templates';
    echo <<<EOT
    <button type='button'
	    onclick='VIEW("downloads/index.html")'
	    title='$title'>
	View Downloads</button>

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
    <button type='button' id='refresh'
            onclick='location.replace ("$refresh")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("problem-page")'>
	?</button>
    </td>
    </tr></table>
    </div>
EOT;

    $order_options = '';
    foreach ( ['lexigraphic' => '(reverse lex order)',
	       'recent' => '(most recent first)',
	       'extension' => '(extension order)']
	      as $key => $label )
    {
	$selected = ( $key == $order ? 'selected' : '' );
	$order_options .=
	    "<option value='$key' $selected>" .
	    "$label</option>";
    }

    $count = 0;
    $show_map = [];
    $display_map = [];
	// $show_map[$fname] => id or
	// $display_map[$fname] => id
	// maps file name last component to the
	// button id that identifies the button
	// to click to show/display the file.
    $display_list = [];

    $kept = [];
    $kept_count = 0;
    $show = [];
    $working_files = [];
    $working_has_show = false;
    if ( isset ( $work['DIR'] ) )
    {
	$workdir = $work['DIR'];
	$result = $work['RESULT'];
	$kept = $work['KEPT'];
	$kept_count = count ( $kept );
	$show = $work['SHOW'];
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
	<pre>   </pre>
	<div id='abort-switch' class='abort-switch'
	     style='visibility:hidden'>
	<div id='abort-checkbox' class='checkbox'
	     onclick='ABORT_CLICK()'></div>
	<strong id='abort-label' style='color:red'>
	     Abort</strong>
	</div>
	</td><td style='text-align:right'>
	<button type='button'
		onclick='HELP( "problem-commands")'>
	    ?</button>
	</td>
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
	    problem_file_names( $workdir, false );

	if ( count ( $working_files ) > 0 )
	{
	    $delete_title = 'Delete working'
	                  . ' directory/files and'
			  . ' command history';
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
		Commands</strong>
	    <form method='POST' action='problem.php'
		  id='working-order-form'>
	    <input type='hidden'
		   name='id' value='$ID'>
	    <input type='hidden'
		   name='problem' value='$problem'>
	    <select name='order' class='order'
		onchange='document.getElementById
		  ("working-order-form").submit()'
		title='Select file listing order'>
	    $order_options
	    </select></form>
	    <pre>   </pre>
	    <!-- for some reason the following form
	         includes the above <select> if we
		 use type='submit' for the <button>
	    -->
	    <form method='POST' action='problem.php'
	          id='delete-working-form'>
	    <input type='hidden'
		   name='id' value='$ID'>
	    <input type='hidden'
		   name='problem' value='$problem'>
	    <input type='hidden'
		   name='delete_working'>
	    </form>
	    <button type='button'
	            onclick='document.getElementById
		        ("delete-working-form")
			.submit()'
		    title='$delete_title'>
	    Delete Working Files and Command History
	    </button>
	    </td><td style='text-align:right'>
	    <button type='button'
		    onclick='HELP("problem-working")'>
		?</button>
	    </td>
	    </tr></table>
	    <div id='working_body'
		 style='display:none'>
	    <table style='display:block'>
EOT;

	    foreach ( $working_files as $fname )
	    {
		$f = "$epm_data/$workdir/$fname";

		$show_fname =
		    in_array ( $fname, $show );
		             
		$class = ( $show_fname ? 'show' : '' );
		if ( $show_fname )
		    $working_has_show = true;

		++ $count;
		echo "<tr class='$class'>";
		echo "<td" .
		     " style='text-align:right'>";
		list ( $fext, $ftype, $fdisplay,
		       $fshow, $flinkable, $fcomment )
		    = file_info ( $workdir, $fname,
				  $count,
				  $display_list );

		if ( $fshow )
		{
		    $show_map[$fname] = "show$count";
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
		    echo <<<EOT
			<pre id='file$count'
			    >$fname</pre>
			</td>
EOT;

		if ( $fdisplay )
		{
		    $display_map[$fname] =
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
	<form method='POST' action='problem.php'
	      id='problem-order-form'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden'
	       name='problem' value='$problem'>
	<select name='order' class='order'
		onchange='document.getElementById
		    ("problem-order-form").submit()'
		title='Select file listing order'>
	$order_options
	</select></form>
    </strong>
    </td>
EOT;
    if ( $rw )
        echo <<<EOT
	<td>
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
	<input type='hidden'
	       name='delete_files' value=''>
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
	<input type='hidden'
	       name='delete_files' value=''>
	<button type="submit" name="execute_deletes">
		Delete Over-Struck Files</button>
	</form>
	</td><td style='text-align:right'>
	<button type='button'
		onclick='HELP("problem-marks")'>
	    ?</button>
	</td>
EOT;
    echo <<<EOT
    </tr>
    </table>
    <div id='problems_body'>
    <form action='problem.php' method='POST'>
    <input type='hidden'
	   name= 'problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='delete_files' value=''>
EOT;
    function MAKE ( $fbase, $sext, $dext )
    {
	if ( $sext != '' ) $sext = ".$sext";
	if ( $dext != '' ) $dext = ".$dext";
	echo "<button type='submit'" .
	     " name='make'" .
	     " title='Make $fbase$dext" .
	     " from $fbase$sext'" .
	     " value='$fbase$sext:$fbase$dext'>" .
	     "&rArr;$dext</button>";
    }
    echo "<table style='display:block'>";
    foreach ( problem_file_names( $probdir )
	      as $fname )
    {

        $is_working = in_array
	    ( $fname, $working_files );

	$class = '';
	if ( $is_working )
	    /* Do Nothing */;
	elseif ( in_array ( $fname, $kept ) )
	    $class = 'kept';
	elseif ( $kept_count == 0 )
	    /* Do Nothing */;
	elseif ( in_array ( $fname, $show ) )
	    $class = 'show';

	++ $count;
	echo "<tr class='$class'>";
	echo "<td style='text-align:right'>";
	list ( $fext, $ftype,
	       $fdisplay, $fshow, $flinkable,
	       $fcomment )
	    = file_info ( $probdir, $fname, $count,
			  $display_list );
	$fbase = pathinfo ( $fname, 
			    PATHINFO_FILENAME );

	if ( $fshow )
	{
	    if ( ! $is_working && $kept_count > 0 )
		$show_map[$fname] = "show$count";
		// Working directory overrides
		// problem directory.
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
	    if ( ! $is_working && $kept_count > 0 )
		$display_map[$fname] =
		    "file{$count}_button";
		// Working directory overrides
		// problem directory.
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

	if ( $rw )
	    echo <<<EOT
	    <td><button type='button'
		 id='button$count'
		 onclick='TOGGLE_DELETE
		    ($count, "$fname")'
		 title='Mark $fname For Deletion'>
	    <pre id='mark$count'>&Chi;</pre>
	    </button></td>
EOT;
        echo <<<EOT
	<td colspan='100'>
EOT;
	if ( ! $rw )
	    /* Do Nothing */;
	elseif ( $flinkable )
	{
	    $ext = ( $fext != '' ? ".$fext" : $fext );
	    $base = pathinfo
	        ( $fname, PATHINFO_FILENAME );
	    $link = "$problem$ext";
	    $re = '/-(generate|filter|monitor)'
	        . "-$problem\$/";
	    if ( preg_match ( $re, $base, $matches ) )
		$link = "{$matches[1]}-$problem$ext";

	    echo "<button type='submit'" .
		 " name='link'" .
		 " title='Link $link to $fname'" .
		 " value='$fname'>" .
		 "Link</button>";
	}
	elseif ( $fext == 'in' )
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
	    echo "<button type='submit'" .
		 " style='background-color:" .
			 "var(--hl-orange)'" .
		 " name='make'" .
		 " title='Make $fbase.ftest" .
		 " from $fname'" .
		 " value='$fname:$fbase.ftest'>" .
		 "&rArr;.ftest</button>";
	}
	elseif ( $fext == 'run' )
	{
	    echo "<button type='submit'" .
		 " name='run'" .
		 " value='$fname'>" .
		 "Run</button>";
	}
	elseif ( $fext == '' )
	{
	    $re = '/^(generate|filter|monitor)'
	        . "-$problem\$/";
	    if ( preg_match ( $re, $fbase ) )
		MAKE ( $fbase, '', 'txt' );
	}
	echo "<pre>  $fcomment</pre>";
	echo "</td></tr>";
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

    if ( count ( $show ) > 0 )
    {
	$files = [];

	foreach ( $show as $fname )
	{
	    if ( isset ( $show_map[$fname] )
	         ||
		 isset ( $display_map[$fname] ) )
		$files[] = $fname;
	}
	$ids = [];
	if ( count ( $files ) >= 2 )
	{
	    if ( isset ( $display_map[$files[0]] )
		 &&
		 isset ( $display_map[$files[1]] ) )
		 $ids = [$display_map[$files[0]],
		         $display_map[$files[1]]];
	    elseif ( isset ( $show_map[$files[0]] )
	             &&
		     isset ( $display_map[$files[1]] ) )
		 $ids = [$show_map[$files[0]],
		         $display_map[$files[1]]];
	    elseif ( isset ( $display_map[$files[0]] )
		     &&
		     isset ( $show_map[$files[1]] ) )
		 $ids = [$display_map[$files[0]],
		         $show_map[$files[1]]];
	    else
	         $ids = [$show_map[$files[0]]];
	}
	elseif ( count ( $files ) == 1 )
	{
	    if ( isset ( $display_map[$files[0]] ) )
	        $ids = [$display_map[$files[0]]];
	    else
	        $ids = [$show_map[$files[0]]];
	}

	if ( $working_has_show ) $ids[] =
	    'working_button';

	foreach ( $ids as $id )
	    echo "<script>document" .
		 ".getElementById('$id').click();" .
		 "</script>";
    }

    echo <<<EOT
    <form action='problem.php'
	  method='POST' id='reload'>
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
              echo "LOG = console.log;";
    ?>
    var ID = '<?php echo $ID; ?>';

    var xhttp = new XMLHttpRequest();

    function FAIL ( message )
    {
	alert ( message );
	window.close();
	location.assign ( 'illegal.html' );
    }

    let reload = document.getElementById("reload");
    let manage = document.getElementById("manage");
    let work_display =
        document.getElementById("work-display");
    let problem_display =
        document.getElementById("problem-display");
    let abort_switch =
        document.getElementById("abort-switch");
    let abort_checkbox =
        document.getElementById("abort-checkbox");
    let abort_label =
        document.getElementById("abort-label");
    let on = 'black';
    let off = 'white';

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
		for ( var j = 0; j < ids.length; ++ j )
		{
		    // if ( ids[j] == null ) continue;
		    ids[j].value = ID;
		}
		continue;
	    }
	    else if ( item[0] == 'RELOAD'
	              &&
		      item.length == 1 )
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
		    FAIL ( 'bad xhttp response: ' +
			   RESPONSE );
	    }
	    catch ( err )
	    {
		FAIL ( 'bad xhttp response: ' +
		       RESPONSE +
		       "\n    " + err.message );
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
	    RESPONSE = this.responseText;
	    PROCESS_RESPONSE ( RESPONSE );
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
	if ( isset ( $problem )
	     &&
	     isset ( $work['RESULT'] ) )
	{
	    $r = $work['RESULT'];
	    if ( $r === true )
		echo "REQUEST_UPDATE();";
	    if ( ! is_array ( $r ) || $r != ['D',0] )
		 echo "document.getElementById" .
		      "('commands_button').click();";
	}
    ?>

</script>

</body>
</html>
