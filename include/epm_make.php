<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue Feb 11 10:12:14 EST 2020

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.  Of course uploaded
// files and components cannot have /."
//
// WARNING: No error message, including $sysfail,
//          may contain the value of $epm_data or
//          $epm_home.
//
// To include this program, be sure the following are
// defined.  Also either define $admin_params correctly
// or leave it undefined and this file will define it.

if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $epm_home ) )
    exit ( 'ACCESS ERROR: $epm_home not set' );
if ( ! isset ( $uid ) )
    exit ( 'ACCESS ERROR: $uid not set' );
if ( ! isset ( $problem ) )
    exit ( 'ACCESS ERROR: $problem not set' );

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
    #
    # Sending signal 0 does not actually send a
    # signal but returns status 0 if the process is
    # still running and status non-0 otherwise.
    # Note that the posix_kill function is not
    # available in vanilla PHP.

    return ( $kill_status == 0 );
}

// Problem Parameters:
//
if ( ! isset ( $problem_params ) )
{
    $f = "users/user$uid/$problem/problem.params";
    $problem_params = [];
    if ( is_readable ( "$epm_data/$f" ) )
	$problem_params = get_json ( $epm_data, $f );
}
if ( isset ( $problem_params['remote_dirs'] ) )
    $remote_dirs = $problem_params['remote_dirs'];
else
    $remote_dirs = [];

// Template root directories:
//
$template_roots = [];
if ( is_dir ( "$epm_data/template" ) )
    $template_roots[] = $epm_data;
$template_roots[] = $epm_home;

// Function to get and decode json file, which must be
// readable.  It is a fatal error if the file cannot be
// read or decoded.
//
// The file name is $r/$file, where $r is either
// $epm_home or $epm_data and will NOT appear in any
// error message.
//
function get_json ( $r, $file )
{
    $f = "$r/$file";
    $c = @file_get_contents ( $f );
    if ( $c === false )
	ERROR ( "cannot read readable $file" );
    $c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	// Get rid of `//...' comments.
    $j = json_decode ( $c, true );
    if ( $j === NULL )
    {
	$m = json_last_error_msg();
	ERROR
	    ( "cannot decode json in $file:" . PHP_EOL .
	      "    $m" );
    }
    return $j;
}

// Function to pretty print a template.  Changes
// XXXX:YYYY:ZZZZ to XXXX => YYYY (ZZZZ).
//
function pretty_template ( $template )
{
    if ( ! preg_match ( '/^([^:]+):([^:]+):(.*)$/',
                        $template, $matches ) )
        return $template;
    $r = "{$matches[1]} => {$matches[2]}";
    if ( $matches[3] != "" )
        $r = "$r ({$matches[3]})";
    return $r;
}

// Given a problem name, file names, and a template,
// determine if the template matches the problem and
// file name.  If no, return NULL.  If yes, return an
// array containing the map from wild card symbols to
// their values.  Note that if the template does not
// contain PPPP or any other wildcard, this may be an
// empty array, but will not be NULL.
//
// If PPPP is in the template, replace it with problem
// name before proceeding futher.
//
// Either $filenames is a single name and $template
// is just the source or just the destination part of
// a .tmpl file name, or $filenames has the form
// $srcfile:$desfile an $template is the part of the
// .tmpl file name before the second :.
//
function template_match
    ( $problem, $filenames, $template )
{
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

// Build a cache of templates.  This is a map of the
// form:
//		template => [root, json]
//
// where "{$root}/template/{$template}.tmpl is a
// template file, root is either $epm_home or $epm_data,
// and json is NULL, but will be set to the decoded json
// read when the template file is read as per the
// get_template function below.  If two files with the
// same template are found, only the one appearing
// with the first root in $template_roots is recorded.
// The cache is stored in $template_cache.  No value
// is returned.
//
$template_cache = NULL;
function load_template_cache()
{
    global $template_roots, $template_cache;

    if ( isset ( $template_cache) ) return;
    foreach ( $template_roots as $r )
    {
	$dircontents = @scandir ( "$r/template" );
	if ( $dircontents === false )
	    ERROR ( "cannot read " .
	            ( $r == $epm_data ? "DATA" :
		                        "HOME" ) .
		    "/template" );

	foreach ( $dircontents as $fname )
	{
	    if ( ! preg_match ( '/^(.+)\.tmpl$/',
	                        $fname, $matches ) )
	        continue;
	    $template = $matches[1];
	    if ( isset ( $template_cache[$template] ) )
	        continue;
	    $template_cache[$template] =
	        [ $r, NULL ];
	}
    }
    if ( ! isset ( $template_cache ) )
        ERROR ( "no readable template directories" );
}

// Read the decoded json from a template file as stored
// in the template cache.  Sysfail on errors.
//
function get_template_json ( $template )
{
    global $template_cache;
    load_template_cache();

    if ( ! isset ( $template_cache[$template] ) )
        ERROR ( "get_template called with $template" .
	        " which is not cache key" );
    $pair = & $template_cache[$template];
    $result = & $pair[1];
    if ( ! isset ( $result ) )
    {
	$r = $pair[0];
	$f = "template/{$template}.tmpl";
	if ( ! is_readable ( "$r/$f" ) )
	    ERROR ( "cannot read $f" );
	$result = get_json ( $r, $f );
    }
    return $result;
}

// Go through the template cache and find each template
// that has the given source file name and destination
// file name and matches the given condition (which is
// NULL if the template is to have no CONDITION).
//
// For each template found, list in $templates elements
// of the form:
//
//   [template, root, json]
// 
// containing the information copied from the
//
//	template => [root, json]
//
// but with wildcards in json replaced by their matches
// found from matching the source and destination file
// names and problem name to the template.
//
function find_templates
    ( $problem, $srcfile, $desfile, $condition,
      & $templates )
{
    global $template_cache;
    load_template_cache();

    $templates = [];
    foreach ( $template_cache as $template => $pair )
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
	    ( $problem, "$srcfile:$desfile",
			"$tsrc:$tdes" );

	if ( is_null ( $match ) ) continue;

	$json = get_template_json ( $template );

	$json = substitute_match ( $json, $match );

	if ( isset ( $json['CONDITION'] ) )
	    $cond = $json['CONDITION'];
	else
	    $cond = NULL;
	if ( $cond != $condition ) continue;

	$templates[] =
	    [ $template, $pair[0], $json ];
    }
}

