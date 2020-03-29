<?php

    // File:	project.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Mar 29 02:57:08 EDT 2020

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
    //	    users/UID/+indices+/current
    //
    // that lists the indices which the user is current-
    // ly interested in.  Its contents are lines of
    // the forms:
    //
    //	    TIME PROJECT FILENAME
    //
    // indicating that the index file with the given
    // FILENAME and PROJECT was viewed at the given
    // TIME.  PROJECT may be '-' to indicate the index
    // of the user's current problems, and FILENAME
    // may be '-' to indicate the index of either the
    // user's current problems or the PROJECT's current
    // problems.  This file is sorted most recent entry
    // first, with at most one entry for each PROJECT-
    // FILENAME pair.

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
    $post_processed = false;
    		     // Set true when POST recognized.

    // Read permissions into $perm, where the permission
    // line
    //		PERM PROJECT PROBLEM
    //
    // becomes  'PERM' => [['PROJECT','PROBLEM']] and
    // several lines with the same 'PERM' concatenate
    // values.
    //
    // In addition compute:
    //
    //	    $perm_projects	List of user's PROJECT
    //			 	re's without duplicates.
    //
    $legal_permissions =
        ['owner','push','pull','index','review'];
    $perm = [];
    $perm_projects = [];
    $f = "admin/users/$uid/$uid.perm";
    $c = $file_get_contents ( "$epm_data/$f" );
    if ( $c !=== false )
    { 
	$c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	    // Get rid of `//...' comments.
	$c = explode ( "\n", $c );
	foreach ( $c as $line )
	{
	    $line = trim ( $line );
	    if ( $line == '' ) continue;
	    if ( preg_match ( '#/#', $line ) )
	    {
	        $m = "permission '$line' in $f has"
		   . " illegal '/'" );
		WARN ( $m );
		$errors[] = $m;
		continue;
	    }
	    $items = explode
	        ( preg_replace
		      ( '#\h+#', ' ', $line ) );
	    $items = explode ( ' ', $line );
	    $type = $items[0];
	    if ( ! in_array
	               ( $type, $legal_permissions) )
	    {
	        $m = "permission '$line' in $f has"
		   . " illegal permission type";
		WARN ( $m );
		$errors[] = $m;
		continue;
	    }
	    if ( count ( $items ) > 3 )
	    {
	        $m = "permission '$line' in $f has"
		   . " too many items or illegal"
		   . " horizontal space";
		WARN ( $m );
		$errors[] = $m;
		continue;
	    }
	    $p = array_slice ( $items, 1 );
	    $bad_re = false;
	    foreach ( $p as $re )
	    {
	        if (     @preg_match ( "/$re/", 'XXXX' )
		     === false )
		{
		    // false means error in $re and NOT
		    // that there is no match
		    //
		    $m = "permission '$line' in $f has"
		       . " bad regular expression"
		       . " '$re'";
		    WARN ( $m );
		    $errors[] = $m;
		    $bad_re = true;
		}
	    }
	    if ( $bad_re ) continue;

	    $perm[$type][] = $p;
	    if ( count ( $p ) >= 1 )
	        $perm_projects[] = $p;
	}
    }
    $perm_projects = array_unique ( $perm_projects );

    // Compute in $projects the list of projects the
    // $uid user is allowed to look at.  Sort naturally.
    //
    $projects = [];
    $ps = @scandir ( "$epm_data/projects" );
    if ( $ps == false )
        ERROR ( "cannot read 'projects' directory" );
    foreach ( $ps as $project )
    {
        foreach ( $perm_projects as $re )
	{
	    if ( preg_match ( "/$re/", $project ) )
	    {
	        $projects[] = $project;
		break;
	    }
	}
    }
    natsort ( $projects );



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
EOT;

    if ( count ( $problems ) > 0 )
    {
	echo "<tr><td></td><td>";
	echo "<label>" .
	     "<input type='submit'" .
	     " name='goto_problem'" .
	     " value='Go To Problem:'>";
        echo "<select name='selected_problem'" .
	     " title='problem to go to'>";
	foreach ( $problems as $value )
	    echo "    <option value='$value'>" .
	             "$value</option>";
        echo "</select></label></td>";
	if ( isset ( $problem ) )
	    echo <<<EOT
	         <td><h5>Go To:</h5>
		 <button type='submit'
			 formaction='run.php'
			 formmethod='GET'>
			 Run Page</button>
		 <pre>  </pre>
	         <button type='submit'
			formaction='option.php'
			formmethod='GET'>
			Option Page</button>
		 </td>
EOT;
	echo "</tr>";
    }
    echo <<<EOT
    </form>
    <form action='problem.php' method='POST'
          class='no-margin'>
    <tr><td colspan='2'><h5>or Create New Problem:<h5>
    <input type="text" size="32" name="new_problem"
           placeholder="New Problem Name" id="create">
    </form>
    </td></tr></table>
    </div>
EOT;

?>

</body>
</html>

