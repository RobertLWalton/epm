<?php

// File:    epm_maintenance.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Jul 11 12:16:55 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain;
// they make no warranty and accept no liability
// for EPM.

// Functions used to maintain directories and their
// contents.  Used by $epm_home/bin/epm.
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
if ( ! isset ( $epm_web_group ) )
    exit ( 'ACCESS ERROR: $epm_web_group not set' );

// Return the numeric ID of a group name.
//
function gid_from_name ( $name )
{
    $entry = exec
        ( "getent group $name", $nothing, $r );
    if ( $r != 0 )
        ERROR ( "getent group $name failed" );
    if ( ! preg_match ( '/^([^:]+):[^:]*:(\d+):/',
                        $entry, $matches ) )
        ERROR ( "group $name getent result badly" .
	        "formatted:" . PHP_EOL .
		"    $entry" );
    $group = $matches[1];
    if ( $group != $name )
        ERROR ( "group $name getent result has wrong" .
	        "group name:" . PHP_EOL .
		"    $entry" );
    return intval ( $matches[2] );
}

// Test if a numeric group ID is one of the groups
// of the current process.
//
function check_gid ( $gid )
{
    $gids = exec ( "id -G", $nothing, $r );
    if ( $r != 0 )
        ERROR ( "id -G failed" );
    if ( ! preg_match ( '/^( |\d)+$/', $gids ) )
        ERROR ( "id -G result badly formatted:" .
	        PHP_EOL .
		"    $gids" );
    foreach ( explode ( ' ', $gids ) as $g )
    {
        $g = trim ( $g );
	if ( $g == '' ) continue;
	$g = intval ( $g );
	if ( $g == $gid ) return true;
    }
    return false;
}

$epm_web_gid = gid_from_name ( $epm_web_group );
if ( ! check_gid ( $epm_web_gid ) )
    ERROR ( "$epm_web_group is NOT a group of the" .
            " current process" );

// Section delimiter functions.
//
$epm_titles = [];
function title ( $title )
{
    global $epm_titles, $epm_time_format;
    $epm_titles[] = $title;
    $c = count ( $epm_titles );
    $dashes =
        substr ( '--------------------', 0, 5 * $c );
    if ( $c == 0 )
	echo "$dashes [" .
	     strftime ( $epm_time_format ) .
	     "] $title" . PHP_EOL;
    else
	echo "$dashes $title" . PHP_EOL;
}
function done()
{
    global $epm_titles, $epm_time_format;
    $c = count ( $epm_titles );
    $title = array_pop ( $epm_titles );
    $stars = substr
        ( '********************', 0, 5 * $c );
    if ( $c == 0 )
	echo "$stars [" .
	     strftime ( $epm_time_format ) .
	     "] done $title" . PHP_EOL;
    else
	echo "$stars done $title" . PHP_EOL;
}

// Return directory name with components equal to `.'
// and `..' removed (the latter removing the previous
// component).
//
function scrub_dir ( $dir )
{
    $parent = pathinfo ( $dir, PATHINFO_DIRNAME );
    $base = pathinfo ( $dir, PATHINFO_BASENAME );
    if ( $parent == $dir ) return $dir;
    $parent = scrub_dir ( $parent );

    if ( $base == '..' )
        return pathinfo ( $parent, PATHINFO_DIRNAME );
    elseif ( $base == '.' )
        return $parent;
    elseif ( $parent == '/' )
	return '/' . $base;
    else
	return $parent . '/' . $base;
}

// Function to set the permissions and group of a
// file or directory, with optional directory recursion.
// 
// The file or directory is $base/$fname.  If it is
// a link or if its owner is root, nothing is done.
// Otherwise it is given the $perms permission, which is
// 0771 or a subset, with the following modifications:
//
//	02000 (g+s) is added for every directory
//	00111 (a+x) is removed for non-executable files
//		    (files without u+x permission)
//      00001 (o+x) is removed for directories whose
//            names end in '+', and for the descendants
//            of such directories.
// 
// Values of $perms are:
//
//	0750  for $epm_web and its contents
//	0750  for $epm_home and its descendants
//	0771  for $epm_data just by itself
//	0770  for $epm_data/admin and its descendants
//	0771  for $epm_data/default and its contents
//	0771  for $epm_data/projects and its descendants
//	0770  for $epm_data/lists* and its descendants
//	0771  for $epm_data/users* and its descendants
//
// The *'ed directories are created dynamically and NOT
// created at setup.
//
// Exceptions to the general rules are:
//
//	0771  for +work+ and +run+ subdirectories of
//            $epm_data/users/UID/PROBLEM directories
//     04751  for $epm_home/bin/epm_sandbox (must
//	      be g+r during setup so it can be compared
//            to $epm_home/secure/epm_sandbox)
//
// $base/$fname is always given the $epm_web_group.
//
// Messages give only $fname and hide $base.
//
// When recursing into a directory, names in the
// directory beginning with `.' are ignored.
//
$set_perms_map = [
    scrub_dir ( $epm_home ) => 'HOME',
    scrub_dir ( $epm_web ) => 'WEB',
    scrub_dir ( $epm_data ) => 'DATA' ];
