<?php

    // File:	contest.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Apr 18 17:07:10 EDT 2022

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
    //	    add-email=EMAIL
    //		Set add_email variable and enable
    //	        new account to be set/selected.
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
    $write_contestdata = false;
        // Set to true to write contestdata after
	// processing POSTs.

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

    $is_manager = & $data['IS-MANAGER'];
	// True if $aid is contest manager and false
	// otherwise.
    $add_email = & $data['ADD-EMAIL'];
        // Email of account to be added, or not set if
	// none.
    $add_aids = & $data['ADD-AIDS'];
        // Accounts that may be selected for addition
	// given $add_email.
    $add_aid = & $data['ADD-AID'];
        // Account to be added, or not set if none
	// or only $add_email known.

    // Set $contestname to $name and if this is NULL,
    // set all $contestdata[...] element values to NULL,
    // but otherwise read projects/$name/+contest+ into
    // $contestdata.
    //
    function init_contest ( $name = NULL )
    {
        global $contestname, $contestdata, $epm_data,
	       $is_manager, $aid, $flags;

	// You cannot simply set $contestdata because
	// of the references into it, but must set
	// each of its elements individually.

	foreach (array_keys ( $contestdata ) as $k )
	    $contestdata[$k] = NULL;

	$is_manager = false;

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
		foreach ( $j as $k => $v )
		    $contestdata[$k] = $v;

		if ( isset ( $flags[$aid] )
		     &&
		     $flags[$aid][0] == 'M' )
		    $is_manager = true;
	    }
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
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " no registration-email" );
	$v = $post['registration-email'];
	$v = trim ( $v );
	if ( $v == '' ) $v = NULL;
	$r['REGISTRATION-EMAIL'] = $v;

	if ( ! isset ( $post['contest-type'] ) )
	    $v = NULL;  // Unset radio OK.
	else
	{
	    $v = $post['contest-type'];
	    if ( ! in_array ( $v, ['1-phase',
	                           '2-phase'] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " contest-type = $v" );
	}
	$r['CONTEST-TYPE'] = $v;

	if ( ! isset ( $post['judge-can-see'] ) )
	    $v = NULL;  // Unset checkbox OK.
	else
	{
	    $v = $post['judge-can-see'];
	    if ( $v != 'checked' )
		exit ( "UNACCEPTABLE HTTP POST:" .
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
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " no $m" );
	    $v = $post[$m];
	    $v = trim ( $v );
	    if ( $v == '' ) $v = NULL;
	    else
	    {
		$w = @strtotime ( $v );
		if ( $w === false )
		    exit ( "UNACCEPTABLE HTTP POST:" .
			   " $m = $v" );
		$v = date ( $epm_time_format, $w );
	    }
	    $r[$n] = $v;
	}

	return $r;
    }

    // Check the parameters returned by get_parameters
    // for warnings.
    //
    // Some error checks reference $contestdata; e.g.,
    // DEPLOYED is used to prevent changes to CONTEST-
    // TYPE.
    //
    // $warnings must be set to a list (it cannot be
    // undefined) when this is called.
    //
    function check_parameters ( $params, & $warnings )
    {
        global $contestdata;

    	$v = $params['REGISTRATION-EMAIL'];
	$err = [];
	if ( $v === NULL )
	    $warnings[] =
		"Registration Email is missing";
	elseif ( ! validate_email ( $v, $err ) )
	{
	        $warnings[] = "Bad Registration Email:";
		foreach ( $err as $e )
		    $warnings[] = "    $e";
		unset ( $params['REGISTRATION-EMAIL'] );
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
	    $warnings[] = "cannot change contest type" .
	                  " from $v to $w because";
	    $warnings[] = "contest was deployed " .
	                  $contestdata['DEPLOYED'];
	    unset ( $params['CONTEST-TYPE'] );
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
	    $warnings[] = "Solution Stop Time is not" .
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
	    $warnings[] =
	        "Description Stop Time is not" .
		" later than Description Start" .
		" Time";
    }

    // Given a set of parameters containing account
    // information, make display containing one row
    // per account of the form:
    //
    //		<tr>
    //		<td> 
    //		<pre class='flagbox evencolumn Om'
    //		     data-on='M'
    //		     data-off='m'
    //		     data-current=Fm
    //		     data-initial=Fm
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     Dm</pre>
    //		<pre class='flagbox oddcolumn Oj'
    //		     data-on='J'
    //		     data-off='j'
    //		     data-current=Fj
    //		     data-initial=Fj
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     Dj</pre>
    //		<pre class='flagbox evencolumn Oc'
    //		     data-on='C'
    //		     data-off='c'
    //		     data-current=Fc
    //		     data-initial=Fc
    //		     onmouseenter=ENTER(this)
    //		     onmouseleave=LEAVE(this)
    //		     onclick=CLICK(this)>
    //		     Dc</pre>
    //		</td>
    //		<td style='padding-left:1em'>
    //          <strong>aid</strong></td>
    //		<td style='padding-left:3em'>
    //          <strong>email</strong></td>
    //		</tr>
    //		
    // where
    //		aid = account-id
    //		Fm is one of 'M', 'm', '-'
    //		Fj is one of 'J', 'j', '-'
    //		Fc is one of 'C', 'c', '-'
    //		data-current is current value
    //		data-initial is value when page loaded
    //		Dm is one of:    If current flag =:
    //		   &nbsp;M&nbsp;	'M'
    //		   &nbsp;M&nbsp;	'm'
    //		     Om = overstrike
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
		    $class .= " overstrike";
		    $d2 = $M;
		}
		else
		    $d2 = $M;
		if ( $i % 2 == 0 )
		    $class .= " even-column";
		else
		    $class .= " odd-column";

		$r .= "<pre class='$class'"
		    . "     data-on='$M'"
		    . "     data-off='$m'"
		    . "     data-current='$f'"
		    . "     data-initial='$f'"
		    . "     onmouseenter='ENTER(this)'"
		    . "     onmouseleave='LEAVE(this)'"
		    . "     onclick='CLICK(this)'>"
		    . "&nbsp;$d2&nbsp;</pre>";
	    }
	    $email = $emails[$aid];
	    $r .= "</td><td style='padding-left:1em'>"
	        . "<strong>$aid</strong>"
	        . "</td><td style='padding-left:3em'>"
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
        // Indicates if $aid has create-contest priv.

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
	 ! isset ( $contestname ) )
	exit ( "UNACCEPTABLE HTTP POST: no contest" );

    if ( $process_post
         &&
	 isset ( $_POST['op'] ) )
    {
        $process_post = false;
	$params = get_parameters ( $_POST );
	check_parameters ( $params, $warnings );
	foreach ( $params as $k => $v )
	    $contestdata[$k] = $v;
	$write_contestdata = true;
    }

    if ( $process_post
         &&
	 isset ( $_POST['add-email'] )
	 &&
	 $state == 'normal' )
    {
        $process_post = false;
	$m = trim ( $_POST['add-email'] );
	    // Most browsers trim emails, but just in
	    // case we do too.
	if ( validate_email ( $m, $errors ) )
	{
	    LOCK ( 'admin', LOCK_SH );

	    $e = read_email ( $m );
	    if ( count ( $e ) == 0 )
	        $errors[] = "no user has email: $m";
	    elseif ( $e[0] == '-' )
	    {
	        $t = implode ( ' ', array_slice ( $e, 1 ) );
	        $errors[] = "a user with email $m" .
		            " has never logged in";
	        $errors[] = "but has been assigned to" .
		            " team(s): $t";
	    }
	    else
	    {
		$add_email = $m;
	        $add_uid = $e[0];
		$add_atime = $e[2];
		$notice =
		    "<strong>email $m belongs to user" .
		    " $add_uid who last confirmed" .
		    " $add_atime</strong>";
	        $add_aids = [ $add_uid ];
		$aid_options =
		    "<option value='$add_uid'>" .
		    "user $add_uid - personal" .
		    " account</option>";
		map_tids ( $tid_map, $add_uid );
		foreach ( $tid_map as $tid => $type )
		{
		    $add_aids[] = $tid;
		    $aid_options .=
			"<option value='$tid'>" .
			"team $tid - $type</option>";
		    $m = ( $type == 'manager' ?
		           'the' : 'a' );
		    $notice .=
			"<br><strong>user $add_uid is" .
			" $m $type of team $tid" .
			"</strong>";
		}
		if ( count ( $tid_map ) == 0 )
		    $add_aid = $add_uid;
	    }
	}
    }

    if ( $process_post
         &&
	 isset ( $_POST['add-account'] )
	 &&
	 $state == 'normal' )
    {
        $process_post = false;
	$account = $_POST['add-account'];
	if ( $account == '*CANCEL*' )
	    $add_email = NULL;
	elseif ( ! in_array ( $account, $add_aids ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " add_account=$account" );
	else
	    $add_aid = $account;
    }


    if ( $process_post )
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( isset ( $add_aid) )
    {
	if ( isset ( $flags[$add_aid] ) )
	    $errors[] =
	        "account $add_aid has previously" .
		" been added to contest";
	else
	{
	    $flags[$add_aid] = '---';
	    $emails[$add_aid] = $add_email;
	    $times[$add_aid] = date ( $epm_time_format );
	    $write_contestdata = true;
	    if ( isset ( $notice ) )
	        $notice .= '<br><br>';
	    $notice .=
		"<strong>acccount $add_aid with" .
		" email $add_email has been added" .
		"</strong>";
	}
	$add_aid = NULL;
	$add_email = NULL;
    }

    if ( $write_contestdata )
    {
	$j = json_encode ( $contestdata,
		            JSON_PRETTY_PRINT );
	$f = "projects/$contestname/+contest+";
	$r = file_put_contents ( "$epm_data/$f", $j );
	if ( $r === false )
	    ERROR ( "cannot write file $f" );
    }

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

div.add-account {
    background-color: var(--bg-orange);
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
    background-color: var(--bg-green);
}
pre.flagbox {
    cursor: default;
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

if ( isset ( $contestname ) && $is_manager )
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

if ( ! isset ( $add_email ) )
    echo <<<EOT
    <div class='add-account'>
    <form method='POST' action='contest.php'
          id='add-email'>
    <input type='hidden' name='id' value='$ID'>

    <label>Add Account with Email:</label>
    <input type='email' name='add-email'
           value='$add_email' size='40'
	   onkeydown='KEYDOWN("add-email")'>
    </form>
    </div>
EOT;

elseif ( ! isset ( $add_aid ) )
    echo <<<EOT
    <div class='add-account'>
    <form method='POST' action='contest.php'>
    <input type='hidden' name='id' value='$ID'>

    <label>Select Account to Add with Email
    $add_email:</label>
    <select name='add-account'>
    <option value='*CANCEL*'>cancel</option>
    $aid_options
    </select>
    <button type='submit'>Submit</button>
    </form>
    </div>
EOT;

$account_rows = display_account ( $contestdata );
echo <<<EOT
<div class='accounts'>
<table>
$account_rows
</table>
</div>
EOT;

} // end if ( isset ( $contestname ) && $is_manager )

if ( isset ( $contestname ) && ! $is_manager )
{
    function TBD ( $v, $tbd = 'TDB' )
        { return ( $v === NULL ? $tbd : $v ); }
    $Registration_Email = TBD ( $registration_email );
    $Contest_Type = TBD ( $contest_type );
    if ( $judge_can_see === NULL )
	$Judge_Can_See = '';
    else
	$Judge_Can_See =
	    "<strong style='margin-left:3em'>" .
	    'Judges Can See Contestant Emails' .
	    '</strong>';
    function time_TBD ( $time )
    {
        if ( ! isset ( $time ) ) return 'TBD';
	$time = strtotime ( $time );
	return date ( 'm/d/Y, h:i A T', $time );
    }
    $Solution_Start = time_TBD ( $solution_start );
    $Solution_Stop = time_TBD ( $solution_stop );
    $Description_Start = time_TBD ( $description_start );
    $Description_Stop = time_TBD ( $description_stop );

    echo <<<EOT
    <div class='parameters'>

    <label>To Register, Email:</label>
    <strong>$Registration_Email</strong>

    <div style='margin-top:0.5em;margin-bottom:0.5em'>
    <label>Contest Type:</label>
    <strong>$Contest_Type</strong>
    $Judge_Can_See
    </div>

    <div>
    <label>Problem Solution Submit Times:</label>
    <label style='margin-left:1em'>Start:</label>
    <strong>$Solution_Start</strong>
    <label style='margin-left:1em'>Stop:</label>
    <strong>$Solution_Stop</strong>
    </div>

    <div>
    <label>Problem Definition Submit Times:</label>
    <label style='margin-left:1em'>Start:</label>
    <strong>$Description_Start</strong>
    <label style='margin-left:1em'>Stop:</label>
    <strong>$Description_Stop</strong>
    </div>

    </div>

EOT;

} // end if ( isset ( $contestname ) && ! $is_manager )

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

function DISPLAY ( box, value )
{
    if ( value == '-' )
    {
        box.innerHTML = '&nbsp;&nbsp;&nbsp';
	box.style.textDecoration = 'none';
    }
    else if ( value == box.dataset.on )
    {
        box.innerHTML =
	    '&nbsp;' + box.dataset.on + '&nbsp';
	box.style.textDecoration = 'none';
    }
    else
    {
        box.innerHTML =
	    '&nbsp;' + box.dataset.on + '&nbsp';
	box.style.textDecoration = 'line-through';
    }
}

function NEXT ( box )
{
    var next = box.dataset.current;
    if ( next == box.dataset.initial )
    {
        if ( next == '-' )
	    next = box.dataset.on;
	else if ( next == box.dataset.on )
	    next = box.dataset.off;
	else
	    next = box.dataset.on;
    }
    else
        next = box.dataset.initial;
    return next;
}

function ENTER ( box )
{
    DISPLAY ( box, NEXT ( box ) );
}

function LEAVE ( box )
{
    DISPLAY ( box, box.dataset.current );
}

function CLICK ( box )
{
    box.dataset.current = NEXT ( box );
    DISPLAY ( box, box.dataset.current );
}

</script>

</body>
</html>
