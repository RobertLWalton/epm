<?php

// File:    epm_user.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue Apr  5 04:16:51 EDT 2022

// The authors have placed EPM (its files and the
// content of these files) in the public domain;
// they make no warranty and accept no liability
// for EPM.

// Functions to read user information.
//
// WARNING: No error message may contain the value of
//          $epm_data or $epm_home.
//
// To include this program, be sure the following are
// defined.

if ( ! isset ( $epm_web ) )
    exit ( 'ACCESS ERROR: $epm_web not set' );
if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $epm_home ) )
    exit ( 'ACCESS ERROR: $epm_home not set' );

// Fields are in display order with value being display
// label.  Format is
//
//	KEY => [FORM_LABEL, MIN_LENGTH, MAX_LENGTH,
//              PLACEHOLDER, TITLE]
//
// or   KEY => [] if KEY is to be displayed in another
//                   fashion.
//
$epm_info_fields =
    [ 'user' =>
          [ 'uid' => ['User ID',4,12,
                      'Your User ID',
		      'Your User ID (short name by' .
		      ' which others will know you)'],
	    'emails' => [],
	    'guests' => [],
	    'full_name' => ['Full Name',8,40,
	                    'John Doe',
			    'Your Full Name'],
	    'organization' => ['Organization',8,40,
	                       'University, Company,' .
			           ' or Association',
			       'Organization with' .
			           ' which you are' .
				   ' associated'],
	    'location' => ['Location',8,40,
	                   'Town, State, and Country',
	                   'Town, State, and Country' .
			       ' of Organization' .
			       ' or Yourself']
	  ],
      'team' =>
          [ 'manager' => ['Manager',4,12,
	                  'User ID of Manager',
	                  'User ID of Manager'],
            'tid' => ['Team ID',4,12,
                      'Team ID',
		      'Team ID (short name by which' .
		      ' others will know the team)'],
	    'team_name' => ['Team Full Name',6,40,
	                    'Whatever Works for You',
			    'Team Full Name'],
	    'members' => [],
	    'organization' => ['Organization',8,40,
	                       'University, Company,' .
			           ' or Association',
			       'Organization with' .
			           ' which team is' .
				   ' associated'],
	    'location' => ['Location',8,40,
	                   'Town, State, and Country',
	                   'Town, State, and Country' .
			       ' of Organization' .
			       ' or Team Members']
	  ]
    ];

// Get list of users or teams.  $type is either 'user'
// or 'team'.
//
function read_accounts ( $type )
{
    global $epm_data, $epm_name_re;

    $r = [];
    $d = "admin/{$type}s";
    @mkdir ( "$epm_data/$d", 02770, true );
    $c = @scandir ( "$epm_data/$d" );
    if ( $c === false )
	ERROR ( "cannot read $d" );
    foreach ( $c as $u )
    {
	if ( preg_match ( $epm_name_re, $u ) )
	    $r[] = $u;
    }
    return $r;
}

// Get the tids list from the $uid user's 'manager'
// or 'member' files.
//
function read_tids ( $uid, $name )
{
    global $epm_data;

    $f = "admin/users/$uid/$name";
    $c = @file_get_contents ( "$epm_data/$f" );
    if ( $c === false ) return [];
    $c = trim ( $c );
    if ( $c == '' ) return [];
    return explode ( ' ', $c );
}

// Write the $tids list to the $uid user's 'manager'
// or 'member' files.
//
function write_tids ( $tids, $uid, $name )
{
    global $epm_data;

    $f = "admin/users/$uid/$name";
    $r = @file_put_contents
        ( "$epm_data/$f",
	  implode ( ' ', $tids ) );
    if ( $r === false )
        ERROR ( "cannot write file $f" );
}

// Check that a value is a legal email.  Return true if
// it is, else return false.  If it is not legal, write
// error messages to $errors.  If $email is blank,
// return false but no error messages are written.
//
function validate_email ( $email, & $errors )
{
    $email =  trim ( $email );
    if ( $email == "" ) return false;
    $svalue = filter_var
	( $email, FILTER_SANITIZE_EMAIL );
    if ( $email != $svalue )
    {
	$errors[] =
	    "Email $email contains characters" .
	    " illegal in an email address";
	return false;
    }
    if ( ! filter_var
	      ( $email,
		FILTER_VALIDATE_EMAIL ) )
    {
	$errors[] =
	    "Email $email is not a valid email" .
	    " address";
	return false;
    }
    return true;
}

