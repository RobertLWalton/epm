<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Apr 13 13:55:46 EDT 2020

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
    // format, and all descriptions are any sequence
    // of non-blank lines followed by a blank line or
    // end of file.  Descriptions may NOT contain '<' or
    // '>', but may may contain HTML symbol names of the
    // form '&...;'.  The description are meant to be
    // displayed in an HTML <p> paragraph.
    //
    // An index is a NAME.index file in a directory:
    //
    //	    users/UID/+indices+
    //
    // Such a file belongs to the UID user and can be
    // edited by the user.  The file begins with a
    // description of the index followed by a blank line
    // followed by lines of the form:
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
    // paragraphs separated by blank lines.  There are
    // two kinds of paragraphs.  Non-indented paragraphs
    // are just like descriptions and are displayed as
    // <p> paragraphs.  Indented paragraphs are display-
    // ed inside <pre></pre> so they are displayed
    // literally in monospace font.  In addition, before
    // display any tabs are replaced by exactly 8
    // spaces (so tabs should only be used in a sequence
    // of tabs at the very beginning of a line).
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
    //		['PROJECT', 'LIST']
    //		Names current list for operations that
    //		need it, or is NULL.
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
    //     EPM_PROJECT LIST-CONTENTS
    //     EPM_PROJECT STACK-CONTENTS 
    //		Each is a list of the lines in the LIST
    //		or STACK, exclusive of description and
    //		blank lines.  The lines are assigned
    //		natural number IDs as follows.  If
    //		there are S stack lines and L LIST
    //		lines, the stack lines have IDs 1, ...,
    //		S and the LIST lines IDs S+2, ...,
    //		S+1+L.  IDs S+1 and S+2+L are reserved
    //		for empty entries at the ends of lists.


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
    //		from <selection>... in the project page.
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
    //	    done='yes' list='...' stack='...'
    //		Update list and stack and return to
    //		project page.
    //
    //	    new-list='LIST' list='...' stack='...'
    //		Update list and stack and load a new
    //		edit page with the new LIST.
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
    //	   Operations that execute push/pull.
    //
    //		push=PROBLEM project=PROJECT
    //		    Push PROBLEM to PROJECT.
    //
    //		pull=PROBLEM project=PROJECT
    //		    Pull PROBLEM from PROJECT.
    //
    //		compile-pull=PROBLEM project=PROJECT
    //		    Compile pull of PROBLEM from
    //		    PROJECT.
    //
    //		compile-push=PROBLEM project=PROJECT
    //		    Compile push of PROBLEM to PROJECT.
    //
    //		execute=yes
    //		    Execute compiled push or pull.
    //
    //
    //	    Responses:
    //
    //		ERROR new-ID-value
    //		error-messages
    //
    //		COMPILED new-ID-value
    //		compiled-changes
    //
    //		DONE new-ID-value
    //
    //		Here error-messages is a string
    //		consisting of lines each with an EOL,
    //		and compiled-changes is similar.

    
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

    // require "$epm_home/include/debug_info.php";

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
    // that $enabling_map['PROBLEM'] is NOT set is
    // ignored.
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
	    if ( isset ( $enabling_map )
	         &&
		 ! isset ( $enabling_map[$problem] ) )
	        continue;

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
		$pmap[$problem] = $matches[1];
	    }
	    else
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
    //         <span class='CLASS'>
    //         PROBLEM &rArr; DESTINATION
    //         </span>
    //         </td>
    //         </tr>
    //
    // where CLASS is 'push-new' if PROJECT == '' and
    // 'push-parent' otherwise, and DESTINATION is
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
	    $class = ( $project == '' ?
	               'push-new' :
		       'push-parent' );
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
	    <span class='$class'>
	    $problem &rArr; $destination
	    </span>
	    </td>
	    </tr>
