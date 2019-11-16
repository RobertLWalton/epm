<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Nov 16 12:25:29 EST 2019

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.

// Given a problem name, a source file name, and a
// template source name, determine if the template
// source name matches the problem and source file
// name.  If no, return NULL.  If yes, return an array
// containing the map from wild card symbols to their
// values.  Note that if the template does not contain
// PPPP or any other wildcard, this may be an empty
// array, but will not be NULL.
//
// If PPPP is in the template, replace it with problem
// name before proceeding futher.
//
function template_match
    ( $problem, $filename, $template )
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
	           ( "/\G$char{4}/", $template, $matches,
		     0, $offset ) )
	{
	    ++ $offset;
	    continue;
	}
	$template = preg_replace
	    ( "/$char{4}/", '(.*)', $template, 1 );
	$ids[] = "$char$char$char$char";
    }
    if ( ! preg_match ( "/^$template\$/", $filename,
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
// [source, filename, json-decode-of-file-contents]
// in the order that the files were found.
//
// While going through the directory list, find each
// file with a required file extension and add it to
// the requires list whose elements are just file names.
//
// The extension arguments, if not missing, are
// regular expressions.  E.g., '(in|test|)' or just
// 'cc'.  Required file extensions may NOT include
// tmpl or optn.
//
// All directory and file names are relative to
// epm_data.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function file_templates_options_and_requires
    ( $dirs, $src_ext, $des_ext, $req_ext,
      & $templates, & $options, & $requires, & $errors )
{
    global $epm_data;

    $templates = [];
    $options = [];
    $requires = [];
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
	    if ( preg_match ( '/\.([^.]*)$/', $fname, $matches ) )
	        $ext = $matches[1];
	    else
	        $ext = "";

	    if ( ! is_null ( $req_ext )
	         &&
	         preg_match ( "/^$req_ext$/", $ext ) )
	    {
	        $requires[] = "$dir/$fname";
		continue;
	    }

	    if ( ! preg_match
	               ( '/^(tmpl|optn)$/', $ext ) )
	        continue;

	    if ( ! preg_match
	           ( '/^([^:]+):([^:]+):',
		     $fname, $matches ) )
	    {
	        $errors[] = "bad template or option"
		         . " file name format $fname";
	        continue;
	    }

	    $src = $matches[0];
	    $des = $matches[1];

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

	    if ( $ext == '.tmpl' )
	        $templates[] =
		    ["$src", "$dir/$fname", $json];
	    else
	    {
	        $options[] =
		    ["$src", "$dir/$fname", $json];
	    }
	}
	close ( $desc );
    }
}

// Given the name of an uploaded file, and the tem-
// plates, options, and requires lists from find_
// templates_options_and_requires, return the template
// file json data that is to be used to make something
// from the uploaded file, with options inserted and
// substitutions made for wildcards from the file
// template.
//
// Require that the REQUIRES files of any returned
// template exist or be the uploaded file.
//
// If there are several suitable template files, prefer
// first ones with the largest number of REQUIRES files,
// and second the one in the earliest directory in the
// templates list.  If there are several suitable
// options files, use the first one in the options list.
//
// Any errors cause error messages to be appended to
// the errors list.
//
// If no file is found, return NULL.
//
function find_make_control
	( $problem, $upload,
	  $templates, $options, $requires, & $errors )
{
    foreach ( $templates as $element )
    {
        $template = $element[0];
	$match = template_match
	    ( $problem, $upload, $template );
	if ( is_null ( $match ) ) continue;

	$filename = $element[1];
	$control = $element[2];

	$control =
	    substitute_match ( $control, $match );

	if ( ! isset ( $control['REQUIRES'] ) )
	{
	    $errors[] =
		"no REQUIRES in $filename";
	    continue;
	}
	$reqval = $control['REQUIRES'];
	if ( ! is_array ( $reqval ) )
	{
	    $errors[] = "REQUIRES is not an"
		      . " array in $filename";
	    continue;
	}

	// TBD
    }
}

?>
