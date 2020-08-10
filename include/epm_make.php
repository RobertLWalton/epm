<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Mon Aug 10 17:43:19 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain;
// they make no warranty and accept no liability
// for EPM.

// Functions used to make files from other files.
//
// Used by epm_run, so $_SESSION must be declared as
// a global.
//
// Note that file names can have -, _, ., /, but no
// other special characters.  Of course uploaded
// files and components cannot have /."
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
if ( ! isset ( $aid ) )
    exit ( 'ACCESS ERROR: $aid not set' );
if ( ! isset ( $problem ) )
    exit ( 'ACCESS ERROR: $problem not set' );
if ( ! isset ( $probdir ) )
    exit ( 'ACCESS ERROR: $probdir not set' );

if ( ! isset ( $_SESSION['EPM_WORK'][$problem] ) )
    $_SESSION['EPM_WORK'][$problem] = [];
$work = & $_SESSION['EPM_WORK'][$problem];
if ( ! isset ( $_SESSION['EPM_RUN'][$problem] ) )
    $_SESSION['EPM_RUN'][$problem] = [];
$run = & $_SESSION['EPM_RUN'][$problem];

require 'epm_template.php';

if ( ! isset ( $is_epm_test ) )
    $is_epm_test = false;
    // True means we are running a test script that is
    // NOT driven by an http server.  Some functions,
    // notably move_uploaded_file, will not work
    // in this test script environment.

// Return true if process with $pid is still running,
// and false otherwise.
//
function is_running ( $pid )
{
    exec ( "kill -s 0 $pid 2>/dev/null",
	   $kill_output, $kill_status );
    //
    // Sending signal 0 does not actually send a
    // signal but returns status 0 if the process is
    // still running and status non-0 otherwise.
    // Note that the posix_kill function is not
    // available in vanilla PHP.

    return ( $kill_status == 0 );
}

// Given file names, and a template, determine if the
// template matches $problem and the file names.  If
// no, return NULL.  If yes, return an array containing
// the map from wild card symbols to their values.  Note
// that if the template does not contain PPPP or any
// other wildcard, this may be an empty array, but will
// not be NULL.
//
// If PPPP is in the template, replace it with $problem
// before proceeding futher.
//
// Either $filenames is a single name and $template
// is just the source or just the destination part of
// a .tmpl file name, or $filenames has the form
// $srcfile:$desfile and $template is the part of the
// .tmpl file name before the second :.
//
function template_match ( $filenames, $template )
{
    global $problem;

    // Turn template into a regexp.
    //
    $template = preg_replace
        ( '/\./', '\\.', $template );
    $template = preg_replace
        ( '/PPPP/', $problem, $template,
	  -1, $PPPP_count );
    $offset = 0;
    $ids = [];
    while ( preg_match
                ( '/[A-Z]/', $template, $matches,
                  PREG_OFFSET_CAPTURE, $offset ) )
    {
        $char = $matches[0][0];
	$offset = $matches[0][1];
	if ( ! preg_match
	           ( "/\G$char{4}/", $template,
		     $matches, 0, $offset ) )
	{
	    ++ $offset;
	    continue;
	}
	$template = preg_replace
	    ( "/$char{4}/", '(.*)', $template, 1 );
	$ids[] = "$char$char$char$char";
    }
    if ( ! preg_match ( "/^$template\$/", $filenames,
                                          $matches ) )
        return NULL;

    $result = [];
    for ( $i = 0; $i < count ( $ids ); ++ $i )
    {
        if ( isset ( $result[$ids[$i]] ) )
	{
	    if ( $result[$ids[$i]] != $matches[$i+1] )
	        return NULL;
	}
	else
	    $result[$ids[$i]] = $matches[$i+1];
    }
    if ( $PPPP_count > 0 )
	$result['PPPP'] = $problem;
    return $result;
}

// Given a string and substitutions such as those
// computed by file_name_match, return the string with
// the substitutions made.
//
function string_substitute_match ( $string, $match )
{
    foreach ( $match as $key => $value )
	$string = preg_replace
	    ( "/$key/", $value, $string );
    return $string;
}

// Given an array and substitutions such as those
// computed by file_name_match, return the array with
// the substitutions made in the array values that are
// strings, and recursively in array values that are
// arrays.
//
function substitute_match ( $item, $match )
{
    if ( is_string ( $item ) )
        return string_substitute_match
	    ( $item, $match );
    else if ( is_array ( $item ) )
    {
        $new_array = [];
        foreach ( $item as $key => $value )
	    $new_array[$key] = substitute_match
	        ( $value, $match );
	return $new_array;
    }
    else
        return $item;
}

// Go through the template cache and find each template
// that has the given source file name and destination
// file name and matches the given condition (which is
// NULL if the template is to have no CONDITION).
//
// For each template found, list in $templates elements
// of the form:
//
//   [template, directory, json]
// 
// containing the information copied from the
//
//	template => [root, directory, json]
//
// but with wildcards in json replaced by their matches
// found from matching the source and destination file
// names and $problem to the template.
//
function find_templates
    ( $srcfile, $desfile, $condition, & $templates )
{
    global $template_cache;
    load_template_cache();

    $templates = [];
    foreach ( $template_cache as $template => $triple )
    {
	if ( ! preg_match
	       ( '/^([^:]+):([^:]+):/',
		 $template, $matches ) )
	{
	    WARN ( "bad template format $template" );
	    continue;
	}

	$tsrc = $matches[1];
	$tdes = $matches[2];

	$match = template_match
	    ( "$srcfile:$desfile", "$tsrc:$tdes" );

	if ( is_null ( $match ) ) continue;

	$json = get_template_json ( $template );

	$json = substitute_match ( $json, $match );

	if ( isset ( $json['CONDITION'] ) )
	    $cond = $json['CONDITION'];
	else
	    $cond = NULL;
	if ( $cond != $condition ) continue;

	$templates[] =
	    [ $template, $triple[1], $json ];
    }
}

// Load the argument map that is to be applied to
// template COMMANDS.  The argument map is computed from
// results of get_template_optn and get_problem_optn.
// Append errors to $errors: if there are errors then
// nothing is done.  There is no return value in any
// case.
//
$argument_map = NULL;
$argument_map_allow_local = NULL;
function load_argument_map
	( $allow_local_optn, & $errors )
{
    global $argument_map, $argument_map_allow_local,
           $problem;
    if ( isset ( $argument_map )
         &&
	    $allow_local_optn
	 == $argument_map_allow_local )
        return;

    $errors_size = count ( $errors );
    $template_optn = get_template_optn();
    $problem_optn =
        get_problem_optn ( $allow_local_optn, $errors );
    if ( count ( $errors ) > $errors_size )
        return;

    $argument_map = [];
        
    $arg_map = [];
    $val_map = [];

    foreach ( $template_optn as $opt => $description )
    {
	if ( isset ( $description['valname'] ) )
	{
	    $valname = $description['valname'];
	    $val_map[$valname] = $problem_optn[$opt];
	}
	elseif ( isset ( $description['argname'] ) )
	{
	    $argname = $description['argname'];
	    if ( isset ( $arg_map[$argname] ) )
	        $arg_map[$argname] .=
		    " $problem_optn[$opt]";
	    else
		$arg_map[$argname] =
		    $problem_optn[$opt];
	}
    }

    $argument_map_allow_local = $allow_local_optn;
    $argument_map =
        substitute_match ( $arg_map, $val_map );
}

// Build caches of files that may be required.  The
// caches have entries of the form:
//
//	filename => directory
//
// where filename is the last component of the file
// name and directory is the first directory in the
// ancestors of $probdir in which the file can be found
// under the full name:
//
//	$epm_data/directory/filename
//
// $remote_file_cache is for the ancestor directories
// of $probdir.   $local_file_cache is for the files
// listed in the single directory $probdir.
//
// All directories names are relative to $epm_data.  Any
// filename that does not match epm_filename_re is
// ignored.  This function does NOT return a value.
//
$local_file_cache = NULL;
$remote_file_cache = NULL;
function reload_file_caches()
{
    global $local_file_cache, $remote_file_cache;
    $local_file_cache = NULL;
    $remote_file_cache = NULL;
    load_file_caches();
}
function load_file_caches()
{
    global $epm_data, $problem, $probdir,
	   $epm_filename_re,
           $remote_file_cache, $local_file_cache;

    if ( isset ( $local_file_cache )
         &&
	 isset ( $remote_file_cache ) )
        return;

    $local_file_cache = [];
    $remote_file_cache = [];
    foreach ( find_ancestors ( $probdir ) as $dir )
    {
	$c = @scandir ( "$epm_data/$dir" );
	if ( $c === false )
	    ERROR ( "cannot read $dir" );

	foreach ( $c as $fname )
	{
	    if ( ! preg_match
	               ( $epm_filename_re, $fname ) )
	        continue;
	    if ( isset ( $remote_file_cache[$fname] ) )
	        continue;
	    $remote_file_cache[$fname] = $dir;
	}
    }

    $c = @scandir ( "$epm_data/$probdir" );
    if ( $c === false )
	ERROR ( "cannot read $probdir" );
    foreach ( $c as $fname )
    {
	if ( ! preg_match ( $epm_filename_re, $fname ) )
	    continue;
	$local_file_cache[$fname] = $probdir;
    }
}