// Get the item list from the admin/email/$email file.
//
function read_email ( $email )
{
    global $epm_data;

    $f = "admin/email/" . rawurlencode ( $email );
    $c = @ file_get_contents ( "$epm_data/$f" );
    if ( $c === false ) return [];;
    $c = trim ( $c );
    if ( $c == '' ) return [];
    return explode ( ' ', $c );
}

// Write the item list to the admin/email/$email file.
//
function write_email ( $items, $email )
{
    global $epm_data;

    $f = "admin/email/" . rawurlencode ( $email );
    $r = @file_put_contents
        ( "$epm_data/$f",
	  implode ( ' ', $items ) );
    if ( $r === false )
        ERROR ( "cannot write file $f" );
}

// Initialize email.  If email exists, begins with '-',
// and therefore contains a list of tids, update the tid
// infos and uid member list.
//
// Return true if initialization succeeded and false
// if it failed because email file already existed
// and did not begin with '-'.
//
function init_email ( $uid, $email )
{
    global $epm_data;

    $items = read_email ( $email );
    if ( count ( $items ) > 0 )
    {
	if ( $items[0] != '-' )
	    return false;
	array_splice ( $items, 0, 1 );
	$tids = read_tids ( $uid, 'member' );
	foreach ( $items as $tid )
	{
	    $fl = "admin/teams/$tid/$uid.login";
	    $fi = "admin/teams/$tid/$uid.inactive";

	    $info = read_info ( 'team', $tid );
	    $mems = & $info['members'];
	    $found = NULL;
	    $duplicate = false;

	    $new_mems = [];
	    foreach ( $mems as $mem )
	    {
	        list ( $id, $mail ) =
		    split_member ( $mem );
		if ( $mail == $email )
		    $found = "$uid($email)";
		elseif ( $id == $uid )
		    $duplicate = true;
		else
		    $new_mems[] = $mem;
	    }

	    if ( ! isset ( $found ) ) continue;
	        // Nothing to change.

	    $new_mems[] = $found;
	    $info['members'] = $new_mems;
	    write_info ( $info );

	    if ( $duplicate ) continue;
	        // Do not change $uid/member or
		// admin/teams/$tid/$uid.login.

	    $tids[] = $tid;

	    if ( file_exists ( "$epm_data/$fi" ) )
		rename ( "$epm_data/$fi",
			 "$epm_data/$fl" );
	    else
	    {
		$r = @file_put_contents
		    ( "$epm_data/$fl", '',
		      FILE_APPEND );
		if ( $r === false )
		    ERROR ( "cannot write $fl" );
	    }
	}
	write_tids ( $tids, $uid, 'member' );
    }

    $STIME = $_SESSION['EPM_TIME'];
    write_email ( [$uid, 0, $STIME], $email );
    return true;
}

// Return UID associated with email, or false if none.
//
function uid_of_email ( $email )
{
    global $epm_data;

    $f = "admin/email/" . rawurlencode ( $email );
    $c = @ file_get_contents ( "$epm_data/$f" );
    if ( $c === false ) return false;
    $c = trim ( $c );
    if ( $c == '' ) return false;
    $items = explode ( ' ', $c );
    $r = $items[0];
    if ( $r == '-' ) return false;
    return $r;
}

// Compute the $map:
//
//	EMAIL-ADDDESS => UID
//
// from the admin/email directory.
//
// You may want to lock admin before calling this.
//
function email_map ( & $map )
{
    global $epm_data, $epm_name, $epm_name_re;

    $map = [];

    $d = 'admin/email';
    $efiles = @scandir ( "$epm_data/$d" );
    if ( $efiles === false )
	ERROR ( "cannot open $d" );

    foreach ( $efiles as $efile )
    {
	if ( ! preg_match ( '/%40/', $efile ) )
	    continue;
	    // %40 is the rawurlencode of @
	$f = "admin/email/$efile";
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c === false )
	{
	    WARN ( "cannot read $f" );
	    continue;
	}
	$c = trim ( $c );
	$items = explode ( ' ', $c );

	if ( count ( $items ) < 1 )
	{
	    WARN ( "empty $f" );
	    continue;
	}
	elseif ( $items[0] == '-' )
	    continue;
	elseif ( ! preg_match
		      ( $epm_name_re, $items[0] ) )
	{
	    WARN ( "bad value $c in $f" );
	    continue;
	}

	$email = rawurldecode ( $efile );

	$map[$email] = $items[0];
    }
}

