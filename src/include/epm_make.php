<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue Nov 19 02:01:07 EST 2019

// Functions used to make files from other files.
//
// Note that file names can have -, _, ., /, but no
// other special characters.  Of course uploaded
// files and components cannot have /.

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
if ( isset ( $params['upload_require_ext'] ) )
    $upload_require_ext =
        $params['upload_require_ext'];
else
    $upload_require_ext = [
	"in" => "(test|ftest)"];

if ( isset ( $params['upload_maxsize'] ) )
    $upload_maxsize = $params['upload_maxsize'];
else
    $upload_maxsize = 2000000;

// User Parameters:
//
if ( ! isset ( $_SESSION['epm_user_params'] ) )
    exit ( 'SYSTEM ERROR: epm_user_params not set' );
    // Should be set if epm_admin_params set.

$params = $_SESSION['epm_user_params'];
if ( isset ( $params['make_dirs'] ) )
    $make_dirs = $params['make_dirs'];
else
    $make_dirs = ["users/user$userid/."];

if ( isset ( $params['upload_maxsize'] ) )
    $upload_maxsize = $params['upload_maxsize'];
                   

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
    // Turn template source into a regexp.
    //
    if ( ! preg_match ( '/^([^:]*):/', $template,
                                       $matches ) )
        return NULL;
    $source = $matches[1];
    $source = preg_replace
        ( '/\./', '\\.', $source );
    $source = preg_replace
        ( '/PPPP/', $problem, $source,
	  -1, $PPPP_count );
    $offset = 0;
    $ids = [];
    while ( preg_match
                ( '/[A-Z]/', $source, $matches,
                  PREG_OFFSET_CAPTURE, $offset ) )
    {
        $char = $matches[0][0];
	$offset = $matches[0][1];
	if ( ! preg_match
	           ( "/\G$char{4}/", $source, $matches,
		     0, $offset ) )
	{
	    ++ $offset;
	    continue;
	}
	$source = preg_replace
	    ( "/$char{4}/", '(.*)', $source, 1 );
	$ids[] = "$char$char$char$char";
    }
    if ( ! preg_match ( "/^$source\$/", $filename,
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
// template (.tmpl) or option (.optn) file that has the
// given source extension and destination extension
// (either of which may be NULL if it not to be tested).
// Also find each required file that has the given
// required extension (e.g., .test or no extension).
//
// List the found template files in the templates
// list, each element of which has the form:
//
//   [template, filename, json-decode-of-file-contents]
//
// Here `template' is the part of the last component
// of the file name minus the extension (.tmpl or
// .optn).  This list is in the order that the files
// were found.
//
// The options map maps option file templates to the
// json decode of the file contents, preferring the
// first file found with a given template.
//
// The requires map maps the last component of file
// names to the full name (relative to epm_data) of
// the first file found with that last component.
//
// The extension arguments, if not NULL, are regular
// expressions.  E.g., '(in|test|)' or just 'cc'.
// Required file extensions may NOT include tmpl or
// optn.
//
// All directory and full file names are relative to
// epm_data.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function find_templates_options_and_requires
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
	    if ( preg_match ( '/^\.+$/', $fname ) )
	        continue;

	    if ( preg_match ( '/^(.*)\.([^.]*)$/',
	                      $fname, $matches ) )
	    {
		$template = $matches[1];
	        $ext = $matches[2];
	    }
	    else
	        $ext = "";

	    if ( ! is_null ( $req_ext )
	         &&
	         preg_match ( "/^$req_ext$/", $ext ) )
	    {
		if ( ! isset ( $requires[$fname] ) )
		    $requires[$fname] = "$dir/$fname";
		continue;
	    }

	    if ( ! preg_match
	               ( '/^(tmpl|optn)$/', $ext ) )
	        continue;

	    if ( ! preg_match
	           ( '/^([^:]+):([^:]+):/',
		     $fname, $matches ) )
	    {
	        $errors[] = "bad template or option"
		         . " file name format $fname";
	        continue;
	    }

	    $src = $matches[1];
	    $des = $matches[2];

	    if ( ! is_null ( $src_ext )
		 &&
		 ( $src_ext == "" ?
		   preg_match ( '/\./', $src ) :
		   ! preg_match
			 ( "/\\.$src_ext\$/",
			   $src ) ) )
		continue;
	    if ( ! is_null ( $des_ext )
		 &&
		 ( $des_ext == "" ?
		   preg_match ( '/\./', $des ) :
		   ! preg_match
			 ( "/\\.$des_ext\$/",
			   $des ) ) )
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

	    if ( $ext == 'tmpl' )
	        $templates[] =
		    ["$src", "$dir/$fname", $json];
	    else
	    {
	        if ( ! isset ( $options[$template] ) )
		    $options[$template] = $json;
	    }
	}
	closedir ( $desc );
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
// and second ones earliest in the templates list.  Use
// the options map options file with the same template,
// if it exists.
//
// Any errors cause error messages to be appended to
// the errors list.
//
// If no template file is found, return NULL.
//
function find_make_control
	( $problem, $upload,
	  $templates, $options, $requires, & $errors )
{
    $best_json = NULL;
    $best_match = NULL;
    $best_template = NULL;
    $best_requires = -1;
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

	$OK = true;
	foreach ( $reqval as $required )
	{
	    if ( $required == $problem ) continue;
	    if ( ! isset ( $requires[$required] ) )
	    {
	        $OK = false;
		break;
	    }
	}
	if ( ! $OK ) continue;

	if ( $best_requires >= count ( $reqval ) )
	    continue;

	$best_json = $control;
	$best_template = $template;
	$best_match = $match;
	$best_requires = count ( $reqval );
    }
    foreach ( $options[$best_template]
              as $key => $value )
        $best_json[$key] = substitute_match
	    ( $value, $best_match );

    return $best_json;
}

