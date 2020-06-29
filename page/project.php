<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun 29 14:42:40 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Pushes and pulls problem and maintains problem
    // lists.  Does NOT delete projects or project
    // problems or maintain permissions: see
    // manage.php.

    // Directories and Files
    // ----------- --- -----

    // Each project has a directory:
    //
    //		projects/PROJECT
    //
    // and a permission file:
    //
    //		projects/PROJECT/+perm+
    //
    // The permission file in turn has lines of the
    // form
    //
    //		TYPE UID-RE
    //
    // where TYPE is one of:
    //
    //	   owner	Specify PROJECT owners.
    //	   review	Allow attaching problem reviews.
    //	   push		Allow pushing new problems.
    //	   pull		Allow pulling problems.
    //
    // and UID-RE is a regular expression that is
    // matched to a user's UID to determine if the
    // user has the permissions specified by TYPE.
    //
    // The single `projects' directory, and each
    // projects/PROJECT/PROBLEM directory, also con-
    // tains a +perm+ file.
    //
    // A user with `owner' permissions for a directory
    // can perform all operations on the directory and
    // it descendents, including changing the +perm+
    // files.
    //
    // In the following all TIMEs are in the $epm_time_
    // format, and all descriptions consist of para-
    // graphs at the end of a file, each paragraph
    // preceeded by a blank line.  Descriptions may NOT
    // contain '<' or '>', but may may contain HTML
    // symbol names of the form '&...;'.  Each non-
    // indented description paragraph is displayed in
    // an HTML <p> paragraph.  Each indented description
    // paragraph is displayed in a <pre> paragraph after
    // tabs are each replaced by 8 spaces (tabs should
    // ONLY be at the beginnings of lines).
    //
    // A list is a NAME.list file in a directory:
    //
    //	    users/UID/+lists+
    //
    // Such a file belongs to the UID user and can be
    // edited by the user.  The file begins with lines
    // of the form:
    //
    //	    TIME PROJECT PROBLEM
    //
    // that specify a problem in a project.  Thus a list
    // is a list of problems.  If a problem is not in a
    // project, but is in the user's users/UID direc-
    // tory, PROJECT is `-'.  The TIME is the last
    // time before the line was added to the list file
    // that a change was made to the PROBLEM files by a
    // push or pull.

    // the problem or perform a maintenance operation
    // on the problem (e.g., change owner).
    //
    // A list file ends with description paragraphs
    // describing the list, each preceeded by a blank
    // line.  Note that a list file that is empty
    // except for descriptions must begin with a blank
    // line.
    //
    // For there is a directory
    //
    //	    lists
    //
    // that contains symbolic links of the form:
    //
    //	    OWNER:NAME.list =>
    //		users/OWNER/+lists+/NAME.list
    //
    // which make particular indices visible to users.
    // To users other than OWNER this lists are read-
    // only.  The OWNER of a list may create such a
    // link by publishing the list, and delete the
    // link by unpublishing the list.
    //
    // In addition there are read-only lists contain-
    // ing the problems in the directories:
    //
    //	    users/UID
    //
    //	    projects/PROJECT
    //
    // These are used to edit other lists, which is
    // done by copying entries from one list to another.
    //
    // Each user with given UID has a list of favorite
    // lists in
    //
    //	    users/UID/+lists+/+favorites+
    //
    // This files contents are lines of the forms:
    //
    //	    TIME OWNER BASENAME
    //
    // denoting the list file
    //
    //		users/OWNER/+lists+/BASENAME.list
    //
    // If the OWNER is not the UID, then the symbolic
    // link
    //
    //		lists/OWNER-BASENAME.list
    //
    // to the list must exist whenever the list is added
    // to favorites or read.  TIME is the modification
    // time of the list file when the list is added to
    // the favorites list.
    //
    // To use a list you must first add it to your
    // favorites.  However you may read a list's
    // desciption before adding it, in order to decide
    // if the list is worth adding.
    //
    // The directory:
    //
    //	    projects/PROJECT/PROBLEM/+review+
    //
    // holds files named UID.review which are reviews of
    // the problem by the UID user.  There can be at
    // most one review for each PROJECT, PROBLEM, UID
    // triple.  A review file is just a sequence of
    // description paragraphs separated by blank lines.
    //
    // A review file may be created by its UID user
    // after the user has solved the PROBLEM, if the
    // user is the owner of the PROBLEM, or if the user
    // has `review' permission for the problem.  The
    // creator of a review file may delete or replace
    // it.
    //
    // A problem may be pulled by any user with `pull'
    // permission for the problem.  A problem may be
    // created in a project by pushing it into the
    // project by any user with `push' permission for
    // the project.  When a problem is created, the
    // projects/PROJECT/PROBLEM/+perm+ file is set to
    // make the pushing user the owner of the problem
    // in the project.  Only an owner of a problem may
    // make subsequent pushes to the problem after the
    // problem has been created.
    //
    // A PROBLEM in a PROJECT has two directories
    // containing its files:
    //
    //	     projects/PROJECT/PROBLEM
    // and
    //	     projects/PROJECT/PROBLEM/+sources+
    //
    // The former directory contains the publicly
    // useable PROBLEM files, and the latter directory
    // contains source code.  These are kept in separate
    // directories so that they may be backed up
    // separately.
    //
    // When a problem is pushed, only uploaded files
    // and a few made files, such as .ftest and .pdf
    // files, are copied to the problem's project
    // subdirectories.
    //
    // When a problem is submitted, the resulting run
    // output (.rout) file is placed in the file
    //
    //	     projects/PROJECT/PROBLEM/+submits+/
    //		      CCCCCC-UID-RUN-PROBLEM.rout
    //
    // file, where CCCCCC is a counter that goes 000000,
    // 000001, 000002, ... as submissions occur, UID
    // is the user who submitted, and RUN-PROBLEM.run
    // is the corresponding run (.run) file.
    //	    
    // The following are log files:
    //
    //	    users/UID/+actions+
    //	    users/UID/PROBLEM/+actions+
    //	    users/UID/PROBLEM/+changes+
    //	    users/UID/+lists+/+actions+
    //	    projects/PROJECT/+actions+
    //	    projects/PROJECT/PROBLEM/+actions+
    //	    projects/PROJECT/PROBLEM/+changes+
    //	    lists/+actions+
    //
    // The +changes+ files describe change to the files
    // within the directory containing the +changes+
    // file.  There is no fixed format for these
    // descriptions.
    //
    // The +actions+ files contains lines each describ-
    // ing an action.  When an action occurs, its
    // action description line is written into the
    // +actions+ files of every directory involved in
    // the action.  This means that 4 +actions+ files
    // are written for push and pull operations and 2
    // +actions+ files are written for list operations.
    //
    // The following are the possible action description
    // lines:
    //
    //	 TIME UID pull PROJECT PROBLEM
    //	 TIME UID push PROJECT PROBLEM
    //	 TIME UID submit PROJECT PROBLEM STIME SCORE...
    //   TIME UID publish OWNER LISTNAME
    //   TIME UID unpublish OWNER LISTNAME
    //   TIME UID add OWNER LISTNAME
    //   TIME UID sub OWNER LISTNAME
    //
    // Here TIME is the time of the action, UID is the
    // user performing the action, PROJECT may be '-'
    // to indicate that the PROBLEM is private to the
    // UID user, STIME is the max solution time in a
    // submit run, SCORE... is the submit run score and
    // may contain single spaces, OWNER is the owner of
    // the list named LISTNAME, add means that user UID
    // has added the list to their favorites, and sub
    // means user UID has removed the list from their
    // favorites.
    //
    // +actions+ logs are NOT protected by locking and
    // it is possible for the writing of two lines to
    // collide and produce a garbled line.

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //     EPM_PROJECT LISTNAME
    //		Name of selected problem list.
    //		Preserved accross GETs of this page.
    //
    //     EPM_DATA OP
    //		One of:
    //		    NULL
    //		    'push'
    //		    'pull'
    //
    // During a push or pull operation:
    //
    //     EPM_DATA PROBLEM
    //		Name of problem being pushed or pulled.
    //
    //     EPM_DATA PROJECT
    //		Name of project into which problem is
    //		being pushed or from which it is being
    //		pulled.
    //
    //     EPM_DATA COMMANDS
    //		List of commands to be executed by
    //		execute_commands to accomplish the
    //		current push/pull operation.  Commands
    //		are to be executed with $epm_data being
    //		the current directory and 06 the current
    //		mask.
    //
    //     EPM_DATA CHANGES
    //		String to be appended to +changes+ file
    //		after commands are executed.  Also
    //		displayed to user when change-approval
    //		is required.
    //
    //	   EPM_DATA LOCK
    //		Time returned by LOCK before compile
    //		of projects/PROJECT/PROBLEM directory,
    //		if it exists.
    //
    //	   EPM_DATA ALTERED
    //		Filemtime of users/UID/PROBLEM/+altered+
    //		before compile, or 0 if file does not
    //		exist.

    // Non-XHTTP POSTs:
    // --------- -----
    //
    // Non-XHTTP POSTs include id=value-of-ID derived
    // from including in each form:
    //
    //	    <input type='hidden'
    //		   name='id' value='ID-value'>
    //
    // Initially there is a project page used to select
    // a problem LISTNAME and then either select an
    // operation to be performed, push or pull, or
    // create/reload a tab for a problem that either
    // exists or is being created.
    //
    // Project Page POST:
    // ------- ---- ----
    //
    //      listname=LISTNAME
    //		Set EPM_PROJECT LISTNAME.  LISTNAME has
    //		the format PROJECT:BASENAME.
    //
    //	    op=OPERATION 
    //		where OPERATION is 'push' or 'pull'.
    //	        Response is one of 2 page layouts
    //		according to the operation.
    //
    //	    goto=PROBLEM
    //		Create a tab for the problem named
    //		having the tab (window) name '<PROBLEM>'
    //		and src problem.php?problem=PROBLEM.
    //		Shift visibility to that tab.
    //		
    //	    create=PROBLEM
    //		Create a problem with the given name
    //		and then execute goto=PROBLEM.

    // XHTTP POSTS
    // ----- -------
    //
    // XHTTP POSTs include id=value-of-ID
    //
    // XHTTP POSTs are used for push and pull.

    // XHTTP Push/Pull Operations
    // ----- --------- ----------
    //
    // The push or pull pages present the user with a
    // LIST of candidate problems to be pushed or
    // pulled.  Javascript is used to select problems
    // and then submit these.  Submission causes
    // XHTTP operations that execute push or pull.
    // The server POSTs just execute pushes and pulls,
    // while javascript maintains a view of the results
    // on the page.
    //
    //	 Operations that execute push/pull.
    //
    //     finish=push problem=PROBLEM project=PROJECT
    //	       Push PROBLEM to PROJECT.
    //
    //     finish=pull problem=PROBLEM project=PROJECT
    //	       Pull PROBLEM from PROJECT.
    //
    //     compile=push problem=PROBLEM project=PROJECT
    //	       Compile push of PROBLEM to PROJECT.
    //
    //     compile=pull problem=PROBLEM project=PROJECT
    //	       Compile pull of PROBLEM from PROJECT.
    //
    //     send-changes=yes
    //	       Send changes from previous compile (this
    //         can happen after WARN response).  What
    //
    //     execute=yes
    //	       Execute compiled push or pull.
    //
    //	    Responses:
    //
    //		WARN new-ID-value
    //		warning-messages
    //
    //		ERROR new-ID-value
    //		error-messages
    //
    //		COMPILED new-ID-value
    //		compiled-changes
    //
    //		DONE new-ID-value
    //
    //		Here warning-messages is a string
    //		consisting of lines each with an EOL,
    //		and error-messages and compiled-changes
    //		are similar.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_template.php";
        // This last is only needed by merge function.

    if ( $epm_method == 'GET' )
    {
        $_SESSION['EPM_DATA'] = [ 'OP' => NULL ];
	if ( ! isset ( $_SESSION['EPM_PROJECT'] ) )
	    $_SESSION['EPM_PROJECT'] =
	        [ 'LISTNAME' => NULL ];
    }

    // Given a listname, return your problems that
    // are in the list (i.e., that have PROJECT == '-')
    // as an HTML <option> list.
    //
    function listname_to_problem_options ( $listname )
    {
	$r = '';
	foreach ( listname_to_list ( $listname )
		  as $item )
	{
	    list ( $time, $project, $problem ) =
		$item;
	    if ( $project == '-' )
		$r .= "<option value='$problem'>"
		    . "$problem</option>";
	}
	return $r;
    }

    // Given a problem list in the form of elements
    //
    //		[TIME PROJECT PROBLEM]
    //
    // return the rows of a table for pushing problems
    // as a string.  The string has one segment for each
    // list element for which PROBLEM exists in users/
    // UID.  The segment has the form:
    //
    //     <tr data-project='PROJECT'
    //         data-problem='PROBLEM'>
    //         <td>
    //         <div class='checkbox'
    //              onclick='PUSH(this)'></div>
    //         <span class='problem'>
    //         PROBLEM &rArr; DESTINATION
    //         </span>
    //	       <pre>  </pre>COMMENT
    //         </td>
    //         </tr>
    //
    // where DESTINATION is
    //
    //     <span class='selected-project'>
    //     Selected Project</span>
    //
    // if PROJECT == '-' and the parent of PROBLEM does
    // not exist, or is the parent if that exists and
    // PROJECT == '-', or is PROJECT otherwise.  The
    // COMMENT tells whether this is a re-push, change
    // to a different parent, or creation of parent.
    //
    function list_to_push_rows ( $list, & $warnings )
    {
	$map = read_problems();
	$r = '';
	foreach ( $list as $items )
	{
	    list ( $time, $project, $problem ) = $items;
	    if ( ! isset ( $map[$problem] ) ) continue;
	    $parent = $map[$problem];
	    $comment = '(create parent)';
	    $destination = $project;
	    if ( $project == $parent )
	    {
	        if ( $parent == '-' )
		    $destination =
			'<span class=' .
			'"selected-project">' .
		        'Selected Project</span>';
		else
		    $comment = '(re-push to parent)';
	    }
	    else if ( $project == '-' )
	    {
		$project = $parent;
		$destination = $project;
		$comment = '(re-push to parent)';
	    }
	    elseif ( $parent != '-' )
		$comment = '(<mark>CHANGE</mark> to'
		         . ' <mark>DIFFERENT</mark>'
			 . ' parent)';

	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <div class='checkbox'
	        onclick='PUSH(this)'></div>
	    <span class='problem'>
	    $problem &rArr; $destination
	    </span>
	    <pre>  </pre>$comment
	    </td>
	    </tr>
EOT;
	}
	return $r;
    }

    // Given a $list of items of the form
    //
    //	   [TIME PROJECT PROBLEM]
    //
    // return as a string the rows of a table for
    // pulling the problems listed.  The string has one
    // segment for each list element in the same order
    // as the elements in the list.
    //
    // The segment for one list element is:
    //
    //     <tr data-project='PROJECT'
    //         data-problem='PROBLEM'>
    //         <td>
    //         <div class='checkbox'
    //              onclick='PULL(this)'></div>
    //         <span class='problem'>
    //         PROBLEM &lArr; PROJECT
    //	       <pre>  </pre>COMMENT
    //         </span>
    //         </td>
    //         </tr>
    //
    // Here NOTE is one of:
    //
    //	   (create UID PROBLEM)
    //		if PROBLEM does not exist for user
    //		and PROJECT != '-'
    //     (re-pull)
    //		if PROBLEM exists and has a parent and
    //		PROJECT == '-'
    //     (re-pull)
    //		if PROBLEM exists and has a parent and
    //		and the parent == PROJECT
    //	   (pull into <mark>EXISTING</mark> UID PROBLEM)
    //		if PROBLEM exists and has no parent and
    //		PROJECT != '-'
    //	   (<mark>CHANGE</mark> to<mark>DIFFERENT</mark>
    //			parent)
    //		if PROBLEM exists and has a parent that
    //		is different from PROJECT
    //
    // If a list element refers to a PROBLEM that exists
    // but has no parent and PROJECT == '-', then a
    // message is appended to $warnings and NO string
    // segment is generated for the list element.
    //
    function list_to_pull_rows ( $list, & $warnings )
    {
        global $uid;

	$map = read_problems();
	$r = '';
        foreach ( $list as $items )
	{
	    list ( $time, $project, $problem ) = $items;
	    if ( $project == '-' )
	    {
		if ( ! isset ( $map[$problem] )
		     ||
		     $map[$problem] == '-' )
		{
		    $warnings[] =
		        "cannot pull $uid $problem" .
			" as no project is specified";
		    continue;
		}
		$project = $map[$problem];
		$comment = "(re-pull)";
	    }
	    elseif ( ! isset ( $map[$problem] ) )
		$comment = "(create $uid problem)";
	    elseif ( $project == $map[$problem] )
		$comment = "(re-pull)";
	    elseif ( '-' == $map[$problem] )
		$comment =
		    "(pull into <mark>EXISTING</mark>" .
		    " $uid $problem)";
            else
		$comment = '(<mark>CHANGE</mark> to'
		         . ' <mark>DIFFERENT</mark>'
			 . ' parent)';

	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <div class='checkbox'
	         onclick='PULL(this)'></div>
	    <span class='problem'>
	    $problem &lArr; $project
	    </span>
	    <pre>  </pre>$comment
	    </td>
	    </tr>
EOT;
	}
	return $r;
    }

    // Get the value produced for $fname by
    // $push_file_map.  Return '' if there is no such
    // value. 
    //
    function get_action ( $problem, $fname )
    {
        global $push_file_map;

	$ext = pathinfo ( $fname, PATHINFO_EXTENSION );
	if ( ! isset ( $push_file_map[$ext] ) )
	    return '';
	$amap = $push_file_map[$ext];
	if ( is_array ( $amap ) )
	{
	    $base = pathinfo
		( $fname, PATHINFO_FILENAME );
	    $action = NULL;
	    foreach ( $amap as $re => $act )
	    {
		$re = preg_replace
		    ( '/PPPP/', $problem, $re );
		if ( ! preg_match
			   ( "/^($re)\$/", $base ) )
		    continue;
		$action = $act;
		break;
	    }
	    if ( ! isset ( $action ) ) return '';
	}
	else
	    $action = $amap;
	if ( ! in_array ( $action, ['R','L','S'],
				   true ) )
	    ERROR ( "bad action `$action' for" .
		    " \$push_file_map['$ext']" );
	return $action;
    }

    // Compute EPM_DATA CHANGES, COMMANDS, PROJECT,
    // and PROBLEM to be used to push $problem to
    // $project.  If $problem has a parent, it is an
    // error if the parent is not $project.  It is an
    // error if $problem or $project do not exist.
    //
    function compile_push_problem
	( $project, $problem, & $warnings, & $errors )
    {
	global $epm_data, $uid, $epm_filename_re,
	       $epm_time_format, $epm_parent_re,
	       $data, $push_file_map;

        $srcdir = "users/$uid/$problem";
	$d = "projects/$project";
	if ( ! is_dir ( "$epm_data/$srcdir" ) )
	{
	    $errors[] =
	        "you have no problem named $problem";
	    return;
	}
	if ( ! is_dir ( "$epm_data/$d" ) )
	{
	    $errors[] =
	        "there is no project named $project";
	    return;
	}
	$desdir = "$d/$problem";
	$g = "$srcdir/+parent+";
	if ( is_link ( "$epm_data/$g" ) )
	{
	    $s = @readlink ( "$epm_data/$g" );
	    if ( $s === false )
		ERROR ( "cannot read link $g" );
	    if ( ! preg_match
	             ( $epm_parent_re, $s, $matches ) )
		ERROR
		    ( "link $g value $s badly formed" );
	    list ( $x, $parproj, $parprob ) =
	        explode ( '/', $matches[1] );
	    if ( $parprob != $problem )
	        ERROR ( "problem illegally renamed:" .
		        " $problem != $parprob" );
	    if ( $parproj != $project )
	    {
	        $errors[] = "problem already has a"
		          . " parent in a different"
			  . " project ($parproj)";
		return;
	    }
	}
	if ( is_dir ( "$epm_data/$desdir" ) )
	{
	    $pmap = problem_permissions
		 ( $project, $problem );
	    if ( ! $pmap['owner'] )
	    {
		$errors[] =
		    "$problem => $project is not" .
		    " possible because you are" .
		    " not an owner of the existing" .
		    " problem $project $problem";
		return;
	    }
	    $new_push = false;
	}
	else
	{
	    $pmap = project_permissions ( $project );
	    if ( ! $pmap['push'] )
	    {
		$errors[] =
		    "$problem => $project is not" .
		    " possible because you do not" .
		    " have push permissions";
		$errors[] = "    for the $project" .
		    " project, and problem $project" .
		    " $problem does not exist yet";
		return;
	    }
	    $new_push = true;
	}

	$changes = "Changes to Push $uid $problem to"
	         . " $project $problem by $uid ("
	         . strftime ( $epm_time_format )
	         . "):" . PHP_EOL;
	$commands = [];
	$desmap = [];
	if ( $new_push )
	{
	    $changes .= "  make $project $problem"
	              . " directory" . PHP_EOL;
	    $commands[] = ['mkdir', $desdir, '02771'];
	    $commands[] = ['mkdir',
	                   "$desdir/+sources+",
	                   '02770'];
	    $commands[] = ['mkdir', "$desdir/+submits+",
	                            '02770'];
	    $changes .= "  make $uid the owner of the"
	              . " $project $problem directory"
		      . PHP_EOL;
	    $commands[] = ['append', "$desdir/+perm+",
	                             "owner $uid" .
				     PHP_EOL];
	    $changes .=
	        "  make $project $problem the parent" .
		" of $uid $problem" . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$desdir",
	                   "$srcdir/+parent+"];
	}
	else
	{
	    $files = @scandir ( "$epm_data/$desdir" );
	    if ( $files === false )
		ERROR ( "cannot read $desdir" );
	    foreach ( $files as $fname )
	    {
		if ( ! preg_match
			   ( $epm_filename_re,
			     $fname ) )
		    continue;
		$desmap[$fname] = true;
	    }
	}

	$files = @scandir ( "$epm_data/$srcdir" );
	if ( $files === false )
	    ERROR ( "cannot read $srcdir" );
	foreach ( $files as $fname )
	{
	    if ( ! preg_match
	               ( $epm_filename_re, $fname ) )
	        continue;
	    $action = get_action ( $problem, $fname );
	    if ( $action == '' ) continue;

	    if ( $action == 'S' )
	    {
		$t = "+sources+/$fname";
		$s = " sources";
	    }
	    else
	    {
	        $t = $fname;
		$s = '';
	    }
	    $f = "$srcdir/$fname";
	    if ( is_link ( "$epm_data/$f" ) )
	    {
	        $lnk = @readlink ( "$epm_data/$f" );
		if ( $lnk === false )
		    ERROR ( "cannot read link $f" );
		if ( $lnk == "+parent+/$t" )
		{
		    unset ( $desmap[$fname] );
		    continue;
		}
	    }

	    $changes .=
	        "  move $fname from $uid $problem to" .
		" $project $problem$s" . PHP_EOL;
	    $commands[] =
	        ['rename', "$srcdir/$fname",
		           "$desdir/$t"];
		    // This will also move a link.
	    $changes .=
	        "  link $fname in $uid $problem to" .
	        " $fname in $project $problem$s" .
		PHP_EOL;
	    $commands[] = ['link',
	                   "+parent+/$t",
	                   "$srcdir/$fname"];
	    unset ( $desmap[$fname] );
	}
	$fname = "$problem.optn";
	if ( is_readable
	    ( "$epm_data/$srcdir/$fname" ) )
	{
	    $changes .=
	        "  merge $fname in $uid $problem" .
		" into $fname in $project $problem" .
		PHP_EOL;
	    $commands[] =
	        ['merge', "$srcdir/$fname",
		          "$desdir/$fname"];
		// Merge merges optn files.
	}
	foreach ( $desmap as $fname => $value )
	{
	    $action = get_action ( $problem, $fname );
	    if ( $action == '' ) continue;

	    if ( $action == 'S' )
	    {
		$t = "+sources+/$fname";
		$s = " sources";
	    }
	    else
	    {
	        $t = $fname;
		$s = '';
	    }
	    $warnings[] = "if you continue $fname"
	                . " will be removed from"
			. " $project $problem$s;";
	    $warnings[] = "to avoid this pull the"
	                . " problem first";
	    $changes .=
	        "  remove $fname from $project" .
		" $problem$s" .  PHP_EOL;
	    $commands[] = ['unlink', "$desdir/$t"];
	}
	if ( count ( $commands ) == 0 )
	    $changes = '';
	$data['PROJECT'] = $project;
	$data['PROBLEM'] = $problem;
	$data['CHANGES'] = $changes;
	$data['COMMANDS'] = $commands;
    }

    // Compute EPM_DATA CHANGES, COMMANDS, PROJECT,
    // and PROBLEM to be used to pull $problem from
    // $project.  If $problem has been pulled, it is
    // and error if it has not been pulled from
    // $project.  It is an error if $problem in $project
    // does not exist.
    //
    function compile_pull_problem
	( $project, $problem, & $warnings, & $errors )
    {
	global $epm_data, $uid, $epm_filename_re,
	       $epm_time_format, $data,
	       $push_file_map, $epm_parent_re;

        $desdir = "users/$uid/$problem";
	$d = "projects/$project";
	if ( ! is_dir ( "$epm_data/$d" ) )
	{
	    $errors[] =
	        "there is no project named $project";
	    return;
	}
	$srcdir = "$d/$problem";
	if ( ! is_dir ( "$epm_data/$srcdir" ) )
	{
	    $errors[] = "project $project does not have"
	              . " a problem named $problem";
	    return;
	}

	$g = "$desdir/+parent+";
	if ( is_link ( "$epm_data/$g" ) )
	{
	    $s = @readlink ( "$epm_data/$g" );
	    if ( $s === false )
		ERROR ( "cannot read link $g" );
	    if ( ! preg_match
	             ( $epm_parent_re, $s, $matches ) )
		ERROR
		    ( "link $g value $s badly formed" );
	    list ( $x, $parproj, $parprob ) =
	        explode ( '/', $matches[1] );
	    if ( $parprob != $problem )
	        ERROR ( "problem illegally renamed:" .
		        " $problem != $parprob" );
	    if ( $parproj != $project )
	    {
	        $errors[] = "problem already has a"
		          . " parent in a different"
			  . " project ($parproj)";
		return;
	    }
	}
	$pmap = problem_permissions
	     ( $project, $problem );
	if ( ! $pmap['pull'] )
	{
	    $errors[] =
		"$problem <= $project is not" .
		" possible because you do not have" .
		" `pull' permission for" .
		" problem $project $problem";
	    return;
	}
	$is_owner = $pmap['owner'];

	$new_pull = ! is_dir ( "$epm_data/$desdir" );

	$changes = "Changes to Pull $uid $problem from"
	         . " $project $problem by $uid ("
	         . strftime ( $epm_time_format )
	         . "):" . PHP_EOL;
	$commands = [];

	if ( $new_pull )
	{
	    $changes .= "  make problem $uid $problem"
	              . " directory" . PHP_EOL;
	    $commands[] = ['mkdir', $desdir, '02771'];
	    $changes .=
	        "  make $project $problem the parent" .
		" of $uid $problem" . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$srcdir",
	                   "$desdir/+parent+"];
	}

	$files = @scandir ( "$epm_data/$srcdir" );
	if ( $files === false )
	    ERROR ( "cannot read $srcdir" );
	foreach ( $files as $fname )
	{
	    if ( ! preg_match
	               ( $epm_filename_re, $fname ) )
	        continue;
	    $ext = pathinfo
	        ( $fname, PATHINFO_EXTENSION );
            $action = get_action ( $problem, $fname );
	    if ( $action == '' ) continue;

	    if ( $action != 'L' && ! $is_owner )
	        continue;

	    if ( $action == 'S' )
	    {
		$t = "+sources+/$fname";
		$s = " sources";
	    }
	    else
	    {
	        $t = $fname;
		$s = '';
	    }

	    $g = "$srcdir/$t";
	    $h = "$desdir/$fname";
	    if ( is_link ( "$epm_data/$h" ) )
	    {
	        $link = @readlink ( "$epm_data/$h" );
		if ( $link === false )
		    ERROR ( "cannot read link $h" );
		if ( $link != "+parent+/$t" )
		    $warnings[] =
		        "existing $h will not be" .
			" altered; delete and re-run" .
			" if desired";
		continue;
	    }
	    elseif ( file_exists ( "$epm_data/$h" ) )
	    {
		$warnings[] =
		    "existing $h will not be altered;" .
		    " delete and re-run if desired";
		continue;
	    }

	    $changes .=
	        "  link $fname in $uid $problem to" .
	        " $fname in $project $problem$s" .
		PHP_EOL;
	    $commands[] = ['link',
	                   "+parent+/$t",
	                   "$desdir/$fname"];
	}
	$f = "$desdir/$problem.optn";
	if ( is_readable ( "$epm_data/$f" ) )
	{
	    $warnings[] =
		"existing $f will remain and" .
		" override inherited options;" .
		PHP_EOL .
		"  use Options Page to revert to" .
		" inherited options if desired";
	}
	if ( count ( $commands ) == 0 )
	    $changes = '';
	$data['PROJECT'] = $project;
	$data['PROBLEM'] = $problem;
	$data['CHANGES'] = $changes;
	$data['COMMANDS'] = $commands;
    }

    // Execute EPM_DATA COMMANDS.  Errors cause abort
    // and append to $errors.
    //
    // The supported commands are:
    //
    //     ['unlink',$f] => unlink $f
    //
    //     ['mkdir',$d, $mode] =>
    //		mkdir $epm_data/$d $mode true
    //
    //	   ['append',$f,$line] =>
    //		put_file_contents $f $line FILE_APPEND
    //
    //     ['rename',$f,$g] => rename $epm_data/$f
    //				      $epm_data/$g
    //
    //     ['link',$f,$g] => symbolic_link $f
    //				           $epm_data/$g
    //
    //	   ['merge',$f,$g] => merge $f into $g
    //		there $f and $g are .optn files
    //		in user's problem directory and
    //		project problem directory respectively.
    //
    function execute_commands ( & $errors )
    {
        global $epm_data, $data;

	$commands = $data['COMMANDS'];

	$umask = umask ( 06 );
	foreach ( $commands as $command )
	{
	    $cop = $command[0];
	    if ( $cop == 'unlink' )
	        $r = @unlink
		         ( "$epm_data/{$command[1]}" );
	    elseif ( $cop == 'mkdir' )
	        $r = @mkdir
		         ( "$epm_data/{$command[1]}",
			   intval ( $command[2], 8 ),
			   true );
	    elseif ( $cop == 'append' )
	        $r = @file_put_contents
		         ( "$epm_data/{$command[1]}",
			   $command[2],
			   FILE_APPEND );
	    elseif ( $cop == 'rename' )
	        $r = @rename
		         ( "$epm_data/{$command[1]}",
			   "$epm_data/{$command[2]}" );
	    elseif ( $cop == 'link' )
	        $r = symbolic_link
		         ( "$command[1]",
			   "$epm_data/{$command[2]}" );
	    elseif ( $cop == 'merge' )
	        $r = merge ( $command[1], $command[2] );
	    else
	        ERROR ( 'bad command: ' .
		        json_encode ( $command ) );

	    if ( $r === false )
	    {
		$c = implode ( ' ', $command );
		WARN ( "$c failed" );
		$errors[] = "$c failed";
		umask ( $umask );
		return;
	    }
	}
	umask ( $umask );
    }

    // Merge source .optn file into destination .optn
    // file.
    //
    function merge ( $srcfile, $desfile )
    {
        global $epm_data;

	$srcdir = pathinfo
	    ( $srcfile, PATHINFO_DIRNAME );
	$problem = pathinfo
	    ( $srcfile, PATHINFO_FILENAME );
	$desdir = pathinfo
	    ( $desfile, PATHINFO_DIRNAME );


        $template_optn = get_template_optn();
	$optmap = [];
	$errors = [];
	foreach ( $template_optn
	          as $opt => $description )
	{
	    if ( isset ( $description['default'] ) )
		$optmap[$opt] = $description['default'];
	}
	$dirs = array_reverse
	    ( find_ancestors ( $desdir ) );
	load_optmap
	    ( $optmap, $dirs, $problem, $errors );
	$basemap = $optmap;
	$dirs = [$desdir, $srcdir];
	load_optmap
	    ( $optmap, $dirs, $problem, $errors );
	if ( count ( $errors ) > 0 )
	{
	    WARN ( implode ( PHP_EOL, $errors ) );
	    return false;
	}

	$newmap = [];
	foreach ( $optmap as $opt => $value )
	{
	    if ( $basemap[$opt] != $value )
	        $newmap[$opt] = $value;
	}

	if ( ! @unlink ( "$epm_data/$srcfile" ) )
	    ERROR ( "could not unlink $srcfile" );
	if ( file_exists ( "$epm_data/$desfile" )
	     &&
	     ! @unlink ( "$epm_data/$desfile" ) )
	    ERROR ( "could not unlink $desfile" );
	if ( count ( $newmap ) == 0 ) return true;
	$r = @file_put_contents
	    ( "$epm_data/$desfile",
	      json_encode ( $newmap,
	                    JSON_PRETTY_PRINT ) );
	return $r;
    }

    // Write EPM_DATA CHANGES to destination
    // +changes+ file and push action item to
    // +actions+ files.
    //
    function record_push_execution ()
    {
	global $epm_data, $data, $uid,
	       $epm_time_format;

	$changes = $data['CHANGES'];
	if ( $changes == '' ) return;

	$project = $data['PROJECT'];
	$problem = $data['PROBLEM'];
	$d = "projects/$project/$problem";
	$f = "$d/+changes+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "cannot stat $f" );
	$time = strftime ( $epm_time_format, $time );
	$action = "$time $uid push $project $problem"
	        . PHP_EOL;

	$f = "projects/$project/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$f = "projects/$project/$problem/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$f = "users/$uid/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$f = "users/$uid/$problem/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );
    }

    // Write EPM_DATA CHANGES to destination
    // +changes+ file and action to source +actions+
    // files.
    //
    function record_pull_execution ()
    {
	global $epm_data, $data, $uid, $epm_time_format;

	$changes = $data['CHANGES'];
	if ( $changes == '' ) return;

	$project = $data['PROJECT'];
	$problem = $data['PROBLEM'];
	$f = "users/$uid/$problem/+changes+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );

	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "cannot stat $f" );
	$time = strftime ( $epm_time_format, $time );
	$action = "$time $uid pull $project $problem"
	        . PHP_EOL;

	$g = "projects/$project/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$g", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $g" );

	$g = "projects/$project/$problem/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$g", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $g" );

	$g = "users/$uid/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$g", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $g" );

	$g = "users/$uid/$problem/+actions+";
	$r = @file_put_contents
	    ( "$epm_data/$g", $action, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $g" );
    }

    $data = & $_SESSION['EPM_DATA'];
    $op = & $data['OP'];
    $listname = & $_SESSION['EPM_PROJECT']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $goto = NULL;    // Existing (or newly created)
    		     // problem whose tab should be
		     // created (or gone to).

    if ( ! isset ( $op ) )
    {
        $favorites = favorites_to_list ( 'pull|push' );
	if ( ! isset ( $listname ) )
	{
	    list ( $time, $proj, $base ) =
	        $favorites[0];
	    $listname = "$proj:$base";
	}
    }

    if ( $epm_method == 'POST' )
    {
        if ( isset ( $_POST['listname'] ) )
	{
	    if ( isset ( $op ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $new_listname = $_POST['listname'];
	    if ( in_list ( $new_listname, $favorites )
	         === NULL )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $listname = $new_listname;
	}
        elseif ( isset ( $_POST['op'] ) )
	{
	    $new_op = $_POST['op'];
	    if ( ! in_array
	               ( $new_op, ['push','pull'],
		                  true ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $listname ) )
	        $errors[] = "you must select problem"
		          . " list BEFORE you push or"
			  . " pull";
	    else
		$op = $new_op;
	}
        elseif ( isset ( $_POST['goto'] ) )
	{
	    if ( isset ( $op ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $listname ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $problem = $_POST['goto'];
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $d = "users/$uid/$problem";
	    if ( ! is_dir ( "$epm_data/$d" ) )
	        $errors[] = "your $problem problem"
		          . " no longer exists";
	    else
		$goto = $problem;
	}
        elseif ( isset ( $_POST['create'] ) )
	{
	    if ( isset ( $op ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $problem = trim ( $_POST['create'] );
	    $d = "users/$uid/$problem";
	    if ( $problem == '' )
	    {
		// User hit carriage return on empty
		// field.  Do nothing.
	    }
	    elseif ( ! preg_match ( $epm_name_re,
				    $problem ) )
		$errors[] =
		    "problem name `$problem' contains" .
		    " an illegal character or does" .
		    " not begin and end with a letter";
	    elseif ( is_dir ( "$epm_data/$d" ) )
		$errors[] =
		    "trying to create problem" .
		    " `$problem' which already exists";
	    else
	    {
		$m = umask ( 06 );
		if ( ! @mkdir ( "$epm_data/$d",
		                02771, true ) )
		{
		    $errors[] =
			"trying to create problem" .
			" `$problem' which already" .
			" exists";
		}
		else
		{
		    $goto = $problem;
		    $time = @filemtime
		        ( "$epm_data/$d" );
		    if ( $time === false )
		        ERROR ( "cannot stat $d" );
		    $time = strftime
		        ( $epm_time_format, $time );
		    $action = "$time $uid create"
		            . " $problem"
			    . PHP_EOL;

		    umask ( $m );

		    $f = "users/$uid/+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );

		    $f = "$d/+actions+";
		    $r = @file_put_contents
			( "$epm_data/$f", $action,
			  FILE_APPEND );
		    if ( $r === false )
			ERROR ( "cannot write $f" );
		}
		umask ( $m );
	    }
	}
	elseif ( ! isset ( $op ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	// From here on we are processing POSTs
	// occuring during push/pull ops.
        elseif ( isset ( $_POST['send-changes'] ) )
	{
	    echo "COMPILED $ID\n";
	    echo $data['CHANGES'];
	    exit;
	}
        elseif ( isset ( $_POST['execute'] ) )
	{
	    if ( ! isset ( $data['PROBLEM'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( ! isset ( $data['PROJECT'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $problem = $data['PROBLEM'];
	    $project = $data['PROJECT'];

	    $d = "projects/$project/$problem";
	    if ( is_dir ( "$epm_data/$d" ) )
	    {
		$lock_type = ( $op == 'push' ?
		               LOCK_EX : LOCK_SH );
		$lock = LOCK ( $d, $lock_type );
		if ( ! isset ( $data['LOCK'] )
		     ||
		     $lock > $data['LOCK'] )
		    $errors[] =
		        "problem $project $problem" .
			" was changed by a push" .
			" during this $op" .
			PHP_EOL .
			"  so this $op has been" .
			" cancelled; try again";
	    }
	    $f = "users/$uid/$problem/+altered+";
	    $altered = @filemtime ( "$epm_data/$f" );
	    if ( $altered === false ) $altered = 0;
	    if ( $altered > $data['ALTERED'] )
		$errors[] = "$uid $problem was altered"
			  . " by another one of your"
			  . " tabs during this $op";

	    if ( count ( $errors ) == 0 )
		execute_commands ( $errors );
	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $ID\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $op == 'push' )
	        record_push_execution();
	    else
	        record_pull_execution();
	    echo "DONE $ID\n";
	    exit;
	}
        elseif ( $op == 'push' || $op == 'pull' )
	{
	    $just_compile = isset ( $_POST['compile'] );
	    if ( $just_compile )
	        $oper = $_POST['compile'];
	    elseif ( ! isset ( $_POST['finish'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    else
	        $oper = $_POST['finish'];
	    if ( $oper != $op )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( ! isset ( $_POST['problem'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    elseif ( ! isset ( $_POST['project'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $problem = $_POST['problem'];
	    $project = $_POST['project'];
	    if ( $project == '-' )
	        ERROR ( "compile or finish project" .
		        " is `-'" );

	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! preg_match
	               ( $epm_name_re, $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    $d = "projects/$project/$problem";
	    if ( is_dir ( "$epm_data/$d" ) )
	    {
		$lock_type = ( $op == 'push' ?
		               LOCK_EX : LOCK_SH );
		$data['LOCK'] = LOCK ( $d, $lock_type );
	    }
	    $f = "users/$uid/$problem/+altered+";
	    $altered = @filemtime ( "$epm_data/$f" );
	    if ( $altered === false ) $altered = 0;
	    $data['ALTERED'] = $altered;

	    if ( $op == 'push' )
		compile_push_problem
		    ( $project, $problem,
		      $warnings, $errors );
	    else
		compile_pull_problem
		    ( $project, $problem,
		      $warnings, $errors );

	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $ID\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( count ( $warnings ) > 0 )
	    {
		echo "WARN $ID\n";
		foreach ( $warnings as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $just_compile )
	    {
		echo "COMPILED $ID\n";
		echo $data['CHANGES'];
		exit;
	    }
	    execute_commands ( $errors );
	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $ID\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $op == 'push' )
	        record_push_execution();
	    else
	        record_pull_execution();
	    echo "DONE $ID\n";
	    exit;
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
    div.op th {
        font-size: var(--large-font-size);
	text-align: left;
    }
    span.problem {
	display:inline;
        font-size: var(--large-font-size);
    }
    div.progress {
	background-color: #00FF00;
    }
    div.push-pull-list, div.edit-list {
	background-color: #F2D9D9;
    }
    span.selected-project {
	color: red;
	display:inline-block;
	font-weight: bold;
    }
    label.select-project {
	color: red;
	display:inline-block;
    }
    #warn-response {
        background-color: #FF99FF;
	margin-bottom: 0px;
    }
    #warn-messages {
        background-color: #FFCCFF;
        font-size: var(--large-font-size);
	display: block;
	margin-top: 3px;
	margin-left: var(--indent);
	margin-bottom: 0px;
    }
    #error-response {
        background-color: yellow;
	margin-bottom: 0px;
    }
    #error-messages {
        background-color: #FFFF99;
        font-size: var(--large-font-size);
	display: block;
	margin-top: 3px;
	margin-left: var(--indent);
	margin-bottom: 0px;
    }
    #compile-response {
        background-color: pink;
	margin-bottom: 0px;
    }
    #compile-messages {
        background-color: #FFCCCC;
        font-size: var(--large-font-size);
	display: block;
	margin-top: 3px;
	margin-left: var(--indent);
	margin-bottom: 0px;
    }

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

function FAIL ( message )
{
    alert ( message );
    location.assign ( '/page/login.php' );
}

</script>

</head>

<?php 

    if ( $op == 'pull' )
	$pull_rows = list_to_pull_rows
	    ( listname_to_list ( $listname ),
	      $warnings );
    elseif ( $op == 'push' )
	$push_rows = list_to_push_rows
	    ( listname_to_list ( $listname ),
	      $warnings );
	// Must execute these before $warnings is used.

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

    if ( isset ( $op ) )
    {
	$cancel = ( $op == 'push' ?
		    'Cancel All Pushing' :
		    'Cancel All Pulling' );
	echo <<<EOT

	<div id='warn-response' style='display:none'>
	<table style='width:100%'>
	<tr>
	<td><strong>WARNINGS:</strong></td>
	<td>
	<button type='button'
	        onclick='IGNORE_WARNINGS()'>
	IGNORE WARNINGS</button>
	<pre>    </pre>
	<button type='button' onclick='WARNINGS_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<form method='GET' action='project.php'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit'>$cancel</button></form>
	</td>
	</td><td style='text-align:right'>
	<button type='button'
		onclick='HELP("project-warnings")'>
	    ?</button>
	</td>
	</tr>
	</table>
	<pre id='warn-messages'></pre>
	</div>

	<div id='error-response' style='display:none'>
	<table style='width:100%'>
	<tr>
	<td><strong>Errors:</strong></td>
	<td>
	<button type='button' onclick='START_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<form method='GET' action='project.php'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit'>$cancel</button></form>
	</td>
	</td><td style='text-align:right'>
	<button type='button'
		onclick='HELP("project-errors")'>
	    ?</button>
	</td>
	</tr>
	</table>
	<pre id='error-messages'></pre>
	</div>

	<div id='compile-response' style='display:none'>
	<table style='width:100%'>
	<tr>
	<td><strong>Proposed Actions:</strong></td>
	<td>
	<button type='button' onclick='EXECUTE()'>
	EXECUTE</button>
	<pre>    </pre>
	<button type='button' onclick='SKIP_TO_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<form method='GET' action='project.php'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit'>$cancel</button></form>
	</td>
	</td><td style='text-align:right'>
	<button type='button'
		onclick='HELP("project-execute")'>
	    ?</button>
	</td>
	</tr>
	</table>
	<pre id='compile-messages'></pre>
	</div>
EOT;
    }

    $display = ( isset ( $op ) ? 'none' : 'table-row' );
    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>

    <tr id='goto-row' style='display:$display'>
    <td>
    <strong>User:</strong>
    <button type='submit'
	    formaction='user.php'
	    title='Click to See User Profile'>
	    $email</button>
    </td>
    <td>

    <div id='done-response' style='display:none'>
    <strong style='color:red'>Done!</strong>
    <pre>    </pre>
    <strong>Go To:</strong>
    <button type='submit'
	    formaction='project.php'>
	    Project</button>
    </div>
    <div id='not-done-response' style='display:inline'>
    <strong>Go To</strong>
    </div>
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
            onclick='HELP("project-page")'>
	?</button>
    </td>
    </tr>
EOT;

    if ( isset ( $op ) )
    	echo <<<EOT
	<tr id='check-row'>
	<td>
	<strong>User: $email</strong>
	</td>
	<td>
	<div class='checkbox'
	     id='check-proposed'
	     onclick='CHECK(this)'></div>
	<strong>Check Proposed Actions</strong>
	</td>
	<td style='text-align:right'>
	<button type='button'
		onclick='HELP("project-page")'>
	    ?</button>
	</td>
	</tr>
EOT;

    echo <<<EOT
    </table>
    </form>
EOT;
    if ( $op == NULL )
    {
	$listname_options = list_to_options
	    ( $favorites, $listname );
	$push_title = 'Push Problems in Selected'
	            . ' List to Projects';
	$pull_title = 'Pull Problems in Selected'
	            . ' List from Projects';
	$select_title = 'Lists of'
	              . ' Problems to Push or Pull'
		      . ' or Go To';
        echo <<<EOT
	<strong>Selected Problem List:</strong>
	<form method='POST' action='project.php'
	      id='listname-form'>
	<input type='hidden' name='id' value='$ID'>
	<select name='listname'
		onclick='document.getElementById
			    ("listname-form").submit()'>
	$listname_options
	</select></form>
	<strong>and</strong>
	<form method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit' name='op' value='push'
	        title='$push_title'>
	Push
	</button>
	<strong>or</strong>
	<button type='submit' name='op' value='pull'
	        title='$pull_title'>
	Pull
	</button>
	</form>
EOT;
	if ( isset ( $listname ) )
	{
	    $problem_options =
		listname_to_problem_options
		    ( $listname );
	    echo <<<EOT
	    <strong>or Create Tab for Problem:</strong>
EOT;
	    if ( $problem_options != '' ) echo <<<EOT
	    <form method='POST' action='project.php'
		  id='goto-form'>
	    <input type='hidden' name='id' value='$ID'>
	    <select name='goto'
		    onclick='document.getElementById
				("goto-form").submit()'>
	    $problem_options
	    </select></form>
EOT;
	    else echo <<<EOT
	    selected list contains none of
	          <mark>Your</mark> problems
EOT;
        }
	echo <<<EOT
	<br>
	<strong>Create New Problem:</strong>
	<form method='POST' action='project.php'
	      id='create-form'>
	<input type='hidden' name='id' value='$ID'>
	<input type="text" size="32"
	       placeholder="New Problem Name"
	       title="New Problem Name"
	       name='create'
	       onkeydown='KEYDOWN("create-form")'>
	</form>
EOT;
    }
    echo <<<EOT
    </div>
EOT;
    if ( $op == 'push' )
    {
	$project_options = values_to_options
	    ( read_projects ( 'push' ) );

	echo <<<EOT
	<div class='push-pull-list'>
	<form method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<table width='100%' id='problem-table'>
	<tr id='pre-submit'>
	    <th style='text-align:left'>
	        <strong>Problems (select to push)
		        </strong></th>
	    <td><input type='button'
	               onclick='SUBMIT_PUSH()'
		       value='Submit'>
	        <input type='button'
	               onclick='RESET_PUSH()'
		       value='Reset'>
		<button type='submit'
		        formmethod='GET'
		        formaction='project.php'>
                        Cancel</button>
	    </td>
	    <td id='project-selector'
	        style='visibility:hidden'>
	    <label class='select-project'>
	    <strong>Select Project</strong>:
	    <select id='selected-project-selector'>
	    $project_options
	    </select></label>
	    </td>
	    <td>
            <td style='text-align:right'>
	    <button type='button'
		    onclick='HELP("project-push")'>
		?</button>
            </td>
	</tr>
	<tr id='post-submit' style='display:none'>
	    <th id='post-submit'
	        style='text-align:left'>
	        <strong>Problems
		        (selected are being pushed)
		    </strong></th>
	    <td id='selected-project-column'>
	    <strong>Selected Project:
	    <span id='selected-project-value'
	          class='selected-project'>
	    </span></strong></td>
	    <td>
            <td style='text-align:right'>
	    <button type='button'
		    onclick='HELP("project-push")'>
		?</button>
            </td>
	</tr>
	$push_rows
	</table>
	</form>
	</div>

	<script>

	var project_selector = document.getElementById
	    ( 'project-selector' );
	var selected_project_selector =
	    document.getElementById
	        ('selected-project-selector');
	var selected_project_column =
	    document.getElementById
	        ('selected-project-column');
	var selected_project_value =
	    document.getElementById
	        ('selected-project-value');

	var push_counter = 0;
	var selected_project = null;

	// The following is called when a push checkbox
	// is clicked for a problem.
	//
	// This function keeps a counter of the number
	// of boxes with no project that are checked and
	// makes project-selector visible if the counter
	// is not zero.
	//
	function PUSH ( checkbox )
	{
	    if ( submit ) return;
	    var row = checkbox.parentElement
	                      .parentElement;
	    if ( checkbox.style.backgroundColor != on )
	        // Initial color may be anything but on.
	    {
		// Click must turn box on.
		//
		checkbox.style.backgroundColor = on;
		if ( row.dataset.project == '-'
		     &&
		     ++ push_counter == 1 )
		    project_selector.style.visibility =
		        'visible';
	    }
	    else
	    {
		// Click must turn box off.
		//
		checkbox.style.backgroundColor = off;
		if ( row.dataset.project == '-'
		     &&
		     -- push_counter == 0 )
		    project_selector.style.visibility =
		        'hidden';
	    }
	} 

	function RESET_PUSH ()
	{
	    if ( submit ) return;
	    for ( var i = 0; i < problem_rows.length;
	                     ++ i )
	    {
		var row = problem_rows[i];
	        var problem = row.dataset.problem;
		if ( problem === undefined ) continue;
		var td = row.children[0];
		var checkbox = td.children[0];
		if ( checkbox.style
		             .backgroundColor != on )
		    continue;
		checkbox.style.backgroundColor = off;
		if ( row.dataset.project == '-'
		     &&
		     -- push_counter == 0 )
		    project_selector.style.visibility =
		        'hidden';
	    }

	}

	function SUBMIT_PUSH ()
	{
	    selected_project =
	        selected_project_selector.value;
	    selected_project_value.innerText =
	        selected_project;
	    selected_project_column.style.visibility =
		project_selector.style.visibility;
	    pre_submit.style.display = 'none';
	    post_submit.style.display = 'table-row';
	    submit = true;
	    START_NEXT();
	}

	function START_NEXT ()
	{
	    warn_response.style.display = 'none';
	    error_response.style.display = 'none';
	    compile_response.style.display = 'none';
	    while (   ++ current_row
	            < problem_rows.length )
	    {
		let row = problem_rows[current_row];
	        var problem = row.dataset.problem;
		if ( problem === undefined ) continue;
	        var project = row.dataset.project;
		var td = row.children[0];
		var checkbox = td.children[0];
		if ( checkbox.style
		             .backgroundColor != on )
		    continue;
		break;
	    }
	    if ( current_row >= problem_rows.length )
	    {
	        goto_row.style.display = 'table-row';
	        check_row.style.display = 'none';
	        done_response.style.display = 'inline';
	        not_done_response.style.display =
		    'none';
		return;
	    }
	    checkbox.style.backgroundColor =
		running;
	    var check_compile =
	        (    check_proposed.style
		                .backgroundColor
		  == on );
	    op = ( check_compile ? 
		   'compile' : 'finish' );
	    if ( project == '-' )
	        project = selected_project;
	    SEND ( op + '=push&problem=' + problem
		      + '&project=' + project,
		   check_compile ?
		       COMPILE_RESPONSE :
		       DONE_RESPONSE );
	}

	</script>
EOT;
    }

    if ( $op == 'pull' )
    {
	echo <<<EOT
	<div class='push-pull-list'>
	<form method='POST'>
	<input type='hidden' name='id' value='$ID'>
	<table width='100%' id='problem-table'>
	<tr id='pre-submit'>
	    <th style='text-align:left'>
	        <strong>Problems (select to pull)
		        </strong></th>
	    <td><input type='button'
	               onclick='SUBMIT_PULL()'
		       value='Submit'>
	        <input type='button'
	               onclick='RESET_PULL()'
		       value='Reset'>
		<button type='submit'
		        formmethod='GET'
		        formaction='project.php'>
                        Cancel</button>
	    </td>
	    <td>
            <td style='text-align:right'>
	    <button type='button'
		    onclick='HELP("project-pull")'>
		?</button>
            </td>
	</tr>
	<tr id='post-submit' style='display:none'>
	    <th id='post-submit'
	        style='text-align:left'>
	        <strong>Problems
		        (selected are being pulled)
		    </strong></th>
            <td style='text-align:right'>
	    <button type='button'
		    onclick='HELP("project-pull")'>
		?</button>
            </td>
	</tr>
	$pull_rows
	</table>
	</form>
	</div>

	<script>

	// The following is called when a push checkbox
	// is clicked for a problem.
	//
	function PULL ( checkbox )
	{
	    if ( submit ) return;
	    var row = checkbox.parentElement
	                      .parentElement;
	    if ( checkbox.style.backgroundColor != on )
	        // Initial color may be anything but on.
	    {
		// Click must turn box on.
		//
		checkbox.style.backgroundColor = on;
	    }
	    else
	    {
		// Click must turn box off.
		//
		checkbox.style.backgroundColor = off;
	    }
	} 

	function RESET_PULL ()
	{
	    if ( submit ) return;
	    for ( var i = 0; i < problem_rows.length;
	                     ++ i )
	    {
		var row = problem_rows[i];
	        var problem = row.dataset.problem;
		if ( problem === undefined ) continue;
		var td = row.children[0];
		var checkbox = td.children[0];
		if ( checkbox.style
		             .backgroundColor != on )
		    continue;
		checkbox.style.backgroundColor = off;
	    }

	}

	function SUBMIT_PULL ()
	{
	    pre_submit.style.display = 'none';
	    post_submit.style.display = 'table-row';
	    submit = true;
	    START_NEXT();
	}

	function START_NEXT ()
	{
	    warn_response.style.display = 'none';
	    error_response.style.display = 'none';
	    compile_response.style.display = 'none';
	    while (   ++ current_row
	            < problem_rows.length )
	    {
		let row = problem_rows[current_row];
	        var problem = row.dataset.problem;
		if ( problem === undefined ) continue;
	        var project = row.dataset.project;
		var td = row.children[0];
		var checkbox = td.children[0];
		if ( checkbox.style
		             .backgroundColor != on )
		    continue;
		break;
	    }
	    if ( current_row >= problem_rows.length )
	    {
	        goto_row.style.display = 'table-row';
	        check_row.style.display = 'none';
	        done_response.style.display = 'inline';
	        not_done_response.style.display =
		    'none';
		return;
	    }
	    checkbox.style.backgroundColor =
		running;
	    var check_compile =
	        (    check_proposed.style
		                .backgroundColor
		  == on );
	    op = ( check_compile ? 
		   'compile' : 'finish' );
	    SEND ( op + '=pull&problem=' + problem
		      + '&project=' + project,
		   check_compile ?
		       COMPILE_RESPONSE :
		       DONE_RESPONSE );
	}

	</script>
EOT;
    }

    if ( ! isset ( $op ) )
        echo <<<EOT
	<script>
	function KEYDOWN ( form_id )
	{
	    if ( event.code === 'Enter' )
	    {
		event.preventDefault();
		let form = document.getElementById
		    ( form_id );
		form.submit();
	    }
	}

	function GOTO_PROBLEM ( problem )
	{
	    let w = window.open
	        ( '$epm_root/page/problem.php' +
		  '?problem=' + problem, problem, '' );
	}
	</script>
EOT;
    if ( isset ( $goto ) )
        echo <<<EOT
	<script>
	GOTO_PROBLEM ( '$goto' );
	</script>
EOT;
    if ( isset ( $op ) )
    {
        $check_proposed =
	    ( $op == 'push' ? 'on' : 'off' );
        echo <<<EOT
	<script>

	ID = '$ID';

	let off = 'white';
	let on = 'black';
	let running = 'red';
	let succeeded = 'green';
	let failed = 'yellow';

	let problem_rows =
	    document.getElementById('problem-table')
	            .rows;
	let pre_submit =
	    document.getElementById('pre-submit');
	let post_submit =
	    document.getElementById('post-submit');
	let check_proposed =
	    document.getElementById('check-proposed');
	check_proposed.style.backgroundColor =
	    $check_proposed;

	var compile_response =
	    document.getElementById
	        ('compile-response');
	var compile_messages =
	    document.getElementById
	        ('compile-messages');
	var warn_response =
	    document.getElementById
	        ('warn-response');
	var warn_messages =
	    document.getElementById
	        ('warn-messages');
	var error_response =
	    document.getElementById
	        ('error-response');
	var error_messages =
	    document.getElementById
	        ('error-messages');
	var goto_row =
	    document.getElementById
	        ('goto-row');
	var check_row =
	    document.getElementById
	        ('check-row');
	var done_response =
	    document.getElementById
	        ('done-response');
	var not_done_response =
	    document.getElementById
	        ('not-done-response');

	var submit = false;
	var current_row = -1;

	function CHECK ()
	{
	    if ( check_proposed.style.backgroundColor
	         != on )
		check_proposed.style.backgroundColor =
		    on;
	    else
		check_proposed.style.backgroundColor =
		    off;
	}

	function SKIP_TO_NEXT ()
	{
	    let row = problem_rows[current_row];
	    let td = row.children[0];
	    let checkbox = td.children[0];
	    checkbox.style.backgroundColor = off;
	    START_NEXT();
	}

	function COMPILE_RESPONSE ( op, text )
	{
	    if ( op == 'ERROR' )
	    {
	        ERROR_RESPONSE ( text );
		return;
	    }
	    else if ( op == 'WARN' )
	    {
	        WARN_RESPONSE ( text );
		return;
	    }
	    if ( text == '' )
	    {
	        DONE_RESPONSE ( 'DONE', text );
		return;
	    }
	    compile_messages.textContent = text;
	    compile_response.style.display = 'block';
	}

	function DONE_RESPONSE ( op, text )
	{
	    if ( op == 'ERROR' )
	    {
	        ERROR_RESPONSE ( text );
		return;
	    }
	    else if ( op == 'WARN' )
	    {
	        WARN_RESPONSE ( text );
		return;
	    }
	    let row = problem_rows[current_row];
	    let td = row.children[0];
	    let checkbox = td.children[0];
	    checkbox.style.backgroundColor = succeeded;
	    START_NEXT();
	}

	function ERROR_RESPONSE ( text )
	{
	    let row = problem_rows[current_row];
	    let td = row.children[0];
	    let checkbox = td.children[0];
	    checkbox.style.backgroundColor = failed;

	    error_messages.textContent = text;
	    error_response.style.display = 'block';
	}

	function WARN_RESPONSE ( text )
	{
	    warn_messages.textContent = text;
	    warn_response.style.display = 'block';
	}

	function IGNORE_WARNINGS()
	{
	    warn_response.style.display = 'none';
	    if ( check_proposed.style.backgroundColor
	         != on )
		SEND ( 'execute', DONE_RESPONSE );
	    else
		SEND ( 'send-changes',
		       COMPILE_RESPONSE );
	}

	function WARNINGS_NEXT()
	{
	    let row = problem_rows[current_row];
	    let td = row.children[0];
	    let checkbox = td.children[0];
	    checkbox.style.backgroundColor = off;
	    START_NEXT();
	}

	function EXECUTE ()
	{
	    compile_response.style.display = 'none';
	    SEND ( 'execute', DONE_RESPONSE );
	}

	let ids = document.getElementsByName ( 'id' );

	var xhttp = new XMLHttpRequest();
	var message_sent = null;
	var response_re = /^(\S+) (\S+)\s([^]*)$/;
	    // Backslash n is turned into a newline
	    // during initial character scanning
	    // before identifiers, comments, etc are
	    // parsed.
	function SEND ( message, callback )
	{
	    xhttp.onreadystatechange = function() {
		LOG ( 'xhttp state changed to state '
		      + this.readyState );
		if (     this.readyState
		     !== XMLHttpRequest.DONE
		     ||
		     message_sent == null )
		    return;

		if ( this.status != 200 )
		    FAIL ( 'Bad response status ('
			   + this.status
			   + ') from server replying '
			   + 'to ' + message_sent );

		LOG ( 'xhttp response: '
		      + this.responseText );
		let matches =
		    this.responseText.match
			( response_re );
		if ( matches == null
		     ||
		     ! ['DONE','COMPILED',
		        'WARN', 'ERROR']
		           .includes ( matches[1] ) )
		    FAIL ( 'bad response to ' +
		           message_sent +
			   ':\\n    ' +
			   this.responseText );
		message_sent = null;
		ID = matches[2];
		for ( var i = 0; i < ids.length; ++ i )
		{
		    // if ( ids[i] == null ) continue;
		    ids[i].value = ID;
		}
		callback ( matches[1], matches[3] );
	    };
	    xhttp.open ( 'POST', "project.php", true );
	    xhttp.setRequestHeader
		( "Content-Type",
		  "application/x-www-form-urlencoded" );
	    message_sent = message;
	    let data = message
	             + '&xhttp=yes&id=' + ID;
	    LOG ( 'xhttp sent: ' + data );
	    xhttp.send ( data );
	}

	</script>
EOT;
    }

?>

</body>
</html>