// Return the HTML for a $list of emails.  Each email
// in $list becomes a <tr><td>...</td></tr> segment in
// the returned HTML.  If $email is not NULL, its
// segment is marked as '(used for current login)'.
// If $act == 'strip', the non-domain part of each email
// is rendered as `...'.  If $act == 'delete', a
// `Delete' button is added after each email != $email,
// with name='delete-email' and value='email'.
//
function emails_to_rows
	( $list, $email = NULL, $act = NULL )
{
    $r = '';
    foreach ( $list as $item )
    {
        if ( $act == 'strip' )
	    $item = preg_replace
	        ( '/^[^@]*@/', '...@', $item );
	$r .= '<tr><td>' . htmlspecialchars ( $item );
        if ( $item == $email )
	    $r .= ' (used for current login)';
	elseif ( $act == 'delete' )
	    $r .= " <button type='submit'"
	        . "         name='delete-email'"
	        . "         value='$item'>"
	        . "         Delete</button>";
	$r .= '</td></tr>';
    }
    return $r;
}

// Split member into [uid,email] where either may be ''.
//
function split_member ( $member )
{
    $pos = strpos ( $member, '(' );
    if ( $pos === false ) return [$member,''];
    $uid = substr ( $member, 0, $pos );
    $email = substr ( $member, $pos + 1, -1 );
    return [$uid,$email];
}

// Return the HTML for a $list of members, where each
// member is a string (UID(EMAIL), UID, or (EMAIL)).
// Each Member in $list becomes a <tr><td>...</td></tr>
// segment in the returned HTML.  If $act == 'delete',
// a `Delete' button is added after each member with
// name='delete-member' and value='C' where C is the
// index of the row (0, 1, 2, ... ).
//
function members_to_rows ( $list, $act = NULL )
{
    $r = '';
    $C = 0;
    foreach ( $list as $mem )
    {
	$r .= "<tr><td>$mem";
	if ( $act == 'delete' )
	    $r .= " <button type='submit'"
	        . "         name='delete-member'"
	        . "         value='$C'>"
	        . "         Delete</button>";
	$r .= '</td></tr>';
	++ $C;
    }
    return $r;
}

// Ditto for guests but `delete-guest' is used instead
// of `delete-member'.
//
function guests_to_rows ( $list, $act = NULL )
{
    $r = '';
    $C = 0;
    foreach ( $list as $guest )
    {
	$r .= "<tr><td>$guest";
	if ( $act == 'delete' )
	    $r .= " <button type='submit'"
	        . "         name='delete-guest'"
	        . "         value='$C'>"
	        . "         Delete</button>";
	$r .= '</td></tr>';
	++ $C;
    }
    return $r;
}

// Read, check, and return json_decode of AID.info file.
// $type is 'user' or 'team'.  Errors are terminal.
// File must be readable.
//
function read_info ( $type, $aid )
{
    global $epm_data, $epm_info_fields;

    $f = "admin/{$type}s/$aid/$aid.info";
    $c = @file_get_contents ( "$epm_data/$f" );
    if ( $c === false )
	ERROR ( "cannot read $f" );
    $info = json_decode ( $c, true );
    if ( $info === NULL )
    {
	$m = json_last_error_msg();
	ERROR ( "cannot decode json in $f:" .
		PHP_EOL . "    $m" );
    }
    foreach ( $epm_info_fields[$type]
              as $key => $items )
    {
	if ( ! isset ( $info[$key] ) )
	{
	    if ( count ( $items ) > 0 )
		ERROR ( "$f has no $key" );
	    else
	        $info[$key] = [];
		// This allows emails, members, and
		// guests to be missing.
	}
    }

    return $info;
}