// Get the template.optn file json with overrides from
// earlier template directories and users/user$id
// directory.  Cache result in $template_optn.
//
$template_optn = NULL;
function get_template_optn()
{
    global $template_roots, $epm_data, $uid,
           $template_optn;

    if ( isset ( $template_optn ) )
        return $template_optn;

    $files = [];
    foreach ( array_reverse ( $template_roots ) as $r )
        $files[] = [$r, "template/template.optn"];
    $files[] = [$epm_data,
                "/users/user$uid/template.optn"];

    $template_optn = [];
    foreach ( $files as $e )
    {
	$r = $e[0];
	$f = $e[1];
        if ( ! is_readable ( "$r/$f" ) ) continue;
	$j = get_json ( $r, $f );

	// template.optn values are 2D arrays.
	//
	foreach ( $j as $opt => $description )
	foreach ( $description as $key => $value )
	    $template_optn[$opt][$key] = $value;
    }
    return $template_optn;
}

// Get the PPPP.optn file for problem PPPP from
// $remote_file_cache, if it exists.  Then if
// $allow_local_optn is true, get PPPP.optn from $local_
// file_cache and use it to any override options gotten
// from $remote_file_cache.  Cache the result in
// $problem_optn.
//
$problem_optn = NULL;
function get_problem_optn
	( $problem, $allow_local_optn )
{
    global $epm_data, $problem_optn,
           $local_file_cache, $remote_file_cache;

    if ( isset ( $problem_optn ) )
        return $problem_optn;

    $f = "$problem.optn";
    $files = [];
    if ( isset ( $remote_file_cache[$f] ) )
        $files[] = "{$remote_file_cache[$f]}/$f";
    if (    $allow_local_optn
         && isset ( $local_file_cache[$f] ) )
        $files[] = "{$local_file_cache[$f]}/$f";

    $problem_optn = [];
    foreach ( $files as $f )
    {
        if ( ! is_readable ( "$epm_data/$f" ) )
	    continue;
	$j = get_json ( $epm_data, $f );

	// PPPP.optn values are 1D arrays.
	//
	foreach ( $j as $opt => $value )
	    $problem_optn[$opt] = $value;

    }
    return $problem_optn;
}

// Load the argument map that is to be applied to
// template COMMANDS.  The argument map is computed from
// results of get_template_optn and get_problem_optn.
// Append warnings to $warnings for options that must be
// modified or ignored, and append errors to $errors for
// options that cannot be given any value.
//
$argument_map = NULL;
function load_argument_map
	( $problem, $allow_local_optn,
	  & $warnings, & $errors )
{
    global $template_optn, $problem_optn,
           $argument_map;
    if ( isset ( $argument_map ) ) return;

    get_template_optn();
    get_problem_optn( $problem, $allow_local_optn );

    $arg_map = [];
    $val_map = [];
    foreach ( $problem_optn as $opt => $value )
    {
        if ( ! isset ( $template_optn[$opt] ) )
	    $warnings[] =
	        "$opt option from $problem.optn" .
		" file is not in template.optn file" .
		PHP_EOL .
	        "    and therefore it is illegal and" .
		" its value $value is ignored";
    }
    foreach ( $template_optn as $opt => $description )
    {
	$default = NULL;
        if ( isset ( $description['default'] ) )
            $default = $description['default'];
	$ovalue = NULL;
        if ( isset ( $problem_optn[$opt] ) )
            $ovalue = $problem_optn[$opt];

	$value = NULL;
        if ( isset ( $description['values'] ) )
	{
	    $values = $description['values'];
	    if ( ! is_array ( $values )
	         ||
		 count ( $values ) == 0 )
	    {
	        $errors[] =
		    "badly formatted values member of" .
		    " option $opt in template.optn" .
		    " file; option $opt ignored";
		continue;
	    }

	    if ( isset ( $default ) )
		$value = $default;
	    else
	        $value = $values[0];

	    if ( isset ( $ovalue ) )
	    {
	        if (    array_search
		          ( $ovalue, $values, true )
		     !== false )
		    $value = $ovalue;
		else
		    $warnings[] =
		        "$opt option value $ovalue" .
		        " from $problem.optn file" .
			PHP_EOL .
			"    is not legal, using" .
			" default $value instead";
	    }
	}
        else if ( isset ( $description['type'] ) )
	{
	    $type = $description['type'];
	    if ( isset ( $description['range'] ) )
	        $range = $description['range'];
	    else
	        $range = NULL;

	    // In the following, if all error checks
	    // are passed, either $ovalue or $default
	    // must be set, unless default member is
	    // improperly missing.

	    if ( array_search
	             ( $type,
		       ['args', 'natural', 'float'],
		        true ) === false )
	    {
	        $errors[] =
		    "unknown type $type for option" .
		    " $opt in template.optn file;" .
		    " option ignored";
		continue;
	    }
	    else if ( $type == 'args' )
	    {
	        if ( ! isset ( $default ) )
		    $default = "";
	    }
	    else if ( ! isset ( $range ) )
	    {
		$errors[] =
		    "no range member for option $opt" .
		    " of type $type" . PHP_EOL .
		    " in template.optn file; option" .
		    " ignored";
		continue;
	    }
	    else if ( ! is_array ( $range )
	              ||
		      count ( $range ) != 2 )
	    {
		$errors[] =
		    "badly formatted range member" .
		    " for option $opt of type $type" .
		    PHP_EOL .
		    " in template.optn file; option" .
		    " ignored";
		continue;
	    }
	    else if ( isset ( $ovalue )
	              &&
		      ! is_numeric ( $ovalue ) )
	    {
		$warnings[] =
		    "option $opt value $ovalue from" .
		    " $problem.optn file" .
		    PHP_EOL .
		    " is not numeric; $ovalue ignored";
		$ovalue = NULL;
	    }
	    else if ( isset ( $ovalue )
	              &&
		      $type == 'natural'
		      &&
		      ! preg_match
		            ( '/^\d+$/', $ovalue ) )
	    {
		$warnings[] =
		    "option $opt value $ovalue from" .
		    " $problem.optn file" .
		    PHP_EOL .
		    " is not natural number;" .
		    " $ovalue ignored";
		$ovalue = NULL;
	    }
	    else if ( isset ( $ovalue )
	              &&
		      $ovalue < $range[0] )
	    {
		$warnings[] =
		    "option $opt value $ovalue" .
		    " from $problem.optn file" .
		    " is too small" .
		    PHP_EOL .
		    "    (less than {$range[0]});" .
		    " {$range[0]} used instead";
		$ovalue = $range[0];
	    }
	    else if ( isset ( $ovalue )
	              &&
		      $ovalue > $range[1] )
	    {
		$warnings[] =
		    "option $opt value $ovalue" .
		    " from $problem.optn file" .
		    " is too large" .
		    PHP_EOL .
		    "    (greater than {$range[1]});" .
		    " {$range[1]} used instead";
		$ovalue = $range[1];
	    }

	    if ( isset ( $ovalue ) )
	        $value = $ovalue;
	    else if ( isset ( $default ) )
	        $value = $default;
	    else if ( isset ( $range ) )
	        $value = $range[1];
	    else
	    {
		$errors[] =
		    "no default member for option" .
		    " $opt of type $type in" .
		    PHP_EOL .
		    " template.optn file, and no" .
		    " valid $problem.optn value;" .
		    " option ignored";
		continue;
	    }
	}
	else
	{
	    $errors[] =
                "option $opt in template.optn file" .
		" has neither values" .
		PHP_EOL .
		"    nor type members; option ignored";
	    continue;
	}

	if ( ! isset ( $value ) )
	    ERROR ( "option $opt value not set in" .
	            " by load_argument_map" );

	if ( isset ( $description['argname'] ) )
	{
	    $argname = $description['argname'];
	    if ( isset ( $arg_map[$argname] ) )
	        $arg_map[$argname] .=
		    ' ' . $value;
	    else
	        $arg_map[$argname] = $value;
	}
	else if ( isset ( $description['valname'] ) )
	    $val_map[$description['valname']] = $value;
	else
	    $errors[] =
	        "option $opt in template.optn file" .
		" has neither argname" .
		PHP_EOL .
		"    nor valname members; option" .
		" ignored";
    }

    $argument_map =
        substitute_match ( $arg_map, $val_map );
}

