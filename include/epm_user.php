<?php

// File:    epm_user.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Jun 13 13:03:16 EDT 2020

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
	if ( count ( $items ) < 1
	     ||
	     ! preg_match
		   ( $epm_name_re, $items[0] ) )
	{
	    WARN ( "bad value $c in $f" );
	    continue;
	}

	$email = rawurldecode ( $efile );

	$map[$email] = $items[0];
    }
}

// Read, check, and return json_decode of UID.info file.
// Errors are terminal.  File must be readable.
//
function read_uid_info ( $uid )
{
    global $epm_data;

    $f = "admin/users/$uid/$uid.info";
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
    foreach ( ['uid',
               'emails',
	       'full_name',
	       'organization',
	       'location'] as $key )
    {
	if ( ! isset ( $info[$key] ) )
	    ERROR ( "$f has no $key" );
    }

    return $info;
}

// Return the HTML for a $list of emails.  Each email
// in $list becomes a <pre>...</pre> segment in the
// returned HTML.  If $email is not NULL, its segment
// is marked as '(used for current login)'.  Segments
// are separated by <br>.
//
function emails_to_lines ( $list, $email = NULL )
{
    $r = [];
    foreach ( $list as $item )
    {
	$line = '<pre>' . htmlspecialchars ( $item )
	      . '</pre>';
        if ( $item == $email )
	    $line .= ' (used for current login)';
	$r[] = $line;
    }
    return implode ( '<br>', $r );
}

// Given user $info from json_decode, return the HTML
// for the rows of a table that contains that info.
// The row labels are <th> and values are <td>.
//
function user_info_to_rows ( $info )
{
    $uid = $info['uid'];
    $hfull_name = htmlspecialchars
        ( $info['full_name'] );
    $horganization = htmlspecialchars
        ( $info['organization'] );
    $hlocation = htmlspecialchars
        ( $info['location'] );
    return <<<EOT
    <tr><th>User ID:</th>
	<td>$uid</td></tr>
    <tr><th>Full Name:</th>
	<td>$hfull_name</td></tr>
    <tr><th>Organization:</th>
	<td>$horganization</td></tr>
    <tr><th>Location:</th>
	<td>$hlocation</td></tr>
EOT;
}

// Given a list of action items, return the HTML for one
// table row per line.  Each action item has one of the
// format:
//
//	[TIME, UID, 'info', KEY, OP, VALUE]
//	[TIME, UID, 'push', PROJECT, PROBLEM]
//	[TIME, UID, 'pull', PROJECT, PROBLEM]
//	[TIME, UID, 'create', UID, PROBLEM]
//	[TIME, UID, 'submit', PROJECT, RUNBASE,
//			      STIME, SCORE...]
//
// For 'info':
//
//     KEY is the UID.info element changed
//     OP is '=' to reset the element to VALUE,
//           '+' to add VALUE to the element list,
//           '-' to subtract VALUE from the element
//               list
//     If KEY is 'email', a VALUE of the form
//     XXX@YYY is replaced by ...@YYY.
//
// For 'submit':
//
//     RUNBASE.run is the run file name
//     STIME is the maximum solution CPU time for any
//           run file test case
//     SCORE... is the run score and may have multiple
//              elements that should be separated by
///             single spaces
//
function actions_to_rows ( $actions )
{
    $r = '';
    foreach ( $actions as $items )
    {
        $time = $items[0];
        $user = $items[1];
        $type = $items[2];
	if ( $type == 'info' )
	{
	    $key = $items[3];
	    $op = $items[4];
	    $value = $items[5];
	    if ( $key == 'email' )
		$value = preg_replace
		    ( '/^[^@]+@/', '...@', $value );
	    if ( $key == 'email' ) $key = 'emails';
	    elseif ( $key == 'full_name' )
	        $key = 'full name';
	    if ( $op == '=' )
		$a = "set $key to $value";
	    elseif ( $op == '+' )
		$a = "add $value to $key";
	    elseif ( $op == '-' )
		$a = "remove $value from $key";
	    else
		$a = "unknown operation $op";
	}
	else if ( $type == 'pull' )
	    $a = "pull {$items[4]} from {$items[3]}";
	else if ( $type == 'push' )
	    $a = "push {$items[4]} to {$items[3]}";
	else if ( $type == 'create' )
	    $a = "created her/his own problem"
	       . " {$items[3]}";
	else if ( $type == 'submit' )
	{
	    $project = $items[3];
	    $runbase = $items[4];
	    $cpu_time = $items[5];
	    $score = implode
	        ( ' ', array_slice ( $items, 6 ) );
	    $a = "submit $runbase.run in $project"
	       . "  $cpu_time $score";
	}
	else
	    $a = "unknown action type $type";

	$r .= "<tr><td>$time</td><td>$user</td>"
	    . "<td>$a</td></tr>";
    }
    return $r;
}

// Read file and turn its lines into action items.
// Return a list of the action items, most recent
// first (i.e., reverse order of file lines).  Return
// [] if file cannot be read.  Filename is relative to
// $epm_data.
//
function read_actions ( $fname )
{
    global $epm_data;

    $r = [];
    $c = @file_get_contents ( "$epm_data/$fname" );
    if ( $c === false ) return $r;

    $lines = explode ( "\n", $c );
    foreach ( $lines as $line )
    {
        $line = trim ( $line );
	if ( $line == '' ) continue;
	$line = preg_replace ( '/\s+/', ' ', $line );
	$line = explode ( ' ', $line );
	$value = implode
	    ( ' ', array_slice ( $line, 5 ) );
	array_splice ( $line, 5, 1000, [$value] );
	$r[] = $line;
    }
    return array_reverse ( $r );
}
