<?php

// File:    epm_user.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sun May 24 18:53:26 EDT 2020

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
