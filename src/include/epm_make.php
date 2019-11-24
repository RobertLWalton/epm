<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sun Nov 24 03:47:18 EST 2019

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
    exit ( 'SYSTEM ERROR: $epm_data not set' );
if ( ! isset ( $_SESSION['userid'] ) )
    exit ( 'SYSTEM ERROR: session userid not set' );
if ( ! isset ( $_SESSION['problem'] ) )
    exit ( 'SYSTEM ERROR: sesssion has no current' .
           ' problem' );

$userid = $_SESSION['userid'];
$problem = $_SESSION['problem'];

if ( ! isset ( $_SESSION['epm_admin_params'] ) )
    include 'get_params.php';

// Administrative Parameters:
//
$params = $_SESSION['epm_admin_params'];
if ( isset ( $params['upload_target_ext'] ) )
    $upload_target_ext =
        $params['upload_target_ext'];
else
    $upload_target_ext = [
        "c" =>  "",
	"cc" => "",
	"java" => "class",
	"py" => "pyc",
	"tex" => "pdf",
	"in" => "out"];

if ( isset ( $params['upload_maxsize'] ) )
    $upload_maxsize = $params['upload_maxsize'];
else
    $upload_maxsize = 2000000;

$root = $_SERVER['DOCUMENT_ROOT'];
$template_dirs = ["$root/src/template"];

// User Parameters:
//
if ( ! isset ( $_SESSION['epm_user_params'] ) )
    exit ( 'SYSTEM ERROR: epm_user_params not set' );
    // Should be set if epm_admin_params set.

$params = $_SESSION['epm_user_params'];
if ( isset ( $params['make_dirs'] ) )
    $make_dirs = $params['make_dirs'];
else
    $make_dirs = ["users/user$userid/$problem"];

if ( isset ( $params['upload_maxsize'] ) )
    $upload_maxsize = $params['upload_maxsize'];
                   
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

