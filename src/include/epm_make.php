<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Fri Nov 15 19:16:28 EST 2019

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.

// Given a problem name, a source file name, and a
// template file name, determine if the template
// file name matches the problem and source file
// name.  If no, return false.  If yes, return
// an array containing the map from wild card
// symbols to their value.
//
function file_name_match
    ( $problem, $filename, $template )
{
    if ( ! preg_match ( '/^([^:]*):/', $template,
                                       $matches ) )
        return FALSE;
    $temname = $matches[1];
    $temname = preg_replace ( '/\./', '\\.', $temname );
    $temname = preg_replace
        ( '/PPPP/', $problem, $temname, -1, $count );
    if ( $count == 0 ) return FALSE;
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
        return FALSE;

    $result = [];
    for ( $i = 0; $i < count ( $ids ); ++ $i )
    {
        if ( isset ( $result[$ids[$i]] ) )
	{
	    if ( $result[$ids[$i]] != $matches[$i+1] )
	        return FALSE;
	}
	else
	    $result[$ids[$i]] = $matches[$i+1];
    }
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

?>