EOT;
	}
	return $r;
    }

    // Given the list of elements of the form
    //
    //	   [TIME PROJECT PROBLEM]
    //
    // where PROJECT is '-' if the PROBLEM is in
    // users/$uid, then return the rows of a table for
    // pulling problems as a string.  The string has
    // one segment for each element in which PROJECT
    // is NOT '-', in the same order as the elements
    // in the list.
    //
    // The segment for one list element is:
    //
    //     <tr data-project='PROJECT'
    //         data-problem='PROBLEM'>
    //         <td>
    //         <span class='problem-checkbox'
    //               onclick='PULL(this)'>&nbsp;</span>
    //         <span class='pull-problem'>
    //         PROBLEM &lArr; PROJECT
    //         </span>
    //         </td>
    //         </tr>
    //
    function list_to_pull_rows ( $list )
    {
	$r = '';
        foreach ( $list as $items )
	{
	    list ( $time, $project, $problem ) = $items;
	    if ( $project == '-' ) continue;
	    $r .= <<<EOT
	    <tr data-project='$project'
	        data-problem='$problem'>
	    <td>
	    <span class='problem-checkbox'
	        onclick='PULL(this)'>&nbsp;</span>
	    <span class='pull-problem'>
	    $problem &lArr; $project
	    </span>
	    </td>
	    </tr>
EOT;
	}
	return $r;
    }

    // Given a list name of the form 'PROJECT:BASENAME'
    // return the file name of the list relative to
    // $epm_data.
    //
    function listname_to_filename ( $listname )
    {
        global $uid;

	if ( $listname == '*FAVORITES*' )
	    ERROR ( "listname_to_filename was given" .
	            " '$listname' as a listname" );

        list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $project == '-' )
	    $d = "users/$uid/";
	else
	    $d = "projects/$project";
	return "$d/+indices+/$listname";
    }

    // Return the lines from list $listame in the
    // form of a list of elements each of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where PROJECT may be `-'.  The description at the
    // beginning of the file is skipped (the list proper
    // must follow a blank line).  If the file does
    // not exist, [] is returned.  File list line for-
    // matting errors are fatal.
    //
    // $listname has the form PROJECT:BASENAME as per
    // listname_to_filename.
    //
    function read_list ( $listname )
    {
        global $epm_data;
	$filename = listname_to_filename ( $listname );
	$list = [];
	$map = [];
	$c = @file_get_contents
	    ( "$epm_data/$filename" );
	if ( $c !== false )
	{
	    $c = explode ( "\n", $c );
	    $in_description = true;
	    foreach ( $c as $line )
	    {
		$line = $trim ( $line );
		if ( $line == '' )
		{
		    $in_description = false;
		    continue;
		}
		elseif ( $in_description )
		    continue;

		$line = preg_replace
		    ( '/\h+/', ' ', $line );
		$items = explode ( ' ', $line );
		if ( count ( $items ) != 3 )
		    ERROR ( "badly formatted line" .
			    " '$line' in $filename" );
		list ( $time, $project, $basename ) =
		    $items;
		$key = "$project:$basename";
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

    // Return the problems in $project in the form
    // of a list of elements each of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where TIME is the modification time of the
    // PROBLEM's +changes+ file.  List elements
    // are sorted most recent TIME first.
    //
    function project_to_list ( $project )
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
	rsort ( $map, SORT_NUMERIC );

	$list = [];
	foreach ( $map as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       $project, $problem];
	return $list;
    }

    // Given a list produced by read_list, make an
    // $enabling_map from it listing the user's problem
    // in the list (from lines with PROJECT `-'), and
    // then call read_problems and problems_to_push_rows
    // to return a list as per the latter function.
    //
    function list_to_push_rows ( $list )
    {
        $enabling_map = [];
	foreach ( $list as $items )
	{
	    list ( $time, $project, $problem ) = $items;
	    if ( $project != '-' ) continue;
	    $enabling_map[$problem] = true;
	}
	return problems_to_push_rows
	    ( read_problems ( $enabling_map ) );
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
		$line = $trim ( $line );
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
    // which case it is `<i>Your</i>', $basename is
    // BASENAME unless that is `-', in which case it is
    // `<i>Problems</i>', and $time is the first 10
    // characters of TIME (i.e., the day, excluding the
    // time of day).
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
		$project = '<i>Your</i>';
	    if ( $basename == '-' )
		$basename = '<i>Problems</i>';
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
    // $project.  It is an error if $project does not
    // exist.
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
	        "mvx -f $srcdir/$fname $desdir";
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

	$project = $data['PROJECT'];
	$problem = $data['PROBLEM'];
	$f = "projects/$project/$problem/"
	   . "+changes+";
	$changes = $data['CHANGES'];
	$r = @file_put_contents
	    ( "$epm_data/$f", $changes, FILE_APPEND );
	if ( $r === false )
	    ERROR ( "cannot write $f" );
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

	    if ( $list == '*FAVORITES*'
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
        elseif ( isset ( $_POST['done'] ) )
	{
	    if ( $op == 'edit' )
	        execute_edit ( $errors );
	    $op = NULL;
	    $data['OP'] = $op;
	}
        elseif ( isset ( $_POST['new-list'] ) )
	{
	    if ( $op != 'edit' )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    execute_edit ( $errors );
	    if ( count ( $errors ) == 0 )
	    {
	        $list = $_POST['new-list'];
		$data['LIST'] = $list;
	    }
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
	    echo "DONE $id\n";
	    exit;
	}
        elseif ( $op == 'push' )
	{
	    $problem = NULL;
	    $just_compile = false;
	    if ( isset ( $_POST['push'] ) )
		$problem = $_POST['push'];
	    elseif ( isset ( $_POST['compile-push'] ) )
	    {
		$problem = $_POST['compile-push'];
		$just_compile = true;
	    }
	    if ( ! isset ( $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! isset ( $_POST['project'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $project = $_POST['project'];
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( ! preg_match
	               ( $epm_name_re, $project ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    compile_push_problem
		( $project, $problem, $errors );
	    if ( count ( $errors ) > 0 )
	    {
		echo "ERROR $id\n";
		foreach ( $errors as $e )
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
	    echo "DONE $id\n";
	    exit;
	}
	elseif ( $op == 'pull' )
	{
	    // TBD
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
	    width: 1280px;
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
    div.op th {
        font-size: var(--large-font-size);
	text-align: left;
    }
    span.problem {
	display:inline;
        font-size: var(--large-font-size);
	font-family: "Courier New", Courier, monospace;
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
    div.push-list {
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
    span.push-new {
	background-color: #F2D9D9;
	display:inline-block;
        font-size: var(--large-font-size);
    }
    span.push-parent {
	background-color: #F2D9D9;
	display:inline-block;
        font-size: var(--large-font-size);
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
	$errors_help = HELP ( 'project-errors' );
	$proposed_help = HELP ( 'project-execute' );
	echo <<<EOT
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
	$options = "<option value='*FAVORITES*'>"
		 . "<i>Favorites</i></option>"
		 . favorites_to_options ( 'pull' );
        echo <<<EOT
	<form method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<button type='submit' name='op' value='push'>
	Push
	</button>
	<pre>   </pre>
	<button type='submit' name='op' value='pull'>
	Pull
	</button>
	<pre>   </pre>
	<button type='submit'
	       name='op' value='edit'>
	Edit List
	</button>
	<pre>   </pre>
	<label>
	<h5>Selected List</h5>
	<select name='selected-list'>
	$options
	</select>
	</label>
EOT;
    }
    echo <<<EOT
    </div>
EOT;
    if ( $op == 'push' )
    {
	$push_help = HELP ( 'project-push' );

	if ( $list == '-:-' )
	    $rows = problems_to_push_rows
		( read_problems() );
	else
	    $rows = list_to_push_rows
		( read_list ( $list ) );

	$project_options = projects_to_options
	    ( read_projects ( 'push' ) );

	echo <<<EOT
	<div class='push-list'>
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
	        <h5>Problems (selected are being push)
		    </h5></th>
	    <td><span class='problem-checkbox'
	              id='check-proposed'
	              onclick='CHECK(this)'>
		      &nbsp;</span>
		Check Proposed Actions</td>
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
	// is clicked for a problem that has no project
	// (i.e., it has not previously been pulled).
	// This function keeps a counter of the number
	// of these boxes that are checked and makes
	// project-selector visible if the counter is
	// not zero.
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
		   'compile-push' : 'push' );
	    if ( project == '' )
	        project = selected_project;
	    SEND ( op + '=' + problem
		      + '&project=' + project,
		   check_compile ?
		       COMPILE_RESPONSE :
		       PUSH_RESPONSE );
	}

	</script>
EOT;

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
	var check_proposed =
	    document.getElementById('check-proposed');
	check_proposed.style.backgroundColor = on;

	var compile_response =
	    document.getElementById
	        ('compile-response');
	var compile_messages =
	    document.getElementById
	        ('compile-messages');
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
		     ! ['DONE','COMPILED','ERROR']
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

    }

?>

</body>
</html>