// Given $templates computed by find_templates and the
// caches computed by load_file_caches, return the
// control, i.e., the selected element of $templates,
// and set $local_required to the list of $local_file_
// cache files required by the control, $remote_required
// to the list of $remote_file_cache files required by
// the control, and $creatable to the list of files that
// the control requires to be created (locally).
//
// If multiple templates satisfy required file con-
// straints, ones with the largest total number of files
// that are either required or to be created are select-
// ed.  It is an error if more than one is selected by
// this rule, or if no template meets the constraints.
//
// Any errors cause error messages to be appended to
// the $errors list and NULL to be returned.
//
// Only the last component of a file name is listed in
// $local_required, $remote_required, or $creatable.
// The directory containing a $remote_required file can
// be found using $remote_file_cache.
//
// This function calls load_file_caches.
//
function find_control
	( $templates,
	  & $local_required, & $remote_required,
	  & $creatable, & $errors )
{
    global $local_file_cache, $remote_file_cache;
    load_file_caches();

    $best_template = NULL;
        // Element of $templates for the first element
	// of $best_found.
    $best_found = [];
        // List of elements of the form [template,
	// flfiles,frfiles,cfiles] where flfiles is the
	// value for $local_required, frfiles is the
	// value for $remote_required, cfiles is the
	// value for $creatable, and only templates with
	// NO files that are needed but neither found
	// nor creatable and with the most total number
	// of files that are either found or creatable
	// are listed.
    $best_found_count = -1;
        // Number of files listed in each element
	// of $best_found.
    $best_not_found = [];
        // List of elements of the form [template,
	// nflfiles,nfrfiles] where nflfiles is the list
	// of REQUIRED and LOCAL-REQUIRED files that
	// were not found or CREATABLE, and nfrfiles
	// is the list of REMOTE-REQUIRED files that
	// were not found.  Only templates with at least
	// 1 such file are included, and only those with
	// the least total number of such files.
    $best_not_found_count = 1000000000;
        // Number of files listed in each element
	// of $best_not_found;

    foreach ( $templates as $template )
    {
        $json = $template[2];
	$creatables = [];
	if ( isset ( $json['CREATABLE'] ) )
	{
	    $cs = $json['CREATABLE'];
	    if ( ! is_array ( $cs ) )
		ERROR
		    ( "{$template[0]} json CREATABLE" .
		      " is not a list" );
	    foreach ( $cs as $c )
	        $creatables[$c] = true;
	}
	$flfiles = [];
	    // Local required files found.
	$frfiles = [];
	    // Remote required files found.
	$cfiles = [];
	    // Files not found but CREATABLE that can
	    // be local.
	$nflfiles = [];
	    // Required files not found and not
	    // creatable that can be local.
	$nfrfiles = [];
	    // Required files not found that must be
	    // remote.

	if ( isset ( $json['LOCAL-REQUIRES'] ) )
	{
	    if ( ! is_array
	               ( $json['LOCAL-REQUIRES'] ) )
		ERROR
		    ( "{$template[0]} json" .
		      " LOCAL-REQUIRES is not a list" );
	    foreach ( $json['LOCAL-REQUIRES'] as $f )
	    {
		if ( isset ( $local_file_cache[$f] ) )
		    $flfiles[] = $f;
		else
		if ( isset ( $creatables[$f] ) )
		    $cfiles[] = $f;
		else
		    $nflfiles[] = $f;
	    }
	}

	if ( isset ( $json['REQUIRES'] ) )
	{
	    if ( ! is_array ( $json['REQUIRES'] ) )
		ERROR
		    ( "{$template[0]} json" .
		      " REQUIRES is not a list" );
	    foreach ( $json['REQUIRES'] as $f )
	    {
		if ( isset ( $local_file_cache[$f] ) )
		    $flfiles[] = $f;
		else
		if ( isset ( $remote_file_cache[$f] ) )
		    $frfiles[] = $f;
		else
		if ( isset ( $creatables[$f] ) )
		    $cfiles[] = $f;
		else
		    $nflfiles[] = $f;
	    }
	}

	if ( isset ( $json['REMOTE-REQUIRES'] ) )
	{
	    if ( ! is_array
	               ( $json['REMOTE-REQUIRES'] ) )
		ERROR
		    ( "{$template[0]} json" .
		      " REMOTE-REQUIRES is not" .
		      " a list" );
	    foreach ( $json['REMOTE-REQUIRES'] as $f )
	    {
		if ( isset ( $remote_file_cache[$f] ) )
		    $frfiles[] = $f;
		else
		    $nfrfiles[] = $f;
	    }
	}

	$nfcount = count ( $nflfiles )
	         + count ( $nfrfiles );
	if ( $nfcount > 0 )
	{
	    $element =
	        [$template[0],$nflfiles,$nfrfiles];
	    if ( $nfcount == $best_not_found_count )
		$best_not_found[] = $element;
	    else if ( $nfcount < $best_not_found_count )
	    {
		$best_not_found_count = $nfcount;
		$best_not_found = [$element];
	    }
	    continue;
	}

	$fcount = count ( $flfiles )
	        + count ( $frfiles )
		+ count ( $cfiles );
	$element =
	    [$template[0],$flfiles,$frfiles,$cfiles];
	if ( $fcount == $best_found_count )
	    $best_found[] = $element;
	else if ( $fcount > $best_found_count )
	{
	    $best_found_count = $fcount;
	    $best_found = [$element];
	    $best_template = $template;
	    $local_required = $flfiles;
	    $remote_required = $frfiles;
	    $creatable = $cfiles;
	}
    }

    if ( count ( $best_found ) == 1 )
	return $best_template;
    else if ( count ( $best_found ) > 1 )
    {
	$errors[] =
	    "too many templates found with the same" .
	    " number of existing or creatable" .
	    " required files:";
	foreach ( $best_found as $e )
	{
	    $errors[] = pretty_template ( $e[0] )
	              . ' NEEDS:';
	    if ( ! empty ( $e[1] ) )
	        $errors[] = '    LOCAL '
		          . implode ( ',', $e[1] );
	    if ( ! empty ( $e[2] ) )
	        $errors[] = '    REMOTE '
		          . implode ( ',', $e[2] );
	    if ( ! empty ( $e[3] ) )
	        $errors[] = '    CREATABLE '
		          . implode ( ',', $e[3] );
	}
    }
    else
    {
	$errors[] =
	    "no template found whose required" .
	    " files all exist or are creatable;" .
	    " closest are:";
	foreach ( $best_not_found as $e )
	{
	    $errors[] = pretty_template ( $e[0] )
	              . ' NEEDS:';
	    if ( ! empty ( $e[1] ) )
	        $errors[] = '    LOCAL '
		          . implode ( ',', $e[1] );
	    if ( ! empty ( $e[2] ) )
	        $errors[] = '    REMOTE '
		          . implode ( ',', $e[2] );
	}
    }

    return NULL;
}

// Find the .shout file in directory $dir and abort
// any running shell script outputting to that file.
// If the script is still running, sends a HUP signal
// to the process group and checks that the group
// leader is no longer running.  If it is still
// running, sends a KILL signal and checks.  If
// still running, returns.  Checks delay as long as
// 0.5 seconds to see if process dies.
//
// If ....shout does not exist or exists and has
// pid and pid is not initially running, returns ''.
// Otherwise returns a non-empty string message which
// is one of following:
//
//	....sh did not start properly
//		(i.e., no pid found in ....shout)
//      ....sh killed by SIGHUP
//      ....sh killed by SIGKILL
//      failed to kill ....sh (pid ...) with SIGKILL

function abort_dir ( $dir )
{
    global $epm_data;
    $d = "$epm_data/$dir";
    $c = @scandir ( $d );
    if ( $c === false ) return '';

    $basename = NULL;
    foreach ( $c as $fname )
    {
	if ( preg_match ( '/^(.+)\.shout$/',
			  $fname, $matches ) )
	{
	    $basename = $matches[1];
	    break;
	}
    }
    if ( ! isset ( $basename ) ) return '';

    $c = @file_get_contents ( "$d/$basename.shout" );
    if ( $c === false
	 ||
	 ! preg_match ( '/^(\d+) PID\n/',
			$c, $matches ) )
	return "$dir/$basename.sh did not" .
	       " start properly";

    $pid = $matches[1];
    if ( ! is_running ( $pid ) ) return '';
    exec ( "kill -s HUP -$pid >/dev/null 2>&1" );
        // For some reason sending QUIT instead of HUP
	// does not work.
    $time = microtime ( true ) + 0.5;
    while ( microtime ( true ) < $time )
    {
        usleep ( 100000 );
	if ( ! is_running ( $pid ) )
	    return "$dir/$basename.sh killed by SIGHUP";
    }
    exec ( "kill -s KILL -$pid >/dev/null 2>&1" );
    while ( microtime ( true ) < $time )
    {
        usleep ( 100000 );
	if ( ! is_running ( $pid ) )
	    return
	        "$dir/$basename.sh killed by SIGKILL";
    }
    return "failed to kill $dir/$basename.sh" .
           " (pid $pid) with SIGKILL";
}

