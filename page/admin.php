<?php

    // File:	admin.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Jul  1 03:29:04 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays and edits permissions, deletes and
    // renames project problems and projects.


    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_admin.php";

    if ( $epm_method == 'GET' )
    {
	if ( ! isset ( $_SESSION['EPM_ADMIN'] ) )
	    $_SESSION['EPM_ADMIN'] =
	        [ 'LISTNAME' => NULL ];
    }

    $listname = & $_SESSION['EPM_ADMIN']['LISTNAME'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

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
            onclick='HELP("admin-page")'>
	?</button>
    </td>
    </tr>
EOT;

?>

</body>
</html>