// Go through the template directories and find each
// template (.tmpl) file that has the given source file
// name and destination file name, either of which may
// be NULL if it is not to be tested (both cannot be
// NULL).
//
// For each template file found, list in $templates
// elements of the form:
//
//   [template, filename, json]
//
// Here `template' is the last component of the file
// name minus the extension .tmpl and json is the file
// contents with wildcards substituted.  This list is in
// the order that the files were found.  Filename is the
// absolute name of the template file and is only used
// in error messages.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function find_templates
    ( $problem, $srcfile, $desfile,
      & $templates, & $errors )
{
    global $template_dirs;

    if ( is_null ( $srcfile ) && is_null ( $desfile ) )
        exit ( 'SYSTEM ERROR; find_templates called' .
	       ' with both $srcfile and $desfile NULL'
	     );

    $templates = [];
    foreach ( $template_dirs as $dir )
    {
        $desc = opendir ( "$dir" );
	if ( ! $desc )
	{
	    $errors[] =
	        "cannot open search directory $dir";
	    continue;
	}
	while ( $fname = readdir ( $desc ) )
	{
	    if ( preg_match ( '/^\.+$/', $fname ) )
	        continue;

	    if ( ! preg_match ( '/^(.*)\.tmpl$/',
	                      $fname, $matches ) )
	        continue;
	    $template = $matches[1];

	    if ( ! preg_match
	           ( '/^([^:]+):([^:]+):/',
		     $template, $matches ) )
	    {
	        $errors[] = "bad template file name"
		          . " format $dir/$fname";
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

	    $file = file_get_contents ( "$dir/$fname" );
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
	    $json = substitute_match ( $json, $match );

	    $templates[] =
	        [ $template, "$dir/$fname", $json ];
	}
	closedir ( $desc );
    }
}

// Given the output of find_templates and the list of
// directories in which required and option files are
// to be found, create maps of required file names to
// first directory in which the required file name is
// found and option file names to the first directory
// in which the option file is found.  In these maps,
// "" is used to mean `no directory'.
//
// Directory names are relative the $epm_data.
//
// Any errors cause error messages to be appended to
// the errors list.
// 
function find_requires_and_options
    ( $dirs, $templates,
      & $requires, & $options, & $errors )
{
    global $epm_data;

    // Initialize the maps from $templates so we know
    // which files we are looking for.
    //
    $required = [];
    $options = [];
    foreach ( $templates as $template )
    {
        $json = $template[2];
	$optfile = "$template[0].optn";
	$options[$optfile] = "";
	foreach ( ['REQUIRES', 'LOCAL-REQUIRES',
	                       'REMOTE-REQUIRES']
		  as $R )
	{
	    if ( isset ( $json[$R] ) )
	    {
		foreach ( $json[$R] as $required )
		    $requires[$required] = "";
	    }
	}
    }

    // Cycle through $dirs and set the maps.
    //
    foreach ( $dirs as $dir )
    {
        $desc = opendir ( "$epm_data/$dir" );
	if ( ! $desc )
	{
	    $errors[] =
	        "cannot open search directory" .
		" $epm_data/$dir";
	    continue;
	}
	while ( $fname = readdir ( $desc ) )
	{
	    if ( preg_match ( '/^\.+$/', $fname ) )
	        continue;

	    if ( isset ( $requires[$fname] )
	         &&
		 $requires[$fname] == "" )
	        $requires[$fname] = $dir;

	    if ( isset ( $options[$fname] )
	         &&
		 $options[$fname] == "" )
	        $options[$fname] = $dir;
	}
	closedir ( $desc );
    }

}

// Given $templates computed by find_templates and
// $requires and $options computed by find_requires_and_
// options, return the control, i.e., the selected
// element of $template, and set $required to the list
// of required files and $option to the json of the
// option file, or [] if no such file.  The filenames
// returned in $required are relative to $epm_data.
// $dirs is the directory list used by find_requires_
// and_options, and is used to identify the local
// directory (its the first one) and identify whether
// there is more than one directory.
//
// If multiple templates satisfy required file
// constraints, ones with the largest number of required
// files are selected, and among these the first in
// the $templates list.
//
// If $uploaded is not NULL, it is the name of the
// file being uploaded and will satisfy REQUIRES or
// LOCAL-REQUIRES.
//
// Returns NULL if no template found meeting required
// file constraints.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function find_control
	( $dirs, $uploaded, $templates, $requires,
	  $options, & $required, & $option, & $errors )
{
    $required = [];
    $option = [];

    $best_template = NULL;
    $best_count = -1;
    $local_dir = $dirs[0];
    $dirs_count = count ( $dirs );
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
		        if ( $R == 'REMOVE-REQUIRES'
			     &&
			     $dirs_count != 1 )
			    $OK = false;
			continue;
		    }

		    if ( ! isset
			     ( $requires[$rfile] ) )
		    {
		        $OK = false;
			break;
		    }
		    $rdir = $requires[$rfile];
		    if ( $rdir == "" )
		        $OK = false;
		    else switch ( $R )
		    {
		        case 'LOCAL-REQUIRES':
			    if ( $rdir != $local_dir )
			        $OK = false;
			    break;
		        case 'REMOTE-REQUIRES':
			    if ( $rdir == $local_dir
			         &&
				 $dirs_count != 1 )
			        $OK = false;
			    break;
		    }
		    if ( ! $OK ) break;

		    $rlist[] = "$rdir/$rfile";
		}
	    }
	    if ( ! $OK ) break;
	}
	if ( ! $OK ) continue;

	$rlist = array_unique ( $rlist );
	$rcount = count ( $rlist );
	if ( $rcount <= $best_count )
	    continue;

	$best_template = $template;
	$best_count = $rcount;
	$required = $rlist;
    }

    $ofile = "$best_template[0].optn";
    if ( ! isset ( $options[$ofile] ) )
	$ofile = NULL;
    else
    {
	$odir = $options[$ofile];
	if ( $odir == "" )
	    $ofile = NULL;
	else
	    $ofile = "$odir/$ofile";
    }
    if ( ! is_null ( $ofile ) )
    {
	$ocontents = file_get_contents
	    ( "$epm_data/$ofile" );
	if ( ! $ocontents )
	{
	    $errors[] = "cannot read $epm_data/$ofile";
	    return NULL;
	}
	$ojson = json_decode ( $ocontents, true );
	if ( ! $ojson )
	{
	    $errors[] = "cannot decode json in"
	              . " $epm_data/$ofile";
	    return NULL;
	}
	$option = $ojson;
    }
    else
        $option = [];

    return $best_template;
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

    if ( ! mkdir ( $dir ) )
    {
	$sysalert = "could not make $dir";
	include 'sysalert.php';
	$errors[] = "SYSTEM ERROR: could not make $dir";
    }
}

