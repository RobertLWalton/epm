<?php

    // File:	epm_list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun 15 12:50:48 EDT 2020

    // Functions for managing lists.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $uid ) )
	exit ( 'ACCESS ERROR: $uid not set' );

    if ( ! is_dir ( "$epm_data/users/$uid/+lists+" ) )
        @mkdir ( "$epm_data/users/$uid/+lists+",
	         0770, true );

    // Permission maps.  These map:
    //
    //		permission => {true,false}
    //
    // according to whether or not the current $uid is
    // granted or not granted the permission.
    //
    define ( "ALL_PERMISSIONS",
             [ 'owner'  => true, 'push'  => true,
	       'pull'   => true, 'list' => true,
	       'review' => true ] );
    define ( "NO_PERMISSIONS",
             [ 'owner'  => false, 'push'  => false,
	       'pull'   => false, 'list' => false,
	       'review' => false ] );

    // Add permissions from $pfile into permission map
    // $pmap.  Erroneous lines in the file generate
    // WARN messages and are ignored.  $pfile is a file
    // name relative to $epm_data.  If $pfile is not
    // readable, $pmap is not changed (and it is NOT an
    // error).  If a permission TYPE is not set in the
    // initial $pmap, it is not legal.
    //
    function add_permissions
	    ( $pfile, & $pmap = NO_PERMISSIONS )
    {
        global $uid, $epm_data;

	$c = @file_get_contents ( "$epm_data/$pfile" );
	if ( $c === false ) return;

	$c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	    // Get rid of `//...' comments.
	$c = explode ( "\n", $c );
	foreach ( $c as $line )
	{
	    $m = NULL;
	    $line = trim ( $line );
	    if ( $line == '' ) continue;
	    if ( ! preg_match
	               ( '/^(\S+)\s+(\S+)$/',
		         $line, $matches ) )
	        $m = "badly formatted permission"
		   . " '$line' in $pfile";
	    elseif ( preg_match ( '#/#', $line ) )
	        $m = "permission '$line' in $pfile has"
		   . " illegal '/'";
	    elseif ( ! isset ( $pmap[$matches[1]] ) )
	        $m = "bad permission type"
		   . " '{$matches[1]}' in $pfile";
	    else
	    {
	        $r = preg_match
		    ( "/^({$matches[2]})\$/", $uid );
		if ( $r === false )
		    $m = "bad permission regular"
		       . " expression '{$matches[2]}'"
		       . " in $pfile";
		elseif ( $r )
		    $pmap[$matches[1]] = true;
	    }
	    if ( isset ( $m ) )
		WARN ( $m );
	}
    }

    // Add project permissions to $pmap and return
    // $pmap.
    //
    function project_permissions
	    ( $project, & $pmap = NO_PERMISSIONS )
    {
	add_permissions
	    ( "projects/$project/+perm+", $pmap );
	if ( $pmap['owner'] ) return ALL_PERMISSIONS;
	add_permissions ( 'projects/+perm+', $pmap );
	if ( $pmap['owner'] ) return ALL_PERMISSIONS;
	return $pmap;
    }

    // Add problem permissions to $pmap and return
    // $pmap.
    //
    function problem_permissions
	    ( $project, $problem,
	      & $pmap = NO_PERMISSIONS )
    {
	add_permissions
	    ( "projects/$project/$problem/+perm+",
	      $pmap );
	if ( $pmap['owner'] ) return ALL_PERMISSIONS;
	return project_permissions
	    ( $project, $pmap );
    }

    // Return the list of projects that have a given
    // type of permission that matches the $type_re
    // regular expression.  The list is sorted in
    // natural order.
    //
    function read_projects ( $type_re )
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
	    $pmap = project_permissions ( $project );
	    foreach ( $pmap as $type => $value )
	    {
	        if ( ! $value ) continue;
		if ( ! preg_match
		         ( "/^($type_re)\$/", $type ) )
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
    function values_to_options ( $list )
    {
	$r = '';
	foreach ( $list as $value )
	{
	    $r .= "<option value='$value'>"
		. "$value</option>";
	}
	return $r;
    }

    // Return a map from a user's own problems to the
    // projects each is descended from, or '-' if a
    // problem is not descended from a project.  Sort
    // the map by problems (keys) in natural order.
    //
    function read_problems()
    {
	global $epm_data, $uid, $epm_name_re;

	$pmap = [];
	$f = "users/$uid";
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

    // Given a list name of one of the forms:
    //
    //		-:-
    //		PROJECT:-
    //		PROJECT:BASENAME
    //		+favorites+
    //
    // return the file name of the list relative to
    // $epm_data, or return NULL if the name is of
    // the form -:- or PROJECT:-.
    //
    function listname_to_filename ( $listname )
    {
        global $uid;

	if ( preg_match ( '/\+.+\+/', $listname ) )
	    return "users/$uid/+lists+/$listname";

        list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $basename == '-' ) return NULL;

	if ( $project == '-' )
	    $d = "users/$uid";
	else
	    $d = "projects/$project";
	return "$d/+lists+/{$basename}.list";
    }

    // Given a basename make a new empty file for the
    // listname '-:basename' and add its name to the
    // beginning of +favorites+ using the current
    // time as the TIME value.  If there are errors
    // append to $errors.
    //
    function make_new_list ( $basename, & $errors )
    {
        global $epm_data, $uid, $epm_name_re,
	       $epm_time_format;

	if ( ! preg_match ( $epm_name_re, $basename ) )
	{
	   $errors[] = "$basename is badly formed"
	             . " list name";
	   return;
	}
	$f = "users/$uid/+lists+/$basename.list";
	if ( file_exists ( "$epm_data/$f" ) )
	{
	   $errors[] = "the $basename list already"
	             . " exists";
	   return;
	}

	$r = @file_put_contents ( "$epm_data/$f", '' );
	if ( $r === false )
	    ERROR ( "could not write $f" );
	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "could not stat $f" );
	$time = strftime ( $epm_time_format, $time );

	$f = "users/$uid/+lists+/+favorites+";
	$flist = read_file_list ( $f );
	array_unshift
	    ( $flist, [$time, '-', $basename] );
	write_file_list ( $f, $flist );
	    // We need to remove any previous
	    // -:$basename in case list was deleted
	    // and then re-created.
    }

    // Delete the named list, or append to $errors.
    // However if $execute is false, just check for
    // errors and return.
    //
    function delete_list
            ( $listname, & $errors, $execute )
    {
        global $epm_data, $uid;

        list ( $project, $basename ) =
	    explode ( ':', $listname );
	$pname = ( $project == '-' ?
	           'Your' : $project );
	if ( $basename == '-' )
	{
	    $errors[] = "cannot delete $pname Problems";
	    return;
	}

        $f = "users/$uid/+lists+/$basename.list";
	if ( $project == '-' )
	{
	    if ( ! file_exists ( "$epm_data/$f" ) )
	    {
	        $errors[] = "you have no list named"
		          . " $basename";
	        return;
	    }
	}
	else
	{
	    $g = "projects/$project/+lists+/"
	       . "$basename.list";
	    if ( ! is_link ( "$epm_data/$g" ) )
	    {
	        $errors[] = "there is no list"
		          . " `$pname $basename'";
	        return;
	    }
	    $n = @readlink ( "$epm_data/$g" );
	    if ( $n === false )
	        ERROR ( "cannot read link $g" );
	    $re = '#^\.\./\.\./\.\./users/([^/]+)/#';
	    if ( ! preg_match ( $re, $n, $matches ) )
	        ERROR ( "$n read from link $g is" .
		        " badly formed" );
	    if ( $uid != $matches[1] )
	    {
	        $errors[] = "list `$pname $basename'"
		          . " belongs to {$matches[1]}"
			  . " and not to you";
	        return;
	    }

	    $f = $g;
	}

	if ( ! $execute ) return;

	unlink ( "$epm_data/$f" );

	$f = "users/$uid/+lists+/+favorites+";
	delete_from_file_list
	    ( $f, $project, $basename );
    }

    // Return the lines from the list with the given
    // $filename in the form of a list of elements each
    // of the form
    //
    //	    [TIME PROJECT PROBLEM]
    //
    // where PROJECT may be `-'.  Reading stops with the
    // first blank line.  If the file does not exist, []
    // is returned.  Line formatting errors are fatal.
    // Lines with duplicate PROJECT:PROBLEM are fatal.
    //
    function read_file_list ( $filename )
    {
        global $epm_data;
	$list = [];
	$map = [];
	$c = @file_get_contents
	    ( "$epm_data/$filename" );
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
		list ( $time, $project, $problem ) =
		    $items;
		$key = "$project:$problem";
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
    //	    [TIME PROJECT NAME]
    //
    // to the named file, preserving any part of the
    // file that is after its first blank line.  Each
    // element becomes one line consisting of the
    // element members separated by 2 single spaces.
    //
    // If a PROJECT:NAME occurs several times, only the
    // first is kept, but the output TIME is the latest
    // of the associated TIMEs.
    //
    function write_file_list ( $filename, $list )
    {
        global $epm_data;
	$keys = [];
	$map = [];
	foreach ( $list as $items )
	{
	    list ( $time, $project, $name ) = $items;
	    $key = "$project:$name";
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
	    list ( $project, $name ) =
	        explode ( ':', $key );
	    $lines[] = "{$map[$key]} $project $name";
	}

	$c = @file_get_contents
	    ( "$epm_data/$filename" );
	if ( $c !== false )
	{
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
		        continue;

		    if ( $last_blank )
		    {
		        $lines[] = '';
			$last_blank = false;
		    }
		    $lines[] = $fline;
		}
	    }
	}

	$c = '';
	foreach ( $lines as $line )
	    $c .= $line . PHP_EOL;
	$r = @file_put_contents
	         ( "$epm_data/$filename", $c );
	if ( $r === false )
	    ERROR ( "cannot write $filename" );
    }

    // Delete all lines `TIME $project $basename' from
    // list with given $filename.
    //
    function delete_from_file_list
	    ( $filename, $project, $basename )
    {
	$list = read_file_list ( $filename );
	$changed = false;
	$out = [];
	foreach ( $list as $e )
	{
	    if (    $e[1] == $project
	         && $e[2] == $basename )
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

	$c = @file_get_contents
	    ( "$epm_data/$filename" );
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

	$r = @file_put_contents
	         ( "$epm_data/$filename", $r );
	if ( $r === false )
	    ERROR ( "cannot write $filename" );
    }

    // Read list description and return it as as HTML.
    // Returns '' if file does not exist.
    //
    function read_list_description ( $filename )
    {
        global $epm_data;

	$c = @file_get_contents
	    ( "$epm_data/$filename" );
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
    // for all PROBLEMs users/UID/PROBLEM where TIME
    // is the modification time of the problem
    // directory.  Sort by TIME.
    //
    function read_your_list()
    {
	global $epm_data, $uid, $epm_name_re,
	       $epm_time_format;

	// First build map from PROBLEM to TIME
	// and sort on TIME.
	//
	$map = [];
	$f = "users/$uid";
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

    // Given a $listname return the list of elements
    //
    //		[TIME PROJECT PROBLEM]
    //
    // named.  $listname may be one of:
    //
    //     -:-
    //     PROJECT:-
    //     PROJECT:BASENAME
    //	   +favorites+
    //
    // Note that if $listname ends with '-' the list is
    // read-only.
    //	   
    function listname_to_list ( $listname )
    {
	if ( $listname == '-:-' )
	    return read_your_list();
	elseif ( preg_match ( '/^(.+):-$/',
	                      $listname, $matches ) )
    	    return read_project_list ( $matches[1] );
	else
	    return read_file_list
		( listname_to_filename ( $listname ) );
    }

    // Returns favorites list as per
    //
    //    listname_to_list ( '+favorites+' )
    //
    // unless that list is empty, in which case
    // constructs a list consisting of users problems
    // and problems of all projects for which user
    // has a permission of a type matching $type_re,
    // and writes that into +favorites+ before
    // returning the constructed list.
    //
    function favorites_to_list ( $type_re )
    {
	global $epm_data, $uid, $epm_time_format;

        $list = listname_to_list ( '+favorites+' );
	if ( count ( $list ) > 0 ) return $list;
	$f = "users/$uid";
	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "cannot stat $f" );
	$time = strftime ( $epm_time_format, $time );
	$list[] = [$time, '-', '-'];
	foreach ( read_projects ( $type_re )
	          as $project )
	{
	    $f = "projects/$project";
	    $time = @filemtime ( "$epm_data/$f" );
	    if ( $time === false )
	        ERROR ( "cannot stat $f" );
	    $time = strftime
	        ( $epm_time_format, $time );
	    $list[] = [$time, $project, '-'];
	}
	$f = "users/$uid/+lists+/+favorites+";
	write_file_list ( $f, $list );
	return $list;
    }

    // If $name is in list, return its $list entry,
    // else return NULL.  $name has the form
    // PROJECT:PROBLEM (or OWNER:BASENAME).  List
    // entries have the form [TIME, PROJECT, PROBLEM].
    //
    function in_list ( $name, $list )
    {
        list ( $project, $problem ) =
            explode ( ':', $name );
	foreach ( $list as $item )
	{
	    list ( $time, $lproject, $lproblem )
	        = $item;
	    if ( $project == $lproject
	         &&
		 $problem == $lproblem )
	        return $item;
	}
	return NULL;
    }

    // Given a list of elements of the form
    //
    //		[TIME PROJECT NAME]
    //
    // return a string whose segments have the form
    //
    //	    <option value='PROJECT:NAME'>
    //      $project $name $time
    //      </option>
    //
    // where $project is PROJECT unless that is `-', in
    // which case it is `Your', $name is NAME unless
    // that is `-', in which case it is `Problems', and
    // $time is the first 10 characters of TIME (i.e.,
    // the day, excluding the time of day).
    //
    // However, if 'PROJECT:NAME' is listed in $exclude,
    // omit that option.  Also if $select is not NULL
    // but is 'PROJECT:NAME', add the `selected'
    // attribute to the associated option.
    //
    function list_to_options
            ( $list, $select = NULL, & $exclude = [] )
    {
	if ( isset ( $select ) )
	    list ( $sproject, $sname ) =
	        explode ( ':', $select );
	else
	{
	    $sproject = NULL; $sname = NULL;
	}

        $r = '';
	foreach ( $list as $e )
	{
	    list ( $time, $project, $name ) = $e;
	    $selected = '';
	    if ( $project == $sproject
	         &&
		 $name == $sname )
	        $selected = 'selected';
	    $key = "$project:$name";
	    if ( in_array ( $key, $exclude, true ) )
	        continue;

	    if ( $project == '-' )
	        $project = 'Your';
	    if ( $name == '-' )
	        $name = 'Problems';
	    $time = substr ( $time, 0, 10 );
	    $r .= "<option value='$key' $selected>"
	        . "$project $name $time"
		. "</option>";
	}
	return $r;
    }

?>
