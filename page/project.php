<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Apr  7 05:59:14 EDT 2020

    // Maintains indices and projects.  Pushes and pulls
    // problems from projects and changes project owners.

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
    // In addition there are read-only indices containing
    // the problems in the directories:
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
    //	    users/UID/+indices+/favorites
    //
    // that lists the user's favorite indices.  Its contents
    // are lines of the forms:
    //
    //	    TIME PROJECT BASENAME
    //
    // indicating that the index file with the name
    // BASENAME.index in PROJECT was viewed at the given
    // TIME.  PROJECT may be '-' to indicate an index of
    // the current user and NOT of a project, and BASENAME
    // may be '-' to indicate the index is a list of all
    // the problems in the PROJECT or of all the UID
    // user's problems.  The user may edit this in the
    // same manner as the user edits lists.
    //
    // Lastly there are two stacks used in editing:
    //
    //	    users/UID/+indices+/fstack
    //	    users/UID/+indices+/istack
    //
    // Fstack is the favorites stack and contains lines
    // copied from or to be copied to the favorites
    // file.  Istack is the index stack and contains
    // non-description lines copied from or to be copied
    // to indices.

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
    //		    'edit-list'
    //
    //	   EPM_PROJECT LIST
    //		['PROJECT', 'LIST']
    //		Names current list for operations that
    //		need it, or is NULL.
    //
    // During a push or pull:
    //
    //     EPM_PROJECT SELECTED-PROJECT
    //		Selected project for newly pushed
    //		problems.  Ignored for pushed problems
    //		with parents and for pulls.
    //
    //     EPM_PROJECT CHECKED-PROBLEMS
    //		List of checked problems that have not
    //		yet been processed.  The first of these
    //		is the one currently being processed.
    //		This is NULL if $op is not push or pull
    //		or list of checked problems has not yet
    //		been received.
    //
    //     EPM_PROJECT COMMANDS
    //		List of commands to be executed by
    //		execute_commands to accomplish the
    //		current push/pull operation.
    //
    //     EPM_PROJECT CHANGES
    //		String to be appended to +changes+ file
    //		after commands are executed.  Also
    //		displayed to user when change-approval
    //		is required.
    //
    //     EPM_PROJECT CHANGE-FILE
    //		Name of file to which CHANGES are to be
    //		appended upon success, relative to $epm_
    //		data.
    //
    //     EPM_PROJECT APPROVAL
    //		True if change-approval is required, and
    //		false otherwise.
    //
    //     EPM_PROJECT PROGRESS
    //		List of progress messages to be printed
    //		like error messages after errors and
    //		warnings.


    // XHTTP Operations
    // ----- ----------
    //
    //     These are operations on the current list or
    //     favorites and the current stack.  They are
    //     performed in the page by javascript and
    //     performed by the server when xhttp posts
    //     giving these operations are received.  The
    //     post data is a sequence of lines each con-
    //     taining one of these operations.
    //
    //	   In the following # is a row number, or row
    //	   index, where 0 is the first (topmost) row.
    //
    //		LSM #	Move row # from list to top of
    //			stack.
    //		LSC #	Copy row # from list to top of
    //			stack.
    //		SLM #	Move top of stack to list row #.
    //		SLC #	Copy top of stack to list row #.
    //		MU #	Move list row # to top of list.
    //		MD #	Move list row # to bottom of
    //			list.
    //
    //	    The data POSTed by an xhttp request consist
    //	    of the following name/value pairs:
    //
    //		Name	Value
    //
    //		id	EPM_PROJECT ID value.
    //		count	Number of first operation.
    //		ops	Operations, one per line, in a
    //			string.
    //
    //	    The operations are numbered 0, 1, ... from
    //	    the time of the last page reload.  The xhttp
    //	    response is `done #' where # is the number of
    //	    the last operation received and processed.
    //	    The xhttp code can buffer operations not yet
    //	    processed and will wait for the last opera-
    //	    tion to be processed before the page is un-
    //	    loaded or hidden.

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
	    'LIST' => NULL ,
	    'CHECKED-PROBLEMS' => NULL];

    $data = & $_SESSION['EPM_PROJECT'];
    $id = $data['ID'];
    $op = $data['OP'];
    $list = $data['LIST'];
    $checked_problems = $data['CHECKED-PROBLEMS'];

    if ( $method == 'POST'
         &&
	 ( ! isset ( $_POST['ID'] )
	   ||
	   $_POST['ID'] != $id ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

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
	    if ( ! preg_match
	               ( '/^\s*(\S+)\s+(\S+)\s*$/',
		         $line, $matches ) )
	        $m = "badly formatted permission"
		   . " '$line' in $f";
	    elseif ( preg_match ( '#/#', $line ) )
	        $m = "permission '$line' in $f has"
		   . " illegal '/'";
	    elseif ( ! isset ( $pmap[$matches[1]] ) )
	        $m = "bad permission type"
		   . " '{$matches[1]}' in $f";
	    else
	    {
	        $r = preg_match
		    ( "/({$matches[2]})/", $iid );
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
	    ( $pmap, "projects/$project/$problem/+perm+" );
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
	    ERROR ( "cannot read 'projects' directory" );
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
    // the parent is unknown and the segment form is
    //
    //	   <tr><td class='problem'>
    //	       <input type='checkbox'
    //		      name='check$c'
    //		      value='PROBLEM'
    //		      onclick='PUSH(this)'>
    //	       PROBLEM</td></tr>
    //
    // and if PROJECT is NOT '' the segment form is
    //
    //	   <tr><td class='problem'>
    //	       <input type='checkbox'
    //		      name='check$c'
    //		      value='PROBLEM'>
    //         PROBLEM &rAarr; PROJECT
    //         </td></tr>
    //
    // $c is a counter that counts the rows output.
    //
    function problems_to_push_rows ( $map )
    {
	$r = '';
	$c = -1;
        foreach ( $map as $problem => $project )
	{
	    $c += 1;
	    if ( $project == '' )
	        $r .= <<<EOT
		<tr><td class='problem'>
		    <input type='checkbox'
		           name='check$c'
			   value='$problem'
			   onclick='PUSH(this)'>
		    $problem</td></tr>
EOT;
	    else
	        $r .= <<<EOT
		<tr><td class='problem'>
		    <input type='checkbox'
		           name='check$c'
			   value='$problem'>
		    $problem &rArr; $project</td></tr>
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
    //	    users/UID/+indices+/favorites
    //
    // in the form of a list of elements of the form
    //
    //	    [TIME PROJECT BASENAME]
    //
    // If there is no file line `- - TIME', then such
    // an element is added to the beginning of the list
    // with the current time as TIME.
    //
    // A non-existant favorites file is treated as a
    // file of zero length.  File line formatting errors
    // are fatal.
    //
    function read_favorites ()
    {
        global $epm_data, $uid, $epm_time_format;
	$list = [];
	$map = [];
	$f = "users/$uid/+indices+/favorites";
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
		if ( isset ( $map[$key] ) )
		    ERROR ( "line '$line' duplicates" .
			    " line '{$map[$key]}' in" .
			    " $f" );
		$map[$key] = $line;
		$list[] = $items;
	    }
	}
	if ( ! isset ( $map['-:-'] ) )
	{
	    $time = strftime ( $epm_time_format );
	    array_unshift ( $list, [$time, '-', '-'] );
	}
	return $list;
    }

    // Given a list of elements each of the form:
    //
    //	    [TIME PROJECT BASENAME]
    //
    // return a string whose segments have the form
    //
    //	    <option value='PROJECT:BASENAME'>
    //      $project $basename $time
    //      </option>
    //
    // If PROJECT in the file is '-' then `$project' is
    // `<i>Your</i>'.  If BASENAME in the file is `-'
    // $basename is `<i>Problems</i>'.  Here $time is
    // the first 10 characters of TIME (i.e., the day,
    // excluding the time of day).
    //
    function favorites_to_options ( $list )
    {
	$r = '';
	foreach ( $list as $e )
	{
	    list ( $time, $project, $basename ) = $e;
	    $value = "$project:$basename";
	    if ( $project == '-' )
		$project = '<i>Your</i>';
	    if ( $basename == '-' )
		$basename = '<i>Problems</i>';
	    else
		$basename = preg_replace
		    ( '-', ' ', $basename );
	    $time = substr ( $time, 0, 10 );
	    $r .= "<option value='$value'>"
		. "$project $basename $time"
		. "</option>";
	}
	return $r;
    }

    // Compute EPM_PROJECT CHANGES, CHANGE-FILE, and
    // COMMANDS to be used to push $problem to $project.
    // If $problem has been pulled, $project is ignored
    // and the problem's parent is used instead.
    //
    function compile_push_problem ( $problem, $project )
    {
	global $epm_data, $uid, $epm_filename_re,
	       $epm_time_format, $data,
	       $push_file_map;

        $srcdir = "users/$uid/$problem";
	$g = "$srcdir/+parent+";
	$new_push = true;
	if ( is_link ( "$epm_data/$g" ) )
	{
	    $re = "/\/\.\.\/projects\/([^\/]+)\/"
		. "$problem\$/";
	    $s = @readlink ( "$epm_data/$g" );
	    if ( $s === false )
		ERROR ( "cannot read link $g" );
	    if ( ! preg_match
		       ( $re, $s, $matches ) )
		ERROR ( "link $g value $s is" .
			" mal-formed" );
	    $project = $matches[1];
	    $desdir = "projects/$project/$problem";
	    if ( ! is_dir ( "$epm_data/$desdir" ) )
		ERROR ( "$desdir is not a directory" );
	    $new_push = false;
	}
	else
	    $desdir = "projects/$project/$problem";

	$changes = "Changes to Push $problem ("
	         . strftime ( $epm_time_format )
	         . "):" . PHL_EOL;
	$commands = [];
	    // Commands are to be executed with
	    // $epm_data being the current directory
	    // and 07 the current mask.
	if ( $new_push )
	{
	    $changes .= "make directory for $problem in"
	              . " project $project" . PHP_EOL;
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

	    $changes .= "move $fname to project $problem"
	              . PHP_EOL;
	    $commands[] = "mv -f $srcdir/$fname $desdir";
	        // This will also move a link.

	    if ( $action == 'R' ) continue;
	    if ( $action != 'L' )
	        ERROR ( "bad value for" .
		        " \$push_file_map['$ext']" );

	    $changes .= "link $fname from project"
	              . " $problem to local $problem"
	              . PHP_EOL;
	    $commands[] = "ln -s ../../../$desdir/$fname"
	                . " $srcdir/$fname";
	}
	$data['CHANGES'] = $changes;
	$data['CHANGE-FILE'] = "$desdir/+changes+";
	$data['COMMANDS'] = $commands;
    }

    // Execute EPM_PROJECT COMMANDS.  Errors cause abort
    // and append to $errors.  A command is in error
    // if it produces any standard output or standard
    // error output.
    //
    function execute_commands ( $errors )
    {
        global $epm_data, $data;
	foreach ( $data['COMMANDS'] as $command )
	{
	    $output = [];
	    exec ( "umask 07; cd $epm_data;" .
	           " $command 2>&1", $output );
	    $err = '';
	    foreach ( $output as $line )
	    {
	        if ( preg_match ( '/^\s*$/', $line ) )
		    continue;
		if ( $err != '' ) $err .= PHP_EOL;
		$err .= $line;
	    }
	    if ( $err != '' )
	    {
	        $errors[] = $err;
		break;
	    }
	}
    }

    if ( $method == 'POST' )
    {
        if ( isset ( $_POST['cancel'] ) )
	{
	    $op = NULL;
	    $data['OP'] = $op;
	}
        elseif ( isset ( $_POST['op'] ) )
	{
	    if ( ! isset ( $_POST['selected-list'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $op = $_POST['op'];
	    $list = $_POST['selected-list'];

	    if ( $list == '*FAVORITES*'
		 &&
		 $op != 'edit-list' )
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
	elseif ( $op == 'edit-list' )
	{
	    // TBD
	}
	elseif ( ! isset 
	            ( $_POST['selected-project'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	elseif ( $op != 'push' && $op != 'pull' )
	    ERROR ( "\$op = '$op' is not push or" .
	            " pull" );
	elseif ( isset ( $_POST['submit'] ) )
	{
	    $data['SELECTED-PROJECT'] =
	        $_POST['selected-project'];
	    $data['CHECKED-PROBLEMS'] = [];
	    $data['COMMANDS'] = NULL;
	    $data['CHANGES'] = NULL;
	    $data['APPROVAL'] = true;
	    $data['PROGRESS'] = [];
	    $checked_problems =
	        & $data['CHECKED-PROBLEMS'];

	    foreach ( $_POST as $key => $value )
	    {
	        if ( preg_match
		         ( '/^check\d+$/', $key ) )
		    $checked_problems[] = $value;
	    }
	    $compile_next = true;
	}
	elseif ( isset ( $_POST['execute_yes'] ) )
	{
	    if ( count ( $errors ) > 0 )
	        ERROR ( "\$errors not empty at" .
		        " execute_yes" );
	    execute_commands ( $errors );
	    // TBD

	}
	elseif ( isset ( $_POST['execute_no'] ) )
	    $compile_next = true;
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );

	$id = bin2hex ( random_bytes ( 16 ) );
	$data['ID'] = $id;
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
    td.problem {
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
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
    }
    div.op {
	background-color: #F2D9D9;
    }

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>
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

    $project_help = HELP ( 'project-page' );
    $options = "<option value='*FAVORITES*'>"
             . "<i>Favorites</i></option>"
	     . favorites_to_options
	           ( read_favorites() );
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
    <td><h5>Go To:</h5>
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
        echo <<<EOT
	<form method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<label>
	<input type='submit' name='op' value='push'>
	<h5>Push</h5>
	</label>
	<pre>   </pre>
	<label>
	<input type='submit' name='op' value='pull'>
	<h5>Pull</h5>
	</label>
	<pre>   </pre>
	<label>
	<input type='submit' name='op' value='edit-list'>
	<h5>Edit List</h5>
	</label>
	<pre>   </pre>
	<label>
	<h5>Selected List</h5>
	<select name='selected-list'>
	$options
	</select>
	</label>
EOT;
    echo <<<EOT
    </div>
EOT;

    if ( $op == 'push' && $checked_problems == NULL )
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
	<div class='op'>
	<form method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<table width='100%'>
	<tr><th style='text-align:left'>
	        <h5>Problems (check to push)</h5></th>
	    <pre>    </pre>
	    <td><input type='submit'
	               name='cancel'
		       value='Cancel'></td>
	    <td id='project-selector'
	        style='visibility:hidden'>
	    <label>
	    <h5>Select Project</h5>:
	    <select name='selected-project'>
	    $project_options
	    </select></label>
	    </td>
	    <td>
	    <pre>    </pre>
            <td style='text-align:right'>
            $push_help</td>
	</tr>
	$rows
	</table>
	</form>
	</div>
	<script>

	// The following is called when a push checkbox
	// is clicked for a problem that has no project
	// (i.e., it has not previously been pulled).
	// This function keeps a counter of the number
	// of these boxes that are checked and makes
	// project-selector visible if the counter is
	// not zero.
	//
	var push_counter = 0;
	var project_selector = document.getElementById
	    ( 'project-selector' );
	function PUSH ( checkbox )
	{
	    if ( checkbox.checked )
	    {
		// Click has turned box on.
		//
		if ( ++ push_counter == 1 )
		    project_selector.style.visibility =
		        'visible';
	    }
	    else
	    {
		// Click has turned box off.
		//
		if ( -- push_counter == 0 )
		    project_selector.style.visibility =
		        'hidden';
	    }
	}
	</script>
EOT;
    }

?>

</body>
</html>
