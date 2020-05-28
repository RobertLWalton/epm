<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu May 28 15:39:42 EDT 2020

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
    // tains a +perm+ file all of whose lines have the
    // `owner' type.
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
    //	    OWNER-NAME.list =>
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
    //	     projects/PROJECT/PROBLEM/+solutions+
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

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //     EPM_DATA OP
    //		One of:
    //		    NULL
    //		    'push'
    //		    'pull'
    //
    // During a push or pull operation:
    //
    //	   EPM_DATA LIST
    //		Names current list for push or pull.
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

    // Non-XHTTP POSTs:
    // --------- -----
    //
    // Non-XHTTP POSTs include id=value-of-ID derived
    // from including in each form:
    //
    //	    <input type='hidden' id='id' name='id'
    //             value='ID-value'>
    //
    // Initially there is a project page used to select
    // the operation to be performed: push/pull/edit.
    //
    // Project Page POST:
    // ------- ---- ----
    //
    //	    op='OPERATION' selected-list='SELECTED-LIST'
    //		where OPERATION is 'push', 'pull, or
    //		'edit' and SELECTED-LIST is selected
    //		from <selection>... in the project page
    //		and has the format PROJECT:BASENAME.
    //
    //	    Response is one of 3 page layouts according
    //      to the operation.
    //
    // Push/Pull POST:
    // --------- ----
    //
    //	    done='yes'
    //		Return to project page.  Actions are
    //		performed using XHTTP (cancel simply
    //		sends done POST).


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

    
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_template.php";
        // This last is only needed by merge function.

    if ( $epm_method == 'GET' )
        $_SESSION['EPM_DATA'] = [
	    'OP' => NULL,
	    'LIST' => NULL ];

    $data = & $_SESSION['EPM_DATA'];
    $op = $data['OP'];
    $list = $data['LIST'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $delete_list = NULL;
        // Set to list to be deleted.  Causes delete
	// OK question.
    $compile_next = false;
    	// Set to cause first element of EPM_DATA
	// CHECKED-PROBLEMS to be compiled.

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
    //         <span class='problem-checkbox'
    //               onclick='PUSH(this)'>&nbsp;</span>
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
        global $epm_data;

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

	    if ( $project != '-' )
	    {
		$d = "projects/$project/$problem";
		if ( is_dir ( "$epm_data/$d" ) )
		{
		    $pmap = problem_permissions
		         ( $project, $problem );
		    if ( ! $pmap['owner'] )
		    {
		        $warnings[] =
			    "$problem => $project" .
			    " omitted because you" .
			    " are not an owner of" .
			    " the existing" .
			    " $project $problem";
			continue;
		    }
		}
	    }

	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <span class='problem-checkbox'
	        onclick='PUSH(this)'>&nbsp;</span>
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

    // Return a list containing the user's problems that
    // have a parent.  List elements are of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // and the TIME is the modification time of the
    // parents +changes+ file.  The list is sorted by
    // TIME.
    //
    function problems_to_pull_list ()
    {
        global $epm_data, $uid, $epm_time_format;
	$map = [];
	foreach ( read_problems()
	          as $problem => $project )
	{
	    if ( $project == '-' ) continue;
	    $f = "users/$uid/$problem/"
	       . "+parent+/+changes+";
	    $time = @filemtime ( "$epm_data/$f" );
	    if ( $time === false )
	    {
	        WARN ( "cannot stat $f" );
		continue;
	    }
	    $map["$project:$problem"] = $time;
	}
	arsort ( $map, SORT_NUMERIC );
	$list = [];
	foreach ( $map as $key => $time )
	{
	    list ( $project, $problem ) =
	        explode ( ':', $key );
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       $project, $problem];
	}
	return $list;
    }

    // Given a $listname in the form 'PROJECT:BASENAME',
    // where 'PROJECT:-' and '-:-' are allowed, get
    // the list of elements
    //
    //	   [TIME PROJECT PROBLEM]
    //
    // names and return as a string the rows of a table
    // for pulling problems.  The string has one segment
    // for each list element in the same order as the
    // elements in the list.
    //
    // The segment for one list element is:
    //
    //     <tr data-project='PROJECT'
    //         data-problem='PROBLEM'>
    //         <td>
    //         <span class='problem-checkbox'
    //               onclick='PULL(this)'>&nbsp;</span>
    //         <span class='problem'>
    //         PROBLEM &lArr; PROJECT NOTE TIME
    //         </span>
    //         </td>
    //         </tr>
    //
    // Here NOTE is one of:
    //
    //	   (new)	if PROBLEM does not exist for
    //			user
    //     (re-pull)	if PROBLEM exists and has
    //			PROJECT as its current parent
    //
    // If a list element refers to a PROBLEM that exists
    // but has no parent, or has a parent incompatible
    // with the PROJECT associated in the list element
    // (where PROJECT == '-' is compatible with all
    // projects), then a message is appended to
    // $warnings and NO string segment is generated for
    // the list element.
    //
    function listname_to_pull_rows
	    ( $listname, & $warnings )
    {
        global $epm_data, $uid, $epm_parent_re;

	list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $project == '-' )
	    $list = problems_to_pull_list();
	elseif ( $basename == '-' )
	    $list = read_project_list ( $project );
	else
	    $list = read_file_list
		( listname_to_filename ( $listname ) );

	$r = '';
        foreach ( $list as $items )
	{
	    list ( $time, $project, $problem ) = $items;
	    $f = "users/$uid/$problem";
	    if ( is_dir ( "$epm_data/$f" ) )
	    {
		$g = "$f/+parent+";
		if ( is_link ( "$epm_data/$g" ) )
		{
		    $parent = @readlink
		        ( "$epm_data/$g" );
		    if ( $parent === false )
		        ERROR ( "cannot read link $g" );
		    if ( ! preg_match ( $epm_parent_re,
		                        $parent,
					$matches ) )
		        ERROR ( "link $g has" .
			        " malformed value" .
				" $parent" );
		    $parent = $matches[1];
		    $parts = explode ( '/', $parent );
		    if ( $project == '-' )
		        $project = $parts[1];
		    $desired =
		         "projects/$project/$problem";
		    if ( $parent != $desired )
		    {
		        $warnings[] =
			    "$problem has been" .
			    " previously pulled from" .
			    " $parent and this" .
			    " conflicts with request" .
			    " to pull from $desired";
			continue;
		    }
		    else
		        $note = '(re-pull)';
		}
		else
		{
		    $warnings[] =
			"$problem already exists and" .
			" has no parent and so cannot" .
			" be pulled from" .
			" $project/$problem";
		    continue;
		}
	    }
	    elseif ( $project == '-' )
	    {
		$warnings[] =
		    "$problem in list has no" .
		    " associated project";
	        continue;
	    }
	    else
	        $note = '(new)';

	    $time = substr ( $time, 0, 10 );
	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <span class='problem-checkbox'
	        onclick='PULL(this)'>&nbsp;</span>
	    <span class='problem'>
	    $problem &lArr; $project $note $time
	    </span>
	    </td>
	    </tr>
EOT;
	}
	return $r;
    }

    // Compute EPM_DATA CHANGES, COMMANDS, PROJECT,
    // and PROBLEM to be used to push $problem to
    // $project.  If $problem has been pulled, it is
    // and error if it has not been pulled from
    // $project.  It is an error if $problem or $project
    // do not exist.
    //
    function compile_push_problem
	( $project, $problem, & $errors )
    {
	global $epm_data, $uid, $epm_filename_re,
	       $epm_time_format, $data,
	       $push_file_map;

        $srcdir = "users/$uid/$problem";
	$d = "projects/$project";
	if ( ! is_dir ( "$epm_data/$srcdir" ) )
	{
	    $errors[] = "$problem is not a problem";
	    return;
	}
	if ( ! is_dir ( "$epm_data/$d" ) )
	{
	    $errors[] = "$project is not a project";
	    return;
	}
	$desdir = "$d/$problem";
	$g = "$srcdir/+parent+";
	$new_push = true;
	if ( is_link ( "$epm_data/$g" ) )
	{
	    $new_push = false;
	    $s = @readlink ( "$epm_data/$g" );
	    if ( $s === false )
		ERROR ( "cannot read link $g" );
	    $sok = "../../../$desdir";
	    if ( $s != $sok )
	    {
	        $errors[] = "$g links to $s but should"
		          . " link to $sok";
		return;
	    }
	    if ( ! is_dir ( "$epm_data/$desdir" ) )
	    {
		$errors[] = "$desdir is not a"
		          . " directory";
		return;
	    }
	}
	elseif ( is_dir ( "$epm_data/$desdir" ) )
	{
	    $errors[] = "$project already has a problem"
	              . " named $problem";
	    return;
	}

	$changes = "Changes to Push $problem by $uid ("
	         . strftime ( $epm_time_format )
	         . "):" . PHP_EOL;
	$commands = [];
	if ( $new_push )
	{
	    $changes .= "make $project/$problem"
	              . " directory" . PHP_EOL;
	    $commands[] = ['mkdir', $desdir];
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
	    if ( ! isset ( $push_file_map[$ext] ) )
	        continue;
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
		if ( ! isset ( $action ) )
		    continue;
	    }
	    else
	        $action = $amap;

	    $g = "$srcdir/$fname";
	    $h = "$desdir/$fname";
	    if ( is_link ( "$epm_data/$g" ) )
	    {
	        $link = @readlink ( "$epm_data/$g" );
		if ( $link === false )
		    ERROR ( "cannot read link $g" );
		if ( $link == "../../../$h" )
		    continue;
	    }

	    $changes .= "move $fname to"
	    	      . " $project/$problem/$fname"
		      . PHP_EOL;
	    $commands[] =
	        ['rename', "$srcdir/$fname",
		           "$desdir/$fname"];
		    // This will also move a link.

	    if ( $action == 'R' ) continue;
	    if ( $action != 'L' )
	        ERROR ( "bad value for" .
		        " \$push_file_map['$ext']" );

	    $changes .= "link $fname to"
	              . " $project/$problem/$fname"
	              . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$desdir/$fname",
	                   "$srcdir/$fname"];
	}
	$fname = "$problem.optn";
	if ( is_readable
	    ( "$epm_data/$srcdir/$fname" ) )
	{
	    $changes .= "merge $fname into"
	    	      . " $project/$problem/$fname"
		      . PHP_EOL;
	    $commands[] =
	        ['merge', "$srcdir/$fname",
		          "$desdir/$fname"];
		// Merge merges optn files.
	}
	if ( $new_push )
	{
	    $changes .= "link +parent+ to"
	              . " $project/$problem"
	              . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$desdir",
	                   "$srcdir/+parent+"];
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
	       $push_file_map;

        $desdir = "users/$uid/$problem";
	$d = "projects/$project";
	if ( ! is_dir ( "$epm_data/$d" ) )
	{
	    $errors[] = "$project is not a project";
	    return;
	}
	$srcdir = "$d/$problem";
	if ( ! is_dir ( "$epm_data/$srcdir" ) )
	{
	    $errors[] = "project $project does not have"
	              . " a problem named $problem";
	    return;
	}
	$new_pull = true;
	if ( is_dir ( "$epm_data/$desdir" ) )
	{
	    $g = "$desdir/+parent+";
	    if ( ! is_link ( "$epm_data/$g" ) )
	    {
		$errors[] = "$uid already has a problem"
		          . " named $problem that was"
			  . " not pulled from any"
			  . " project";
		return;
	    }
	    $s = @readlink ( "$epm_data/$g" );
	    if ( $s === false )
		ERROR ( "cannot read link $g" );
	    $sok = "../../../$srcdir";
	    if ( $s != $sok )
	    {
	        $errors[] = "$g links to $s but should"
		          . " link to $sok";
		return;
	    }
	    $new_pull = false;
	}

	$changes = "Changes to Pull From $project ("
	         . strftime ( $epm_time_format )
	         . "):" . PHP_EOL;
	$commands = [];
	if ( $new_pull )
	{
	    $changes .= "make $uid/$problem"
	              . " directory" . PHP_EOL;
	    $commands[] = ['mkdir', $desdir];
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
	    if ( ! isset ( $push_file_map[$ext] ) )
	        continue;
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
		if ( ! isset ( $action ) )
		    continue;
	    }
	    else
	        $action = $amap;

	    if ( $action != 'L' ) continue;

	    $g = "$srcdir/$fname";
	    $h = "$desdir/$fname";
	    if ( is_link ( "$epm_data/$h" ) )
	    {
	        $link = @readlink ( "$epm_data/$h" );
		if ( $link === false )
		    ERROR ( "cannot read link $h" );
		if ( $link != "../../../$g" )
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

	    $changes .= "link $project/$problem/$fname"
	              . " to $fname"
	              . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$srcdir/$fname",
	                   "$desdir/$fname"];
	}
	$f = "$desdir/$problem.optn";
	if ( is_readable ( "$epm_data/$f" ) )
	{
	    $warnings[] =
		"existing $f will remain and override" .
		" inherited options;" . PHP_EOL .
		"use Options Page to revert to" .
		" inherited options if desired";
	}
	if ( $new_pull )
	{
	    $changes .= "link +parent+ to"
	              . " $project/$problem"
	              . PHP_EOL;
	    $commands[] = ['link',
	                   "../../../$srcdir",
	                   "$desdir/+parent+"];
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
    //     ['mkdir',$d] => mkdir $epm_data/$d
    //
    //     ['rename',$f,$g] => rename $epm_data/$f
    //				      $epm_data/$g
    //
    //     ['link',$f,$g] => symbolic_link $f
    //				           $epm_data/$g
    //
    function execute_commands ( & $errors )
    {
        global $epm_data, $data;

	$commands = $data['COMMANDS'];

	$umask = umask ( 06 );
	foreach ( $commands as $command )
	{
	    $cop = $command[0];
	    if ( $cop == 'mkdir' )
	        $r = @mkdir
		         ( "$epm_data/{$command[1]}" );
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

	$time = filemtime ( "$epm_data/$f" );
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

	$time = filemtime ( "$epm_data/$f" );
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

    if ( $epm_method == 'POST' )
    {
        if ( isset ( $_POST['op'] ) )
	{
	    if ( ! isset ( $_POST['selected-list'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $op = $_POST['op'];
	    $list = $_POST['selected-list'];

	    if ( ! in_array ( $op, ['push', 'pull'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    $data['OP'] = $op;
	    $data['LIST'] = $list;
	}
	elseif ( ! isset ( $op ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        elseif ( isset ( $_POST['cancel'] ) )
	{
	    $op = NULL;
	    $data['OP'] = $op;
	}
        elseif ( isset ( $_POST['send-changes'] ) )
	{
	    echo "COMPILED $ID\n";
	    echo $data['CHANGES'];
	    exit;
	}
        elseif ( isset ( $_POST['execute'] ) )
	{
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

	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! preg_match
	               ( $epm_name_re, $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    if ( $op == 'push' )
		compile_push_problem
		    ( $project, $problem, $errors );
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
    }

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
    span.problem-checkbox {
        height: 15px;
        width: 30px;
	display: inline-block;
	margin-right: 3px;
	border: 1px solid;
	border-radius: 7.5px;
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
    // Alert must be scheduled as separate task.
    //
    LOG ( "call to FAIL: " + message );
<?php
    if ( $epm_debug )
        echo <<<'EOT'
	    setTimeout ( function () {
		alert ( message );
		window.location.reload ( true );
	    });
EOT;
    else
        echo <<<'EOT'
	    throw "CALL TO FAIL: " + message;
EOT;
?>
}
</script>

</head>

<?php 

    if ( $op == 'pull' )
	$pull_rows = listname_to_pull_rows
	    ( $list, $warnings );
    elseif ( $op == 'push' )
	$push_rows = list_to_push_rows
	    ( listname_to_list ( $list ),
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

    if ( $op == 'push' || $op == 'pull' )
    {
	$cancel = ( $op == 'push' ?
		    'Cancel Pushing' :
		    'Cancel Pulling' );
	$warnings_help = HELP ( 'project-warnings' );
	$errors_help = HELP ( 'project-errors' );
	$proposed_help = HELP ( 'project-execute' );
	echo <<<EOT

	<div id='warn-response' style='display:none'>
	<form>
	<input type='hidden' id='id-warn'
	       name='id' value='$ID'>
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
	<button type='submit' name='done' value='yes'
	        formaction='project.php'
		formmethod='GET'>
	$cancel</button>
	</td>
	</td><td style='text-align:right'>
	$warnings_help</td>
	</tr>
	</table>
	</form>
	<pre id='warn-messages'></pre>
	</div>

	<div id='error-response' style='display:none'>
	<form>
	<input type='hidden' id='id-error'
	       name='id' value='$ID'>
	<table style='width:100%'>
	<tr>
	<td><strong>Errors:</strong></td>
	<td>
	<button type='button' onclick='START_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<button type='submit' name='cancel' value='yes'
	        formaction='project.php'
		formmethod='GET'>
	$cancel</button>
	</td>
	</td><td style='text-align:right'>
	$errors_help</td>
	</tr>
	</table>
	</form>
	<pre id='error-messages'></pre>
	</div>

	<div id='compile-response' style='display:none'>
	<form>
	<input type='hidden' id='id-compile'
	       name='id' value='$ID'>
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
	<button type='submit' name='cancel' value='yes'
	        formaction='project.php'
		formmethod='GET'>
	$cancel</button>
	</td>
	</td><td style='text-align:right'>
	$proposed_help</td>
	</tr>
	</table>
	</form>
	<pre id='compile-messages'></pre>
	</div>
EOT;
    }

    $project_help = HELP ( 'project-page' );
    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <input type='hidden' id='id-header'
           name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
EOT;
    if ( $op == 'push' || $op == 'pull' )
    	echo <<<EOT
	<td>
	<strong>User: $email</strong>
	</td>
	<td>
	<div id='done-response' style='display:none'>
	<strong>Done!</strong>
	<button type='submit'
		formaction='project.php'>
		Continue</button>
	<pre>    </pre>
	</div>
	<div id='check-proposed-display'
	     style='display:none'>
	<span class='problem-checkbox'
	      id='check-proposed'
	      onclick='CHECK(this)'>&nbsp;</span>
	<strong>Check Proposed Actions</strong>
	</div>
EOT;
    else
    	echo <<<EOT
	<td>
	<label>
	<strong>User:</strong>
	<input type='submit' value='$email'
	       formaction='user.php'
	       title='Click to See User Profile'>
	</label>
	</td>
	<td>
	<strong>Go To</strong>
	<button type='submit'
		formaction='problem.php'>
		Problem</button>
	<button type='submit'
		formaction='list.php'>
		Edit Lists</button>
	<button type='submit'
		formaction='favorites.php'>
		Edit Favorites</button>
	<strong>Page</strong>
EOT;
    echo <<<EOT
    </td>
    <td style='text-align:right'>
    $project_help</td>
    </tr>
    </table>
    </form>
EOT;
    if ( $op == NULL )
    {
	$options = list_to_options
	    ( favorites_to_list ( 'pull|push' ) );
	$push_title = 'Push Problems in Selected'
	            . ' List to Projects';
	$pull_title = 'Pull Problems in Selected'
	            . ' List from Projects';
	$select_title = 'Lists of'
	              . ' Problems to Push or Pull';
        echo <<<EOT
	<form method='POST'>
	<input type='hidden' id='id'
	       name='id' value='$ID'>
	<label>
	<strong>Select List:</strong>
	<select name='selected-list'
	        title='$select_title'>
	$options
	</select>
	<strong>and</strong>
	<button type='submit' name='op' value='push'
	        title='$push_title'>
	Push
	</button>
	<strong>or</strong>
	<button type='submit' name='op' value='pull'
	        title='$pull_title'>
	Pull
	</button>
	</label>
	</form>
EOT;
    }
    echo <<<EOT
    </div>
EOT;
    if ( $op == 'push' )
    {
	$push_help = HELP ( 'project-push' );
	$project_options = projects_to_options
	    ( read_projects ( 'push' ) );

	echo <<<EOT
	<div class='push-pull-list'>
	<form method='POST'>
	<input type='hidden' id='id'
	       name='id' value='$ID'>
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
		<input type='submit'
	               name='cancel'
		       value='Cancel'></td>
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
            $push_help</td>
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
            $push_help</td>
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
	        check_proposed_display.style.display =
		    'none';
	        done_response.style.display = 'inline';
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
	$pull_help = HELP ( 'project-pull' );

	echo <<<EOT
	<div class='push-pull-list'>
	<form method='POST'>
	<input type='hidden' id='id'
	       name='id' value='$ID'>
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
		<input type='submit'
	               name='cancel'
		       value='Cancel'></td>
	    <td>
            <td style='text-align:right'>
            $pull_help</td>
	</tr>
	<tr id='post-submit' style='display:none'>
	    <th id='post-submit'
	        style='text-align:left'>
	        <strong>Problems
		        (selected are being pulled)
		    </strong></th>
            <td style='text-align:right'>
            $pull_help</td>
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
	        check_proposed_display.style.display =
		    'none';
	        done_response.style.display = 'inline';
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
	        FAIL ( 'project for ' + problem +
		       ' is \'-\'' );
	    SEND ( op + '=pull&problem=' + problem
		      + '&project=' + project,
		   check_compile ?
		       COMPILE_RESPONSE :
		       DONE_RESPONSE );
	}

	</script>
EOT;
    }

    if ( $op == 'push' || $op == 'pull' )
    {
        $check_proposed =
	    ( $op == 'push' ? 'on' : 'off' );
        echo <<<EOT
	<script>

	var off = 'transparent';
	var on = 'black';
	var running = 'red';
	var succeeded = 'green';
	var failed = 'yellow';

	var id_warn =
	    document.getElementById('id-warn');
	var id_error =
	    document.getElementById('id-error');
	var id_compile =
	    document.getElementById('id-compile');
	var id_header =
	    document.getElementById('id-header');
	var id = document.getElementById('id');
	var problem_rows =
	    document.getElementById('problem-table')
	            .rows;
	var pre_submit =
	    document.getElementById('pre-submit');
	var post_submit =
	    document.getElementById('post-submit');
	var check_proposed_display =
	    document.getElementById
	        ('check-proposed-display');
	var check_proposed =
	    document.getElementById('check-proposed');
	check_proposed.style.backgroundColor =
	    $check_proposed;
	check_proposed_display.style.display = 'inline';

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
	var done_response =
	    document.getElementById
	        ('done-response');

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
		id_warn.value = matches[2];
		id_error.value = matches[2];
		id_compile.value = matches[2];
		id_header.value = matches[2];
		id.value = matches[2];
		callback ( matches[1], matches[3] );
	    };
	    xhttp.open ( 'POST', "project.php", true );
	    xhttp.setRequestHeader
		( "Content-Type",
		  "application/x-www-form-urlencoded" );
	    message_sent = message;
	    LOG ( 'xhttp sent: ' + message );
	    xhttp.send ( message + '&id=' + id.value );
	}

	</script>
EOT;
    }

?>

</body>
</html>