// Clean up a working or run directory.  Calls abort_dir
// to kill any process with a .shout file.  Then
// executes rm -rf on the directory.  Lastly, creates a
// new directory under the same name.
//
// Directory name is relative to epm_data.
//
// Warnings are issued if a .sh file process is killed
// or did not start properly (no `$$ PID' line in .shout
// file).
//
function cleanup_dir ( $dir, & $warnings )
{
    global $epm_data;

    $m = abort_dir ( $dir );
    if ( $m != '' ) $warnings[] = $m;

    $d = "$epm_data/$dir";
    if ( file_exists ( $d ) )
    {
        exec ( "rm -rf $d" );
	clearstatcache();
    }

    $m = umask ( 06 );
    if ( ! mkdir ( $d, 02771) )
	// Must give o+x permission so epm_sandbox can
	// execute programs that are in directory.
	//
	ERROR ( "could not make $dir" );
    umask ( $m );
}

// Link files from the required lists into the working
// directory.  The required lists are computed by
// find_control and only contain names of last
// components, that for remoted_required must be looked
// up in the remote_file_cache.  It is a fatal error if
// a required file is NOT listed in the appropriate
// cache.
//
// Errors cause error messages to be appended to errors.
//
// WARNING: $workdir must not contain `.'.  Its
//          components are each replaced by `..' to
//          make the name of $epm_data relative to
//          $workdir.
//
function link_required
	( $local_required, $remote_required, $workdir,
	  & $errors )
{
    global $epm_data,
           $local_file_cache, $remote_file_cache;

    if ( preg_match ( '/\./', $workdir ) )
        ERROR ( "\$workdir $workdir contains a '.'" );

    $reldir = preg_replace
        ( '#[^/]+#', '..', $workdir );
	// Name of $epm_data relative to $epm_data/
	// $workdir, used so that symbolic link can be
	// printed without printing name of $epm_data.

    // Make list of elements of form [file,target,link]
    // where target is to become the value of the
    // symbolic link in workdir, and file is the name of
    // the file relative to $epm_data.
    //
    $list = [];
    foreach ( $local_required as $f )
    {
	if ( ! isset ( $local_file_cache[$f] ) )
	    ERROR ( "link_required: $f in" .
	            " \$local_required not" .
	            " in \$local_file_cache" );
	$d = $local_file_cache[$f];
        $list[] = ["$d/$f", "../$f", $f];
    }
    foreach ( $remote_required as $f )
    {
	if ( ! isset ( $remote_file_cache[$f] ) )
	    ERROR ( "link_required: $f in" .
	            " \$remote_required not" .
	            " in \$remote_file_cache" );
	$d = $remote_file_cache[$f];
        $list[] = ["$d/$f", "$reldir/$d/$f", $f];
    }

    foreach ( $list as $e )
    {
	list ( $f, $t, $l ) = $e;
	$g = "$epm_data/$f";

	if ( preg_match ( '/\./', $l ) )
	{
	    if ( ! is_readable ( $g ) )
	    {
		$errors[] = "$f is not readable";
		continue;
	    }
	}
	else
	{
	    if ( ! is_executable ( $g ) )
	    {
		$errors[] = "$f is not executable";
		continue;
	    }
	}

	if ( ! symbolic_link
	           ( $t, "$epm_data/$workdir/$l" ) )
	{
	    $errors[] = "cannot symbolically link"
	              . " $workdir/$l to $t";
	    continue;
	}
    }
}

// Return COMMANDS list from control as updated by
// the $argument_map.  The latter must be [] if it is
// not used.  Load_argument_map must be called before
// this function is called.
//
// In order to be recognized as an argument in a
// command, the argument must be preceeded by $, which
// will be removed when the argument is replaced.
//
function get_commands ( $control )
{
    global $argument_map;

    if ( ! isset ( $control[2]['COMMANDS'] ) )
        return [];
    $commands = $control[2]['COMMANDS'];
    $map = [];
    foreach ( $argument_map as $key => $value )
        $map['\$' . $key] = $value;
    return substitute_match
               ( $commands, $map );
}

// Compile $commands into the file:
//
//	$epm_data/$dir/$base.sh
//
// This is compiled so that the first line of the
// output file (the $base.shout file) will have the
// form `i PID' where i is the shell process identifier,
// and when the shell terminates the output file will
// end with '::n e DONE\n', where n is the line number
// of the terminating command's first line, and e is the
// exit code.  If the run terminates normally, n is 'D'
// and e is 0.
//
// The status map is initialized in $map.  See update_
// workmap for its format.  Initially all entries have
// the form [statfile, 'X'].
//
function compile_commands
	( & $map, $base, $dir, $commands )
{
    global $epm_data;

    $r = '';     // Value to write to $base.sh
    $map = [];   // Runmap.
    $n = 0;      // Line count
    $cont = 0;   // Next line is continuation.
    $r .= "trap 'exit 129' HUP" . PHP_EOL;
                 // If we do not do this, sending HUP
		 // returns exit code 0.
    $r .= "trap 'echo ::\$n \$? DONE' EXIT" . PHP_EOL;
    $r .= "n=B; echo $$ PID" . PHP_EOL;
    $r .= "n=B; set -e" . PHP_EOL;
    foreach ( $commands as $line )
    {
        ++ $n;
	if ( ! $cont )
	    $r .= "n=$n; $line" . PHP_EOL;
	else
	    $r .= $line . PHP_EOL;

	if ( preg_match
	         ( '/-status\h+(\H+\.[a-z]\d*stat)\h/',
	           $line, $matches ) )
	    $map[$n] = [$matches[1],'X'];

        $cont = preg_match ( '/^(|.*\h)\\\\$/', $line );
    }
    $r .= "n=D; exit 0" . PHP_EOL;
    if ( ! file_put_contents 
	    ( "$epm_data/$dir/$base.sh", $r ) )
	ERROR ( "cannot write $dir/$base.sh" );

    krsort ( $map, SORT_NUMERIC );
}

// Update the $work['MAP'] status map by reading status
// files in last-line-first order.  Return a list of
// keys whose time values have changed.  Inputs
// $work['DIR'].
//
// A status map is a map from command line numbers
// sorted in last-line-number-first order to:
//
//     [statfile, 'X']
//       if statfile does not exist yet
//     [statfile, 'R', time]
//       if statfile subprocess is still running
//     [statfile, 'S', time]
//       if statfile subprocess is completed with exit
//       code 0
//     [statfile, 'F', time, exitcode, message]
//       if statfile subprocess is completed with non-0
//       exit code, where message is a string giving
//       the cause of subprocess termination
//
// Here statfile is the last component of the name of a
// *.*stat status file, and time is the total CPU
// execution time in seconds of the subprocess (so far).
// If a subprocess was terminated by a signal, the
// signal number is added to 128 to make the exitcode.
//
function update_workmap ()
{
    global $_SESSION, $work;

    $workdir = $work['DIR'];
    $map = & $work['MAP'];
    $r = [];
    foreach ( $map as $key => $e )
    {
	if ( $e[1] == 'S' || $e[1] == 'F' )
	    continue;
	$stat = get_status ( $workdir, $e[0] );
	if ( $stat != NULL )
	{
	    if ( $e[1] == 'X' || $e[2] != $stat[2] )
	        $r[] = $key;
	    $map[$key] = $stat;
	}
    }
    return $r;
}

