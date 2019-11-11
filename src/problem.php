<?php

    // File:	problem.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Nov 10 20:02:48 EST 2019

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
    $method = $_SERVER['REQUEST_METHOD'];

    if ( ! isset ( $_SESSION['confirmation_time'] ) )
    {
	header ( "Location: login.php" );
	exit;
    }

    $userid = $_SESSION['userid'];
    $email = $_SESSION['email'];

    if ( $userid == 'NEW'
         ||
	 ! is_writable ( "users/user$userid" ) )
    {
	header ( "Location: user_edit.php" );
	exit;
    }

    // Set $problems to list of available problems.
    //
    $problems = [];

    $desc = opendir ( 'users/user$userid' );
    if ( ! $desc )
        exit ( 'SYSTEM ERROR: cannot open' .
	        " users/user$userid" );
    {
	$value = readdir ( $desc );
	if ( ! $value )
	{
	    closedir ( $desc );
	    break;
	}
	$problems[] = $value;
    }

    // Set $problem to current problem, or NULL if none.
    //
    $problem = NULL;
    $problem_error = NULL;
    if (    $method == 'GET'
         && isset ( $_GET['problem'] ) )
    {
        $problem = $_GET['problem'];
	if ( ! preg_match ( '/^[-_A-Za-z0-9]+$/',
	                    $problem )
	     ||
	     ! preg_match ( '[A-Za-z]', $problem ) )
	{
	    $problem_error =
	        "problem name $problem contains an" .
		" illegal character";
	    $problem = NULL;
	}
	else if ( ! is_writable
		      ( "users/user$userid/$problem" ) )
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
    else if ( isset ( $problem ) )
        $_SESSION['problem'] = $problem;


<html>
<body>

<?php 

    if ( isset ( $problem_error ) )
        echo "<mark>ERROR:" .
	     " $problem_error</mark><br<br>\n";
    echo "User: $mail&nbsp;&nbsp;&nbsp;&nbsp;";
    echo "Problem: " . ( isset ( $problem ) ?
                         $problem :
			 "none selected" );
    if ( count ( $problems ) > 0 )
    {
	echo '<form action="src/problem.php"' .
		  ' method="POST">' . "\n";
	echo "<button type='submit'>Go To Problem:" .
	     "</button>\n";
        echo "<select name='problem'>\n";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>\n"
        echo "</select>\n";
        echo "</form>\n";
        echo "<br>\n";
    }
    echo <<<EOT
    <form action="src/problem.php" method="POST">
    <button type="submit">Create New Problem:</button>
    <input type="text" maxlength="32" name="problem">
    </form>
EOT

?>

</body>
</html>