// Build caches of files that may be required.  The
// caches have entries of the form:
//
//	filename => directory
//
// where filename is the last component of the file
// name and directory is the first directory in which
// the file can be found under the full name:
//
//	$epm_data/directory/filename
//
// $remote_file_cache is for the directories listed in
// $remote_dirs.   $local_file_cache is for the files
// listed in the single directory $local_dir.  All
// directories names are relative to $epm_data.  This
// function does NOT return a value.
//
$local_file_cache = NULL;
$remote_file_cache = NULL;
function load_file_caches ( $local_dir )
{
    global $epm_data, $remote_dirs,
           $remote_file_cache, $local_file_cache;

    if ( isset ( $local_file_cache )
         &&
	 isset ( $remote_file_cache ) )
        return;

    $local_file_cache = [];
    $remote_file_cache = [];
    foreach ( $remote_dirs as $dir )
    {
	$c = @scandir ( "$epm_data/$dir" );
	if ( $c === false )
	    ERROR ( "cannot read $dir" );

	foreach ( $c as $fname )
	{
	    if ( preg_match  ( '/^\.+$/', $fname ) )
	        continue;
	    if ( isset ( $remote_file_cache[$fname] ) )
	        continue;
	    $remote_file_cache[$fname] = $dir;
	}
    }

    $c = @scandir ( "$epm_data/$local_dir" );
    if ( $c === false )
	ERROR ( "cannot read $local_dir" );
    foreach ( $c as $fname )
    {
	if ( preg_match  ( '/^\.+$/', $fname ) )
	    continue;
	$local_file_cache[$fname] = $local_dir;
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
// that are required or be created are selected.  It is
// an error if more than one is selected by this rule,
// or if no template meets the constraints.
//
// Any errors cause error messages to be appended to
// the $errors list and NULL to be returned.
//
// Only the last component of a file name is listed in
// $local_required, $remote_required, or $creatable.
// The directory containing a $remote_required file can
// be found using $remote_file_cache.
//
// Load_file_caches must be called before this function
// is called.
//
function find_control
	( $templates,
	  & $local_required, & $remote_required,
	  & $creatable, & $errors )
{
    global $local_file_cache, $remote_file_cache;

    $best_template = NULL;
        // Element of $templates for the first element
	// of $best_found.
    $best_found = [];
        // List of elements of the form [template,
	// lfiles,rfiles,cfiles] where lfiles is the
	// value for $local_required, $rfiles is the
	// value for $remote_required, $cfiles is the
	// value for $creatable, and only templates with
	// NO not found files or creatable and the most
	// total number of found or creatable files are
	// listed.
    $best_found_count = -1;
        // Number of files listed in each element
	// of $best_found.
    $best_not_found = [];
        // List of elements of the form [template,
	// lfiles,rfiles] where lfiles is the list
	// of REQUIRED and LOCAL-REQUIRED files that
	// were not found or CREATABLE, and rfiles
	// is the list of REMOTE-REQUIRED files that
	// were not found, only templates with at least
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
	$fllist = [];
	    // Local required files found.
	$frlist = [];
	    // Remote required files found.
	$clist = [];
	    // Files not found but CREATABLE that can
	    // be local.
	$nfllist = [];
	    // Required files not found and not
	    // creatable that can be local.
	$nfrlist = [];
	    // Required files not found and not
	    // creatable that must be remote.

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
		    $fllist[] = $f;
		else
		if ( isset ( $creatables[$f] ) )
		    $clist[] = $f;
		else
		    $nfllist[] = $f;
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
		    $fllist[] = $f;
		else
		if ( isset ( $remote_file_cache[$f] ) )
		    $frlist[] = $f;
		else
		if ( isset ( $creatables[$f] ) )
		    $clist[] = $f;
		else
		    $nfllist[] = $f;
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
		    $frlist[] = $f;
		else
		    $nfrlist[] = $f;
	    }
	}

	$nfcount = count ( $nfllist )
	         + count ( $nfrlist );
	if ( $nfcount > 0 )
	{
	    $element = [$template[0],$nfllist,$nfrlist];
	    if ( $nfcount == $best_not_found_count )
		$best_not_found[] = $element;
	    else if ( $nfcount < $best_not_found_count )
	    {
		$best_not_found_count = $nfcount;
		$best_not_found = [$element];
	    }
	    continue;
	}

	$fcount = count ( $fllist )
	        + count ( $frlist )
		+ count ( $clist );
	$element =
	    [$template[0],$fllist,$frlist,$clist];
	if ( $fcount == $best_found_count )
	    $best_found[] = $element;
	else if ( $fcount > $best_found_count )
	{
	    $best_found_count = $fcount;
	    $best_found = [$element];
	    $best_template = $template;
	    $local_required = $fllist;
	    $remote_required = $frlist;
	    $creatable = $clist;
	}
    }

    if ( count ( $best_found ) == 1 )
	return $best_template;
    else if ( count ( $best_found ) > 1 )
    {
	$errors[] =
	    "too many templates found with the same" .
	    " number of existing required files:";
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
	    " files exist; closest are:";
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

// Clean up a working directory.  For each .shout file,
// finds its PID and if that is still running, kills it
// with SIGKILL.  Then executes rm -rf on the directory.
// Lastly, creates a new directory under the same name.
//
// Directory name is relative to epm_data.
//
function cleanup_working ( $work )
{
    global $epm_data;
    $d = "$epm_data/$work";
    $c = @scandir ( $d );
    if ( $c !== false )
    {
	foreach ( $c as $fname )
	{
	    if ( ! preg_match ( '/^(.+)\.shout$/',
	                        $fname ) )
	        continue;

	    $fc = @file_get_contents ( "$d/$fname" );
	    if ( $fc === false ) continue;

	    if ( ! preg_match ( '/^(\d+) PID\n/',
	                        $fc, $matches ) )
		continue;
	    $pid = $matches[1];
	    if ( ! is_running ( $pid ) )
	        continue;
	    exec
	        ( "kill -s KILL $pid >/dev/null 2>&1" );
	}
    }

    if ( file_exists ( $d ) )
        exec ( "rm -rf $d" );

    $m = umask ( 06 );
    if ( ! mkdir ( $d, 0771) )
	// Must give o+x permission so epm_sandbox can
	// execute programs that are in working
	// directory.
	//
	ERROR ( "could not make $dir" );
    umask ( $m );
}

// Link files from the required lists into the working
// working directory.  The required lists are computed
// by find_control and only contain names of last
// components, that for remoted_required must be looked
// up in the remote_file_cache.  It is a fatal error if
// a required file is NOT listed in the appropriate
// cache.
//
// Errors cause error messages to be appended to errors.
//
function link_required
	( $local_required, $remote_required, $work,
	  & $errors )
{
    global $epm_data,
           $local_file_cache, $remote_file_cache;

    // Make list of elements of form [file,target,link]
    // where target is to become the value of the
    // symbolic link in work, and file is the name of
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
        $list[] = ["$d/$f", "../../../../$d/$f", $f];
    }

    foreach ( $list as $e )
    {
	$f = $e[0];
	$t = $e[1];
	$l = $e[2];
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

	if ( ! symlink ( $t, "$epm_data/$work/$l" ) )
	{
	    $errors[] = "cannot symbolically link"
	              . " $work/$l to $t";
	    continue;
	}
    }
}

