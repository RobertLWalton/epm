<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Apr 22 13:19:06 EDT 2020

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
    //	   index	Allow attaching indices.
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
    // An index is a NAME.index file in a directory:
    //
    //	    users/UID/+indices+
    //
    // Such a file belongs to the UID user and can be
    // edited by the user.  The file begins with lines
    // of the form:
    //
    //	    TIME PROJECT PROBLEM
    //
    // that specify a problem in a project.  Thus an
    // index is a list of problems.  If a problem is not
    // in a project, but is in the user's users/UID
    // directory, PROJECT is `-'.  The TIME is the last
    // time the index entry was used to push or pull
    // the problem or perform a maintenance operation
    // on the problem (e.g., change owner).
    //
    // An index file ends with description paragraphs
    // describing the index, each preceeded by a blank
    // line.  Note that an index file that is empty
    // except for descriptions must begin with a blank
    // line.
    //
    // For each project the directory:
    //
    //	    projects/PROJECT/+indices+
    //
    // contains symbolic links of the form:
    //
    //	    UID-NAME.index =>
    //		users/UID/+indices+/NAME.index
    //
    // which make particular indices visible to users
    // who have `index' permission for the project.  To
    // these users these indices are read-only.  These
    // users may add symbolic links to their own
    // indices, and delete such links.
    //
    // In addition there are read-only indices contain-
    // ing the problems in the directories:
    //
    //	    users/UID
    //
    //	    projects/PROJECT
    //
    // These are used to edit other indices, which is
    // done by copying entries from one index to another
    // (via an intermediary called the stack).
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
    // has `review' permission for the project.  Users
    // who can create the review can delete it.
    //
    // A problem may be pulled by any user with `pull'
    // permission for the problem's project.  A problem
    // may be created in a project by any user with
    // `push' permission for the project.  When a
    // problem is created, the user that pushed it
    // becomes the sole owner of the problem.  Only an
    // owner of a problem (or of the containing project
    // or of all projects) may make subsequent pushes
    // after the problem has been created.
    //
    // When a problem is pushed, only uploaded files
    // and a few made files, such as .ftest and .pdf
    // files, are copied to the problem's project
    // subdirectory.
    //
    // Each project problem has its own .git repository
    // using the directory
    //
    //	    projects/PROJECT/PROBLEM/.git
    //
    // When the problem is pushed, this directory is
    // updated.  Versions in the directory may be tagged
    // by the problem owner.
    //
    // The following file usage log files are written
    // whenever a file or immediate subdirectory of the
    // log containing directory is `used':
    //
    //	    users/UID/+indices+/usage.log
    //	    projects/PROJECT/+indices+/usage.log
    //	    projects/PROJECT/PROBLEM/+review+/usage.log
    //	    projects/PROJECT/usage.log
    //
    // For indices, using means opening the index proper
    // for viewing, and not just reading the index des-
    // cription.  For reviews usage means opening the
    // review file for viewing, and for PROJECT direct-
    // ories, usage means pulling a problem.  The log
    // files contain lines with the format:
    //
    //		TIME UID FILENAME
    //
    // where TIME is the time of usage, UID is the ID of
    // the using user, and FILENAME is the name of the
    // file or subdirectory used.  These logs may be
    // purged when they get large of older entries that
    // have the same UID and FILENAME as more recent
    // entries.
    //
    // If an index file with symbolic link name
    // UID-NAME.index is opened, a log entry will be
    // written in both the directory containing the
    // symbolic link to the file and in the directory
    // containing the index file itself.
    //
    // For each user with given UID there is the file:
    //
    //	    users/UID/+indices+/+favorites+
    //
    // that lists the user's favorite indices.  Its
    // contents are lines of the forms:
    //
    //	    TIME PROJECT BASENAME
    //
    // indicating that the index file with the name
    // BASENAME.index in PROJECT was viewed at the given
    // TIME.  PROJECT may be '-' to indicate an index of
    // the current user and NOT of a project, and
    // BASENAME may be '-' to indicate the index is a
    // list of all the problems in the PROJECT or of all
    // the UID user's problems.  The user may edit this
    // in the same manner as the user edits lists.
    //
    // Lastly there are two stacks used in editing:
    //
    //	    users/UID/+indices+/+fstack+
    //	    users/UID/+indices+/+istack+
    //
    // +fstack+ is the favorites stack and contains
    // lines copied from or to be copied to the
    // +favorites+ file.  +istack+ is the index stack
    // and contains non-description lines copied from
    // or to be copied to indices.

    // Session Data
    // ------- ----

    // Session data is in EPM_PROJECT as follows:
    //
    //     EPM_PROJECT ID
    //	   	32 hex digit random number used to
    //		verify POSTs to this page.
    //
    //     EPM_PROJECT OP
    //		One of:
    //		    NULL
    //		    'push'
    //		    'pull'
    //		    'edit'
    //
    //	   EPM_PROJECT LIST
    //		Names current list for operations that
    //		need it, in the format PROJECT:BASENAME,
    //		or is NULL;
    //
    // During a push or pull operation:
    //
    //     EPM_PROJECT PROBLEM
    //		Name of problem being pushed or pulled.
    //
    //     EPM_PROJECT PROJECT
    //		Name of project into which problem is
    //		being pushed or from which it is being
    //		pulled.
    //
    //     EPM_PROJECT COMMANDS
    //		List of commands to be executed by
    //		execute_commands to accomplish the
    //		current push/pull operation.  Commands
    //		are to be executed with $epm_data being
    //		the current directory and 06 the current
    //		mask.
    //
    //     EPM_PROJECT CHANGES
    //		String to be appended to +changes+ file
    //		after commands are executed.  Also
    //		displayed to user when change-approval
    //		is required.
    //
    // During an edit operation;
    //
    //     EPM_PROJECT ELEMENTS
    //		A list of the elements of the form
    //
    //			[TIME PROBJECT PROBLEM]
    //		or
    //			[TIME PROJECT BASENAME]
    //
    //		that are displayed as list or stack
    //		rows.  The 'submit' POST contains
    //		indices of these elements for the
    //		edited version of the list and stack.


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
    //
    //
    // Edit POSTs:
    // ---- -----
    // 
    // The edit page presents a LIST and a stack, each
    // a list of entries, each with a natural number id.
    // Editing just rearranges these entries, possibly
    // duplication some, possibly deleting some.  NO
    // entries are created.  Rearrangement is done
    // strictly by javascript.
    //
    //	    cancel='yes'
    //		Return to project page and do NOT update
    //		list or stack.
    //
    //	    submit='yes' list='...' stack='...'
    //		Update list and stack and return to
    //		project page.
    //
    // In list='...' the '...' is just a list of entry
    // ids in the order that they appear in the edited
    // LIST, separated by `:'s.  Stack='...' is similar
    // but for the stack.  XHTTP is NOT used.


    // XHTTP POSTS
    // ----- -------
    //
    // XHTTP POSTs include id=value-of-ID
    //
    // XHTTP POSTs are used for push and pull
    // operations.

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

    if ( isset ( $_SESSION['EPM_RUN']['RESULT'] )
         &&
	 $_SESSION['EPM_RUN']['RESULT'] === true )
    {
	// Run still running.
	//
	header ( 'Location: /page/run.php' );
	exit;
    }

    require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    $user_dir = "users/$uid";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( $method == 'GET' )
        $_SESSION['EPM_PROJECT'] = [
	    'ID' => bin2hex ( random_bytes ( 16 ) ),
	    'OP' => NULL,
	    'LIST' => NULL ];

    $data = & $_SESSION['EPM_PROJECT'];
    $id = $data['ID'];
    $op = $data['OP'];
    $list = $data['LIST'];

    if ( $method == 'POST'
         &&
	 ( ! isset ( $_POST['ID'] )
	   ||
	   $_POST['ID'] != $id ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $id = bin2hex ( random_bytes ( 16 ) );
    $data['ID'] = $id;

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $compile_next = false;
    	// Set to cause first element of EPM_PROJECT
	// CHECKED-PROBLEMS to be compiled.

    // Permission maps.  These map:
    //
    //		permission => {true,false}
    //
    // according to whether or not the current $uid is
    // granted or not granted the permission.
    //
    $all_permissions =
        [ 'owner'  => true, 'push'  => true,
	  'pull'   => true, 'index' => true,
	  'review' => true ];
    $no_permissions =
        [ 'owner'  => false, 'push'  => false,
	  'pull'   => false, 'index' => false,
	  'review' => false ];

    // Add permissions from $pfile into permission map
    // $pmap.  Erroneous lines in the file generate
    // $errors messages and WARN messages and are
    // ignored.  $pfile is a file name relative to
    // $epm_data.  If $pfile is not readable, $pmap is
    // not changed (and it is NOT an error).  If a
    // permission TYPE is not set in the initial $pmap,
    // it is not legal.
    //
    function add_permissions ( & $pmap, $pfile )
    {
        global $uid, $epm_data, $errors;

	$c = @file_get_contents ( "$epm_data/$pfile" );
	if ( $c === false ) return;

	$c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	    // Get rid of `//...' comments.
	$c = explode ( "\n", $c );
	foreach ( $c as $line )
	{
	    $m = NULL;
	    $line = trim ( $line );
	    if ( $line == '' ) continue;
	    if ( ! preg_match
	               ( '/^(\S+)\s+(\S+)$/',
		         $line, $matches ) )
	        $m = "badly formatted permission"
		   . " '$line' in $f";
	    elseif ( preg_match ( '#/#', $line ) )
	        $m = "permission '$line' in $pfile has"
		   . " illegal '/'";
	    elseif ( ! isset ( $pmap[$matches[1]] ) )
	        $m = "bad permission type"
		   . " '{$matches[1]}' in $f";
	    else
	    {
	        $r = preg_match
		    ( "/({$matches[2]})/", $uid );
		if ( $r === false )
		    $m = "bad permission regular"
		       . " expression '{$matches[2]}'"
		       . " in $f";
		elseif ( $r )
		    $pmap[$matches[1]] = true;
	    }
	    if ( isset ( $m ) )
	    {
	        $errors[] = $m;
		WARN ( $m );
	    }
	}
    }

    // Return the permission map for a project.
    //
    function project_permissions ( $project )
    {
        global $all_permissions, $no_permissions;
        $pmap = ['owner' => false];
	add_permissions ( $pmap, 'projects/+perm+' );
	if ( $pmap['owner'] ) return $all_permissions;
	$pmap = $no_permissions;
	add_permissions
	    ( $pmap, "projects/$project/+perm+" );
	return $pmap;
    }

    // Return the permission map for a problem in a
    // project.  If the $uid has owner permission in
    // in projects/$project/$problem/+perm+, then
    // $all_permissions is returned, else the permis-
    // sions of the project is returned.
    //
    function problem_permissions ( $project, $problem )
    {
        global $all_permissions;
        $pmap = ['owner' => false];
	add_permissions
	    ( $pmap,
	      "projects/$project/$problem/+perm+" );
	if ( $pmap['owner'] ) return $all_permissions;
	return project_permissions ( $project );
    }

    // Return the list of projects that have a given
    // type of permission that matches the $type_re
    // regular expression.  The list is sorted in
    // natural order.
    //
    function read_projects ( $type_re )
    {
	global $epm_data, $epm_name_re;
	$projects = [];
	$ps = @scandir ( "$epm_data/projects" );
	if ( $ps == false )
	    ERROR ( "cannot read 'projects'" .
	            " directory" );
	foreach ( $ps as $project )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $project ) )
	        continue;
	    $pmap = project_permissions ( $project );
	    foreach ( $pmap as $type => $value )
	    {
	        if ( ! $value ) continue;
		if ( ! preg_match
		         ( "/^($type_re)\$/", $type ) )
		    continue;
	        $projects[] = $project;
		break;
	    }
	}
	natsort ( $projects );
	return $projects;
    }

    // Given a list of PROJECTs return a string whose
    // segments have the form
    //
    //	    <option value='PROJECT'>
    //      $project
    //      </option>
    //
    function projects_to_options ( $list )
    {
	$r = '';
	foreach ( $list as $project )
	{
	    $r .= "<option value='$project'>"
		. "$project</option>";
	}
	return $r;
    }

    // Return a map from a user's own problems to the
    // projects each is descended from, or '' if a
    // problem is not descended from a project.  Sort
    // the map by problems (keys) in natural order.
    //
    // If $enabling_map is NOT NULL, any PROBLEM such
    // that $enabling_map['PROBLEM'] is NOT set, or
    // is set to a value that is neither '' not the
    // project PROBLEM is descended from, is ignored.
    //
    function read_problems ( $enabling_map = NULL )
    {
	global $epm_data, $uid, $epm_name_re;

	$pmap = [];
	$f = "users/$uid";
	$ps = @scandir ( "$epm_data/$f" );
	if ( $ps == false )
	    ERROR ( "cannot read $f directory" );
	foreach ( $ps as $problem )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
	        continue;
	    $eproject = '';
	    if ( isset ( $enabling_map ) )
	    {
	        if ( ! isset
		          ( $enabling_map[$problem] ) )
		    continue;
		$eproject = $enabling_map[$problem];
	    }

	    $g = "$f/$problem/+parent+";
	    $re = "/\/\.\.\/projects\/([^\/]+)\/"
	        . "$problem\$/";
	    if ( is_link ( "$epm_data/$g" ) )
	    {
	        $s = @readlink ( "$epm_data/$g" );
		if ( $s === false )
		    ERROR ( "cannot read link $g" );
		if ( ! preg_match
		           ( $re, $s, $matches ) )
		    ERROR ( "link $g value $s is" .
		            " mal-formed" );
		if (    $eproject == ''
		     || $eproject == $matches[1] )
		    $pmap[$problem] = $matches[1];
	    }
	    elseif ( $eproject == '' )
		$pmap[$problem] = '';
	}
	ksort ( $pmap, SORT_NATURAL );
	return $pmap;
    }

    // Given the map produced by read_problems, return
    // the rows of a table for pushing problems as a
    // string.  The string has one segment for each
    //
    //		PROBLEM => PROJECT
    //
    // mapping element, in the same order as these
    // elements are in the map.  If PROJECT is ''
    // the parent is unknown.
    //
    // The segment for one problem is:
    //
    //     <tr data-project='PROJECT'
    //         data-problem='PROBLEM'>
    //         <td>
    //         <span class='problem-checkbox'
    //               onclick='PUSH(this)'>&nbsp;</span>
    //         <span class='problem'>
    //         PROBLEM &rArr; DESTINATION
    //         </span>
    //         </td>
    //         </tr>
    //
    // where DESTINATION is
    //
    //     <span class='selected-project'>
    //     Selected Project</span>
    //
    // if PROJECT == '' and PROJECT otherwise.
    //
    function problems_to_push_rows ( $map )
    {
	$r = '';
        foreach ( $map as $problem => $project )
	{
	    $destination =
	        ( $project == '' ?
		  "<span class='selected-project'>" .
		  "Selected Project</span>" :
		  $project );
	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <span class='problem-checkbox'
	        onclick='PUSH(this)'>&nbsp;</span>
	    <span class='problem'>
	    $problem &rArr; $destination
	    </span>
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
	    if ( $project == '' ) continue;
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

    // Return a list whose elements have the form:
    //
    //		[TIME - PROBLEM]
    //
    // for all PROBLEMs users/UID/PROBLEM where TIME
    // is the modification time of the problem +changes+
    // file, or is the current time if there is no
    // such file.  Sort by TIME.
    //
    function problems_to_edit_list()
    {
	global $epm_data, $uid, $epm_name_re,
	       $epm_time_format;

	$pmap = [];
	$f = "users/$uid";
	$ps = @scandir ( "$epm_data/$f" );
	if ( $ps == false )
	    ERROR ( "cannot read $f directory" );
	foreach ( $ps as $problem )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
	        continue;

	    $g = "$f/$problem/+changes+";
	    $time = @filemtime ( "$epm_data/$g" );
	    if ( $time === false ) $time = time();
	    $pmap[$problem] = $time;
	}
	arsort ( $pmap, SORT_NUMERIC );
	$list = [];
	foreach ( $pmap as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       '-', $problem];
	return $list;
    }

    // Given a list name of one of the forms:
    //
    //		-:-
    //		PROJECT:-
    //		PROJECT:BASENAME
    //		+favorites+
    //		+istack+
    //		+fstack+
    //
    // return the file name of the list relative to
    // $epm_data.  Return NULL if the name is of
    // the form -:- or PROJECT:-.
    //
    function listname_to_filename ( $listname )
    {
        global $uid;

	if ( preg_match ( '/\+.+\+/', $listname ) )
	    return "users/$uid/+indices+/$listname";

        list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $basename == '-' ) return NULL;

	if ( $project == '-' )
	    $d = "users/$uid/";
	else
	    $d = "projects/$project";
	return "$d/+indices+/{$basename}.index";
    }

    // Given a basename make a new empty file for the
    // listname '-:basename' and add its name to the
    // beginning of +favorites+ using the current
    // time as the TIME value.  If there are errors
    // append to $errors.
    //
    function make_new_list ( $basename, & $errors )
    {
        global $epm_data, $uid, $epm_name_re,
	       $epm_time_format;

	if ( ! preg_match ( $epm_name_re, $basename ) )
	{
	   $errors[] = "$basename is badly formed"
	             . " list name";
	   return;
	}
	$f = "users/$uid/+indices+/$basename.index";
	if ( file_exists ( "$epm_data/$f" ) )
	{
	   $errors[] = "the $basename list already"
	             . " exists";
	   return;
	}

	$r = @file_put_contents ( "$epm_data/$f", '' );
	if ( $r === false )
	    ERROR ( "could not write $f" );
	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "could not stat $f" );
	$time = strftime ( $epm_time_format, $time );

	$g = "users/$uid/+indices+/+favorites+";
	$c = @file_get_contents ( "$epm_data/$g", '' );
	if ( $c === false ) $c = '';
	$c = "$time - $basename" . PHP_EOL . $c;
	$r = @file_put_contents ( "$epm_data/$g", $c );
	if ( $r === false )
	    ERROR ( "could not write $g" );
    }

    // Return the lines from the list with the given
    // $filename in the form of a list of elements each
    // of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where PROJECT may be `-'.  Reading stops with the
    // first blank line.  If the file does not exist, []
    // is returned.  Line formatting errors are fatal.
    //
    function read_file_list ( $filename )
    {
        global $epm_data;
	$list = [];
	$map = [];
	$c = @file_get_contents
	    ( "$epm_data/$filename" );
	if ( $c !== false )
	{
	    $c = explode ( "\n", $c );
	    foreach ( $c as $line )
	    {
		$line = trim ( $line );
		if ( $line == '' ) break;

		$line = preg_replace
		    ( '/\h+/', ' ', $line );
		$items = explode ( ' ', $line );
		if ( count ( $items ) != 3 )
		    ERROR ( "badly formatted line" .
			    " '$line' in $filename" );
		list ( $time, $project, $problem ) =
		    $items;
		$key = "$project:$problem";
		if ( isset ( $map[$key] ) )
		    ERROR ( "line '$line' duplicates" .
			    " line '{$map[$key]}' in" .
			    " $filename" );
		$map[$key] = $line;
		$list[] = $items;
	    }
	}
	return $list;
    }

    // Write a list of elements of the form
    //
    //	    [TIME PROJECT NAME]
    //
    // to the named file, preserving any part of the
    // file that is after its first blank line.  Each
    // element becomes one line consisting of the
    // element members separated by 2 single spaces.
    //
    // If a PROJECT:NAME occurs several times, only the
    // first is kept, but the output TIME is the latest
    // of the associated TIMEs.
    //
    function write_file_list ( $filename, $list )
    {
        global $epm_data;
	$keys = [];
	$map = [];
	foreach ( $list as $items )
	{
	    list ( $time, $project, $name ) = $items;
	    $key = "$project:$name";
	    if ( isset ( $map[$key] ) )
	    {
	        $time2 = $map[$key];
		if ( $time > $time2 )
		    $map[$key] = $time;
	    }
	    else
	    {
	        $map[$key] = $time;
		$keys[] = $key;
	    }
	}
	$lines = [];
	foreach ( $keys as $key )
	{
	    list ( $project, $name ) =
	        explode ( ':', $key );
	    $lines[] = "{$map[$key]} $project $name";
	}

	$c = @file_get_contents
	    ( "$epm_data/$filename" );
	if ( $c !== false )
	{
	    $flines = explode ( "\n", $c );
	    $in_description = false;
	    $last_blank = true;
	    // This deletes blank lines at end of
	    // file.
	    foreach ( $flines as $fline )
	    {
	        $fline = rtrim ( $fline );
		    // We need to find blank lines BUT
		    // leave indentation of non-blank
		    // lines.
		if ( $fline == '' )
		{
		    if ( ! $in_description )
		        $in_description = true;
		    else
		        $last_blank = true;
		}
		else
		{
		    if ( ! $in_description )
		        continue;

		    if ( $last_blank )
		    {
		        $lines[] = '';
			$last_blank = false;
		    }
		    $lines[] = $fline;
		}
	    }
	}

	$c = '';
	foreach ( $lines as $line )
	    $c .= $line . PHP_EOL;
	$r = @file_put_contents
	         ( "$epm_data/$filename", $c );
	if ( $r === false )
	    ERROR ( "cannot write $filename" );
    }

    // Return the problems in $project in the form
    // of a list of elements each of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where TIME is the modification time of the
    // PROBLEM's +changes+ file.  List elements
    // are sorted most recent TIME first.
    //
    function read_project_list ( $project )
    {
        global $epm_data, $epm_name_re,
	       $epm_time_format;

	// First build map from PROBLEM to TIME
	// and sort on TIME.
	//
	$map = [];
	$d = "projects/$project";
	$problems = @scandir ( "$epm_data/$d" );
	if ( $problems === false )
	    ERROR ( "cannot read $d" );
	foreach ( $problems as $problem )
	{
	    if ( ! preg_match ( $epm_name_re,
	                        $problem ) )
	        continue;
	    $f = "$d/$problem/+changes+";
	    $time = @filemtime ( "$epm_data/$f" );
	    if ( $time === false )
	    {
	        WARN ( "cannot stat $f" );
		continue;
	    }
	    $map[$problem] = $time;
	}
	arsort ( $map, SORT_NUMERIC );

	$list = [];
	foreach ( $map as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       $project, $problem];
	return $list;
    }

    // Given a $listname in the form 'PROJECT:BASENAME',
    // where 'PROJECT:-' and '-:-' are allowed, make an
    // $enabling_map from the named list, and then call
    // read_problems and problems_to_push_rows to return
    // a string or rows as per the latter function.
    //
    // If a PROBLEM appears in a list with more than one
    // PROJECT, it will be given the project *AMBIGUOUS*
    // in the $enabling_map, which effectively causes
    // the problem to be ignored.  It is assumed that
    // a PROBLEM will not appear twice in the same list
    // with the same PROJECT.
    //
    function listname_to_push_rows ( $listname )
    {
	list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $project != '-' )
	{
	    $enabling_map = [];
	    if ( $basename != '-' )
	        $list = read_file_list
		    ( listname_to_filename
		          ( $listname ) );
	    else
	        $list = read_project_list ( $project );

	    foreach ( $list as $items )
	    {
		list ( $time, $project, $problem ) =
		    $items;
		if ( isset ( $enabling_map[$problem] ) )
		    $enabling_map[$problem] =
		        '*AMBIGUOUS*';
		else
		    $enabling_map[$problem] = $project;
	    }
	}
	else
	    $enabling_map = NULL;

	return problems_to_push_rows
	    ( read_problems ( $enabling_map ) );
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
        global $epm_data, $uid;

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
	$re = '#^\.\./\.\./\.\./projects/(.+)$#';
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
		    if ( ! preg_match ( $re, $parent,
		                        $matches ) )
		        ERROR ( "link $g has" .
			        " malformed value" .
				" $parent" );
		    $parent = $matches[1];
		    if ( $project == '-' )
		        $project =
			    explode ( '/', $parent )[0];

		    if (    "$project/$problem"
		         != $parent )
		    {
		        $warnings[] =
			    "$problem already has" .
			    " parent $parent that" .
			    " conflicts with request" .
			    " to pull from" .
			    " $project/$problem";
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

    // Given a list of elements of the form
    //
    //		[TIME PROJECT PROBLEM]
    //
    // where PROJECT may be '-', add the elements to the
    // $elements list and return a string whose segments
    // are HTML rows of the form:
    //
    //		<tr class='edit-row'>
    //		<td></td>
    //		<td data-index='I' class='edit-name'>
    //		PROJECT PROBLEM TIME
    //		</td>
    //		</tr>
    //
    // where if PROJECT is '-' it is replaced by
    // '<i>Your</i>' in the string, TIME is the first
    // 10 characters of the time (just the day part),
    // and I is the index of the element in the
    // $elements list.
    //
    function list_to_edit_rows ( & $elements, $list )
    {
	$r = '';
	foreach ( $list as $element )
	{
	    $I = count ( $elements );
	    $elements[] = $element;
	    list ( $time, $project, $problem ) =
	        $element;
	    if ( $project == '-' )
		$project = '<i>Your</i>';
	    $time = substr ( $time, 0, 10 );
	    $r .= <<<EOT
	          <tr>
		  <td><button type='button'
		              onclick='DELETE(this)'>
		      &Chi;</button></td>
		  <td data-index='$I' class='edit-name'
		       onclick='DUP(this)'>
		  $project $problem $time
		  </td>
		  </tr>
EOT;
	}
	return $r;
    }

    // Given a $listname return the list of elements
    //
    //		[TIME PROJECT PROBLEM]
    //
    // named.  $listname may be one of:
    //
    //     -:-
    //     PROJECT:-
    //     PROJECT:BASENAME
    //	   +istack+
    //	   +fstack+
    //	   +favorites+
    //
    // Note that if $listname ends with '-' the list is
    // read-only.
    //	   
    function listname_to_list ( $listname )
    {
	if ( $listname == '-:-' )
	    return problems_to_edit_list();
	elseif ( preg_match ( '/^(.+):-/',
	                      $listname, $matches ) )
    	    return read_project_list ( $matches[1] );
	else
	    return read_file_list
		( listname_to_filename ( $listname ) );
    }

    // Return the lines from:
    //
    //	    users/UID/+indices+/+favorites+
    //
    // in the form of an ordered map of elements of the
    // form
    //
    //	    PROJECT:BASENAME => TIME
    //
    // For each PROJECT:BASENAME pair, if the pair is
    // not in the +favorites+ file, but is a key in
    // $inmap, then the $inmap element is added to the
    // end of the output map, preserving the order of
    // $inmap.
    //
    // A non-existant +favorites+ file is treated as a
    // file of zero length.  File line formatting errors
    // are fatal.
    //
    // Note that PROBLEM == '-' denotes the list of
    // problems in PROJECT, or if PROJECT is also '-',
    // the list of the UID user's problems is denotes.
    //
    function read_favorites ( $inmap = [] )
    {
        global $epm_data, $uid;

	// First build a map PROJECT:BASENAME => TIME
	// from the +favorites+ file.  Then add to it.
	//
	$outmap = [];
	$f = "users/$uid/+indices+/+favorites+";
	$linemap = [];
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c !== false )
	{
	    $c = explode ( "\n", $c );
	    foreach ( $c as $line )
	    {
		$line = trim ( $line );
		if ( $line == '' ) continue;
		$line = preg_replace
		    ( '/\h+/', ' ', $line );
		$items = explode ( ' ', $line );
		if ( count ( $items ) != 3 )
		    ERROR ( "badly formatted line" .
			    " '$line' in $f" );
		list ( $time, $project, $basename ) =
		    $items;
		$key = "$project:$basename";
		if ( isset ( $map[$linekey] ) )
		    ERROR ( "line '$line' duplicates" .
			    " line '{$linemap[$key]}'" .
			    " in $f" );
		$linemap[$key] = $line;
		$outmap[$key] = $time;
	    }
	}
	foreach ( $inmap as $key => $time )
	{
	    if ( ! isset ( $outmap[$key] ) )
	        $outmap[$key] = $time;
	}

	return $outmap;
    }

    // Given a $type_re to pass to read_projects,
    // build an $inmap containing first the user's
    // own problems and then all the projects returned
    // by read_projects.  Use the current time for
    // $inmap elements.  Then call read_favorites with
    // $inmap to get a map of favorites whose elements
    // have the form:
    //
    //		PROJECT:PROBLEM => TIME
    //
    // From this list return a a string whose segments
    // have the form
    //
    //	    <option value='PROJECT:BASENAME'>
    //      $project $basename $time
    //      </option>
    //
    // where $project is PROJECT unless that is `-', in
    // which case it is `Your', $basename is
    // BASENAME unless that is `-', in which case it is
    // `Problems', and $time is the first 10 characters
    // of TIME (i.e., the day, excluding the time of
    // day).
    //
    // Note that markup such as <i> is NOT recognized in
    // options by HTML, so is not used here.
    //
    function favorites_to_options ( $type_re )
    {
	global $epm_time_format;
	$time = strftime ( $epm_time_format );
	$inmap = [ '-:-' => $time ];
	foreach ( read_projects ( $type_re )
	          as $project )
	{
	    $key = "$project:-";
	    $inmap[$key] = $time;
	}

	$fmap = read_favorites ( $inmap );

	$r = '';
	foreach ( $fmap as $key => $time )
	{
	    list ( $project, $basename ) =
	        explode ( ':', $key );
	    if ( $project == '-' )
		$project = 'Your';
	    if ( $basename == '-' )
		$basename = 'Problems';
	    else
		$basename = preg_replace
		    ( '-', ' ', $basename );
	    $time = substr ( $time, 0, 10 );
	    $r .= "<option value='$key'>"
		. "$project $basename $time"
		. "</option>";
	}
	return $r;
    }

    // Compute EPM_PROJECT CHANGES, COMMANDS, PROJECT,
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
	    $commands[] = "mkdir $desdir";
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
	        "mv -f $srcdir/$fname $desdir";
		    // This will also move a link.

	    if ( $action == 'R' ) continue;
	    if ( $action != 'L' )
	        ERROR ( "bad value for" .
		        " \$push_file_map['$ext']" );

	    $changes .= "link $fname to"
	              . " $project/$problem/$fname"
	              . PHP_EOL;
	    $commands[] = "ln -s"
	                . " ../../../$desdir/$fname"
	                . " $srcdir/$fname";
	}
	if ( $new_push )
	{
	    $changes .= "link +parent+ to"
	              . " $project/$problem"
	              . PHP_EOL;
	    $commands[] = "ln -s"
	                . " ../../../$desdir"
	                . " $srcdir/+parent+";
	}
	if ( count ( $commands ) == 0 )
	    $changes = '';
	$data['PROJECT'] = $project;
	$data['PROBLEM'] = $problem;
	$data['CHANGES'] = $changes;
	$data['COMMANDS'] = $commands;
    }

    // Compute EPM_PROJECT CHANGES, COMMANDS, PROJECT,
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
	    $commands[] = "mkdir $desdir";
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
	    $commands[] = "ln -s"
	                . " ../../../$srcdir/$fname"
	                . " $desdir/$fname";
	}
	if ( $new_pull )
	{
	    $changes .= "link +parent+ to"
	              . " $project/$problem"
	              . PHP_EOL;
	    $commands[] = "ln -s"
	                . " ../../../$srcdir"
	                . " $desdir/+parent+";
	}
	if ( count ( $commands ) == 0 )
	    $changes = '';
	$data['PROJECT'] = $project;
	$data['PROBLEM'] = $problem;
	$data['CHANGES'] = $changes;
	$data['COMMANDS'] = $commands;
    }

    // Execute EPM_PROJECT COMMANDS.  Errors cause abort
    // and append to $errors.  A command is in error
    // if it produces any standard output or standard
    // error output.
    //
    function execute_commands ( & $errors )
    {
        global $epm_data, $data;

	$commands = $data['COMMANDS'];
	if ( count ( $commands ) == 0 ) return;

	foreach ( $commands as $command )
	{
	    $output = [];
	    exec ( "umask 06; cd $epm_data;" .
	           " $command 2>&1", $output );
	    $err = '';
	    foreach ( $output as $line )
	    {
	        if ( preg_match ( '/^\s*$/', $line ) )
		    continue;
		if ( $err != '' ) $err .= PHP_EOL;
		$err .= "    $line";
	    }
	    if ( $err != '' )
	    {
	        $err = "Error in $command:" . PHP_EOL
		     . $err;
		WARN ( $err );
		$errors[] = $err;
		return;
	    }
	}
    }

    // Write EPM_PROJECT CHANGES to destination
    // +changes+ file.
    //
    function record_push_execution ()
    {
	global $epm_data, $data;

	$changes = $data['CHANGES'];
	if ( $changes == '' ) return;

	$project = $data['PROJECT'];
	$problem = $data['PROBLEM'];
	$f = "projects/$project/$problem/"
	   . "+changes+";
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );
    }

    // Write EPM_PROJECT CHANGES to destination
    // +changes+ and source +pulls+ files.
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
	$g = "projects/$project/$problem/+pulls+";
	$r = @file_put_contents
	    ( "$epm_data/$g", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $g" );
    }

    function execute_edit ( & $errors )
    {
        global $data, $list;

	if ( ! isset ( $_POST['list'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	if ( ! isset ( $_POST['stack'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	$elements = $data['ELEMENTS'];
	$bound = count ( $elements );

	$fname = listname_to_filename ( $list );
	$sname = listname_to_filename
	    ( $list == '+favorites+' ?
	      '+fstack+' : '+istack+' );

	$indices = explode ( ':', $_POST['stack'] );
	$slist = [];
	foreach ( $indices as $index )
	{
	    if ( $index < 0 || $index >= $bound )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $slist[] = $elements[$index];
	}

	if ( isset ( $fname ) )
	{
	    $indices = explode ( ':', $_POST['list'] );
	    $flist = [];
	    foreach ( $indices as $index )
	    {
	        if ( $index < 0 || $index >= $bound )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		$flist[] = $elements[$index];
	    }
	    write_file_list ( $fname, $flist );
	        // Don't write until all UNACCEPTABLE
		// HTTP POST checks done.
	}

	write_file_list ( $sname, $slist );
    }

    if ( $method == 'POST' )
    {
        if ( isset ( $_POST['op'] ) )
	{
	    if ( ! isset ( $_POST['selected-list'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $op = $_POST['op'];
	    $list = $_POST['selected-list'];

	    if ( ! in_array ( $op, ['push', 'pull',
	                            'edit'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    if ( $list == '+favorites+'
		 &&
		 $op != 'edit' )
	    {
		$errors[] = "Favorites is not allowed"
			  . " as a list of problems to"
			  . " $op";
		$op = NULL;
		$data['OP'] = $op;
	    }
	    else
	    {
		$data['OP'] = $op;
		$data['LIST'] = $list;
	    }
	}
	elseif ( ! isset ( $op ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        elseif ( isset ( $_POST['cancel'] ) )
	{
	    if ( $op != 'edit' )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $op = NULL;
	    $data['OP'] = $op;
	}
        elseif ( isset ( $_POST['update'] ) )
	{
	    if ( $op != 'edit' )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $_POST['new-list'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $_POST['basename'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    execute_edit ( $errors );
	    if ( count ( $errors ) > 0 )
	        /* do nothing */;
	    elseif ( $_POST['update'] == 'done' )
	    {
		$op = NULL;
		$data['OP'] = $op;
	    }
	    elseif ( $_POST['update'] == 'change-list' )
	    {
		$list = $_POST['new-list'];
		$data['LIST'] = $list;
	    }
	    elseif ( $_POST['update'] == 'create-list' )
	    {
		$basename = $_POST['basename'];
		make_new_list ( $basename, $errors );
		if ( count ( $errors ) == 0 )
		{
		    $list = "-:$basename";
		    $data['LIST'] = $list;
		}
	    }
	}
        elseif ( isset ( $_POST['cancel'] ) )
	{
	    $op = NULL;
	    $data['OP'] = $op;
	}
        elseif ( isset ( $_POST['send-changes'] ) )
	{
	    if ( $op == 'edit' )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    echo "COMPILED $id\n";
	    echo $data['CHANGES'];
	    exit;
	}
        elseif ( isset ( $_POST['execute'] ) )
	{
	    if ( $op == 'edit' )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    execute_commands ( $errors );
	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $id\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $op == 'push' )
	        record_push_execution();
	    else
	        record_pull_execution();
	    echo "DONE $id\n";
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
		echo "ERROR $id\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( count ( $warnings ) > 0 )
	    {
		echo "WARN $id\n";
		foreach ( $warnings as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $just_compile )
	    {
		echo "COMPILED $id\n";
		echo $data['CHANGES'];
		exit;
	    }
	    execute_commands ( $errors );
	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $id\n";
		foreach ( $errors as $e )
		    echo "$e\n";
		exit;
	    }
	    if ( $op == 'push' )
	        record_push_execution();
	    else
	        record_pull_execution();
	    echo "DONE $id\n";
	    exit;
	}
    }

?>

<html>
<head>
<style>
    @media screen and ( max-width: 1365px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	}
    }
    @media screen and ( min-width: 1366px ) {
	:root {
	    --font-size: 16px;
	    --large-font-size: 20px;
	    width: 1366px;
	    font-size: var(--font-size);
	    overflow: scroll;
	}
    }
    .indented {
	margin-left: 20px;
    }
    .inline {
	display:inline;
    }
    .no-margin {
	margin: 0 0 0 0;
    }
    h5 {
        font-size: var(--large-font-size);
	margin: 0 0 0 0;
	display:inline;
    }
    pre, button, input, select, form {
	display:inline;
        font-size: var(--font-size);
    }
    button {
        border-width: 2px;
	padding: 1px 6px 1px 6px;
    }
    div.op th {
        font-size: var(--large-font-size);
	text-align: left;
    }
    span.problem {
	display:inline;
        font-size: var(--large-font-size);
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    div.errors, div.notices {
	background-color: #F5F81A;
    }
    div.warnings {
	background-color: #FFC0FF;
    }
    div.progress {
	background-color: #00FF00;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
	padding-top: 5px;
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
    #stack-table {
	background-color: #E6FF99;
	float: left;
	width: 50%;
        font-size: var(--large-font-size);
    }
    #list-table {
	background-color: #B3FFB3;
	float: left;
	width: 50%;
        font-size: var(--large-font-size);
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
	margin-left: 20px;
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
	margin-left: 20px;
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
	margin-left: 20px;
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
	// Must execute before $warnings is used.

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>";
	echo "<h5>Errors:</h5>";
	echo "<div class='indented'>";
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<h5>Warnings:</h5>";
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
	<table style='width:100%'>
	<tr>
	<td><h5>WARNINGS:</h5></td>
	<td>
	<button type='button'
	        onclick='IGNORE_WARNINGS()'>
	IGNORE</button>
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
	<table style='width:100%'>
	<tr>
	<td><h5>Errors:</h5></td>
	<td>
	<button type='button' onclick='START_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<button type='submit' name='done' value='yes'
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
	<table style='width:100%'>
	<tr>
	<td><h5>Proposed Actions:</h5></td>
	<td>
	<button type='button' onclick='EXECUTE()'>
	EXECUTE</button>
	<pre>    </pre>
	<button type='button' onclick='SKIP_TO_NEXT()'>
	Skip to Next</button>
	<pre>    </pre>
	<button type='submit' name='done' value='yes'
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
    <form>
    <table style='width:100%'>
    <tr>
    <td>
    <label>
    <h5>User:</h5> <input type='submit' value='$email'
		    formaction='user.php'
		    formmethod='GET'
                    title='click to see user profile'>
    </label>
    </td>
    <td>
    <div id='done-response' style='display:none'>
    <h5>Done!</h5>
    <button type='submit'
	    formaction='project.php'
	    formmethod='GET'>
	    Continue</button>
    <pre>  </pre>
    </div>
    <div id='check-proposed-display'
         style='display:none'>
    <span class='problem-checkbox'
	  id='check-proposed'
	  onclick='CHECK(this)'>&nbsp;</span>
    <h5>Check Proposed Actions</h5>
    <pre>  </pre>
    </div>
    <h5>Go To:</h5>
    <button type='submit'
	    formaction='problem.php'
	    formmethod='GET'>
	    Problem Page</button>
    <pre>  </pre>
    <button type='submit'
	    formaction='run.php'
	    formmethod='GET'>
	    Run Page</button>
    </td>
    <td>
    </td><td style='text-align:right'>
    $project_help</td>
    </tr>
    </table>
    </form>
EOT;
    if ( $op == NULL )
    {
	$options = favorites_to_options ( 'pull|push' )
	         . "<option value='+favorites+'>"
		 . "Favorites</option>";
        echo <<<EOT
	<form method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit' name='create-list'
	       value= ''
	       style='visibility:hidden'>
	       <!-- This is the first submit input
	            in the form and is therefore
		    triggered when a new list name
		    is entered at the end of the form.
		 -->
	<button type='submit' name='op' value='push'>
	Push
	</button>
	<pre>  </pre>
	<button type='submit' name='op' value='pull'>
	Pull
	</button>
	<pre>  </pre>
	<button type='submit'
	       name='op' value='edit'>
	Edit List
	</button>
	<pre>  </pre>
	<label>
	<h5>Select List</h5>
	<select name='selected-list'>
	$options
	</select>
	</label>
	<pre>  </pre>
	<h5>or Create New List</h5>
	<input type="text"
	       size="24" name="new-list"
               placeholder="New List Name"
	       id="create-list">
	       <!-- Pressing the enter key here
	            triggers the hidden input submit
		    above.
		-->
	</form>
EOT;
    }
    echo <<<EOT
    </div>
EOT;
    if ( $op == 'push' )
    {
	$push_help = HELP ( 'project-push' );

	$rows = listname_to_push_rows ( $list );

	$project_options = projects_to_options
	    ( read_projects ( 'push' ) );

	echo <<<EOT
	<div class='push-pull-list'>
	<form method='POST'>
	<input type='hidden' id='ID'
	       name='ID' value='$id'>
	<table width='100%' id='problem-table'>
	<tr id='pre-submit'>
	    <th style='text-align:left'>
	        <h5>Problems (select to push)</h5></th>
	    <td><input type='button'
	               onclick='SUBMIT_PUSH()'
		       value='Submit'>
	        <input type='button'
	               onclick='RESET_PUSH()'
		       value='Reset'>
		<input type='submit'
	               name='done'
		       value='Cancel'></td>
	    <td id='project-selector'
	        style='visibility:hidden'>
	    <label class='select-project'>
	    <h5>Select Project</h5>:
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
	        <h5>Problems (selected are being pushed)
		    </h5></th>
	    <td id='selected-project-column'>
	    <h5>Selected Project:
	    <span id='selected-project-value'
	          class='selected-project'>
	    </span></h5></td>
	    <td>
            <td style='text-align:right'>
            $push_help</td>
	</tr>
	$rows
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
		if ( row.dataset.project == ''
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
		if ( row.dataset.project == ''
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
		if ( row.dataset.project == ''
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
	    if ( project == '' )
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
	<input type='hidden' id='ID'
	       name='ID' value='$id'>
	<table width='100%' id='problem-table'>
	<tr id='pre-submit'>
	    <th style='text-align:left'>
	        <h5>Problems (select to pull)</h5></th>
	    <td><input type='button'
	               onclick='SUBMIT_PULL()'
		       value='Submit'>
	        <input type='button'
	               onclick='RESET_PULL()'
		       value='Reset'>
		<input type='submit'
	               name='done'
		       value='Cancel'></td>
	    <td>
            <td style='text-align:right'>
            $pull_help</td>
	</tr>
	<tr id='post-submit' style='display:none'>
	    <th id='post-submit'
	        style='text-align:left'>
	        <h5>Problems (selected are being pulled)
		    </h5></th>
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
	    if ( project == '' )
	        FAIL ( 'project for ' + problem +
		       ' is \'\'' );
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
        echo <<<EOT
	<script>

	var off = 'transparent';
	var on = 'black';
	var running = 'red';
	var succeeded = 'green';
	var failed = 'yellow';

	var ID =
	    document.getElementById('ID');
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
	check_proposed.style.backgroundColor = on;
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
		ID.value = matches[2];
		callback ( matches[1], matches[3] );
	    };
	    xhttp.open ( 'POST', "project.php", true );
	    xhttp.setRequestHeader
		( "Content-Type",
		  "application/x-www-form-urlencoded" );
	    message_sent = message;
	    LOG ( 'xhttp sent: ' + message );
	    xhttp.send ( message + '&ID=' + ID.value );
	}

	</script>
EOT;

    if ( $op == 'edit' )
    {
	$edit_help = HELP ( 'project-edit' );

	$data['ELEMENTS'] = [];
	$elements = & $data['ELEMENTS'];

	$list_rows = list_to_edit_rows
	    ( $elements, listname_to_list ( $list ) );
	$is_read_only = 'false';
	if ( $list == '+favorites+' )
	{
	    $name = '<i>Favorites</i>';
	    $stack = '+fstack+';
	}
	else
	{
	    list ( $project, $basename ) =
		explode ( ':', $list );
	    if ( $project == '-' )
		$project = '<i>Your</i>';
	    if ( $basename == '-' )
	    {
		$basename =
		    '<i>Problems</i> (read-only)';
		$is_read_only = 'true';
	    }
	    $name = "$project $basename";
	    $stack = '+istack+';
	}
	$stack_rows = list_to_edit_rows
	    ( $elements, listname_to_list ( $stack ) );

	$options = favorites_to_options ( 'pull|push' )
	         . "<option value='+favorites+'>"
		 . "Favorites</option>";

	echo <<<EOT
	<div class='edit-list'>
	<div style='display:inline'>

	<form method='POST' action='project.php'
	      id='submit-form'>
	<input type='hidden' name='ID' value='$id'>
	<input type='hidden' name='update' id='update'>
	<input type='hidden' name='new-list' id='new-list'>
	<input type='hidden' name='basename' id='basename'>
	<input type='hidden' name='stack' value=''
	       id='stack-args'>
	<input type='hidden' name='list' value=''
	       id='list-args'>
	</form>

	<input type='button'
	       onclick='CLEAR_EDIT()'
	       value='Clear'>
	<form method='POST' action='project.php'
	      style='display:inline'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit'
	       name='cancel'
	       value='Cancel'></td>
	</form>
	<input type='button'
	       onclick='UPDATE_EDIT ( "done" )'
	       value='Done'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "update" )'
	       value='Update'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "change-list" )'
	       value='Change List'>
	<h5>:</h5>
	<select id='change-list'>
	$options
	</select>
	<pre>   </pre>
	<h5>or Create New List:</h5>
	<input type="text"
	       id='created-basename'
	       size="24"
               placeholder="New List Name"
	       onkeydown='CREATE_NEW_LIST(event)'>
	</form>
	</div>
	<div style='display:inline;float:right'>
	$edit_help
	</div>
	</div>

	<table id='stack-table'>
	<tr class='edit-row'>
	<th colspan=2><i>Stack</i></th></tr>
	$stack_rows
	</table>
	<table id='list-table'>
	<tr class='edit-row'>
	<th colspan=2>$name</th></tr>
	$list_rows
	</table>

	<script>

	let is_read_only = '$is_read_only';

	let stack_table = document.getElementById
	    ( 'stack-table' );
	let stack_rows = stack_table.rows;
	let list_table = document.getElementById
	    ( 'list-table' );
	let list_rows = list_table.rows;

	let delete_button =
	    "<button type='button'" +
		   " onclick='DELETE(this)'>" +
	    "&Chi;</button>";

	if ( is_read_only )
	    for ( var i = 1; i < list_rows.length;
	                     ++ i )
		list_rows[i].children[0].style.display =
		    'none';
	else
	    for ( var i = 0; i < list_rows.length;
	                     ++ i )
	    {
	        var tr = list_rows[i];
		tr.setAttribute ( 'class', 'edit-row' );
	    }

	for ( var i = 1; i < list_rows.length;
			 ++ i )
	{
	    var tr = list_rows[i];
	    tr.setAttribute ( 'draggable', 'true' );
	}
	for ( var i = 0; i < stack_rows.length;
			 ++ i )
	{
	    var tr = stack_rows[i];
	    tr.setAttribute ( 'class', 'edit-row' );
	    if ( i != 0 )
		tr.setAttribute
		    ( 'draggable', 'true' );
	}

	var drag_start = null;
	document.addEventListener ( "drag",
	    function(event) {}, false );
	document.addEventListener ( "dragstart",
	    function(event) {
		drag_start = event.target;
	    }, false );
	document.addEventListener ( "dragover",
	    function(event) {
	        event.preventDefault();
	    }, false );
	document.addEventListener ( "drop",
	    function(event) {
	        event.preventDefault();
		var t = event.target;
		while ( t != undefined
		        &&
			t.className != 'edit-row' )
		    t = t.parentElement;

		if ( t != undefined )
		{
		    let drag_table =
		        drag_start.parentElement
				  .parentElement;
		    let tr =
		        drag_start.cloneNode ( true );
		    let des_table =
		        t.parentElement.parentElement;
		    let des_rows = des_table.rows;
		    let des_body = des_table.tBodies[0];
		    let des_index = t.rowIndex + 1;

		    if ( drag_table == stack_table
		         ||
			 ! is_read_only )
		        drag_table.deleteRow
			    ( drag_start.rowIndex );
		    if ( des_index < des_rows.length )
		        des_body.insertBefore
			    ( tr, des_rows[des_index] );
		    else
		        des_body.appendChild ( tr );
		    tr.children[0].innerHTML =
		        delete_button;
		    tr.children[0].style.display =
		        'inline';
		    tr.children[1].style
		                  .textDecoration =
		        'none';
		    tr.setAttribute
		        ( 'class', 'edit-row' );
		}
	    }, false );


	function DELETE ( button )
	{
	    let tr = button.parentElement.parentElement;
	    let text = tr.children[1];
	    if (    text.style.textDecoration
		 == 'line-through' )
		text.style.textDecoration = 'none';
	    else
		text.style.textDecoration =
		    'line-through';
	}

	function DUP ( td )
	{
	    let tr = td.parentElement;
	    let tbody = tr.parentElement;
	    let table = tbody.parentElement;
	    if ( table == list_table
	         &&
		 is_read_only )
	        return;

	    let new_tr = tr.cloneNode ( true );
	    tbody.insertBefore ( new_tr, tr );
	    new_tr.children[1].style.textDecoration =
	        'none';
	}

	function CLEAR_EDIT ()
	{
	    var list = [];
	    var index = [];
	    for ( var i = 1; i < list_rows.length; )
	    {
		var tr = list_rows[i];
		var text = tr.children[1];
		var tbody = tr.parentElement;
		if (    text.style.textDecoration
		     == 'line-through' )
		     list.push
		         ( tbody.removeChild ( tr ) );
		else
		{
		    let k = text.dataset.index;
		    index[k] = true;
		    ++ i;
		}
	    }
	    for ( var i = 1; i < stack_rows.length; )
	    {
		var tr = stack_rows[i];
		var text = tr.children[1];
		var tbody = tr.parentElement;
		if (    text.style.textDecoration
		     == 'line-through' )
		     list.push
		         ( tbody.removeChild ( tr ) );
		else
		{
		    let k = text.dataset.index;
		    index[k] = true;
		    ++ i;
		}
	    }
	    let stack_body = stack_table.tBodies[0];
	    for ( var i = 0; i < list.length; ++ i )
	    {
		let k = list[i].children[1]
		               .dataset.index;
		if ( index[k] != true )
		{
		    stack_body.appendChild ( list[i] );
		    index[k] = true;
		}
	    }
	}

	// Send `submit' post with stack and list ids.
	//
	function UPDATE_EDIT ( kind )
	{
	    let submit_form = document.getElementById
		( 'submit-form' );
	    let update = document.getElementById
		( 'update' );
	    let stack_args = document.getElementById
		( 'stack-args' );
	    let list_args = document.getElementById
		( 'list-args' );
	    let new_list = document.getElementById
		( 'new-list' );
	    let change_list = document.getElementById
		( 'change-list' );

	    update.value = kind;
	    new_list.value = change_list.value;

	    var stack = [];
	    var indices = [];
	    for ( var i = 1; i < stack_rows.length;
	                     ++ i )
	    {
	        let tr = stack_rows[i];
		let text = tr.children[1];
		if (    text.style.textDecoration
		     == 'line-through' )
		    continue;
		let index = text.dataset.index;
		if ( indices[index] == true )
		    continue;
		stack.push ( index );
		indices[index] = true;
	    }
	    stack_args.value = stack.join(':');

	    if ( ! is_read_only )
	    {
		var list = [];
		indices = [];
		for ( var i = 1; i < list_rows.length;
				 ++ i )
		{
		    let tr = list_rows[i];
		    let text = tr.children[1];
		    if (    text.style.textDecoration
			 == 'line-through' )
			continue;
		    let index = text.dataset.index;
		    if ( indices[index] == true )
			continue;
		    list.push ( index );
		    indices[index] = true;
		}
		list_args.value = list.join(':');
	    }
	    submit_form.submit();
	}

	function CREATE_NEW_LIST ( event )
	{
	    if ( event.keyCode === 13 )
	    {
	        event.preventDefault();
		let created_basename =
		    document.getElementById
			( 'created-basename' );
		let basename =
		    document.getElementById
			( 'basename' );
		basename.value =
		    created_basename.value;
		UPDATE_EDIT ( 'create-list' );

	    }
	}

	</script>
EOT;
    }

?>

</body>
</html>
