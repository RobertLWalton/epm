<?php

// File:    epm_template.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue May  5 02:55:11 EDT 2020

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

// Compute the list of ancestors of a directory by
// tracing the +parent+ links.  Each directory is
// relative to $epm_data.
//
// All link values must match $epm_parent_re.
//
function find_ancestors ( $directory )
{
    global $epm_data, $epm_parent_re;

    $ancestors = [];
    while ( is_link
                ( "$epm_data/$directory/+parent+" ) )
    {
	$s = @readlink
	    ( "$epm_data/$directory/+parent+" );
	if ( $s === false )
	    ERROR ( "cannot read link" .
	            " $directory/+parent+" );
	if ( ! preg_match
		   ( $epm_parent_re,
		     $s, $matches ) )
	    ERROR ( "link $directory/+parent+ value" .
	            " $s is malformed" );
	$directory = $matches[1];
	$ancestors[] = $directory;
    }
    return $ancestors;
}

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
// done by check_template_optn, and is not done here.
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

// Add to $errors any errors found in $template_optn.
// Default values are not checked; for these call
// check_template_defaults.
//
function check_template_optn ( & $errors )
{
    $template_optn = get_template_optn();

    $type_re =
	['natural' => '/^\d+$/',
	 'integer' => '/^(|\+|-)\d+$/',
	 'float' => '/^(|\+|-)\d+(|\.\d+)'
		  . '(|(e|E)(|\+|-)\d+)$/'];

    $defaultmap = [];
    $errors_size = count ( $errors );
	// check_optmap will check default values in
	// $defaultmap below.

    foreach ( $template_optn as $opt => $description )
    {
	if ( ! isset ( $description['description'] ) )
	{
	    $errors[] =
	        "option $opt has NO description";
	    continue;
	}

	$isarg = isset ( $description['argname'] );
	$isval = isset ( $description['valname'] );
	$hasvalues = isset ( $description['values'] );
	$hastype = isset ( $description['type'] );
	$hasdefault = isset ( $description['default'] );
	$hasrange = isset ( $description['range'] );

	if ( $isarg && $isval )
	    $errors[] = "option $opt has BOTH"
	              . " 'argname' AND 'valname'";
	if ( $isval )
	{
	    if ( ! $hasdefault )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'default'";
	    if ( ! $hasrange )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'range'";
	    if ( ! $hastype )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'type'";
	    if ( $hasvalues )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'values'";
	}
	elseif ( $isarg )
	{
	    if ( ! $hasdefault )
		$errors[] = "option $opt has 'argname'"
		          . " but no 'default'";
	    if ( $hastype )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'type'";
	    if ( $hasrange )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'range'";
	}
	else
	{
	    if ( $hasvalues )
		$errors[] = "option $opt has 'values'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hastype )
		$errors[] = "option $opt has 'type'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hasrange )
		$errors[] = "option $opt has 'range'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hasdefault )
		$errors[] = "option $opt has 'default'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	}

	if ( $hastype )
	{
	    $type = $description['type'];
	    if ( ! isset ( $type_re[$type] ) )
	        $errors[] = "option $opt has undefined"
		          . " type $type";
	    elseif ( $hasrange )
	    {
	        $re = $type_re[$type];
	        $r = $description['range'];
		if ( ! isset ( $r[0] )
		     ||
		     ! isset ( $r[1] ) )
		    $errors[] = "option $opt has badly"
			      . " formatted range";
		elseif ( ! preg_match ( $re, $r[0] )
		         ||
		         ! preg_match ( $re, $r[1] ) )
			$errors[] =
			    "option $opt has range" .
			    " [{$r[0]},{$r[1]}] with" .
			    " badly formatted limits";
		elseif ( $r[0] > $r[1] )
		    $errors[] =
			"option $opt has range" .
			" [{$r[0]},{$r[1]}] with" .
			" lower limit > upper limit";
	    }
	}

	if ( $hasdefault ) $defaultmap[$opt] =
	    $description['default'];
    }

    if ( count ( $errors ) > $errors_size )
        return;

    check_optmap ( $defaultmap, 'default', $errors );
}

// Load into $optmap options from $dir/$problem.optn
// files where the directories are listed in $dirs.
// $optmap entries have the form $option => $value.
//
// Options in later files override those in earlier
// files.  Options that are not in $template_optn
// cause error messages to be appended to $errors.
//
function load_optmap
	( & $optmap, $dirs, $problem, $errors )
{
    global $epm_data;

    $template_optn = get_template_optn();

    foreach ( $dirs as $dir )
    {
	$f = "$dir/$problem.optn";
	if ( is_readable ( "$epm_data/$f" ) )
	{
	    $j = get_json ( $epm_data, $f );
	    foreach ( $j as $opt => $value )
	    {
		if ( isset ( $template_optn
		                 [$opt]['default'] ) )
		    $optmap[$opt] = $value;
		else
		    $errors[] = "option $opt in $f is"
		              . " not in legal (not in"
			      . " template.optn)";
	    }
	}
    }
}

// Add to $errors any errors found in the $optmap of
// $opt => $value.  Template options are in global
// $template_optn and must be valid.  Error messages
// complain about `$name values', e.g., if $name is
// 'default' they complain about `default values'.
//
// If $correct is true, correct the value by changing
// it to default value or moving it to nearest range
// limit.
//
function check_optmap
    ( & $optmap, $name, & $errors, $correct = false )
{
    $template_optn = get_template_optn();

    $type_re =
	['natural' => '/^\d+$/',
	 'integer' => '/^(|\+|-)\d+$/',
	 'float' => '/^(|\+|-)\d+(|\.\d+)'
		  . '(|(e|E)(|\+|-)\d+)$/'];

    foreach ( $optmap as $opt => $value )
    {
	$d = & $template_optn[$opt];
	$set_to_default = false;
	$reset_value = NULL;
	if ( isset ( $d['values'] ) )
	{
	    $values = $d['values'];
	    if ( ! in_array ( $value, $values ) )
	    {
		$errors[] = "option $opt $name"
			  . " value '$value' is not"
			  . " in option `values'";
		$set_to_default = $correct;
	    }
	}
	elseif ( isset ( $d['type'] ) )
	{
	    $type = $d['type'];
	    $re = $type_re[$type];
	    if ( ! preg_match ( $re, $value ) )
	    {
		$errors[] =
		    "option $opt $name value" .
		    " '$value' has illegal" .
		    " format for its type $type";
		$set_to_default = $correct;
	    }
	    else
	    {
		$r = $d['range'];
		if ( $value < $r[0] )
		{
		    $errors[] =
			"option $opt $name value" .
			" '$value' is too small";
		    if ( $correct )
			$reset_value = $r[0];
		}
		elseif ( $value > $r[1] )
		{
		    $errors[] =
			"option $opt $name value" .
			" '$value' is too large";
		    if ( $correct )
			$reset_value = $r[1];
		}
	    }
	}
	elseif ( isset ( $d['default'] ) )
	{
	    $re = '/^[-\+_@=\/:\.,A-Za-z0-9\h]*$/';
	    if ( ! preg_match ( $re, $value ) )
	    {
		$errors[] =
		    "option $opt $name value" .
		    " '$value' contains a" .
		    " special character other" .
		    " than - + _ @ = / : . ,";
		$set_to_default = $correct;
	    }
	}
	else
	{
	    $errors[] =
	        "option $opt is not legal option" .
		" (not in template.optn)";
	    if ( $correct )
	    {
	        $errors[] = "option $opt deleted";
		unset ( $optmap[$opt] );
	    }
	}

	if ( $set_to_default )
	{
	    $optmap[$opt] = $d['default'];
	    $errors[] = "option $opt $name value reset"
	              . " to default {$d['default']}";
	}
	elseif ( isset ( $reset_value ) )
	{
	    $optmap[$opt] = $reset_value;
	    $errors[] = "option $opt $name value reset"
	              . " to $reset_value";
	}
    }
}

// Get the PPPP.optn files from the following in order,
// using later values to override previous values:
//
//	Defaults from (get_)template_optn.
//	$problem.optn files in ancestors of $probdir
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
    global $epm_data, $problem, $probdir,
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

    $dirs = array_reverse
	( find_ancestors ( $probdir ) );
    if ( $allow_local_optn )
        $dirs[] = $probdir;
    load_optmap
        ( $problem_optn, $dirs, $problem, $errors );
    check_optmap ( $problem_optn, 'option', $errors );
    return $problem_optn;
}

?>
