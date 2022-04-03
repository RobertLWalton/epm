<?php

    // File:	contest.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Apr  3 01:58:13 EDT 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Creates contests, displays and edits contest
    // registration data.

    // Session Data:
    //
    //	   $contest = & $_SESSION['EPM_CONTEST']
    //			         ['CONTESTNAME']
    //		Current contest name.
    //
    //	   $state (see index.php)
    //		normal
    //		warning
    //
    //	   Contests are projects.  Contest data is
    //	   stored in projects/PROJECT/+contest+ file.
    //     This file must list $aid as manager to allow
    //     $aid to edit file. 
    //
    // POSTs:
    //
    //	    new-contest=CONTESTNAME
    //		Create contest with name CONTESTNAME.
    //
    //	    contest=CONTESTNAME
    //		Set existing CONTESTNAME.
    //
    //	    account=EMAIL
    //		Add account with given email to the
    //		list of contest accounts.  Team email
    //		is that of team manager.
    //
    //	    flags=FLAG-LIST    warning={,no,yes}
    //		Replace account flag list with that
    //		given.  FLAG-LIST is comma separated
    //		list of items of form ACCOUNT:mjc.
    //
    //    	    m: 'M' if manager, else '-'
    //    	    j: 'J' if judge, else '-'
    //    	    c: 'C' if contestant, else '-'
    //
    //		Warning is given when manager ceases to
    //		be a manager.
    //		
    //	    type=TYPE
    //		Set contest type: 1-PHASE or 2-PHASE.
    //		
    //	    start=TIME
    //		Set contest start time.
    //
    //	    stop=HH:MM
    //		Set contest stop time.
    //
    //	    op=OPERATION
    //
    //		One of:
    //
    //	    	    cancel
    //			Reset account list from
    //			+contest+ file.
    //
    //		    deploy
    //			Create account projects for a
    //			2-PHASE contest.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_list.php";

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    $contestname =
        & $_SESSION['EPM_CONTEST']['CONTESTNAME'];
    $contestdata = & $data['CONTEST'];

    $options = & $contestdata['OPTIONS'];
        // [12][j]
	//	1: 1-phase contest
	//	2: 2-phase contest
	//	j: judges can see emails
	//         (managers can always see emails)
    $start = & $contestdata['START'];
        // start time in $epm_time_format
    $stop = & $contestdata['STOP'];
        // stop time in $epm_time_format
    $deployed = & $contestdata['DEPLOYED'];
        // time last deployed in $epm_time_format
    $flags = & $contestdata['FLAGS'];
        // map ACCOUNT => "[Mm-][Jj-][Cc-]"
	//    M if now manager, m if was manager,
	//      - if neither
	//    J if now judge, j if was judge,
	//      - if neither
	//    C if now contestant, c if was contestant,
	//      - if neither
    $times = & $contestdata['TIMES'];
        // map ACCOUNT => time of last change to account
	//		  flags
    $emails = & $contestdata['EMAILS'];
        // map ACCOUNT => "email address"
	// Email addresses used to add accounts
	// to contest.

    // Set $contestname to $name and if this is NULL,
    // set all $contestdata[...] element values to NULL,
    // but otherwise read projects/$name/+contest+ into
    // $contestdata.
    //
    function init_contest ( $name = NULL )
    {
        global $contestname, $contestdata;
	$contestname = $name;
        if ( isset ( $name ) )
	{
	    $f = "projects/$name/+contest+";
	    $c = @get_file_contents ( "$epm_data/$f" );
	    if ( $c === false )
	    {
	        $errors[] =
		    "contest $name no longer exists";
		$contestname = NULL;
		    // This clears $contestdata below.
	    }
	    else
	    {
		$j = json_decode ( $c, true );
		if ( $j === NULL )
		{
		    $m = json_last_error_msg();
		    ERROR
		        ( "cannot decode json in $f:" .
			  PHP_EOL . "    $m" );
		}
	    }
	}

        if ( ! isset ( $contestname ) )
	{
	    // You cannot simply set $contestdata to
	    // NULL because of references into it.

	    $contestdata['NAME'] = NULL;
	    $contestdata['OPTIONS'] = NULL;
	    $contestdata['START'] = NULL;
	    $contestdata['STOP'] = NULL;
	    $contestdata['DEPLOYED'] = NULL;
	    $contestdata['FLAGS'] = NULL;
	    $contestdata['TIMES'] = NULL;
	    $contestdata['EMAILS'] = NULL;
	}
    }

    if ( $epm_method == 'GET' )
        init_contest ( $contestname );

    $process_post = ( $epm_method == 'POST' );
        // True if POST that has not yet been processed.
    $updated = false;
        // True iff $contestdata has been updated.

    $notice = NULL;
        // If not NULL, output after errors and warnings
	// as <div class='notice'>$notice</div>.
    $action = NULL;
        // If not NULL, written as an action into each
	// member of $action_files.
    $action_files = [ "accounts/$aid/+actions+" ];

    if ( $process_post
	 &&
	 $state == 'normal'
	 &&
	 isset ( $_POST['rw'] ) )
    {
	$process_post = false;
	require "$epm_home/include/epm_rw.php";
    }

    if ( $process_post
	 &&
	 $state == 'normal'
	 &&
	 ! $rw )
    {
	$process_post = false;
	$errors[] = "you no longer have read-write" .
	            " privilege";
    }

    if ( $process_post
         &&
	 isset ( $_POST['new-contest'] )
	 &&
	 $state == 'normal' )
    {
        $process_post = false;

	$new_contest = $_POST['new-contest'];
	$d = "projects/$new_contest";
	$c = "projects/$new_contest/+contest+";
	root_priv_map ( $root_map );
	if ( ! preg_match
	           ( $epm_name_re, $new_contest ) )
	    $errors[] = "badly formatted new contest" .
	                " name: $new_contest";
	elseif ( file_exists ( "$epm_data/$c" ) )
	    $errors[] = "contest $new_contest already" .
	                " exists";
	elseif ( ! isset ( $root_map['create-contest'] )
	         ||
		 $root_map['create-contest'] == '-' )
	    $errors[] = "you do not have" .
	                " `create-contest' privileges";
	else
	{
	    if ( is_dir ( "$epm_data/$d" ) )
	    {
	        $warnings[] = "project $new_contest" .
		              " already exists";
	        $warnings[] = "making it into a contest";
	    }
	    $r = @mkdir ( "$epm_data/$d", 02771, true );
	    if ( $r === false )
		ERROR
		    ( "cannot make directory $d" );
	    $j = json_encode
		( ['NAME' => $new_contest],
		  JSON_PRETTY_PRINT );
	    $r = file_put_contents
		( "$epm_data/$c", $j );
	    if ( $r === false )
		ERROR ( "cannot write file $c" );
	    init_contest ( $new_contest );
	}
    }


    if ( $process_post )
	exit ( 'UNACCEPTABLE HTTP POST' );


    if ( isset ( $action ) )
    {
	foreach ( $action_files as $f )
	{
	    $r = @file_put_contents
		( "$epm_data/$f", $action,
		  FILE_APPEND );
	    if ( $r === false )
		ERROR ( "cannot write $f" );
	}
    }
