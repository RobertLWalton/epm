<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Dec  5 06:19:02 EST 2019

// To include this in programs that are not pages run
// by the web server, you must pre-define $_SESSION
// accordingly.

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.  Of course uploaded
// files and components cannot have /.

if ( ! isset ( $is_epm_test ) )
    $is_epm_test = false;
    // True means we are running a test script that is
    // NOT driven by an http server.  Some functions,
    // notably move_uploaded_file, will not work
    // in this test script environment.

if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $_SESSION['userid'] ) )
    exit ( 'ACCESS ERROR: session userid not set' );
if ( ! isset ( $_SESSION['problem'] ) )
    exit ( 'ACCESS ERROR: sesssion has no current' .
           ' problem' );

$userid = $_SESSION['userid'];
$problem = $_SESSION['problem'];

if ( ! isset ( $_SESSION['epm_admin_params'] ) )
    include 'get_admin_params.php';

// Administrative Parameters:
//
$params = $_SESSION['epm_admin_params'];
$upload_target_ext = $params['upload_target_ext'];
$upload_maxsize = $params['upload_maxsize'];
$template_dirs = $params['template_dirs'];
$display_file_ext = $params['display_file_ext'];

// Problem Parameters:
//
$file = "$epm_data/users/user$userid/"
      . "$problem/$problem.params";
$params = [];
if ( is_readable ( $file ) )
{
    $contents = file_get_contents ( $file );
    if ( ! $contents )
        exit ( "cannot read $file" );
    $params = json_decode ( $contents, true );
    if ( ! $params )
        exit ( "cannot decode json $file" );
}
if ( isset ( $params['make_dirs'] ) )
    $make_dirs = $params['make_dirs'];
else
    $make_dirs = ["users/user$userid/$problem"];

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
//		template => [filename, json]
//
// where `template' is the last component of the file
// name minus the extension .tmpl and json is NULL, but
// will be set to the decoded json read from the file
// when the file is read as per the get_template
// function below.  If two files with the same template
// are found, one in $epm_root/template and one in
// $epm_root/local/template, only the latter is
// recorded.  Filenames are absolute.  The cache is
// stored in $template_cache.
//
$template_cache = NULL;
function load_template_cache()
{
    global $template_dirs, $template_cache;

    if ( isset ( $template_cache) ) return;
    foreach ( $template_dirs as $dir )
    {
	$dircontents = scandir ( $dir );
	if ( $dircontents === false )
	{
	    $sysfail = "cannot read $dir";
	    include 'sysalert.php';
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
	        [ "$dir/$fname", NULL ];
	}
    }
    if ( ! isset ( $template_cache ) )
    {
        $sysfail = "no readable template directories";
	include 'sysalert.php';
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
        $sysfail = "get_template called with template"
	         . " that is not cache key";
	include 'sysalert.php';
    }
    $pair = & $template_cache[$template];
    $result = & $pair[1];
    if ( ! isset ( $result ) )
    {
	$filename = & $pair[0];
	$contents = file_get_contents ( $filename );
	if ( $contents === false )
	{
	    $sysfail = "cannot read $filename";
	    include 'sysalert.php';
	}
	$json = json_decode ( $contents, true );
	if ( ! isset ( $json ) )
	{
	    $sysfail = "cannot json decode $filename";
	    include 'sysalert.php';
	}
	$result = $json;
    }
    return $result;
}

// Go through the template cache and find each template
// that has the given source file name and destination
// file name, either of which may be NULL if it is not
// to be tested (both cannot be NULL).
//
// For each template found, list in $templates elements
// of the form:
//
//   [template, filename, json]
// 
// containing the information copied from the
//
//	template => [filename, json]
//
// but with wildcards in json replaced by their matches
// found from matching the source and destination file
// names and problem name to the template.  Filename is
// the absolute name of the template file and is only
// used in error messages.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function find_templates
    ( $problem, $srcfile, $desfile,
      & $templates, & $errors )
{
    global $template_cache;
    load_template_cache();

    if ( is_null ( $srcfile ) && is_null ( $desfile ) )
    {
        $sysfail = 'find_templates called'
	         . ' with both $srcfile and $desfile'
		 . ' NULL';
	include 'sysalert.php';
    }

    $templates = [];
    foreach ( $template_cache as $template => $pair )
    {
	$filename = $pair[0];
	if ( ! preg_match
	       ( '/^([^:]+):([^:]+):/',
		 $template, $matches ) )
	{
	    $errors[] = "bad template file name"
		      . " format $filename";
	    continue;
	}

	$tsrc = $matches[1];
	$tdes = $matches[2];

	if ( is_null ( $desfile ) )
	    $match = template_match
		( $problem, $srcfile, $tsrc );
	elseif ( is_null ( $srcfile ) )
	    $match = template_match
		( $problem, $desfile, $tdes );
	else
	    $match = template_match
		( $problem, "$srcfile:$desfile",
			    "$tsrc:$tdes" );

	if ( is_null ( $match ) ) continue;

	$json = get_template_json ( $template );

	$json = substitute_match ( $json, $match );

	$templates[] =
	    [ $template, $filename, $json ];
    }
}

