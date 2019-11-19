<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 19 02:01:37 EST 2019

    // Selects user problem.
    //
    //		admin/email_index/*
    //		admin/user{$userid}.json
    //
    // containing information about user.  Gives the
    // user the option of going to user_edit.php or
    // problem.php.

    session_start();
    clearstatcache();
    if ( ! isset ( $_SESSION['epm_data'] ) )
        exit ( 'SYSTEM ERROR: epm_data not set' );
    $data = $_SESSION['epm_data'];
    $uploaded_file = NULL;

    include 'include/debug_info.php';

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }

    $userid = $_SESSION['userid'];
    $email = $_SESSION['email'];

    $user_dir = "$data/users/user$userid";

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
	    "$data/users/user$userid/$problem";

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
		      ( "$data/users/user$userid" .
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
	$f = basename
	    ($_FILES['uploaded_file']['name']);
        $f = "$data/uploads/$f";
	if ( move_uploaded_file
	         ( $_FILES['uploaded_file']['tmp_name'],
	           $f ) )
	    $uploaded_file = $f;
    }


?>


<html>
<body>

<div style="background-color:#c0ffff;width:30%;float:left">
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
    <label for="problem">Create New Problem:</label>
    <input type="text" size="32" name="new_problem"
           placeholder="New Problem Name">
    </form>

    <br><br>

    <form enctype="multipart/form-data"
          action="problem.php" method="post">
    <input type="hidden" name="MAX_FILE_SIZE"
	   value="2000000">
    <label for="uploaded_file">File to Upload:</label>
    <input type="file" name="uploaded_file">
    <input type="submit" name="upload"
           value="Upload File">
    </form>
EOT;

?>
</div>

<?php

    if ( isset ( $uploaded_file ) )
    {
        $contents = file_get_contents
	    ( $uploaded_file );
        echo "<div style='background-color:#ffffc0;" .
	     "width:70%;height:60%;float:left;overflow:scroll'>" .
	     "<pre>$contents</pre></div>";
    }
?>

</body>
</html>
