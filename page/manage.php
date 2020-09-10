<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Sep 10 18:03:11 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits privileges, deletes and
    // renames project problems and projects.

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

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_MANAGE'] ) )
	    $_SESSION['EPM_MANAGE'] =
	        [ 'LISTNAME' => NULL ];
	$data['PROJECT'] = NULL;
	$data['PROBLEM'] = NULL;
    }

    $listname = & $_SESSION['EPM_MANAGE']['LISTNAME'];
    $project = & $data['PROJECT'];
    $problem = & $data['PROBLEM'];
        // If $problem is set and $project is not,
	// it is `your' problem.  If $project is set
	// and $problem is not, only the project is
	// selected.

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $edited_contents = NULL;
        // Contents of edited version of +priv+.
    $owner_warn = false;
        // True to ask if its ok to accept $edited_
	// contents which removes $aid from ownership.
    $move_warn = NULL;
        // If not NULL, ask if problem should be
	// moved to this project.
    $download = NULL;
        // If not NULL, open the download page with
	// filename = $download and content-type =
	// application/x-gzip.

    $favorites = read_favorites_list ( $warnings );
    if ( ! isset ( $listname ) )
    {
        list ( $time, $proj, $base ) = $favorites[0];
	$listname = "$proj:$base";
    }
    $list = read_problem_list ( $listname, $warnings );
    $priv_projects = read_projects();
    $move_projects = read_projects ( ['move-to'] );

    if ( $epm_method == 'POST' )
    {
	if ( isset ( $_POST['rw'] ) )
	    require "$epm_home/include/epm_rw.php";
        elseif ( isset ( $_POST['listname'] ) )
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
		    $project = NULL;
		else
		    $project = $proj;
		$problem = $prob;
	    }
	}
	elseif ( ! $rw )
	    /* Do Nothing */;
	    // From this point on posts are ignored if
	    // $rw is false.
        elseif ( isset ( $_POST['download'] ) )
	{
	    if ( ! isset ( $problem )
	         &&
		 ! isset ( $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    if ( ! isset ( $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    $d = NULL;
	    if ( isset ( $project ) )
	    {
		problem_priv_map
		    ( $pmap, $project, $problem ); 
		if ( ! isset ( $pmap['download'] )
		     ||
		     $pmap['download'] != '+' )
		    $errors[] = "you do not have"
		              . " download privilege"
			      . " on $project $problem";
		else
		{
		    $d = "projects/$project/$problem";
		    $n = "$project-$problem";
		}
	    }
	    else
	    {
		$d = "accounts/$aid/$problem";
		$n = $problem;
	    }

	    if ( isset ( $d ) )
	    {
		$e = "../../../accounts/$aid";
		$c = "cd $epm_data/$d;"
		   . "rm -f $e/+download-$uid+;"
		   . "tar zcf $e/+download-$uid+ .;";
		exec ( $c, $forget, $r );
		if ( $r != 0 )
		    $errors[] =
		        "could not create $n.tgz";
		else
		    $download = "$n.tgz";
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
	    if ( trim ( $edited_contents ) == '' )
	        $edited_contents = " \n";
   	    $warn = $_POST['warning'];
	    check_problem_priv
	        ( $pmap, $project, $problem,
		  $edited_contents, $errors );
	    if ( count ( $errors ) == 0 )
	    {
		$is_owner = ( isset ( $pmap['owner'] )
		              &&
			      $pmap['owner'] == '+' );
		if ( $is_owner || $warn == 'yes' )
		{
		    $f = "/projects/$project/$problem/"
		       . "+priv+";
		    $r = ATOMIC_WRITE
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
		    $action = "$time $aid update"
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
	    if ( trim ( $edited_contents ) == '' )
	        $edited_contents = " \n";
   	    $warn = $_POST['warning'];
	    check_project_priv
	        ( $pmap, $project,
		  $edited_contents, $errors );
	    if ( count ( $errors ) == 0 )
	    {
		$is_owner = ( isset ( $pmap['owner'] )
		              &&
			      $pmap['owner'] == '+' );
		if ( $is_owner || $warn == 'yes' )
		{
		    $f = "/projects/$project/+priv+";
		    $r = ATOMIC_WRITE
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
		    $action = "$time $aid update"
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
		}
		else
		    $owner_warn = true;
	    }
	}
        elseif ( isset ( $_POST['move'] ) )
	{
	    if ( ! isset ( $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    $proj = $_POST['move'];
	    if ( ! isset ( $_POST['warning'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( $proj == '' )
	        /* do nothing */;
	    elseif ( ! in_array ( $proj, $move_projects,
	                          true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
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

    $project_options =
        values_to_options
	    ( $priv_projects,
	      isset ( $problem ) ? NULL : $project );
    $move_options =
        values_to_options
	    ( $move_projects, $move_warn );
    $listname_options = list_to_options
        ( $favorites, $listname );
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
EOT;

    if ( $rw && isset ( $project )
             && isset ( $problem ) )
        echo <<<EOT
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
EOT;

    if ( isset ( $problem ) )
        echo <<<EOT
	<strong>or</strong>
	<form method='POST' action='manage.php'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='download' value=''>
	<button type='submit'>Download Problem</button>
	</form>
EOT;

    echo <<<EOT
    </div>
EOT;

    if ( isset ( $project ) && isset ( $problem ) )
    {
        $f =  "/projects/$project/$problem/+priv+";
        $priv_file_contents = ATOMIC_READ
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
	if ( $rw
	     &&
	     isset ( $pmap['owner'] )
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
        $priv_file_contents = ATOMIC_READ
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

	if ( $rw
	     &&
	     isset ( $pmap['owner'] )
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
