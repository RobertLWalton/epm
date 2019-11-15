<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Fri Nov 15 07:06:14 EST 2019

// Functions used to make files from other files.

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
    $temname = preg_replace
        ( '/PPPP/', $problem, $temname, -1, $count );
    if ( $count == 0 ) return FALSE;
    $offset = 0;
    $ids = [];
    while ( preg_match
                ( '/[A-Z]/', $temname, $matches,
                  PREG_OFFSET_CAPTURE, $offset ) )
    {
        $char = $match[0][0];
	$offset = $match[0][1];
	if ( ! preg_match
	           ( "/^$char{4}/", $temname, $matches,
		     0, $offset ) )
	{
	    ++ $offset;
	    continue;
	}
	$temname = preg_replace
	    ( "/^$char{4}/", '(.*)', $temname );
	$ids = "$char$char$char$char";
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

?>
