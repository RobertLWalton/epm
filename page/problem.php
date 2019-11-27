<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 26 23:46:11 EST 2019

    // Selects user problem.  Displays and uploades
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
    $uploaded_file = NULL;

    if ( ! isset ( $_SESSION['epm_admin_params'] ) )
	include 'get_params.php';
    $params = $_SESSION['epm_admin_params'];
    $upload_maxsize = $params['upload_maxsize'];

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
    Current Problem:&nbsp;$current_problem
    </form>
    <form action='problem.php' method='POST'>
EOT;
    if ( count ( $problems ) > 0 )
    {
	echo "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'>\n";
        echo "<select name='problem'>\n";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>\n";
        echo "</select>\n";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
    }
    echo <<<EOT
    <br>or
    <label for="problem">Create New Problem:</label>
    <input type="text" size="32" name="new_problem"
           placeholder="New Problem Name">
    </form>
EOT;
    if ( isset ( $problem ) )
    {
        echo <<<EOT

	<br><br>

	<form enctype="multipart/form-data"
	      action="problem.php" method="post">
	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$upload_maxsize">
	<label for="uploaded_file">File to Upload:</label>
	<input type="file" name="uploaded_file">
	<input type="submit" name="upload"
	       value="Upload File">
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