// Clean up a working directory.  If it has a PID file,
// kill the PID.  Then if it exists, unlink its contents
// and the directory itself, orphaning the directory.
// Then create a new directory under the same name.
//
// Directory name is relative to epm_data.
//
// Returns true on success and false on failure, and in
// the latter case issues a sysalert.
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
	if ( $PID )
	{
	    exec ( "kill -1 $PID" );
	    usleep ( 500000 );
	    exec ( "kill -9 $PID" );
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
// working directory, using the requires map generated
// by find_templates_options_and_requires.  Ignore and
// do not line a required list file with name equal to
// the name of the uploaded file, if that argument is
// not NULL.  The required list is generally taken from
// the REQUIRES member of a make control.
//
// Errors cause error messages to be appended to errors.
//
function link_requires
	( $dir, $uploaded, $required, $requires,
	  & $errors )
{
    global $epm_data;

    foreach ( $required as $fname )
    {
        if ( $fname == $uploaded ) continue;
	if ( ! isset ( $requires[$fname] ) )
	{
	    $errors[] = "$fname not in requires list";
	    continue;
	}
	$rname = "$epm_data/$requires[$fname]";
	if ( ! is_readable ( $rname ) )
	{
	    $errors[] = "$rname is not readable";
	    continue;
	}
	if ( ! preg_match ( '/\./', $fname )
	     &&
	     ! is_executable ( $rname ) )
	{
	    $errors[] = "$rname is not executable";
	    continue;
	}
	$link = "$epm_data/$dir/$fname";
	if ( ! symlink ( $rname, "$link" ) )
	{
	    $errors[] = "cannot link $rname to $link";
	    continue;
	}
    }
}

// Return COMMANDS list from control with OPTIONS
// inserted.
//
function get_commands ( $control )
{
    $commands = $control['COMMANDS'];
    $options = $control['OPTIONS'];
    $match = [];
    foreach ( $options as $key => $value )
    {
        $opts = "";
	foreach ( $value as $item )
	{
	    $opt = $item[0];
	    if ( $opt == "" ) continue;
	    $opts = "$opts $opt";
	}
	$match[$key] = trim ( $opts );
    }
    return substitute_match ( $commands, $match );
}

// Process an uploaded file.  Errors append error
// message to $errors.  Output from commands
// executed is appended to $output (this does not
// include writes to standard error by bash). 
//
function process_upload
	( $upload, & $output, & $errors )
{
    $errors_size = count ( $errors );
    if ( ! isset ( $_FILES[$upload] ) )
    {
        $errors[] =
	    "SYSTEM ERROR: \$_FILES['$upload'] not set";
	return;
    }
    $fname = $_FILES[$upload]['name'];
    if ( ! preg_match ( '/^[-_.a-zA-Z0-9]*$/',
                        $fname ) )
    {
        $errors[] =
	    "uploaded file $fname has character" .
	    " other than letter, digit, ., -, or _";
	return;
    }
    if ( ! preg_match ( '/\.([^.]+)$/', $fname,
                                        $matches ) )
    {
        $errors[] =
	    "uploaded file $fname has no extension";
	return;
    }
    $ext = $matches[1];

    $ferror = $_FILES[$upload]['error'];
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

    $fsize = $_FILES[$upload]['size'];
    if ( $fsize > $upload_maxsize )
    {
        $errors[] =
	    "uploaded file $fname to large;" .
	    " your limit is $maxsize";
	return;
    }

    $req_ext = NULL;
    if ( isset ( $upload_require_ext[$ext] ) )
        $req_ext = $upload_require_ext[$ext];

    find_templates_options_and_requires
	( $make_dirs, $ext, NULL, $req_ext,
	  $templates, $options, $requires, $errors );

    if ( count ( $errors ) > $errors_size ) return;

    $control = find_make_control
	( $problem, $fname,
	  $templates, $options, $requires, $errors );

    if ( count ( $errors ) > $errors_size ) return;
    if ( ! $control )
    {
        $errors[] =
	    "cannot upload $fname, because it" .
	    " does not have legal extension" .
	    " or file name pattern";
	return;
    }

    $work = "users/user$userid/work";
    cleanup_working ( $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    $required = $control['REQUIRES'];
    link_requires
	( $work, $fname, $required, $requires,
	  $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( file_exists ( $work/$fname ) )
    {
        $errors[] =
	    "SYSTEM_ERROR: uploaded file is $fname" .
	    " but $work/$fname already exists";
	return;
    }

    $commands = get_commands ( $control );

    $ftmp_name = $_FILES[$upload]['tmp_name'];

    if ( ! move_uploaded_file
               ( $ftmp_name, $work/$fname ) )
    {
        $errors[] =
	    "SYSTEM_ERROR: failed to move $ftmp_name" .
	    " (alias for uploaded $fname)" .
	    " to $work/$fname";
	return;
    }

    foreach ( $commands as $command )
    {
        exec ( $command, $output, $ret );
	if ( $ret != 0 )
	{
	    $errors[] =
		"error code $ret returned upon" .
		" executing $command";
	    return;
	}
    }
}

?>