function set_perms
	( $base, $fname, $perms, $dryrun,
	         $recurse = true )
{
    global $epm_web_gid, $set_perms_map;

    $f = "$base/$fname";
    if ( is_link ( $f ) ) return;

    if ( ! file_exists ( $f ) )
        ERROR ( "$fname does not exist" );
    if ( fileowner ( $f ) == 0 ) return;

    if ( isset ( $set_perms_map[$base] ) )
	$pbase = $set_perms_map[$base];
    else
	$pbase = 'OTHER';

    // We must change the group BEFORE we read or change
    // permissions, as changing the group removes g+s.
    //
    $fgid = @filegroup ( $f );
    if ( $fgid === false )
	ERROR ( "could not get group id of $fname" );
    if ( $fgid != $epm_web_gid )
    {
        $action = "group id $fgid => $epm_web_gid:"
	         . " $pbase/$fname";
	echo "changing $action" . PHP_EOL;
	if ( ! $dryrun
	     &&
	     ! @chgrp ( $f, $epm_web_gid ) )
	    ERROR ( "could not change $action" );
    }

    $old_perms = @fileperms ( $f );
    if ( $old_perms === false )
        ERROR ( "cannot read permissions of $fname" );
    $old_perms = $old_perms & 07777;
    $new_perms = $perms;
    if ( is_dir ( $f ) )
    {
	if ( $fname != '' && $fname[-1] == '+' )
	    $new_perms = $new_perms & 07770;

	if ( $recurse )
	{
	    $gs = @scandir ( $f );
	    if ( $gs === false )
		ERROR ( "cannot read $fname" );
	    if ( $fname == '' ) $gbase = '';
	    else $gbase = "$fname/";
	    foreach ( $gs as $g )
	    {
	        if ( $g[0] == '.' ) continue;
		set_perms
		    ( $base, "{$gbase}$g",
		      $new_perms, $dryrun, true );
	    }
	}

        $new_perms = $new_perms | 02000;
    }
    elseif ( ( $old_perms & 0100 ) == 0 )
	$new_perms = $new_perms & 07660;

    if ( $old_perms != $new_perms )
    {
        $action = sprintf ( "%05o => %05o: %s",
	                     $old_perms, $new_perms,
			     "$pbase/$fname" );
	echo 'changing ' . $action . PHP_EOL;
	if ( ! $dryrun
	     &&
	     ! @chmod ( $f, $new_perms ) )
	    ERROR ( "could not change $action" );
    }
}

// Functions to set various perms.
//
function set_perms_problem
	( $project, $problem, $dryrun )
{
    global $epm_data;
    title
        ( "setting permissions for $project $problem" );
    set_perms ( $epm_data, "projects/$project/$problem",
                0771, $dryrun );
    done();
}
function set_perms_project ( $project, $dryrun )
{
    global $epm_data;
    title ( "setting permissions for $project" );
    set_perms ( $epm_data, "projects/$project",
                0771, $dryrun );
    done();
}
function set_perms_projects ( $dryrun )
{
    global $epm_data;
    if ( ! is_dir ( "$epm_data/projects" ) ) return;
    title ( "setting permissions for projects" );
    set_perms ( $epm_data, "projects", 0771, $dryrun );
    done();
}
function set_perms_default ( $dryrun )
{
    global $epm_data;
    if ( ! is_dir ( "$epm_data/default" ) ) return;
    title ( "setting permissions for default" );
    set_perms ( $epm_data, "default", 0771, $dryrun );
    done();
}
function set_perms_admin ( $dryrun )
{
    global $epm_data;
    if ( ! is_dir ( "$epm_data/admin" ) ) return;
    title ( "setting permissions for admin" );
    set_perms ( $epm_data, "admin", 0770, $dryrun );
    done();
}
function set_perms_lists ( $dryrun )
{
    global $epm_data;
    if ( ! is_dir ( "$epm_data/lists" ) ) return;
    title ( "setting permissions for lists" );
    set_perms ( $epm_data, "lists", 0770, $dryrun );
    done();
}
function set_perms_home ( $dryrun )
{
    global $epm_home;
    title ( "setting permissions for \$epm_home" );
    set_perms ( $epm_home, "", 0750, $dryrun );
    done();
}
function set_perms_web ( $dryrun )
{
    global $epm_web;
    title ( "setting permissions for \$epm_web" );
    set_perms ( $epm_web, "", 0750, $dryrun );
    done();
}
function set_perms_all ( $dryrun )
{
    set_perms_projects ( $dryrun );
    set_perms_default ( $dryrun );
    set_perms_admin ( $dryrun );
    set_perms_lists ( $dryrun );
    set_perms_home ( $dryrun );
    set_perms_web ( $dryrun );
}

