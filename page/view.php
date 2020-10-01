<?php

    // File:	view.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Oct  1 02:05:15 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

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
    // Each post may selects a user, project, problem
    // list, or problem.
    //
    //	    user=AID
    //
    //	    project=PROJECT
    //
    //	    problem=PROJECT:PROBLEM
    //
    //	    listname=PROJECT:BASENAME

    $epm_page_type = '+view+';
    $epm_ID_init = true;
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_user.php";
    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_view.php";

    if ( ! isset ( $_SESSION['EPM_VIEW'] ) )
        $_SESSION['EPM_VIEW'] = [ 'LISTNAME' => NULL ];
    $listname = & $_SESSION['EPM_VIEW']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $user = NULL;    // AID for 'user' POST.
    $project = NULL; // Project for 'project' or
    		     // 'problem' POST.
    $problem = NULL; // Problem for 'problem' POST.

    $non_others = ['submit','push','pull'];
        // List for actions_to_rows.

    $favorites = read_favorites_list ( $warnings );
    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }
    $list = read_problem_list ( $listname, $warnings );
    $projects = read_projects ( ['view'] );
    $users = read_accounts ( 'user' );

    if ( $epm_method == 'POST' )
    {
        if ( isset ( $_POST['listname'] ) )
	{
	    $new_listname = $_POST['listname'];
	    list ( $proj, $basename ) =
	        explode ( ':', $new_listname );
	    if ( "$proj:$basename" != $new_listname )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $found = false;
	    foreach ( $favorites as $item )
	    {
	        list ( $time, $p, $b ) = $item;
		if ( $p == $proj && $b == $basename )
		{
		    $found = true;
		    break;
		}
	    }
	    if ( ! $found )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $listname = $new_listname;
	    $list = read_problem_list
	        ( $listname, $warnings );
	}
        elseif ( isset ( $_POST['user'] ) )
	{
	    $user = $_POST['user'];
	    if ( $user == '' )
	        $user = NULL;
	    elseif ( ! in_array ( $user, $users,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
        elseif ( isset ( $_POST['project'] ) )
	{
	    $project = $_POST['project'];
	    if ( $project == '' )
	        $project = NULL;
	    elseif ( ! in_array ( $project, $projects,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
        elseif ( isset ( $_POST['problem'] ) )
	{
	    $key = $_POST['problem'];
	    if ( $key != '' )
	    {
		list ( $project, $problem ) =
		    explode ( ':', $key );
		if (    "$project:$problem"
		     != $_POST['problem'] )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		$found = false;
		foreach ( $list as $item )
		{
		    list ( $time, $proj, $prob ) =
		    	$item;
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
div.changes {
    background-color: var(--bg-blue);
    padding-top: var(--pad);
}
div.actions {
    background-color: var(--bg-violet);
    padding-top: var(--pad);
}
div.changes td, div.actions td {
    padding-left: var(--font-size);
}

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

function TOGGLE_BODY ( name, thing )
{
    var BUTTON = document.getElementById
	    ( name + '_button' );
    var MARK = document.getElementById
	    ( name + '_mark' );
    var BODY = document.getElementById
	    ( name + '_body' );
    if ( BODY.style.display == 'none' )
    {
	MARK.innerHTML = "&uarr;";
	BUTTON.title = "Hide " + thing;
	BODY.style.display = 'block';
    }
    else
    {
	MARK.innerHTML = "&darr;";
	BUTTON.title = "Show " + thing;
	BODY.style.display = 'none';
    }
}

var logexp = null;
var keymap = new Map();

function LOGEXP_COMPILE ( logexp_src )
{
    keymap.clear();
    logexp_src = logexp_src.trim();
    if ( logexp_src == '' )
    {
        logexp = null;
	return;
    }
    logexp = [];
    logexp_src = logexp_src.split ( /\s+/ );
    for ( var i = 0; i < logexp_src.length; ++ i )
    {
        let term = logexp_src[i].split ( '&' );
	logexp.push ( term );
	for ( var j = 0; j < term.length; ++ j )
	    keymap.set ( term[j], true );
    }
}

function LOGEXP_APPLY ( keys )
{
    if ( logexp === null ) return true;
    keys = keys.split ( ':' );
    or_loop: for ( var i = 0; i < logexp.length; ++ i )
    {
        for ( var j = 0; j < logexp[i].length, ++ j )
	{
	    if ( ! keys.includes ( logexp[i][j] )
	        continue or_loop;
	    else
	        keymap.delete ( logexp[i][j] );
	}
	return true;
    }
    return false;
}

function LOGEXP_EXECUTE ( )
{
    var rows = document.getElementsByClassName ( 'row' );
    for ( var i = 0; i < rows.length; ++ i )
    {
	if ( logexp === null
	     ||
	     LOGEXP_APPLY ( rows[i].dataset.keys ) )
	    rows[i].style.display = 'table-row';
	else
	    rows[i].style.display = 'none';
    }
}

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

    $user_options =
        values_to_options ( $users, $user );
    $project_options =
        values_to_options
	    ( $projects,
	      isset ( $problem ) ? NULL : $project );
    $listname_options = list_to_options
        ( $favorites, $listname );
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong title='Login Name'>$lname</strong>
    </td>
    <td>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button' id='refresh'
            onclick='location.replace
	        ("view.php")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("view-page")'>
	?</button>
    </td>
    </tr>
    </table>
    </div>

    <div class='select'>

    <strong>Select User:</strong>
    <form method='POST' action='view.php'
          id='user-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='user'
            onchange='document.getElementById
	                ("user-form").submit()'>
    <option value=''>No User Selected</option>
    $user_options
    </select></form>

    <strong>or Project:</strong>
    <form method='POST' action='view.php'
          id='project-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='project'
            onchange='document.getElementById
	                ("project-form").submit()'>
    <option value=''>No Project Selected</option>
    $project_options
    </select></form>

    <br>
EOT;
    if ( isset ( $problem ) )
	$key = "$project:$problem";
    else
	$key = NULL;
    $problem_options = list_to_options
	( $list, $key );
    $include_placeholder =
	'logical expression' .
	' (clear to include all actions)';
    $clear_title =
	'clearing logical expression includes' .
	' all actions';
    echo <<<EOT

    <strong>or Problem:</strong>
    <form method='POST' action='view.php'
	  id='problem-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='problem'
	    onchange='document.getElementById
			("problem-form").submit()'>
    <option value=''>No Problem Selected</option>
    $problem_options
    </select></form>
    <strong>from Problem List:</strong>
    <form method='POST' action='view.php'
          id='listname-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='listname'
            onchange='document.getElementById
	                ("listname-form").submit()'>
    $listname_options
    </select></form>

    <br>
    <strong>Include Actions:</strong>
    <pre> </pre>
    <input type='text' id='logexp' size='60'
	   placeholder='$include_placeholder'
	   title=
	     'type logical expression and then enter'
	   onkeydown='LOGEXP_KEYDOWN(event)'
           value=''>
    <pre> </pre>
    <button type='button' onclick='LOGEXP_CLEAR()'
            title='$clear_title'>
    Clear</button>

    </div>
EOT;

    if ( isset ( $user ) )
    {
	$f = "admin/users/$user/+actions+";
	$change_rows = actions_to_rows
	    ( read_actions ( "$f" ), $non_others );
	$g = "accounts/$user/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$g" ), $non_others );
        echo <<<EOT
	<div class='user'>

	<div class='changes'>
	<table style='width:100%'><tr>
	<td>
	<button type='button'
	    id='user_info_button'
	    onclick='TOGGLE_BODY
		 ("user_info",
		  "Changes to User Information")'
	    title='Show Changes to User Information'>
	    <pre id='user_info_mark'>&darr;</pre>
	    </button>
	<strong>Actions Changing $user
	        Profile and Emails
	        (most recent first):</strong>
	</td>
	</tr></table>
	<div id='user_info_body' style='display:none'>
	<table>
	$change_rows
	</table>
	</div>
	</div>

	<div class='actions'>
	<table style='width:100%'><tr>
	<td>
	<button type='button'
	    id='user_actions_button'
	    onclick='TOGGLE_BODY
		 ("user_actions",
		  "User Actions")'
	    title='Show User Actions'>
	    <pre id='user_actions_mark'>&uarr;</pre>
	    </button>
	<strong>Other Actions of $user
	        (most recent first):</strong>
	</td>
	</tr></table>
	<div id='user_actions_body'>
	<table>
	$action_rows
	</table>
	</div>
	</div>
EOT;
    }

    if ( isset ( $project ) && ! isset ( $problem ) )
    {
	$g = "projects/$project/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$g" ), $non_others );
        echo <<<EOT
	<div class='actions'>
	<table style='width:100%'><tr>
	<td>
	<strong>Actions on $project Project
	        (most recent first):</strong>
	</td>
	</tr></table>
	<table>
	$action_rows
	</table>
	</div>
EOT;
    }

    if ( isset ( $project ) && isset ( $problem ) )
    {
	if ( $project == '-' )
	    $f = "accounts/$aid/$problem/+actions+";
	else
	    $f = "projects/$project/$problem/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$f" ), $non_others );
	if ( $project == '-' ) $project = '<i>Your</i>';
        echo <<<EOT
	<div class='actions'>
	<table style='width:100%'><tr>
	<td>
	<strong>Actions on $project $problem
	        (most recent first):</strong>
	</td>
	</tr></table>
	<table>
	$action_rows
	</table>
	</div>
EOT;
    }

?>

</body>
</html>