// Return COMMANDS list from control as updated by
// the $argument_map.  The latter must be [] if it is
// not used.  Load_argument_map must be called before
// this function is called.
//
function get_commands ( $control )
{
    global $argument_map;

    if ( ! isset ( $control[2]['COMMANDS'] ) )
        return [];
    $commands = $control[2]['COMMANDS'];
    return substitute_match
               ( $commands, $argument_map );
}

// Compile $commands into the file:
//
//	$epm_data/$work/$runfile.sh
//
// This is compiled so that when its run terminates,
// its output file (its .shout file) will end with
// '::n e DONE\n' where n is the line number of the
// terminating commands first line, e is the exit
// code, and if the run terminates normally, n is 'D'
// and e is 0.
//
// Also set the following:
//
//     $_SESSION['EPM_WORK'] to $work
//     $_SESSION['EPM_RUNFILE'] to $runfile
//     $_SESSION['EPM_RUNMAP'] to a map from
//	 command line numbers sorted in last-line-
//       number-first order to:
//
//	   [filename, 'X']
//	     if file does not exist yet
//	   [filename, 'R', time]
//	     if file subprocess is still running
//	   [filename, 'D', time]
//	     if file subprocess is completed with exit
//	     code 0
//	   [filename, 'F', time, exitcode, message]
//	     if file subprocess is completed with non-0
//           exit code, where message is a string giving
//           the cause of subprocess termination
//
// Here time is the total CPU execution time in seconds
// of the subprocess (so far).  If a subprocess was
// terminated by a signal, the signal number is added to
// 128 to make the exitcode.
//
function compile_commands ( $runfile, $work, $commands )
{
    global $epm_data;

    unset ( $_SESSION['EPM_WORK'] );
    unset ( $_SESSION['EPM_RUNFILE'] );
    unset ( $_SESSION['EPM_RUNMAP'] );

    $r = '';  // Value to write to $runfile
    $m = [];  // Runmap.
    $n = 0;   // Line count
    $cont = 0;   // Next line is continuation.
    $r .= "trap 'echo ::\$n \$? DONE' EXIT" . PHP_EOL;
    $r .= "n=B; echo $$ PID" . PHP_EOL;
    $r .= "n=B; set -e" . PHP_EOL;
    foreach ( $commands as $line )
    {
        ++ $n;
	if ( ! $cont )
	    $r .= "n=$n; $line" . PHP_EOL;
	else
	    $r .= "          $line" . PHP_EOL;

	if ( preg_match
	         ( '/-status\h+(\H+\.[a-z]\d*stat)\h/',
	           $line, $matches ) )
	    $m[$n] = [$matches[1],'X'];

        $cont = preg_match ( '/^(|.*\h)\\\\$/', $line );
    }
    $r .= "n=D; exit 0" . PHP_EOL;
    if ( ! file_put_contents 
	    ( "$epm_data/$work/$runfile.sh", $r ) )
	ERROR ( "cannot write $work/$runfile.sh" );

    krsort ( $m, SORT_NUMERIC );
    $_SESSION['EPM_WORK'] = $work;
    $_SESSION['EPM_RUNFILE'] = $runfile;
    $_SESSION['EPM_RUNMAP'] = $m;
}

