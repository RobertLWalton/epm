<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Fri Dec 27 03:33:47 EST 2019

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
if ( ! isset ( $userid ) )
    exit ( 'ACCESS ERROR: $userid not set' );
if ( ! isset ( $problem ) )
    exit ( 'ACCESS ERROR: $problem not set' );

if ( ! isset ( $is_epm_test ) )
    $is_epm_test = false;
    // True means we are running a test script that is
    // NOT driven by an http server.  Some functions,
    // notably move_uploaded_file, will not work
    // in this test script environment.

// Administrative Parameters:
//
if ( ! isset ( $admin_params ) )
{
    $f = "src/default_admin.params";
    if ( ! is_readable ( "$epm_home/$f" ) )
    {
        $sysfail = "cannot read $f";
	require 'sysalert.php';
    }
    $admin_params = get_json ( $epm_home, $f );

    // Get local administrative parameter overrides.
    //
    $f = "admin/admin.params";
    if ( is_readable ( "$epm_data/$f" ) )
    {
        $j = get_json ( $epm_data, $f );
	foreach ( $j as $key => $value )
	    $admin_params[$key] = $value;

    }
}
$upload_target_ext = $admin_params['upload_target_ext'];
$upload_maxsize = $admin_params['upload_maxsize'];
$display_file_ext = $admin_params['display_file_ext'];

// Problem Parameters:
//
if ( ! isset ( $problem_params ) )
{
    $f = "users/user$userid/$problem/problem.params";
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
    $c = file_get_contents ( $f );
    if ( $c === false )
    {
	$sysfail = "cannot read readable $file";
	require 'sysalert.php';
    }
    $c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	// Get rid of `//...' comments.
    $j = json_decode ( $c, true );
    if ( $j === NULL )
    {
	$m = json_last_error_msg();
	$sysfail =
	    "cannot decode json in $file:\n    $m";
	require 'sysalert.php';
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
	$dircontents = scandir ( "$r/template" );
	if ( $dircontents === false )
	{
	    $sysfail = "cannot read "
	             . ( $r == $epm_data ? "DATA" :
		                           "HOME" )
		     . "/template";
	    require 'sysalert.php';
	}

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
    {
        $sysfail = "no readable template directories";
	require 'sysalert.php';
    }
}

// Read the decoded json from a template file as stored
// in the template cache.  Sysfail on errors.
//
function get_template_json ( $template )
{
    global $template_cache;
    load_template_cache();

    if ( ! isset ( $template_cache[$template] ) )
    {
        $sysfail = "get_template called with $template"
	         . " which is not cache key";
	require 'sysalert.php';
    }
    $pair = & $template_cache[$template];
    $result = & $pair[1];
    if ( ! isset ( $result ) )
    {
	$r = $pair[0];
	$f = "template/{$template}.tmpl";
	if ( ! is_readable ( "$r/$f" ) )
	{
	    $sysfail = "cannot read $f";
	    require 'sysalert.php';
	}
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
	    $sysalert = "bad template format $template";
	    require 'sysalert.php';
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
    global $template_roots, $epm_data, $userid,
           $template_optn;

    if ( isset ( $template_optn ) )
        return $template_optn;

    $files = [];
    foreach ( array_reverse ( $template_roots ) as $r )
        $files[] = [$r, "template/template.optn"];
    $files[] = [$epm_data,
                "/users/user$userid/template.optn"];

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
function get_problem_optn ( $problem, $allow_local_optn )
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
		" file is not in template.optn file\n" .
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
		        " from $problem.optn file\n" .
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
		    " of type $type\n" .
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
		    " for option $opt of type $type\n" .
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
		    " $problem.optn file\n" .
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
		    " $problem.optn file\n" .
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
		    " is too small\n" .
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
		    " is too large\n" .
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
		    " $opt of type $type in\n" .
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
		" has neither values\n" .
		"    nor type members; option ignored";
	    continue;
	}

	if ( ! isset ( $value ) )
	{
	    $sysfail = "option $opt value not set in"
	             . " by load_argument_map";
	    require 'sysalert.php';
	}

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
		" has neither argname\n" .
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
	$c = scandir ( "$epm_data/$dir" );
	if ( $c === false )
	{
	    $sysfail = "cannot read $dir";
	    require 'sysalert.php';
	}

	foreach ( $c as $fname )
	{
	    if ( preg_match  ( '/^\.+$/', $fname ) )
	        continue;
	    if ( isset ( $remote_file_cache[$fname] ) )
	        continue;
	    $remote_file_cache[$fname] = $dir;
	}
    }

    $c = scandir ( "$epm_data/$local_dir" );
    if ( $c === false )
    {
	$sysfail = "cannot read $local_dir";
	require 'sysalert.php';
    }
    foreach ( $c as $fname )
    {
	if ( preg_match  ( '/^\.+$/', $fname ) )
	    continue;
	$local_file_cache[$fname] = $local_dir;
    }
}

