<?php

    // File:	contest.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun  6 16:59:08 EDT 2022

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
    //	        one of:
    //		  * account to be selected if user
    //		    for email has team accounts
    //		  * new account to be added if user has
    //		    no teams and account not already
    //		    added
    //		  * email of old account to be changed
    //		    is user has no teams and user's
    //		    account was previously added
    //
    //	    add-account=AID
    //		Set add_aid variable and enable one of:
    //		  * new account to be added if user has
    //		    account not already added
    //		  * email of old account to be changed
    //		    account was previously added
    //
    //	    op=save OPTIONS
    //		Update +contest+ according to OPTIONS:
    //
    //		registration-email=EMAIL
    //		contest-type=[12]-phase (or omitted)
    //		can-see[K1][K2]=checked
    //		solution-start=TIME
    //		solution-stop=TIME
    //		description-start=TIME
    //		description-stop=TIME
    //		account-flags=FLAG-LIST

    //		K1 and K2 are one of:
    //		    manager, judge, contestant

    //		FLAG-LIST is comma separated list of
    //		items of form ACCOUNT:FLAGS, where FLAGS
    //		is replaced by `XXX' to delete ACCOUNT.
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
    $has_been_deployed = false;
        // Set to true if contest was deployed by
	// THIS post.

    $contestname =
        & $_SESSION['EPM_CONTEST']['CONTESTNAME'];
    $contestdata = & $data['CONTEST'];

    // Parameters stored in +contest+.
    // See HELP for more documentation.
    //
    $contest_description =
	    & $contestdata['contest-description'];
        // Contest description, text as per Descriptions
	// in HELP.
    $registration_email =
	    & $contestdata['registration-email'];
        // Email or NULL.
    $contest_type = & $contestdata['contest-type'];
        // Either '1-phase' or '2-phase' or NULL.
    $can_see = & $contestdata['can-see'];
        // can_see[k1][k2] == 'checked' or '' for
	// k1 == 'judge', 'contestant'
	// k2 == 'manager', 'judge', 'contestant'
    $can_see_labels = ['manager','judge','contestant'];
        // In order of characters in flags, MJC.
    $solution_start = & $contestdata['solution-start'];
    $solution_stop = & $contestdata['solution-stop'];
    $description_start =
	    & $contestdata['description-start'];
    $description_stop =
	    & $contestdata['description-stop'];
        // Time in $epm_time_format or NULL.

    $parameter_labels = [
        'contest-description',
        'registration-email',
	'contest-type',
	'solution-start',
	'solution-stop',
	'description-start',
	'description-stop' ];

    $deployed = & $contestdata['deployed'];
        // Time last deployed in $epm_time_format,
	// or NULL.
    $flags = & $contestdata['flags'];
        // map ACCOUNT => "[Mm-][Jj-][Cc-]"
	//    M if now manager, m if was manager,
	//      - if neither
	//    J if now judge, j if was judge,
	//      - if neither
	//    C if now contestant, c if was contestant,
	//      - if neither
    $emails = & $contestdata['emails'];
        // map ACCOUNT => "email address"
	// Email addresses used to add account
	// to contest.
    $previous_emails =
            & $contestdata['previous-emails'];
        // map ACCOUNT => "previous email address"
	// Previous value of $emails[ACCOUNT] or NULL
	// or unset if none.
    $times = & $contestdata['times'];
        // map ACCOUNT => time of last change to account
	//		  flags or email

    $is_manager = & $data['IS-MANAGER'];
	// True iff $flags[$aid] is set and == 'M..'.
    $is_participant = & $data['IS-PARTICIPANT'];
	// True iff $flags[$aid] is set and has an
	// M, J, or C.
    $email_mask = & $data['EMAIL-MASK'];
	// If $is_participant but not $is_manager,
	// set to 'mjc' where
	//   m = 'M' if $can_see[k]['manager'] != ''
	//	        else 'X'
	//   j = 'J' if $can_see[k]['judge'] != ''
	//	        else 'X'
	//   c = 'C' if $can_see[k]['contestant'] != ''
	//	        else 'X'
	//   k = 'judge' if $flags[$aid] is '.J.'
	//               else 'contestant'
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
	       $is_manager, $is_participant,
	       $email_mask, $can_see, $can_see_labels,
	       $aid, $flags;

	// You cannot simply set $contestdata because
	// of the references into it, but must set
	// each of its elements individually.

	foreach (array_keys ( $contestdata ) as $k )
	    $contestdata[$k] = NULL;

	$is_manager = false;
	$is_participant = false;
	$email_mask = 'XXX';

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
		foreach ( array_keys ( $contestdata )
		          as $k )
		{
		    if ( isset ( $j[$k] ) )
			$contestdata[$k] = $j[$k];
		    else
			$contestdata[$k] = NULL;
		}
		if ( isset ( $flags[$aid] ) )
		{
		    $k1s = [];
		    if ( $flags[$aid][0] == 'M' )
		    {
			$is_manager = true;
			$is_participant = true;
		    }
		    if ( $flags[$aid][1] == 'J' )
		    {
			$is_participant = true;
			$k1s[] = 'judge';
		    }
		    if ( $flags[$aid][2] == 'C' )
		    {
			$is_participant = true;
			$k1s[] = 'contestant';
		    }

		    $MJC = 'MJC';
		    $mask = ['X','X','X'];
		    foreach ( $k1s as $k1 )
		    {
			for ( $i = 0; $i < 3; $i ++ )
			{
			    $k2 = $can_see_labels[$i];
			    if (    $can_see[$k1][$k2]
			         != '' )
				$mask[$i] = $MJC[$i];
			}
		    }
		    $email_mask = implode ( '', $mask );
		}
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
    // call exit with UNACCEPTABLE HTTP POST.  Return
    // array.
    //
    // The account-flags=FLAG-LIST parameter is
    // converted to a $parameters['flags] map and
    // checked against $flags to see if the parameter
    // is legal.
    //
    // More subtle parameter errors are detected by
    // check_parameters which generates warnings.
    //
    function get_parameters ( $post )
    {
	global $epm_time_format, $flags,
	       $can_see_labels;

        $r = [];

	if ( ! isset ( $post['contest-description'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " no contest-description" );
	$v = $post['contest-description'];
	$v = rtrim ( $v );
	if ( $v == '' ) $v = NULL;
	$r['contest-description'] = $v;

	if ( ! isset ( $post['registration-email'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " no registration-email" );
	$v = $post['registration-email'];
	$v = trim ( $v );
	if ( $v == '' ) $v = NULL;
	$r['registration-email'] = $v;

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
	$r['contest-type'] = $v;

	$w = [];
	foreach ( $can_see_labels as $k1 )
	{
	    if ( $k1 == 'manager' ) continue;

	    $w[$k1] = [];
	    foreach ( $can_see_labels as $k2 )
	        $w[$k1][$k2] = '';
	}
	if ( isset ( $post['can-see'] ) )
	{
	    $v = $post['can-see'];
	    if ( ! is_array ( $v ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " can-see = $v" );
	    foreach ( $v as $k1 => $v1 )
	    {
	        if ( ! in_array
		         ( $k1, $can_see_labels )
		     ||
		     $k1 == 'manager' )
		    exit ( "UNACCEPTABLE HTTP POST:" .
			   " can-see[$k1]" );
	        if ( ! is_array ( $v1 ) )
		    exit ( "UNACCEPTABLE HTTP POST:" .
			   " can-see[$k1] = $v1" );
		foreach ( $v1 as $k2 => $v2 )
		{
		    if ( ! in_array
		             ( $k2, $can_see_labels ) )
			exit ( "UNACCEPTABLE HTTP" .
			       " POST:" .
			       " can-see[$k1][$k2]" );
		    if ( $v2 != 'checked' )
			exit ( "UNACCEPTABLE HTTP" .
			       " POST:" .
			       " can-see[$k1][$k2] =" .
			       " $v2" );
		    $w[$k1][$k2] = $v2;
		}
	    }
	}
	$r['can-see'] = $w;

	foreach (
	    ['solution-start','solution-stop',
	     'description-start','description-stop']
	    as $m )
	{
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
	    $r[$m] = $v;
	}

	if ( ! isset ( $post['account-flags'] ) )
	    exit ( "UNACCEPTABLE HTTP POST:" .
	           " no account-flags" );
	$account_flags = & $r['flags'];
	$account_flags = [];
	$MJC = 'MJC';
	$mjc = 'mjc';
	foreach ( explode
		      ( ';', $post['account-flags'] )
		  as $item )
	{
	    list ( $a, $f ) = explode ( ':', $item );
	    if ( ! isset ( $flags[$a] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " extraneous account $a" );
	    $old_f = $flags[$a];
	    $account_flags[$a] = $f;
	    if ( $old_f == $f || $f == 'XXX' ) continue;
	    for ( $i = 0; $i < 3; $i ++ )
	    {
	        $M = $MJC[$i];
	        $m = $mjc[$i];
		$fi = $f[$i];
		$old_fi = $old_f[$i];
		if ( $fi == $old_fi ) continue;
		if ( $old_fi == '-' ) $next = $M;
		elseif ( $old_fi == $M ) $next = $m;
		else $next = $M;
		if ( $fi == $next ) continue;
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " $a => $f !~ $old_f" );
	    }
	}
	foreach ( $flags as $a => $f )
	{
	    if ( ! isset ( $account_flags[$a] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " missing account $a" );
	}

	return $r;
    }

    // Check the parameters returned by get_parameters
    // for warnings.
    //
    // Some error checks reference $contestdata; e.g.,
    // $deployed is used to prevent changes to
    // $contest_type.
    //
    // Some warnings cause $params to be changed so that
    // it does not alter $contestdata.
    //
    // $warnings must be set to a list (it cannot be
    // undefined) when this is called.
    //
    function check_parameters ( & $params, & $warnings )
    {
        global $contestdata, $aid;

    	$v = $params['registration-email'];
	$err = [];
	if ( isset ( $v )
	     &&
	     ! validate_email ( $v, $err ) )
	{
	        $warnings[] = "Bad Registration Email:";
		foreach ( $err as $e )
		    $warnings[] = "    $e";
		$params['registration-email'] =
		    $contestdata['registration-email'];
	}

	if ( isset ( $contestdata['deployed'] )
	     &&
	     isset ( $contestdata['contest-type'] )
	     &&
	     $contestdata['contest-type']
	     !=
	     $params['contest-type'] )
	{
	    $v = $contestdata['contest-type'];
	    $w = $params['contest-type'];
	    if ( $w == NULL ) $w = "NONE";
	    $warnings[] =
	        "contest type is being changed" .
	        " from $v to $w after";
	    $warnings[] =
	        "contest was deployed on " .
	        $contestdata['deployed'];
	}

	$f = $params['flags'][$aid];
	if ( $f[0] != 'M' )
	{
	    $warnings[] =
	        "you cannot delete yourself or cease" .
		" to be a manager";
	    $warnings[] =
	        "(you must get another manager to" .
		" make such changes)";
	    $params['flags'][$aid] =
	        $contestdata['flags'][$aid];
	}
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
    //		<button type='button'
    //		     style='margin-left:1em'
    //		     data-del='false'
    //		     onclick=TOGGLE_DELETE(this)
    //               title='Delete Account'
    //		     <pre>&Chi;</pre></button>
    //		</td>
    //		<td style='padding-left:1em'>
    //          <strong>aid</strong></td>
    //		<td style='padding-left:3em'>
    //          <strong>email</strong></td>
    //		<td style='padding-left:3em'>
    //          <strong>previous-email</strong></td>
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
    // Previous-email is omitted if it does not exist
    // or current user is not a manager.
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
    function display_accounts
    		( $is_manager, $email_mask = 'XXX' )
    {
        global $flags, $emails, $previous_emails;
	$MJC = 'MJC';
	$mjc = 'mjc';

        $r = '';
	foreach ( $flags as $aid => $aidflags )
	{
	    $r .= "<tr><td>";
	    for ( $i = 0; $i < 3; $i ++ )
	    {
	        $M = $MJC[$i];
	        $m = $mjc[$i];
		$f = $aidflags[$i];
		$class = 'flagbox';
		if ( $f == '-' )
		    $d2 = '&nbsp;';
		elseif ( $f == $m )
		{
		    if ( $is_manager )
		    {
			$class .= " overstrike";
			$d2 = $M;
		    }
		    else
			$d2 = '&nbsp;';
		}
		else
		    $d2 = $M;
		if ( $i % 2 == 0 )
		    $class .= " even-column";
		else
		    $class .= " odd-column";

		if ( $is_manager )
		    $r .= "<pre class='$class'"
			. "     data-on='$M'"
			. "     data-off='$m'"
			. "     data-current='$f'"
			. "     data-initial='$f'"
			. "     onmouseenter='ENTER"
			. "                     (this)'"
			. "     onmouseleave='LEAVE"
			. "                     (this)'"
			. "     onclick='CLICK(this)'>"
			. "&nbsp;$d2&nbsp;</pre>";
		else
		    $r .= "<pre class='$class'>"
			. "&nbsp;$d2&nbsp;</pre>";
	    }

	    if ( $is_manager )
		$r .= "<button type='button'"
		    . "        style='margin-left:1em'"
		    . "        data-del='false'"
		    . "        onclick="
		    . "            TOGGLE_DELETE(this)"
		    . "        title='Delete Account'"
		    . "         <pre>&Chi;</pre>"
		    . "        </button>";

	    $show_email = $is_manager;
	    $i = 0;
	    while ( ! $show_email && $i < 3 )
	    {
	        if ( $email_mask[$i] == $aidflags[$i] )
		    $show_email = true;
		++ $i;
	    }

	    $r .= "</td><td style='padding-left:1em'>"
		. "<strong>$aid</strong></td>";
	    if ( $show_email )
	    {
		$email = $emails[$aid];
		$r .= "<td style='padding-left:3em'>"
		    . "<strong>$email</strong></td>";
	    }
	    if ( isset ( $previous_emails[$aid] )
	         &&
		 $is_manager )
	    {
		$email = $previous_emails[$aid];
		$r .= "<td style='padding-left:3em'>"
		    . "<strong>(previously $email)"
		    . "</strong></td>";
	    }
	    $r .= "</tr>";
	}
	return $r;
    }

    # Update the contest project privilege file.  This
    # file has the format:
    #
    #	# *BEGIN* *CONTEST* *ACCOUNT* *DEFINITIONS*
    #	+ @manager <manager-account>
    #	.....
    #	+ @judge <judge-account>
    #	.....
    #	+ @contestant <contestant-account>
    #	.....
    #	# *END* *CONTEST* *ACCOUNT* *DEFINITIONS*
    #   <manually edited stuff, if any>
    #	# *BEGIN* *CONTEST* *PRIVILEGES*
    #	<result of epm_contest_priv in parameters.php>
    #	# *END* *CONTEST* *PRIVILEGES*
    #
    # The *CONTEST* *PRIVILEGES* section is omitted
    # if $contest_description or $registration_email
    # is NULL, and false is returned.  Otherwise true
    # is returned.
    #
    function deploy()
    {
    	global $contestname, $contest_type,
               $registration_email,
	       $contest_description,
	       $solution_start, $solution_stop,
	       $description_start, $description_stop,
	       $flags, $deployed,
	       $epm_data, $epm_time_format;

	$begin_accounts =
	    "# *BEGIN* *CONTEST* *ACCOUNT*" .
	    " *DEFINITIONS*";
	$end_accounts =
	    "# *END* *CONTEST* *ACCOUNT* *DEFINITIONS*";
	$begin_privs =
	    "# *BEGIN* *CONTEST* *PRIVILEGES*";
	$end_privs =
	    "# *END* *CONTEST* *PRIVILEGES*";

	$pm = '';
	$pj = '';
	$pc = '';
	foreach ( $flags as $account => $f )
	{
	    if ( $f[0] == 'M' )
	        $pm .= "+ @manager $account" . PHP_EOL;
	    if ( $f[1] == 'J' )
	        $pj .= "+ @judge $account" . PHP_EOL;
	    if ( $f[2] == 'C' )
	        $pc .= "+ @contestant $account"
		    . PHP_EOL;
	}
	$p = $begin_accounts . PHP_EOL .
	     $pm . $pj . $pc .
	     $end_accounts . PHP_EOL . PHP_EOL;

	$fname = "projects/$contestname/+priv+";
	if ( file_exists ( "$epm_data/$fname" ) )
	{
	    $c = ATOMIC_READ ( "$epm_data/$fname" );
	    if ( $c === false )
	        ERROR ( "cannot read extant $fname" );
	    $lines = explode ( "\n", $c );
	    if ( $lines[0] == $begin_accounts )
	    {
	        $i = array_search
		    ( $end_accounts, $lines );
		if ( $i === false )
		    ERROR ( "badly formatted $fname" );
		array_splice ( $lines, 0, $i + 1 );
	    }

	    $blanks = 0;
	    $first = true;
	    foreach ( $lines as $line )
	    {
		if ( trim ( $line ) == '' )
		{
		    $blanks = $blanks + 1;
		    continue;
		}
		elseif ( $line == $begin_privs )
		    break;

		if ( $first )
		    $first = false;
		elseif ( $blanks > 0 )
		    $p .= PHP_EOL;
		    // Multiple blank lines between
		    // non-blank lines become single
		    // blank lines.

		$blanks = 0;
		$p .= $line . PHP_EOL;
	    }
	    if ( ! $first )
		$p .= PHP_EOL;
	}

	$output_privs =
	    ( isset ( $contest_description )
	      &&
	      isset ( $registration_email ) );
		        
	if ( $output_privs )
	{
	    $p .= $begin_privs . PHP_EOL;
	    $p .= epm_contest_priv
		      ( $contest_type,
			$solution_start,
			$solution_stop,
			$description_start,
			$description_stop );
	    $p .= $end_privs . PHP_EOL;
	}

	$r = ATOMIC_WRITE ( "$epm_data/$fname", $p );
	if ( $r === false )
	    ERROR ( "cannot write $fname" );

	$t = filemtime ( "$epm_data/$fname" );
	if ( $t === false )
	    ERROR ( "cannot stat $fname" );
	$deployed = date ( $epm_time_format, $t );

	return $output_privs;
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
    $actions = [];
        // List of actions, each of which is written
	// as a line with PHP_EOL added into each member
	// of $action_files.
    $action_files = [ "accounts/$aid/+actions+" ];

    // Put $message in $notice, separating it from the
    // previous message, if any, with $separator.  The
    // $message is put in <strong>...</strong> brackets.
    //
    function note ( $message, $separator = "<br>" )
    {
        global $notice;
	$message = "<strong>" . $message . "</strong>";
	if ( isset ( $notice ) )
	    $notice .= $separator . $message;
	else
	    $notice = $message;
    }

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
		( ['flags' => [$aid => 'M--'],
		   'emails' => [$aid => $m],
		   'times' => [$aid => $t]],
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

    if ( isset ( $contestname ) )
        $action_files[] =
	    "projects/$contestname/+actions+";

    if ( $process_post
         &&
	 isset ( $_POST['op'] ) )
    {
        $process_post = false;
	$op = $_POST['op'];
	if ( $op == 'save' )
	{
	    $params = get_parameters ( $_POST );
	    check_parameters ( $params, $warnings );
	    $pflags = & $params['flags'];
	    $time = date ( $epm_time_format );
	    $header = "$time $aid contest $contestname";
	    $MJC = ['M','J','C'];
	    $mjc = ['manager','judge','contestant'];
	    foreach ( $flags as $a => $f )
	    {
		$pf = $pflags[$a];
	        if ( $pf == 'XXX' )
		{
		    note ( "deleted account $a" );
		    unset ( $pflags[$a] );
		    unset ( $emails[$a] );
		    unset ( $previous_emails[$a] );
		    unset ( $times[$a] );
		    $actions[] = "$header delete $a";
		}
		elseif ( $f != $pf )
		{
		    for ( $i = 0; $i < 3; $i++ )
		    {
		        $fi = ( $f[$i] == $MJC[$i] );
		        $pfi = ( $pf[$i] == $MJC[$i] );
			if ( $fi == $pfi ) continue;
			$s = ( $pfi ? '+' : '-' );
			$r = $mjc[$i];
			$actions[] =
			    "$header role $a $s $r";
		    }
		    $times[$a] = $time;
		}
	    }
	    foreach ( $parameter_labels as $k )
	    {
	        if ( $contestdata[$k] == $params[$k] )
		    continue;
		$v = $params[$k];
		if ( ! isset ( $v ) ) $v = 'NULL';
		elseif ( $k == 'contest-description' )
		{
		    $v = explode ( "\n", $v );
		    $v = trim ( $v[0] );
		    if ( strlen ( $v ) > 20 )
		        $v = substr ( $v, 0, 17 )
			   . "...";
		}
		$actions[] = "$header set $k $v";
		$contestdata[$k] = $params[$k];
	    }
	    $contestdata['flags'] = $params['flags'];
	    $contestdata['can-see'] =
	        $params['can-see'];
	    $write_contestdata = true;
	}
	elseif ( $op != 'reset' )
	    exit ( "UNACCEPTABLE HTTP POST: op=$op" );
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
	        $t = implode
		    ( ' ', array_slice ( $e, 1 ) );
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
		note ( "email $m belongs to user" .
		       " $add_uid who last confirmed" .
		       " $add_atime" );
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
		    note ( "user $add_uid is" .
			   " $m $type of team $tid" );
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
	           " add-account=$account" );
	else
	    $add_aid = $account;
    }


    if ( $process_post )
	exit ( 'UNACCEPTABLE HTTP POST' );

    if ( isset ( $add_aid) )
    {
	if ( ! isset ( $emails[$add_aid] ) )
	{
	    $flags[$add_aid] = '---';
	    $emails[$add_aid] = $add_email;
	    $times[$add_aid] =
		date ( $epm_time_format );
	    $write_contestdata = true;
	    note ( "acccount $add_aid with" .
		   " email $add_email has been added",
		   "<br><br>" );
	}
	elseif ( $emails[$add_aid] == $add_email )
	    $warnings[] =
	        "account $add_aid with email" .
		" $add_email already exists";
	else
	{
	    note ( "email of acccount $add_aid" .
		   " has been changed from " .
		   $emails[$add_aid] .
		   " to $add_email",
		   "<br><br>" );
	    $previous_emails[$add_aid] =
	        $emails[$add_aid];
	    $emails[$add_aid] = $add_email;
	    $times[$add_aid] =
		date ( $epm_time_format );
	    $write_contestdata = true;
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
	$has_been_deployed = deploy();
	if ( $has_been_deployed === false )
	    $warnings[] = "contest was NOT deployed";
    }

    if ( isset ( $contestname )
	 &&
	 $is_manager )
    {
	if ( $has_been_deployed )
	    note ( "Contest Has Just Been Deployed!",
	           "<br><br>" );
	elseif ( isset ( $deployed ) )
	    note ( "Contest Was Deployed:" .
		   "&nbsp;&nbsp;&nbsp;$deployed",
	           "<br><br>" );
	else
	    note ( "Contest Has NOT Been Deployed",
	           "<br><br>" );
    }

    if ( count ( $actions ) > 0 )
    {
        $c = implode ( PHP_EOL, $actions ) . PHP_EOL;
	foreach ( $action_files as $f )
	{
	    $r = @file_put_contents
		( "$epm_data/$f", $c, FILE_APPEND );
	    if ( $r === false )
		ERROR ( "cannot write $f" );
	}
    }

    if ( isset ( $contestname ) )
    {
	if ( $contest_description === NULL )
	    $warnings[] =
		"Contest Description is missing";

	if ( $contest_type === NULL )
	    $warnings[] =
		"Contest Type is missing";

	if ( $registration_email === NULL )
	    $warnings[] =
		"Registration Email is missing";

	if ( ! isset ( $solution_start ) )
	    $warnings[] =
	        "Solution Start Time is missing";
	elseif ( ! isset ( $solution_stop ) )
	    $warnings[] =
	        "Solution Stop Time is missing";
	elseif ( strtotime ( $solution_stop )
	         <=
	         strtotime ( $solution_start ) )
	    $warnings[] = "Solution Stop Time is not" .
	                  " later than Solution Start" .
			  " Time";

	if ( ! isset ( $description_start ) )
	    $warnings[] =
	        "Problem Definition Start Time is" .
		" missing";
	elseif ( ! isset ( $description_stop ) )
	    $warnings[] =
	        "Problem Definition Stop Time is" .
		" missing";
	elseif ( strtotime ( $description_stop )
	         <=
	         strtotime ( $description_start ) )
	    $warnings[] =
	        "Problem Definition Stop Time is not" .
		" later than Problem Definition Start" .
		" Time";

	if ( ! $is_participant )
	    $warnings[] =
	        'You are not contestant, judge,' .
		' or manager of this contest.';
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
    background-color: var(--bg-tan);
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

    // By fixing div height we keep it from changing
    // when flipping from non-edited to edited.
    //
    echo <<<EOT
    <div class='manage' style='height:6em'>
    <table style='width:100%'
           id='not-edited'>

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

    <table style='width:100%;display:none'
           id='edited'>
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

    if ( isset ( $contest_description ) )
        $description_html =
	    description_to_HTML
	        ( $contest_description );
    else
        $description_html = '';
    $z = date ( "T" );
    $z = "<strong>$z</strong>";
    function local_time ( $time )
    {
	if ( $time === NULL ) return NULL;
        $time = strtotime ( $time );
	$time = date ( "Y-m-d\Th:i:s", $time );
	return $time;
    }
    $Solution_Start = local_time ( $solution_start );
    $Solution_Stop = local_time ( $solution_stop );
    $Description_Start =
        local_time ( $description_start );
    $Description_Stop =
        local_time ( $description_stop );
    if ( isset ( $contest_type ) )
        $select_type =
	    "<script>document.getElementById" .
	    " ( '$contest_type' ).checked=true;" .
	    "</script>";
    else
        $select_type = '';
    $dtitle = 'mm/dd/yyyy, hh::mm:[AP]M';

    if ( ! isset ( $add_email ) )
	echo <<<EOT
	<div class='add-account'>
	<form method='POST' action='contest.php'
	      id='add-account'>
	<input type='hidden' name='id' value='$ID'>

	<label>Email for New/Old Account:</label>
	<input type='email' name='add-email'
	       value='$add_email' size='40'
	       onkeydown='KEYDOWN("add-account")'>
	</form>
	</div>
EOT;

    elseif ( ! isset ( $add_aid ) )
	echo <<<EOT
	<div class='add-account'>
	<form method='POST' action='contest.php'
	      id='add-account'>
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

    echo <<<EOT
    <div class='parameters'>
    <form method='POST' action='contest.php'
          id='parameters-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <input type='hidden' name='account-flags'
                         id='account-flags'>

    <label>Contest Description:</label>
    <br>
    <div class='list-description'
         style='margin-left:1em'
    >$description_html</div>

    <br>
    <label style='padding-right:1em'>
    Edit Contest Description:</label>
    <br>
    <textarea name='contest-description'
              style='vertical-align:text-top;
	             margin-bottom:1em;
		     margin-left:1em'
	      oninput='ONCHANGE()'
	      rows='4' cols='80'
    >$contest_description</textarea>

    <br>
    <label>To Register, Email</label>
    <strong>:&nbsp;&nbsp;&nbsp;&nbsp;</strong>
    <input type='email' name='registration-email'
           value='$registration_email' size='40'
	   onchange='ONCHANGE()'
	   onkeydown='KEYDOWN(null)'>

    <table style='padding:1% 0px'>
    <tr>
    <td style='padding-right:5em'>
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
    </td>
    <td style='padding-right:2em'>
    <label>Judges Can See Email Addresses of:</label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[judge][manager]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['judge']['manager']}
	   onchange='ONCHANGE()'>
    Managers</label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[judge][judge]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['judge']['judge']}
	   onchange='ONCHANGE()'>
    Judges</label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[judge][contestant]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['judge']['contestant']}
	   onchange='ONCHANGE()'>
    Contestants</label>
    </td>
    </tr>
    <tr>
    <td></td>
    <td style='padding-right:2em'>
    <label>Contestants Can See Email Addresses of:
    </label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[contestant][manager]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['contestant']['manager']}
	   onchange='ONCHANGE()'>
    Managers</label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[contestant][judge]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['contestant']['judge']}
	   onchange='ONCHANGE()'>
    Judges</label>
    </td>
    <td>
    <label>
    <input type='checkbox'
           name='can-see[contestant][contestant]'
	   style='margin-bottom:0px'
	   value='checked'
	   {$can_see['contestant']['contestant']}
	   onchange='ONCHANGE()'>
    Contestants</label>
    </td>
    </tr>
    </table>

    <table>
    <tr>
    <td>
    <label>Problem Solution Submit Times:</label>
    </td>
    <td style='padding-left:1em'>
    <label>Start:</label>
    &nbsp;
    <input type='datetime-local' name='solution-start'
                value='$Solution_Start'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </td>
    <td style='padding-left:1em'>
    <label>Stop:</label>
    &nbsp;
    <input type='datetime-local' name='solution-stop'
                value='$Solution_Stop'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </td>
    </tr>

    <tr>
    <td>
    <label>Problem Definition Submit Times:</label>
    </td>
    <td style='padding-left:1em'>
    <label>Start:</label>
    &nbsp;
    <input type='datetime-local'
                name='description-start'
                value='$Description_Start'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </td>
    <td style='padding-left:1em'>
    <label>Stop:</label>
    &nbsp;
    <input type='datetime-local'
                name='description-stop'
                value='$Description_Stop'
	        title='$dtitle'
		onchange='ONCHANGE()'> $z
    </td>
    </tr>
    </table>

    </form>
    </div>
EOT;

$account_rows = display_accounts ( true );
echo <<<EOT
<div class='accounts'>
<table id='account-rows'>
$account_rows
</table>
</div>

<script>

var not_edited =
    document.getElementById ( 'not-edited' );
var edited =
    document.getElementById ( 'edited' );
var add_account =
    document.getElementById ( 'add-account' );
function ONCHANGE ( )
{
    not_edited.style.display = 'none';
    edited.style.display = 'table';
    add_account.style.visibility = 'hidden';
}

var parameters_form =
    document.getElementById ( 'parameters-form' );
var op_input =
    document.getElementById ( 'op' );
var account_rows =
    document.getElementById ( 'account-rows' );
var account_flags =
    document.getElementById ( 'account-flags' );
function SUBMIT ( op )
{
    op_input.value = op;
    if ( op == 'reset' )
    {
	parameters_form.submit();
	return;
    }

    var ROW = account_rows.firstElementChild  // tbody
                          .firstElementChild; // tr
    var list = [];
    while ( ROW != null )
    {
        let TD = ROW.firstElementChild;
        var FLAGS = TD.firstElementChild;  // pre
	var flags = [];
	for ( var i = 0; i < 3; ++ i )
	{
	    flags.push ( FLAGS.dataset.current );
	    FLAGS = FLAGS.nextElementSibling;
	}
	flags = flags.join ( '' );
	if ( FLAGS.dataset.del == 'true' )
	    flags = 'XXX';

	let account = TD.nextElementSibling  // td
	                .firstElementChild   // strong;
			.innerText;
	list.push ( [account,flags].join ( ':' ) );

        ROW = ROW.nextElementSibling;
    }
    account_flags.value = list.join ( ';' );
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
    ONCHANGE();
    DISPLAY ( box, box.dataset.current );
}

function TOGGLE_DELETE ( button )
{
    ACCOUNT = button.parentElement
                    .nextElementSibling
		    .firstElementChild;
    if ( button.dataset.del == 'true' )
    {
        button.dataset.del = 'false';
	button.title = 'Delete Account';
	ACCOUNT.style.textDecoration = 'none';
    }
    else
    {
        button.dataset.del = 'true';
	button.title = 'UN-Delete Account';
	ACCOUNT.style.textDecoration =
	    'line-through red wavy';
    }

    ONCHANGE();
}

</script>
EOT;

} // end if ( isset ( $contestname ) && $is_manager )

if ( isset ( $contestname ) && ! $is_manager )
{
    function TBD ( $v, $tbd = 'TDB' )
        { return ( $v === NULL ? $tbd : $v ); }
    if ( isset ( $contest_description ) )
        $description_html =
	    description_to_HTML
	        ( $contest_description );
    else
        $description_html = 'TBD';
    $Registration_Email = TBD ( $registration_email );
    $Contest_Type = TBD ( $contest_type );
    function time_TBD ( $time )
    {
        if ( ! isset ( $time ) ) return 'TBD';
	$time = strtotime ( $time );
	return date ( 'm/d/Y, h:i A T', $time );
    }
    $Solution_Start =
        time_TBD ( $solution_start );
    $Solution_Stop =
        time_TBD ( $solution_stop );
    $Description_Start =
        time_TBD ( $description_start );
    $Description_Stop =
        time_TBD ( $description_stop );

    $i = 0;
    if ( $can_see['contestant']['contestant'] != '' )
        $i = $i + 1;
    if ( $can_see['judge']['contestant'] != '' )
        $i = $i + 2;
    $Can_See = ['Only Managers',
                'Managers and Contestants',
                'Managers and Judges',
                'Managers, Judges,' .
		' and Contestants'][$i];

    echo <<<EOT
    <div class='parameters'>

    <label>Contest Description:</label>
    <br>
    <div class='list-description'
         style='margin-left:1em;
	        padding-bottom:0.5em'
    >$description_html</div>

    <label>To Register, Email</label>
    <strong>:&nbsp;&nbsp;&nbsp;&nbsp;</strong>
    <strong>$Registration_Email</strong>

    <table style='padding:1% 0px'>
    <tr>
    <td style='padding-right:5em'>
    <label>Contest Type:</label>
    <strong>$Contest_Type</strong>
    </td>
    <td style='padding-right:2em'>
    <label>$Can_See Can See Email Addresses
           of Contestants</label>
    </td>
    </tr>
    </table>

    <table>
    <tr>
    <td>
    <label>Problem Solution Submit Times:</label>
    </td>
    <td style='padding-left:1em'>
    <label>Start:</label>
    &nbsp;
    <strong>$Solution_Start</strong>
    </td>
    <td style='padding-left:1em'>
    <label>Stop:</label>
    &nbsp;
    <strong>$Solution_Stop</strong>
    </td>
    </tr>

    <tr>
    <td>
    <label>Problem Definition Submit Times:</label>
    </td>
    <td style='padding-left:1em'>
    <label>Start:</label>
    &nbsp;
    <strong>$Description_Start</strong>
    </td>
    <td style='padding-left:1em'>
    <label>Stop:</label>
    &nbsp;
    <strong>$Description_Stop</strong>
    </td>
    </tr>
    </table>

    </div>
EOT;

    if ( $is_participant )
    {
	$account_rows =
	    display_accounts ( false, $email_mask );
	echo <<<EOT
	<div class='accounts'>
	<table id='account-rows'>
	$account_rows
	</table>
	</div>
EOT;
    }

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
</script>

</body>
</html>