// Update $_SESSION['EPM_RUNMAP']map by reading status
// files in last-line-first order.  Return true if one
// of the entries newly updated has failed ('F') state
// with the given exitcode, and false otherwise.
//
function update_runmap ( $exitcode = -1 )
{
    $work = & $_SESSION['EPM_WORK'];
    $m = & $_SESSION['EPM_RUNMAP'];
    $r = false;
    foreach ( $m as $key )
    {
        $e = $m[$key];
	if ( $e[1] != 'X' ) continue;
	$stat = get_status ( $work, $e[0] );
	if ( $stat != NULL )
	{
	    $m[$key] = $stat;
	    if (    $stat[1] == 'F'
	         && $stat[3] == $exitcode )
		$r = true;
	}
    }
    return $r;
}

// Read epm_sandbox -status file $work/$sfile and return
// value to store in $_SESSION['EPM_RUNMAP'] entry.
//
// If status file could not be read or was misformatted,
// return NULL.  In the misformatted case, retries are
// done every 10 milliseconds for 2 seconds.  In the
// not-readable case return is immediate.
//
function get_status ( $work, $sfile )
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
	    ( "$epm_data/$work/$sfile" );
	if ( $c === false )
	    return NULL;
	$c = explode ( ' ', $c );
	if ( count ( $c ) != 17 ) continue;
	if ( $c[0] != $c[16] ) continue;
	$state    = $c[1];
	$cputime  = $c[3];
	$space    = $c[4];
	$filesize = $c[7];
	$exitcode = $c[11];
	$signal   = $c[12];
	$usertime = $c[13];
	$systime  = $c[14];
	break;
    }

    if ( ! preg_match ( '/^\d*(|\.\d+)$/', $usertime )
         ||
         ! preg_match ( '/^\d*(|\.\d+)$/', $usertime ) )
        return NULL;

    $time = stringf ( '%.3f', $usertime + $systime );

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
    if ( $code <= 128 ) switch ( $code )
    {
	case 126:
	    return 'invoked command could not'
		 . ' execute';
	case 127:
	    return 'command not found';
	case 128:
	    return 'invalid argument to exit';
	default:
	    return "command failed with exit code"
	         . " $code";
    }
    elseif ( $code <= 256 ) switch ( $code - 128 )
    {
	case 24:
	    return "CPU time limit"
	         . ( isset ( $cputime ) ?
	             " ($cputime sec)" : "" )
	         . " exceeded";
	    break;
	case 25:
	    return "output file size limit"
	         . ( isset ( $filesize ) ?
	             " ($filesize bytes)" : "" )
		 . " exceeded";
	    break;
	case 1:
	    return 'terminated by hangup signal';
	    break;
	case 2:
	    return 'terminated by interrupt signal';
	    break;
	case 3:
	    return 'terminated by quit signal';
	    break;
	case 6:
	    return 'terminated by abort';
	    break;
	case 8:
	    return 'terminated by floating point'
	       . ' exception signal';
	    break;
	case 9:
	    return 'terminated by kill signal';
	    break;
	case 7:
	case 10:
	case 11:
	    return 'terminated invalid memory'
	       . ' reference';
	    break;
	case 13:
	    return 'terminated by broken pipe';
	    break;
	case 14:
	    return 'terminated by alarm timer';
	    break;
	case 15:
	    return 'terminated by termination signal';
	    break;
	default:
	    return "command failed with signal "
	         . ( $code - 128 );
    }
    else
	return "command failed with exit code"
	     . " $code";
}

// Execute $runfile.sh within $epm_data/$work in
// background with empty standard input.  Put standard
// output in $runfile.shout and standard error in
// $runfile.sherr.  The standard output is line
// buffered (as per stdbuf(1) -oL ).
//
function execute_commands ( $runfile, $work )
{
    global $epm_data, $epm_home, $uid, $problem;

    $r = '';
    $r .= "cd $epm_data/$work" . PHP_EOL;
    $r .= "export EPM_HOME=$epm_home" . PHP_EOL;
    $r .= "export EPM_DATA=$epm_data" . PHP_EOL;
    $r .= "export EPM_UID=$uid" . PHP_EOL;
    $r .= "export EPM_PROBLEM=$problem" . PHP_EOL;
    $r .= "export EPM_WORK=$work" . PHP_EOL;
    $r .= "bash $runfile.sh >$runfile.shout" .
                         " 2>$runfile.sherr &" .
			 PHP_EOL;
	// bash appears to flush echo output even when
	// stdout is redirected to a file, and so
	// `echo $$ PID;' promptly echoes PID.
    $desc = @popen ( $r, 'w' );
    if ( $desc === false )
        ERROR ( "cannot execute $runfile.sh in $work" );
    if ( @pclose ( $desc ) == -1 )
        ERROR ( "error executing pclose for" .
	        " $runfile.sh in $work" );
}