// Do not execute the following when commands are
// executing in +work+ or +run+ subdirectories as
// their permissions will be mis-set.  As they are
// recreated upon next use, the damage will not
// affect future executions.
//
function set_perms_user_problem
	( $user, $problem, $dryrun )
{
    global $epm_data;
    title ( "setting permissions for $user $problem" );
    set_perms ( $epm_data, "users/$user/$problem",
                0771, $dryrun );
    done();
}
function set_perms_user ( $user, $dryrun )
{
    global $epm_data;
    title ( "setting permissions for $user" );
    set_perms ( $epm_data, "users/$user",
                0771, $dryrun );
    done();
}
function set_perms_users ( $dryrun )
{
    global $epm_data;
    title ( "setting permissions for users" );
    set_perms ( $epm_data, "users", 0771, $dryrun );
    done();
}

// Function to init a project problem directory.
//
// For every YYYY in $epm_specials, checks if the
// problem directory contains YYYY-PPPP as a link or a
// file.  If not, and in +sources+ there is the
// file YYYY-PPPP.cc or YYYY-PPPP.c, compiles this
// last to produce YYYY-PPPP.
//
// Then if monitor-PPPP does not exist as a file,
// for YYYY equal to `generate' or `filter' checks
// if YYYY-PPPP now exists as a link or a file.  If
// not, makes a link
//
//	YYYY-PPPP => ../../../default/epm_default_YYYY
//
// If an action is being take, a message describing the
// action is written to the standard output.
//
// If $dryrun is true, no actual actions are executed
// but the messages are written anyway.
//
function init_problem
    ( $project, $problem, $dryrun )
{
    global $epm_data, $epm_specials;
    title ( "initing $project $problem" );

    $d1 = "projects";
    $d2 = "$d1/$project";
    $d3 = "$d2/$problem";
    $d4 = "$d3/+sources+";
    if ( ! is_dir ( "$epm_data/$d2" ) )
        ERROR ( "$d2 is not a directory" );
    if ( ! is_dir ( "$epm_data/$d3" ) )
        ERROR ( "$d3 is not a directory" );

    foreach ( $epm_specials as $spec )
    {
	$f = "$d3/$spec-$problem";
	if ( is_link ( "$epm_data/$f" ) )
	    continue;
	if ( file_exists ( "$epm_data/$f" ) )
	    continue;
	$gc = "$d4/$spec-$problem.c";
	$gcc = "$d4/$spec-$problem.cc";
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
	    continue;

	echo ( $action . PHP_EOL );
	if ( $dryrun ) continue;

	passthru ( $command, $r );
	if ( $r != 0 )
	    ERROR ( "compile returned exit code $r" );
    }

    $f = "$epm_data/$d3/monitor-$problem";
    if ( ! file_exists ( "$epm_data/$f" ) )
        foreach ( ['generate', 'filter'] as $spec )
    {
	$f = "$d3/$spec-$problem";
	if ( is_link ( "$epm_data/$f" ) )
	    continue;
	if ( file_exists ( "$epm_data/$f" ) )
	    continue;
	$action = "symbolically link $f to default";
	$command = "ln -s ../../../default/"
		 . "epm_default_$spec"
		 . " $epm_data/$f";

	echo ( $action . PHP_EOL );
	if ( $dryrun ) continue;

	passthru ( $command, $r );
	if ( $r != 0 )
	    ERROR ( "link returned exit code $r" );
    }
    done();
}