// Link files from the required list into the working
// working directory.  Ignore and do not link a required
// list file with last name component equal to the name
// of the uploaded file, if that argument is not NULL.
// The required list is generally computed by find_
// control.
//
// Errors cause error messages to be appended to errors.
//
function link_required
	( $uploaded, $work, $required, & $errors )
{
    global $epm_data;

    foreach ( $required as $rname )
    {
        $rbase = basename ( $rname );
        if ( $rname == $uploaded ) continue;

	$rname = "$epm_data/$rname";

	if ( ! is_readable ( $rname ) )
	{
	    $errors[] = "$rname is not readable";
	    continue;
	}
	if ( ! preg_match ( '/\./', $rbase )
	     &&
	     ! is_executable ( $rname ) )
	{
	    $errors[] = "$rname is not executable";
	    continue;
	}
	$rlink = "$epm_data/$work/$rbase";
	if ( ! symlink ( $rname, "$rlink" ) )
	{
	    $errors[] = "cannot symbolically link"
	              . " $rname to $rlink";
	    continue;
	}
    }
}

// Return COMMANDS list from control with OPTIONS
// inserted.  $option is option file json, or [] if
// none.
//
// Errors in options cause warning messages to be
// appended to the warnings list and corrective action
// to be taken as indicated in the warning messages.
//
// Unspecified options left in the resulting commands
// cause an error message to be appended to the errors
// list, and the commands to contain `UNSPECIFIED...'
// in place of the unspecified options.
//
function get_commands
	( $control, $option, & $warnings, & $errors )
{
    if ( ! isset ( $control[2]['COMMANDS'] ) )
        return [];
    $commands = $control[2]['COMMANDS'];
    if ( isset ( $control[2]['OPTIONS'] ) )
        $options = $control[2]['OPTIONS'];
    else
        $options = [];

    if ( ! is_array ( $options ) )
    {
	$errors[] =
	    "options misformatted in .tmpl file;" .
	    " commands ignored";
	return [];
    }

    if ( ! is_array ( $option ) )
    {
	$warnings[] =
	    ".optn file misformatted; .optn file" .
	    " ignored";
	$option = [];
    }

    foreach ( $option as $key => $value )
    {
        if ( ! isset ( $options[$key] ) )
	    $warnings[] =
	        "option $key in .optn file is not" .
		" recognized; may be obsolete; option" .
		" $key in .optn file is ignored";
    }
     
    $map = [];
    $submap = [];
    foreach ( $options as $key => $object )
    {
	if ( ! is_array ( $object ) )
	{
	    $warnings[] = "option $key missformatted in"
	                . " .tmpl file; option $key"
			. " ignored";
	    continue;
	}

	$argname = NULL;
        if ( isset ( $object['argname'] ) )
	    $argname = $object['argname'];
	$valname = NULL;
        if ( isset ( $object['valname'] ) )
	    $valname = $object['valname'];

	$name = $argname;
	if ( is_null ( $name ) )
	    $name = $valname;
	elseif ( ! is_null ( $valname ) )
	{
	    $warnings[] = "option $key has BOTH"
	                . " argname and valname;"
	                . " option $key ignored";
	    continue;
	}

	if ( is_null ( $name ) )
	{
	    $warnings[] = "option $key has no name;"
	                . " option $key ignored";
	    continue;
	}
	elseif ( ! is_string ( $name ) )
	{
	    $warnings[] = "option $key name is not a"
	                . " string; option $key"
			. " ignored";
	    continue;
	}

	if ( ! is_null ( $argname )
	     &&
	     ! isset ( $map[$argname] ) )
	    $map[$name] = "";
	    // Be sure all argnames have a value even if
	    // their associated options are illegal or
	    // "".


	if ( isset ( $option[$key] )
	     &&
	     ! is_string ( $option[$key] ) )
	{
	    $warnings[] = "override for option $key"
	                . " missformatted in"
	                . " .optn file; override"
			. " ignored";
	    unset ( $option[$key] );
	}

	if ( isset ( $object['type'] ) )
	{
	    $type = $object['type'];

	    if ( isset ( $object['default'] ) )
		$value = $object['default'];
	    else
	        $value = "UNSPECIFIED-"
		       . strtoupper ( $name );

	    if ( isset ( $option[$key] ) )
	    {
	        $ovalue = $option[$key];

		if ( $type == 'integer' )
		{
		    if ( ! pre_match
		        ( '/(\+|\-|)[1-9][0-9]+/',
			  $ovalue ) )
		    {
			$warnings[] =
			    "override $ovalue in" .
			    " .optn file for option" .
			    " $key in .tmpl file is" .
			    " not an integer;" .
			    " override ignored";
			$ovalue = NULL;
		    }
		}
		elseif ( $type == 'float' )
		{
		    if ( ! is_number ( $ovalue ) )
		    {
			$warnings[] =
			    "override $ovalue in" .
			    " .optn file for option" .
			    " $key in .tmpl file is" .
			    " not a number;" .
			    " override ignored";
			$ovalue = NULL;
		    }
		}
		elseif ( $type == 'arg' )
		{
		    if ( pre_match ( '/\s/', $ovalue ) )
		    {
			$warnings[] =
			    "override $ovalue in" .
			    " .optn file for option" .
			    " $key in .tmpl file" .
			    " contains a whitespace" .
			    " character; override" .
			    " ignored";
			$ovalue = NULL;
		    }
		}
		elseif ( $type == 'args' )
		{
		    if ( pre_match ( '/\v/', $ovalue ) )
		    {
			$warnings[] =
			    "override $ovalue in" .
			    " .optn file for option" .
			    " $key in .tmpl file" .
			    " contains a vertical" .
			    " space character;" .
			    " override ignored";
			$ovalue = NULL;
		    }
		}
		else
		{
		    $warnings[] =
			"option $key in .tmpl file" .
			" has unknown type $type;" .
			" override $ovalue from" .
			" .optn file ignored";
		    $ovalue = NULL;
		}

		if ( ( $type == 'integer'
		       ||
		       $type == 'float' )
		     &&
		     ! is_null ( $ovalue ) )
		{
		    if ( ! isset ( $options['range'] ) )
		    {
			$warnings[] =
			    "option $key in .tmpl" .
			    " file has integer or" .
			    " float type but no" .
			    " range; .optn file" .
			    " override $ovalue" .
			    " ignored";
			$ovalue = NULL;
		    }
		    else
		    {
			$range = $object['range'];

			$oldvalue = $ovalue;
			if ( $ovalue > $range[1] )
			{
			    $ovalue = $range[1];
			    $warnings[] =
				"override $oldvalue" .
				" in .optn file is" .
				" too large for" .
				" option $key;" .
				" $ovalue used" .
				" instead";
			}
			elseif ( $ovalue < $range[0] )
			{
			    $ovalue = $range[0];
			    $warnings[] =
				"override $oldvalue" .
				" in .optn file is" .
				" too small for" .
				" option $key;" .
				" $ovalue used" .
				" instead";
			}
		    }
		}

		if ( ! is_null ( $ovalue ) )
		    $value = $ovalue;
	    }
	}
	elseif ( isset ( $object['values'] ) )
	{
	    $vlist = $object['values'];
	    $value = $vlist[0];

	    if ( isset ( $option[$key] ) )
	    {
	        $ovalue = $option[$key];
		if (     array_search
		             ( $ovalue, $vlist, true )
		     !== false )
		    $value = $ovalue;
		else
		    $warnings[] =
		        "override $ovalue in .optn" .
			" file is not a legal value" .
			" for option $key; override" .
			" $ovalue ignored";
	    }

	    if ( $value == "" ) continue;
	}
	else
	{
	    $warnings[] =
		"option $key has no values or type in" .
		" .tmpl file; option $key ignored";
	    continue;
	}

	if ( ! is_null ( $argname ) )
	{
	    if ( $map[$argname] == "" )
		$map[$argname] = $value;
	    elseif ( $value != "" )
		$map[$argname] =
		    "{$map[$argname]} $value";
	}
	else
	    $submap[$valname] = $value;
    }

    $map = substitute_match ( $map, $submap );
    $commands = substitute_match ( $commands, $map );

    $unspecifieds = [];
    foreach ( $commands as $command )
    {
        $offset = 0;
        while ( preg_match ( '/UNSPECIFIED[-_A-Z]*/',
	                     $command, $matches,
			     PREG_OFFSET_CAPTURE,
			     $offset ) )
	{
	    $unspecifieds[] = $match[0][0];
	    $offset = ++ $match[0][1];
	}
    }
    if ( count ( $unspecifieds ) > 0 )
        $errors[] = "commands contain "
	          . implode ( " ", $unspecifieds );
    return $commands;
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
		" executing $command";
	    return;
	}
    }
}

