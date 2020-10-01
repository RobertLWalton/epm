<?php

// File:	epm_view.php
// Author:	Robert L Walton <walton@acm.org>
// Date:	Wed Sep 30 16:24:17 EDT 2020

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
//	[TIME, UID, TYPE, ...]
//
// or more specifically, one of:
//
//	[TIME, UID, 'info', KEY, OP, VALUE]
//	[TIME, UID, 'push', PROJECT, PROBLEM]
//	[TIME, UID, 'pull', PROJECT, PROBLEM]
//	[TIME, UID, 'submit', PROJECT, PROBLEM, RUNBASE,
//			      STIME, SCORE...]
//	[TIME, UID, 'create-problem', '-', PROBLEM]
//	[TIME, UID, 'delete-problem', '-', PROBLEM]
//	[TIME, UID, 'update-priv', PROJECT, '-']
//	[TIME, UID, 'update-priv', PROJECT, PROBLEM]
//	[TIME, UID, 'create-list', '-', NAME]
//	[TIME, UID, 'update-list', '-', NAME]
//	[TIME, UID, 'publish-list', '-', NAME]
//	[TIME, UID, 'unpublish-list', '-', NAME]
//	[TIME, UID, 'delete-list', '-', NAME]
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
//              single spaces
//
// Give each row class='TYPE' if TYPE is in $types
// list, or class='other' if TYPE is not in $types.
//
function actions_to_rows ( $actions, $types )
{
    global $view_cache;
    $r = '';
    foreach ( $actions as $items )
    {
        $time = $items[0];
        $user = $items[1];
        $type = $items[2];
	$type = explode ( '-', $type );
	$type[] = '';
	$a = NULL;
	if ( $type[0] == 'info' )
	{
	    $key = $items[3];
	    $op = $items[4];
	    $value = implode
	        ( ' ', array_slice ( $items, 5 ) );
	    $k = [$key];
	    if ( $key == 'email' )
		$value = preg_replace
		    ( '/^[^@]+@/', '...@', $value );
	    if ( $key == 'email' )
	    {
	        $key = 'emails';
		$k = ['email','emails'];
	    }
	    elseif ( $key == 'full_name' )
	    {
	        $key = 'full name';
		$k = ['full','name'];
	    }

	    if ( $op == '=' )
		$a = "set $key to $value";
	    elseif ( $op == '+' )
		$a = "add $value to $key";
	    elseif ( $op == '-' )
		$a = "remove $value from $key";
	    else
		$a = "unknown operation $op";
	    $k[] = 'info';
	}
	elseif ( $type[1] == 'problem' )
	{
	    $a = "{$type[0]} problem {$items[4]}";
	    $k = [$type[0],$items[4],'problem'];
	}
	elseif ( $type[1] == 'list' )
	{
	    $a = "{$type[0]} list {$items[4]}";
	    $k = [$type[0],$items[4],'list'];
	}
	else
	{
	    $project = $items[3];
	    if ( isset ( $view_cache[$project] ) )
	        $v = $view_cache[$project];
	    else
	        $v = view_priv ( $project );
	    if ( $v != '+' ) continue;
	}

	if ( isset ( $a ) )
	    /* Do Nothing */;
	else if ( $type[1] == 'priv' )
	{
	    $k = [$type[0],$items[3],'privileges'];
	    $n = $items[4];
	    if ( $n == '' ) $n = 'project';
	    else $k[] = $items[4];
	    $a = "{$type[0]} {$items[3]} $n privileges";
	}
	else if ( $type[0] == 'pull' )
	{
	    $a = "pull {$items[4]} from {$items[3]}";
	    $k = ['pull',$items[3],$items[4]];
	}
	else if ( $type[0] == 'push' )
	{
	    $a = "push {$items[4]} to {$items[3]}";
	    $k = ['push',$items[3],$items[4]];
	}
	else if ( $type[0] == 'submit' )
	{
	    $project = $items[3];
	    $problem = $items[4];
	    $runbase = $items[5];
	    $cpu_time = $items[6];
	    $score = implode
	        ( ' ', array_slice ( $items, 7 ) );
	    $a = "submit $runbase.run in $project"
	       . "  $cpu_time $score";
	    $k = ['submit',$items[3],$items[4]];
	}
	else
	{
	    $a = "unknown action type {$items[2]}";
	    $k = ['unknown',$items[2]];
	}

	$k = implode ( ':', $k );

	$r .= "<tr data-keys='$k'>"
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
	$r[] = explode ( ' ', $line );
    }
    return array_reverse ( $r );
}

?>