// Function to init all the problems in a project.
//
function init_project ( $project, $dryrun )
{
    global $epm_data, $epm_name_re;

    $d1 = "projects";
    $d2 = "$d1/$project";
    if ( ! is_dir ( "$epm_data/$d2" ) )
        ERROR ( "$d2 is not a directory" );

    $dirs = @scandir ( "$epm_data/$d2" );
    if ( $dirs === false )
        ERROR ( "cannot read $d2" );
    foreach ( $dirs as $problem )
    {
        if ( ! preg_match ( $epm_name_re, $problem ) )
	    continue;
	init_problem ( $project, $problem, $dryrun );
    }
}

// Function to init all projects.
//
function init_projects ( $dryrun )
{
    global $epm_data, $epm_name_re;

    $d1 = "projects";
    if ( ! is_dir ( "$epm_data/$d1" ) )
        ERROR ( "$d1 is not a directory" );

    $dirs = @scandir ( "$epm_data/$d1" );
    if ( $dirs === false )
        ERROR ( "cannot read $d1" );
    foreach ( $dirs as $project )
    {
        if ( ! preg_match ( $epm_name_re, $project ) )
	    continue;
	init_project ( $project, $dryrun );
    }
}

// Function to sync $epm_library to $epm_data problem.
//
function export_problem ( $project, $problem, $dryrun )
{
    global $epm_data, $epm_library, $epm_specials;
    title ( "exporting $project $problem" );

    $dir = "projects/$project";
    if ( ! is_dir ( "$epm_library/$dir" ) )
        ERROR ( "$dir is not a \$epm_library" .
	        " directory" );
    $dir = "$dir/$problem";
    if ( ! is_dir ( "$epm_data/$dir" ) )
        ERROR ( "$dir is not a \$epm_data directory" );

    $opt = ( $dryrun ? '-n' : '' );
    foreach ( $epm_specials as $spec )
        $opt .= " --include '$spec-$problem.*'"
              . " --exclude $spec-$problem";
    $opt .= " --include +sources+"
          . " --exclude '+*+'";
    $command = "rsync $opt -avc --delete"
             . " --info=STATS0,FLIST0"
             . " $epm_data/$dir/ $epm_library/$dir/";
    passthru ( $command, $r );
    if ( $r != 0 )
        ERROR ( "rsync returned exit code $r" );
    done();
         PHP_EOL . PHP_EOL;
}

// Function to sync $epm_library to $epm_data project.
//
function export_project ( $project, $dryrun )
{
    global $epm_data, $epm_library, $epm_name_re;

    $d1 = "projects";
    $d2 = "$d1/$project";
    if ( ! is_dir ( "$epm_data/$d2" ) )
        ERROR ( "$d2 is not a \$epm_data directory" );
    if ( ! is_dir ( "$epm_library/$d2" )
         &&
         ! @mkdir ( "$epm_library/$d2", 0750, true ) )
	ERROR ( "cannot make $d2 in \$epm_library" );

    $dirs = @scandir ( "$epm_data/$d2" );
    if ( $dirs === false )
        ERROR ( "cannot read $d2 in \$epm_data" );
    foreach ( $dirs as $problem )
    {
        if ( ! preg_match ( $epm_name_re, $problem ) )
	    continue;
	export_problem ( $project, $problem, $dryrun );
    }
}

// Function to sync $epm_library to $epm_data projects.
//
function export_projects ( $dryrun )
{
    global $epm_data, $epm_library, $epm_name_re;

    $d1 = "projects";
    if ( ! is_dir ( "$epm_data/$d1" ) )
        ERROR ( "$d1 is not a \$epm_data directory" );
    if ( ! is_dir ( "$epm_library/$d1" )
         &&
         ! @mkdir ( "$epm_library/$d1", 0750 ) )
	ERROR ( "cannot make $d1 in \$epm_library" );

    $dirs = @scandir ( "$epm_data/$d1" );
    if ( $dirs === false )
        ERROR ( "cannot read $d1 in \$epm_data" );
    foreach ( $dirs as $project )
    {
        if ( ! preg_match ( $epm_name_re, $project ) )
	    continue;
	export_project ( $project, $dryrun );
    }
}

