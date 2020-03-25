<?php

// File:    epm_template.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed Mar 25 02:30:20 EDT 2020

// Functions used to read templates.  Required by
// epm_make.php.
//
// Used by epm_run, so $_SESSION must be declared as
// a global.
//
// WARNING: No error message may contain the value
//          of $epm_data or $epm_home.
//
// To include this program, be sure the following are
// defined.

if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $epm_home ) )
    exit ( 'ACCESS ERROR: $epm_home not set' );
if ( ! isset ( $uid ) )
    exit ( 'ACCESS ERROR: $uid not set' );

// Readable template directories, in use-first order:
//
$template_dirs = [];
if ( is_dir ( "$epm_data/admin/users/$uid/template" ) )
    $template_dirs[] =
        [$epm_data, "admin/users/$uid/template"];
if ( is_dir ( "$epm_data/template" ) )
    $template_dirs[] = [$epm_data, "template"];
$template_dirs[] = [$epm_home, "template"];


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
    $c = preg_replace ( '#"[\h\r]*\n\s*"#', '', $c );
        // Allow quoted strings to be split accross
	// lines, as in "xxx"\n"xxx".
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
        $r .= " ({$matches[3]})";
    return $r;
}


// Build a cache of templates.  This is a map of the
// form:
//	    template => [root, directory, json]
//
// where "{$root}/${directory}/{$template}.tmpl is a
// template file, root is either $epm_home or $epm_data,
// and json is NULL, but will be set to the decoded json
// read when the template file is read as per the
// get_template function below.  If two files with the
// same template are found, only the one appearing
// with the earlier directory in $template_dirs is
// recorded.  The cache is stored in $template_cache.
// No value is returned.
//
$template_cache = NULL;
function load_template_cache()
{
    global $template_dirs, $template_cache;

    if ( isset ( $template_cache) ) return;
    foreach ( $template_dirs as $e )
    {
        list ( $r, $d ) = $e;
	$dircontents = @scandir ( "$r/$d" );
	if ( $dircontents === false )
	    ERROR ( "cannot read $d" );

	foreach ( $dircontents as $fname )
	{
	    if ( ! preg_match ( '/^(.+)\.tmpl$/',
	                        $fname, $matches ) )
	        continue;
	    $template = $matches[1];
	    if ( ! isset
	               ( $template_cache[$template] ) )
		$template_cache[$template] =
		    [ $r, $d, NULL ];
	}
    }
    if ( ! isset ( $template_cache ) )
        ERROR ( "no readable template directories" );
}

// Read the decoded json from a template file as stored
// in the template cache.  Errors are fatal.
//
function get_template_json ( $template )
{
    global $template_cache;
    load_template_cache();

    if ( ! isset ( $template_cache[$template] ) )
        ERROR ( "get_template called with $template" .
	        " which is not cache key" );
    $triple = & $template_cache[$template];
    $result = & $triple[2];
    if ( ! isset ( $result ) )
    {
	$r = $triple[0];
	$d = $triple[1];
	$f = "$d/{$template}.tmpl";
	if ( ! is_readable ( "$r/$f" ) )
	    ERROR ( "cannot read $f" );
	$result = get_json ( $r, $f );
    }
    return $result;
}

?>
