<?php

// File:	epm_view.php
// Author:	Robert L Walton <walton@acm.org>
// Date:	Wed Jun  8 13:59:41 EDT 2022

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
//	[TIME, AID, TYPE, ...]
//
// or more specifically, one of:
//
//	[TIME, AID, 'info', KEY, OP, VALUE]
//	[TIME, AID, 'contest', CONTESTNAME, KEY, ...]
//	[TIME, AID, 'push', PROJECT, PROBLEM]
//	[TIME, AID, 'pull', PROJECT, PROBLEM]
//	[TIME, AID, 'submit', PROJECT, PROBLEM, RUNBASE,
//			      STIME, SCORE...]
//	[TIME, AID, 'create-problem', '-', PROBLEM]
//	[TIME, AID, 'delete-problem', '-', PROBLEM]
//	[TIME, AID, 'update-priv', '-', '-']
//	[TIME, AID, 'update-priv', PROJECT, '-']
//	[TIME, AID, 'update-priv', PROJECT, PROBLEM]
//	[TIME, AID, 'block', PROJECT, '-']
//	[TIME, AID, 'block', PROJECT, PROBLEM]
//	[TIME, AID, 'unblock', PROJECT, '-']
//	[TIME, AID, 'unblock', PROJECT, PROBLEM]
//	[TIME, AID, 'create-list', '-', NAME]
//	[TIME, AID, 'update-list', '-', NAME]
//	[TIME, AID, 'publish-copy', PROJECT, NAME]
//	[TIME, AID, 'publish-move', PROJECT, NAME]
//	[TIME, AID, 'unpublish-copy', PROJECT, NAME]
//	[TIME, AID, 'unpublish-move', PROJECT, NAME]
//	[TIME, AID, 'delete-list', '-', NAME]
//	[TIME, AID, 'download', '-', PROBLEM]
//	[TIME, AID, 'download', PROJECT, '-']
//	[TIME, AID, 'download', PROJECT, PROBLEM]
//	[TIME, AID, 'copy-from', PROJECT1, PROBLEM,
//                               PROJECT2, PROBLEM]
//	[TIME, AID, 'copy-from', PROJECT1, '-',
//                               PROJECT2, '-']
//	[TIME, AID, 'update-from', PROJECT1, PROBLEM,
//                                 PROJECT2, PROBLEM]
//	[TIME, AID, 'update-from', PROJECT1, '-',
//                                 PROJECT2, '-']
//
// For 'info':
//
//     KEY is the AID.info element changed
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
// Each row has class 'row' and data-keys equal to
// the list of ':' separated keys of the row (see
// Help Page for documentation of keys).
//
function actions_to_rows ( $actions )
{
    global $view_cache, $epm_data;
    $r = '';
    foreach ( $actions as $items )
    {
        $time = $items[0];
        $account = $items[1];
        $type = $items[2];
	$type = explode ( '-', $type );
	$type[] = '';
	$a = NULL;
	$k = [$account];
	if ( $type[0] == 'info' )
	{
	    $key = $items[3];
	    $op = $items[4];
	    $value = implode
	        ( ' ', array_slice ( $items, 5 ) );
	    if ( $key == 'email' )
		$value = preg_replace
		    ( '/^[^@]+@/', '...@', $value );
	    $k = array_merge
	        ( $k, preg_split ( '/[^A-Za-z0-9]+/',
		                   $value ) );

	    if ( $key == 'email' )
	    {
	        $key = 'emails';
	        array_push ( $k, 'email', 'emails' );
	    }
	    elseif ( $key == 'full_name' )
	    {
	        $key = 'full name';
	        array_push ( $k, 'full', 'name' );
	    }
	    elseif ( $key == 'members' )
	        array_push ( $k, 'member', 'members' );
	    elseif ( $key == 'guests' )
	        array_push ( $k, 'guest', 'guests' );
	    else
	        $k[] = $key;

	    if ( $op == '=' )
	    {
		$a = "set $key to $value";
		$k[] = 'set';
	    }
	    elseif ( $op == '+' )
	    {
		$a = "add $value to $key";
		$k[] = 'add';
	    }
	    elseif ( $op == '-' )
	    {
		$a = "remove $value from $key";
		$k[] = 'remove';
	    }
	    else
	    {
		$a = "unknown operation $op";
		$k[] = 'unknown';
		$k[] = 'operation';
	    }
	}
	elseif ( $items[3] != '-' )
	{
	    $project = $items[3];
	    $d = "$epm_data/projects/$project";
	    if ( is_dir ( $d ) )
	    {
		if ( isset ( $view_cache[$project] ) )
		    $v = $view_cache[$project];
		else
		    $v = view_priv ( $project );
		if ( $v != '+' ) continue;
	    }
	}

	if ( $type[0] == 'contest' )
	{
	    // Check for view_priv was made above.

	    $contestname = $items[3];
	    $key = $items[4];
	    $k[] = 'contest';
	    $k[] = $contestname;
	    $k = array_merge
	        ( $k, preg_split ( '/[^A-Za-z0-9]+/',
		                   $key ) );
	    $a = "for contest $contestname";

	    if ( $key == 'create-contest' )
	        $a = "created contest $contestname";
	    elseif ( $key == 'add-account' )
	    {
		$k[] = $items[5];
	        $a = "$a added account {$items[5]}";
	    }
	    elseif ( $key == 'email' )
	    {
		$k[] = $items[5];
		$k[] = $items[7];
	        $a = "$a set email for account"
		   . " {$items[5]} to {$items[7]}";
	    }
	    elseif ( $key == 'role' )
	    {
		$k[] = $items[5];
		$op = $items[6];
		$k[] = $items[7];
		if ( $op == '+' )
		    $a = "$a added {$items[7]} role"
		       . " to account {$items[5]}";
		else
		    $a = "$a removed {$items[7]} role"
		       . " from account {$items[5]}";
	    }
	    elseif ( $key == 'set' )
	    {
	        $name = $items[5];
		$value = implode
		    ( ' ', array_slice ( $items, 7 ) );
		$k = array_merge
		    ( $k,
		      preg_split ( '/[^A-Za-z0-9]+/',
				   $name ) );
		$k = array_merge
		    ( $k,
		      preg_split ( '/[^A-Za-z0-9]+/',
				   $value ) );
		$a = "$a set $name = $value";
	    }
	    elseif ( $key == 'can-see' )
	    {
	        $role1 = $items[5];
	        $op    = $items[6];
	        $role2 = $items[7];
		$k[] = $role1;
		$k[] = $role2;
		if ( $op == '+' )
		    $a = "$a allowing $role1 to see"
		       . " $role2 emails";
		else
		    $a = "$a preventing $role1 from"
		       . " seeing $role2 emails";
	    }
	    elseif ( $key == 'delete-account' )
	    {
		$k[] = $item[5];
	        $a = "$a deleted account {$items[5]}";
	    }
	    else
	        $a = "$a unknown key $key";
	}

	// Action items from here on cannot be viewed
	// unless $item[3] is '-' or a PROJECT with
	// view privilege.

	if ( isset ( $a ) )
	    /* Do Nothing */;
	elseif ( $type[1] == 'problem' )
	{
	    $a = "{$type[0]} problem {$items[4]}";
	    array_push
	        ( $k, $type[0], $items[4], 'problem' );
	}
	elseif ( $type[1] == 'list' )
	{
	    if ( $type[0] == 'copy' )
	    {
	        $m = $items[3];
	        $n = $items[4];
		if ( $m == '-' ) $m = 'Your';
		else $k[] = $m;
		if ( $n == '-' ) $n = 'Problems';
		else $k[] = $n;
		$a = "create Your list {$items[6]}" .
		     " as copy of $m $n";
		array_push
		    ( $k, 'copy', $items[4],
		          'list', 'create' );
	    }
	    else
	    {
		$a = "{$type[0]} list {$items[4]}";
		array_push
		    ( $k, $type[0], $items[4], 'list' );
	    }
	}
	elseif ( $type[0] == 'publish' )
	{
	    array_push
		( $k, $type[0], $items[3], $items[4],
		      'list' );
	    $a = "publish Your {$items[4]} list" .
		 " to project ${items[3]}";
	    if ( $type[1] == 'move' )
	        $a .= " and delete Your {$items[4]}";
	    else
	        $a .= " while keeping Your {$items[4]}";
	}
	elseif ( $type[0] == 'unpublish' )
	{
	    array_push
		( $k, $type[0], $items[3], $items[4],
		      'list', 'publish' );
	    $a = "copy {$items[4]} list in project" .
	         " {$items[3]} to Your {$items[4]}" .
		 " list";
	    if ( $type[1] == 'move' )
		$a .= " and unpublish (delete)" .
		      " {$items[4]} list in project" .
		      " ${items[3]}";
	}
	else if ( $type[1] == 'priv' )
	{
	    array_push ( $k, $type[0], 'privileges' );
	    $m = $items[3];
	    $n = $items[4];
	    if ( $m == '-' )
	    {
	        $m = 'root';
		$n = '';
	    }
	    else
	    {
		if ( $n == '-' ) $n = 'project';
		else $k[] = $n;
	    }
	    $k[] = $m;
	    if ( $n != '' ) $n = " $n";
	    $a = "{$type[0]} $m$n privileges";
	}
	else if ( $type[0] == 'block'
	          ||
		  $type[0] == 'unblock' )
	{
	    array_push ( $k, 'block', 'unblock',
	                     $items[3] );
	    $n = $items[4];
	    if ( $n == '-' ) $n = 'project';
	    else $k[] = $n;
	    $a = "{$type[0]} {$items[3]} $n privileges";
	}
	else if ( $type[0] == 'download' )
	{
	    $k[] = 'download';
	    $m = $items[3];
	    $n = $items[4];
	    if ( $m == '-' ) $m = 'Your';
	    else $k[] = $m;
	    if ( $n == '-' ) $n = 'project';
	    else $k[] = $n;
	    $a = "{$type[0]} $m $n";
	}
	else if ( $type[1] == 'from' )
	{
	    // Keys may be duplicated.
	    //
	    array_push
	        ( $k, $type[0], $items[3], $items[4] );
	    $m = $items[4];
	    $n = $items[6];
	    if ( $m == '-' ) $m = 'project';
	    else $k[] = $m;
	    if ( $n == '-' ) $n = 'project';
	    else $k[] = $n;
	    $a = "{$type[0]} {$items[3]} $m" .
	         " from {$items[5]} $n";
	}
	else if ( $type[0] == 'pull' )
	{
	    $a = "pull {$items[4]} from {$items[3]}";
	    array_push
	        ( $k, 'pull', $items[3], $items[4] );
	}
	else if ( $type[0] == 'push' )
	{
	    $a = "push {$items[4]} to {$items[3]}";
	    array_push
	        ( $k, 'push', $items[3], $items[4] );
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
	    array_push
	        ( $k, 'submit', $items[3], $items[4],
		  $runbase );
	    $k = array_merge
	        ( $k, array_slice ( $items, 7 ) );
	}
	else
	{
	    $a = "unknown action type {$items[2]}";
	    array_push
	        ( $k, 'unknown', $items[2] );
	    $k = array_merge ( $k, $type );
	}

	$k = implode ( ':', $k );

	$r .= "<tr class='row' data-keys='$k'>"
	    . "<td>$time</td>"
	    . "<td>$account</td>"
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