// Function to sync $epm_data to epm_library problem.
//
function import_problem ( $project, $problem, $dryrun )
{
    global $epm_data, $epm_specials;
    title ( "importing $project $problem" );

    $desdir = "projects/$project";
    if ( ! is_dir ( "$epm_data/$desdir" ) )
        ERROR ( "$desdir is not an \$epm_data" .
	        " directory" );
    $desdir = "$desdir/$problem";
    $lib = epm_library ( $project );
    if ( ! isset ( $lib ) )
        ERROR ( "epm_library ( $project ) is not" .
	        " defined" );
    $srcdir = "$lib/$problem";
    if ( ! is_dir ( $srcdir ) )
        ERROR ( "$problem is not an epm_library" .
	        " ( $project ) subdirectory" );

    $opt = ( $dryrun ? '-n' : '' );
    foreach ( $epm_specials as $spec )
        $opt .= " --include '$spec-$problem.*'"
              . " --exclude $spec-$problem";
    $opt .= " --include +sources+"
          . " --exclude '+*+'";
    $command = "rsync $opt -avc --delete"
             . " --info=STATS0,FLIST0"
             . " $srcdir/ $epm_data/$desdir/";
	// It is necessary to have the excludes
	// because we have the --delete; with the
	// excludes rsync will NOT delete excluded
	// files (which are not in the library).

    passthru ( $command, $r );
    if ( $r != 0 )
        ERROR ( "rsync returned exit code $r" );
    done();
         PHP_EOL . PHP_EOL;
}

// Function to sync $epm_data to epm_library($project).
// If epm_library ( $project ) not defined, does
// nothing.
//
function import_project ( $project, $dryrun )
{
    global $epm_data, $epm_name_re;

    $lib = epm_library ( $project );
    if ( ! isset ( $lib ) ) return;
    if ( ! is_dir ( $lib ) )
        ERROR ( "epm_library ( $project ) is not a" .
	        " directory" );

    $d = "projects/$project";
    if ( ! is_dir ( "$epm_data/$d" )
         &&
         ! @mkdir ( "$epm_data/$d", 0770, true ) )
	ERROR ( "cannot make $d in \$epm_data" );

    $dirs = @scandir ( $lib );
    if ( $dirs === false )
        ERROR ( "cannot read epm_library" .
	        " ( $project )" );
    foreach ( $dirs as $problem )
    {
        if ( ! preg_match ( $epm_name_re, $problem ) )
	    continue;
	import_problem ( $project, $problem, $dryrun );
    }
}

// Function to sync $epm_data to epm_library projects.
// Only projects already in $epm_data are sync'ed.
//
function import_projects ( $dryrun )
{
    global $epm_data, $epm_name_re;

    if ( ! is_dir ( "$epm_data/projects" ) )
        ERROR ( "`projects' directory is not exist" .
	        " in \$epm_data" );
    $dirs = @scandir ( "$epm_data/projects" );
    if ( $dirs === false )
        ERROR ( "cannot read `projects' directory" .
	        " in \$epm_data" );
    foreach ( $dirs as $project )
    {
        if ( ! preg_match ( $epm_name_re, $project ) )
	    continue;
	$lib = epm_library ( $project );
	if ( ! isset ( $lib ) ) continue;
	if ( ! is_dir ( $lib ) ) continue;
	import_project ( $project, $dryrun );
    }
}

// If $dir is not a directory, make it.  Directory names
// are relative to $epm_data.
//
function make_dir ( $dir, $dryrun )
{
    global $epm_data;
    if ( is_dir ( "$epm_data/$dir" ) ) return;
    echo ( "making directory $dir" . PHP_EOL );
    if ( $dryrun ) return;
    if ( $dir == '.' || $dir == '' )
        $d = $epm_data;
	// Cannot make D/. if D does not exist.
    else
        $d = "$epm_data/$dir";
    if ( ! @mkdir ( "$d" ) )
        ERROR ( "cannot make directory $dir" );
}

// If $from is not a symbolic link, make it.  $from is
// relative to $epm_data, but $to can be absolute or
// relative to $from.
//
function make_link ( $to, $from, $dryrun )
{
    global $epm_data;
    if ( is_link ( "$epm_data/$from" ) ) return;
    echo ( "making link $from => $to" . PHP_EOL );
    if ( $dryrun ) return;
    if (    exec ( "ln -snf $to $epm_data/$from 2>&1" )
         != '' )
        ERROR ( "cannot make link $from => $to" );
	// PHP symlink fails sometimes so we dare not
	// use it.  -snf will unlink $from if it exists
	// before linking it.
}

