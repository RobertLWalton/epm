<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Nov 16 08:11:02 EST 2019

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
function filename_match
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

// Go through the directory list dirs and find each
// template or option file that has the given
// source extension and destination extension (either
// of which may be NULL if missing).  Return in
// templates and options lists whose elements are
// [filename, json-decode-of-file-contents] in the
// order that the files were found.
//
// All directory and file names are relative to epm_data.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function file_templates_and_options
    ( $dirs, $src_ext, $des_ext,
      & $templates, & $options, & $errors )
{
    global $epm_data;

    $templates = [];
    $options = [];
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
	           ( '/^([^:]+):([^:]+)(:.*|)' .
		     '(\.tmpl|\.opt)$/',
		     $fname, $matches ) )
	        continue;

	    $src = $matches[0];
	    $des = $matches[1];
	    $type = $matches[3];

	    if ( ! is_null ( $src_ext )
		 &&
		 ( $src_ext == "" ?
		   preg_match ( '/\./', $fname ) :
		   ! preg_match
			 ( "/\\.$src_ext\$/",
			   $fname ) ) )
		continue;
	    if ( ! is_null ( $des_ext )
		 &&
		 ( $des_ext == "" ?
		   preg_match ( '/\./', $fname ) :
		   ! preg_match
			 ( "/\\.$des_ext\$/",
			   $fname ) ) )
		continue;

	    $file = file_get_contents
	        ( "$epm_data/$dir/$fname" );
	    if ( ! $file )
	    {
		$errors[] = "cannot read $dir/$fname";
		continue;
	    }
	    $json = json_decode ( $file, true );
	    if ( ! $json )
	    {
		$errors[] =
		    "cannot decode json in $dir/$fname";
		continue;
	    }

	    if ( $type == '.tmpl' )
	        $templates[] = ["$dir/$fname", $json];
	    else
	        $options[] = ["$dir/$fname", $json];
	}
	close ( $desc );
    }
}

// Given the name of an uploaded file, and a list of
// directories, find the template file that is to be
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
	  & $errors )
{
    global $epm_data;
    $templates = [];
        // List of json-decode-of-file for templates
	// for which filename_match works, with wildcard
	// matches substituted in the json.  Listed in
	// order encountered.
    $requires = [];
        // Associative array of form [element => ""]
	// for every element of a REQUIRES list in
	// a template listed above.
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
	               ( '/PPPP.*\.tmpl$/', $fname )
		 &&
		 ! preg_match
	               ( "/$problem.*\.tmpl$/",
		         $fname ) )
	        continue;
	    $match = filename_match
                ( $problem, $filename, $fname );
	    if ( is_null ( $match ) ) continue;
	    $fileval =
	        substitute_match ( $fileval, $match );
	    if ( ! isset ( $fileval['REQUIRES'] ) )
	    {
		$errors[] =
		    "no REQUIRES in $dir/$fname";
		continue;
	    }
	    $reqval = $fileval['REQUIRES'];
	    if ( ! is_array ( $reqval ) )
	    {
		$errors[] = "REQUIRES is not an array"
		          . " in $dir/$fname";
		continue;
	    }

	    $templates[] = $fileval;
	    foreach ( $reqval as $required )
	        $requires[$required] = "";
	}
	close ( $desc );
    }

    // Now go through the directories again, and for
    // each file, if its name matches a key in
    // $requires and that element of $requires has ""
    // as its value, record the filename of the file
    // relative to epm_data as he new value replacing
    // the "".
    //
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
	    if ( isset ( $requires[$fname] )
	         &&
		 $requires[$fname] == ""
		 &&
		 is_readable
		     ( "$epm_data/$dir/$fname" ) )
	        $requires[$fname] = "$dir/$fname";
	}
	close ( $desc );
    }
}

?>
