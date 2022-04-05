<?php

    // File:	contest.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Apr  4 22:46:01 EDT 2022

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

    require "$epm_home/include/debug_info.php";

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
        global $contestname, $contestdata, $epm_data;
	$contestname = $name;
        if ( isset ( $name ) )
	{
	    $f = "projects/$name/+contest+";
	    $c = @file_get_contents ( "$epm_data/$f" );
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
		$contestdata = $j;
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

    // Return a map PROJECT => MTIME of all projects
    // which have a +contest+ file, where MTIME is the
    // mtime of the +contest+ file.  The list may be
    // sorted alphabetically by
    //		ksort ( list, SORT_STRING )
    // or most recent first by
    //		arsort ( list, SORT_NUMERIC )
    //
    $contest_re = '|\/projects/+([^/]+)/\+contest\+$|';
    function find_contests ()
    {
        global $epm_data, $contest_re;
	$r = [];
	$p = "$epm_data/projects/*/+contest+";
	foreach ( glob ( $p ) as $f )
	{
	    if ( preg_match ( $contest_re,
	                      $f, $matches ) )
	    {
	        $project = $matches[1];
		$time = @filemtime ( $f );
		if ( $time === false )
		{
		    WARN ( "cannot stat existing" .
		           " project/$project/" .
			   "+contest+" );
		    continue;
		}
		$r[$project] = $time;
	    }
	}
	return $r;
    }

    if ( $epm_method == 'GET' )
        init_contest ( $contestname );

    $process_post = ( $epm_method == 'POST' );
        // True if POST that has not yet been processed.
    $updated = false;
        // True iff $contestdata has been updated.
    root_priv_map ( $root_map );

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
	        $warnings[] = "making it into a" .
		              " contest";
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

    if ( $process_post
         &&
	 isset ( $_POST['select-contest'] )
	 &&
	 $state == 'normal' )
    {
        $process_post = false;

	$selected_contest = $_POST['select-contest'];
	if ( ! preg_match
	           ( $epm_name_re, $selected_contest ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " select-contest" .
		   " $selected_contest" );

	init_contest ( $selected_contest );
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

div.parameters {
    background-color: var(--bg-green);
    padding-top: var(--pad);
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

    if ( isset ( $contestname ) )
    {
	$show_name = 'inline';
	$shown_name = $contestname;
	$select_msg = 'or Select Another Contest';
    }
    else
    {
	$show_name = 'none';
	$shown_name = '';
	$select_msg = 'Select Contest';
    }

    if ( $state == 'normal' )
    {
        $contests = find_contests();
	$contest_options = '';
	if ( count ( $contests ) == 0 )
	{
	    $select_contest = 'none';
	    $or = '';
	}
	else
	{
	    $select_contest = 'inline';
	    $or = 'or';
	    arsort ( $contests, SORT_NUMERIC );
	    $contest_options .= 
		'<option value="">No Contest Selected' .
		'</option>';
	    foreach ( $contests as $project => $time )
		$contest_options .=
		    "<option value=$project>" .
		    "$project</option>";
	}
    }

    $login_title =
        'Login Name; Click to See User Profile';
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>

    <tr id='not-edited-1' style='width:100%'>
    <form method='GET' action='contest.php'>
    <input type='hidden' name='id' value='$ID'>
    <td>
    <button type='submit'
    	    formaction='user.php'
	    title='$login_title'>
	    $lname</button>
    </td>
    <td>
    <strong>Go To</strong>
    <button type='submit' formaction='project.php'>
    Project
    </button>
    <button type='submit' formaction='manage.php'>
    Manage
    </button>
    <strong>Page</strong>
    </td>
    </td>
    <td>
    </td><td style='text-align:right'>
    $RW_BUTTON
    <button type='button' id='refresh'
            onclick='location.replace
	        ("contest.php?id=$ID")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("contest-page")'>
	?</button>
    </td>
    </form>
    </tr>

    <tr id='not-edited-2' style='width:100%'>
    <td style='display:$show_name'>
    <strong>Current Contest:</strong>
    <pre class='contest'>$shown_name</pre>
    </td>
    <td>
    <div style='display:$select_contest'>
    <strong>$select_msg:</strong>
    <form method='POST' action='contest.php'
	  id='contest-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='select-contest'
	    onchange='document.getElementById
			("contest-form").submit()'>
    $contest_options
    </select></form>
    </div>
    <strong>$or Create New Contest:</strong>
    <form method='POST' action='contest.php'
          id='new-contest-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type="text" size="32"
	   placeholder="New Contest Name"
	   title="New Contest Name"
	   name='new-contest'
	   onkeydown='KEYDOWN("new-contest-form")'>
    </td>
    </form>
    </tr>

    <tr id='edited' style='width:100%;display:none'>
    <td>
    <input type='hidden' name='id' value='$ID'>
    <strong title='Login Name'>$lname</strong>
    </td>
    <td>
    <button type='button'
	    onclick='SUBMIT("save")'>
	    SAVE</button>
    <button type='button'
	    onclick='SUBMIT("reset")'>
	    RESET</button>
    <form method='POST' action='contest.php'
	  id='submit-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <input type='hidden' name='indices'
	   id='indices'>
    </form>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("contest-page")'>
	?</button>
    </td>
    </tr>
    </table></div>
EOT;

if ( isset ( $contestname ) )
{
    echo <<<EOT
    <div class='parameters'>
    <strong>Contest Start:</strong>
    <input type='datetime-local' name='start'
                value='2022-02-07T13:00'>
    </div>

EOT;
}

?>

<script>
function KEYDOWN ( form_id )
{
    if ( event.code === 'Enter' )
    {
	event.preventDefault();
	let form = document.getElementById
	    ( form_id );
	form.submit();
    }
}
</script>

</body>
</html>
