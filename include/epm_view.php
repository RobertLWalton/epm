<?php

// File:	epm_view.php
// Author:	Robert L Walton <walton@acm.org>
// Date:	Sun Jul  5 14:03:06 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain;
// they make no warranty and accept no liability
// for EPM.

// Functions for viewing actions, etc.

// $view_cache contains
//
//		PROJECT => '+'
// or
//		PROJECT => '-'
//
// for all PROJECTs for which `view' privilege has
// been determined.  The function maintains the
// cache and returns its value for $project.
//
// The standard usage is
//
//	if ( isset ( $view_cache[$project] ) )
//	     $r = $view_cache[$project];
//	else $r = view_priv ( $project );
//
// where $r gets the value '+' or '-'.
//
$view_cache = [];
function view_priv ( $project )
{
    global $view_cache;
    if ( isset ( $view_cache[$project] ) )
	return $view_cache[$project];
    project_priv_map ( $map, $project );
    if ( isset ( $map['view'] ) )
	 $r = $map['view'];
    else $r = '-';
    $view_cache[$project] = $r;
    return $r;
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
	else if ( $type == 'update' )
	{
	    $updated = implode
	        ( ' ', array_slice ( $items, 4 ) );
	    $a = "update {$items[3]} $updated";
	}
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

	$r .= "<tr class='$type'>"
	    . "<td>$time</td>"
	    . "<td>$user</td>"
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

?>