// Read epm_sandbox -status file $workdir/$sfile and
// return value to store in $work['MAP'] entry (but
// do not store it).
//
// If status file could not be read or was misformatted,
// return NULL.  In the misformatted case, retries are
// done every 10 milliseconds for 2 seconds.  In the
// not-readable case return is immediate.
//
function get_status ( $workdir, $sfile )
{
    global $epm_data;

    $count = 0;
    while ( true )
    {
        if ( $count == 200 ) return NULL;
	    // Give up after 2 seconds.
	elseif ( $count != 0 ) usleep ( 10000 );
	    // Poll every 10 milliseconds.
	$count += 1;

	$c = @file_get_contents
	    ( "$epm_data/$workdir/$sfile" );
	if ( $c === false )
	    return NULL;
	$c = trim ( $c );
	$c = explode ( ' ', $c );
	if ( count ( $c ) != 19 ) continue;
	if ( $c[0] != $c[18] ) continue;
	$state    = $c[1];
	$cputime  = $c[3];
	$space    = $c[4];
	$filesize = $c[7];
	$sig      = $c[11];
	$T        = $c[12];
	    // T is as in '-SIGXXX T'.
	$exitcode = $c[13];
	$signal   = $c[14];
	$usertime = $c[15];
	$systime  = $c[16];
	break;
    }

    if ( ! preg_match ( '/^\d*(|\.\d+)$/', $usertime )
         ||
         ! preg_match ( '/^\d*(|\.\d+)$/', $systime ) )
        return NULL;

    if ( $sig != 0 )
    {
        if ( $cputime == 'unlimited' || $cputime > $T )
	    $cputime = $T;
    }

    $time = sprintf ( '%.3f', $usertime + $systime );

    if ( $state == 'S' )
    {
        $state = 'E';
	$exitcode = $signal + 128;
    }

    if ( $state == 'R' )
        return [$sfile, 'R', $time];
    elseif ( $state == 'E' && $exitcode == 0 )
        return [$sfile, 'S', $time];
    elseif ( $state == 'E' )
    {
        $m = get_exit_message
	    ( $exitcode, $cputime, $filesize );
	return [$sfile, 'F', $time, $exitcode, $m];
    }
    else
        return NULL;
}

function get_exit_message
	( $code, $cputime = NULL, $filesize = NULL )
{
    global $epm_score_file_written;

    if ( $code <= 128 ) switch ( $code )
    {
	case 1:
	    return 'Command Failed: see Error Output'
	         . ' (*.*err) File(s)';
	case $epm_score_file_written:
	    return 'Score File Written';
	case 120:
	    return 'Interpreter Failed While Exiting';
	case 126:
	    return 'Invoked Command Could Not'
		 . ' Execute';
	case 127:
	    return 'Command Not Found';
	case 128:
	    return 'Invalid Argument to Exit';
	default:
	    return "Command Failed with Exit Code"
	         . " $code";
    }
    elseif ( $code <= 256 ) switch ( $code - 128 )
    {
	case 24:
	    return "CPU Time Limit"
	         . ( isset ( $cputime ) ?
	             " ($cputime sec)" : "" )
	         . " Exceeded";
	    break;
	case 25:
	    return "Output File Size Limit"
	         . ( isset ( $filesize ) ?
	             " ($filesize bytes)" : "" )
		 . " Exceeded";
	    break;
	case 1:
	    return 'Terminated by Hangup Signal';
	    break;
	case 2:
	    return 'Terminated by Interrupt Signal';
	    break;
	case 3:
	    return 'Terminated by Quit Signal';
	    break;
	case 6:
	    return 'Terminated by Abort';
	    break;
	case 8:
	    return 'Terminated by Floating Point'
	         . ' Exception';
	    break;
	case 9:
	    return 'Terminated by Kill Signal';
	    break;
	case 7:
	case 10:
	case 11:
	    return 'Terminated by Invalid Memory'
	         . ' Reference';
	    break;
	case 13:
	    return 'Terminated by Broken Pipe';
	    break;
	case 14:
	    return 'Terminated by Alarm Timer';
	    break;
	case 15:
	    return 'Terminated by Termination Signal';
	    break;
	default:
	    return "Command Failed with Signal "
	         . ( $code - 128 );
    }
    else
	return "Command Failed with Exit Code"
	     . " $code";
}

// Execute $base.sh within $epm_data/$dir in background
// with empty standard input.  Put standard output in
// $base.shout and standard error in $base.sherr.
//
// If $base.sh does not start properly (does not write
// `$$ PID\n' to output file within $epm_shell_timeout
// seconds), outputs an error message.
//
function execute_commands ( $base, $dir, $errors )
{
    global $epm_data, $epm_home, $epm_web,
           $aid, $problem, $epm_shell_timeout;

    $r = '';
    $r .= "cd $epm_data/$dir" . PHP_EOL;
    $r .= "export EPM_WEB=$epm_web" . PHP_EOL;
    $r .= "export EPM_HOME=$epm_home" . PHP_EOL;
    $r .= "export EPM_DATA=$epm_data" . PHP_EOL;
    $r .= "export EPM_AID=$aid" . PHP_EOL;
    $r .= "export EPM_PROBLEM=$problem" . PHP_EOL;
    $r .= "export EPM_DIR=$dir" . PHP_EOL;
    $r .= "export BIN=$epm_home/bin" . PHP_EOL;
    $r .= "exec 0<&-" . PHP_EOL;
    $r .= "exec 1<&-" . PHP_EOL;
    $r .= "exec 2<&-" . PHP_EOL;
    $r .= "exec 3<&-" . PHP_EOL;
    $r .= "exec 4<&-" . PHP_EOL;
    $r .= "exec 5<&-" . PHP_EOL;
    $r .= "exec 6<&-" . PHP_EOL;
	// Must kill fd 3,4,5,6 as leaving them open
	// forces the connection to the browser to
	// stay open and prevents xhttp response from
	// completing on exit.
    $r .= "setsid bash $base.sh >$base.shout" .
          "    2>$base.sherr &" .  PHP_EOL;
	// bash appears to flush echo output even when
	// stdout is redirected to a file, and so
	// `echo $$ PID;' promptly echoes PID.
	//
	// setsid turns the bash execution into its
	// own process group so it can be killed as
	// a process group.
    exec ( $r );

    $count = 0;
    $f = "$dir/$base.shout";
    while ( true )
    {
	$c = @file_get_contents  ( "$epm_data/$f" );
	if ( $c !== false
	     &&
	     preg_match ( '/^(\d+) PID\n/',
	                  $c, $matches ) )
	    break;

	// Poll every 10 ms for $epm_shell_timeout
	// seconds.

	usleep ( 10000 );
	if ( $count == 100 * $epm_shell_timeout )
	{
	    $errors[] = "$dir/$base.sh failed to start";
	    return;
	}
	$count += 1;
    }
}
// This _2 version is just used to prove that FDs
// 3, 4, 5, 6 need to be closed even for proc_open.
// However, as proc_close cannot be used, it is
// not completely correct.
//
function execute_commands_2 ( $base, $dir )
{
    global $epm_data, $epm_home, $aid, $problem;

    $cwd = "$epm_data/$dir";

    $desc = array (
        0 => ['file', '/dev/null', 'r'],
	1 => ['file', "$cwd/$base.shout", 'w'],
	2 => ['file', "$cwd/$base.sherr", 'w'],
        3 => ['file', '/dev/null', 'r'],
        4 => ['file', '/dev/null', 'r'],
        5 => ['file', '/dev/null', 'r'],
        6 => ['file', '/dev/null', 'r'] );
	// Must kill fd 3,4,5,6 as leaving them open
	// forces the connection to the browser to
	// stay open and prevents xhttp response from
	// completing on exit.

    $env = getenv();
    $env['EPM_HOME'] = $epm_home;
    $env['EPM_DATA'] = $epm_data;
    $env['EPM_AID'] = $aid;
    $env['EPM_PROBLEM'] = $problem;
    $env['EPM_DIR'] = $dir;
    $env['BIN'] = "$epm_home/bin";

    $cmd = "bash $base.sh";
    $process = proc_open
        ( $cmd, $desc, $pipes, $cwd, $env );
	// $process cannot cross to the page
	// invocation that would close it.
}