// Copy $epm_home/setup/$dir and its contents to
// $epm_data/$dir.  Make destination directory if
// necessary.  If the destination of a file to be copied
// exists, do not copy the file.  If the destination is
// a dangling link, unlink it and copy.  Copy is
// recursive.
//
function copy_dir ( $dir, $dryrun )
{
    global $epm_home, $epm_data;
    $srcdir = "$epm_home/setup/$dir";
    $desdir = "$epm_data/$dir";

    if ( ! is_dir ( $desdir ) )
        make_dir ( $dir, $dryrun );

    $files = @scandir ( $srcdir );
    if ( $files === false )
        ERROR ( "cannot read setup/$dir" );
    foreach ( $files as $fname )
    {
        if ( $fname[0] == '.' ) continue;
	if ( is_dir ( "$srcdir/$fname" ) )
	{
	    copy_dir ( "$dir/$fname", $dryrun );
	    continue;
	}
	elseif ( file_exists ( "$desdir/$fname" ) )
	    continue;
	elseif ( is_link ( "$desdir/$fname" ) )
	{
	    echo ( "unlinking $dir/$fname" . PHP_EOL );
	    if ( ! $dryrun
	         &&
	         ! @unlink ( "$desdir/$fname" ) )
		ERROR ( "cannot unlink $dir/$fname" );
	}
	$action = "setup/$dir/$fname to $dir/$fname";
	echo ( "copying $action" . PHP_EOL );
	if ( $dryrun ) continue;
	if ( ! @copy ( "$srcdir/$fname",
	               "$desdir/$fname" ) )
	    ERROR ( "cannot copy $action" );
    }
}

// Function to check the permissions of ancestors of
// $dir.  $is_data is true if $dir is $epm_data, and
// is false for $epm_home and $epm_web.
//
function check_ancestors ( & $TODO, $dir, $is_data )
{
    global $epm_web_gid;

    $ancestor = $dir;
    while ( $ancestor != '/' )
    {
        $ancestor = pathinfo
	    ( $ancestor, PATHINFO_DIRNAME );
	$perms = fileperms ( $ancestor );
	if ( ( $perms & 0001 ) != 0 ) continue;
	$mode = 'o+x';
	if ( ! $is_data )
	{
	    $gid = filegroup ( $ancestor );
	    if ( $gid == $epm_web_gid )
	    {
	        $mode = 'g+x';
		if ( ( $perms & 0010 ) != 0 )
		    continue;
	    }
	}
	$TODO .= "chmod $mode $ancestor" . PHP_EOL;
    }
}

