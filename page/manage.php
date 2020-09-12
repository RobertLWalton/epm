<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Sep 12 06:33:23 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits privileges, downloads
    // problems and projects, moves problems between
    // projects.

    // Session Data:
    //
    //	   $listname = & $_SESSION['EPM_MANAGE']
    //			          ['LISTNAME']
    //		Current problem listname.
    //
    //	   $problem = & $data['PROBLEM']
    //	   $project = & $data['PROJECt']
    //		// $project $problem   Selected:
    //		//   NULL     NULL     Nothing
    //		//   PROJECT  NULL     PROJECT
    //		//   NULL     PROBLEM  Your PROBLEM
    //		//   PROJECT  PROBLEM  PROJECT PROBLEM
    //
    //	   $state (see index.php)
    //		normal (no warning)
    //		owner-warn  (warn user that he will no
    //			     longer be an owner of a
    //			     project or problem if
    //			     submitted +priv+ file is
    //			     accepted)
    //		move-warn (ask the user if he really
    //			   wants to move the problem)
    //
    //	   $download_enabled = & $data['DOWNLOAD-ENABLED']
    //		true if user was presented with a download
    //		button by the last response
    //
    //	   $update_enabled = & $data['UPDATE-ENABLED']
    //		true if user was presented with editable
    //		or edited +priv+ file by last response
    //		(true in owner-warn state)
    //
    //	   $move_enabled = & $data['MOVE-ENABLED']
    //		true if user was presented with move to
    //		project option or move warning by last
    //		response (true in move-warn state)
    //
    // POSTs:
    //
    //	    listname=PROJECT:BASENAME
    //		Set LISTNAME; deselect problem/project.
    //
    //	    project=PROJECT
    //		Select project; set $problem to NULL.
    //
    //	    problem=-:PROBLEM
    //		Select problem with NULL $project.
    //
    //	    problem=PROJECT:PROBLEM
    //		Select problem in project.
    //
    //	    update=FILE    warning={,no,yes}
    //		Replace +priv+ file for selected project
    //		or problem if privileges allow.
    //
    //	    reset
    //		Restore edited +priv+ file to original.
    //		
    //	    download
    //		Download selected project or problem
    //		if privileges allow.
    //
    //	    move=NEW_PROJECT   warning={,no,yes}
    //		Move PROJECT PROBLEM to NEW_PROJECT

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_MANAGE'] ) )
	    $_SESSION['EPM_MANAGE'] =
	        [ 'LISTNAME' => NULL ];
	$data['PROJECT'] = NULL;
	$data['PROBLEM'] = NULL;
	$data['DOWNLOAD-ENABLED'] = false;
	$data['UPDATE-ENABLED'] = false;
	$data['MOVE-ENABLED'] = false;
    }

    $listname = & $_SESSION['EPM_MANAGE']['LISTNAME'];
    $project = & $data['PROJECT'];
    $problem = & $data['PROBLEM'];
    $download_enabled = & $data['DOWNLOAD-ENABLED'];
    $update_enabled = & $data['UPDATE-ENABLED'];
    $move_enabled = & $data['MOVE-ENABLED'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $process_post = ( $epm_method == 'POST' );
        // True if POST that has not yet been processed.
    			
    $edited_contents = NULL;
	// If not NULL, use to refresh edited version
	// of +priv+.
    $move_to = NULL;
        // If not NULL, this is project that is selected
	// for current problem to be moved to.
    $download = NULL;
        // If not NULL, invoke look.php with
	// location=+temp+ and filename = $download.


    // Establish $favorites and $listname.
    //
    $favorites = read_favorites_list ( $warnings );
        // List of selectable problem lists.
    if ( $process_post
         &&
	 isset ( $_POST['listname'] )
	 &&
	 $state == 'normal' )
    {
        $process_post = false;

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
	    exit ( 'UNACCEPTABLE HTTP POST: LISTNAME' );
	$listname = $new_listname;
	$project = NULL;
	$problem = NULL;
    }

    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }

    // Establish $list, $priv_projects, $rw, $project,
    // and $problem.
    //
    $list = read_problem_list ( $listname, $warnings );
        // List of selectable problems.
    $priv_projects = read_projects();
        // List of selectable projects.
    if ( $process_post
	 &&
	 $state == 'normal' )
    {
	if ( isset ( $_POST['rw'] ) )
	{
	    $process_post = false;
	    require "$epm_home/include/epm_rw.php";
	}
        elseif ( isset ( $_POST['project'] ) )
	{
	    $process_post = false;

	    $proj = $_POST['project'];
	    if ( $proj == '' )
	    {
		$project = NULL;
		$problem = NULL;
	    }
	    elseif ( ! in_array
	                   ( $proj, $priv_projects ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' PROJECT' );
	    else
	    {
		$project = $proj;
		$problem = NULL;
	    }
	}
        elseif ( isset ( $_POST['problem'] ) )
	{
	    $process_post = false;

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
		    exit ( 'UNACCEPTABLE HTTP POST:' .
		           ' PROBLEM' );
		if ( $proj == '-' )
		    $project = NULL;
		else
		    $project = $proj;
		$problem = $prob;
	    }
	}
    }

    if ( ! $process_post )
    {
	// These must be reset if $project or $problem
	// is reset.  They can be reset if $rw is reset.
	// Each may be set to true after POST
	// processing.
	//
        $update_enabled = false;
	$move_enabled = false;
    }

    $pmap = [];
        // Privileges for selected problem or project
	// if $project is set.
    if ( isset ( $project ) )
    {
        if ( isset ( $problem ) )
	    problem_priv_map
	        ( $pmap, $project, $problem ); 
	else
	    project_priv_map ( $pmap, $project ); 
    }

    if ( $process_post
         &&
	 $download_enabled
	 &&
         isset ( $_POST['download'] ) )
    {
        $process_post = false;

	if ( ! isset ( $problem )
	     &&
	     ! isset ( $project ) )
	    ERROR ( "bad \$download_enabled" );

	$d = NULL;
	if ( isset ( $project )
	     &&
	     ( ! isset ( $pmap['download'] )
	       ||
	       $pmap['download'] != '+' ) )
	    $errors[] = "you do not have download"
	              . " privilege for $project"
		      . ( isset ( $problem ) ?
		          " $problem" : '' );
	elseif ( ! isset ( $project ) )
	{
	    $d = "accounts/$aid/$problem";
	    $n = $problem;
	}
	elseif ( ! isset ( $problem ) )
	{
	    $d = "projects/$project";
	    $n = $project;
	}
	else
	{
	    $d = "projects/$project/$problem";
	    $n = "$project-$problem";
	}

	if ( isset ( $d ) )
	{
	    $t = "$epm_data/accounts/$aid"
	       . "/+download-$uid+";
	    $c = "cd $epm_data/$d;"
	       . "rm -f $t;"
	       . "tar zcf $t .;";
	    exec ( $c, $forget, $r );
	    if ( $r != 0 )
		$errors[] =
		    "could not create $n.tgz";
	    else
		$download = "$n.tgz";
	}
    }

    if ( $process_post
	 &&
	 $update_enabled
	 &&
	 $state == 'normal'
	 &&
         isset ( $_POST['reset'] ) )
    {
        $process_post = false;
    }

    if ( $process_post
	 &&
	 $update_enabled
	 &&
         isset ( $_POST['update'] )
	 &&
         isset ( $_POST['warning'] ) )
    {
        $process_post = false;

	if ( ! isset ( $project ) )
	    ERROR ( "bad \$update_enabled" );

	$warn = $_POST['warning'];
	if ( ! in_array ( $warn, ['','no','yes'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( $state != ( $warn == '' ? 'normal' :
	                               'owner-warn' ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$state = 'normal';
	    // May be changed below.

	$edited_contents = $_POST['update'];
	if ( trim ( $edited_contents ) == '' )
	    $edited_contents = " \n";
	if ( ! $rw )
	    $errors[] = "you no longer have read-write"
	              . " privilege";
	elseif ( isset ( $problem ) )
	{
	    check_problem_priv
		( $update_pmap, $project, $problem,
		  $edited_contents, $errors );
	    $f = "/projects/$project/$problem/"
	       . "+priv+";
	    $n = "$project $problem";
	}
	else
	{
	    check_project_priv
		( $update_pmap, $project,
		  $edited_contents, $errors );
	    $f = "/projects/$project/+priv+";
	    $n = "$project project";
	}
	if ( count ( $errors ) == 0 && $warn != 'no' )
	{
	    $is_owner =
	        ( isset ( $update_pmap['owner'] )
		  &&
		  $update_pmap['owner'] == '+' );
	    if ( $is_owner || $warn == 'yes' )
	    {
		$r = ATOMIC_WRITE
		    ( "$epm_data/$f",
		      $edited_contents );
		if ( $r === false )
		    ERROR ( "cannot write $f" );
		$edited_contents = NULL;

		$time = @filemtime
		    ( "$epm_data/$f" );
		if ( $time === false )
		    ERROR ( "cannot stat $f" );
		$time = strftime
		    ( $epm_time_format, $time );
		$action = "$time $aid update"
			. " $n privileges"
			. PHP_EOL;

		$f = "projects/$project/+actions+";
		$r = @file_put_contents
		    ( "$epm_data/$f", $action,
		      FILE_APPEND );
		if ( $r === false )
		    ERROR ( "cannot write $f" );

		if ( isset ( $problem ) )
		{
		    $f = "projects/$project/"
		       / "$problem/+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );
		}
	    }
	    else
		$state = 'owner-warn';
	}
    }

    if ( isset ( $project ) && isset ( $problem ) )
    {
	$move_to_projects =
	    read_projects ( ['move-to'] );
	project_priv_map ( $project_pmap, $project );
    }

    if ( $process_post
	 &&
	 $move_enabled
	 &&
         isset ( $_POST['move'] )
	 &&
         isset ( $_POST['warning'] ) )
    {
        $process_post = false;

	if ( ! isset ( $problem )
	     ||
	     ! isset ( $project ) )
	    ERROR ( "bad \$move_enabled" );

	$warn = $_POST['warning'];
	if ( ! in_array ( $warn, ['','no','yes'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( $state != ( $warn == '' ? 'normal' :
	                               'move-warn' ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$state = 'normal';
	    // May be changed below.

	$proj = $_POST['move'];
        if ( $proj != '' && $warn != 'no' )
	{
	    // Note: $move_to is NULL at this point.
	    //
	    if ( ! in_array
	               ( $proj, $move_to_projects ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( ! $rw )
		$errors[] = "you no longer have"
		          . " read-write privilege";
	    elseif ( $proj == $project )
		$errors[] = "problem is aleady in $proj"
		          . " project";
	    elseif ( $_POST['warning'] == 'yes' )
	        $errors[] = "moving not yet"
		          . " implemented";
	    elseif ( $warn == '' )
	    {
		$state = 'move-warn';
	        $move_to = $proj;
	    }
	    else
	        $errors[] = 'move not yet implemented';
	}
    }

    $download_enabled =
        ( $state == 'normal'
          &&
	  ( isset ( $problem ) || isset ( $project ) ) );

    if ( $state == 'owner-warn' )
        $update_enabled = true;
    elseif ( $state == 'normal'
             &&
	     $rw
	     &&
	     isset ( $project )
	     &&
	     isset ( $pmap['owner'] )
	     &&
	     $pmap['owner'] == '+' )
        $update_enabled = true;

    if ( $state == 'normal'
         &&
	 $rw
	 &&
	 isset ( $project )
	 &&
	 isset ( $problem )
	 &&
	 isset ( $project_pmap['move-from'] )
	 &&
	 $project_pmap['move-from'] == '+' )
        $move_enabled = true;

    if ( $process_post )
	exit ( 'UNACCEPTABLE HTTP POST' );
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
    if ( $state == 'owner-warn' )
    {
        echo <<<EOT
	<div class='warnings'>
	<strong>WARNING: you will lose owner privileges
	        with this change;
		<br>
		Do you want to continue?</strong>
	<pre>   </pre>
	<button type='button'
		onclick='UPDATE("yes")'>
	     YES</button>
	<pre>   </pre>
	<button type='button'
		onclick='UPDATE("no")'>
	     NO</button>
	<br></div>
EOT;
    }
    if ( $state == 'move-warn' )
    {
        echo <<<EOT
	<div class='warnings'>
	<form action='manage.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden'
	       name='move' value='$move_to'>
	<strong>WARNING: do you really want to move
	                 $problem from $project
			 to $move_to?</strong>
	<pre>   </pre>
	<button type='submit'
	        name='warning' value='yes'>
	     YES</button>
	<pre>   </pre>
	<button type='submit'
	        name='warning' value='no'>
	     NO</button>
	<br></form></div>
EOT;
    }

    if ( isset ( $download ) )
    {
        echo <<<EOT
	<script>
	window.open
	    ( 'look.php' +
	      '?disposition=download' +
	      '&location=' +
	      encodeURIComponent ( '+temp+' ) +
	      '&filename=' +
	      encodeURIComponent ( '$download' ),
	      '_blank' );
	</script>
EOT;
    }

    $login_title =
        'Login Name; Click to See User Profile';
    echo <<<EOT
    <div style='background-color:orange;
                text-align:center'>
    <strong>This Page is Under Construction.</strong>
    </div>
    <div class='manage'>
    <form method='GET' action='manage.php'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
EOT;

    if ( $state == 'normal' )
        echo <<< EOT
	<button type='submit'
		formaction='user.php'
		title='$login_title'>
		$lname</button>
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
EOT;
    else
        echo <<< EOT
        $lname
	</td>
	<td>
EOT;

    echo <<<EOT
    </td>
    <td style='text-align:right'>
    $RW_BUTTON
    <button type='button' id='refresh'
            onclick='location.replace
	        ("manage.php?id=$ID")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("manage-page")'>
	?</button>
    </td>
    </tr>
    </table></form></div>
EOT;

    $disabled =
        ( $state == 'normal' ? '' : 'disabled' );
    $listname_options = list_to_options
        ( $favorites, $listname );
    $project_options =
        values_to_options
	    ( $priv_projects,
	      ( isset ( $problem ) ?
	        NULL : $project ) );
	    // If problem is selected, it is the
	    // problem and not the project selector
	    // that is providing the project.
    if ( isset ( $problem ) )
    {
	if ( isset ( $project ) )
	    $key = "$project:$problem";
	else
	    $key = "-:$problem";
    }
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
    <select name='problem' $disabled
	    onchange='document.getElementById
			("problem-form").submit()'>
    <option value=''>No Problem Selected</option>
    $problem_options
    </select></form>
    <strong>from Problem List:</strong>
    <form method='POST' action='manage.php'
          id='listname-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='listname' $disabled
            onchange='document.getElementById
	                ("listname-form").submit()'>
    $listname_options
    </select></form>

    <br>

    <strong>or Select Project</strong>
    <form method='POST' action='manage.php'
          id='project-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='project' $disabled
            onchange='document.getElementById
	                ("project-form").submit()'>
    <option value=''>No Project Selected</option>
    $project_options
    </select></form>
EOT;

    if ( $move_enabled && $state == 'normal' )
    {
	$move_options =
	    values_to_options ( $move_to_projects );
        echo <<<EOT
	<strong>or Move Problem to Project</strong>
	<form method='POST' action='manage.php'
	      id='move-form'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='warning' value=''>
	<select name='move'
		onchange='document.getElementById
			    ("move-form").submit()'>
	<option value=''>No Project Selected</option>
	$move_options
	</select></form>
EOT;
    }

    if ( $download_enabled )
    {
	if ( isset ( $problem ) )
	    $m = 'Download Problem';
	else
	    $m = 'Download Project';
        echo <<<EOT
	<strong>or</strong>
	<form method='POST' action='manage.php'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='download' value=''>
	<button type='submit'>$m</button>
	</form>
EOT;
    }

    echo <<<EOT
    </div>
EOT;

    if ( isset ( $project ) )
    {
	if ( isset ( $problem ) )
	{
	    $f =  "/projects/$project/$problem/+priv+";
	    $n = "$project $problem Problem";
	    $c = 'problem';
	}
	else // just $project set
	{
	    $f =  "/projects/$project/+priv+";
	    $n = "$project Project";
	    $c = 'project';
	}

	$priv_file_contents = ATOMIC_READ
	    ( "$epm_data/$f" );
	if ( $priv_file_contents === false )
	    $priv_file_contents = " \n";
	echo <<<EOT
	<div class='$c'>
	<strong>$n Privileges</strong>
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

	if ( $update_enabled )
	{
	    if ( isset ( $edited_contents ) )
		$priv_file_contents = $edited_contents;
	    $e = ( $state == 'normal' ?
		   'contenteditable=true' : '' );
	    echo <<<EOT
	    <div class='problem'>
	    <form method='POST' action='manage.php'
		  enctype='multipart/form-data'
		  id='post'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden' name='update'
				 id='value'>
	    <input type='hidden' id='warning'
		   name='warning' value=''>
	    <strong>Edit and</strong>
	    <button type='button'
		    onclick='UPDATE("")'>
		Submit</button>
	    </form>
	    <pre>   </pre>
	    <form method='POST' action='manage.php'>
	    <input type='hidden' name='id' value='$ID'>
	    <button type='submit' name='reset'>
	        Reset</button>
	    </form>
	    <div class='priv'>
	    <pre $e id='contents'
		>$priv_file_contents</pre>
	    </div>
	    </div>
EOT;
	}

	if ( isset ( $problem ) )
	{
	    $f =  "/projects/$project/+priv+";
	    $priv_file_contents = ATOMIC_READ
		( "$epm_data/$f" );
	    if ( $priv_file_contents === false )
		$priv_file_contents = " \n";
	    echo <<<EOT
	    <div class='project'>
	    <strong>$project Project Privileges</strong>
	    <div class='priv'>
	    <pre>$priv_file_contents</pre>
	    </div>
	    </div>
EOT;
	}
    }


?>

<script>
function UPDATE ( warn )
{
    src = document.getElementById ( 'contents' );
    des = document.getElementById ( 'value' );
    form = document.getElementById ( 'post' );
    warning = document.getElementById ( 'warning' );
    des.value = src.innerText;
    warning.value = warn;
    form.submit();
}
</script>

</body>
</html>