// First update_work_results is called, and then
// update_workmap.  Then return an HTML listing that
// displays the commands started by start_make_file
// and their results.
//
// The listing begins with
//
//	<table id='command_table'>
//
// and ends with '</table>' followed by error messages,
// if any.  Error messages are output as
//
//    <pre class='error-message'>$message</pre>
//
// Each command line gets a row in a table, and if that
// command line number has 'X' or 'R' state in the
// runmap, the second column for the row is given the
// HTML:
//
//    <td class='time'><pre id='stat_time$n'><pre></td>
//
function get_commands_display ( & $display )
{
    global $epm_data, $work, $epm_score_file_written,
           $_SESSION;

    $workdir = $work['DIR'];
    $workbase = $work['BASE'];
    $map = & $work['MAP'];
    $r = update_work_results();
    update_workmap();

    $r_line = NULL;
    $r_message = NULL;

    if ( $r === false )
    {
        $r_line = 1;
	$r_message = "run $workbase.sh died for no good"
	           . " reason, try again";
    }
    elseif ( is_array ( $r ) && $r[0] == 'B' )
    {
        $r_line = 1;
	$r_message = "run $workbase.sh died during"
	           . " startup, try again";
    }
    elseif (    is_array ( $r )
             && $r[0] != 'D'
	     && $r[1] != $epm_score_file_written )
    {
	$r_line = $r[0];
	$r_message = get_exit_message ( $r[1] );

	// If there is a failed ('F') runmap entry
	// with the same exit code ($r[1]) suppress
	// the r_message.
	//
	foreach ( $map as $key => $e )
	{
	    if ( $e[1] == 'F' && $e[3] == $r[1] )
	    {
		$r_line = NULL;
		$r_message = NULL;
		break;
	    }
	}
    }

    $display = "<table id='command_table'>";
    $c = @file_get_contents 
	    ( "$epm_data/$workdir/$workbase.sh" );
    if ( $c === false )
	ERROR ( "cannot read $workdir/$workbase.sh" );
    $c = explode ( "\n", $c );
    $n = 0;
    $cont = 0;
    $stars = '';
    $messages = [];
    foreach ( $c as $line )
    {
        if ( ! $cont
	     &&
	     ! preg_match ( '/^n=([0-9]+);(.*)$/',
	                    $line, $matches ) )
	    continue;
	++ $n;
	if ( ! $cont )
	{
	    if ( $n != $matches[1] )
	        ERROR ( "n={$matches[1]} in" .
		        " $workbase.sh should" .
			" equal $n" );
	    $line = $matches[2];
	}
	$line = rtrim ( $line );
	$hline = htmlspecialchars ( $line );
	$display .= "<tr><td><pre>$hline</pre></td>";

	$mstars = [];
	if ( isset ( $map[$n] ) )
	{
	    $display .= "<td class='time'>";
	    $mentry = $map[$n];

	    $state = $mentry[1];
	    if ( $state == 'X' )
	    {
		$display .=
		    "<pre id='stat_time$n'></pre>";
	    }
	    elseif ( $state == 'R' )
	    {
		$display .=
		    "<pre id='stat_time$n'>" .
		    "{$mentry[2]}s</pre>";
	    }
	    elseif ( $state == 'S' )
		$display .= "<pre>{$mentry[2]}s<pre>";
	    else
	    {
		$stars .= '*';
		$mstars[] = $stars;
		$messages[] = "$stars {$mentry[4]}";
		$display .= "<pre>{$mentry[2]}s<pre>";
	    }
	    $display .= "</td>";
	}
	elseif ( $n == $r_line )
	    $display .= "<td></td>";
	    // Because $mstars will not be empty.

	if ( $n == $r_line )
	{
	    $stars .= '*';
	    $mstars[] = $stars;
	    $messages[] = "$stars $r_message";
	}
	if ( count ( $mstars ) > 0 )
	{
	    $mstars = implode ( ' ', $mstars );
	    $display .=
	        "<td><pre class='error-message'>" .
		"$mstars<pre></td>";
	}
	$display .= "</tr>";
        $cont = preg_match ( '/^(|.*\h)\\\\$/', $line );
    }
    $display .= '</table>';
    if ( count ( $messages ) > 0 )
    {
        foreach ( $messages as $m )
	    $display .=
	      "<br><pre class='error-message'>$m<pre>";
    }
}

// Read $workdir/$workbase.shout and write the result so
// far of running $workdir/$workbase.sh into
//
//	$work['RESULT']
//
// Also return this result.
//
// If the value of $work['RESULT'] already indicates the
// run is finished, this function does nothing but
// return this value.
//
// If the run is finished, the result is [n,code] where
// n is the value of the variable $n in the shell script
// and code is the exit code.  For a normal completion,
// n is 'D'.  For an abnormal completion, n is the line
// number of the first line of the terminating command
// from get_commands.  If n is 'B', a startup command
// terminated, which is an EPM system error.
//
// If the run is not finished and $wait is 0, the result
// is true if the run is running and false if it has
// died.
//
// If the run is not finished, and $wait is > 0, poll
// every 0.1 seconds until the run finishes or dies or
// $wait/10 seconds have elapsed.  If the run finishes
// or dies, the result is as above.  Otherwise the
// result is true to indicate the run is still running.
//
// It is unwise to set $wait to a value larger than 100.
//
function update_work_results ( $wait = 0 )
{
    global $epm_data, $work, $_SESSION;

    $workbase = $work['BASE'];
    $workdir = $work['DIR'];
    $result = $work['RESULT'];
    if ( is_array ( $result ) || $result === false )
        return $result;

    $result = get_command_results
    	( $workbase, $workdir, $wait );
    $work['RESULT'] = $result;
    return $result;
}

// Ditto but updates $run['RESULT]' by reading
// $rundir/$runbase.shout.
//
function update_run_results ( $wait = 0 )
{
    global $epm_data, $run, $_SESSION;
    $runbase = $run['BASE'];
    $rundir = $run['DIR'];
    $result = $run['RESULT'];
    if ( is_array ( $result ) || $result === false )
        return $result;

    $result = get_command_results
    	( $runbase, $rundir, $wait );
    $run['RESULT'] = $result;
    return $result;
}

// Same as update_work_results, except ignores
// $_SESSION, takes parameters as arguments, reads
// $base.shout, and just returns the result.
//
function get_command_results ( $base, $dir, $wait = 0 )
{
    global $epm_data, $epm_shell_timeout;

    $shout = "$epm_data/$dir/$base.shout";
    $shtime = false;

    // Get pid.
    //
    $count = 0;
    $pid = -1;
    while ( true )
    {
	$c = @file_get_contents  ( $shout );
	if ( $c !== false
	     &&
	     preg_match ( '/^(\d+) PID\n/',
	                  $c, $matches ) )
	{
	    $pid = $matches[1];
	    break;
	}

	if ( $shtime === false )
	    $shtime = @filemtime ( $shout );
	    // Delay doing this until we must.
	    // No point in doing it more than once
	    // as result is cached by PHP (see
	    // PHP clearstatcache function).

	// Typically $shtime is false.  It will only
	// not be false if execution started some
	// time ago and died, in which case this code
	// reduces delay in discovering death.
	//
	if (    $shtime !== false
	     && time() > $shtime + $epm_shell_timeout )
	{
	    return false;
	}

	// Poll every 10 ms for $epm_shell_timeout
	// seconds.

	usleep ( 10000 );
	if ( $count == 100 * $epm_shell_timeout )
	{
	    return false;
	}
	$count += 1;
    }

    // Get result.
    //
    $count = 0;
    $r = true;
    while ( true )
    {
	if ( $c !== false
	     &&
             preg_match ( '/::([A-Z0-9]+) (\d+) DONE$/',
	                  $c, $matches ) )
	{
	    $result = [$matches[1], $matches[2]];
	    return $result;
	}

	// If its not running must be sure shout file
	// does not indicate it is done.
	//
	if ( ! $r )
	{
	    return false;
	}

	// Poll every 100 ms for wait/10 seconds.
	//
	if ( $count >= $wait )
	{
	    return true;
	}
	usleep ( 100000 );
	$count += 1;

	$r = is_running ( $pid );
	$c = @file_get_contents ( $shout );
    }
}

// Execute CHECKS.  Append failures to $errors.
//
// The different types of checks are:
//
//	EMPTY filename
//	    Checks that file exists and is empty.
//
//	SUCCESS filename
//	    Checks that file ends with:
//		::D 0 DONE
//	    and is less than 1 megabyte in size.
//
// Non-existant files are treated as empty (zero length)
// files.
//
function execute_checks ( $checks, & $errors )
{
    global $epm_data, $work, $_SESSION;

    $workdir = $work['DIR'];

    foreach ( $checks as $check )
    {
        if ( ! preg_match ( '/^\s*(\S+)\s+(\S+)\s*$/',
	                    $check, $matches ) )
	{
	    $errors[] = "malformed CHECKS item: $check";
	    continue;
	}
	$test = $matches[1];
	$file = "$workdir/$matches[2]";

	$size = @filesize ( "$epm_data/$file" );
	if ( $size === false ) $size = 0;

	if ( $test == 'EMPTY' )
	{
	    if ( $size != 0 )
		$errors[] = "file $file is not empty";
	    continue;
	}

	if ( $size > 1024 * 1024 )
	{
	    $errors[] = "file $file too large"
	               . " ($size > 1 megabyte)";
	    continue;
	}

	$contents = @file_get_contents
	    ( "$epm_data/$file" );
	if ( $contents === false ) $contents = '';

	if ( $test == 'SUCCESS' )
	{
	    if ( !  preg_match ( '/::D 0 DONE$/',
	                         $contents ) )
	    {
		$name = pathinfo
		    ( $file, PATHINFO_FILENAME );
		$errors[] =
		    "execution of commands in" .
		    " $name.sh failed";
	    }
	    continue;
	}
	

	$errors[] = "bad test in CHECKS item: $check";
    }
}

