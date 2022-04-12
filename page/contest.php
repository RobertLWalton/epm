<?php

    // File:	contest.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Apr 12 16:51:16 EDT 2022

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
    //	    op=save OPTIONS
    //		Update +contest+ according to OPTIONS:
    //
    //		registration-email=EMAIL
    //		contest-type=[12]-phase
    //		judge-can-see=checked (or omitted)
    //		solution-start=TIME
    //		solution-stop=TIME
    //		description-start=TIME
    //		description-stop=TIME
    //		account-flags=FLAG-LIST

    //		FLAG-LIST is comma separated list of
    //		items of form ACCOUNT:FLAGS.
    //		
    //	    op=reset
    //		Restore data from +contest+.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_user.php";

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    $contestname =
        & $_SESSION['EPM_CONTEST']['CONTESTNAME'];
    $contestdata = & $data['CONTEST'];

    // Parameters stored in +contest+.
    // See HELP for more documentation.
    //
    $registration_email =
	    & $contestdata['REGISTRATION-EMAIL'];
        // Email or NULL.
    $contest_type = & $contestdata['CONTEST-TYPE'];
        // Either '1-phase' or '2-phase' or NULL.
    $judge_can_see = & $contestdata['JUDGE-CAN-SEE'];
        // 'checked' or NULL. 
    $solution_start = & $contestdata['SOLUTION-START'];
    $solution_stop = & $contestdata['SOLUTION-STOP'];
    $description_start =
	    & $contestdata['DESCRIPTION-START'];
    $description_stop =
	    & $contestdata['DESCRIPTION-STOP'];
        // Time in $epm_time_format or NULL.
    $deployed = & $contestdata['DEPLOYED'];
        // Time last deployed in $epm_time_format,
	// or NULL.
    $flags = & $contestdata['FLAGS'];
        // map ACCOUNT => "[Mm-][Jj-][Cc-]"
	//    M if now manager, m if was manager,
	//      - if neither
	//    J if now judge, j if was judge,
	//      - if neither
	//    C if now contestant, c if was contestant,
	//      - if neither
    $emails = & $contestdata['EMAILS'];
        // map ACCOUNT => "email address"
	// Email addresses used to add account
	// to contest.
    $times = & $contestdata['TIMES'];
        // map ACCOUNT => time of last change to account
	//		  flags or email

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
	    $contestdata['REGISTRATION-EMAIL'] = NULL;
	    $contestdata['CONTEST-TYPE'] = NULL;
	    $contestdata['JUDGE-CAN-SEE'] = NULL;
	    $contestdata['SOLUTION-START'] = NULL;
	    $contestdata['SOLUTION-STOP'] = NULL;
	    $contestdata['DESCRIPTION-START'] = NULL;
	    $contestdata['DESCRIPTION-STOP'] = NULL;
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

    // Look for parameters in $post and copy them to
    // elements of an array with element labels as
    // per $contestdata.  If a parameter that should
    // have a value does not, or has a value that
    // cannot be generated by a legal contest page,
    // call ERROR.  If no errors, return the array.
    //
    // More subtle parameter errors are detected by
    // check_parameters.
    //
    function get_parameters ( $post )
    {
	global $epm_time_format;

        $r = [];

	if ( ! isset ( $post['registration-email'] ) )
	    ERROR ( "UNACCEPTABLE HTTP POST:" .
	            " no registration-email" );
	$v = $post['registration-email'];
	if ( $v == '' ) $v = NULL;
	$r['REGISTRATION-EMAIL'] = $v;

	if ( ! isset ( $post['contest-type'] ) )
	    $v = NULL;  // Unset radio OK.
	else
	{
	    $v = $post['contest-type'];
	    if ( ! in_array ( $v, ['1-phase',
	                           '2-phase'] ) )
		ERROR ( "UNACCEPTABLE HTTP POST:" .
			" contest-type = $v" );
	}
	$r['CONTEST-TYPE'] = $v;

	if ( ! isset ( $post['judge-can-see'] ) )
	    $v = NULL;  // Unset checkbox.
	else
	{
	    $v = $post['judge-can-see'];
	    if ( $v != 'checked' )
		ERROR ( "UNACCEPTABLE HTTP POST:" .
			" judge-can-see = $v" );
	}
	$r['JUDGE-CAN-SEE'] = $v;

	foreach (
	    [['solution-start','SOLUTION-START'],
	     ['solution-stop','SOLUTION-STOP'],
	     ['description-start','DESCRIPTION-START'],
	     ['description-stop','DESCRIPTION-STOP']]
	    as $pair )
	{
	    $m = $pair[0];
	    $n = $pair[1];
	    if ( ! isset ( $post[$m] ) )
		ERROR ( "UNACCEPTABLE HTTP POST:" .
			" no $m" );
	    $v = $post[$m];
	    if ( $v == '' ) $v = NULL;
	    else
	    {
		$w = @strtotime ( $v );
		if ( $w === false )
		    ERROR ( "UNACCEPTABLE HTTP POST:" .
			    " $m = $v" );
		$v = date ( $epm_time_format, $w );
	    }
	    $r[$n] = $v;
	}

	return $r;
    }

    // Check the parameters returned by get_parameters
    // for errors and warnings.  Return 0 if none,
    // 1 if only warnings, 2 if there are errors.
    //
    // Some error checks reference $contestdata; e.g.,
    // DEPLOYED is used to prevent changes to CONTEST-
    // TYPE.
    //
    // $errors and $warnings must be set to a list
    // (they cannot be undefined) when this is called.
    //
    function check_parameters
	    ( $params, & $warnings, & $errors )
    {
        global $contestdata;

        $wc = count ( $warnings );
        $ec = count ( $errors );

    	$v = $params['REGISTRATION-EMAIL'];
	$err = [];
	if ( ! validate_email ( $v, $err ) )
	{
	    if ( count ( $err ) == 0 )
	        $warnings[] =
		    "Registration Email is missing";
	    else
	    {
	        $errors[] = "Bad Registration Email:";
		foreach ( $err as $e )
		    $errors[] = "    $e";
	    }
	}

	if ( isset ( $contestdata['DEPLOYED'] )
	     &&
	     isset ( $contestdata['CONTEST-TYPE'] )
	     &&
	     $contestdata['CONTEST-TYPE']
	     !=
	     $params['CONTEST-TYPE'] )
	{
	    $v = $contestdata['CONTEST-TYPE'];
	    $w = $params['CONTEST-TYPE'];
	    if ( $w == NULL ) $w = "NONE";
	    $errors[] = "cannot change contest type" .
	                " from $v to $w because";
	    $errors[] = "contest was deployed " .
	                $contestdata['DEPLOYED'];
	    $params['CONTEST-TYPE'] = $v;
	}

	if ( ! isset ( $params['SOLUTION-START'] ) )
	    $warnings[] =
	        "Solution Start Time is missing";
	elseif ( ! isset ( $params['SOLUTION-STOP'] ) )
	    $warnings[] =
	        "Solution Stop Time is missing";
	elseif ( strtotime ( $params['SOLUTION-STOP'] )
	         <=
	         strtotime ( $params['SOLUTION-START'] )
	       )
	    $errors[] = "Solution Stop Time is not" .
	                " later than Solution Start" .
			" Time";

	if ( ! isset ( $params['DESCRIPTION-START'] ) )
	    $warnings[] =
	        "Description Start Time is missing";
	elseif ( ! isset
	             ( $params['DESCRIPTION-STOP'] ) )
	    $warnings[] =
	        "Description Stop Time is missing";
	elseif ( strtotime
		     ( $params['DESCRIPTION-STOP'] )
	         <=
	         strtotime
		     ( $params['DESCRIPTION-START'] )
	       )
	    $errors[] = "Description Stop Time is not" .
	                " later than Description Start" .
			" Time";

	if ( count ( $errors ) > $ec ) return 2;
	elseif ( count ( $warnings ) > $wc ) return 1;
	else return 0;
    }

    // Given a set of parameters containing account
    // information, make display containing one row
    // per account of the form:
    //
    //		<tr>
    //		<td> 
    //		<pre class='flagbox'
    //		     data-curr=Fm
    //		     data-init=Fm
    //		     id='aid-M'
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     Dm</pre>
    //		<pre class='flagbox'
    //		     data-curr=Fj
    //		     data-init=Fj
    //		     id='aid-J'
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     Dj</pre>
    //		<pre class='flagbox'
    //		     data-curr=Fc
    //		     data-init=Fc
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     id='aid-C'>
    //		     Dc</pre>
    //		</td>
    //		<td><strong>aid</strong></td>
    //		<td><strong>email</strong></td>
    //		</tr>
    //		
    // where
    //		aid = account-id
    //		Fm is one of 'M', 'm', '-'
    //		Fj is one of 'J', 'j', '-'
    //		Fc is one of 'C', 'c', '-'
    //		data-curr is current value
    //		data-init is value when page loaded
    //		Dm is one of:    If current flag =:
    //		   &nbsp;M&nbsp;	'M'
    //		   &nbsp;M&nbsp;	'm'
    //			with overstrike text-decoration
    //		   &nbsp;&nbsp;&nbsp;	'-'
    //		Dj similar with M => J
    //		Dc similar with C => J
    //
    // Onclick performs the following transformation
    // for M (J and C are similar):
    //
    //	    if current == initial:
    //		if current == M: current = m
    //		if current == m: current = M
    //		if current == -: current = M
    //	    else:
    //		current = initial
    //
    function display_account ( $params )
    {
        $r = '';
	$emails = $params['EMAILS'];
	foreach ( $params['FLAGS'] as $aid => $flags )
	{
	    $r .= "<tr><td>";
	    $MJC = 'MJC';
	    $mjc = 'mjc';
	    for ( $i = 0; $i < 3; $i ++ )
	    {
	        $M = $MJC[$i];
	        $m = $mjc[$i];
		$f = $flags[$i];
		$class = 'flagbox';
		if ( $f == '-' )
		    $d2 = '&nbsp;';
		elseif ( $f == $m )
		{
		    $class .= ",overstrike";
		    $d2 = $M;
		}
		else
		    $d2 = $M;
		if ( $i % 2 == 0 )
		    $class .= ",even-column";
		else
		    $class .= ",odd-column";

		$r .= "<pre class='$class'"
		    . "     data-curr='$f'"
		    . "     data-init='$f'"
		    . "     id='$aid-$M'"
		    . "     onmouseenter='ENTER(this)'"
		    . "     onmouseleave='LEAVE(this)'"
		    . "     onclick='CLICK(this)'>"
		    . "&nbsp;$d2&nbsp;</pre>";
	    }
	    $email = $emails[$aid];
	    $r .= "&nbsp;&nbsp;&nbsp;&nbsp;"
	        . "<strong>$aid</strong>"
	        . "</td><td>"
	        . "<strong>$email</strong>"
		. "</td></tr>";
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
	    $t = date ( $epm_time_format );
	    $m = $_SESSION['EPM_EMAIL'];
	    $j = json_encode
		( ['NAME' => $new_contest,
		   'FLAGS' => [$aid => 'M--'],
		   'EMAILS' => [$aid => $m],
		   'TIMES' => [$aid => $t]],
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

    if ( $process_post
         &&
	 isset ( $_POST['op'] ) )
    {
        $process_post = false;
	$params = get_parameters ( $_POST );
	print_r ( $params );
	echo "<BR>";
	check_parameters
	    ( $params, $warnings, $errors );
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

div.accounts {
    background-color: var(--bg-tan);
    padding-top: var(--pad);
}

pre.overstrike {
    text-decoration: line-through;
}
pre.even-column {
    background-color: var(--bg-blue);
}
pre.odd-column {
    background-color: var(--bg-tan);
}
pre.flagbox {
    border: 2px solid black;
    background-color: var(--bg-orange);
}

</style>

</head>

<div style='background-color:orange;
	    text-align:center'>
<label>This Page is Under Construction.</label>
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
	if ( isset ( $root_map['create-contest'] )
	     &&
	     $root_map['create-contest'] == '+' )
	    $create_contest = 'inline';
	else
	    $create_contest = 'none';
    }

    $login_title =
        'Login Name; Click to See User Profile';
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%' id='not-edited'>

    <tr style='width:100%'>
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
    <td style='text-align:center'>
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

    <tr style='width:100%'>
    <td style='display:$show_name'>
    <label>Current Contest:</label>
    <pre class='contest'>$shown_name</pre>
    </td>

    <td>

    <div style='display:$select_contest'>
    <label>$select_msg:</label>
    <form method='POST' action='contest.php'
	  id='contest-form'>
    <input type='hidden' name='id' value='$ID'>
    <select name='select-contest'
	    onchange='document.getElementById
			("contest-form").submit()'>
    $contest_options
    </select></form>
    </div>
    <div style='display:$create_contest'>
    <label>$or Create New Contest:</label>
    <form method='POST' action='contest.php'
          id='new-contest-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type="text" size="32"
	   placeholder="New Contest Name"
	   title="New Contest Name"
	   name='new-contest'
	   onkeydown='KEYDOWN("new-contest-form")'>
    </div>

    </td>
    </form>
    </tr>
    </table>

    <table style='width:100%;display:none' id='edited'>
    <tr style='width:100%'>
    <td style='width:25%'>
    <input type='hidden' name='id' value='$ID'>
    <strong title='Login Name'>$lname</strong>
    </td>
    <td style='text-align:left'>
    <label>Current Contest:</label>
    <pre class='contest'>$shown_name</pre>
    </td>
    <td style='text-align:right'>
    <button type='button'
	    onclick='SUBMIT("save")'>
	    SAVE</button>
    <button type='button'
	    onclick='SUBMIT("reset")'>
	    RESET</button>
    </td>
    <td style='width:25%;text-align:right'>
    <button type='button'
            onclick='HELP("contest-page")'>
	?</button>
    </td>
    </tr>
    </table>
    </div>
EOT;

if ( isset ( $contestname ) )
{
    // Note: NULL automatically converts to the
    // empty string.

    $z = date ( "T" );
    $z = "<strong>$z</strong>";
    if ( isset ( $contest_type ) )
        $select_type =
	    "<script>document.getElementById" .
	    " ( '$type' ).select();</script>";
    else
        $select_type = '';
    $dtitle = 'mm/dd/yyyy, hh::mm:[AP]M';
    echo <<<EOT
    <div class='parameters'>
    <form method='POST' action='contest.php'
          id='parameters-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <label>To Register, Email:</label>
    <input type='email' name='registration-email'
           value='$registration_email' size='40'
	   onchange='ONCHANGE()'
	   onkeydown='KEYDOWN(null)'>

    <div style='margin-top:0.5em;margin-bottom:0.5em'>
    <label>Contest Type:</label>
    <input type='radio' id='1-phase'
           name='contest-type' value='1-phase'
	   onchange='ONCHANGE()'>
    <label for='1-phase'>One Phase</label>
    <input type='radio' id='2-phase'
           name='contest-type' value='2-phase'
	   onchange='ONCHANGE()'>
    <label for='2-phase'>Two Phase</label>
    $select_type
    <input style='margin-left:10em'
           type='checkbox' name='judge-can-see'
	                   value='checked'
                           id='judge-can-see'
			   $judge_can_see
			   onchange='ONCHANGE()'>
    <label for='judge-can-see'>
    Judges Can See Contestant Account Names/Emails
    </label>
    </div>

    <div>
    <label>Problem Solution Submit Times:</label>
    <label style='margin-left:1em'>Start:</label>
    <input type='datetime-local' name='solution-start'
                value='$solution_start'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    <label style='margin-left:1em'>Stop:</label>
    <input type='datetime-local' name='solution-stop'
                value='$solution_stop'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </div>

    <div>
    <label>Problem Definition Submit Times:</label>
    <label style='margin-left:1em'>Start:</label>
    <input type='datetime-local'
                name='description-start'
                value='$description_start'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    <label style='margin-left:1em'>Stop:</label>
    <input type='datetime-local'
                name='description-stop'
                value='$description_stop'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </div>

    </form>
    </div>

EOT;

$account_rows = display_account ( $contestdata );
echo <<<EOT
<div class='accounts'>
<table style='width:100%'>
$account_rows
</table>
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
	if ( form_id !== null )
	{
	    let form = document.getElementById
		( form_id );
	    form.submit();
	}
    }
}

var not_edited =
    document.getElementById ( 'not-edited' );
var edited =
    document.getElementById ( 'edited' );
function ONCHANGE ( )
{
    not_edited.style.display = 'none';
    edited.style.display = 'table';
}

var parameters_form =
    document.getElementById ( 'parameters-form' );
var op_input =
    document.getElementById ( 'op' );
function SUBMIT ( op )
{
    op_input.value = op;
    parameters_form.submit();
}
</script>

</body>
</html>
