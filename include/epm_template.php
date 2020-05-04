<?php

// File:    epm_template.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Mon May  4 02:49:10 EDT 2020

// Functions used to read templates and option files.
// Required by epm_make.php.
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

// Get the template.optn file json with overrides from
// earlier template directories in $template_dirs.
// Cache result in $template_optn.
//
// Checking of template option contents for validity is
// done by check_optmap, and is not done here.
//
$template_optn = NULL;
function get_template_optn()
{
    global $template_dirs, $template_optn;

    $description_keys =
	['argname', 'valname', 'description',
	 'values','type','range','default'];

    if ( isset ( $template_optn ) )
        return $template_optn;

    $template_optn = [];
    foreach ( $template_dirs as $e )
    {
        list ( $r, $d ) = $e;
	$f = "$d/template.optn";
        if ( ! is_readable ( "$r/$f" ) ) continue;
	$j = get_json ( $r, $f );

	// template.optn values are 2D arrays.
	//
	foreach ( $j as $opt => $description )
	foreach ( $description as $key => $value )
	{
	    if ( ! in_array
	               ( $key, $description_keys ) )
		ERROR ( "bad description key $key" .
		        " in $f" );

	    if ( ! isset
	               ( $template_optn[$opt][$key] ) )
		$template_optn[$opt][$key] = $value;
	}
    }
    return $template_optn;
}

// Add to $errors any errors found in the $optmap of
// $opt => $value.  Template options are in $options
// and must be valid.  Error messages complain about
// `$name values'.
//
$epm_type_re =
    ['natural' => '/^\d+$/',
     'integer' => '/^(|\+|-)\d+$/',
     'float' => '/^(|\+|-)\d+(|\.\d+)'
	      . '(|(e|E)(|\+|-)\d+)$/'];
//
function check_optmap
    ( & $optmap, $options, $name, & $errors )
{
    global $epm_type_re;

    foreach ( $optmap as $opt => $value )
    {
	$d = & $options[$opt];
	if ( isset ( $d['values'] ) )
	{
	    $values = $d['values'];
	    if ( ! in_array ( $value, $values ) )
		$errors[] = "option $opt $name"
			  . " value '$value' is not"
			  . " in option `values'";
	}
	elseif ( isset ( $d['type'] ) )
	{
	    $type = $d['type'];
	    $re = $epm_type_re[$type];
	    if ( ! preg_match ( $re, $value ) )
		$errors[] =
		    "option $opt $name value" .
		    " '$value' has illegal" .
		    " format for its type $type";
	    else
	    {
		$r = $d['range'];
		if ( $value < $r[0] )
		    $errors[] =
			"option $opt $name value" .
			" '$value' is too small";
		elseif ( $value > $r[1] )
		    $errors[] =
			"option $opt $name value" .
			" '$value' is too large";
	    }
	}
	else
	{
	    $re = '/^[-\+_@=\/:\.,A-Za-z0-9\h]*$/';
	    if ( ! preg_match ( $re, $value ) )
		$errors[] =
		    "option $opt $name value" .
		    " '$value' contains a" .
		    " special character other" .
		    " than - + _ @ = / : . ,";
	}
    }
}

// Get the PPPP.optn files from the following in order,
// using later values to override previous values:
//
//	Defaults from (get_)template_optn.
//	$problem.optn files in $remote_dirs.
//      $problem.optn file in $probdir iff
//          $allow_local_optn is true.
//
// Check options and list errors in $errors.  Ignore
// options with no 'default' in template_optn.  Cache
// the result in $problem_optn and $problem_optn_allow_
// local.
//
$problem_optn = NULL;
$problem_optn_allow_local = NULL;
function get_problem_optn
	( $allow_local_optn, & $errors )
{
    global $epm_data, $problem, $probdir, $remote_dirs,
           $problem_optn, $problem_optn_allow_local;

    if ( isset ( $problem_optn )
	 &&     $problem_optn_allow_local
	    === $allow_local_optn )
	    return $problem_optn;

    $problem_optn = [];
    $problem_optn_allow_local = $allow_local_optn;

    $template_optn = get_template_optn();
    foreach ( $template_optn as $opt => $desc )
    {
        if ( isset ( $desc['default'] ) )
	    $problem_optn[$opt] = $desc['default'];
    }

    $f = "$problem.optn";
    $dirs = array_reverse ( $remote_dirs );
    if ( $allow_local_optn )
        $dirs[] = $probdir;

    foreach ( $dirs as $d )
    {
        if ( ! is_readable ( "$epm_data/$d/$f" ) )
	    continue;
	$j = get_json ( $epm_data, "$d/$f" );

	// PPPP.optn values are 1D arrays.  We process
	// directories in use-last order.  If an option
	// has no value when we encounter it, the
	// option has no template default value, and
	// we ignore the option.
	//
	foreach ( $j as $opt => $value )
	{
	    if ( isset ( $problem_optn[$opt] ) )
		$problem_optn[$opt] = $value;
	}

    }
    check_optmap ( $problem_optn, $template_optn,
                   'option', $errors );
    return $problem_optn;
}

?>
