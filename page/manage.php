<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Jul  9 16:24:17 EDT 2020

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

    require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_MANAGE'] ) )
	    $_SESSION['EPM_MANAGE'] =
	        [ 'LISTNAME' => NULL ];
	$_SESSION['EPM_DATA'] =
		[ 'PROJECT' => NULL,
		  'PROBLEM' => NULL ];
    }

    $listname = & $_SESSION['EPM_MANAGE']['LISTNAME'];
    $data = & $_SESSION['EPM_DATA'];
    $project = & $data['PROJECT'];
    $problem = & $data['PROBLEM'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $edited_contents = NULL;
        // Contents of edited version of +priv+.
    $owner_warn = false;
        // True to ask if its ok to accept $edited_
	// contents which removes $uid from ownership.
    $move_warn = NULL;
        // If not NULL, ask if problem should be
	// moved to this project.

    $favorites = read_favorites_list
	( ['pull','push-new','view'], $warnings );
    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }
    $list = read_problem_list ( $listname, $warnings );
    $priv_projects = read_projects
	( ['owner','pull','push-new','view'] );
    $move_projects = read_projects
	( ['push-new'] );

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
	    $project = NULL;
	    $problem = NULL;
	}
        elseif ( isset ( $_POST['project'] ) )
	{
	    $proj = $_POST['project'];
	    if ( $proj == '' )
	    {
		$project = NULL;
		$problem = NULL;
	    }
	    elseif ( ! in_array ( $proj, $priv_projects,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    else
	    {
		$project = $proj;
		$problem = NULL;
	    }
	}
        elseif ( isset ( $_POST['problem'] ) )
	{
	    $key = $_POST['problem'];
	    if ( $key == '' )
	    {
		$project = NULL;
		$problem = NULL;
	    }
	    else
	    {
		list ( $proj, $prob ) = explode
		    ( ':', $key );
		if (    "$proj:$prob"
		     != $_POST['problem'] )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		$found = false;
		foreach ( $list as $item )
		{
		    list ( $time, $pj, $pb ) = $item;
		    if (    $pj == $proj
			 && $pb == $prob )
		    {
			$found = true;
			break;
		    }
		}
		if ( ! $found )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		if ( $proj == '-' )
		    $errors[] = 'You must select a'
		              . ' project problem';
		else
		{
		    $project = $proj;
		    $problem = $prob;
		}
	    }
	}
        elseif ( isset ( $_POST['warning'] )
	         &&
		 $_POST['warning'] == 'no' )
	    /* do nothing */;
        elseif ( isset ( $_POST['problem-priv'] ) )
	{
	    if ( ! isset ( $_POST['warning'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

   	    $edited_contents = $_POST['problem-priv'];
   	    $warn = $_POST['warning'];
	    $r = check_priv_file_contents
	        ( $edited_contents, $errors,
		  "In Proposed Problem Privilege" .
		  " File:" );
	    if ( count ( $errors ) == 0 )
	    {
	    	if ( ! isset ( $r ) )
		{
		    project_priv_map
		        ( $pmap, $project );
		    if ( isset ( $pmap['owner'] ) )
		        $r = $pmap['owner'];
		}
		if ( $r == '+' || $warn == 'yes' )
		{
		    $f = "/projects/$project/$problem/"
		       . "+priv+";
		    $r = @file_put_contents
		        ( "$epm_data/$f",
			  $edited_contents );
		    if ( $r === false )
		        ERROR ( "cannot write $f" );

		    $time = @filemtime
		        ( "$epm_data/$f" );
		    if ( $time === false )
			ERROR ( "cannot stat $f" );
		    $time = strftime
		        ( $epm_time_format, $time );
		    $action = "$time $uid update"
		            . " $project $problem"
			    . " privileges"
			    . PHP_EOL;

		    $f = "projects/$project/+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );

		    $f = "projects/$project/$problem/"
		       . "+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );

		    $edited_contents = NULL;
		    $problem = NULL;
		    $project = NULL;
		}
		else
		    $owner_warn = true;
	    }
	}
        elseif ( isset ( $_POST['project-priv'] ) )
	{
	    if ( ! isset ( $_POST['warning'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( isset ( $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

   	    $edited_contents = $_POST['project-priv'];
   	    $warn = $_POST['warning'];
	    $r = check_priv_file_contents
	        ( $edited_contents, $errors,
		  "In Proposed Project Privilege" .
		  " File:" );
	    if ( count ( $errors ) == 0 )
	    {
		if ( $r == '+' || $warn == 'yes' )
		{
		    $f = "/projects/$project/+priv+";
		    $r = @file_put_contents
		        ( "$epm_data/$f",
			  $edited_contents );
		    if ( $r === false )
		        ERROR ( "cannot write $f" );

		    $time = @filemtime
		        ( "$epm_data/$f" );
		    if ( $time === false )
			ERROR ( "cannot stat $f" );
		    $time = strftime
		        ( $epm_time_format, $time );
		    $action = "$time $uid update"
		            . " $project project"
			    . " privileges"
			    . PHP_EOL;

		    $f = "projects/$project/+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );

		    $edited_contents = NULL;
		    $project = NULL;
		}
		else
		    $owner_warn = true;
	    }
	}
        elseif ( isset ( $_POST['move'] ) )
	{
	    $proj = $_POST['move'];
	    if ( ! isset ( $_POST['warning'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( $proj == '' )
	        /* do nothing */;
	    elseif ( ! in_array ( $proj, $move_projects,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( ! isset ( $problem ) )
		$errors[] = "you must select a problem"
		          . " first";
	    elseif ( $proj == $project )
		$errors[] = "problem is aleady in $proj"
		          . " project";
	    elseif ( $_POST['warning'] == 'yes' )
	        $errors[] = "moving not yet"
		          . " implemented";
	    else
	        $move_warn = $proj;
	}
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );
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
div.project, div.problem {
    background-color: var(--bg-tan);
    padding: var(--pad) 0px;
    margin: 0px;
    display: inline-block;
    float: left;
    width: 50%;
}
div.project {
    background-color: var(--bg-tan);
}
div.problem {
    background-color: var(--bg-blue);
}
div.priv {
    margin-left: 2%;
    border: black solid 1px;
    width: 95%;
}
div.priv pre {
    font-size: var(--large-font-size);
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
    if ( $owner_warn )
    {
        if ( isset ( $problem ) ) $type = 'problem';
	else                      $type = 'project';

        echo <<<EOT
	<div class='warnings'>
	<strong>WARNING: you will lose owner privileges
	        with this change;
		<br>
		Do you want to continue?</strong>
	<pre>   </pre>
	<button type='button'
		onclick='COPY("$type","yes")'>
	     YES</button>
	<pre>   </pre>
	<button type='button'
		onclick='COPY("$type","no")'>
	     NO</button>
	<br></div>
EOT;
    }
    if ( isset ( $move_warn ) )
    {
        echo <<<EOT
	<div class='warnings'>
	<strong>WARNING: do you really want to move
	                 $problem from $project
			 to $move_warn?</strong>
	<pre>   </pre>
	<button type='button'
	        onclick='MOVE("yes")'>
	     YES</button>
	<pre>   </pre>
	<button type='button'
		onclick='MOVE("no")'>
	     NO</button>
	<br></div>
EOT;
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

    $project_options =
        values_to_options ( $priv_projects, $project );
    $move_options =
        values_to_options
	    ( $move_projects, $move_warn );
    $listname_options = list_to_options
        ( $favorites, $listname );
    if ( isset ( $problem ) )
        $key = "$project:$problem";
    else
        $key = NULL;
    $problem_options = list_to_options
        ( $list, $key );
    echo <<<EOT

    <div class='select'>
    <strong>Select Problem:</strong>
    <form method='POST' action='manage.php'
	  id='problem-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='problem'
	    onchange='document.getElementById
			("problem-form").submit()'>
    <option value=''>No Problem Selected</option>
    $problem_options
    </select></form>
    <strong>from Problem List:</strong>
    <form method='POST' action='manage.php'
          id='listname-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='listname'
            onchange='document.getElementById
	                ("listname-form").submit()'>
    $listname_options
    </select></form>

    <br>

    <strong>or Select Project</strong>
    <form method='POST' action='manage.php'
          id='project-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='project'
            onchange='document.getElementById
	                ("project-form").submit()'>
    <option value=''>No Project Selected</option>
    $project_options
    </select></form>

    <strong>or Move Problem to Project</strong>
    <form method='POST' action='manage.php'
          id='move-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' id='move-warning'
	   name='warning' value=''>
    <select name='move'
            onchange='document.getElementById
	                ("move-form").submit()'>
    <option value=''>No Project Selected</option>
    $move_options
    </select></form>

    </div>
EOT;

    if ( isset ( $problem ) )
    {
        $f =  "/projects/$project/$problem/+priv+";
        $priv_file_contents = @file_get_contents
	    ( "$epm_data/$f" );
        if ( $priv_file_contents === false )
	    $priv_file_contents = " \n";
        echo <<<EOT
	<div class='problem'>
	<strong>$project $problem Problem Privileges
	        </strong>
	<button type='button'
	        style='visibility:hidden'>
		Submit</button>
		<!-- this keeps the two header
		     heights the same, as button
		     is higher than text -->
	<div class='priv'>
	<pre>$priv_file_contents</pre>
	</div>
	</div>
EOT;
        problem_priv_map ( $pmap, $project, $problem ); 
	if ( isset ( $pmap['owner'] )
	     &&
	     $pmap['owner'] == '+' )
	{
	    if ( isset ( $edited_contents ) )
		$priv_file_contents = $edited_contents;
	    echo <<<EOT
	    <div class='problem'>
	    <form method='POST' action='manage.php'
		  enctype='multipart/form-data'
		  id='problem-post'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden' name='problem-priv'
				 id='problem-value'>
	    <input type='hidden' id='problem-warning'
	           name='warning' value=''>
	    <strong>Edit and</strong>
	    <button type='button'
		    onclick='COPY("problem","")'>
		Submit</button>
	    <div class='priv'>
	    <pre contenteditable='true'
		 id='problem-contents'
		>$priv_file_contents</pre>
	    </div>
	    </form>
	    </div>
EOT;
	}
    }
    if ( isset ( $project ) )
    {
        $priv_file_contents = @file_get_contents
	    ( "$epm_data/projects/$project/+priv+" );
        if ( $priv_file_contents === false )
	    $priv_file_contents = " \n";
        echo <<<EOT
	<div style='clear:both'></div>
	<div class='project'>
	<strong>$project Project Privileges</strong>
	<button type='button'
	        style='visibility:hidden'>
		Submit</button>
		<!-- this keeps the two header
		     heights the same, as button
		     is higher then text -->
	<div class='priv'>
	<pre>$priv_file_contents</pre>
	</div>
	</div>
EOT;
        project_priv_map ( $pmap, $project ); 
	if ( isset ( $pmap['owner'] )
	     &&
	     $pmap['owner'] == '+'
	     &&
	     ! isset ( $problem ) )
	{
	    if ( isset ( $edited_contents ) )
		$priv_file_contents = $edited_contents;
	    echo <<<EOT
	    <div class='project'>
	    <form method='POST' action='manage.php'
		  enctype='multipart/form-data'
		  id='project-post'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden' name='project-priv'
				 id='project-value'>
	    <input type='hidden' id='project-warning'
	           name='warning' value=''>
	    <strong>Edit and</strong>
	    <button type='button'
		    onclick='COPY("project","")'>
		Submit</button>
	    <div class='priv'>
	    <pre contenteditable='true'
		 id='project-contents'
		>$priv_file_contents</pre>
	    </div>
	    </form>
	    </div>
EOT;
	}
    }


?>

<script>
function COPY ( type, warn )
{
    src = document.getElementById
        ( type + '-contents' );
    des = document.getElementById ( type + '-value' );
    form = document.getElementById ( type + '-post' );
    warning = document.getElementById
        ( type + '-warning' );
    des.value = src.innerText;
    warning.value = warn;
    form.submit();
}
function MOVE ( warn )
{
    form = document.getElementById ( 'move-form' );
    warning = document.getElementById
        ( 'move-warning' );
    warning.value = warn;
    form.submit();
}
</script>

</body>
</html>