// Given $templates computed by find_templates and the
// caches computed by $make_file_caches, return the
// control, i.e., the selected element of $templates,
// and set $local_required to the list of $local_file_
// cache files required by the control and $remote_
// required to the list of $remote_file_cache files
// required by the control.
//
// If multiple templates satisfy required file
// constraints, ones with the largest number of required
// files are selected.  It is an error if more than
// one is selected by this rule.
//
// It is an error if no template is found with all its
// required files found, or if more than one template is
// found with the maximum number of required files all
// of which are found.
//
// Any errors cause error messages to be appended to
// the $errors list, NULL to be returned, and $required
// to be set to [].
//
// Only the last component of a file name is listed in
// $local_required or $remote_required.  The directory
// containing a $remote_required file can be found using
// $remote_file_cache.
//
// If NULL is returned and one or more templates with
// all their REQUIRED or LOCAL-REQUIRED not-found files
// are CREATABLE, the union of these not-found creatable
// files of all these templates is returned in
// $creatables, and appropriate error messages listing
// these files are appended to $errors.  Otherwise
// $creatables is [].  Only the last component of a file
// is listed in $creatables.
//
function find_control
	( $templates,
	  & $local_required, & $remote_required,
	  & $creatables, & $errors )
{
    global $local_file_cache, $remote_file_cache;

    // Note: if $uploaded is not NULL, all templates
    // not listing $uploaded as REQUIRED or LOCAL-
    // REQUIRED are not considered (rejected).

    $best_template = NULL;
        // Element of $templates for the first element
	// of $best_found.
    $best_found = [];
        // List of elements of the form [template,
	// lfiles,rfiles] where lfiles is the value for
	// $local_required and $rfiles is the value for
	// $remote_required, and only templates with NO
	// not found files and the most number of found
	// files are listed.
    $best_found_count = -1;
        // Number of files listed in each element
	// of $best_found.
    $best_not_found = [];
        // List of elements of the form [template,
	// lfiles,rfiles] where lfiles is the list
	// of REQUIRED and LOCAL-REQUIRED files that
	// were not found or CREATABLE, and rfiles
	// is the list of REMOTE-REQUIRED files that
	// were not found, and only templates with
	// at least 1 such file are included, and
	// only those with the least total number of
	// such files.
    $best_not_found_count = 1000000000;
        // Number of files listed in each element
	// of $best_not_found;
    $not_found_creatable = [];
        // List of elements of the form [template,
	// cfiles] where cfiles lists the REQUIRED
	// and LOCAL-REQUIRED files that were not found
	// but were CREATABLE, and only templates with
	// no other not-found files are listed.
    foreach ( $templates as $template )
    {
        $json = $template[2];
	$creatables = [];
	if ( isset ( $json['CREATABLE'] ) )
	    $creatables = $json['CREATABLE'];
	if ( ! is_array ( $creatables ) )
	{
	    $sysfail = "{$template[0]} json CREATABLE"
	             . " is not a list";
	    require 'sysalert.php';
	}
	$fllist = [];
	    // Local required files found.
	$frlist = [];
	    // Remote required files found.
	$clist = [];
	    // Required files not found but creatable.
	$nfllist = [];
	    // Required files not found and not
	    // creatable that can be local.
	$nfrlist = [];
	    // Required files not found and not
	    // creatable that must be remote.

	if ( isset ( $json['LOCAL-REQUIRES'] ) )
	{
	    foreach ( $json['LOCAL-REQUIRES'] as $f )
	    {
		if ( isset ( $local_file_cache[$f] ) )
		    $fllist[] = $f;
		else
		if (     array_search
		           ( $f, $creatables, true )
		     !== false )
		    $clist[] = $f;
		else
		    $nfllist[] = $f;
	    }
	}

	if ( isset ( $json['REQUIRES'] ) )
	{
	    foreach ( $json['REQUIRES'] as $f )
	    {
		if ( isset ( $local_file_cache[$f] ) )
		    $fllist[] = $f;
		else
		if ( isset ( $remote_file_cache[$f] ) )
		    $frlist[] = $f;
		else
		if (     array_search
		           ( $f, $creatables, true )
		     !== false )
		    $clist[] = $f;
		else
		    $nfllist[] = $f;
	    }
	}

	if ( isset ( $json['REMOTE-REQUIRES'] ) )
	{
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

	$ccount = count ( $clist );
	if ( $ccount > 0 )
	{
	    $not_found_creatable[] =
	        [$template[0],$clist];
	    continue;
	}

	$fcount = count ( $fllist )
	        + count ( $frlist );
	$element = [$template[0],$fllist,$frlist];
	if ( $fcount == $best_found_count )
	    $best_found[] = $element;
	else if ( $fcount > $best_found_count )
	{
	    $best_found_count = $fcount;
	    $best_found = [$element];
	    $best_template = $template;
	    $local_required = $fllist;
	    $remote_required = $frlist;
	}
    }

    $creatables = [];
    if ( count ( $best_found ) == 1 )
	return $best_template;
    else if ( count ( $best_found ) > 1 )
    {
	$errors[] =
	    "too many templates found with the same" .
	    " number of existing required files:";
	foreach ( $best_found as $e )
	{
	    $m = pretty_template ( $e[0] )
	       . ' NEEDS';
	    if ( ! empty ( $e[1] ) )
	        $m .= ' LOCAL '
		    . implode ( ',', $e[1] );
	    if ( ! empty ( $e[2] ) )
	        $m .= ' REMOTE '
		    . implode ( ',', $e[2] );
	    $errors[] = $m;
	}
    }
    else if ( count ( $not_found_creatable ) > 0 )
    {
	$errors[] =
	    "templates found need to have files" .
	    " created:";
	foreach ( $not_found_creatable as $e )
	{
	    $m = pretty_template ( $e[0] )
	       . ' NEEDS CREATABLE '
	       . implode ( ',', $e[1] );
	    $errors[] = $m;
	    // Append $e[1] to $creatables.
	    foreach ( $e[1] as $f )
		$creatables[] = $f;
	}
    }
    else
    {
	$errors[] =
	    "no template found whose required" .
	    " files exist; closest are:";
	foreach ( $best_not_found as $e )
	{
	    $m = pretty_template ( $e[0] )
	       . ' NEEDS';
	    if ( ! empty ( $e[1] ) )
	        $m .= ' LOCAL '
		    . implode ( ',', $e[1] );
	    if ( ! empty ( $e[2] ) )
	        $m .= ' REMOTE '
		    . implode ( ',', $e[2] );
	    $errors[] = $m;
	}
    }

    $creatables = array_unique ( $creatables );
    $creatables = array_slice ( $creatables, 0 );
        // Reindex array.
    $required = [];
    return NULL;
}

// Clean up a working directory.  If it has a PID file,
// kill the PID.  Then if it exists, unlink its contents
// and the directory itself, orphaning the directory.
// Then create a new directory under the same name.
//
// Directory name is relative to epm_data.
//
// If directory cannot be cleaned up, issues system
// alert and adds to errors.
//
function cleanup_working ( $dir, & $errors )
{
    global $epm_data;
    $d = "$epm_data/$dir";

    if ( file_exists ( "$d/PID" ) )
    {
        $PID = file_get_contents ( "$d/PID" );

	// PID file if it exists has the form
	//
	//    pid:expire
	//
	// where it may be assumed that if time()
	// >= expire the process that originally
	// had pid is dead.  This is necessary because
	// pid's can be reused, though (almost)
	// certainly not within the same hour.
	//
	if ( $PID )
	{
	    $pair = explode ( $PID, ":" );
	    if ( count ( $pair ) == 2 )
	    {
		$pid = $pair[0];
		$expire = $pair[1];
		if ( time() < $expire )
		{
		    exec ( "kill -1 $PID" );
		    usleep ( 500000 );
		    exec ( "kill -9 $PID" );
		}
	    }
	}
    }

    if ( file_exists ( $d ) )
        exec ( "rm -rf $d" );

    if ( ! mkdir ( $d, 0771) )
	// Must give o+x permission so epm_sandbox can
	// execute programs that are in working
	// directory.
    {
	$sysfail = "could not make $dir";
	require 'sysalert.php';
    }
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
	{
	    $sysfail = "link_required: $f in"
	             . " \$local_required not"
	             . " in \$local_file_cache";
	    require 'sysalert.php';
	    // Does NOT return.
	}
	$d = $local_file_cache[$f];
        $list[] = ["$d/$f", "../$f", $f];
    }
    foreach ( $remote_required as $f )
    {
	if ( ! isset ( $remote_file_cache[$f] ) )
	{
	    $sysfail = "link_required: $f in"
	             . " \$remote_required not"
	             . " in \$remote_file_cache";
	    require 'sysalert.php';
	    // Does NOT return.
	}
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

// Run $commands in $work.  Append output to output
// and error messages to $errors.
//
function run_commands
	( $commands, $work, & $output, & $errors )
{
    global $epm_data, $epm_home, $userid, $problem;

    $command = '';
    $e = '';
    foreach ( $commands as $c )
    {
	$e .= "\n    $c";
        if ( preg_match ( '/^(.*\h)\\\\$/', $c,
	                  $matches ) )
	{
	    $command .= $matches[1];
	    continue;
	}
	else
	    $command .= $c;

        exec ( "cd $epm_data/$work;" .
	       " export EPM_HOME=$epm_home;" .
	       " export EPM_DATA=$epm_data;" .
	       " export EPM_USERID=$userid;" .
	       " export EPM_PROBLEM=$problem;" .
	       " export EPM_WORK=$work;" .
	       " $command",
	       $output, $ret );
	if ( $ret != 0 )
	{
	    $errors[] =
		"error code $ret returned upon" .
		" executing$e";
	    return;
	}
	$command = '';
	$e = '';
    }
}

// Move KEEP files, if any, from $work to $local_dir.
// List last component names of files moved in $moved.
// Append error messages to $errors.
//
function move_keep
	( $control, $work, $local_dir,
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
        $lfile = "$local_dir/$fname";
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
// $local_dir.  Files that are not readable are ignored;
// there can be no errors.
//
function compute_show
	( $control, $work, $local_dir, $moved )
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
	    $sfile = "$local_dir/$fname";
	else
	    $sfile = "$work/$fname";
	if ( is_readable ( "$epm_data/$sfile" ) )
	    $slist[] = "$sfile";
    }
    return $slist;
}

// Run commands and CHECKS to make file $des from file
// $src.  If some file (usually $src) is uploaded, its
// name is $upload and its tmp_name is $uploaded_tmp,
// and it will be moved into the working directory
// (but will not be checked for size and other errors).
//
// Load_file_caches and load_argument_map must be called
// before this function is called.
//
// Output from exec is appended to $output, errors are
// appended to $errors, the commands executed (but not
// the checks) are returned in $commands (if an early
// command has exit code != 0, later commands in this
// list are not executed).
//
// If the make template cannot be found but there are
// some templates that would work if some file are
// created, $creatables is set to list these files as
// per find_control, and error messages are appended to
// $errors listing these files.
//
function make_file
	( $src, $des, $condition,
	  $problem, $work,
	  $uploaded, $uploaded_tmp,
	  & $control, & $commands,
	  & $output, & $creatables,
	  & $errors )
{
    global $epm_data, $is_epm_test;

    $commands = [];
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
	  $creatables, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( is_null ( $control ) ) return;

    cleanup_working ( $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    link_required
	( $local_required, $remote_required,
	  $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( isset ( $uploaded ) )
    {
	$f = "$work/$uploaded";
	if ( file_exists ( "$epm_data/$f" ) )
	{
	    $sysfail =
		"uploaded file is $uploaded but" .
		" $f already exists";
	    require 'sysalert.php';
	}

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

    run_commands ( $commands, $work, $output, $errors );
    if ( count ( $errors ) > $errors_size )
        return;

    if ( isset ( $control[2]['CHECKS'] ) )
	run_commands ( $control[2]['CHECKS'], $work,
	               $output, $errors );
}

// Given the file $src make the file $des and keep any
// files the make template said should be kept.  The
// template must have NO CONDITION, and local
// problem.optn file is allowed.  Upon return, if there
// are no errors, $moved is the list of files moved, and
// $show is the list of files to show.  All file names
// are relative to $epm_data.  $commands is the list of
// commands executed.  Errors append error message lines
// to $errors and warnings append to $warnings.
//
// If the make template cannot be found but there are
// some templates that would work if some file are
// created, $creatables is set to list these files as
// per find_control, and error messages are appended to
// $errors listing these files.
//
function make_and_keep_file
	( $src, $des, $problem,
	  $work, $local_dir,
	  & $commands, & $moved, & $show,
	  & $output, & $creatables,
	  & $warnings, & $errors )
{
    load_file_caches ( $local_dir );

    $errors_size = count ( $errors );

    load_argument_map
        ( $problem, true, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $moved = [];
    $output = [];
    make_file ( $src, $des,
                NULL /* no CONDITION */,
                $problem,
		$work,
		NULL, NULL /* no upload, upload_tmp */,
		$control, $commands,
		$output, $creatables, 
		$errors );

    if ( count ( $errors ) == $errors_size )
	move_keep ( $control, $work, $local_dir,
		    $moved, $errors );
 
    $show = compute_show
        ( $control, $work, $local_dir, $moved );
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
	( $upload, $problem, $work, $local_dir,
	  & $commands, & $moved, & $show,
	  & $output, & $creatables,
	  & $warnings, & $errors )
{
    global $epm_data, $is_epm_test,
           $upload_target_ext, $upload_maxsize,
	   $remote_file_cache;

    load_file_caches ( $local_dir );

    $moved = [];
    $show = [];
    $errors_size = count ( $errors );

    if ( ! is_array ( $upload ) )
    {
        $sysfail =
	    'process_upload: $upload is not an array';
	require 'sysalert.php';
    }

    $fname = $upload['name'];
    if ( ! preg_match ( '/^[-_.a-zA-Z0-9]*$/',
                        $fname ) )
    {
        $errors[] =
	    "uploaded file $fname has character" .
	    " other than letter, digit, ., -, or _";
	return;
    }
    if ( ! preg_match ( '/^(.+)\.([^.]+)$/',
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
    if ( $fsize > $upload_maxsize )
    {
        $errors[] =
	    "uploaded file $fname too large;" .
	    " limit is $upload_maxsize";
	return;
    }

    $ftmp_name = $upload['tmp_name'];

    load_argument_map
        ( $problem, true, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $output = [];
    make_file ( $fname, $tname, "UPLOAD $fname",
                $problem,
		$work,
		$fname, $ftmp_name,
		$control, $commands,
		$output, $creatables,
		$errors );
    if ( count ( $errors ) > $errors_size )
        goto SHOW;

    move_keep ( $control, $work, $local_dir,
                $moved, $errors );
             
SHOW:

    $show = compute_show
        ( $control, $work, $local_dir, $moved );
}

// Create the named file, which was listed in
// $createables.
//
function create_file
	( $filename, $problem_dir, & $errors )
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
	    {
		$sysfail =
		    "create_file: cannot copy $o to" .
		    " $filename";
		require 'sysalert.php';
	    }
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
	if ( ! symlink ( "/usr/bin/epm_default_$b", $f ) )
	{
	    $sysfail =
		"create_file: cannot symbolically link" .
		" $filename to /usr/bin/epm_default_$b";
	    require 'sysalert.php';
	}
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
    global $epm_data;

    $index = -1;
    $lines = 5;
    $i = -1;
    foreach ( $show_files as $fname )
    {
        ++ $i;
	$f = "$epm_data/$fname";
	$t = exec ( "file $f" );
	if ( preg_match ( '/PDF/', $t ) )
	{
	    $index = $i;
	    break;
	}
	else if ( preg_match ( '/(ASCII|UTF-8)/', $t ) )
	{
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