# Using data recorded in $_SESSION by compile_commands,
# output, and updating this data, return HTML listing
# the compiled commands and their exit codes messages.
#
# First get_command_results is called, and then
# update_runmap.
#
# Each command line gets a row in a table, and if that
# command line number has 'X' or 'R' state in the
# runmap, the second column for the row is given the
# HTML:
#
#    <td><pre id='stat_time$n'><pre></td>
#
# The lowest line number for which such a second column
# is output is returned; if there are no such -1 is
# returned.  The HTML is returned in $display.
#	
function get_commands_display ( & $display )
{
    global $epm_data;

    $work = $_SESSION['EPM_WORK'];
    $runfile = $_SESSION['EPM_RUNFILE'];
    $m = & $_SESSION['EPM_RUNMAP'];
    $r = get_command_results ( $runfile, $work, 0 );
    $r_line = NULL;
    $r_message = NULL;
    $exitcode = -1;
    if ( $r === false )
        ERROR ( 'run died for no good reason,' .
	        ' try again' );
    elseif ( $r !== true )
    {
        if ( ! update_runmap ( $r[1] ) )
	{
	    $r_line = $r[0];
	    $r_message = get_exit_message ( $r[1] );
	}
    }
    else
	update_runmap();

    $keys = array_keys ( $m );
    sort ( $keys, SORT_NUMERIC );
    $last = count ( $keys );
    $i = 0;

    $display = "<table id='command_table'>" . PHP_EOL;
    $display_status = false;
    $c = @file_get_contents 
	    ( "$epm_data/$work/$runfile.sh" );
    if ( $c === false )
	ERROR ( "cannot read $work/$runfile.sh" );
    $c = explode ( "\n", $c );
    $count = 0;
    $cont = 0;
    $stars = '';
    $messages = [];
    if ( $r_line != NULL )
    {
        $stars = '*';
	$messages[] = "$stars $r_message";
    }
    foreach ( $c as $line )
    {
        if ( ! $cont
	     &&
	     ! preg_match ( '/^n=[0-9]+;(.*)$/', $line,
	                    $matches ) )
	    continue;
	++ $count;
	if ( ! $cont ) $line = $matches[1];
	$line = trim ( $line );
	$line = ( $cont ? '    ' : '' ) . $line;
	$hline = htmlspecialchars ( $line );
	$display .= "<tr><td><pre>$hline</pre></td>";

	if ( $count == $r_line )
	    $display .= "<td class='red'>*</td>";

	elseif ( $i < $last && $count = $keys[$i] )
	{
	    $me = $m[$keys[$i]];
	    $i += 1;

	    $state = $me[1];
	    if ( $state == 'X' ) continue;
	    elseif ( $state == 'R' )
		$display .=
		    "<td><pre id='stat_time$count'>" .
		    "</pre></td>";
	    else
	    {
		$display .= "<td><pre>${me[2]}<pre>";
	        if ( $state == 'F' )
		{
		    $stars .= '*';
		    $messages[] = "$stars $me[4]";
		    $display .=
		        "&nbsp<pre class='red'>" .
			"$stars<pre>";
		}
		$display .= "</td>";
	    }
	}
	$display .= "</tr>" . PHP_EOL;
        $cont = preg_match ( '/^(|.*\h)\\\\$/', $line );
    }
    $display .= '</table>' . PHP_EOL;
    if ( count ( $messages ) > 0 )
    {
        foreach ( $messages as $m )
	    $display .= "<br><pre class='red'>$m<pre>";
    }
}

