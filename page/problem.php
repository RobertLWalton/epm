<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Dec  9 02:15:31 EST 2019

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

    include 'include/debug_info.php';

    $userid = $_SESSION['epm_userid'];
    $epm_data = $_SESSION['epm_data'];
    $epm_root = $_SESSION['epm_root'];
    $email = $_SESSION['epm_email'];

    $uploaded_file = NULL;
    $show_file = NULL;  // File shown to right.
    $show_files = [];   // Files shown to left.
    $errors = [];
    $warnings = [];
    $file_made = false;
    $delete_problem = false;
    $deleted_problem = NULL;

    if ( ! isset ( $_SESSION['epm_admin_params'] ) )
        exit ( 'SYSTEM ERROR: problems.php:' .
	       ' $_SESSION["epm_admin_params"]' .
	       ' not set' );
    $params = $_SESSION['epm_admin_params'];
    $upload_maxsize = $params['upload_maxsize'];
    $display_file_ext = $params['display_file_ext'];

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );


    $user_dir = "$epm_data/users/user$userid";

    // Set $problem to current problem, or NULL if none.
    //
    $problem = NULL;
    $new_problem = false;
    if ( isset ( $_POST['goto_problem'] )
         ||
	 isset ( $_POST['new_problem'] ) )
    {
	// new_problem takes precedence over problem,
	// as the latter is always set to the current
	// selected value (unless there are no problems
	// in which case there is no `goto_problem' or
	// 'problem'.
	//
        $problem = trim ( $_POST['new_problem'] );
	if ( $problem == "" )
	    $problem =
	        trim ( $_POST['problem'] );
	else
	    $new_problem = true;

	if ( $problem == '' )
	    $problem = NULL;
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
    }

    if ( isset ( $problem ) )
    {
	$problem_dir =
	    "$epm_data/users/user$userid/$problem";

	if ( $new_problem )
	{
	    if ( file_exists ( $problem_dir ) )
	    {
		$errors[] =
		    "problem $problem already exists" .
		    " for user $email";
		$problem = NULL;
	    }
	    elseif ( ! mkdir ( $problem_dir, 0771 ) )
		exit ( "SYSTEM ERROR: cannot make" .
		       $problem_dir );
	}
	elseif ( ! is_writable
		      ( "$epm_data/users/user$userid" .
		        "/$problem" ) )
	{
	    $errors[] =
	        "problem $problem does not exist" .
		" for user $email";
	    $problem = NULL;
	}
    }

    if (    ! isset ( $problem )
         && isset ( $_SESSION['problem'] ) )
        $problem = $_SESSION['problem'];
    elseif ( isset ( $problem ) )
	$_SESSION['problem'] = $problem;

    if ( isset ( $problem ) )
	$problem_dir =
	    "$epm_data/users/user$userid/$problem";
    else
	$problem_dir = NULL;

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
	unset ( $_SESSION['problem'] );
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
	if ( ! preg_match ( '/^\.\.*$/', $value ) )
	    $problems[] = $value;
    }

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
	    if ( ! array_search
	               ( $ext, $display_file_ext,
		               true ) )
		continue;
	    $problem_file_names[] = $fname;
	}
	return $problem_file_names;
    }

    if ( isset ( $_POST['show_file'] ) )
    {
	$fname = $_POST['show_file'];
	if ( array_search
	         ( $fname, problem_file_names(),
		           true ) === false )
	    exit ( "ACCESS: illegal POST to" .
	           " problem.php" );

        $f = "users/user$userid/$problem/$fname";
	$t = exec ( "file $epm_data/$f" );
	if ( preg_match ( '/ASCII/', $t ) )
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
	include 'include/epm_make.php';
	load_make_cache();
	if ( ! isset ( $make_cache[$h] ) )
	    $errors[] = "cannot make $g as $h does"
	              . " not exist";
	else
	{
	    $output = [];
	    make_and_keep_file
		( $f, $g, $problem,
		  "users/user$userid/$problem",
		  $commands, $kept, $show_files,
		  $output, $warnings, $errors );
	    $file_made = true;
	    $problem_file_names = NULL;
		// Clear cache.
	}
    }
    else if ( isset ( $_POST['upload'] ) )
    {
	$upload_info = $_FILES['uploaded_file'];
	$uploaded_file = $upload_info['name'];
	if ( $uploaded_file != "" )
	{
	    include 'include/epm_make.php';
	    $output = [];
	    process_upload
		( $upload_info, $problem,
                  "users/user$userid/$problem",
		  $commands, $kept,
		  $upload_show, $output,
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
	$f = "$epm_data/$show_file";
	$c = file_get_contents ( $f );
	if ( $c === false )
	    $errors[] = "cannot read $f";
	else
	{
	    $lines = explode ( "\n", $c );
	    if ( array_slice ( $lines, -1, 1 ) == [""] )
		array_splice ( $lines, -1, 1 );
	    $count = 0;
	    echo "<div style='background-color:" .
		 "#d0fbd1;width:50%;" .
		 "float:right;overflow:scroll;" .
		 "height:100%'>\n";
	    echo "<u style='" .
	         "background-color:#ffe6ee'>\n" .
		 "$b</u>:<br><table>\n";
	    foreach ( $lines as $line )
	    {
	        ++ $count;
	        echo "<tr><td style='" .
		     "background-color:#b3e6ff;" .
		     "text-align:right;'>\n" .
		     "<pre>$count:</pre></td>" .
		     "<td><pre>  $line</pre></td></tr>\n";
	    }
	    echo "</table></div>\n";
	}
    }

?>

<html>
<body>

<div style="background-color:#96F9F3;width:50%;float:left">
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
	    echo "<pre>$e</pre><br>\n";
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
	echo "<tr><td style='text-align:right'>" .
	     "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'>\n";
        echo "</td><td><select name='problem'>\n";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>\n";
        echo "</select></td></tr>\n";
    }
    echo <<<EOT
    <tr><td style='text-align:right'>
    <label>or Create New Problem:</label></td><td>
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
		     " Current Problem Files:" .
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

?>
</div>

<?php
    if ( $file_made )
    {
	echo "<div style='background-color:#c0ffc0;" .
	     "width:50%;'>\n";
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
	     "#AEF9B0;width:50%;'>\n";
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

</body>
</html>