// Get the template.optn file with overrides from
// earlier template directories and users/user$id
// directory.  Append non-fatal errors to $errors.
// Cache result in $template_optn.
//
function get_template_optn ( & $errors )
{
    global $template_dirs, $epm_data, $userid,
           $template_optn;

    $dirs = array_reverse ( $template_dirs );
    $dirs[] = "$epm_data/users/user$userid";

    $template_optn = [];
    foreach ( $dirs as $dir )
    {
	$filename = "$dir/template.optn";
        if ( ! is_readable ( $filename ) ) continue;
	$contents = file_get_contents ( $filename );
	if ( $contents === false )
	{
	    $sysfail = "cannot read readable $filename";
	    include 'sysalert.php';
	}
	$json = json_decode ( $contents, true );
	if ( ! isset ( $json ) )
	{
	    $sysalert = "cannot json decode $filename";
	    include 'sysalert.php';
	    $errors[] = $sysalert;
	    continue;
	}

	// template.optn values are 2D arrays.
	//
	foreach ( $json as $opt => $description )
	foreach ( $description as $key => $value )
	    $template_optn[$opt][$key] = $value;
    }
    return $template_optn;
}

// Get the PPPP.optn file for problem PPPP, with
// overrides from more local directories.  Append
// some errors to $errors.  Cache the result in
// $problem_optn.
//
function get_problem_optn ( $problem, & $errors )
{
    global $epm_data, $make_dirs, $problem_optn;

    $problem_optn = [];
    foreach ( array_reverse ( $make_dirs ) as $dir )
    {
        $filename = "$epm_data/$dir/$problem.optn";
        if ( ! is_readable ( $filename ) ) continue;
	$contents = file_get_contents ( $filename );
	if ( $contents === false )
	{
	    $sysfail = "cannot read readable $filename";
	    include 'sysalert.php';
	}
	$json = json_decode ( $contents, true );
	if ( ! isset ( $json ) )
	{
	    $sysalert = "cannot json decode $filename";
	    include 'sysalert.php';
	    $errors[] = $sysalert;
	    continue;
	}

	// PPPP.optn values are 1D arrays.
	//
	foreach ( $json as $opt => $value )
	    $problem_optn[$opt] = $value;

    }
    return $problem_optn;
}

// Return argument map that is to be applied to template
// COMMANDS from results of get_template_optn and
// get_problem_optn.  Append warnings to $warnings for
// options that must be modified or ignored, and append
// errors to $errors for options that cannot be given
// any value.
//
function compute_optn_map
	( $problem, & $warnings, & $errors )
{
    global $template_optn, $problem_optn;
    $error_size = count ( $errors );
    get_template_optn ( $errors );
    get_problem_optn ( $problem, $errors );
    if ( count ( $errors ) > $error_size ) return;

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
	             . " in compute_optn_map";
	    include 'sysalert.php';
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

    return substitute_match ( $arg_map, $val_map );
}

// Build a cache of files that may be required.  The
// cache has entries of the form:
//
//	filename => directory
//
// where filename is the last component of the file
// name and directory is the first $make_dirs directory
// in which the file can be found under the full name:
//
//	$epm_data/directory/filename
//
$make_cache = NULL;
function load_make_cache()
{
    global $epm_data, $make_dirs, $make_cache;
    if ( isset ( $make_cache ) ) return;

    foreach ( $make_dirs as $dir )
    {
	$d = "$epm_data/$dir";
	$dircontents = scandir ( $d );
	if ( $dircontents === false )
	{
	    $sysfail = "cannot read $d";
	    include 'sysalert.php';
	}

	foreach ( $dircontents as $fname )
	{
	    if ( preg_match  ( '/^\.+$/', $fname ) )
	        continue;
	    if ( isset ( $make_cache[$fname] ) )
	        continue;
	    $make_cache[$fname] = $dir;
	}
    }
}

