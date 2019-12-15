<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Dec 15 01:19:44 EST 2019

    // Selects user problem.  Displays and uploads
    // problem files.

    session_start();
    clearstatcache();
    umask ( 06 );
        // o+x must be allowed on problem executables
	// and directories because of epm_sandbox.

    if ( ! isset ( $_SESSION['epm_userid'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }
    if (    $_SESSION['epm_ipaddr']
	 != $_SERVER['REMOTE_ADDR'] )
        exit ( 'UNACCEPTABLE IPADDR CHANGE' );

    $userid = $_SESSION['epm_userid'];
    $epm_data = $_SESSION['epm_data'];
    $epm_root = $_SESSION['epm_root'];
    $email = $_SESSION['epm_email'];
    $include = "$epm_root/include";

    // require "$include/debug_info.php";

    $user_dir = "$epm_data/users/user$userid";

    // Administrative Parameters:
    //
    $params = $_SESSION['epm_admin_params'];
    $upload_maxsize = $params['upload_maxsize'];
    $display_file_ext = $params['display_file_ext'];

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // Set $problem to current problem, or NULL if none.
    // Also set $problem_dir if $problem not NULL.
    //
    $problem = NULL;
    $problem_dir = NULL;
    if ( isset ( $_POST['new_problem'] ) )
    {
        $problem = trim ( $_POST['new_problem'] );
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
	elseif ( is_dir ( "$user_dir/$problem" ) )
	{
	    $errors[] =
	        "trying to create $problem which" .
		" already exists";
	    $problem = NULL;
	}
	elseif ( ! mkdir ( "$user_dir/$problem",
	                   0771 ) )
	{
	    $sysfail = "cannot make $user_dir/$problem";
	    require "$include/sysalert.php";
	}
        unset ( $_SESSION['delete_problem_tag'] );
    }
    elseif ( isset ( $_POST['selected_problem'] ) )
    {
        $problem = trim ( $_POST['selected_problem'] );
	if ( ! preg_match
	           ( '/^[-_A-Za-z0-9]+$/', $problem ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	elseif ( ! is_dir ( "$user_dir/$problem" ) )
	{
	    $errors[] =
	        "trying to select non-existant" .
		" problem: $problem";
	    $problem = NULL;
	}
        unset ( $_SESSION['delete_problem_tag'] );
    }

    if (    ! isset ( $problem )
         && isset ( $_SESSION['epm_problem'] ) )
        $problem = $_SESSION['epm_problem'];
    elseif ( isset ( $problem ) )
	$_SESSION['epm_problem'] = $problem;

    if ( isset ( $problem ) )
	$problem_dir =
	    "$epm_data/users/user$userid/$problem";
    else
	$problem_dir = NULL;

    // Data Set by GET and POST Requests:
    //
    $show_file = NULL;  // File to be shown to right.
    $show_files = [];   // Files to be shown to left.
    $errors = [];	// Error messages to be shown.
    $warnings = [];	// Warining messages to be
    			// shown.
    $file_made = false;
        // True if $output, $commands, and $kept are to
	// be displayed.
    $uploaded_file = NULL;
        // 'name' of uploaded file, if any file was
	// uploaded.
    $delete_problem = false;
        // True to ask whether current problem is to be
	// deleted.
    $deleted_problem = NULL;
        // Set to announce that $deleted_problem has
	// been deleted.

    if ( isset ( $_POST['delete_problem'] ) )
    {
	$prob = $_POST['delete_problem'];
	if ( $prob != $problem )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$delete_problem_tag =
	    bin2hex ( random_bytes ( 8 ) );
	$_SESSION['delete_problem_tag'] =
	    $delete_problem_tag;
	$delete_problem = true;
    }
    else if ( isset ( $_POST['delete_problem_yes'] ) )
    {
        if ( ! isset ( $_SESSION['delete_problem_tag'] )
	     ||
	        $_SESSION['delete_problem_tag']
	     != $_POST['delete_problem_yes'] )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
        unset ( $_SESSION['delete_problem_tag'] );
	exec ( "rm -rf $problem_dir" );
	$deleted_problem = $problem;
	$problem = NULL;
	$problem_dir = NULL;
	unset ( $_SESSION['epm_problem'] );
    }
    else if ( isset ( $_POST['delete_problem_no'] ) )
    {
        if ( ! isset ( $_SESSION['delete_problem_tag'] )
	     ||
	        $_SESSION['delete_problem_tag']
	     != $_POST['delete_problem_no'] )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
        unset ( $_SESSION['delete_problem_tag'] );
    }

    // Set $problems to list of available problems.
    //
    $problems = [];

    $desc = opendir ( $user_dir );
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
        global $problem_dir, $problem_file_names,
	       $display_file_ext;

	if ( isset ( $problem_file_names ) )
	    return $problem_file_names;

	if ( ! isset ( $problem_dir ) )
	{
	    $problem_file_names = [];
	    return $problem_file_names;
	}

	clearstatcache();
	$map = [];

	foreach ( scandir ( $problem_dir ) as $fname )
	{
	    if ( preg_match ( '/^\./', $fname ) )
	        continue;
	    if ( $fname == "+work+" ) continue;
	    if ( preg_match ( '/[^.]\.([^.]+)$/',
	                       $fname, $matches ) )
		$ext = $matches[1];
	    else
	        $ext = "";
	    if ( array_search
		     ( $ext, $display_file_ext,
		             true ) === false )
		continue;
	    $f = "$problem_dir/$fname";
	    $map[$fname] = filemtime($f);
	}
	arsort ( $map, SORT_NUMERIC );
	    // Note, keys cannot be floating point and
	    // files often share modification times.
	foreach ( $map as $key => $value )
	    $problem_file_names[] = $key;

	return $problem_file_names;
    }

    if ( isset ( $_POST['show_file'] ) )
    {
	$f = $_POST['show_file'];
	if ( array_search
	         ( $f, problem_file_names(), true )
	     === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );

        $f = "users/user$userid/$problem/$f";
	$t = exec ( "file $epm_data/$f" );
	if ( preg_match ( '/(ASCII|PDF)/', $t ) )
	    $show_file = $f;
	else
	    $show_files[] = $f;
    }
    else if ( isset ( $_POST['delete_file'] ) )
    {
	$f = $_POST['delete_file'];
	if ( array_search
	         ( $f, problem_file_names(),
		       true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$f = "$problem_dir/$f";
        if ( ! unlink ( $f ) )
	    $errors[] = "could not delete $f";
	$problem_file_names = NULL;
	    // Clear cache.
    }
    else if ( isset ( $_POST['move_file'] ) )
    {
        $f = $_POST['move_file'];
	if ( array_search
	         ( $f, problem_file_names(),
		       true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	if ( ! preg_match ( '/^(.+)\.out$/', $f,
	                  $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$g = $matches[1] . '.test';
	if ( ! rename ( "$problem_dir/$f",
	                "$problem_dir/$g" ) )
	    $errors[] = "could not move $f to $g";
	$problem_file_names = NULL;
	    // Clear cache.
    }
    else if ( isset ( $_POST['make_score'] ) )
    {
	require "$include/epm_make.php";
	    // Do this first as it may change $f, etc.

        $f = $_POST['make_score'];
	if ( array_search
	         ( $f, problem_file_names(),
		       true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	if ( ! preg_match ( '/^(.+)\.in$/', $f,
	                  $matches ) )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );
	$g = $matches[1] . '.score';
	$h = $matches[1] . '.test';
	load_make_cache();
	$output = [];
	$d = "users/user$userid/$problem";
	make_and_keep_file
	    ( $f, $g, $problem,
	      "$d/+work+", $d, NULL,
	      $commands, $kept, $show_files,
	      $output, $creatables,
	      $warnings, $errors );
	$file_made = true;
	$problem_file_names = NULL;
	    // Clear cache.
    }
    else if ( isset ( $_POST['upload'] ) )
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
	    require "$include/epm_make.php";
		// Do this first as it may change $f,
		// etc.

	    $output = [];
	    $d = "users/user$userid/$problem";
	    process_upload
		( $upload_info, $problem,
		  "$d/+work+", $d,
		  $commands, $kept,
		  $upload_show, $output, $creatables,
		  $warnings, $errors );
	    foreach ( $upload_show as $f )
	    {
	        if ( ! preg_match ( '/\.out$/', $f )
		     ||
		     filesize ( "$epm_data/$f" ) == 0 )
		    $show_files[] = $f;
		else
		    $show_file = $f;
	    }
	    $file_made = true;
	    $problem_file_names = NULL;
		// Clear cache.
	}
	else
	    $errors[] = "no file selected for upload";
    }


?>

<?php 

    // If a file is to be shown to the right, output
    // it before anything else.
    //
    if ( isset ( $show_file ) )
    {
	$b = basename ( $show_file );
	if ( preg_match ( '/\.pdf$/', $b ) )
	    $e = 'pdf_show.php';
	else
	    $e = 'ascii_show.php';
	echo "<iframe" .
	     " src='$e?filename=$b'" .
	     " style='width:50%;height:97%;" .
	     "float:right'>\n";
	echo "</iframe>\n";
    }

?>

<html>
<body>

<div style="background-color:#96F9F3;width:47%;float:left">
<?php 

    if ( $delete_problem )
    {
	echo "<div style='background-color:#F5F81A'>\n";
	echo "<form method='POST'" .
	     " style='display:inline'" .
	     " action=problem.php>";
	echo "Do you really want to delete current" .
	     " problem $problem?";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='delete_problem_yes'" .
	     " value='$delete_problem_tag'>" .
	     "YES</button>";
	echo "&nbsp;&nbsp;<button type='submit'" .
	     " name='delete_problem_no'" .
	     " value='$delete_problem_tag'>" .
	     "NO</button>";
	echo "</form></div>\n";
    }
    else if ( isset ( $deleted_problem ) )
    {
	echo "<div style='background-color:#F5F81A'>\n";
	echo "Problem $deleted_problem has been deleted!<br>";
	echo "</div>\n";
    }
    if ( count ( $errors ) > 0 )
    {
	echo "<div style='background-color:#F5F81A'>\n";
	echo "Errors:\n";
	echo "<div style='margin-left:20px;font-size:110%'>\n";
	foreach ( $errors as $e )
	    echo "<pre style='margin:0 0'>$e</pre><br>\n";
	echo "</div></div>\n";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div style='background-color:#ffc0ff'>\n";
	echo "Warnings:\n";
	echo "<div style='margin-left:20px'>\n";
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>\n";
	echo "</div></div>\n";
    }

    $current_problem = ( isset ( $problem ) ?
                                 $problem :
			         "none selected" );
    echo <<<EOT
    <form style='display:inline'
          action='user.php' method='GET'>
    User: <input type='submit' value='$email'>
    </form>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <b>Current Problem:</b>&nbsp;$current_problem
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
    echo "<table><form action='problem.php' method='POST'>";
    if ( count ( $problems ) > 0 )
    {
	echo "<form action='problem.php'" .
	     " method='POST'" .
	     " style='display:inline'>\n";
	echo "<tr><td style='text-align:right'>" .
	     "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'></td>\n";
        echo "<td><select name='selected_problem'>\n";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>\n";
        echo "</select></td></tr></form>\n";
    }
    echo <<<EOT
    <form action='problem.php' method='POST'
	  style='display:inline'>
    <tr><td style='text-align:right'>
    or Create New Problem:</td><td>
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
		     " Current Problem Files (most recent first):" .
		     "<table style='display:block'>";
	    echo "<tr>";
	    echo "<td style='text-align:right'>" .
	         "<button type='submit'" .
	         " name='show_file' value='$fname'>" .
		 $fname . "</button></td>";
	    echo "<td><button type='submit'" .
	         " name='delete_file' value='$fname'>" .
		 "Delete</button></td>";
	    if ( preg_match ( '/\.out$/', $fname ) )
	    {
		echo "<td><button type='submit'" .
		     " name='move_file' value='$fname'>" .
		     "Move to .test</button></td>";
	    }
	    if ( preg_match ( '/\.in$/', $fname ) )
	    {
		echo "<td><button type='submit'" .
		     " name='make_score'" .
		     " value='$fname'>" .
		     "Make .score</button></td>";
	    }
	    echo "</tr>";
	}
	if ( $count > 0 ) echo "</table></form>";

        echo <<<EOT

	<form enctype="multipart/form-data"
	      action="problem.php" method="post">
	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$upload_maxsize">
	<input type="submit" name="upload"
	       value="Upload File:">
	<input type="file" name="uploaded_file">
	</form>
EOT;
    }

    if ( $file_made )
    {
	echo "<div style='background-color:#c0ffc0;'>";
        if ( count ( $output ) > 0 )
	{
	    echo "Output:<br><ul>\n";
	    foreach ( $output as $e )
	        echo "<li><pre style='margin:0 0'>" .
		     "$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $commands ) > 0 )
	{
	    echo "Commands:<br><ul>\n";
	    foreach ( $commands as $e )
	        echo "<li><pre style='margin:0 0'>" .
		     "$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $kept ) > 0 )
	{
	    echo "Kept:<ul>\n";
	    foreach ( $kept as $e )
	        echo "<li><pre style='margin:0 0'>" .
		     "$e</pre>\n";
	     echo "</ul>\n";
	}
	echo "</div>\n";
    }

    if ( count ( $show_files ) > 0 )
    {
	echo "<div style='" .
	     "background-color:" .
	     "#AEF9B0;'>\n";
	foreach ( $show_files as $f )
	{
	    $f = "$epm_data/$f";
	    $b = basename ( $f );
	    if ( filesize ( $f ) == 0 )
	    {
		echo "<u>$b</u> is empty<br>\n";
		continue;
	    }
	    $t = exec ( "file $f" );
	    if ( preg_match ( '/ASCII/', $t ) )
	    {
		echo "<u>$b</u>:<br>\n";
		echo '<pre>'
		   . file_get_contents ( $f )
		   . "</pre>\n\n";
	    }
	    else
	    {
		$t = explode ( ":", $t );
		$t = $t[1];
		$t = explode ( ",", $t );
		$t = $t[0];
		$t = trim ( $t );
		echo "<u>$b</u> is $t<br>\n";
	    }
	}
	echo "</div>\n";
    }
?>

</div>
</body>
</html>
