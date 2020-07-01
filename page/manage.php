<?php

    // File:	manage.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jul  1 12:29:51 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits privileges, deletes and
    // renames project problems and projects.

    // Permissions are granted by +priv+ files in
    // project and project problems.  If a user has a
    // privilege for a project, the user also has the
    // privilege for all problems in the project.
    //
    // The privileges are:
    //
    //    Project and Project Problem Permissions:
    //
    //	    owner	Right to change +priv+ file
    //			of project or problem.
    //
    //	    view	Right to view actions attached
    //			to the project or problem.
    //
    //    Project Permissions:
    //
    //
    //	    push-new	Right to push new problems into
    //			project.
    //
    //    Project Problem Permissions:
    //
    //
    //	    pull	Right to pull problem.
    //
    //	    re-push	Right to re-push problems.
    //
    // Note that an owner does not have other permis-
    // sions, but must change the +priv+ files to
    // grant needed privileges to her/himself.
    //
    // A +priv+ file consists of entries of the form:
    //
    //	    S PRIV RE
    //
    // where PRIV is one of the privilege names, S is
    // + to grant the privilege or - to deny it, and
    // RE is a regular expression matched against the
    // user's UID.  A +priv+ file line whose RE matches
    // the current UID is said to be matching.  The
    // +priv+ files are read one line at a time, and
    // the first matching line for a particular permis-
    // sion determines the result.  If there are no
    // matching lines, privilege is denied.
    // 
    // Problem privileges are determined by reading
    // the problem +priv+ file followed by the project
    // +priv+ file, and using the first matching line,
    // if any.  Thus the owner of a problem has more
    // control over the problem than the owner of the
    // project in which the problem lies.
    //
    // When a new problem is pushed, the problem is
    // given a +priv+ file giving the pusher all of
    // the above privileges.
    //
    // Lines in +priv+ files beginning with '#" are
    // treated as comment lines and are ignored, as
    // are blank lines.  REs cannot contain whitespace.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_manage.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_ADMIN'] ) )
	    $_SESSION['EPM_ADMIN'] =
	        [ 'LISTNAME' => NULL ];
    }

    $listname = & $_SESSION['EPM_ADMIN']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    if ( $epm_method == 'POST' )
    {
        if ( isset ( $_POST['listname'] ) )
	{
	    $new_listname = $_POST['listname'];
	    if ( in_list ( $new_listname, $favorites )
	         === NULL )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $listname = $new_listname;
	}
	else
	    exit ( 'UNACCEPTABLE HTTP POST' );
    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>

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

    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong>User:</strong>
    <button type='submit'
	    formaction='user.php'
	    title='Click to See User Profile'>
	    $email</button>
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
    <button type='button'
            onclick='HELP("manage-page")'>
	?</button>
    </td>
    </tr>
EOT;

?>

</body>
</html>
