<?php

    // File:	view.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun  1 23:34:16 EDT 2020

    // Allows user and problem information to be viewed.

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //	   EPM_DATA LISTNAME
    //		Current problem listname.

    // POST:
    // ----
    //
    // Each post may selects a user, problem list, or
    // problem.
    //
    //	    user=UID
    //
    //	    problem=PROJECT:PROBLEM
    //
    //	    listname=PROJECT:BASENAME

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_user.php";
    require "$epm_home/include/epm_list.php";

    // Get list of users.
    //
    function read_users ()
    {
        global $epm_data, $epm_name_re;

	$r = [];
	$d = '/admin/users';
	$c = @scandir ( "$epm_data/$d" );
	if ( $c === false )
	    ERROR ( "cannot read $d" );
	foreach ( $c as $u )
	{
	    if ( preg_match ( $epm_name_re, $u ) )
		$r[] = $u;
	}
	return $r;
    }

    // Return HTML of options from list of users.
    //
    function users_to_options ( $users )
    {
	$r = '';
	foreach ( $users as $u )
	    $r .= "<option value='$u'>$u</option>";
	return $r;
    }

    if ( $epm_method == 'GET' )
        $_SESSION['EPM_DATA'] = [ 'LISTNAME' => NULL ];
    elseif ( ! isset ( $_SESSION['EPM_DATA'] ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $data = & $_SESSION['EPM_DATA'];
    $listname = $data['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $user = NULL;    // UID for 'user' POST.
    $project = NULL; // Project for 'problem' POST.
    $problem = NULL; // Problem for 'problem' POST.

    $favorites = favorites_to_list ( 'pull|push' );
    $list = NULL;
    if ( isset ( $listname ) )
        $list = listname_to_list ( $listname );
    $users = read_users();

    if ( $epm_method == 'POST' )
    {
        if ( isset ( $_POST['listname'] ) )
	{
	    $listname = $_POST['listname'];
	    list ( $project, $basename ) =
	        explode ( ':', $listname );
	    if ( "$project:$basename" != $listname )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $found = false;
	    foreach ( $favorites as $item )
	    {
	        list ( $time, $p, $b ) = $item;
		if ( $p == $project && $b == $basename )
		{
		    $found = true;
		    break;
		}
	    }
	    if ( ! $found )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $list = listname_to_list ( $listname );
	    $data['LISTNAME'] = $listname;
	}
        elseif ( isset ( $_POST['user'] ) )
	{
	    $user = $_POST['user'];
	    if ( ! preg_match ( $epm_name_re, $user ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $d = "admin/users/$user";
	    if ( ! is_dir ( "$epm_data/$d" ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
        elseif ( isset ( $_POST['problem'] ) )
	{
	    list ( $project, $problem ) =
	        explode ( ':', $_POST['problem'] );
	    if (    "$project:$problem"
	         != $_POST['problem'] )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $found = false;
	    foreach ( $list as $item )
	    {
	        list ( $time, $proj, $prob ) = $item;
		if (    $proj == $project
		     && $prob == $problem )
		{
		    $found = true;
		    break;
		}
	    }
	    if ( ! $found )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>

div.select {
    background-color: var(--bg-green);
    padding-top: var(--pad);
}
div.user {
    background-color: var(--bg-tan);
    padding-top: var(--pad);
}
div.profile {
    float: left;
}
div.profile table {
    font-size: var(--large-font-size);
    padding-left: var(--pad);
}
div.profile th {
    text-align: right;
    padding-right: var(--pad);
}
div.emails {
    float: left;
    margin-left: 3em;
}
div.emails pre {
    font-size: var(--large-font-size);
    padding-left: var(--large-font-size);
}
div.changes {
    background-color: var(--bg-blue);
    padding-top: var(--pad);
}
div.changes table {
}
div.changes td {
    padding-left: var(--font-size);
}

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

</script>

</head>
<body>

<?php 

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

    $view_help = HELP ( 'view-page' );
    $user_options = users_to_options ( $users );
    $listname_options = list_to_options
        ( $favorites, $listname );
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <form>
    <input type='hidden' name='id' value='$ID'>
    <label>
    <strong>User:</strong>
    <input type='submit' value='$email'
	   formaction='user.php'
	   formmethod='GET'
           title='Click to See User Profile'>
    </label>
    </form>
    </td>
    <td>
    <strong>Go To</strong>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <button type='submit' formaction='project.php'>
    Project
    </button>
    <button type='submit' formaction='problem.php'>
    Problem
    </button>
    </form>
    <strong>Page</strong>
    </td>
    <td>
    </td><td style='text-align:right'>
    $view_help</td>
    </tr>
    </table>
    </div>

    <div class='select'>

    <strong>Select User</strong>
    <form method='POST' action='view.php'
          id='user-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='user'
            onclick='document.getElementById
	                ("user-form").submit()'>
    $user_options
    </select></form>

    <strong>or Problem List</strong>
    <form method='POST' action='view.php'
          id='listname-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='listname'
            onclick='document.getElementById
	                ("listname-form").submit()'>
    $listname_options
    </select></form>
EOT;
    if ( isset ( $list ) )
    {
	$problem_options = list_to_options ( $list );
	list ( $proj, $prob ) =
	    explode ( ':',  $listname );
	if ( $proj == '-' ) $proj = '<i>Your</i>';
	if ( $prob == '-' ) $prob = '<i>Problems</i>';
    	echo <<<EOT

	<strong>or Problem from $proj $prob</strong>
	<form method='POST' action='view.php'
	      id='problem-form'>
	<input type='hidden' name='id' value='$ID'>
	<select name='problem'
		onclick='document.getElementById
			    ("problem-form").submit()'>
	$problem_options
	</select></form>
EOT;
    }

    echo <<<EOT
    </div>
EOT;

    if ( isset ( $user ) )
    {
	$info = read_uid_info ( $user );
        $info_rows = user_info_to_rows ( $info );
	$lines = emails_to_lines ( $info['emails'] );
	$lines = preg_replace
	    ( '/<pre>[^@]+@/', '<pre>...@', $lines );
	$f = "admin/users/$user/+changes+";
	$change_rows = actions_to_rows
	    ( read_actions ( "$f" ) );
        echo <<<EOT
	<div class='user'>

	<div class='profile'>
	<strong>$uid Profile:</strong>
	<table>
	$info_rows
	</table>
	</div>

	<div class='emails'>
	<strong>$uid Emails:</strong>
	<br>
	$lines
	</div>

	<div style='clear:both'></div>
	    <!-- Needed to make div.user height =
		 max height of contents. -->
	</div>

	<div class='changes'>
	<strong>Changes to $uid Profile and Emails
	        (most recent first):</strong>
	<table>
	$change_rows
	</table>
	<div>
EOT;
    }

?>

</body>
</html>