// Given $templates computed by find_templates and
// the $make_cache, return the control, i.e., the
// selected element of $templates, and set $required
// to the list of files required by the control.
//
// If multiple templates satisfy required file
// constraints, ones with the largest number of required
// files are selected.  It is an error if more than
// one is selected by this rule.
//
// $local_dir is the directory satisfying LOCAL-
// REQUIRES.  It is relative to $epm_data and can be
// compared to the values in the $make_cache.
// If $local_dir is NULL, LOCAL-REQUIRES and REMOTE-
// REQUIRES are treated the same as REQUIRES.
//
// If $uploaded is not NULL, it is the name of the
// file being uploaded and will satisfy REQUIRES or
// LOCAL-REQUIRES.  It is NOT listed in $required.
//
// Only the last component of a file names is listed in
// $required.  The directory containing it can be
// found using $make_cache.
//
// It is an error if no template is found meeting
// required file constraints, or if more than one
// template is found with the maximum number of existing
// required files.
//
// Any errors cause error messages to be appended to
// the errors list and NULL to be returned.
//
function find_control
	( $templates, $local_dir, $uploaded,
	  & $required, & $errors )
{
    global $make_cache;
    load_make_cache();

    $best_template = NULL;
    $tlist = [];
    $best_count = -1;
    foreach ( $templates as $template )
    {
	$rlist = [];

        $json = $template[2];
	$OK = true;
	foreach ( ['REQUIRES', 'LOCAL-REQUIRES',
	                       'REMOTE-REQUIRES']
		  as $R )
	{
	    if ( isset ( $json[$R] ) )
	    {
		foreach ( $json[$R] as $rfile )
		{
		    if ( $rfile == $uploaded )
		    {
		        if ( $R == 'REMOTE-REQUIRES'
			     &&
			     isset ( $local_dir ) )
			{
			    $OK = false;
			    break;
			}
			continue;
		    }

		    if ( ! isset
			     ( $make_cache[$rfile] ) )
		    {
		        $OK = false;
			break;
		    }
		    $rdir = $make_cache[$rfile];
		    if ( isset ( $local_dir ) )
		        switch ( $R )
		    {
		        case 'LOCAL-REQUIRES':
			    if ( $rdir != $local_dir )
			        $OK = false;
			    break;
		        case 'REMOTE-REQUIRES':
			    if ( $rdir == $local_dir )
			        $OK = false;
			    break;
		    }
		    if ( ! $OK ) break;

		    $rlist[] = $rfile;
		}
	    }
	    if ( ! $OK ) break;
	}
	if ( ! $OK ) continue;

	$rlist = array_unique ( $rlist );
	$rcount = count ( $rlist );
	if ( $rcount == $best_count )
	    $tlist[] = $template[0];
	else if ( $rcount > $best_count )
	{
	    $tlist = [$template[0]];
	    $best_template = $template;
	    $best_count = $rcount;
	    $required = $rlist;
	}
    }

    if ( count ( $tlist ) == 1 )
	return $best_template;
    else if ( count ( $tlist ) == 0 )
	$errors[] =
	    "no template found whose required" .
	    " files exist";
    else
    {
        $tlist = implode ( " ", $tlist );
	$errors[] =
	    "too many templates found with the same" .
	    " number of existing required files:\n" .
	    "    $tlist";
    }
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
    $dir = "$epm_data/$dir";

    if ( file_exists ( "$dir/PID" ) )
    {
        $PID = file_get_contents ( "$dir/PID" );

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

    if ( file_exists ( $dir ) )
        exec ( "rm -rf $dir" );

    if ( ! mkdir ( $dir, 0771) )
    // Must give o+x permission so epm_sandbox can
    // execute programs that are in working directory.
    {
	$sysalert = "could not make $dir";
	include 'sysalert.php';
	$errors[] = "SYSTEM ERROR: could not make $dir";
    }
}

// Link files from the required list into the working
// working directory.  Ignore and do not link a required
// list file with name equal to the uploaded file, if
// that argument is not NULL.  The required list is
// generally computed by find_control and only contain
// names of last components, that must be looked up in
// the make_cache.  It is a fatal error if a required
// file is NOT listed in make_cache.
//
// Errors cause error messages to be appended to errors.
//
function link_required
	( $uploaded, $work, $required, & $errors )
{
    global $epm_data, $make_cache;
    load_make_cache();

    foreach ( $required as $rname )
    {
        if ( $rname == $uploaded ) continue;

	if ( ! isset ( $make_cache[$rname] ) )
	{
	    $sysfail = "in link_required: $rname not"
	             . " in \$make_cache";
	    include 'sysalert.php';
	    // Does NOT return.
	}

	$dir = $make_cache[$rname];

	$rfile = "$epm_data/$dir/$rname";

	if ( ! is_readable ( $rfile ) )
	{
	    $errors[] = "$rfile is not readable";
	    continue;
	}
	if ( ! preg_match ( '/\./', $rname )
	     &&
	     ! is_executable ( $rfile ) )
	{
	    $errors[] = "$rfile is not executable";
	    continue;
	}
	$rlink = "$epm_data/$work/$rname";
	if ( ! link ( $rfile, "$rlink" ) )
	{
	    $errors[] = "cannot symbolically link"
	              . " $rfile to $rlink";
	    continue;
	}
    }
}

// Return COMMANDS list from control as updated by
// the optn_map.  The latter must be [] if it is
// not used.
//
function get_commands ( $control, $optn_map )
{
    if ( ! isset ( $control[2]['COMMANDS'] ) )
        return [];
    $commands = $control[2]['COMMANDS'];
    return substitute_match ( $commands, $optn_map );
}

// Run $commands in $work.  Append output to output
// and error messages to $errors.
//
function run_commands
	( $commands, $work, & $output, & $errors )
{
    global $epm_data;

    foreach ( $commands as $command )
    {
        exec ( "cd $epm_data/$work; $command",
	       $output, $ret );
	if ( $ret != 0 )
	{
	    $errors[] =
		"error code $ret returned upon" .
		" executing\n    $command";
	    return;
	}
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
        $wfile = "$epm_data/$work/$fname";
        $lfile = "$epm_data/$local_dir/$fname";
	if ( ! file_exists ( $wfile ) )
	{
	    $errors[] = "KEEP file $fname was not"
	              . " made by $control[1]";
	    continue;
	}
	if ( ! rename ( $wfile, $lfile ) )
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
// Output from exec is appended to $output, warnings and
// errors are appended to $warnings and $errors, the
// commands executed (but not the checks) are returned
// in $commands (if an early command has exit code != 0
// later commands in this list are not executed).
//
function make_file
	( $src, $des,
	  $problem, $local_dir,
	  $uploaded, $uploaded_tmp,
	  & $control, & $commands,
	  & $output, & $warnings, & $errors )
{
    global $epm_data, $is_epm_test;

    $commands = [];
    $errors_size = count ( $errors );

    find_templates
	( $problem, $src, $des,
	  $templates, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( count ( $templates ) == 0 )
    {
        $errors[] =
	    "there are no templates" .
	    " $src:$des:... for problem $problem";
	return;
    }

    $control = find_control
	( $templates, $local_dir, $uploaded,
	  $required, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( is_null ( $control ) ) return;

    $work = "$local_dir/+work+";
    cleanup_working ( $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    link_required
	( $uploaded, $work, $required, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( isset ( $uploaded ) )
    {
	$f = "$epm_data/$work/$uploaded";
	if ( file_exists ( $f ) )
	{
	    $sysfail =
		"uploaded file is $uploaded but" .
		" $f already exists";
	    include 'sysalert.php';
	}

	if ( $is_epm_test ?
	     ! rename ( $uploaded_tmp, $f ) :
	     ! move_uploaded_file
		   ( $uploaded_tmp, $f ) )
	{
	    $errors[] =
		"SYSTEM_ERROR: failed to move" .
		" $uploaded_tmp" .
		" (alias for uploaded $uploaded)" .
		" to $f";
	    return;
	}
    }

    $optn_map = compute_optn_map
        ( $problem, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $commands = get_commands ( $control, $optn_map );

    run_commands ( $commands, $work, $output, $errors );
    if ( count ( $errors ) > $errors_size )
        return;

    if ( isset ( $control[2]['CHECKS'] ) )
	run_commands ( $control[2]['CHECKS'], $work,
	               $output, $errors );
}

// Process an uploaded file whose $_FILES[...] value
// is given by the $upload argument.
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
function process_upload
	( $upload, $problem, $local_dir,
	  & $commands, & $moved, & $show,
	  & $output, & $warnings, & $errors )
{
    global $epm_data, $is_epm_test,
           $upload_target_ext, $upload_maxsize;

    $moved = [];
    $show = [];
    $errors_size = count ( $errors );

    if ( ! is_array ( $upload ) )
    {
        $sysfail =
	    'process_upload: $upload is not an array';
	include 'sysalert.php';
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

    $output = [];
    make_file ( $fname, $tname,
                $problem, $local_dir,
		$fname, $ftmp_name,
		$control, $commands, $output,
		$warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $work = "$local_dir/+work+";

    move_keep ( $control, $work, $local_dir,
                $moved, $errors );
    if ( ! rename ( "$epm_data/$work/$fname",
                    "$epm_data/$local_dir/$fname" ) )
	$errors[] =
	    "SYSTEM_ERROR: could not rename" .
	    " $epm_data/$work/$fname to" .
	    " $epm_data/$local_dir/$fname";
    else
        $moved[] = $fname;
             
    $show = compute_show
        ( $control, $work, $local_dir, $moved );
}

?>