# Read $work/$runfile.shout and output the results of
# running $work/$runfile.sh.  If the run is finished,
# return [n,code] where n is the value of the variable
# $n in the shell script and code is the exit code.
# So n is 'D' for normal completion, the line number
# of the first line of the terminating command from
# get_commands for abnormal completion, and something
# else (e.g. 'B') for system error.
#
# If the run is not finished and $wait is 0, return true
# if the run is running and false if it has died.
#
# If the run is not finished, and $wait is > 0, poll
# every 100ms until the run finished or dies or $wait/10
# seconds have elapsed.  If the run finishes or dies,
# return as above.  If $wait seconds elapse, return
# true to indicate the run is still running.
#
# It is unwise to set $wait to a value larger than 100.
#
function get_command_results
	( $runfile, $work, $wait = 10 )
{
    global $epm_data;

    $count = 0;
    $pid = -1;
    while ( true )
    {
	$c = @file_get_contents 
		( "$epm_data/$work/$runfile.shout" );
	if ( $c !== false
	     &&
	     preg_match ( '/^(\d+) PID\n/',
	                  $c, $matches ) )
	{
	    $pid = $matches[1];
	    break;
	}

	# Poll every 10 ms for 10 seconds.
	# This allows shell time to start up and create
	# .shout file and write first line.

	usleep ( 10000 );
	if ( $count == 1000 )
	    ERROR
	        ( "cannot read $work/$runfile.shout" );
	$count += 1;
    }

    $count = 0;
    while ( true )
    {
	if ( $c !== false
	     &&
             preg_match ( '/::([A-Z0-9]+) (\d+) DONE$/',
	                  $c, $matches ) )
	{
	    return [$matches[1], $matches[2]];
	}

	if ( ! is_running ( $pid ) ) return false;
	if ( $wait == 0 ) return true;

	# Poll every 100 ms for wait/10 seconds.

	usleep ( 100000 );
	if ( $count == $wait ) return true;
	$count += 1;
	$c = @file_get_contents 
		( "$epm_data/$work/$runfile.shout" );
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
//	    Checks that file ends with the line:
//		D 0 DONE
//	    and is less than 1 megabyte in size.
//
function execute_checks
	( $control, $work, & $errors )
{
    global $epm_data;

    if ( ! isset ( $control[2]['CHECKS'] ) )
        return;
    $checks = $control[2]['CHECKS'];
    foreach ( $checks as $check )
    {
        if ( ! preg_match ( '/^\s*(\S+)\s+(\S+)\s*$/',
	                    $check, $matches ) )
	{
	    $errors[] = "malformed CHECKS item: $check";
	    continue;
	}
	$test = $matches[1];
	$file = "$epm_data/$work/$matches[2]";

	$size = @filesize ( $file );
	if ( $size === false )
	{
	    $errors[] = "file $file does not exist";
	    continue;
	}

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

	$contents = @file_get_contents ( $file );
	if ( $contents === false )
	{
	    $errors[] = "file $file is not readable";
	    continue;
	}

	if ( $test == 'SUCCESS' )
	{
	    if ( ! preg_match ( '/(^|\n)D 0 DONE$/',
	                        $contents ) )
	    {
		$name = pathinfo
		    ( $file, PATHINFO_FILENAME );
		$errors[] =
		    "execution of $name failed";
	    }
	    continue;
	}

	$errors[] = "bad test in CHECKS item: $test";
    }
}

// Move KEEP files, if any, from $work to $problem_dir.
// List last component names of files moved in $moved.
// Append error messages to $errors.
//
function move_keep
	( $control, $work, $problem_dir,
	  & $moved, & $errors )
{
    global $epm_data;

    $moved = [];

    if ( ! isset ( $control[2]['KEEP'] ) )
        return;

    $keep = $control[2]['KEEP'];
    foreach ( $keep as $fname )
    {
        $wfile = "$work/$fname";
        $lfile = "$problem_dir/$fname";
	if ( ! file_exists ( "$epm_data/$wfile" ) )
	{
	    $c = pretty_template ( $control[0] );
	    $errors[] = "KEEP file $fname was not"
	              . " made by $c";
	    continue;
	}
	if ( ! rename ( "$epm_data/$wfile",
	                "$epm_data/$lfile" ) )
	{
	    $errors[] = "SYSTEM ERROR: could not rename"
	              . " $wfile to $lfile";
	    continue;
	}
	$moved[] = $fname;
    }
}

// Return list of files to be shown.  File and directory
// names are relative to $epm_data.  Files that have not
// been moved are in $work, and moved files are in
// $problem_dir.  Files that are not readable are
// ignored; there can be no errors.
//
// This function can be called when there have been
// previous errors, as long as $control is valid and
// $moved is the list of files that have actuall been
// moved.
//
function compute_show
	( $control, $work, $problem_dir, $moved )
{
    global $epm_data;

    if ( ! isset ( $control[2]['SHOW'] ) )
        return [];

    $slist = [];
    $show = $control[2]['SHOW'];
    foreach ( $show as $fname )
    {
	if (     array_search ( $fname, $moved, true )
	     !== false )
	    $sfile = "$problem_dir/$fname";
	else
	    $sfile = "$work/$fname";
	if ( is_readable ( "$epm_data/$sfile" ) )
	    $slist[] = "$sfile";
    }
    return $slist;
}

// Starts commands and to make file $des from file $src.
// If some file (e.g., $src) is uploaded, its name is
// $upload and its tmp_name is $uploaded_tmp, and it
// will be moved into the working directory (but will
// not be checked for size and other errors).
//
// Load_file_caches and load_argument_map must be called
// before this function is called.
//
// Find_control is used to find the template and lists
// of required and creatable files needed.  The template
// is returned in $control.  Any files that need to be
// created are created by calling create_file and a mes-
// sage indicating the creation is appended to
// $warnings.
//
// The runfile name is returned in $runfile, and is the
// same as $des with its extension deleted.  The shell
// file is $runfile.sh and its output is $runfile.shout.
// If the run does not start because of errors, $runfile
// is NULL.
//
// Errors append to $errors.  If there are errors, the
// run is NOT started.
//
// This function does NOT wait for the run to complete.
// See finish_make_file below.
//
function start_make_file
	( $src, $des, $condition, $problem,
	  $work, $problem_dir, $wait,
	  $uploaded, $uploaded_tmp,
	  & $control, & $runfile,
	  & $warnings, & $errors )
{
    global $epm_data, $is_epm_test;

    $control = NULL;
    $runfile = NULL;
    $errors_size = count ( $errors );

    find_templates
	( $problem, $src, $des, $condition,
	  $templates );
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
    if ( is_null ( $control ) ) return false;

    cleanup_working ( $work );

    foreach ( $creatable as $f )
        create_file
	    ( $f, $problem_dir, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    foreach ( $creatable as $f )
    {
	if ( ! symlink ( "$epm_data/$problem_dir/$f",
	                 "$epm_data/$work/$f" ) )
	    $errors[] = "cannot symbolically link"
	              . " $work/$f to $problem_dir/$f";
    }

    link_required
	( $local_required, $remote_required,
	  $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( isset ( $uploaded ) )
    {
	$f = "$work/$uploaded";
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

    $runfile = pathinfo ( $des, PATHINFO_FILENAME );

    compile_commands ( $runfile, $work, $commands );
    execute_commands ( $runfile, $work );
}

# Finish execution of a run started by start_make_file.
# If the run has died or has not finished in $wait/10
# seconds or has had an error, an error message is
# appended to $errors.  Otherwise CHECKs are run and
# if it does not append error messages to $errors, the
# KEEP files are moved to $problem_dir.  $moved is
# returned as the list of KEEP files actually moved to
# $problem_dir, and will be empty if CHECKs fail or
# the run does not finish successfully.  $show is
# returned as the list of files to show, even if there
# are errors, but does not include any non-readable
# files.  All file and directory names are relative
# to $epm_data, except that only the last component
# of a file name is listed in $moved.
#
function finish_make_file
	( $control, $runfile,
	  $work, $problem_dir, $wait,
	  & $moved, & $show,
	  & $warnings, & $errors )
{
    $errors_size = count ( $errors );
    $moved = [];

    $r = get_command_results ( $runfile, $work, $wait );

    if ( $r === false )
        $errors[] = "SYSTEM_ERROR: $runfile.sh died";
    elseif ( $r === true )
        $errors[] = "SYSTEM_ERROR: $runfile.sh did not"
	          . " finish in time";
    elseif ( $r != ['D',0] )
        $errors[] = "command line ${r[0]} returned"
	          . " exit code ${r[1]}";
    if ( count ( $errors ) > $errors_size )
        goto SHOW;

    execute_checks ( $control, $work, $errors );
    if ( count ( $errors ) > $errors_size )
        goto SHOW;

    move_keep ( $control, $work, $problem_dir,
		$moved, $errors );
 
SHOW:

    $show = compute_show
        ( $control, $work, $problem_dir, $moved );
}

// Given the file $src make the file $des and keep any
// files the make template said should be kept.  The
// template must have NO CONDITION, and local
// problem.optn file is allowed.
//
// Upon return, $runfile is the run file (its actually
// $work/$runfile.sh), $moved is the list of files
// moved, and $show is the list of files to show.  All
// file names in $show are relative to $epm_data, but
// only the last file name component is listed in
// $moved.  If the run did not start, $runfile is NULL
// and $moved and $show are empty.  Otherwise $moved
// and $show are accurate even if there are errors.
//
// Errors append error message lines to $errors and
// warnings append to $warnings.
//
function make_and_keep_file
	( $src, $des,
	  $problem, $work, $problem_dir, $wait,
	  & $runfile, & $moved, & $show,
	  & $warnings, & $errors )
{
    load_file_caches ( $problem_dir );

    $errors_size = count ( $errors );

    load_argument_map
        ( $problem, true, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $runfile = NULL;
    $moved = [];
    $show = [];
    start_make_file
        ( $src, $des, NULL /* no CONDITION */,
          $problem, $work, $problem_dir, $wait,
	  NULL, NULL /* no upload, upload_tmp */,
	  $control, $runfile,
	  $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    finish_make_file
	( $control, $runfile,
	  $work, $problem_dir, $wait,
	  $moved, $show,
	  $warnings, $errors );
}

// Process an uploaded file whose $_FILES[...] value
// is given by the $upload argument.  LOCAL-REQUIRES
// and REMOTE-REQUIRES are treated as REQUIRES.
//
// Errors append error messages to $errors and warning
// messages to $warnings.  Commands are computed using
// get_commands.  Output from commands executed is
// appended to $output (this does not include writes to
// standard error by bash, which are lost).  List of
// KEEP files moved to problem directory is placed in
// $moved, and list of SHOW files is placed in $show.
// File names in these are relative to $epm_data.
//
// If the make template cannot be found but there are
// some templates that would work if some file are
// created, $creatables is set to list these files as
// per find_control, and error messages are appended to
// $errors listing these files.
//
function process_upload
	( $upload,
	  $problem, $work, $problem_dir, $wait,
	  & $runfile, & $moved, & $show,
	  & $warnings, & $errors )
{
    global $epm_data, $is_epm_test,
           $upload_target_ext, $epm_upload_maxsize;

    load_file_caches ( $problem_dir );

    $runfile = NULL;
    $moved = [];
    $show = [];
    $errors_size = count ( $errors );

    if ( ! is_array ( $upload ) )
        ERROR ( 'process_upload: $upload is not' .
	        ' an array' );

    $fname = $upload['name'];
    if ( ! preg_match ( '/^[-_.a-zA-Z0-9]*$/',
                        $fname ) )
    {
        $errors[] =
	    "uploaded file $fname has character" .
	    " other than letter, digit, ., -, or _";
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
    if ( $text != "" ) $tname = "$tname.$text";

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
	        $errors[] = "SYSTEM ERROR uploading"
		          . " $fname, PHP upload error"
			  . " code $ferror";
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

    load_argument_map
        ( $problem, true, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    start_make_file
        ( $fname, $tname, "UPLOAD $fname",
          $problem, $work, $problem_dir, $wait,
	  $fname, $ftmp_name,
	  $control, $runfile,
	  $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    finish_make_file
	( $control, $runfile,
	  $work, $problem_dir, $wait,
	  $moved, $show,
	  $warnings, $errors );
}

// Create the named file, which was listed in
// $createables.  If the file is created, append
// a message to $warnings indicating the file was
// created.
//
function create_file
	( $filename, $problem_dir,
	  & $warnings, & $errors )
{
    global $epm_data;

    $f = "$epm_data/$problem_dir/$filename";
    if ( @lstat ( $f ) !== false )
    {
	$errors[] = "$filename already exists";
	return true;
    }

    if ( preg_match ( '/^(.+\..)test/', $filename,
                                          $matches ) )
    {
	$o = "{$matches[1]}out";
	$g = "$epm_data/$problem_dir/$o";
	if ( is_readable ( $g ) )
	{
	    if ( ! copy ( $g, $f ) )
		ERROR ( "create_file: cannot copy $o" .
		        " to $filename" );
	    $warnings[] =
	        "$filename was created by copying $o";
	    return true;
	}
	else
	{
	    $errors[] =
	        "$o is not readable ($filename is" .
		" made by copying $o)";
	    return false;
	}
    }
    else if ( preg_match ( '/^(generate|filter)_.+$/',
                           $filename, $matches ) )
    {
	$b = $matches[1];
	if ( ! symlink
	           ( "/usr/bin/epm_default_$b", $f ) )
	    ERROR ( "create_file: cannot symbolically" .
		    " link $filename to" .
		    " /usr/bin/epm_default_$b" );
	$warnings[] =
	    "$filename was created by linking to" .
	    " /usr/bin/epm_default_$b";
	return true;
    }
    else
    {
        $errors[] =
	    "do not know how to create $filename";
	return false;
    }

}

// Find a file in $show_files that is pdf or is the
// largest UTF-8 file with size above 5 lines, delete
// it from show_files and return it.  If there is no
// such file, leave $show_files untouched and return
// NULL.  The file names in $show_files are relative
// to $epm_data.
//
function find_show_file ( & $show_files )
{
    global $epm_data, $display_file_type;

    $index = -1;
    $lines = 5;
    $i = -1;
    foreach ( $show_files as $fname )
    {
        ++ $i;
	if ( preg_match ( '/\.([^.]+)$/',
	                  $fname, $matches ) )
    	    $ext = $matches[1];
	else
	    $ext = '';

	if ( ! isset ( $display_file_type[$ext] ) )
	    continue;
	$t = $display_file_type[$ext];
	if ( $t == 'pdf' )
	{
	    $index = $i;
	    break;
	}
	else if ( $t == 'utf8' )
	{
	    $f = "$epm_data/$fname";
	    $c = exec ( "grep -c '$' $f" );
	    if ( $c > $lines )
	    {
	        $index = $i;
		$lines = $c;
	    }
	}
    }
    if ( $index == -1 ) return NULL;
    return array_splice ( $show_files, $index, 1 )[0];
}

?>
