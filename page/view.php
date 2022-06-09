<?php

    // File:	view.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Jun  9 16:56:28 EDT 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Allows account, project, problem, and published
    // list actions to be viewed.

    // Session Data
    // ------- ----

    // Session data is in EPM_VIEW as follows:
    //
    //	   EPM_VIEW LISTNAME
    //		Current problem listname.

    // POST:
    // ----
    //
    // Each post may select an account, project,
    // problem, problem list, or published-lists.
    //
    //	    account=AID
    //
    //	    project=PROJECT
    //
    //	    problem=PROJECT:PROBLEM
    //
    //	    listname=PROJECT:BASENAME
    //
    //	    published-lists

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
    $account = NULL; // AID for 'account' POST.
    $project = NULL; // Project for 'project' or
    		     // 'problem' POST.
    $problem = NULL; // Problem for 'problem' POST.
    $published_lists = false;
    		     // True for 'published-lists' POST.

    $favorites = read_favorites_list ( $warnings );
    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }
    $list = read_problem_list ( $listname, $warnings );
    $projects = read_projects ( ['view'] );
    $users = read_accounts ( 'user' );
    $teams = read_accounts ( 'team' );
    $accounts = array_merge ( $users, $teams );

    if ( $epm_method == 'GET' )
    {
        if ( isset ( $_GET['project'] ) )
	{
	    $project = $_GET['project'];
	    if ( $project == '' )
	        $project = NULL;
	    elseif ( ! in_array ( $project, $projects,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
    }
    else // if $epm_method == 'POST'
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
        elseif ( isset ( $_POST['account'] ) )
	{
	    $account = $_POST['account'];
	    if ( $account == '' )
	        $account = NULL;
	    elseif ( ! in_array
	                   ( $account, $accounts ) )
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
        elseif ( isset ( $_POST['published-lists'] ) )
	    $published_lists = true;
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );
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
div.account {
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

function KEYMAP_STRINGIFY ( )
{
    var r = ''
    keymap.forEach ( function ( value, key, map )
                     {
		         r += ' ' + key;
		     }
		   );
    return r.substring ( 1 );
}

function LOGEXP_COMPILE ( logexp_src )
{
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
    for ( var i = 0; i < keys.length; ++ i )
        keymap.delete ( keys[i] );

    or_loop: for ( var i = 0; i < logexp.length; ++ i )
    {
        for ( var j = 0; j < logexp[i].length; ++ j )
	{
	    let keyword = logexp[i][j];
	    if ( keyword[0] == '-' )
	    {
	        if ( keys.includes
		         ( keyword.substring(1) ) )
		    continue or_loop;
	    }
	    else if ( ! keys.includes ( keyword ) )
	        continue or_loop;
	}
	return true;
    }
    return false;
}

function LOGEXP_EXECUTE ( )
{
    keymap.clear();
    if ( logexp !== null )
    for ( var i = 0; i < logexp.length; ++ i )
    for ( var j = 0; j < logexp[i].length; ++ j )
    {
	var keyword = logexp[i][j];
	if ( keyword[0] == '-' )
	    keyword = keyword.substring ( 1 );
	keymap.set ( keyword, true );
    }

    var rows =
        document.getElementsByClassName ( 'row' );
    for ( var i = 0; i < rows.length; ++ i )
    {
	if ( logexp === null
	     ||
	     LOGEXP_APPLY ( rows[i].dataset.keys ) )
	    rows[i].style.display = 'table-row';
	else
	    rows[i].style.display = 'none';
    }

    var r = KEYMAP_STRINGIFY();
    if ( r == '' )
	document.getElementById ( 'logexp-unused' )
	        .style.display = 'none'
    else
    {
	document.getElementById ( 'logexp-unused' )
	        .style.display = 'block'
	document.getElementById ( 'unused-keywords' )
	        .innerText = r;
    }
}

function LOGEXP_KEYDOWN ( event )
{
    if ( event.code === 'Enter' )
    {
	event.preventDefault();
	let logexp_src =
	    document.getElementById ( 'logexp' );
	LOGEXP_COMPILE ( logexp_src.value );
	LOGEXP_EXECUTE();
    }
}
function LOGEXP_CLEAR ( event )
{
    event.preventDefault();
    logexp = null;
    document.getElementById ( 'logexp' )
            .value = '';
    LOGEXP_EXECUTE();
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

    $account_options =
        values_to_options ( $accounts, $account );
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

    <strong>Select Account:</strong>
    <form method='POST' action='view.php'
          id='account-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='account'
            onchange='document.getElementById
	                ("account-form").submit()'>
    <option value=''>No Account Selected</option>
    $account_options
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

    <strong>or</strong>
    <form method='POST' action='view.php'>
    <input type='hidden' name='id' value='$ID'>
    <button type='submit' name='published-lists'>
    Published Lists</button>
    </form>

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
    <button type='button' onclick='LOGEXP_CLEAR(event)'
            title='$clear_title'>
    Clear</button>
    </div>

    <div class='errors' id='logexp-unused'
         style='display:none;clear:both'>
    <strong>Unused Keywords:</strong>
    <pre id='unused-keywords'></pre>
    </div>
EOT;

    if ( isset ( $account ) )
    {
	if ( in_array ( $account, $users ) )
	    $f = "admin/users/$account/+actions+";
	else
	    $f = "admin/teams/$account/+actions+";
	$change_rows = actions_to_rows
	    ( read_actions ( "$f" ) );
	$g = "accounts/$account/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$g" ) );
        echo <<<EOT
	<div class='account'>

	<div class='changes'>
	<table style='width:100%'><tr>
	<td>
	<button type='button'
	    id='account_info_button'
	    onclick='TOGGLE_BODY
		 ("account_info",
		  "Changes to Account Profile")'
	    title='Show Changes to Account Profile'>
	    <pre id='account_info_mark'>&darr;</pre>
	    </button>
	<strong>Actions Changing $account
	        Profile
	        (most recent first):</strong>
	</td>
	</tr></table>
	<div id='account_info_body'
	     style='display:none'>
	<table>
	$change_rows
	</table>
	</div>
	</div>

	<div class='actions'>
	<table style='width:100%'><tr>
	<td>
	<button type='button'
	    id='account_actions_button'
	    onclick='TOGGLE_BODY
		 ("account_actions",
		  "Account Actions")'
	    title='Show Account Actions'>
	    <pre id='account_actions_mark'>&uarr;</pre>
	    </button>
	<strong>Other Actions of $account
	        (most recent first):</strong>
	</td>
	</tr></table>
	<div id='account_actions_body'>
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
	    ( read_actions ( "$g" ) );
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
	    ( read_actions ( "$f" ) );
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

    if ( $published_lists )
    {
	$g = "lists/+actions+";
	$action_rows = actions_to_rows
	    ( read_actions ( "$g" ) );
        echo <<<EOT
	<div class='actions'>
	<table style='width:100%'><tr>
	<td>
	<strong>Actions on Published Lists
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