// Move $keep files, if any, from EPM_WORK DIR to
// $probdir.  A list of the files moved is placed in
// EPM_WORK KEPT.  All file names consist of just the
// last component, with no directory part names.  Error
// messages are appended to $errors.  If files are
// actually moved, $probdir/+altered+ is touched.
//
function move_keep ( $keep, & $errors )
{
    global $epm_data, $probdir, $work, $_SESSION;

    $workdir = $work['DIR'];
    $work['KEPT'] = [];
    $moved = & $work['KEPT'];
    foreach ( $keep as $fname )
    {
        $wfile = "$workdir/$fname";
        $lfile = "$probdir/$fname";
	if ( ! file_exists ( "$epm_data/$wfile" ) )
	{
	    $errors[] = "KEEP file $fname was not"
	              . " made by {$work['TEMPLATE']}";
	    continue;
	}
	if ( ! rename ( "$epm_data/$wfile",
	                "$epm_data/$lfile" ) )
	{
	    $errors[] = "could not rename $wfile to"
	              . " $lfile";
	    continue;
	}
	$moved[] = $fname;
    }
    if ( count ( $moved ) > 0 )
	touch ( "$epm_data/$probdir/+altered+" );
}

// Starts commands and to make file $des from file $src.
// If some file (e.g., $src) is uploaded, its name is
// $upload and its tmp_name is $uploaded_tmp, and it
// will be moved into the working directory (but will
// not be checked for size and other errors).
//
// This function begins by creating an empty EPM_WORK.
// Then it calls load_argument_map with $allow_local_
// optn, followed by find_templates with $src, $des,
// and $condition, followed by find_control.  This
// last computes the control template along with
// lists of files that need to be created and linked
// into the working directory.
//
// Then this function creates the working directory
// creates CREATABLE files that need to be created,
// and symbolically links files into the working
// directory.  If $uploaded is not NULL, the uploaded
// file is moved to the working directory.
//
// Lastly this function obtains the COMMANDS from the
// control, compiles them, and executes them.  This
// function does NOT wait for execution to complete.
// See finish_make_file below.
//
// If there are errors at any stage, error messages are
// appended to $errors and this function immediately
// returns.
//
// This function begins by setting
//
//      $work = []
//
// and puts all warning messages in
//
//     $work['WARNINGS']
//
// Normally these warning messages are ignored if there
// are errors, and returned by finish_make_file
// otherwise.
//
// If there are no errors, this function finishes by
// setting:
//
//     $work['DIR'] to $workdir
//     $work['BASE'] to $workbase
//		the basename of $des
//     $work['MAP'] to the status map initialized by
//         compile_command
//     $work['RESULT'] to true
//     $work['CONTROL'] to the control found by
//         find_control (note this is unset by
//         finish_make_file)
//     $work['TEMPLATE'] to pretty_
//	   template of template file name (for error
//	   messages).
//     $work['KEPT'] to []
//     $work['SHOW'] to []
//     $work['SHOWN'] to false
//     $work['LOCK'] to $lock
//     $work['ALTERED'] to filemtime of
//			$probdir/+altered+, or 0 if
//			that file does not exist
//
// The $lock parameter should be the value of
//
//	LOCK ( "$probdir/+parent+", LOCK_... )
//
// if that directory exists, where the lock must be
// acquired BEFORE load_file_caches is called.  If
// the directory does not exist, $lock should be NULL.
// The lock may be shared or exclusive.
//
function start_make_file
	( $src, $des, $condition,
	  $allow_local_optn,
	  $lock, $workdir,
	  $uploaded, $uploaded_tmp,
	  & $errors )
{
    global $epm_data, $is_epm_test,
           $problem, $probdir, $work, $_SESSION;

    $work = [];
    $work['WARNINGS'] = [];
    $warnings = & $work['WARNINGS'];

    $control = NULL;
    $workbase = NULL;
    $errors_size = count ( $errors );

    $altered = @filemtime
        ( "$epm_data/$probdir/+altered+" );
    if ( $altered === false ) $altered = 0;
    while ( time() <= $altered ) usleep ( 100000 );

    load_argument_map
	( $allow_local_optn, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    find_templates
	( $src, $des, $condition, $templates );
    if ( count ( $templates ) == 0 )
    {
        $errors[] =
	    "there are no templates" .
	    " $src => $des for problem $problem";
	return;
    }

    $control = find_control
	( $templates, $local_required, $remote_required,
	  $creatable, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( is_null ( $control ) )
        ERROR ( "find_control returned NULL with no" .
	        " errors" );

    cleanup_dir ( $workdir, $warnings );

    foreach ( $creatable as $f )
        create_file ( $f, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    foreach ( $creatable as $f )
    {
	if ( ! symbolic_link
	           ( "$epm_data/$probdir/$f",
	             "$epm_data/$workdir/$f" ) )
	    $errors[] = "cannot symbolically link"
	              . " $workdir/$f to $probdir/$f";
    }

    link_required
	( $local_required, $remote_required,
	  $workdir, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( isset ( $uploaded ) )
    {
	$f = "$workdir/$uploaded";
	if ( file_exists ( "$epm_data/$f" ) )
	    ERROR ( "uploaded file is $uploaded but" .
		    " $f already exists" );

	if ( $is_epm_test ?
	     ! rename ( $uploaded_tmp,
	                "$epm_data/$f" ) :
	     ! move_uploaded_file
		   ( $uploaded_tmp,
		     "$epm_data/$f" ) )
	{
	    $errors[] =
		"SYSTEM_ERROR: failed to move" .
		" $uploaded_tmp" .
		" (alias for uploaded $uploaded)" .
		" to $f";
	    return;
	}
    }

    $commands = get_commands ( $control );

    $workbase = pathinfo ( $des, PATHINFO_FILENAME );

    compile_commands
        ( $map, $workbase, $workdir, $commands );
    execute_commands ( $workbase, $workdir, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $work['DIR'] = $workdir;
    $work['BASE'] = $workbase;
    $work['MAP'] = $map;
    $work['RESULT'] = true;
    $work['CONTROL'] = $control;
    $work['TEMPLATE'] = pretty_template ( $control[0] );
    $work['KEPT'] = [];
    $work['SHOW'] = [];
    $work['SHOWN'] = false;
    $work['LOCK'] = $lock;
    $work['ALTERED'] = $altered;
}

// Finish execution of a run started by start_make_file.
//
// This function requires that
//
//	$work['BASE']
//	$work['DIR']
//
// be set and will do nothing if
//
//	$work['CONTROL']
//
// is not set.  Otherwise it unsets this last global
// after getting its value.
//
// This function begins appending $work['WARNINGS'] to
// $warnings, and then deletes files larger than
// $epm_file_maxsize and generates error messages for
// each file so deleted.
//
// Then this function calls update_work_result with zero
// wait.  If the run has died, has an error, or is still
// running, an error message is appended to $errors.
//
// Next files in control SHOW that are readable in
// $work['DIR'] are appended to the file list in
// $work['SHOW'].  Note that readability is checked
// before any files are moved to $probdir.
//
// Then if there have been errors and KEEP-ON-ERROR is
// not set, this function exits.
// 
// Otherwise control CHECKs are run and if they do not
// append error messages to $errors, the files in
// control KEEP are moved to $probdir and appended to
// the file list in $work['KEPT'].
// 
// All file and directory names are relative to
// $epm_data, except that only the last component
// of a file name is listed in $work['SHOW'] and
// $work['KEPT'].
//
function finish_make_file ( & $warnings, & $errors )
{
    global $_SESSION, $probdir, $aid, $problem,
           $epm_data, $work,
           $epm_file_maxsize, $epm_score_file_written;

    if ( ! isset ( $work['CONTROL'] ) )
        return;

    $d = "$probdir/+parent+";
    if ( is_dir ( "$epm_data/$d" ) )
    {
	$lock = LOCK ( $d, LOCK_SH );
        if ( ! isset ( $work['LOCK'] )
	     ||
	     $lock > $work['LOCK'] )
	{
	    $errors[] = "parent of $aid $problem was"
	              . " pushed during command"
		      . " execution";
	    return;
	}
    }

    $altered = @filemtime
        ( "$epm_data/$probdir/+altered+" );
    if ( $altered === false ) $altered = 0;
    if ( $altered > $work['ALTERED'] )
    {
	$errors[] = "$aid $problem was altered by"
		  . " another one of your tabs"
		  . " during command execution";
	return;
    }

    $warnings = array_merge
        ( $warnings, $work['WARNINGS'] );
    $control = $work['CONTROL'];
    unset ( $work['CONTROL'] );
    $workbase = $work['BASE'];
    $workdir = $work['DIR'];
    $errors_size = count ( $errors );

    $fnames = [];
    $fnames = @ scandir ( "$epm_data/$workdir" );
    clearstatcache();
    foreach ( $fnames as $fname )
    {
	$f = "$workdir/$fname";
        if ( is_dir ( "$epm_data/$f" ) )
	    continue;
        $fsize = 0;
	$fsize = @filesize ( "$epm_data/$f" );
	if ( $fsize <= $epm_file_maxsize )
	    continue;
	$errors[] = "deleting $f because it is too"
	          . " large ($fsize bytes)";
	if ( ! unlink ( "$epm_data/$f" ) )
	    $errors[] = "EPM SYSTEM ERROR: failed"
	              . " to delete $f";
    }

    $r = update_work_results();

    if ( $r === false )
        $errors[] = "EPM SYSTEM_ERROR: $workbase.sh"
	          . " died; try again";
    elseif ( $r === true )
        $errors[] = "EPM SYSTEM_ERROR: $workbase.sh"
	          . " did not finish in time";
    elseif ( $r != ['D',0]
             &&
	     $r[1] != $epm_score_file_written )
    {
        $m = get_exit_message ( $r[1] );
        $errors[] = "command line {$r[0]} returned"
	          . " exit code {$r[1]}: $m";
    }

    if ( isset ( $control[2]['SHOW'] ) )
    {
        $show = & $work['SHOW'];
	$d = "$epm_data/$workdir";
        foreach ( $control[2]['SHOW'] as $fname )
	{
	    $s = @filesize ( "$d/$fname" );
	    if ( $s !== false && $s > 0 )
	        $show[] = $fname;
	}
    }

    $keep_on_error =
        ( isset ( $control[2]['KEEP-ON-ERROR'] ) );

    if ( ! $keep_on_error
         &&
	 count ( $errors ) > $errors_size )
        return;

    if ( isset ( $control[2]['CHECKS'] ) )
        execute_checks ( $control[2]['CHECKS'],
	                 $errors );

    if ( ! $keep_on_error
         &&
	 count ( $errors ) > $errors_size )
        return;

    if ( isset ( $control[2]['KEEP'] ) )
        move_keep ( $control[2]['KEEP'], $errors );
}

// Create new $rundir, link $epm_data/$d/$runfile to
// $epm_data/$rundir, compile into $epm_data/$rundir/
// $runbase.sh the command:
//
//	epm_run [-s] $workdir $runfile $runbase.stat \
//		>$runbase.rout 2>$runbase.rerr
//
// and execute $runbase.sh in background.  Here $runbase
// is $runfile without its extension.  The -s option is
// expressed iff $submit is true.  The $d directory is
// the directory in which $runfile is found by load_
// file_caches, and is local if no -s or remote if -s.
// load_file_caches is executed by this function.
//
// WARNING: $rundir must not contain `.'.  Its
//          components are each replaced by `..' to
//          make the name of $epm_data relative to
//          $rundir.
//
// This function begins by setting
//
//      $run  = []
//      $work = []
//	    -- this last to clear displays of work
//             directory
//
// If there are no errors, this function sets:
//
//     $run['DIR'] to $rundir
//     $run['BASE'] to $runbase
//     $run['SUBMIT'] to $submit
//     $run['RESULT'] to true
//     $run['LOCK'] to $lock
//     $run['ALTERED'] to filemtime of
//		       $probdir/+altered+, or 0 if
//		       that file does not exist
//     $run['FINISHED'] false
//
// The $lock parameter should be the value of
//
//	LOCK ( "$probdir/+parent+", LOCK_... )
//
// if that directory exists, where the lock must be
// acquired BEFORE load_file_caches is called.  If
// the directory does not exist, $lock should be NULL.
// The lock may be shared or exclusive.
//
function start_run
	( $workdir, $runfile, $lock, $rundir, $submit,
	            & $errors )
{
    global $epm_data, $work, $run, $_SESSION,
           $local_file_cache, $remote_file_cache,
	   $probdir;

    $run  = [];
    $work = [];

    $errors_size = count ( $errors );

    $altered = @filemtime
        ( "$epm_data/$probdir/+altered+" );
    if ( $altered === false ) $altered = 0;
    while ( time() <= $altered ) usleep ( 100000 );

    load_file_caches();

    if ( preg_match ( '/\./', $rundir ) )
        ERROR ( "\$rundir $rundir contains a '.'" );

    $reldir = preg_replace
        ( '#[^/]+#', '..', $rundir );
	// Name of $epm_data relative to $epm_data/
	// $rundir, used so that symbolic link can be
	// printed without printing name of $epm_data.

    cleanup_dir ( $rundir, $warnings );

    if ( ! $submit )
    {
	if ( ! isset ( $local_file_cache[$runfile] ) )
	{
	    $errors[] = "cannot find $runfile in local"
	              . " directory";
	    return;
	}
	$d = $local_file_cache[$runfile];
	$r = "..";
    }
    else
    {
	if ( ! isset ( $remote_file_cache[$runfile] ) )
	{
	    $errors[] = "cannot find $runfile in remote"
	              . " directory";
	    return;
	}
	$d = $remote_file_cache[$runfile];
	$r = "$reldir/$d";
    }
    $f = "$d/$runfile";

    if ( ! is_readable ( "$epm_data/$f" ) )
    {
	$errors[] = "cannot read $f";
	return;
    }

    if ( ! symbolic_link
               ( "$r/$runfile",
                 "$epm_data/$rundir/$runfile" ) )
    {
	$errors[] = "cannot symbolically link"
		  . " $rundir/$runfile to $f";
	return;
    }

    $runbase = pathinfo ( $runfile, PATHINFO_FILENAME );

    $commands = [ '$BIN/epm_run' .
    		  ($submit ? ' -s' : '' ) . ' \\',
                  "    $runfile" .
		  " $workdir $runbase.stat \\",
		  "    >$runbase.rout 2>$runbase.rerr"];

    compile_commands
        ( $map, $runbase, $rundir, $commands );
    execute_commands ( $runbase, $rundir, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $run['DIR'] = $rundir;
    $run['BASE'] = $runbase;
    $run['SUBMIT'] = $submit;
    $run['RESULT'] = true;
    $run['LOCK'] = $lock;
    $run['ALTERED'] = $altered;
    $run['FINISHED'] = false;
}
 
// Finish run started by start_run.  Before calling
// this, update_run_results must return a value other
// that true.
//
// This function begins setting $run['FINISHED'] to
// true;
//
// Checks for errors, including non-empty .rerr file.
// Then if there are no errors, moves the .rout file
// into $probdir is $submit is false.
//
// If $submit is true and there are no errors, copies
// (not moves) .rout file to $probdir and appends an
// action line to each of
//
//	projects/$project/+actions+
//	projects/$project/$problem/+actions+
//	accounts/$aid/+actions+
//	accounts/$aid/$problem/+actions+
//
// Then locks project/$project/$problem/+submits+ for
// writing and moves .rout file to
//
//	projects/$project/+submits+/
//		 CCCCCC:$aid:$runbase.rout
//
// Here CCCCCC is the value of
//
//	projects/$project/+submits+/+count+
//
// which is an integer that is incremented by 1.  If
// 
//	projects/$project/+submits+
//
// does not exist, it is created and +count+ is
// initialized to 0.  +count+ does not have superfluous
// high order 0's, but when used in a file name it is
// expanded to 6 digits.
//
function finish_run ( & $warnings, & $errors )
{
    global $epm_data, $probdir, $aid, $problem, $run,
           $epm_parent_re, $_SESSION,
	   $epm_time_format;

    $rundir = $run['DIR'];
    $runbase = $run['BASE'];
    $submit = $run['SUBMIT'];
    $result = $run['RESULT'];

    if ( $result === true )
        ERROR ( "finish_run called with 'true'" .
	        " session EPM_RUN RESULT" );
    if ( $run['FINISHED'] )
        ERROR ( "finish_run called with 'true'" .
	        " session EPM_RUN FINISHED" );

    $run['FINISHED'] = true;

    if ( $submit )
        cleanup_dir ( "$probdir/+work+", $discard );
	// Be sure nothing is left to look at after
	// run except $rundir stuff.  This is here
	// in case bin/epm_run failed before it could
	// do it.

    $d = "$probdir/+parent+";
    if ( is_dir ( "$epm_data/$d" ) )
    {
	$lock = LOCK ( $d, LOCK_SH );
        if ( ! isset ( $run['LOCK'] )
	     ||
	     $lock > $run['LOCK'] )
	{
	    $errors[] = "parent of $aid $problem was"
	              . " pushed during run execution";
	    return;
	}
    }

    $altered = @filemtime
        ( "$epm_data/$probdir/+altered+" );
    if ( $altered === false ) $altered = 0;
    if ( $altered > $run['ALTERED'] )
    {
	$errors[] = "$aid $problem was altered by"
		  . " another one of your tabs"
		  . " during run execution";
	return;
    }

    if ( $result === false )
    {
        $errors[] = "$rundir/$runbase.sh died";
	return;
    }
    if ( $result != ['D',0] )
    {
        $m = get_exit_message ( $result[1] );
        $errors[] = "$rundir/$runbase.sh terminated"
	          . " with error code {$result[1]}: $m";
	return;
    }

    $rout = "$rundir/$runbase.rout";
    $rerr = "$rundir/$runbase.rerr";

    $size = @filesize ( "$epm_data/$rerr" );
    if ( $size === false )
    {
	$errors[] = "file $rerr does not exist";
	return;
    }
    if ( $size != 0 )
    {
	$errors[] = "file $rerr is not empty";
	return;
    }
    if ( ! is_readable ( "$epm_data/$rout" ) )
    {
	$errors[] = "file $rout is not readable";
	return;
    }

    $f = "$probdir/$runbase.rout";
    if ( ! $submit )
    {
	if ( ! rename ( "$epm_data/$rout",
	                "$epm_data/$f" ) )
	{
	    $e = "could not rename $rout to $f";
	    WARN ( $e );
	    $errors[] = "EPM SYSTEM ERROR: $e";
	}
	else
	    touch
		( "$epm_data/$probdir/+altered+" );
	return;
    }

    // From here on $submit == true.
    //
    $contents = @file_get_contents
	( "$epm_data/$rout" );
    if ( $contents === false )
	ERROR ( "cannot read $rout" );
    $r = @file_put_contents
	( "$epm_data/$f", $contents );
    if ( $r === false )
	ERROR ( "cannot write $f" );

    $f = "$probdir/+parent+";
    if ( ! is_link ( "$epm_data/$f" ) )
	ERROR ( "$f is not a link" );
    $r = @readlink ( "$epm_data/$f" );
    if ( $r === false )
	ERROR ( "cannot read link $f" );
    if ( ! preg_match
	       ( $epm_parent_re, $r, $matches ) )
	ERROR ( "link $f has bad value $r" );
    $d = $matches[1];
    if ( ! is_dir ( "$epm_data/$d" ) )
        ERROR ( "directory $d does not exist" );
    if ( ! preg_match
	       ( '#^projects/([^/]+)/#',
		 $d, $matches ) )
	ERROR ( "link $f has bad value $r" );
    $project = $matches[1];

    $time = @filemtime ( "$epm_data/$rout" );
    if ( $time === false )
	ERROR ( "cannot stat $rout" );
    $time = strftime ( $epm_time_format, $time );

    if ( preg_match ( '/(?m)^Score:(.*)$/',
		       $contents, $matches ) )
	$score = trim ( $matches[1] );
    else
	$score = 'Undefined Score';

    if ( preg_match
             ( '/(?m)^Maximum-Solution-Time:(.*)$/',
	       $contents, $matches ) )
	$stime = trim ( $matches[1] );
    else
	$stime = 'NAN';

    if ( preg_match
             ( '/(?m)^First-Failed-Test-Case:(.*)$/',
	       $contents, $matches ) )
	$failed_case = trim ( $matches[1] );
    else
	$failed_case = NULL;

    $action = "$time $aid submit $project $problem"
            . " $runbase $stime $score" . PHP_EOL;

    $f = "projects/$project/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $action, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot write $f" );
    $f = "$d/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $action, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot write $f" );
    $f = "accounts/$aid/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $action, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot write $f" );
    $f = "accounts/$aid/$problem/+actions+";
    $r = @file_put_contents
	( "$epm_data/$f", $action, FILE_APPEND );
    if ( $r === false )
	ERROR ( "cannot write $f" );

    if ( isset ( $failed_case ) )
    {
        foreach ( ['in','ftest'] as $ext )
	{
	    $f = "$failed_case.$ext";
	    $g = "$probdir/$f";
	    if ( file_exists ( "$epm_data/$g" ) )
	        continue;
	    if ( ! symbolic_link ( "+parent+/$f",
	                           "$epm_data/$g" ) )
		$errors[] = "cannot link $f to parent";
	    else
		$warnings[] = "linked $f to parent";
	}
    }

    $d = "$d/+submits+";
    $cf = "$d/+count+";
    @mkdir ( "$epm_data/$d", 02770 );
    LOCK ( $d, LOCK_EX );
    $count = @file_get_contents
	( "$epm_data/$cf" );
    if ( $count === false )
	$count = 0;
	// $d is created when the project problem is
	// created but $cf is not.
    $r = @file_put_contents
	( "$epm_data/$cf", $count + 1);
    if ( $r === false )
	ERROR ( "could not write $cf" );
    $count = sprintf ( '%06d', $count );

    $f = "$d/$count:$aid:$runbase.rout";

    if ( ! rename ( "$epm_data/$rout",
                    "$epm_data/$f" ) )
    {
	$e = "could not rename $rout to $f";
	WARN ( $e );
	$errors[] = "EPM SYSTEM ERROR: $e";
    }
}

// Process an uploaded file whose $_FILES[...] value
// is given by the $upload argument.  Selects a template
// with the `UPLOAD uploaded-file-name' condition.
//
// First checks the upload and the uploaded file for
// errors.  If there are any, appends error messages to
// $errors and returns.
//
// Then calls start_make_file treating the uploaded file
// name as the source file and computing the destination
// file name by changing the uploaded file name exten-
// sion to that designated by $upload_target_ext.  The
// $lock parameter is simply passed to start_make_file.
//
// Uploaded file name must match $epm_filename_re and
// have an extension.  Uploaded file must not be larger
// than $epm_upload_maxsize.
//
function process_upload
	( $upload, $lock, $workdir, & $errors )
{
    global $epm_data, $is_epm_test,
           $upload_target_ext, $epm_upload_maxsize,
	   $epm_filename_re;

    $errors_size = count ( $errors );

    if ( ! is_array ( $upload ) )
        ERROR ( 'process_upload: $upload is not' .
	        ' an array' );

    $fname = $upload['name'];
    if ( $fname == '' )
    {
        $errors[] = "missing upload file";
	return;
    }
    if ( ! preg_match ( $epm_filename_re, $fname ) )
    {
        $errors[] = "uploaded file $fname is not"
	          . " legal EPM file name";
	return;
    }
    if ( ! preg_match ( $epm_filename_re, $fname ) )
    {
        $errors[] = "uploaded file $fname is not"
	          . " legal EPM file name";
	return;
    }
    if ( ! preg_match ( '/^([^.]+)\.([^.]+)$/',
                        $fname, $matches ) )
    {
        $errors[] =
	    "uploaded file $fname has no extension";
	return;
    }
    $base = $matches[1];
    $ext = $matches[2];

    if ( ! isset ( $upload_target_ext[$ext] ) )
    {
        $errors[] =
	    "uploaded file $fname has unrecognized" .
	    " extension";
	return;
    }
    $text = $upload_target_ext[$ext];
    $tname = $base;
    if ( $text != "" ) $tname .= ".$text";

    $ferror = $upload['error'];
    if ( $ferror != 0 )
    {
        switch ( $ferror )
	{
	    case UPLOAD_ERR_INI_SIZE:
	    case UPLOAD_ERR_FORM_SIZE:
	        $errors[] = "$fname too large";
		break;
	    case UPLOAD_ERR_PARTIAL:
	    case UPLOAD_ERR_NO_FILE:
	        $errors[] = "$fname upload failed;"
		          . " try again";
		break;
	    default:
	        $e = "uploading $fname, PHP upload"
		   . " error code $ferror";
		WARN ( $e );
		$errors[] = "EPM SYSTEM ERROR: $e";
	}
	return;
    }

    $fsize = $upload['size'];
    if ( $fsize > $epm_upload_maxsize )
    {
        $errors[] =
	    "uploaded file $fname too large;" .
	    " limit is $epm_upload_maxsize";
	return;
    }

    $ftmp_name = $upload['tmp_name'];

    start_make_file
        ( $fname, $tname, "UPLOAD $fname",
          true, $lock, $workdir,
	  $fname, $ftmp_name,
	  $errors );
}

// Create the named file in $probdir.  If the
// file is created, append a message to $warnings
// indicating the file was created.  Errors append
// to $errors.
//
function create_file
	( $filename, & $warnings, & $errors )
{
    global $epm_data, $problem, $probdir;

    $f = "$epm_data/$probdir/$filename";
    if ( @lstat ( $f ) !== false )
    {
	$errors[] = "$filename already exists";
	return true;
    }

    $file_re = 'generate|filter|monitor';
    if ( preg_match ( "/^($file_re)-$problem\$/",
                      $filename, $matches ) )
    {
	$b = $matches[1];
	if ( ! symbolic_link
	           ( "../../../default/epm_default_$b",
		     $f ) )
	    ERROR ( "create_file: cannot symbolically" .
		    " link $filename to" .
		    " ../../../" .
		    "default/epm_default_$b" );
	$warnings[] =
	    "$filename was created by linking to" .
	    " ../../../default/epm_default_$b";
	return true;
    }
    else
    {
        $errors[] =
	    "do not know how to create $filename";
	return false;
    }

}

?>