// Move KEEP files, if any, from $work to $prob_dir.
// List last component names of files moved in $moved.
// Append error messages to $errors.
//
function move_keep
	( $control, $work, $prob_dir,
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
        $lfile = "$epm_data/$prob_dir/$fname";
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
// $prob_dir.  Files that are not readable are ignored;
// there can be no errors.
//
function compute_show
	( $control, $work, $prob_dir, $moved )
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
	    $sfile = "$prob_dir/$fname";
	else
	    $sfile = "$work/$fname";
	if ( is_readable ( "$epm_data/$sfile" ) )
	    $slist[] = "$sfile";
    }
    return $slist;
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
	( $upload, $problem, & $commands, & $moved,
	  & $show, & $output, & $warnings, & $errors )
{
    global $epm_data, $upload_target_ext, $make_dirs,
           $upload_maxsize, $userid, $problem,
	   $is_epm_test;

    $commands = [];
    $moved = [];
    $show = [];
    $errors_size = count ( $errors );

    if ( ! is_array ( $upload ) )
    {
        $errors[] =
	    "SYSTEM ERROR: $upload is not an array";
	return;
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
		          . " $fname, upload error"
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

    find_templates
	( $problem, $fname, $tname,
	  $templates, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( count ( $templates ) == 0 )
    {
        $errors[] =
	    "there are no templates $fname:$tname:...";
	return;
    }

    find_requires_and_options
	( $make_dirs, $templates,
	  $requires, $options, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $control = find_control
	( $make_dirs, $fname, $templates, $requires,
	  $options, $required, $option, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( is_null ( $control ) )
    {
        $errors[] =
	    "for no template $fname:$tname:... are" .
	    " all its required files pre-existing";
	return;
    }

    $prob_dir = "users/user$userid/$problem";
    $work = "$prob_dir/+work+";
    cleanup_working ( $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    link_required
	( $fname, $work, $required, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( file_exists ( "$work/$fname" ) )
    {
        $errors[] =
	    "SYSTEM_ERROR: uploaded file is $fname" .
	    " but $work/$fname already exists";
	return;
    }

    $ftmp_name = $upload['tmp_name'];

    if ( $is_epm_test ?
         ! rename ( $ftmp_name,
	            "$epm_data/$work/$fname" ) :
         ! move_uploaded_file
	       ( $ftmp_name,
		 "$epm_data/$work/$fname" ) )
    {
        $errors[] =
	    "SYSTEM_ERROR: failed to move $ftmp_name" .
	    " (alias for uploaded $fname)" .
	    " to $work/$fname";
	return;
    }

    $commands = get_commands
        ( $control, $option, $warnings, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $output = [];
    run_commands ( $commands, $work, $output, $errors );
    if ( count ( $errors ) > $errors_size )
        goto SHOW;

    if ( isset ( $control[2]['CHECKS'] ) )
    {
	run_commands ( $control[2]['CHECKS'], $work,
	               $output, $errors );
	if ( count ( $errors ) > $errors_size )
	    goto SHOW;
    }

    move_keep ( $control, $work, $prob_dir,
                $moved, $errors );
    if ( ! rename ( "$epm_data/$work/$fname",
                    "$epm_data/$prob_dir/$fname" ) )
	$errors[] =
	    "SYSTEM_ERROR: could not rename" .
	    " $epm_data/$work/$fname to" .
	    " $epm_data/$prob_dir/$fname";
    else
        $moved[] = $fname;
    if ( count ( $errors ) > $errors_size ) goto SHOW;
             

SHOW:
    $show = compute_show
        ( $control, $work, $prob_dir, $moved );
}

?>