// Function to set up contents of $epm_data, $epm_web,
// and $epm_home/bin and set permissions of $epm_home,
// $epm_web, $epm_data, and their descendents.  Can
// be re-run on existing system to make repairs.
//
function setup ( $dryrun )
{
    global $epm_home, $epm_web, $epm_data,
           $epm_name_re, $epm_backup;

    make_dir ( '.', $dryrun );
    make_dir ( 'projects', $dryrun );
    make_dir ( 'projects/public', $dryrun );
    make_dir ( 'projects/demos', $dryrun );
    make_dir ( 'default', $dryrun );

    // Copy recursively from $epm_home/setup.
    //
    copy_dir ( '.', $dryrun );

    make_link ( $epm_web, '+web+', $dryrun );
    make_link ( "$epm_home/page", '+web+/page',
                $dryrun );
    make_link ( 'page/index.php', '+web+/index.php',
                $dryrun );

    $n = ( $dryrun ? '-n' : '' );

    echo ( "installing epm/src files" . PHP_EOL );
    $command = "cd $epm_home/src;"
             . " DEFAULT=$epm_data/default"
             . " make $n install";
    passthru ( $command, $r );
    if ( $r != 0 )
        ERROR ( "make returned exit code $r" );

    echo ( "making epm/secure/epm_sandbox" . PHP_EOL );
    $command =
        "cd $epm_home/secure; make $n epm_sandbox";
    passthru ( $command, $r );
    if ( $r != 0 )
        ERROR ( "make returned exit code $r" );

    $TODO = "cd $epm_data/admin" . PHP_EOL
          . "edit motd.html as desired" . PHP_EOL
          . "edit +blocking+ as desired" . PHP_EOL;

    if ( isset ( $epm_backup ) )
    {
        if ( ! is_dir ( $epm_backup ) )
	{
	    echo "making backup directory" . PHP_EOL;
	    if ( ! $dryrun
	         &&
	         ! @mkdir ( $epm_backup ) )
		ERROR ( "cannot make $epm_backup" );
	}

	$f = "$epm_backup/crontab";
        if ( ! file_exists ( $f ) )
	{
	    echo ( "writing crontab" . PHP_EOL );
	    $crontab = 
	        "EPM = $epm_home/bin/epm" .
		PHP_EOL .
		"WEB = $epm_web" .
		PHP_EOL .
		"LOG = $epm_backup/LOG" .
		PHP_EOL .
	        '0 4 * * * $EPM backup $WEB' .
		' >>$LOG 2>&1' .
		PHP_EOL;

	    if ( ! $dryrun
	         &&
		 ! file_put_contents ( $f, $crontab ) )
	        ERROR ( "could not write $f" );
	}
	$TODO .= "cd $epm_backup" . PHP_EOL
	       . "Edit crontab if you like" . PHP_EOL
	       . "crontab crontab" . PHP_EOL;
    }

    check_ancestors ( $TODO, $epm_home, false );
    check_ancestors ( $TODO, $epm_web,  false );
    check_ancestors ( $TODO, $epm_data, true );

    $src = "$epm_home/secure/epm_sandbox";
    $des = "$epm_home/bin/epm_sandbox";
    $r = 0;
    if ( file_exists ( $des ) )
    {
	exec ( "cmp -s $src $des", $nothing, $r );
	if ( fileowner ( $des ) != 0 )
	    $r = 1;
	if ( ( fileperms ( $des ) & 07777 ) != 04751 )
	    $r = 1;
	if ( $r != 0 )
	    $TODO .= "rm -f $des" . PHP_EOL;
    }
    else
        $r = 1;
    if ( $r != 0 )
	$TODO .= "cd $epm_home/secure" . PHP_EOL
	       . "su" . PHP_EOL
	       . "make install" . PHP_EOL
	       . "exit" . PHP_EOL;

    $count = 0;
    if ( is_dir ( "$epm_data/projects/demos" ) )
    {
	$demos =
	    @scandir ( "$epm_data/projects/demos" );
	if ( $demos === false )
	    ERROR ( "cannot read projects/demos" );
	foreach ( $demos as $fname )
	{
	    if ( preg_match ( $epm_name_re, $fname ) )
		++ $count;
	}
    }
    if ( $count == 0 )
        $TODO .= "cd $epm_data" . PHP_EOL
	       . "$epm_home/bin/epm import demos" .
	         PHP_EOL;

    set_perms_all ( $dryrun );
    if ( is_dir ( $epm_data ) )
        // May not exist during dry run.
	set_perms ( $epm_data, '', 0771,
	            $dryrun, false );

    if ( $TODO != '' )
    {
	title ( 'TODOs' );
	echo $TODO;
	if ( ! $dryrun )
	{
	    $r = @file_put_contents
		( "$epm_data/TODO", $TODO );
	    if ( $r === false )
		ERROR ( "cannot write TODO" );
	    echo PHP_EOL .
	         "TODOs written to DATA/TODO" .
		 PHP_EOL;
	}
	done();
    }
}

// Compute backup list.  This is a list of items of
// the form:
//
//	[BASE,NUMBER]
//
// where the backup is named BASE-LEVEL.tgz and NUMBER
// n indicates this is the n'th child of the latest
// level 0 backup.  NUMBER is 0 for level 0 backups.
//
// The list is sorted in BASE order, so if NUMBER is n
// then the n'th list entry before the current entry is
// the level 0 parent of the current entry, if the
// current entry is a child.
//
// NUMBER is -1 if a child has no parent, which should
// not happen but might.
//
function backup_list ( & $list )
{
    global $epm_backup;

    $list = [];
    if ( ! is_dir ( $epm_backup ) )
        return;

    $files = scandir ( $epm_backup );
    if ( $files === false )
        ERROR ( 'cannot read $epm_backup' );
    sort ( $files, SORT_STRING );
    $number = -1;
    foreach ( $files as $file )
    {
        if ( ! preg_match ( '/^(.+)-(0|1)\.tgz$/',
	                    $file, $matches ) )
	    continue;
	$base = $matches[1];
	$level = $matches[2];
	if ( $level == '0' )
	    $number = 0;
	elseif ( $number != -1 )
	    ++ $number;
	$list[] = [$base,$number];
    }
}

