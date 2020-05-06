<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed May  6 14:52:16 EDT 2020

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
    // Lastly there is a stack used in editing:
    //
    //	    users/UID/+indices+/+stack+
    //
    // +stack+ contains non-description lines copied
    // from or to be copied to indices.

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

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_template.php";
        // This last is only needed by merge function.

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
    $delete_list = NULL;
        // Set to list to be deleted.  Causes delete
	// OK question.
    $compile_next = false;
    	// Set to cause first element of EPM_PROJECT
	// CHECKED-PROBLEMS to be compiled.

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

    // Write uploaded description.  Takes global $_FILES
    // value as input, extracts description, and writes
    // into list file.  Errors append to $errors and
    // suppress write.  If list does not exist, a
    // warning message is added to $warnings.
    //
    // If successful, this function returns the listname
    // of the list given the description, in the form
    // '-:basename'.  If unsuccessful, false is
    // returned.
    //
    function upload_list_description
	    ( & $warnings, & $errors )
    {
        global $epm_data, $uid, $epm_name_re,
	       $epm_upload_maxsize;

        if ( ! isset ( $_FILES['uploaded_file'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$upload = & $_FILES['uploaded_file'];
	$fname = $upload['name'];
	$errors_size = count ( $errors );

	$ferror = $upload['error'];
	if ( $ferror != 0 )
	{
	    switch ( $ferror )
	    {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
		    $errors[] = "$fname too large";
		    break;
		case UPLOAD_ERR_NO_FILE:
		    $errors[] = "no file choosen;"
			      . " try again";
		    break;
		case UPLOAD_ERR_PARTIAL:
		    $errors[] = "$fname upload failed;"
			      . " try again";
		    break;
		default:
		    $e = "uploading $fname, PHP upload"
		       . " error code $ferror";
		    WARN ( $e );
		    $errors[] = "EPM SYSTEM ERROR: $e";
	    }
	    return false;
	}

	$fext = pathinfo ( $fname, PATHINFO_EXTENSION );
	$fbase = pathinfo ( $fname, PATHINFO_FILENAME );

	if ( $fext != 'dsc' )
	{
	    $errors[] = "$fname has wrong extension"
	              . " (should be .dsc)";
	    return;
	}
	if ( ! preg_match ( $epm_name_re, $fbase ) )
	{
	    $errors[] = "$fbase is not a legal"
	              . " EPM name";
	    return false;
	}

	$fsize = $upload['size'];
	if ( $fsize > $epm_upload_maxsize )
	{
	    $errors[] =
		"uploaded file $fname too large;" .
		" limit is $epm_upload_maxsize";
	    return false;
	}

	$ftmp_name = $upload['tmp_name'];
	$dsc = @file_get_contents ( $ftmp_name );
	if ( $dsc === false )
	{
	    $m = "cannot read uploaded file"
	       . " from temporary";
	    $errors[] = "$m; try again";
	    WARN ( "$m $ftmp_name" );
	    return false;
	}
	$f = "users/$uid/+indices+/$fbase.index";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $warnings[] = "creating list $fbase which"
	                . " does not previously exist";
	    make_new_list ( $fname, $errors );
	    if ( count ( $errors ) > $errors_size )
	        return false;
	}

	write_list_description ( $f, $dsc, $errors );
	if ( count ( $errors ) > $errors_size )
	    return false;
	else
	    return ( "-:$fbase" );
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

    // Execute EPM_PROJECT COMMANDS.  Errors cause abort
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
	$sname = listname_to_filename ( '+stack+' );

	$indices = $_POST['stack'];
	$indices = ( $indices == '' ? [] :
	             explode ( ':', $indices ) );
	    // explode ( ':', '' ) === ['']
	$slist = [];
	foreach ( $indices as $index )
	{
	    if ( $index < 0 || $index >= $bound )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $slist[] = $elements[$index];
	}

	if ( isset ( $fname ) )
	{
	    $indices = $_POST['list'];
	    $indices = ( $indices == '' ? [] :
			 explode ( ':', $indices ) );
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
	                            'edit', 'publish']
			    ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    if ( $op == 'publish' )
	    {
		$op = NULL;
		$data['OP'] = $op;
		// TBD
	    }
	    else
	    {
		$data['OP'] = $op;
		$data['LIST'] = $list;
	    }
	}
        elseif ( isset ( $_POST['create-list'] ) )
	{
	    if ( ! isset ( $_POST['basename'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $basename = $_POST['basename'];
	    make_new_list ( $basename, $errors );
	    if ( count ( $errors ) == 0 )
	    {
		$list = "-:$basename";
		$data['LIST'] = $list;
	    }
	}
        elseif ( isset ( $_POST['upload'] ) )
	{
	    $r = upload_list_description
		( $warnings, $errors );
	    if ( $r !== false )
	    {
	        $list = $r;
		$data['LIST'] = $list;

		$op = 'edit';
		$data['OP'] = $op;
	    }
	}
	elseif ( ! isset ( $op ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        elseif ( isset ( $_POST['cancel'] ) )
	{
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
        elseif ( isset ( $_POST['delete-list'] ) )
	{
	    $action = $_POST['delete-list'];
	    if ( $action == 'YES' )
	    {
		delete_list ( $list, $errors, true );
		$op = NULL;
		$data['OP'] = $op;
	    }
	    elseif ( $action == 'Delete List' )
	    {
		delete_list ( $list, $errors, false );
		if ( count ( $errors ) == 0 )
		    $delete_list = $list;
	    }
	    elseif ( $action != 'NO' )
		exit ( 'UNACCEPTABLE HTTP POST' );
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
    div.list-description-header {
	background-color: #FF8080;
	text-align: center;
	padding: 10px;
    }
    div.list-description {
	background-color: #FFCCCC;
    }
    div.list-description p, div.list-description pre {
        margin: 0px;
        padding: 10px 0px 0px 10px;
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
	// Must execute before $warnings is used.

    if ( isset ( $delete_list ) )
    {
	list ( $project, $basename ) =
	    explode ( ':', $delete_list );
	if ( $project == '-' )
	    $project = '<i>Your</i>';
	if ( $basename == '-' )
	    $basename = '<i>Problems</i>';
	else
	    $basename = preg_replace
		( '/-/', ' ', $basename );
	echo <<<EOT
	<div class='errors'>
	<strong>Do you really want to delete
	        $project $basename?</strong>
	<form action='project.php' method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit' name='delete-list'
	       value='YES'>
	<input type='submit' name='delete-list'
	       value='NO'>
	</form>
	<br></div>
EOT;
    }
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
    <form>
    <table style='width:100%'>
    <tr>
    <td>
    <label>
    <strong>User:</strong>
    <input type='submit' value='$email'
	   formaction='user.php'
	   formmethod='GET'
           title='Click to See User Profile'>
    </label>
    </td>
    <td>
    <div id='done-response' style='display:none'>
    <strong>Done!</strong>
    <button type='submit'
	    formaction='project.php'
	    formmethod='GET'>
	    Continue</button>
    </div>
    <div id='check-proposed-display'
         style='display:none'>
    <span class='problem-checkbox'
	  id='check-proposed'
	  onclick='CHECK(this)'>&nbsp;</span>
    <strong>Check Proposed Actions</strong>
    </div>
    <strong>Go To</strong>
    <button type='submit'
	    formaction='problem.php'
	    formmethod='GET'>
	    Problem</button>
    <strong>Page</strong>
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
	$options = favorites_to_options ( 'pull|push' );
	$push_title = 'Push Problems in Selected'
	            . ' List to Projects';
	$pull_title = 'Pull Problems in Selected'
	            . ' List from Projects';
	$edit_list_title = 'Edit Selected List';
	$select_title = 'Lists of'
	              . ' Problems to Push, Pull,'
		      . ' Edit, or Publish;'
		      . ' Favorites are First';
	$new_list_title = 'New List of Problems';
	$upload_title = 'Upload Selected List'
	              . ' Description (.des) File';
	$upload_file_title = 'Selected List Description'
	                   . ' (.des) File to be'
			   . ' Uploaded';
	$edit_favorites_title = 'Edit Favorites List of'
	                      . ' Lists to Select';
        echo <<<EOT
	<form method='POST'
	      enctype='multipart/form-data'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit' name='create-list'
	       value= ''
	       style='visibility:hidden'>
	       <!-- This is the first submit input
	            in the form and is therefore
		    triggered when a new list name
		    is entered at the end of the form.
		 -->
	<button type='submit' name='op' value='push'
	        title='$push_title'>
	Push
	</button>
	<button type='submit' name='op' value='pull'
	        title='$pull_title'>
	Pull
	</button>
	<button type='submit'
	        name='op' value='edit'
	        title='$edit_list_title'>
	Edit List
	</button>
	<button type='submit'
	        name='op' value='publish'
	        title='Publish Selected List'>
	Publish List
	</button>
	<strong>or</strong>
	<button type='submit'
	        formaction='favorites.php'
		formmethod='GET'
	        title='$edit_favorites_title'>
	Edit Favorites
	</button>
	<br>
	<label>
	<strong>List Select:</strong>
	<select name='selected-list'
	        title='$select_title'>
	$options
	</select>
	</label>
	<pre> </pre>
	<strong>or Create:</strong>
	<input type="text"
	       size="24" name="basename"
               placeholder="New List Name"
	       id="create-list"
	       title='$new_list_title'>
	       <!-- Pressing the enter key here
	            triggers the hidden input submit
		    above.
		-->
	<pre> </pre>
	<strong>or</strong>
	<label>
	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$epm_upload_maxsize">
	<button type='submit'
	        name='upload' value='yes'
	        title='$upload_title'>
	Upload Description
	</button>
	<strong>:</strong>
	<input type="file" name="uploaded_file"
	       title="$upload_file_title">
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
	$delete_list_ok = false;
	$description = '';

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
	else
	{
	    $delete_list_ok = true;
	    $description = read_list_description
		( listname_to_filename ( $list ) );
	}
	$name = "$project $basename";

	$stack_rows = list_to_edit_rows
	    ( $elements,
	      listname_to_list ( '+stack+' ) );

	$options = favorites_to_options ( 'pull|push' );

	$finish_title = 'Save Changes and Return to'
	              . ' Project Page';
	$cancel_title = 'DISCARD Changes and Return to'
	              . ' Project Page';
	$clear_title = 'Move Overstrikes to End of'
	             . ' Stack and Remove Duplicate'
		     . ' Overstrikes';
	$change_to_title = 'Save Changes and Change the'
	                 . ' List Being Edited';

	echo <<<EOT
	<div class='edit-list'>
	<div style='display:inline'>

	<form method='POST' action='project.php'
	      id='submit-form'>
	<input type='hidden' name='ID' value='$id'>
	<input type='hidden' name='update' id='update'>
	<input type='hidden' name='new-list'
	       id='new-list'>
	<input type='hidden' name='basename'
	       id='basename'>
	<input type='hidden' name='stack' value=''
	       id='stack-args'>
	<input type='hidden' name='list' value=''
	       id='list-args'>
	</form>

	<input type='button'
	       onclick='UPDATE_EDIT ( "update" )'
	       value='Save'
	       title='Save Changes'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "done" )'
	       value='Finish'
	       title='$finish_title'>
	<form method='POST' action='project.php'
	      style='display:inline'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit'
	       name='cancel'
	       value='Cancel'
	       title='$cancel_title'>
	</form>
	<input type='button'
	       onclick='CLEAR_EDIT()'
	       value='Clear Strikes'
	       title='$clear_title'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "change-list" )'
	       value='Change To'
	       title='$change_to_title'>
	<strong>:</strong>
	<select id='change-list'
	        title='New List to Edit'>
	$options
	</select>
	<b>Create New List:</b>
	<input type="text"
	       id='created-basename'
	       size="24"
               placeholder="New List Name"
               title="New List Name"
	       onkeydown='CREATE_NEW_LIST(event)'>
EOT;
	if ( $delete_list_ok )
	    echo <<<EOT
	    <form method='POST' action='project.php'
		  style='display:inline'>
	    <input type='hidden' name='ID' value='$id'>
	    <button type='submit'
		    name='delete-list'
		    value='Delete List'
		    title='Delete List Being Edited'>
	    Delete $name</button>
	    </form>
EOT;
	echo <<<EOT
	</div>
	<div style='display:inline;float:right'>
	$edit_help
	</div>
	</div>

	<div>
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
	</div>
EOT;
	if ( $description != '' )
	    echo <<<EOT
	    <div style='display:table;clear:both'>
	    </div>
	    <div class='list-description-header'>
	    <strong>$name Description</strong>
	    </div>
	    <div class='list-description'>
	    $description
	    </div>
EOT;

	echo <<<EOT
	<script>

	let is_read_only = $is_read_only;

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

	function make_draggable ( element )
	{
	    element.setAttribute
	        ( 'draggable', 'true' );

	    // Mozilla but not Chrome requires the
	    // following:
	    //
	    element.addEventListener ( "dragstart",
		function(event) {
		    event.dataTransfer.setData
		        ('text/plain',null);
		}, false );
	}

	for ( var i = 1; i < list_rows.length;
			 ++ i )
	    make_draggable ( list_rows[i] );

	for ( var i = 0; i < stack_rows.length;
			 ++ i )
	{
	    var tr = stack_rows[i];
	    tr.setAttribute ( 'class', 'edit-row' );
	    if ( i != 0 )
		make_draggable ( tr );
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
		    make_draggable ( tr );
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
	    make_draggable ( new_tr );
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
