<?php

    // File:	view.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Jul 12 17:21:07 EDT 2020

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
    //	    user=UID
    //
    //	    project=UID
    //
    //	    problem=PROJECT:PROBLEM
    //
    //	    listname=PROJECT:BASENAME

    if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
        $epm_page_type = '+init+';
    else
        $epm_page_type = '+view+';
    require __DIR__ . '/index.php';

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
    require "$epm_home/include/epm_view.php";

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

    if ( ! isset ( $_SESSION['EPM_VIEW'] ) )
        $_SESSION['EPM_VIEW'] = [ 'LISTNAME' => NULL ];
    $listname = & $_SESSION['EPM_VIEW']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $user = NULL;    // UID for 'user' POST.
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
    $users = read_users();

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

let on = 'black';
let off = 'white';
function INCLUDE ( checkbox, c )
{
    var onoff = checkbox.style.backgroundColor;
    var display;
    if ( onoff == on )
    {
        onoff = off;
	display = 'none';
    }
    else
    {
        onoff = on;
	display = 'table-row';
    }
    checkbox.style.backgroundColor = onoff;
    var rows = document.getElementsByClassName ( c );
    for ( var i = 0; i < rows.length; ++ i )
        rows[i].style.display = display;
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
    <pre>     </pre>
    <strong>Include:</strong>
    <pre> </pre>
    <div class='checkbox'
         style='background-color:black'
	 onclick='INCLUDE(this,"submit")'></div>
    <strong>submit</strong>
    <div class='checkbox'
         style='background-color:black'
	 onclick='INCLUDE(this,"push")'></div>
    <strong>push</strong>
    <div class='checkbox'
         style='background-color:black'
	 onclick='INCLUDE(this,"pull")'></div>
    <strong>pull</strong>
    <div class='checkbox'
         style='background-color:black'
	 onclick='INCLUDE(this,"other")'></div>
    <strong>other</strong>


    <br>
EOT;
    if ( isset ( $problem ) )
	$key = "$project:$problem";
    else
	$key = NULL;
    $problem_options = list_to_options
	( $list, $key );
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
	    ( read_actions ( "$f" ), $non_others );
	$g = "users/$user/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$g" ), $non_others );
        echo <<<EOT
	<div class='user'>

	<div class='profile'>
	<strong>$user Profile:</strong>
	<table>
	$info_rows
	</table>
	</div>

	<div class='emails'>
	<strong>$user Emails:</strong>
	<br>
	$lines
	</div>

	<div style='clear:both'></div>
	    <!-- Needed to make div.user height =
		 max height of contents. -->
	</div>

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
	    <pre id='user_actions_mark'>&darr;</pre>
	    </button>
	<strong>Other Actions of $user
	        (most recent first):</strong>
	</td>
	</tr></table>
	<div id='user_actions_body'
	     style='display:none'>
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
	    $f = "users/$uid/$problem/+actions+";
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

