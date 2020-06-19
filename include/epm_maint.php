<?php

// File:    epm_maint.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Jun 18 21:30:40 EDT 2020

// Functions used to maintain directories and their
// contents.  Used by $epm_home/bin programs and
// by $epm_home/page/login.php.
//
// WARNING: Error messages ARE allowed to contain
//          $epm_data or $epm_home.
//
// For all of this code, errors call the ERROR function
// and then continue processing with suitable omissions.
//
// To include this code, be sure the following are
// defined.

if ( ! isset ( $epm_web ) )
    exit ( 'ACCESS ERROR: $epm_web not set' );
if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $epm_home ) )
    exit ( 'ACCESS ERROR: $epm_home not set' );

// Function to set the modes of the files and subdirec-
// tories in a directory, recursively.  Files get the
// mode 0660, except those with names matching $re
// which get the mode 0771.  Directories get the mode
// 0770.  Links are ignored.
//
// If a file mode is being changed, a message to that
// effect is written to the standard output.
//
// If $dryrun is true, no actual mode changes are done
// but the messages are written anyway.
//
function set_modes ( $d, $dryrun = false, $re = NULL )
{
    global $epm_data;

    $files = @scandir ( "$epm_data/$d" );
    if ( $files === false )
    {
	ERROR ( "cannot read $d" );
	return;
    }
    foreach ( $files as $fname )
    {
	$f = "$d/$fname";
        if ( is_link ( "$epm_data/$f" ) )
	    continue;
	if ( is_dir ( "$epm_data/$f" ) )
	{
	    set_modes ( $f, $dryrun, $re );
	    $m = 0770;
	}
	elseif ( ! isset ( $re ) )
	    $m = 0660;
	elseif ( preg_match ( $re, $fname ) )
	    $m = $dmode;
	else
	    $m = 0660;

	$mode = @fileperms ( "$epm_data/$f" );
	if ( $mode === false )
	{
	    ERROR ( "cannot read mode of $f" );
	    continue;
	}
	$mode = $mode & 0777;
	if ( $mode == $m ) continue;

	$action = sprintf ( "change mode of %s from" .
	                    " %04o to %04o",
			    $f, $mode, $m );
	echo ( $action . PHP_EOL );
	if ( $dryrun ) continue;
	$r = @chmod ( "$epm_data/$f", $m );
	if ( $r === false )
	    ERROR ( "cannot $action" );
    }
}

// Function to init a project problem directory.
//
// Sets directory mode of projects, projects/$project,
// and projects/$project/$problem to 0771.
//
// Makes sure that generate_PPPP exists.  If it does
// not and there is a +solutions+/generate_PPPP.EEE,
// for .EEE being either .c or .cc, compile the later to
// make generate_PPPP.  Otherwise make a link to
// ../../../default/epm_default_generate.
//
// Then do ditto for filter_PPPP.
//
// Then do ditto for monitor_PPPP and display_PPPP,
// but do not make any link if no source file.
//
// Lastly call set_mode for the project/$project/
// $problem directory with an $re that will set the
// mode of any SPECIAL-PROBLEM file to 0771.
//
// If an action is being take, a message describing the
// action is written to the standard output.
//
// If $dryrun is true, no actual actions are executed
// but the messages are written anyway.
//
function init_problem
    ( $project, $problem, $dryrun = false )
{
    global $epm_data, $epm_specials;

    $d1 = "projects";
    $d2 = "$d1/$project";
    $d3 = "$d2/$problem";
    $d4 = "$d3/+solutions+";
    if ( ! is_dir ( "$epm_data/$d3" ) )
    {
        ERROR ( "$d3 is not a directory" );
	return;
    }

    foreach ( [$d1,$d2,$d3] as $d )
    {
	$mode = @fileperms ( "$epm_data/$d" );
	if ( $mode === false )
	{
	    ERROR ( "cannot read mode of $d" );
	    continue;
	}
	$mode = $mode & 0777;
	if ( $mode == $m ) continue;

	$action = sprintf ( "change mode of %s from" .
	                    " %04o to %04o",
			    $d, $mode, $m );
	echo ( $action . PHP_EOL );
	if ( $dryrun ) continue;
	$r = @chmod ( "$epm_data/$d", $m );
	if ( $r === false )
	    ERROR ( "cannot $action" );
    }

    $defaults = ['generate', 'filter'];
    foreach ( $epm_special as $spec )
    {
	$f = "$d3/$spec-$problem";
	if ( is_link ( "$epm_data/$f" ) )
	    continue;
	if ( file_exists ( "$epm_data/$f" ) )
	    continue;
	$gc = "$d4/$spec-$problem.c";
	$gcc = "$d4/$spec-$problem.cc";
	$command = NULL;
	$action = NULL;
	if ( file_exists ( "$epm_data/$gcc" ) )
	{
	    $action = "compile $f from C++";
	    $command = "g++ -o $epm_data/$f " .
		              "$epm_data/$gcc";
	}
	elseif ( file_exists ( "$epm_data/$gc" ) )
	{
	    $action = "compile $f from C";
	    $command = "gcc -o $epm_data/$f " .
		              "$epm_data/$gcc";
	}
	else
	{
	    if ( in_array ( $spec, $defaults, true ) )
	    {
		$action = "symbolically link $f to"
		        . " default" );
		$command = "ln -s ../../../default/"
		         . "epm_default_$spec"
			 . " $epm_data/$f";
	    }
	    else
	        ERROR ( "cannot make $f" );
	}

	if ( ! isset ( $action ) ) continue;
	echo ( $action . PHP_EOL );
	if ( $dryrun ) continue;

	passthru ( $command, $r );
	if ( $r != 0 )
	    ERROR ( "could not $action" );
    }

    // Regular expression that matches names of the form
    // SPECIAL-PROBLEM.
    //
    $spec_re = implode ( "|", $epm_specials );
    $spec_re = "/^($spec_re)-$problem\$/";

    set_modes ( $d3, $dryrun, $spec_re, 0771 );
}
