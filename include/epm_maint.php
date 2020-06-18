<?php

// File:    epm_maint.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Jun 18 09:17:11 EDT 2020

// Functions used to maintain directories and their
// contents.  Used by $epm_home/bin programs and
// by $epm_home/page/login.php.
//
// WARNING: Error messages ARE allowed to contain
//          $epm_data or $epm_home.
//
// To include this code, be sure the following are
// defined.
//
// For all of this code, errors call the ERROR function
// and then continue processing with suitable omissions.

if ( ! isset ( $epm_web ) )
    exit ( 'ACCESS ERROR: $epm_web not set' );
if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $epm_home ) )
    exit ( 'ACCESS ERROR: $epm_home not set' );

// Function to set the modes of the files and
// subdirectories in a directory, recursively.
// Most get the mode 0660.  Directories and
// files with names of the form SPECIAL-PROBLEM
// get the mode 0771.
//
// If a file mode is being changed, a message to that
// effect is written to the standard output.
//
// If $dryrun is true, no actual mode changes are done
// but the messages are written anyway.
//
function set_modes ( $d, $dryrun = false )
{
    global $epm_data, $epm_specials;

    $spec_re = implode ( "|", $epm_specials );
    $spec_re = "/^($spec_re)-[^\.]+\$/";

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
	    set_modes ( $f, $dryrun );
	    $m = 0771;
	}
	elseif ( preg_match ( $spec_re, $fname ) )
	    $m = 0771;
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
	echo ( "set mode $f = $m" );
	if ( $dryrun ) continue;
	$r = @chmod ( "$epm_data/$f", $m );
	if ( $r === false )
	    ERROR
		( "cannot change mode of $f to $m" );
    }
}

// Function to init a project problem directory.
//
// Sets directory mode of projects, projects/$project,
// and projects/$project/$problem to 0771.  Sets
// all other modes of stuff inside problem directory
// to o-rwx, with execeptions below.
//
// Makes sure that generate_PPPP exists.  If it does
// not and there is a +solutions+/generate_PPPP.EEE,
// compile the later to make generate_PPPP.  Otherwise
// make a link to ../../../default/epm_default_generate.
// If it is a file, set its mode to 0771.
//
// Then do ditto for filter_PPPP.
//
// Then do ditto for monitor_PPPP and display_PPPP,
// but do not make any link if no source file.
//
// Outputs to the standard output a description of
// the changes it is making (does not list mode
// changes that would not actually change the mode,
// for example).
//
// If $dryrun is true, does everything but makes no
// actual changes.
//
function init_problem
    ( $project, $problem, $dryrun = false )
{
    global $epm_data, $epm_special;

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
	if ( $mode == 0771 ) continue;
	echo ( "set mode of $d to 0771" );
	if ( $dryrun ) continue;
	$r = @chmod ( "$epm_data/$d", 0771 );
	if ( $r === false )
	    ERROR
	        ( "cannot change mode of $d to 0771" );
    }

    $defaults = ['generate', 'filter'];
    foreach ( $epm_special as $spec )
    {
        $is_defaultable =
	    in_array ( $defaults, $spec, true );
	$f = "$d3/$spec-$problem";
	if ( is_link ( "$epm_data/$f" ) )
	    continue;
	if ( file_exists ( "$epm_data/$f" ) )
	    continue;
	$g = "$d3/$spec-$problem";
	$gc = "$d4/$spec-$problem.c";
	$gcc = "$d4/$spec-$problem.cc";
	$command = NULL;
	$action = NULL;
	if ( file_exists ( "$epm_data/$gcc" ) )
	{
	    $action = "compile $g from $gcc";
	    $command = "g++ -o $epm_data/$g " .
		              "$epm_data/$gcc";
	}
	elseif ( file_exists ( "$epm_data/$gc" ) )
	{
	    $action = "compile $g from $gc";
	    $command = "gcc -o $epm_data/$g " .
		              "$epm_data/$gcc";
	}
	else
	{
	    if ( in_array ( $spec, $defaults, true ) )
	    {
		$action = "symbolically link $g to"
		        . " default" );
		$command = "ln -s ../../../default/"
		         . "epm_default_$spec"
			 . " $epm_data/$g";
	    }
	    else
	        ERROR ( "cannot make $g" );
	}

	if ( ! isset ( $action ) ) continue;
	echo ( $action );
	if ( $dryrun ) continue;

	passthru ( $command, $r );
	if ( $r != 0 )
	    ERROR ( "could not $action" );
    }

    set_modes ( $d3, $dryrun );
}
