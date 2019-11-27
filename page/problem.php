<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Nov 27 07:02:02 EST 2019

    // Selects user problem.  Displays and uploads
    // problem files.

    session_start();
    clearstatcache();
    umask ( 06 );
    if ( ! isset ( $_SESSION['epm_data'] ) )
    {
	header ( "Location: index.php" );
	exit;
    }
    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }

    $epm_data = $_SESSION['epm_data'];
    $userid = $_SESSION['userid'];

    $uploaded_file = NULL;

    if ( ! isset ( $_SESSION['epm_admin_params'] ) )
	include 'include/get_admin_params.php';
    $params = $_SESSION['epm_admin_params'];
    $upload_maxsize = $params['upload_maxsize'];
    $display_file_ext = $params['display_file_ext'];

    include 'include/debug_info.php';

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    $userid = $_SESSION['userid'];
    $email = $_SESSION['email'];

    $user_dir = "$epm_data/users/user$userid";

    if ( $userid == 'NEW'
         ||
	 ! is_writable ( "$user_dir" ) )
    {
	header ( "Location: user_edit.php" );
	exit;
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

    // Set $problem to current problem, or NULL if none.
    //
    $problem = NULL;
    $problem_error = NULL;
    $new_problem = false;
    if ( isset ( $_POST['goto_problem'] ) )
    {
	// new_problem takes precedence over problem,
	// as the latter is always set to the current
	// selected value.
	//
        $problem = trim ( $_REQUEST['new_problem'] );
	if ( $problem == "" )
	    $problem =
	        trim ( $_REQUEST['problem'] );
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
	    $problem_error =
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
		$problem_error =
		    "problem $problem already exists" .
		" for user $email";
		$problem = NULL;
	    }
	    elseif ( ! mkdir ( $problem_dir, 0770 ) )
		exit ( "SYSTEM ERROR: cannot make" .
		       $problem_dir );
	    else
	        $problems[] = $problem;
	}
	elseif ( ! is_writable
		      ( "$epm_data/users/user$userid" .
		        "/$problem" ) )
	{
	    $problem_error =
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

    if ( isset ( $_POST['upload'] ) )
    {
	$upload_info = $_FILES['uploaded_file'];
	$uploaded_file = $upload_info['name'];
	if ( $uploaded_file != "" )
	{
	    include 'include/epm_make.php';
	    $upload_errors = [];
	    $upload_warnings = [];
	    $upload_output = [];
	    process_upload
		( $upload_info, $problem,
		  $upload_commands, $upload_moved,
		  $upload_show, $upload_output,
		  $upload_warnings, $upload_errors );
	}
	else
	    $upload_errors =
	        ["no file selected for upload"];
    }


?>


<html>
<body>

<div style="background-color:#c0ffff;width:50%;">
<?php 

    if ( isset ( $problem_error ) )
        echo "<mark>ERROR:" .
	     " $problem_error</mark><br><br>\n";
    $current_problem = ( isset ( $problem ) ?
                                 $problem :
			         "none selected" );
    echo <<<EOT
    <form action='user.php' method='GET'>
    User: <input type='submit' value='$email'>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <b>Current Problem:&nbsp;$current_problem</b>
    </form>
    <table><form action='problem.php' method='POST'>
EOT;
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
	$problem_dir =
	    "$epm_data/users/user$userid/$problem";
        $count = 0;
	foreach ( scandir ( $problem_dir ) as $fname )
	{
	    if ( preg_match ( '/^\./', $fname ) )
	        continue;
	    if ( ! preg_match ( '/[^.]\.([^.]+)$/',
	                        $fname, $matches ) )
	        continue;
	    $ext = $matches[1];
	    if ( ! array_search
	               ( $ext, $display_file_ext,
		               true ) )
		continue;

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
	    if ( $ext == "out" )
	    {
		echo "<td><button type='submit'" .
		     " name='move_file' value='$fname'>" .
		     "Move to .test</button></td>";
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

    if ( isset ( $uploaded_file ) )
    {
	echo "<div style='background-color:#c0ffc0;width:50%;'>\n";
        if ( count ( $upload_errors ) > 0 )
	{
	    echo "Errors:<br><ul>\n";
	    foreach ( $upload_errors as $e )
	        echo "<li><pre>$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $upload_warnings ) > 0 )
	{
	    echo "Warnings:<br><ul>\n";
	    foreach ( $upload_warnings as $e )
	        echo "<li><pre>$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $upload_output ) > 0 )
	{
	    echo "Output:<br><ul>\n";
	    foreach ( $upload_output as $e )
	        echo "<li><pre>$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $upload_commands ) > 0 )
	{
	    echo "Commands:<br><ul>\n";
	    foreach ( $upload_commands as $e )
	        echo "<li><pre>$e</pre>\n";
	     echo "</ul>\n";
	}
        if ( count ( $upload_moved ) > 0 )
	{
	    echo "Kept:<br><ul>\n";
	    foreach ( $upload_moved as $e )
	        echo "<li><pre>$e</pre>\n";
	     echo "</ul>\n";
	}
	echo "</div>\n";
        if ( count ( $upload_show ) > 0 )
	{
	    foreach ( $upload_show as $f )
	    {
		$f = "$epm_data/$f";
		$b = basename ( $f );
	        if ( filesize ( $f ) == 0 )
		{
		    echo "$b is empty<br>\n";
		    continue;
		}
		echo "$b:\n\n";
		echo "<div style='background-color:#ffc0ff;width:50%;'>\n";
		echo '<pre>' . file_get_contents ( $f )
		             . "</pre>\n\n";
		echo "</div>\n";
	    }
	}
    }
?>

</body>
</html>
