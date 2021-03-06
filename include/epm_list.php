<?php

    // File:	epm_list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed Oct  7 04:22:32 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Functions for managing lists.

    // List names have the form:
    //
    //	-:-		List of Your Problems
    //  -:NAME  	Your NAME list
    //  PROJECT:-	List of PROJECT Problems
    //  ACCOUNT:NAME	Published NAME list of ACCOUNT
    //  +favorites+	Your favorites list.
    //
    // Problem names have the form:
    //
    //  -:PROBLEM		Your PROBLEM
    //  PROJECT:PROBLEM		PROJECT PROBLEM
    //
    // Generic names (other than +favorites+) have the
    // form:
    //
    //  ROOT:LEAF
    //
    // Problem and favorites lists consist of lines
    // each of the form:
    //
    //		TIME ROOT LEAF
    //
    // optionally followed, for problem lists, by a
    // blank line followed by a description.
    //
    // Internal representation of lists consists of
    // a list with elements of the form:
    //
    //		[TIME, ROOT, LEAF]

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $aid ) )
	exit ( 'ACCESS ERROR: $aid not set' );

    // See page/manage.php for the format of +priv+
    // files.
    //
    // A privilege map is a map
    //
    //	    PRIV => '+'		Privilege granted.
    //	    PRIV => '-'		Privilege denied.
    //
    // of privileges constructed by reading +priv+
    // files.  If PRIV is not in the map, no
    // matching lines for PRIV were found in the
    // files read so far.

    // Read actual or proposed contents of a  +priv+
    // file and add to the privilege $map.  File
    // lines with regular expressions are matched
    // against $aid and groups are processed.  PRIVs
    // with no matching lines are not set in the map.
    // Lines with PRIV that is already set in the map
    // are ignored.  Groups are set in the map as if
    // they were privileges.  The $map is NOT
    // initialized.
    //
    // However line formats are checked.  Lines
    // whose first non-whitespace character is '#"
    // are ignored.  Blank lines are also ignored.
    //
    // If an error is found, $error_header is appended
    // to $errors before the first error message.  All
    // the other error messages are indented.
    //
    function process_privs
	    ( & $map, $contents, $allowed_privs,
	      & $errors, $error_header )
    {
        global $aid, $epm_name_re;

	$lines = explode ( "\n", $contents );
	$error_found = false;
	$priv_re = '/^(\+|\-)\h+(\S+)\h+(\S+)$/';
	foreach ( $lines as $line )
	{
	    $line = trim ( $line );
	    if ( $line == '' ) continue;
	    if ( $line[0] == '#' ) continue;
	    if ( ! preg_match ( $priv_re,
	                        $line, $matches ) )
	    {
		if ( ! $error_found )
		{
		    $error_found = true;
		    $errors[] = $error_header;
		}
		$errors[] =
		    "    badly formed line `$line'";
		continue;
	    }
	    $sign = $matches[1];
	    $priv = $matches[2];
	    $re   = $matches[3];

	    $errs = [];
	    $match = false;
	    if ( $priv[0] == '@' )
	    {
	        if (! preg_match
		          ( $epm_name_re,
			    substr ( $priv, 1 ) ) )
		    $errs[] = "bad group name $priv";
	    }
	    elseif ( ! in_array ( $priv,
	                          $allowed_privs,
				  true ) )
		    $errs[] = "privilege $priv not"
		            . " allowed";

	    if ( $re[0] == '@' )
	    {
	        if ( ! preg_match
		           ( $epm_name_re,
			     substr ( $re, 1 ) ) )
		    $errs[] = "bad group name $re";
		elseif ( isset ( $map[$re] )
		         &&
			 $map[$re] == '+' )
		    $match = true;
	    }
	    else
	    {
		$r = preg_match ( "/^($re)\$/", $aid );
		if ( $r === false )
		    $errs[] = "bad RE";
		elseif ( $r == 1 )
		    $match = true;
	    }

	    if ( count ( $errs ) > 0 )
	    {
		if ( ! $error_found )
		{
		    $error_found = true;
		    $errors[] = $error_header;
		}
		foreach ( $errs as $err )
		    $errors[] =
			"    $err in line `$line'";
	    }
	    elseif ( $match
	             &&
		     ! isset ( $map[$priv] ) )
	        $map[$priv] = $sign;
	}
    }

    // Read a +priv+ file and add to the privilege
    // $map.  The file is relative to $epm_data.
    // If it does not exist, nothing is done.
    // File line formatting errors append error
    // messages to $errors.
    //
    function read_priv_file
	( & $map, $fname, $allowed_privs, & $errors )
    {
        global $epm_data;

	if ( ! file_exists ( "$epm_data/$fname" ) )
	    return;
	$c = ATOMIC_READ ( "$epm_data/$fname" );
	if ( $c === false )
	    ERROR ( "cannot read existant $fname" );
	process_privs
	    ( $map, $c, $allowed_privs,
	      $errors, "In $fname:" );
    }

    // Return privilege map containing just the
    // results of $epm_priv_prefix.
    //
    function prefix_priv_map
	( & $map, & $errors )
    {
        global $epm_priv_prefix;
	$map = [];
	process_privs
	    ( $map, $epm_priv_prefix, ['owner'],
	      $errors, "In \$epm_priv_prefix:" );
    }

    // Return the privilege map of a project.  If
    // $errors argument not given, errors are fatal.
    //
    function project_priv_map
        ( & $map, $project, & $errors = NULL )
    {
        global $epm_project_privs;
	$no_errors = ! isset ( $errors );
        prefix_priv_map ( $map, $errors );
	read_priv_file
	     ( $map, "projects/$project/+priv+",
                     $epm_project_privs, $errors );
	if ( $no_errors && isset ( $errors )
	                && count ( $errors ) > 0 )
	    ERROR ( implode ( PHP_EOL, $errors ) );
    }

    // Ditto but use $contents as the (proposed)
    // project privilege file contents.
    //
    function check_project_priv
        ( & $map, $project, $contents, & $errors )
    {
        global $epm_project_privs;
        prefix_priv_map ( $map, $errors );
	process_privs
	    ( $map, $contents, $epm_project_privs,
	      $errors,
	      "In proposed $project project" .
	      " privilege file:" );
    }

    // Return the privilege map of a project problem.
    // If $errors argument not given, errors are fatal.
    //
    function problem_priv_map
	    ( & $map, $project, $problem,
	      & $errors = NULL )
    {
        global $epm_problem_privs, $epm_project_privs;
	$no_errors = ! isset ( $errors );
        prefix_priv_map ( $map, $errors );
	read_priv_file
	    ( $map, "projects/$project/+priv+",
	      $epm_project_privs, $errors );
	read_priv_file
	    ( $map,
	      "projects/$project/$problem/+priv+",
	      $epm_problem_privs, $errors );
	if ( $no_errors && isset ( $errors )
	                && count ( $errors ) > 0 )
	    ERROR ( implode ( PHP_EOL, $errors ) );
    }

    // Ditto but use $contents as the (proposed)
    // project problem privilege file contents.
    //
    function check_problem_priv
        ( & $map, $project, $problem, $contents,
	  & $errors )
    {
        global $epm_problem_privs, $epm_project_privs;
        prefix_priv_map ( $map, $errors );
	read_priv_file
	    ( $map, "projects/$project/+priv+",
	      $epm_project_privs, $errors );
	process_privs
	    ( $map, $contents, $epm_problem_privs,
	      $errors,
	      "In proposed $project $problem" .
	      " problem privilege file:" );
    }

    // Return the list of projects that have one of
    // the given privileges, or if $privs = NULL,
    // that have any privilege.  The list is sorted in
    // natural order.
    //
    function read_projects ( $privs = NULL )
    {
	global $epm_data, $epm_name_re;
	$projects = [];
	$ps = @scandir ( "$epm_data/projects" );
	if ( $ps == false )
	    ERROR ( "cannot read 'projects'" .
	            " directory" );
	foreach ( $ps as $project )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $project ) )
	        continue;
	    project_priv_map ( $map, $project );

	    if ( $privs == NULL )
	        foreach ( $map as $priv => $sign )
		{
		    if ( $priv[0] == '@' )
		        continue;
		    elseif ( $sign == '+' )
		    {
		        $projects[] = $project;
			break;
		    }
		}
	    else
		foreach ( $privs as $priv )
		{
		    if ( ! isset ( $map[$priv] ) )
			continue;
		    if ( $map[$priv] == '-' )
			continue;
		    $projects[] = $project;
		    break;
		}
	}
	natsort ( $projects );
	return $projects;
    }

    // Given a list of VALUEs return a string whose
    // segments have the form
    //
    //	    <option value='VALUE'>
    //      VALUE
    //      </option>
    //
    // If SELECTED != NULL, segment with VALUE ==
    // SELECTED is marked as selected.
    //
    function values_to_options
	    ( $list, $selected = NULL )
    {
	$r = '';
	foreach ( $list as $value )
	{
	    if ( $value == $selected )
	        $s = 'selected';
	    else
	        $s = '';
	    $r .= "<option value='$value' $s>"
		. "$value</option>";
	}
	return $r;
    }

    // Return a map from an account's own problems to
    // the projects each is descended from, or '-' if a
    // problem is not descended from a project.  Sort
    // the map by problems (keys) in natural order.
    //
    function read_problem_map()
    {
	global $epm_data, $aid, $epm_name_re;

	$pmap = [];
	$f = "accounts/$aid";
	$ps = @scandir ( "$epm_data/$f" );
	if ( $ps == false )
	    ERROR ( "cannot read $f directory" );
	foreach ( $ps as $problem )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
	        continue;
	    $g = "$f/$problem/+parent+";
	    $re = "/\/\.\.\/projects\/([^\/]+)\/"
	        . "$problem\$/";
	    if ( is_link ( "$epm_data/$g" ) )
	    {
	        $s = @readlink ( "$epm_data/$g" );
		if ( $s === false )
		    ERROR ( "cannot read link $g" );
		if ( ! preg_match
		           ( $re, $s, $matches ) )
		    ERROR ( "link $g value $s is" .
		            " mal-formed" );
		$pmap[$problem] = $matches[1];
	    }
	    else
		$pmap[$problem] = '-';
	}
	ksort ( $pmap, SORT_NATURAL );
	return $pmap;
    }

    // Given a list name return the file name of the
    // list relative to $epm_data, or return NULL if
    // the name is of the form -:- or PROJECT:-.
    //
    function listname_to_filename ( $listname )
    {
        global $aid;

	if ( $listname[0] == '+' )
	    return "accounts/$aid/+lists+/$listname";

        list ( $account, $name ) =
	    explode ( ':', $listname );
	if ( $name == '-' )
	    return NULL;
	elseif ( $account == '-' )
	    return "accounts/$aid/+lists+/$name.list";
	else
	    return "lists/$account:$name.list";
    }

    // Given a name make a new empty file for the
    // listname '-:name' and add its name to the
    // beginning of +favorites+ using the current
    // time as the TIME value.  If there are errors
    // append to $errors.
    //
    function make_new_list ( $name, & $errors )
    {
        global $epm_data, $aid, $epm_name_re,
	       $epm_time_format;

	if ( ! preg_match ( $epm_name_re, $name ) )
	{
	   $errors[] = "$name is badly formed"
	             . " list name";
	   return;
	}
	$f = "accounts/$aid/+lists+/$name.list";
	if ( file_exists ( "$epm_data/$f" ) )
	{
	   $errors[] = "the $name list already"
	             . " exists";
	   return;
	}

	if ( ! is_dir
	         ( "$epm_data/accounts/$aid/+lists+" ) )
	{
	    $m = umask ( 06 );
	    @mkdir ( "$epm_data/accounts", 02771 );
	    @mkdir ( "$epm_data/accounts/$aid", 02771 );
	    @mkdir ( "$epm_data/accounts/$aid/+lists+",
		     02770 );
	    umask ( $m );
	}

	$r = ATOMIC_WRITE ( "$epm_data/$f", '' );
	if ( $r === false )
	    ERROR ( "could not write $f" );
	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "could not stat $f" );
	$time = strftime ( $epm_time_format, $time );

	$f = "accounts/$aid/+lists+/+favorites+";
	$flist = read_file_list ( $f );
	array_unshift
	    ( $flist, [$time, '-', $name] );
	write_file_list ( $f, $flist );
	    // We need to remove any previous
	    // -:$name in case list was deleted
	    // and then re-created.
    }

    // Delete the named list, or append to $errors.
    // However if $execute is false, just check for
    // errors and return.
    //
    function delete_list
            ( $name, & $errors, $execute )
    {
        global $epm_data, $aid;

        $f = "accounts/$aid/+lists+/$name.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "you have no list named"
		      . " $name";
	    return;
	}

	if ( ! $execute ) return;

	unlink ( "$epm_data/$f" );

	$g = "lists/$aid:$name.list";
	if ( is_link ( "$epm_data/$g" ) )
	    unlink ( "$epm_data/$g" );

	$f = "accounts/$aid/+lists+/+favorites+";
	delete_from_file_list
	    ( $f, '-', $name );
    }

    // Publish the named list, or append to $errors
    // if its already published.
    //
    function publish_list ( $name, & $errors )
    {
        global $epm_data, $aid;

	$f = "accounts/$aid/+lists+/$name.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "you have no list named"
		      . " $name";
	    return;
	}
	$g = "lists/$aid:$name.list";
	if ( is_link ( "$epm_data/$g" ) )
	{
	    $errors[] = "Your $name is already"
	              . " published";
	    return;
	}
	@mkdir ( "$epm_data/lists", 02770 );
	if ( ! symbolic_link ( "../$f",
	                       "$epm_data/$g" ) )
	    ERROR ( "cannot make link $g" );
    }

    // Unpublish the named list, or append to $errors
    // if its already unpublished.
    //
    function unpublish_list ( $name, & $errors )
    {
        global $epm_data, $aid;

	$f = "accounts/$aid/+lists+/$name.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "you have no list named"
		      . " $name";
	    return;
	}
	$g = "lists/$aid:$name.list";
	if ( ! is_link ( "$epm_data/$g" ) )
	{
	    $errors[] = "Your $name is already"
	              . " unpublished";
	    return;
	}
	if ( ! unlink ( "$epm_data/$g" ) )
	    ERROR ( "cannot unlink $g" );
    }

    // Return the lines from the list with the given
    // $filename in the form of a list of elements each
    // of the form
    //
    //	    [TIME ROOT LEAF]
    //
    // Reading stops with the first blank line.  If the
    // file does not exist, [] is returned.  Line
    // formatting errors are fatal.  Lines with
    // duplicate ROOT:LEAF are fatal.
    //
    function read_file_list ( $filename )
    {
        global $epm_data;
	$list = [];
	$map = [];
	$c = ATOMIC_READ ( "$epm_data/$filename" );
	if ( $c !== false )
	{
	    $c = explode ( "\n", $c );
	    foreach ( $c as $line )
	    {
		$line = trim ( $line );
		if ( $line == '' ) break;

		$line = preg_replace
		    ( '/\h+/', ' ', $line );
		$items = explode ( ' ', $line );
		if ( count ( $items ) != 3 )
		    ERROR ( "badly formatted line" .
			    " '$line' in $filename" );
		list ( $time, $root, $leaf ) =
		    $items;
		$key = "$root:$leaf";
		if ( isset ( $map[$key] ) )
		    ERROR ( "line '$line' duplicates" .
			    " line '{$map[$key]}' in" .
			    " $filename" );
		$map[$key] = $line;
		$list[] = $items;
	    }
	}
	return $list;
    }

    // Write a list of elements of the form
    //
    //	    [TIME ROOT LEAF]
    //
    // to the named file, preserving any part of the
    // file that is at or after its first blank line.
    // Each element becomes one line consisting of the
    // element members separated by 2 single spaces.
    //
    // If a ROOT:LEAF occurs several times, only the
    // first is kept, but the output TIME is the latest
    // of the associated TIMEs.
    //
    // If writing the file would not change its
    // contents, the file write is surpressed and false
    // is returned.  Otherwise true is returned.
    //
    function write_file_list ( $filename, $list )
    {
        global $epm_data;
	$keys = [];
	$map = [];
	foreach ( $list as $items )
	{
	    list ( $time, $root, $leaf ) = $items;
	    $key = "$root:$leaf";
	    if ( isset ( $map[$key] ) )
	    {
	        $time2 = $map[$key];
		if ( $time > $time2 )
		    $map[$key] = $time;
	    }
	    else
	    {
	        $map[$key] = $time;
		$keys[] = $key;
	    }
	}
	$lines = [];
	foreach ( $keys as $key )
	{
	    list ( $root, $leaf ) =
	        explode ( ':', $key );
	    $lines[] = "{$map[$key]} $root $leaf";
	}

	$mismatch_found = true;
	$c = ATOMIC_READ ( "$epm_data/$filename" );
	if ( $c !== false )
	{
	    $i = 0;
	    $limit = count ( $lines );
	    $mismatch_found = false;
	    $flines = explode ( "\n", $c );
	    $in_description = false;
	    $last_blank = true;
	    // This deletes blank lines at end of
	    // file.
	    foreach ( $flines as $fline )
	    {
	        $fline = rtrim ( $fline );
		    // We need to find blank lines BUT
		    // leave indentation of non-blank
		    // lines.
		if ( $fline == '' )
		{
		    if ( ! $in_description )
		        $in_description = true;
		    else
		        $last_blank = true;
		}
		else
		{
		    if ( ! $in_description )
		    {
 			if ( $i >= $limit
                             ||
			        trim ( $fline )
			     != $lines[$i] )
			    $mismatch_found = true;
			++ $i;
		        continue;
		    }

		    if ( $last_blank )
		    {
		        $lines[] = '';
			$last_blank = false;
		    }
		    $lines[] = $fline;
		}
	    }
	    if ( $i < $limit ) $mismatch_found = true;
	        // $i == number list items in existing
		// file
	}

	if ( ! $mismatch_found ) return false;

	$c = '';
	foreach ( $lines as $line )
	    $c .= $line . PHP_EOL;
	$r = ATOMIC_WRITE ( "$epm_data/$filename", $c );
	if ( $r === false )
	    ERROR ( "cannot write $filename" );
	return true;
    }

    // Delete all lines `TIME $root $leaf' from
    // list with given $filename.
    //
    function delete_from_file_list
	    ( $filename, $root, $leaf )
    {
	$list = read_file_list ( $filename );
	$changed = false;
	$out = [];
	foreach ( $list as $e )
	{
	    if (    $e[1] == $root
	         && $e[2] == $leaf )
	        $changed = true;
	    else
	        $out[] = $e;
	}
	if ( $changed )
	    write_file_list ( $filename, $out );
    }

    // Replace description in a list file.  Append
    // error instead if description contains < or >.
    // Written description is NOT altered or otherwise
    // checked.
    //
    function write_list_description
	    ( $filename, $description, & $errors )
    {
        global $epm_data;

	$lines = explode ( "\n", $description );
	foreach ( $lines as $line )
	{
	    if ( preg_match ( '/(<|>)/', $line ) )
	    {
		$line = trim ( $line );
	        $errors[] =
		    "< or > is in description line:" .
		    PHP_EOL . "    $line";
		return;
	    }
	}

	$c = ATOMIC_READ ( "$epm_data/$filename" );
	if ( $c === false ) $c = '';
	$c = explode ( "\n", $c );
	    // If $c was '' it is now ['']
	$r = '';
	foreach ( $c as $line )
	{
	    $line = rtrim ( $line );
	    if ( $line == '' ) break;
	    $r .= $line . PHP_EOL;
	}
	if ( $description != '' )
	    $r .= PHP_EOL . $description;

	$r = ATOMIC_WRITE ( "$epm_data/$filename", $r );
	if ( $r === false )
	    ERROR ( "cannot write $filename" );
    }

    // Read list description and return it as as HTML.
    // Returns '' if file does not exist.
    //
    function read_list_description ( $filename )
    {
        global $epm_data;

	$c = ATOMIC_READ ( "$epm_data/$filename" );
	if ( $c === false ) return '';

	$c = explode ( "\n", $c );
	    // If $c was '' it is now ['']
	$r = '';
	$in_description = false;
	$after_blank = true;
	$paragraph = '';
	foreach ( $c as $line )
	{
	    $line = rtrim ( $line );
	    if ( $line == '' )
	    {
	        if ( ! $in_description )
		{
		    $in_description = true;
		    continue;
		}
		if ( $after_blank ) continue;

		if ( $paragraph != '' )
		{
		    $r .= "</$paragraph>" . PHP_EOL;
		    $paragraph = '';
		}
		$after_blank = true;
		continue;
	    }
	    elseif ( ! $in_description )
	        continue;

	    $line = str_replace
	        ( "\t", "        ", $line );
	    $desired =
		 ( $line[0] == ' ' ? 'pre' : 'p' );

	    if ( $after_blank )
	    {
	        $paragraph = $desired;
		$r .= "<$paragraph>" . PHP_EOL;
		$after_blank = false;
	    }
	    elseif ( $paragraph != $desired )
	    {
		// Switch paragraph type.
		//
		$r .= "</$paragraph>" . PHP_EOL;
		$paragraph = $desired;
		$r .= "<$paragraph>" . PHP_EOL;
	    }
	    $r .= $line . PHP_EOL;
	}

	if ( $paragraph != '' )
	    $r .= "</$paragraph>" . PHP_EOL;
	return $r;
    }

    // Return the problems in $project in the form
    // of a list of elements each of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where TIME is the modification time of the
    // PROBLEM's +changes+ file if that exists, or
    // of the PROBLEM's directory otherwise.  List
    // elements are sorted most recent TIME first.
    //
    function read_project_list ( $project )
    {
        global $epm_data, $epm_name_re,
	       $epm_time_format;

	// First build map from PROBLEM to TIME
	// and sort on TIME.
	//
	$map = [];
	$d = "projects/$project";
	$problems = @scandir ( "$epm_data/$d" );
	if ( $problems === false )
	    ERROR ( "cannot read $d" );
	foreach ( $problems as $problem )
	{
	    if ( ! preg_match ( $epm_name_re,
	                        $problem ) )
	        continue;
	    $f = "$d/$problem/+changes+";
	    $time = @filemtime ( "$epm_data/$f" );
	    if ( $time === false )
		$time = @filemtime ( "$epm_data/$d" );
		// For problems copied from repository.
	    $map[$problem] = $time;
	}
	arsort ( $map, SORT_NUMERIC );

	$list = [];
	foreach ( $map as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       $project, $problem];
	return $list;
    }

    // Return a list whose elements have the form:
    //
    //		[TIME - PROBLEM]
    //
    // for all PROBLEMs accounts/AID/PROBLEM where TIME
    // is the modification time of the problem
    // directory.  Sort by TIME.
    //
    function read_your_list()
    {
	global $epm_data, $aid, $epm_name_re,
	       $epm_time_format;

	// First build map from PROBLEM to TIME
	// and sort on TIME.
	//
	$map = [];
	$f = "accounts/$aid";
	$ps = @scandir ( "$epm_data/$f" );
	if ( $ps == false )
	    ERROR ( "cannot read $f directory" );
	foreach ( $ps as $problem )
	{
	    if ( ! preg_match
	               ( $epm_name_re, $problem ) )
	        continue;

	    $g = "$f/$problem";
	    $time = @filemtime ( "$epm_data/$g" );
	    if ( $time === false )
	        ERROR ( "cannot stat $g" );
	    $map[$problem] = $time;
	}
	arsort ( $map, SORT_NUMERIC );
	$list = [];
	foreach ( $map as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       '-', $problem];
	return $list;
    }

    // Read and return a problem list given the
    // listname.
    // 
    // Issue warnings for any problems that no longer
    // exist and delete them from the list (if the
    // listname is ROOT:- there cannot be any such
    // problems).  If the list is local (-:NAME), delete
    // non-existant problems from the list itself.
    //
    // Also, if the list is not your list, ignore any
    // -:PROBLEM entries without warnings.
    //
    function read_problem_list
	    ( $listname, & $warnings )
    {
        global $epm_data, $aid;

	if ( $listname == '-:-' )
	    return read_your_list();
	elseif ( preg_match ( '/^(.+):-$/',
	                      $listname, $matches ) )
    	    return read_project_list ( $matches[1] );

	$f = listname_to_filename ( $listname );
	$old_list = read_file_list ( $f );
	list ( $account, $name ) =
	    explode ( ':', $listname );
	if ( $account == $aid ) $account = '-';

	$first = true;
	$new_list = [];
	foreach ( $old_list as $e )
	{
	    list ( $time, $project, $problem ) = $e;
	    if ( $project == '-' )
	    {
	        if ( $account != '-' ) continue;
		$d = "accounts/$aid/$problem";
	    }
	    else
	        $d = "projects/$project/$problem";

	    if ( is_dir ( "$epm_data/$d" ) )
	    {
	        $new_list[] = $e;
		continue;
	    }

	    if ( $first )
	    {
	        $first = false;
	        $warnings[] = "The following problems"
		            . " no longer exist";
		if ( $account == '-' )
		    $warnings[] = "and have been"
		                . " deleted from"
				. " Your $name:";
		else
		    $warnings[] = "and have been"
		                . " ignored:";
	    }
	    if ( $project == '-' ) $project = 'Your';
	    $warnings[] = "    $project $problem";
	}

	if ( ! $first && $account == '-' )
	    write_file_list ( $f, $new_list );

	return $new_list;
    }

    // Read and return the favorites list.
    //
    // If any entries no longer exist, issue warnings
    // messages for them and delete them from the
    // favorites list.
    //
    // If the resulting list is empty, construct a
    // new favorites list consisting of your problems
    // and problems of all projects for which user
    // has a privilege listed in $privs, or of all
    // projects for which user has any privilege
    // if $privs = NULL.
    //
    function read_favorites_list ( & $warnings )
    {
	global $epm_data, $aid, $epm_time_format;

	$f = "accounts/$aid/+lists+/+favorites+";
	$old_list = read_file_list ( $f );

	$new_list = [];
	$first = true;
	foreach ( $old_list as $e )
	{
	    list ( $time, $root, $name ) = $e;
	    if ( $root == '-' && $name == '-' )
	        $g = "accounts/$aid";
	    elseif ( $name == '-' )
	        $g = "projects/$root";
	    elseif ( $root == '-' )
		$g = "accounts/$aid/+lists+/$name.list";
	    else
		$g = "lists/$root:$name.list";
	    if ( file_exists ( "$epm_data/$g" ) )
	    {
	        $new_list[] = $e;
		continue;
	    }

	    if ( $first )
	    {
	        $first = false;
	        $warnings[] = "The following lists"
		            . " no longer exist";
		$warnings[] = "and have been deleted"
		            . " from Your Favorites:";
	    }
	    if ( $root == '-' ) $root = 'Your';
	    if ( $name == '-' ) $name = 'Problems';
	    $warnings[] = "    $root $name";
	}

	if ( count ( $new_list ) > 0 )
	{
	    if ( ! $first )
	        write_file_list ( $f, $new_list );
	    return $new_list;
	}

	if ( ! $first )
	    $warnings[] = "no lists are left in Your"
	                . " Favorites; reinitializing"
			. " Your Favorites";

	if ( ! is_dir
	         ( "$epm_data/accounts/$aid/+lists+" ) )
	{
	    $m = umask ( 06 );
	    @mkdir ( "$epm_data/accounts", 02771 );
	    @mkdir ( "$epm_data/accounts/$aid", 02771 );
	    @mkdir ( "$epm_data/accounts/$aid/+lists+",
		     02770 );
	    umask ( $m );
	}

	$g = "accounts/$aid";
	$time = @filemtime ( "$epm_data/$g" );
	if ( $time === false )
	    ERROR ( "cannot stat $g" );
	$time = strftime ( $epm_time_format, $time );
	$new_list[] = [$time, '-', '-'];
	foreach ( read_projects() as $project )
	{
	    $g = "projects/$project";
	    $time = @filemtime ( "$epm_data/$g" );
	    if ( $time === false )
	        ERROR ( "cannot stat $g" );
	    $time = strftime
	        ( $epm_time_format, $time );
	    $new_list[] = [$time, $project, '-'];
	}
	write_file_list ( $f, $new_list );
	return $new_list;
    }

    // If $key is in list, return its $list entry,
    // else return NULL.  $key has the form
    // ROOT:LEAF.  List entries have the form
    // [TIME, ROOT, LEAF].
    //
    function in_list ( $key, $list )
    {
        list ( $root, $leaf ) =
            explode ( ':', $key );
	foreach ( $list as $item )
	{
	    list ( $time, $list_root, $list_leaf )
	        = $item;
	    if ( $root == $list_root
	         &&
		 $leaf == $list_leaf )
	        return $item;
	}
	return NULL;
    }

    // Given a list of elements of the form
    //
    //		[TIME ROOT LEAF]
    //
    // return a string whose segments have the form
    //
    //	    <option value='ROOT:LEAF'>
    //      $root $leaf $time
    //      </option>
    //
    // where $root is ROOT unless that is `-', in
    // which case it is `Your', $leaf is LEAF unless
    // that is `-', in which case it is `Problems', and
    // $time is the first 10 characters of TIME (i.e.,
    // the day, excluding the time of day).
    //
    // However, if 'ROOT:LEAF' is listed in $exclude,
    // omit that option.  Also if $select is not NULL
    // but is 'ROOT:LEAF', add the `selected'
    // attribute to the associated option.
    //
    function list_to_options
            ( $list, $select = NULL, $exclude = [] )
    {
	if ( isset ( $select ) )
	    list ( $sroot, $sleaf ) =
	        explode ( ':', $select );
	else
	{
	    $sroot = NULL; $sleaf = NULL;
	}

        $r = '';
	foreach ( $list as $e )
	{
	    list ( $time, $root, $leaf ) = $e;

	    $key = "$root:$leaf";
	    if ( in_array ( $key, $exclude, true ) )
	        continue;

	    $selected = '';
	    if ( $root == $sroot
	         &&
		 $leaf == $sleaf )
	        $selected = 'selected';

	    if ( $root == '-' )
	        $root = 'Your';
	    if ( $leaf == '-' )
	        $leaf = 'Problems';
	    $time = substr ( $time, 0, 10 );
	    $r .= "<option value='$key' $selected>"
	        . "$root $leaf $time"
		. "</option>";
	}
	return $r;
    }

?>
