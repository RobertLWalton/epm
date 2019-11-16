<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Nov 16 01:14:23 EST 2019

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.

// Given a problem name, a source file name, and a
// template file name, determine if the template
// file name matches the problem and source file
// name.  If no, return NULL.  If yes, return
// an array containing the map from wild card
// symbols to their value.  Note that if template
// does not contain PPPP or any other wildcard,
// this may be an empty array.
//
// If PPPP is in the template, replace it with
// problem name before proceeding futher.
//
function file_name_match
    ( $problem, $filename, $template )
{
    if ( ! preg_match ( '/^([^:]*):/', $template,
                                       $matches ) )
        return NULL;
    $temname = $matches[1];
    $temname = preg_replace ( '/\./', '\\.', $temname );
    $temname = preg_replace
        ( '/PPPP/', $problem, $temname,
	  -1, $PPPP_count );
    $offset = 0;
    $ids = [];
    while ( preg_match
                ( '/[A-Z]/', $temname, $matches,
                  PREG_OFFSET_CAPTURE, $offset ) )
    {
        $char = $matches[0][0];
	$offset = $matches[0][1];
	if ( ! preg_match
	           ( "/\G$char{4}/", $temname, $matches,
		     0, $offset ) )
	{
	    ++ $offset;
	    continue;
	}
	$temname = preg_replace
	    ( "/$char{4}/", '(.*)', $temname, 1 );
	$ids[] = "$char$char$char$char";
    }
    if ( ! preg_match ( "/^$temname\$/", $filename,
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

// Given a string and substitutions computed by file_
// name_match, return the string with the substitutions
// made.
//
function string_substitute_match ( $string, $match )
{
    foreach ( $match as $key => $value )
	$string = preg_replace
	    ( "/$key/", $value, $string );
    return $string;
}

// Given an array and substitutions computed by file_
// name_match, return the array with the substitutions
// made in the array values that are strings, and
// recursively in array values that are arrays.
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

// Given the name of an uploaded file, and a list of
// directories, find the make control file that is to be
// used to make something from the uploaded file, and
// return its json decoded array with substitutions for
// parameters in its file name.
//
// Require that the REQUIRES files of any returned
// make control file exist or be the uploaded file.
//
// If there are several suitable files, prefer first
// ones with the largest number of REQUIRES files,
// and second the one in the earliest directory in the
// directory list.
//
// All directory names are relative to the global
// $epm_data.
//
// Any errors cause error messages to be appended to
// the errors list.
//
// Return the name of the best file found in best_
// filename.
//
// If no file is found, return NULL.
//
function find_make_control
	( $user_dir, $problem, $filename, $dirs,
	             & $best_filename, & $errors )
{
    global $epm_data;
    $prob_dir = "$epm_data/$user_dir/$problem";
    $best = NULL;
    $best_requires = -1;
    foreach ( $dirs as $dir )
    {
        $desc = opendir ( "$epm_data/$dir" );
	if ( ! $desc )
	{
	    $errors[] =
	        "cannot open search directory $dir";
	    continue;
	}
	while ( $fname = readdir ( $desc ) )
	{
	    if ( ! preg_match
	               ( '/PPPP.*\.json$/', $fname )
		 &&
		 ! preg_match
	               ( "/$problem.*\.json$/",
		         $fname ) )
	        continue;
	    $match = file_name_match
                ( $problem, $filename, $fname );
	    if ( is_null ( $match ) ) continue;
	    $filejson = file_get_contents
	        ( "$epm_data/$dir/$fname" );
	    if ( ! $filejson )
	    {
		$errors[] = "cannot read $dir/$fname";
		continue;
	    }
	    $fileval = json_decode ( $filejson, true );
	    if ( ! $fileval )
	    {
		$errors[] =
		    "cannot decode json in $dir/$fname";
		continue;
	    }
	    $fileval =
	        substitute_match ( $fileval, $match );
	    if ( ! isset ( $fileval['REQUIRES'] ) )
	    {
		$errors[] =
		    "no REQUIRES in $dir/$fname";
		continue;
	    }
	    $requires = $fileval['REQUIRES'];
	    if ( ! is_array ( $requires ) )
	    {
		$errors[] = "REQUIRES is not an array"
		          . " in $dir/$fname";
		continue;
	    }
	    if ( count ( $requires ) <= $best_requires )
	        continue;
	    $OK = true;
	    foreach ( $requires as $value )
	    {
	        if ( $value != $filename
		     &&
		     ! is_readable
		           ( "$probdir/$value" ) )
		{
		    $OK = false;
		    break;
		}
	    }
	    $best = $fileval;
	    $best_requires = count ( $requires );
	    $best_filename = "$dir/$fname";
	}
	close ( $desc );
    }
}

?>
