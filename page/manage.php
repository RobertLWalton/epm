<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Jul  2 00:04:15 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits privileges, deletes and
    // renames project problems and projects.

    // Permissions are granted by +priv+ files in
    // project and project problems.  If a user has a
    // privilege for a project, the user also has the
    // privilege for all problems in the project.
    //
    // The privileges are:
    //
    //    Project and Project Problem Permissions:
    //
    //	    owner	Right to change +priv+ file
    //			of project or problem.
    //
    //	    view	Right to view actions attached
    //			to the project or problem.
    //
    //    Project Permissions:
    //
    //
    //	    push-new	Right to push new problems into
    //			project.
    //
    //    Project Problem Permissions:
    //
    //
    //	    pull	Right to pull problem.
    //
    //	    re-push	Right to re-push problems.
    //
    // Note that an owner does not have other permis-
    // sions, but must change the +priv+ files to
    // grant needed privileges to her/himself.
    //
    // A +priv+ file consists of entries of the form:
    //
    //	    S PRIV RE
    //
    // where PRIV is one of the privilege names, S is
    // + to grant the privilege or - to deny it, and
    // RE is a regular expression matched against the
    // user's UID.  A +priv+ file line whose RE matches
    // the current UID is said to be matching.  The
    // +priv+ files are read one line at a time, and
    // the first matching line for a particular permis-
    // sion determines the result.  If there are no
    // matching lines, privilege is denied.
    // 
    // Problem privileges are determined by reading
    // the problem +priv+ file followed by the project
    // +priv+ file, and using the first matching line,
    // if any.  Thus the owner of a problem has more
    // control over the problem than the owner of the
    // project in which the problem lies.
    //
    // When a new problem is pushed, the problem is
    // given a +priv+ file giving the pusher all of
    // the above privileges.
    //
    // Lines in +priv+ files beginning with '#" are
    // treated as comment lines and are ignored, as
    // are blank lines.  REs cannot contain whitespace.

    // Session Data
    // ------- ----

    // Session data is in EPM_MANAGE as follows:
    //
    //	   EPM_MANAGE LISTNAME
    //		Current problem listname.

    // POST:
    // ----
    //
    // Each post may selects a project, problem list, or
    // problem.
    //
    //	    problem=PROJECT:PROBLEM
    //
    //	    listname=PROJECT:BASENAME

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_MANAGE'] ) )
	    $_SESSION['EPM_MANAGE'] =
	        [ 'LISTNAME' => NULL ];
    }

    $listname = & $_SESSION['EPM_MANAGE']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $project = NULL; // Project for 'project' or
    		     // 'problem' POST.
    $problem = NULL; // Problem for 'problem' POST.

    $favorites = favorites_to_list
	( ['pull','push-new','view'] );
    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }
    $list = listname_to_list ( $listname );
    $projects = read_projects
	( ['pull','push-new','view'] );

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
	    $list = listname_to_list ( $listname );
	}
        elseif ( isset ( $_POST['project'] ) )
	{
	    $project = $_POST['project'];
	    if ( ! in_array ( $project, $projects,
	                      true ) )
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
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );
    }

    unset ( $problem );
        // Causes bad title in epm_head.php if left set.

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>

div.select {
    background-color: var(--bg-green);
    padding-top: var(--pad);
}
div.priv {
    background-color: var(--bg-tan);
    padding: var(--pad) 0px;
    margin: 0px;
    display: block;
    text-align: left;
}
div.priv pre {
    margin: 0px;
    padding: var(--pad) 0px;
    display: block;
    text-align: left;
}

</style>

</head>

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

    echo <<<EOT
    <div style='background-color:orange;
                text-align:center'>
    <strong>This Page is Under Construction.</strong>
    </div>
    <div class='manage'>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:</strong>
    <button type='submit'
	    formaction='user.php'
	    title='Click to See User Profile'>
	    $email</button>
    </td>
    <td>
    <strong>Go To</strong>
    <button type='submit'
	    formaction='project.php'>
	    Project</button>
    <button type='submit'
	    formaction='list.php'>
	    Edit Lists</button>
    <button type='submit'
	    formaction='favorites.php'>
	    Edit Favorites</button>
    <strong>Page</strong>
    </td>
    <td style='text-align:right'>
    <button type='button'
            onclick='HELP("manage-page")'>
	?</button>
    </td>
    </tr>
    </table></form></div>
EOT;

    $project_options = values_to_options ( $projects );
    $listname_options = list_to_options
        ( $favorites, $listname );
    echo <<<EOT

    <div class='select'>

    <strong>Select Project</strong>
    <form method='POST' action='manage.php'
          id='project-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='project'
            onclick='document.getElementById
	                ("project-form").submit()'>
    $project_options
    </select></form>

    <br>

    <strong>or Problem List</strong>
    <form method='POST' action='manage.php'
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
	<form method='POST' action='manage.php'
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

    if ( isset ( $project ) )
    {
        $priv_file_contents = @file_get_contents
	    ( "$epm_data/projects/$project/+priv+" );
        if ( $priv_file_contents === false )
	    $priv_file_contents = 'None';
	else 
	    $priv_file_contents =
	        "\n$priv_file_contents";
        echo <<<EOT
	<div class='priv'>
	<strong>$project Privileges</strong>
	<pre>
	$priv_file_contents
	</pre>
	</div>
EOT;
    }


?>

</body>
</html>
