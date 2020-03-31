<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Mar 31 16:08:38 EDT 2020

    // Maintains indices and projects.  Pushes and pulls
    // problems from projects and changes project owners.

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
    // An index is a NAME.index file in a directory:
    //
    //	    users/UID/+indices+
    //
    // Such a file belongs to the UID user and can be
    // edited by the user.  The file begins with a
    // description of the index followed by a blank line
    // followed by lines of the form `PROJECT PROBLEM'
    // that specify a problem in a project.  Thus an
    // index is a list of problems.  If a problem is not
    // in a project, but is in the user's users/UID
    // directory, PROJECT is `-'.  The description is
    // any sequence of non-blank lines not containing
    // '<' or '>', but which may contain HTML symbol
    // names of the form '&...;'.  The description will
    // be displayed in an HTML <p> paragraph.
    //
    // For each project the directory:
    //
    //	    projects/PROJECT/+index+
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
    // done by copying entries from one index to
    // another.
    //
    // The directory:
    //
    //	    projects/PROJECT/PROBLEM/+review+
    //
    // holds files named UID.review which are reviews of
    // the problem by the UID user.  There can be at
    // most one review for each PROJECT, PROBLEM, UID
    // triple.  A review file is just text that may NOT
    // contain '<' or '>', but may contain &xxx; HTML
    // symbols.  Each blank line introduces a new para-
    // graph.  Review files will be displayed by adding
    // <p> before each paragraph.
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
    // becomes the owner of the problem.  Only an owner
    // of a problem may make subsequent pushes after the
    // problem has been created.
    //
    // Each project problem has its own .git repository
    // using the directory
    //
    //	    projects/PROJECT/PROBLEM/.git
    //
    // Only uploaded files and .ftest files are included
    // in the repository.
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
    // where TIME is in %FT%T%z format, UID is the ID of
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
    // Lastly, there is for the UID user the file:
    //
    //	    users/UID/+indices+/recent
    //
    // that lists the indices which the user is recently
    // viewed.  Its contents are lines of the forms:
    //
    //	    TIME PROJECT BASENAME
    //
    // indicating that the index file with the name
    // BASENAME.index in PROJECT was viewed at the given
    // TIME.  TIME is in %FT%T%z format, PROJECT may be
    // '-' to indicate an index of the current user and
    // NOT of a project, and BASENAME may be '-' to
    // indicate the index is a list of all the problems
    // in the PROJECT or of all the current user's prob-
    // lems.  This file is sorted most recent entry
    // first, with at most one entry for each PROJECT-
    // BASENAME pair.

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

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

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
    // type of permission, in natural order.
    //
    function get_projects ( $type )
    {
	global $epm_data;
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
	    if ( $pmap[$type] )
	        $projects[] = $project;
	}
	natsort ( $projects );
    }

    // Return a map from a user's own problems to the
    // projects each is descended from, or '' if a
    // problem is not descended from a project.  Sort
    // the map by problems (keys) in natural order.
    //
    function get_problems ()
    {
	global $epm_data, $uid;

	$pmap = [];
	$f = "users/$uid";
	$ps = @scandir ( "$epm_data/$f" );
	if ( $ps == false )
	    ERROR ( "cannot read $f directory" );
	$re =
	    "/\/\.\.\/projects\/([^\/]+)\/$problem\$/";
	foreach ( $ps as $problem )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
	        continue;
	    $g = "$f/$problem/+parent+";
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

    // Return the lines from:
    //
    //	    users/UID/+indices+/recent
    //
    // in the form of a list whose elements are
    //
    // 	    [TIME PROJECT BASENAME]
    //
    // The map is sorted most recent TIMEs first.
    // PROJECT and/or BASENAME may be '-' as in
    // the file.
    //
    function read_recent ()
    {
        global $epm_data;
	$f = "users/$uid/+indices+/recent";
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false ) return [];
	$c = explode ( "\n", $c );
	$map = [];
	foreach ( $c as $line )
	{
	    $line = $trim ( $line );
	    $line = preg_replace
	        ( '/\h+/', ' ', $line );
	    $items = explode ( ' ', $line );
	    if ( count ( $items ) != 3 )
	        ERROR ( "badly formatted line" .
		        " '$line' in $f" );
	    $key = "{$items[1]}:{$items[2]}";
	    if ( isset ( $map[$key] ) )
	        ERROR ( "line '$line' duplicates" .
		        " $key in $f" );
	    $map[$key] = $items[0];
	}
	arsort ( $map, SORT_STRING );
	$list = [];
	foreach ( $map as $key => $time )
	{
	    list ( $project, $basename ) =
	        $explode ( ':', $key );
	    $list[] = [$time, $project, $basename];
	}
	return $list;
    }

    // Given the result of read_recent, return an HTML
    // option list.  In this the values are
    // 'PROJECT:BASENAME'.
    // 
    function option_list ( $recents )
    {
	$r = '';
        foreach ( $recents as $e )
	{
	    list ( $time, $project, $basename ) = $e;
	    $value = "$project:$basename";
	    if ( $project == '-' ) $project = 'Your';
	    if ( $basename == '-' )
	        $basename = 'Problems';
	    else
	        $basename = preg_replace
		    ( '-', ' ', $basename );
	    $r .= "<option value='$value'>"
	        . "$project $basename $time"
		. "</option>";
	}
	return $r;
    }

?>

<html>
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
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    pre.red {
        color: #BB0000;
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
    pre.problem {
        color: #CC00FF;
        font-size: 20px;
    }
    div.problem_display {
	background-color: #F2D9D9;
    }
    div.command_display {
	background-color: #C0FFC0;
    }
    div.work_display {
	background-color: #F2D9D9;
    }
    div.file-name {
	background-color: #B3E6FF;
    }
    div.file-contents {
	background-color: #C0FFC0;
    }
    td.time {
	color: #99003D;
	text-align: right;
    }

</style>

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
    echo <<<EOT
    <div class='manage'>
    <form method='POST'
          style='margin:0 0 1vh 0'>
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
    </td>
    <td>
    </td><td style='text-align:right'>
    $project_help</td>
    </tr>
    </table>
    <label>
    <input type='radio' name='op' value='push'>
    <h5>Push</h5>
    </label>
    <label>
    <input type='radio' name='op' value='pull'>
    <h5>Pull</h5>
    </label>
    <label>
    <input type='radio' name='op' value='edit'>
    <h5>Edit List</h5>
    </label>
    <label>
    <input type='radio' name='op' value='edit'>
    <h5>Select List Elements</h5>
    </label>
    <label>
    <input type='radio' name='op' value='edit'>
    <h5>Edit Favorites</h5>
    </label>
    <label>
    <input type='radio' name='op' value='edit'>
    <h5>Select Favorites</h5>
    </label>
    <br>
    </div>
EOT;

?>

</body>
</html>

