<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Feb  8 14:19:34 EST 2020

    // Selects user problem.  Displays and uploads
    // problem files.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

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
    // Also set $problem_dir to the problem directory if
    // $problem not NULL and the problem directory
    // exists.  If $problem is not NULL but the problem
    // directory does not exist, the problem has been
    // deleted by another session.
    //
    // Also lock the problem directory for the duration
    // of the execution of this page.
    //
    $problem = NULL;
    $problem_dir = NULL;
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
	$problem_dir =
	    "users/user$uid/$problem";
	if ( ! is_dir ( "$epm_data/$problem_dir" ) )
	{
	    $errors[] = "problem $problem has been"
	             . " deleted by another session";
	    $problem_dir = NULL;
	}
	else
	{
	    $lock_desc =
		fopen ( "$epm_data/$problem_dir/+lock+",
		        "w" );
	    flock ( $lock_desc, LOCK_EX );
	}

    }
    else
	$problem_dir = NULL;

    // Data Set by GET and POST Requests:
    //
    $show_file = NULL;  // File to be shown to right.
    $show_files = [];   // Files to be shown to left.
    $runfile = NULL;
        // Non-NULL if there are commands to be
	// displayed.
    $uploaded_file = NULL;
        // 'name' of uploaded file, if any file was
	// uploaded.

    // Set $problems to list of available problems.
    //
    $problems = [];

    $desc = opendir ( "$epm_data/$user_dir" );
    if ( ! $desc )
         error
	     ( "SYSTEM ERROR: cannot open $user_dir" );
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
        global $epm_data, $problem_dir,
	       $problem_file_names, $display_file_type;

	if ( isset ( $problem_file_names ) )
	    return $problem_file_names;

	if ( ! isset ( $problem_dir ) )
	{
	    $problem_file_names = [];
	    return $problem_file_names;
	}

	clearstatcache();
	$map = [];

	foreach ( scandir ( "$epm_data/$problem_dir" )
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
	    $f = "$problem_dir/$fname";
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

    // Remaining POSTs require $problem and $problem_dir
    // to be non-NULL.
    //
    if ( $method != 'POST' ) /* Do Nothing */;
    elseif ( ! isset ( $problem_dir ) )
	/* Do Nothing */;
    elseif ( isset ( $_POST['show_file'] ) )
    {
	require "$epm_home/include/epm_make.php";
	    // Do this first as it may change $f,
	    // etc.  Needed if we set show_files.

	$f = $_POST['show_file'];
	if ( array_search
	         ( $f, problem_file_names(), true )
	     === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );

	$show_files[] = "users/user$uid/$problem/$f";
    }
    elseif ( isset ( $_POST['delete_file'] ) )
    {
	$f = $_POST['delete_file'];
	if ( array_search
	         ( $f, problem_file_names(),
		       true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$f = "$problem_dir/$f";
        if ( ! unlink ( "$epm_data/$f" ) )
	    $errors[] = "could not delete $f";
	$problem_file_names = NULL;
	    // Clear cache.
    }
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
	make_and_keep_file
	    ( $src, $des,
	      $problem, "$problem_dir/+work+",
	      $problem_dir, 100,
	      $runfile, $kept, $show_files,
	      $warnings, $errors );
	$problem_file_names = NULL; // Clear cache.
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
		( $upload_info,
		  $problem, "$problem_dir/+work+",
		  $problem_dir, 100,
		  $runfile, $kept, $show_files,
		  $warnings, $errors );
	    $problem_file_names = NULL; // Clear cache.
	}
	else
	    $errors[] = "no file selected for upload";
    }


?>

<?php 

    // If a file is to be shown to the right, output
    // it before anything else.
    //
    if ( count ( $show_files ) > 0 )
    {
        if ( ! function_exists ( "find_show_file" ) )
	    ERROR ( "problem.php:" .
	            " failed to load epm_make.php" .
		    " while setting show_files" );
        $show_file = find_show_file ( $show_files );
    }

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
    div.runfile }
	background-color: #c0ffc0;
    }
    .commands {
	margin-left: 20px;
    }
	echo "<div style='background-color:#c0ffc0;'>" .
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
	echo "Errors:" . PHP_EOL;
	echo "<div style='margin-left:20px;" .
	                "font-size:110%'>" . PHP_EOL;
	foreach ( $errors as $e )
	    echo "<pre style='margin:0 0'>$e</pre>" .
	         "<br>" . PHP_EOL;
	echo "</div></div>" . PHP_EOL;
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div style='background-color:#ffc0ff'>" .
	     PHP_EOL;
	echo "Warnings:" . PHP_EOL;
	echo "<div style='margin-left:20px'>" . PHP_EOL;
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "</div></div>" . PHP_EOL;
    }

    $current_problem = ( isset ( $problem ) ?
                                 $problem :
			         "none selected" );
    echo <<<EOT
    <form style='display:inline'
          action='user.php' method='GET'>
    <h5>User:</h5> <input type='submit' value='$email'>
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
        echo "<td><select name='selected_problem'>" .
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
	<input type="file" name="uploaded_file">
	</form>
EOT;
    }

    if ( $runfile )
    {
	echo "<div class='runfile'>" .
	     PHP_EOL;
	get_commands_display
	    ( $display, $display_map,
	      $runfile, "$problem_dir/+work+" );
	echo "<h5>Commands:</h5>" . PHP_EOL;
	echo "<table style='margin-left:20px;'>" . PHP_EOL;
	echo $display . PHP_EOL;
	echo "</table>" . PHP_EOL;
        if ( count ( $kept ) > 0 )
	{
	    echo "<h5>Kept:</h5><ul>" . PHP_EOL;
	    foreach ( $kept as $e )
	        echo "<li><pre style='margin:0 0'>" .
		     "$e</pre>" . PHP_EOL;
	     echo "</ul>" . PHP_EOL;
	}
	echo "</div>" . PHP_EOL;
    }

    if ( count ( $show_files ) > 0 )
    {
	echo "<div style='" .
	     "background-color:" .
	     "#AEF9B0;'>" . PHP_EOL;
	foreach ( $show_files as $f )
	{
	    $f = "$epm_data/$f";
	    $b = basename ( $f );
	    if ( filesize ( $f ) == 0 )
	    {
		echo "<u>$b</u> is empty<br>" . PHP_EOL;
		continue;
	    }
	    $ext = pathinfo ( $f, PATHINFO_EXTENSION );
	    if ( ! isset ( $display_file_type[$ext] ) )
	        continue;
	    $type = $display_file_type[$ext];
	    if ( $type == 'pdf' ) $type = 'PDF file';

	    if ( $type == 'utf8' )
	    {
		echo "<u>$b</u>:<br>" . PHP_EOL;
		$c = file_get_contents ( $f );
		$hc = htmlspecialchars ( $c );
		echo "<pre>$hc</pre>" .
		     PHP_EOL . PHP_EOL;
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
		    $t = "$t which is $type";
		}
		else
		    $t = $type;
		echo "<u>$b</u> is $t<br>" . PHP_EOL;
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
</body>
</html>