?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>

div.select {
    background-color: var(--bg-green);
    padding-top: var(--pad);
}
div.project, div.problem, div.root {
    padding: var(--pad) 0px;
    margin: 0px;
    display: inline-block;
    float: left;
    width: 50%;
}
div.root {
    background-color: var(--bg-violet);
}
div.project {
    background-color: var(--bg-tan);
}
div.problem {
    background-color: var(--bg-blue);
}
div.priv {
    margin-left: 2%;
    border: black solid 1px;
    width: 95%;
}
div.priv pre {
    font-size: var(--large-font-size);
}

</style>

</head>

<div style='background-color:orange;
	    text-align:center'>
<strong>This Page is Under Construction.</strong>
</div>

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
    if ( isset ( $notice ) )
        echo "<div class='notice'>$notice</div>";

    if ( $state == 'warning' )
    {
        echo <<<EOT
	<div class='warnings'>
	<strong>WARNING: you will lose contest manager
	        privileges with this change;
		<br>
		Do you want to continue?</strong>
	<pre>   </pre>
	<button type='button'
		onclick='UPDATE("yes")'>
	     YES</button>
	<pre>   </pre>
	<button type='button'
		onclick='UPDATE("no")'>
	     NO</button>
	<br></div>
EOT;
    }

    $login_title =
        'Login Name; Click to See User Profile';
    echo <<<EOT
    <div class='manage'>
    <form method='GET' action='manage.php'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr>
    <td>
EOT;

    if ( $state == 'normal' )
        echo <<< EOT
	<button type='submit'
		formaction='user.php'
		title='$login_title'>
		$lname</button>
	</td>
	<td>
	<strong>Go To</strong>
	<button type='submit'
		formaction='project.php'>
		Project</button>
	<button type='submit'
		formaction='manage.php'>
		Manage</button>
	<strong>Page</strong>
EOT;
    else
        echo <<< EOT
        $lname
	</td>
	<td>
EOT;

    echo <<<EOT
    </td>
    <td style='text-align:right'>
    $RW_BUTTON
    <button type='button' id='refresh'
            onclick='location.replace
	        ("manage.php?id=$ID")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("manage-page")'>
	?</button>
    </td>
    </tr>
    </table></form></div>
EOT;

?>

<script>
function UPDATE ( warn )
{
    src = document.getElementById ( 'contents' );
    des = document.getElementById ( 'value' );
    form = document.getElementById ( 'post' );
    warning = document.getElementById ( 'warning' );
    des.value = src.innerText;
    warning.value = warn;
    form.submit();
}
function BLOCK ( action )
{
    src = document.getElementById ( 'block-contents' );
    des = document.getElementById ( 'block-file' );
    act = document.getElementById ( 'block-act' );
    form = document.getElementById ( 'block-post' );
    des.value = src.value;
    act.value = action;
    form.submit();
}
</script>

</body>
</html>