function clean_backups ( $dryrun, & $list = NULL )
{
    global $epm_backup, $epm_backup_round;

    if ( ! isset ( $list ) ) backup_list ( $list );

    $c = count ( $list );
    if ( $c <= $epm_backup_round ) return;

    $c = $c - $epm_backup_round;
    // We would like to delete first $c $list elements.
    list ( $base, $number ) = $list[$c];
    if ( $number >= 0 )  // handles $number == -1 case
	$c = $c - $number;
    // Now we can delete $c elements.
    if ( $c <= 0 ) return;
    $commands = [];
    $i = 0;
    while ( $i < $c )
    {
        list ( $base, $number ) = $list[$i];
	++ $i;
	$l = ( $number == 0 ? '0' : '1' );
	$commands[] = "rm -f $base-$l.tgz";
	if ( $l == '0' )
	    $commands[] = "rm -f $base-$l.snar";
	else
	    $commands[] = "rm -f $base-$l.parent";
    }
    foreach ( $commands as $command )
    {
	echo ( $command . PHP_EOL );
	if ( $dryrun ) continue;
	passthru ( "cd $epm_backup; $command", $r );
	if ( $r != 0 )
	    ERROR ( "last command returned $r" );
    }

    array_splice ( $list, 0, $c );
}


function backup ( $dryrun )
{
    global $epm_data, $epm_backup, $epm_backup_name,
           $epm_backup_round, $epm_time_format;

    title ( "$epm_backup_name backup" );
    if ( ! is_dir ( $epm_backup ) )
    {
        echo ( "making $epm_backup" . PHP_EOL );
	if ( ! $dryrun
	     &&
	     ! mkdir ( $epm_backup, 0750 ) )
	    ERROR ( "cannot make $epm_backup" );
    }

    backup_list ( $list );

    $time = strftime ( $epm_time_format );
    $time = str_replace ( ':', '_', $time );
        // Tar interprets :'s in its file name
	// specially.
    $base = "$epm_backup_name-$time";
    $commands = [];
    $len = count ( $list );
    if ( $len == 0 )
	$number = 0;
    else
    {
	list ( $pbase, $number ) = $list[$len-1];
	if ( $number == -1 )
	    $number = 0;
	elseif (    2 * ( $number + 1 )
	         >= $epm_backup_round )
	    $number = 0;
	elseif ( $number > 0 )
	{
	    $cbase = $pbase;
	    list ( $pbase, $zero ) =
	        $list[$len-1-$number];
	    ++ $number;
	}
	else
	    $number = 1;
    }

    if ( $number > 1 )
    {
	$p = "$pbase-0.tgz";
	$psize = filesize ( "$epm_backup/$p" );
	if ( $psize == false )
	    ERROR ( "cannot stat $p" );
	$c = "$cbase-1.tgz";
	$csize = filesize ( "$epm_backup/$c" );
	if ( $csize == false )
	    ERROR ( "cannot stat $c" );
	if ( $csize >= $psize )
	    $number = 0;
    }

    if ( $number != 0 )
	$commands[] = [ $epm_backup,
	                "cp -p $pbase-0.snar \\" .
			PHP_EOL .
			"    $base-1.snar" ];
    $d = $epm_backup;
    $l = ( $number == 0 ? '0' : '1' );
    $commands[] = [ $epm_data,
		    "tar -zc \\" .
		    PHP_EOL .
		    "    -g $d/$base-$l.snar \\" .
		    PHP_EOL .
		    "    -f $d/$base-$l.tgz \\" .
		    PHP_EOL .
		    "    --exclude=+work+" .
		    " --exclude=+run+" .
		    " ." ];
    if ( $number != 0 )
    {
	$commands[] = [ $epm_backup,
	                "rm $base-1.snar" ];
	$commands[] = [ $epm_backup,
	                "ln -snf $pbase-0.tgz \\" .
			PHP_EOL .
			"    $base-1.parent" ];
    }
    foreach ( $commands as $e )
    {
        list ( $d, $command ) = $e;
	echo ( $command . PHP_EOL );
	if ( $dryrun ) continue;
	passthru ( "cd $d; $command", $r );
	if ( $r != 0 )
	    ERROR ( "last command returned $r" );
    }
    $list[] = [$base,$number];
    clean_backups ( $dryrun, $list );
    done();
}

?>