// Write JSON AID.info file.  Logs changes to +actions+
// files.  Writes nothing if there are no changes.
//
function write_info ( & $info )
{
    global $epm_data, $epm_time_format;

    if ( isset ( $info['uid'] ) )
    {
        $type = 'user';
        $aid = $info['uid'];
    }
    else
    {
        $type = 'team';
        $aid = $info['tid'];
    }

    $changes = '';
    $time = date ( $epm_time_format );
    $h = "$time $aid info";

    $f = "admin/{$type}s/$aid/$aid.info";
    $old = @file_get_contents ( "$epm_data/$f" );
    if ( $old === false )
    {
	foreach ( $info as $key => $value )
	{
	    if ( is_array ( $value ) )
		foreach ( $value as $item )
		    $changes .= "$h $key + $item"
			      . PHP_EOL;
	    else
		$changes .= "$h $key + $value"
			  . PHP_EOL;
	}
    }
    else
    {
	$old = json_decode ( $old, true );
	if ( $old == NULL )
	{
	    $m = json_last_error_msg();
	    ERROR ( "cannot decode json in $f:" .
		    PHP_EOL . "    $m" );
	}
	foreach ( $info as $key => $value )
	{
	    if ( is_array ( $value ) )
	    {
	        if ( ! isset ( $old[$key] ) )
		    $old[$key] = [];
		$adds = array_diff
		    ( $info[$key], $old[$key] );
		foreach ( $adds as $item )
		    $changes .= "$h $key + $item"
			      . PHP_EOL;
		$subs = array_diff
		    ( $old[$key], $info[$key] );
		foreach ( $subs as $item )
		    $changes .= "$h $key - $item"
			      . PHP_EOL;
	    }
	    elseif ( ! isset ( $old[$key] ) )
		$changes .= "$h $key + $value"
			  . PHP_EOL;
	    elseif ( $old[$key] != $info[$key] )
		$changes .= "$h $key = $value"
			  . PHP_EOL;
	}
	foreach ( $old as $key => $value )
	{
	    if ( isset ( $info[$key] ) ) continue;
	    if ( is_array ( $value ) )
		foreach ( $value as $item )
		    $changes .= "$h $key - $item"
			      . PHP_EOL;
	    else
		$changes .= "$h $key - $value"
			  . PHP_EOL;
	}
    }

    if ( $changes == '' ) return;

    $c = json_encode ( $info, JSON_PRETTY_PRINT );
    if ( $c === false )
	ERROR ( 'cannot json_encode $info' );
    $r = ATOMIC_WRITE ( "$epm_data/$f", $c );
    if ( $r === false )
	ERROR ( "cannot write $f" );

    $f = "admin/{$type}s/$aid/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $changes, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot append to $f" );
    $f = "admin/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $changes, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot append to $f" );
}

// Given an $info, return the HTML for the rows of a
// table that contains displays info.  The row labels
// are <th> and values are <td>.
//
// If $exclude is NOT NULL, all the rows are text
// <input> except those whose keys are in $exclude,
// which is a possibly empty list of keys.  Otherwise
// the rows are htmlspecialchars of their values.
//
function info_to_rows ( & $info, $exclude = NULL )
{
    global $epm_info_fields;

    if ( isset ( $info['manager'] ) )
        $fields = $epm_info_fields['team'];
    else
        $fields = $epm_info_fields['user'];

    $r = '';
    foreach ( $fields as $key => $items )
    {
        if ( $items == [] ) continue;
	list ( $label, $min_length, $max_length,
	               $placeholder, $title ) = $items;
	if ( isset ( $info[$key] ) )
	    $value = $info[$key];
	else
	    $value = '';
	$r .= "<tr><th>$label:</th><td>";
	if ( isset ( $exclude )
	     &&
	     ! in_array ( $key, $exclude, true ) )
	    $r .= "<input type='text'"
	        . " name='$key'"
	        . " value='$value'"
	        . " size='$max_length'"
	        . " placeholder='$placeholder'"
	        . " title='$title'>";
	else
	    $r .= htmlspecialchars ( $value );
	$r .= "</td></tr>";
    }
    return $r;
}

// Copy non-array info values of given $type from
// $src to $des.  Ignore values not set in $src.
//
function copy_info ( $type, & $src, & $des )
{
    global $epm_info_fields;

    $fields = $epm_info_fields[$type];
    foreach ( $fields as $key => $items )
    {
        if ( $items == [] ) continue;
        if ( ! isset ( $src[$key] ) )
	    continue;
	$des[$key] = $src[$key];
    }
}

// Trim the non-array values of $info and check
// that they are not empty and have legal lengths.
// Errors cause messages to $errors.  $info is
// of given $type.
//
function scrub_info ( $type, & $info, & $errors )
{
    global $epm_info_fields;

    $fields = $epm_info_fields[$type];
    foreach ( $fields as $key => $items )
    {
        if ( $items == [] ) continue;
	list ( $label, $min_length, $max_length,
	               $placeholder, $title ) = $items;
	$label = strtolower ( $label );
	if ( ! isset ( $info[$key] ) )
	{
	    $errors[] = "you must set $label";
	    continue;
	}
	$value = $info[$key];
	$value = trim ( $value );
	$info[$key] = $value;
	if ( $value == '' )
	{
	    $errors[] = "you must set $label";
	    continue;
	}
	$length = strlen ( utf8_decode ( $value ) );
	     // Note, grapheme_strlen is not available
	     // because we do not assume intl extension.
	if ( $length < $min_length )
	    $errors[] = "$label is too short"
	              . " (< $min_length characters)";
	if ( $length > $max_length )
	    $errors[] = "$label is too long"
	              . " (> $max_length characters)";
    }
}




?>
