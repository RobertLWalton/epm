<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Nov 12 13:51:37 EST 2019

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
    if ( isset ( $_REQUEST['problem'] ) )
    {
        $problem = trim ( $_REQUEST['problem'] );
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

	if (    $method == 'POST'
	     && isset ( $_POST['submit'] )
	     &&    $_POST['submit']
	        == 'Create New Problem:' )
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

?>


<html>
<body>

<?php 

    if ( isset ( $problem_error ) )
        echo "<mark>ERROR:" .
	     " $problem_error</mark><br><br>\n";
    echo "User: $email&nbsp;&nbsp;&nbsp;&nbsp;";
    echo "Current Problem: " . ( isset ( $problem ) ?
                                 $problem :
			         "none selected" );
    echo "<br><br>\n";
    echo "<form action='problem.php' method='POST'>\n";
    if ( count ( $problems ) > 0 )
    {
	echo "<input type='submit' value='Go To Problem:'>\n";
        echo "<select name='problem'>\n";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>\n";
        echo "</select>\n";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
    }
    echo <<<EOT
    <input type="submit" name="submit"
           value="Create New Problem:"</input>
    <input type="text" size="32" name="problem"
           placeholder="New Problem Name">
    </form>
EOT

?>

</body>
</html>
