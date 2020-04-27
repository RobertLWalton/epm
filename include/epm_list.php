<?php

    // File:	epm_list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Apr 27 02:23:38 EDT 2020

    // Functions for managing lists.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );
    if ( ! isset ( $uid ) )
	exit ( 'ACCESS ERROR: $uid not set' );

    // Permission maps.  These map:
    //
    //		permission => {true,false}
    //
    // according to whether or not the current $uid is
    // granted or not granted the permission.
    //
    $all_permissions =
        [ 'owner'  => true, 'push'  => true,
	  'pull'   => true, 'index' => true,
	  'review' => true ];
    $no_permissions =
        [ 'owner'  => false, 'push'  => false,
	  'pull'   => false, 'index' => false,
	  'review' => false ];

    // Add permissions from $pfile into permission map
    // $pmap.  Erroneous lines in the file generate
    // WARN messages and are ignored.  $pfile is a file
    // name relative to $epm_data.  If $pfile is not
    // readable, $pmap is not changed (and it is NOT an
    // error).  If a permission TYPE is not set in the
    // initial $pmap, it is not legal.
    //
    function add_permissions ( & $pmap, $pfile )
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
		   . " '$line' in $f";
	    elseif ( preg_match ( '#/#', $line ) )
	        $m = "permission '$line' in $pfile has"
		   . " illegal '/'";
	    elseif ( ! isset ( $pmap[$matches[1]] ) )
	        $m = "bad permission type"
		   . " '{$matches[1]}' in $f";
	    else
	    {
	        $r = preg_match
		    ( "/({$matches[2]})/", $uid );
		if ( $r === false )
		    $m = "bad permission regular"
		       . " expression '{$matches[2]}'"
		       . " in $f";
		elseif ( $r )
		    $pmap[$matches[1]] = true;
	    }
	    if ( isset ( $m ) )
		WARN ( $m );
	}
    }

    // Return the permission map for a project.
    //
    function project_permissions ( $project )
    {
        global $all_permissions, $no_permissions;
        $pmap = ['owner' => false];
	add_permissions ( $pmap, 'projects/+perm+' );
	if ( $pmap['owner'] ) return $all_permissions;
	$pmap = $no_permissions;
	add_permissions
	    ( $pmap, "projects/$project/+perm+" );
	return $pmap;
    }

    // Return the permission map for a problem in a
    // project.  If the $uid has owner permission in
    // in projects/$project/$problem/+perm+, then
    // $all_permissions is returned, else the permis-
    // sions of the project is returned.
    //
    function problem_permissions ( $project, $problem )
    {
        global $all_permissions;
        $pmap = ['owner' => false];
	add_permissions
	    ( $pmap,
	      "projects/$project/$problem/+perm+" );
	if ( $pmap['owner'] ) return $all_permissions;
	return project_permissions ( $project );
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

    // Given a list of PROJECTs return a string whose
    // segments have the form
    //
    //	    <option value='PROJECT'>
    //      $project
    //      </option>
    //
    function projects_to_options ( $list )
    {
	$r = '';
	foreach ( $list as $project )
	{
	    $r .= "<option value='$project'>"
		. "$project</option>";
	}
	return $r;
    }

    // Return a map from a user's own problems to the
    // projects each is descended from, or '' if a
    // problem is not descended from a project.  Sort
    // the map by problems (keys) in natural order.
    //
    // If $enabling_map is NOT NULL, any PROBLEM such
    // that $enabling_map['PROBLEM'] is NOT set, or
    // is set to a value that is neither '' not the
    // project PROBLEM is descended from, is ignored.
    //
    function read_problems ( $enabling_map = NULL )
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
	    $eproject = '';
	    if ( isset ( $enabling_map ) )
	    {
	        if ( ! isset
		          ( $enabling_map[$problem] ) )
		    continue;
		$eproject = $enabling_map[$problem];
	    }

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
		if (    $eproject == ''
		     || $eproject == $matches[1] )
		    $pmap[$problem] = $matches[1];
	    }
	    elseif ( $eproject == '' )
		$pmap[$problem] = '';
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
    //		+istack+
    //		+fstack+
    //
    // return the file name of the list relative to
    // $epm_data.  Return NULL if the name is of
    // the form -:- or PROJECT:-.
    //
    function listname_to_filename ( $listname )
    {
        global $uid;

	if ( preg_match ( '/\+.+\+/', $listname ) )
	    return "users/$uid/+indices+/$listname";

        list ( $project, $basename ) =
	    explode ( ':', $listname );
	if ( $basename == '-' ) return NULL;

	if ( $project == '-' )
	    $d = "users/$uid";
	else
	    $d = "projects/$project";
	return "$d/+indices+/{$basename}.index";
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
	$f = "users/$uid/+indices+/$basename.index";
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

	$g = "users/$uid/+indices+/+favorites+";
	$c = @file_get_contents ( "$epm_data/$g", '' );
	if ( $c === false ) $c = '';
	$c = "$time - $basename" . PHP_EOL . $c;
	$r = @file_put_contents ( "$epm_data/$g", $c );
	if ( $r === false )
	    ERROR ( "could not write $g" );
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
	else
	    $bname = preg_replace
		( '/-/', ' ', $basename );

        $f = "users/$uid/+indices+/$basename.index";
	if ( $project == '-' )
	{
	    if ( ! file_exists ( "$epm_data/$f" ) )
	    {
	        $errors[] = "you have no list named"
		          . " $bname";
	        return;
	    }
	}
	else
	{
	    $g = "project/$project/+indices+/"
	       . "$basename.index";
	    if ( ! is_link ( "$epm_data/$g" ) )
	    {
	        $errors[] = "there is no list"
		          . " `$pname $bname'";
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
	        $errors[] = "list `$pname $bname'"
		          . " belongs to {$matches[1]}"
			  . " and not to you";
	        return;
	    }

	    $f = $g;
	}

	if ( ! $execute ) return;

	unlink ( "$epm_data/$f" );

	$f = "users/$uid/+indices+/+favorites+";
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

	foreach ( ['<','>'] as $needle )
	{
	    $r = strpos ( $description, $needle );
	    if ( $r === false ) continue;
	    $ldots = '...';
	    $l = $r - 10;
	    if ( $l < 0 )
	    {
	        $l = 0;
		$ldots = '';
	    }
	    $r += 10;
	    $rdots = '...';
	    if ( $r >= strlen ( $description ) )
	    {
	        $r = strlen ( $description );
		$rdots = '';
	    }
	    $m = $ldots
	       . substr ( $description, $l, $r - $l )
	       . $rdots;

	    $errors[] = "$needle is in description: $m";
	    return;
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
    // PROBLEM's +changes+ file.  List elements
    // are sorted most recent TIME first.
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
	    {
	        WARN ( "cannot stat $f" );
		continue;
	    }
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

    // Given a $listname return the list of elements
    //
    //		[TIME PROJECT PROBLEM]
    //
    // named.  $listname may be one of:
    //
    //     -:-
    //     PROJECT:-
    //     PROJECT:BASENAME
    //	   +istack+
    //	   +fstack+
    //	   +favorites+
    //
    // Note that if $listname ends with '-' the list is
    // read-only.
    //	   
    function listname_to_list ( $listname )
    {
	if ( $listname == '-:-' )
	    return problems_to_edit_list();
	elseif ( preg_match ( '/^(.+):-/',
	                      $listname, $matches ) )
    	    return read_project_list ( $matches[1] );
	else
	    return read_file_list
		( listname_to_filename ( $listname ) );
    }

    // Return the lines from:
    //
    //	    users/UID/+indices+/+favorites+
    //
    // in the form of an ordered map of elements of the
    // form
    //
    //	    PROJECT:BASENAME => TIME
    //
    // For each PROJECT:BASENAME pair, if the pair is
    // not in the +favorites+ file, but is a key in
    // $inmap, then the $inmap element is added to the
    // end of the output map, preserving the order of
    // $inmap.
    //
    // A non-existant +favorites+ file is treated as a
    // file of zero length.  File line formatting errors
    // are fatal.
    //
    // Note that PROBLEM == '-' denotes the list of
    // problems in PROJECT, or if PROJECT is also '-',
    // the list of the UID user's problems is denotes.
    //
    function read_favorites ( $inmap = [] )
    {
        global $epm_data, $uid;

	// First build a map PROJECT:BASENAME => TIME
	// from the +favorites+ file.  Then add to it.
	//
	$outmap = [];
	$f = "users/$uid/+indices+/+favorites+";
	$linemap = [];
	$c = @file_get_contents ( "$epm_data/$f" );
	if ( $c !== false )
	{
	    $c = explode ( "\n", $c );
	    foreach ( $c as $line )
	    {
		$line = trim ( $line );
		if ( $line == '' ) continue;
		$line = preg_replace
		    ( '/\h+/', ' ', $line );
		$items = explode ( ' ', $line );
		if ( count ( $items ) != 3 )
		    ERROR ( "badly formatted line" .
			    " '$line' in $f" );
		list ( $time, $project, $basename ) =
		    $items;
		$key = "$project:$basename";
		if ( isset ( $map[$key] ) )
		    ERROR ( "line '$line' duplicates" .
			    " line '{$linemap[$key]}'" .
			    " in $f" );
		$linemap[$key] = $line;
		$outmap[$key] = $time;
	    }
	}
	foreach ( $inmap as $key => $time )
	{
	    if ( ! isset ( $outmap[$key] ) )
	        $outmap[$key] = $time;
	}

	return $outmap;
    }

    // Given a $type_re to pass to read_projects,
    // build an $inmap containing first the user's
    // own problems and then all the projects returned
    // by read_projects.  Use the current time for
    // $inmap elements.  Then call read_favorites with
    // $inmap to get a map of favorites whose elements
    // have the form:
    //
    //		PROJECT:PROBLEM => TIME
    //
    // From this list return a a string whose segments
    // have the form
    //
    //	    <option value='PROJECT:BASENAME'>
    //      $project $basename $time
    //      </option>
    //
    // where $project is PROJECT unless that is `-', in
    // which case it is `Your', $basename is
    // BASENAME unless that is `-', in which case it is
    // `Problems', and $time is the first 10 characters
    // of TIME (i.e., the day, excluding the time of
    // day).
    //
    // Note that markup such as <i> is NOT recognized in
    // options by HTML, so is not used here.
    //
    function favorites_to_options ( $type_re )
    {
	global $epm_time_format;
	$time = strftime ( $epm_time_format );
	$inmap = [ '-:-' => $time ];
	foreach ( read_projects ( $type_re )
	          as $project )
	{
	    $key = "$project:-";
	    $inmap[$key] = $time;
	}

	$fmap = read_favorites ( $inmap );

	$r = '';
	foreach ( $fmap as $key => $time )
	{
	    list ( $project, $basename ) =
	        explode ( ':', $key );
	    if ( $project == '-' )
		$project = 'Your';
	    if ( $basename == '-' )
		$basename = 'Problems';
	    else
		$basename = preg_replace
		    ( '/-/', ' ', $basename );
	    $time = substr ( $time, 0, 10 );
	    $r .= "<option value='$key'>"
		. "$project $basename $time"
		. "</option>";
	}
	return $r;
    }

?>
