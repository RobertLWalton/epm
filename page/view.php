<?php

    // File:	view.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Jun  6 14:42:38 EDT 2020

    // Allows user and problem information to be viewed.

    // Session Data
    // ------- ----

    // Session data is in EPM_VIEW as follows:
    //
    //	   EPM_VIEW LISTNAME
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

    if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
        $epm_page_type = '+init+';
    else
        $epm_page_type = '+view+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_SESSION['EPM_UID'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to view.php" );
    elseif ( ! isset ( $_SESSION['EPM_EMAIL'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to view.php" );

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    if ( $epm_method == 'GET' )
    {
        require "$epm_home/include/epm_random.php";
        $_SESSION['EPM_ID_GEN']['+view+'] =
	    init_id_gen();
	$ID = bin2hex
	    ( $_SESSION['EPM_ID_GEN']['+view+'][0] );
    }

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

    
    if ( ! isset ( $_SESSION['EPM_VIEW'] ) )
        $_SESSION['EPM_VIEW'] = [ 'LISTNAME' => NULL ];
    $listname = & $_SESSION['EPM_VIEW']['LISTNAME'];

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
	    $new_listname = $_POST['listname'];
	    list ( $project, $basename ) =
	        explode ( ':', $new_listname );
	    if ( "$project:$basename" != $new_listname )
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
	    $listname = $new_listname;
	    $list = listname_to_list ( $listname );
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

@media screen and ( max-width: 1279px ) {
    :root {
	--font-size: 1.4vw;
	--large-font-size: 1.6vw;
	--indent: 1.6vw;
    }
}
@media screen and ( min-width: 1280px ) {
    :root {
	width: 1280px;

	--font-size: 16px;
	--large-font-size: 20px;
	--indent: 20px;
    }
}

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

    $user_options = users_to_options ( $users );
    $listname_options = list_to_options
        ( $favorites, $listname );
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:&nbsp;$email</strong>
    </td>
    <td>
    <form method='GET'>
    <button type='submit' formaction='template.php'>
    View Templates
    </button>
    </form>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("view-page")'>
	?</button>
    </td>
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

