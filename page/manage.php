<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Feb 23 10:50:53 EST 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits privileges, downloads
    // problems and projects, copies problems between
    // projects.

    // Session Data:
    //
    //	   $listname = & $_SESSION['EPM_MANAGE']
    //			          ['LISTNAME']
    //		Current problem listname.
    //
    //	   $problem = & $data['PROBLEM']
    //	   $project = & $data['PROJECT']
    //		// $project $problem   Selected:
    //		//   NULL     NULL     Root Privileges
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
    //		copy-ask (ask the user if he really
    //			  wants to copy the problem
    //			  or project, and whether or
    //			  not he wants to block the
    //			  source of the copy)
    //		update-ask (ask the user if he really
    //			    wants to update files in
    //			    an existing copy of a
    //			    problem or project)
    //		block-ask (ask the user if he really
    //			   wants to block the problem
    //			   or project and if so what
    //			   the block file contents
    //			   should be)
    //		unblock-ask (ask the user if he really
    //			     wants to unblock the
    //			     problem or project)
    //
    //	   $download_enabled =
    //		    & $data['DOWNLOAD-ENABLED']
    //		true if user was presented with a
    //		download button by the last response
    //
    //	   $update_enabled = & $data['UPDATE-ENABLED']
    //		true if user was presented with editable
    //		or edited +priv+ file by last response;
    //		also true in owner-warn state
    //
    //	   $copy_enabled = & $data['COPY-ENABLED']
    //		true if user was presented with copy to
    //		project option or copy warning by last
    //		response; also true in copy-ask and
    //		update-ask states
    //
    //	   $block_enabled = & $data['BLOCK-ENABLED']
    //		true if user was presented with problem
    //		or project block button by last
    //		response; also true in block-ask state
    //
    //	   $unblock_enabled = & $data['UNBLOCK-ENABLED']
    //		true if user was presented with problem
    //		or project unblock button by last
    //		response; also true in unblock-ask state
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
    //	    update=FILE-CONTENTS    warning={,no,yes}
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
    //	    copy={,block,noblock,cancel,update}
    //			to=PROJECT
    //		Copy PROJECT PROBLEM to NEW_PROJECT or
    //		update NEW_PROJECT PROBLEM from
    //		PROJECT PROBLEM
    //
    //	    block={,cancel,submit} file=FILE-CONTENTS
    //		Create block file for selected project
    //		or problem if privileges allow.
    //
    //	    unblock={,cancel,submit}
    //		Unblock selected project or problem if
    //		privileges allow.
    //

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

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
	$data['COPY-ENABLED'] = false;
	$data['BLOCK-ENABLED'] = false;
	$data['UNBLOCK-ENABLED'] = false;
    }

    $listname = & $_SESSION['EPM_MANAGE']['LISTNAME'];
    $project = & $data['PROJECT'];
    $problem = & $data['PROBLEM'];
    $download_enabled = & $data['DOWNLOAD-ENABLED'];
    $update_enabled = & $data['UPDATE-ENABLED'];
    $copy_enabled = & $data['COPY-ENABLED'];
    $block_enabled = & $data['BLOCK-ENABLED'];
    $unblock_enabled = & $data['UNBLOCK-ENABLED'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $process_post = ( $epm_method == 'POST' );
        // True if POST that has not yet been processed.

    $notice = NULL;
        // If not NULL, output after errors and warnings
	// as <div class='notice'>$notice</div>.
    			
    $edited_contents = NULL;
	// If not NULL, use to refresh edited version
	// of +priv+.
    $copy_to = NULL;
        // If not NULL, this is project that is selected
	// for current problem to be copied to.
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
    $priv_projects = read_projects ( NULL, true );
        // List of selectable projects.  Blocked
	// projects are allowed.
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

    $pmap = [];
        // Privileges for selected problem or project
	// if $project is set or root if neither
	// $project or $problem is set.
    if ( isset ( $project ) )
    {
        if ( isset ( $problem ) )
	    problem_priv_map
	        ( $pmap, $project, $problem ); 
	else
	    project_priv_map ( $pmap, $project ); 
    }
    elseif ( ! isset ( $problem ) )
        root_priv_map ( $pmap );

    if ( $process_post
	 &&
	 $block_enabled
	 &&
         isset ( $_POST['block'] ) )
    {
        $process_post = false;

	if ( ! isset ( $project ) )
	    ERROR ( "bad \$block_enabled" );

	$act = $_POST['block'];
	if ( ! in_array ( $act, ['','submit',
	                         'cancel'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " block = $act" );
        if ( $state != ( $act == '' ? 'normal' :
	                              'block-ask' ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " block state = $state" );
	$state = 'normal';
	    // May be changed below.

        if ( $act != 'cancel' )
	{

	    $p = "project $project";
	    if ( isset ( $problem ) )
	        $p = "problem $problem in $p";
	    if ( ! $rw )
		$errors[] = "you no longer have"
		          . " read-write privilege";
	    elseif ( ! isset ( $pmap['block'] )
	             ||
		     $pmap['block'] != '+' )
		$errors[] = "you no longer have"
		          . " block privilege on"
			  . " $p";
	    elseif ( $act == '' )
		$state = 'block-ask';
	    else
	    {
		if ( ! isset ( $_POST['file'] ) )
		    exit ( "UNACCEPTABLE HTTP POST:" .
			   " file" );
		$file = $_POST['file'];
		$f = "projects/$project";
		if ( isset ( $problem ) )
		    $f = "$f/$problem";
		$f = "$f/+blocked+";
		if ( file_exists ( "$epm_data/$f" ) )
		    $errors[] = "$p is" .
		                " already blocked";
		elseif ( $file == '' )
		    $errors[] = "no reason given to" .
		                " block $p;" .
		                " block aborted";
		elseif ( ATOMIC_WRITE ( "$epm_data/$f",
		                        $file )
			 === false )
		    ERROR ( "could not write $f" );
	    }
	}
    }

    if ( $process_post
	 &&
	 $unblock_enabled
	 &&
         isset ( $_POST['unblock'] ) )
    {
        $process_post = false;

	if ( ! isset ( $project ) )
	    ERROR ( "bad \$unblock_enabled" );

	$act = $_POST['unblock'];
	if ( ! in_array ( $act, ['','yes','no'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " unblock = $act" );
        if ( $state != ( $act == '' ? 'normal' :
	                              'unblock-ask' ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " unblock state = $state" );
	$state = 'normal';
	    // May be changed below.

        if ( $act != 'no' )
	{
	    $p = "project $project";
	    if ( isset ( $problem ) )
	        $p = "problem $problem in $p";
	    if ( ! $rw )
		$errors[] = "you no longer have"
		          . " read-write privilege";
	    elseif ( ! isset ( $pmap['block'] )
	             ||
		     $pmap['block'] != '+' )
		$errors[] = "you no longer have"
		          . " block privilege on"
			  . " $p";
	    elseif ( $act == '' )
		$state = 'unblock-ask';
	    else
	    {
		$f = "projects/$project";
		if ( isset ( $problem ) )
		    $f = "$f/$problem";
		$f = "$f/+blocked+";
		if ( ! file_exists ( "$epm_data/$f" ) )
		    $errors[] = "$p is" .
		                " already UNblocked";
		elseif ( unlink ( "$epm_data/$f" )
			 === false )
		    ERROR ( "could not unlink $f" );
	    }
	}
    }

    // Set after processing block/unblock POST.
    //
    $blocked = false;
    if ( isset ( $project ) )
    {
        if ( isset ( $problem ) )
	    $blocked = blocked_problem
	        ( $project, $problem, $warnings );
	else
	    $blocked = blocked_project
	        ( $project, $warnings );
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

	if ( ! isset ( $project )
	     &&
	     isset ( $problem ) )
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
	elseif ( $blocked )
	    $errors[] = "project or problem has" .
	                " become blocked";
	elseif ( isset ( $project ) )
	{
	    $action_files =
		[ "/projects/$project/+actions+" ];
	    if ( isset ( $problem ) )
	    {
		check_problem_priv
		    ( $update_pmap, $project, $problem,
		      $edited_contents, $errors );
		$f = "/projects/$project/$problem/"
		   . "+priv+";
		$n = "$project $problem";
		$action_files[] =
		    "/projects/$project/$problem/"
		   . "+actions+";
	    }
	    else
	    {
		check_project_priv
		    ( $update_pmap, $project,
		      $edited_contents, $errors );
		$f = "/projects/$project/+priv+";
		$n = "$project -";
	    }
	}
	elseif ( ! isset ( $problem ) )
	{
	    check_root_priv
		( $update_pmap,
		  $edited_contents, $errors );
	    $f = "/projects/+priv+";
	    $n = "- -";
	    $action_files =
		[ "/projects/+actions+" ];
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
		$action = "$time $aid update-priv $n"
			. PHP_EOL;

		$action_files[] =
		    "accounts/$aid/+actions+";
		foreach ( $action_files as $f )
		{
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

    if ( $process_post
	 &&
	 $copy_enabled
	 &&
         isset ( $_POST['copy'] ) )
    {
        $process_post = false;

	if ( ! isset ( $problem )
	     ||
	     ! isset ( $project ) )
	    ERROR ( "bad \$copy_enabled" );

	if ( ! isset ( $_POST['to'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " copy without `to' argument" );

	$act = $_POST['copy'];
	if ( $state == 'normal' )
	    $ok = ( $act == '' );
	elseif ( $state == 'copy-ask' )
	    $ok = in_array ( $act, ['block',
	                            'noblock',
				    'cancel'] );
	elseif ( $state == 'update-ask' )
	    $ok = in_array ( $act, ['update',
				    'cancel'] );
	else
	    $ok = false;
	if ( ! $ok )
	    exit ( "UNACCEPTABLE HTTP POST:" .
		   " copy=$act state = $state" );
	$proj = $_POST['to'];
	if ( $proj == '' )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " empty `to' argument" );
	$state = 'normal';
	    // State if cancel or errors.
        if ( $act != 'cancel' )
	{
	    // Note: $copy_to is NULL at this point.
	    //
	    $src = "projects/$project/$problem/";
	    $des = "projects/$proj/$problem/";
	    $is_update = is_dir ( "$epm_data/$des" );
	    project_priv_map ( $projmap, $proj ); 

	    $a = "-av --delete";
	    if ( $is_update )
	        $a .= " --include=+sources+" .
		      " --exclude=+*+";
	    else
	        $a .= " --exclude=+blocked+" .
		      " --exclude=+priv+";

	    $pubargs = "$a $src $des";
	    $args = "2>&1 $a $epm_data/$src"
	          . " $epm_data/$des";

	    if ( $proj == $project )
	        $errors[] = "you cannot copy $problem" .
		            " in $project to itself";
	    elseif ( blocked_project ( $proj, $errors ) )
	        $errors[] = "project $proj has just" .
		            " been blocked";
	    elseif ( ! isset ( $projmap['copy-to'] )
	             ||
		     $projmap['copy-to'] != '+' )
		$errors[] = "you have lost copy-to" .
		            " privilege for project" .
			    " $proj";
	    elseif ( ! isset ( $pmap['copy-from'] )
	             ||
		     $pmap['copy-from'] != '+' )
		$errors[] = "you have lost copy-to" .
		            " privilege for problem" .
			    " $problem of project" .
			    " $project";
	    elseif ( ! $rw )
		$errors[] = "you no longer have"
		          . " read-write privilege";
	    elseif ( $act == '' )
	    {
		$com = "rsync -n $args";
		$pubcom = "rsync $pubargs";
		$copy_out = NULL;
		exec ( $com, $copy_out, $r );
		if ( $r != 0 )
		{
		    $errors[] = $pubcom;
		    $errors[] =
		        "failed with exit code $r";
		}
		else
		{
		    if ( $is_update )
			$state = 'update-ask';
		    else
			$state = 'copy-ask';
		    $copy_to = $proj;
		    $notice =
		        "<strong>" .
			"Command to be executed:" .
			"</strong> $pubcom<br><br>" .
		        rsync_to_html
			    ( $copy_out, true );
		}
	    }
	    else
	        $errors[] = 'copy not yet implemented';
	}
    }

    if ( $process_post )
	exit ( 'UNACCEPTABLE HTTP POST' );

    $download_enabled =
        ( $state == 'normal'
          &&
	  ( isset ( $problem )
	    ||
	    isset ( $project ) ) );
    if ( $download_enabled && isset ( $project ) )
    {
        if ( ! isset ( $pmap['download'] )
	     ||
	     $pmap['download'] != '+' )
	    $download_enabled = false;
    }

    $update_enabled = false;
    if ( $state == 'owner-warn' )
        $update_enabled = true;
    elseif ( $state == 'normal'
             &&
	     $rw
	     &&
	     ! $blocked
	     &&
	     isset ( $pmap['owner'] )
	     &&
	     $pmap['owner'] == '+' )
        $update_enabled = true;

    $block_enabled = false;
    if ( $state == 'block-ask' )
        $block_enabled = true;
    elseif ( $state == 'normal'
             &&
	     ! $blocked
	     &&
	     $rw
	     &&
	     isset ( $project )
	     &&
	     isset ( $pmap['block'] )
	     &&
	     $pmap['block'] == '+' )
        $block_enabled = true;

    $unblock_enabled = false;
    if ( $state == 'unblock-ask' )
        $unblock_enabled = true;
    elseif ( $state == 'normal'
             &&
	     $blocked
	     &&
	     $rw
	     &&
	     isset ( $project )
	     &&
	     isset ( $pmap['block'] )
	     &&
	     $pmap['block'] == '+' )
        $unblock_enabled = true;

    $copy_enabled = false;
    if ( $state == 'copy-ask' )
        $copy_enabled = true;
    elseif ( $state == 'update-ask' )
        $copy_enabled = true;
    elseif ( $state == 'normal'
             &&
	     $rw
	     &&
	     isset ( $project )
	     &&
	     isset ( $problem )
	     &&
	     isset ( $pmap['copy-from'] )
	     &&
	     $pmap['copy-from'] == '+' )
    {
	// If $problem is set, $pmap is problems
	// privilege map.

	$copy_to_projects =
	    read_projects ( ['copy-to'] );
	if ( count ( $copy_to_projects ) > 0 )
	    $copy_enabled = true;
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
div.project, div.problem, div.root {
    padding: var(--pad) 0px;
    margin: 0px;
    display: inline-block;
    float: left;
    width: 50%;
}
div.root {
    background-color: var(--bg-violet);
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
    if ( isset ( $notice ) )
        echo "<div class='notice'>$notice</div>";

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
    if ( $state == 'copy-ask' )
    {
        echo <<<EOT
	<div class='warnings'>
	<form action='manage.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden'
	       name='to' value='$copy_to'>
	<strong>Do you really want to copy
		problem $problem from project $project
		to problem $problem in project
		$copy_to and</strong>
	<br>
	<button type='submit'
	        name='copy' value='block'>
	     BLOCK problem $problem in
	     project $project</button>
	<pre>   </pre>
	<button type='submit'
	        name='copy' value='noblock'>
	     or leave problem $problem in
	     project $project UNBLOCKED</button>
	<pre>   </pre>
	<button type='submit'
	        name='copy' value='cancel'>
	     or CANCEL copy</button>
	<br></form></div>
EOT;
    }
    if ( $state == 'update-ask' )
    {
        echo <<<EOT
	<div class='warnings'>
	<form action='manage.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden'
	       name='to' value='$copy_to'>
	<strong>Do you really want to update
	        problem $problem in project $copy_to
		<br>
		with more recent files from problem
		$problem in project $project?</strong>
	<br>
	<button type='submit'
	        name='copy' value='update'>
	     YES</button>
	<pre>   </pre>
	<button type='submit'
	        name='copy' value='cancel'>
	     NO</button>
	<br></form></div>
EOT;
    }
    if ( $state == 'block-ask' )
    {
	$e = ( isset ( $problem ) ?
	       " Problem $problem" : "" );
        echo <<<EOT
	<div class='warnings'>
	<form action='manage.php' method='POST'
	      id='block-post'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' id='block-act'
	                     name='block' value=''>
	<input type='hidden' id='block-file'
	                     name='file' value=''>
	<strong>Your reason for Blocking$e in Project
	                   $project: </strong>
	<pre>   </pre>
	<button type='button'
		onclick='BLOCK("submit")'>
	     Submit</button>
	<pre>   </pre>
	<button type='button'
		onclick='BLOCK("cancel")'>
	     Cancel</button>
	<br>
	<div class='priv'>
	<textarea contenteditable='true'
	          id='block-contents'
	          placeholder='(replace with reason)'
	     ></textarea>
	</div></form></div>
EOT;
    }
    if ( $state == 'unblock-ask' )
    {
	$e = ( isset ( $problem ) ?
	       " Problem $problem" : "" );
        echo <<<EOT
	<div class='warnings'>
	<form action='manage.php' method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<strong>Do you really want to UNBLOCK$e
	           in Project $project?</strong>
	<pre>   </pre>
	<button type='submit'
	        name='unblock' value='yes'>
	     YES</button>
	<pre>   </pre>
	<button type='submit'
	        name='unblock' value='no'>
	     NO</button>
	</form></div>
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

    if ( $copy_enabled && $state == 'normal' )
    {
	$copy_options =
	    values_to_options ( $copy_to_projects );
        echo <<<EOT
	<strong>or Copy Problem to Project</strong>
	<form method='POST' action='manage.php'
	      id='copy-form'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='copy' value=''>
	<select name='to'
		onchange='document.getElementById
			    ("copy-form").submit()'>
	<option value=''>No Project Selected</option>
	$copy_options
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

    if ( $block_enabled )
    {
	if ( isset ( $problem ) )
	    $m = 'Block Problem';
	else
	    $m = 'Block Project';
        echo <<<EOT
	<strong>or</strong>
	<form method='POST' action='manage.php'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='block' value=''>
	<button $disabled type='submit'>$m</button>
	</form>
EOT;
    }

    if ( $unblock_enabled )
    {
	if ( isset ( $problem ) )
	    $m = 'Unblock Problem';
	else
	    $m = 'Unblock Project';
        echo <<<EOT
	<strong>or</strong>
	<form method='POST' action='manage.php'>
	<input type='hidden' name='id' value='$ID'>
	<input type='hidden' name='unblock' value=''>
	<button $disabled type='submit'>$m</button>
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
    }
    else
    {
	$f =  "/projects/+priv+";
	$n = "Root";
	$c = 'root';
    }

    if ( isset ( $project ) || ! isset ( $problem ) )
    {
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
    }

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

    if ( isset ( $project ) )
    {
	if ( isset ( $problem ) )
	{
	    $f =  "/projects/$project/+priv+";
	    $priv_file_contents = ATOMIC_READ
		( "$epm_data/$f" );
	    if ( $priv_file_contents === false )
		$priv_file_contents = " \n";
	    echo <<<EOT
	    <div style='clear:both'></div>
	    <div class='project'>
	    <strong>$project Project Privileges</strong>
	    <div class='priv'>
	    <pre>$priv_file_contents</pre>
	    </div>
	    </div>
EOT;
	}

	$f =  "/projects/+priv+";
	$priv_file_contents = ATOMIC_READ
	    ( "$epm_data/$f" );
	if ( $priv_file_contents === false )
	    $priv_file_contents = " \n";
	echo <<<EOT
	<div style='clear:both'></div>
	<div class='root'>
	<strong>Root Privileges</strong>
	<div class='priv'>
	<pre>$priv_file_contents</pre>
	</div>
	</div>
EOT;
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
function BLOCK ( action )
{
    src = document.getElementById ( 'block-contents' );
    des = document.getElementById ( 'block-file' );
    act = document.getElementById ( 'block-act' );
    form = document.getElementById ( 'block-post' );
    des.value = src.value;
    act.value = action;
    form.submit();
}
</script>

</body>
</html>
