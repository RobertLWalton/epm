<?php

// File:    epm_make.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed Nov 20 19:12:58 EST 2019

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
if ( is_dir ( "$epm_data/template" ) )
    $template_dirs[] = "$epm_data/template";

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
        $desc = opendir ( "$emp_data/$dir" );
	if ( ! $desc )
	{
	    $errors[] =
	        "cannot open search directory" .
		" $emp_data/$dir";
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
// option file, or NULL if no such file.  The filenames
// returned in $required are relative to $epm_data.
// $dirs is the directory list used by find_requires_
// and_options, and is used to identify the local
// directory (its the first one) and identify whether
// there is more than one directory.
//
// If multiple controls satisfy required file
// constraints, ones with the largest number of required
// files are selected, and among these the first in
// the $templates list.
//
// Returns NULL if no control found meeting required
// file constraints.
//
// Any errors cause error messages to be appended to
// the errors list.
//
function find_control
	( $dirs, $templates, $requires, $options,
	  & $required, & $option, & $errors )
{
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
	}
	if ( ! $OK ) continue;

	$rlist = array_unique ( $rlist );
	$rcount = count ( $rlist );
	if ( $rcount <= $best_count )
	    continue;

	$best = $template;
	$best_count = $rcount;
	$required = $rlist;
    }

    $ofile = "$best[0].optn";
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
	    ( "$epm_data/$oname" );
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

    return $best;
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

	// PID file if it exists has the form
	//
	//    pid:expire
	//
	// where it may be assumed that if time()
	// >= expire the process that originally
	// had pid is dead.  This is necessary because
	// pid's can be reused, though generally not
	// within the same hour.
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
// working directory, using the requires map generated
// by find_templates_options_and_requires.  Ignore and
// do not line a required list file with name equal to
// the name of the uploaded file, if that argument is
// not NULL.  The required list is generally taken from
// the REQUIRES member of a make control.
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
// inserted.  $option is option file json, or
// [] if none.
//
function insert_options ( $control, $option )
{
    $commands = $control[2]['COMMANDS'];
    $match = [];
    foreach ( $options as $key => $value )
    {
        $opts = "";
	foreach ( $value as $subkey => $list )
	{
	    if ( isset ( $option[$key][$subkey] ) )
	        $opt = $option[$key][$subkey];
	    else
		$opt = $list[0];
	    if ( $opt == "" ) continue;
	    $opts = "$opts $opt";
	}
	$match[$key] = trim ( $opts );
    }
    return substitute_match ( $commands, $match );
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

// Move keep files, if any, from $work to $localdir.
// Append error messages to $errors.
//
function move_keep
	( $control, $work, $localdir, & $errors )
{
    global $epm_data;

    if ( ! isset ( $control[2]['KEEP'] ) )
        return;
    $keep = $control[2]['KEEP'];
    foreach ( $keep as $fname )
    {
        $wfile = "$epm_data/$work/$fname";
        $lfile = "$epm_data/$localdir/$fname";
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
    }
}

// Return list of files to be shown.  File and dirctory
// names are relative to $epm_data.  Files that are not
// readable are ignored; there can be no errors.
//
function compute_show ( $control, $work )
{
    global $epm_data;

    if ( ! isset ( $control[2]['SHOW'] ) )
        return [];
    $slist = [];
    $show = $control[2]['SHOW'];
    foreach ( $show as $fname )
    {
        $sfile = "$epm_data/$work/$fname";
	if ( is_readable ( $sfile ) )
	    $slist[] = "$work/$fname";
    }
    return $slist;
}

// Process an uploaded file.  Errors append error
// message to $errors.  Commands are computed using
// get_commands.  Output from commands executed is
// appended to $output (this does not include writes
// to standard error by bash, which are lost).  List
// of SHOW files is placed in $show.
//
function process_upload
	( $problem, $upload, & $commands,
	  & $show, & $output, & $errors )
{
    global $upload_target_ext, $make_dirs, $userid;

    $show = [];
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
	    "uploaded file $fname too large;" .
	    " limit is $maxsize";
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
	( $make_dirs, $templates, $requires, $options,
	  $required, $option, $errors );
    if ( count ( $errors ) > $errors_size ) return;
    if ( is_null ( $control ) )
    {
        $errors[] =
	    "for no template $fname:$tname:... are" .
	    " all its required files pre-existing";
	return;
    }

    $localdir = "users/user$userid";
    $work = "$localdir/+work+";
    cleanup_working ( $work, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    link_required
	( $fname, $work, $required, $errors );
    if ( count ( $errors ) > $errors_size ) return;

    if ( file_exists ( $work/$fname ) )
    {
        $errors[] =
	    "SYSTEM_ERROR: uploaded file is $fname" .
	    " but $work/$fname already exists";
	return;
    }

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

    $commands = get_commands ( $control, $option );

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

    move_keep ( $control, $work, $localdir, $errors );
    if ( count ( $errors ) > $errors_size ) goto SHOW;

SHOW:
    $show = compute_show ( $control, $work );
}

?>
