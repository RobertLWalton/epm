<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Jan 29 08:35:17 EST 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Maintains problem lists.

    // See project.php for directory and files.

    // Session Data
    // ------- ----

    // Session data is in $data as follows:
    //
    //	   $data NAMES
    //		[name1,name2] where nameJ is in the
    //		format PROJECT:BASENAME or is ''
    //		for no-list.
    //
    //	   $data WRITABLE
    //		[writable1,writable2] where writableJ
    //		is true iff nameJ was writable when
    //		presented to user.  This means that
    //		nameJ had the form -:NAME and that $rw
    //		was true on the last transaction of this
    //		page.
    //
    //     $data ELEMENTS
    //		A list of the elements of the form
    //
    //			[TIME PROJECT PROBLEM]
    //
    //		that are displayed as list rows.  The
    //		The 'op' POST contains indices of these
    //		elements for the edited versions of each
    //		list.  The elements for both lists are
    //		included here in arbitrary order.
    //
    //	   $data PUB_J
    //		If set, the list number (0 or 1) of the
    //		list for which the publishing or un-
    //		publishing question is being asked.  If
    //		NOT set, these questions are not being
    //		asked.
    //
    //	   $data PUB_PROJECT
    //		If PUB_J is set and this is set, this is
    //		the project begin published to.
    //		If PUB_J is set and this is NOT set,
    //		list J is being unpublished.

    // POST:
    // ----
    //
    // Each post may update one list and has the
    // following values.
    //
    //	    indices='index0;index1'
    //		Here indexJ is the indices in $data
    //		ELEMENTS of list J, with the indices
    //		separated by `:', and '' denoting the
    //		empty list.
    //
    //	    lengths='length0;length1'
    //		The first lengthJ elements of list J are
    //		marked, and the rest are NOT marked.
    //
    //	    edited='edited0;edited1'
    //		Where editedJ is 'yes' if the list has
    //		been altered since it was last loaded
    //		from a file, and 'no' otherwise.
    //
    //	    list=J
    //		List number (J = 0 or 1) affected by
    //		operation.
    //
    //	    name=NAME
    //		List name for `select' operation below,
    //		or basename for `new' operation
    //		or project name for `publish' operation
    //		or question answer for execute-publish
    //		or execute-unpublish operations.
    //
    //	    op=OPERATION
    //		OPERATION is one of:
    //
    //	     *	save	Save used portion of list J in
    //			its file.
    //
    //	     *	finish	Ditto and set nameJ = '',
    //			indicating there is no longer
    //			any list J.
    //
    //	     *	reset	Restore list J from its file.
    //
    //	     *	cancel	Set nameJ = '' indicating there
    //			is no longer any list J.
    //
    //	     *	delete	Delete list J file and set
    //			nameJ = '' indicating there is
    //			no longer any list J.
    //
    //	    *	dsc	Upload description file for list
    //		        J.  Basename of file must match
    //			basename of nameJ.
    //
    //	     *	publish	Copy or move list TO project.
    //
    //	     *	execute-publish	Execution part of
    //				publish.
    //
    //	    **	select	Set nameJ = NAME and load list J
    //			from file designated by NAME.
    //
    //	    **	copy	Create a new list J with given
    //			-:NAME and load list J with a
    //			copy of list 1-J or empty list
    //			if list 1-J does not exist.
    //
    //	   ** unpublish	Copy or move list FROM project.
    //
    //	   ** execute-publish	Execution part of
    //				unpublish.
    //
    //  * Should be sent ONLY if list J is writable.
    // ** Should be sent ONLY if list J is read-only.

    $epm_page_type = '+main+';
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
    {
	$data['NAMES'] = ['',''];
	$data['WRITABLE'] = [false,false];
	$data['ELEMENTS'] = [];
	$data['PUB_J'] = NULL;
	$data['PUB_PROJECT'] = NULL;
    }

    $names = & $data['NAMES'];
    $writable = & $data['WRITABLE'];
    $elements = & $data['ELEMENTS'];
    $pub_J = & $data['PUB_J'];
    $pub_project = & $data['PUB_PROJECT'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.
    $action = NULL;  // Action to be recorded, if any.
    $action_project = NULL;
        // If set, project whose +actions+ are to be
	// updated by $action.
    $own_re = "/^.*--$aid\$/";
        // Matches list name iff that can be published
	// as an `own' list.

    // None of the following are meaningful if $names[J]
    // is ''.
    //
    $lists = [NULL,NULL];
    $lengths = [0,0];
        // Lists to be given to list_to_edit_rows.
	// $lengths[J] is the number of marked
	// elements of $list[J].
	//
	// If $lists[J] set to NULL by POST, it and
	// $lengths[J] will be set according to the
	// file named by $names[J] if that is not ''.
    $edited = ['no','no'];
        // Set to 'yes' when list is altered (edited)
	// and 'no' when list loaded from file.

    $favorites = read_favorites_list ( $warnings );
    // Build $fmap so that $fmap["PROJECT:BASENAME"]
    // exists iff [TIME PROJECT BASENAME] is in
    // $favorites.
    $fmap = [];
    foreach ( $favorites as $e )
    {
        list ( $time, $project, $basename ) = $e;
	$fmap["$project:$basename"] = true;
    }

    // Given indexJ return the list of elements it
    // designates.  Errors are UNACCEPTABLE POST.
    //
    function index_to_list ( $index )
    {
        global $elements;

	$list = [];
	if ( $index == '' ) return $list;

        $indices = explode ( ':', $index );
	$limit = count ( $elements );
	foreach ( $indices as $I )
	{
	    if ( ! preg_match ( '/^\d+$/', $I ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " index $I" );
	    if ( $I >= $limit )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " index $I >= $limit" );
	    $list[] = $elements[$I];
	}
	return $list;
    }

    // Given a list of elements of the form
    //
    //		[TIME PROJECT PROBLEM]
    //
    // where PROJECT may be '-', add the elements to the
    // $elements list and return a string whose segments
    // are HTML tables of the form:
    //
    //		<table id='I' class='problem'
    //                 draggable='true'
    //		       ondragover='ALLOWDROP(event)'
    //		       ondrop='DROP(event)'
    //                 ondragstart='DRAGSTART(event)'
    //                 ondragend='DRAGEND(event)'>
    //		<tr>
    //		<td style='width:10%;text-align:left'>
    //		<div class='checkbox'
    //		     onclick='CHECK(event)'>
    //		</div></td>
    //		<td style='width:80%;text-align:center'>
    //		$project $problem $time</td>
    //		</tr></table>
    //
    // Here $project is PROJECT unless that is '-', in
    // which case $project is '<i>Your</i>'.  $time is
    // the first 10 characters of TIME (just the day
    // part).  I is the index of the element in the
    // $elements list.
    //
    // Note that the browser may move or duplicate table
    // elements, so for example, the indices in a POST
    // may contain duplicates.
    //
    function list_to_edit_rows ( & $elements, $list )
    {
	$r = '';
	foreach ( $list as $element )
	{
	    $I = count ( $elements );
	    $elements[] = $element;
	    list ( $time, $project, $problem ) =
	        $element;
	    if ( $project == '-' )
		$project = '<i>Your</i>';
	    $time = substr ( $time, 0, 10 );
	    $r .= <<<EOT
    	    <table id='$I'
	           class='problem'
    	           draggable='true'
    	           ondragover='ALLOWDROP(event)'
    	           ondrop='DROP(event)'
    	           ondragstart='DRAGSTART(event)'
    	           ondragend='DRAGEND(event)'>
    	    <tr>
    	    <td style='width:10%;text-align:left'>
    	    <div class='checkbox'
    	         onclick='CHECK(event)'>
	    </div></td>
    	    <td style='width:80%;text-align:center'>
    	    $project $problem $time</td>
    	    </tr></table>
EOT;
	}
	return $r;
    }

    // Write uploaded description.  Takes global $_FILES
    // value as input, extracts description, and writes
    // into list file.  Errors append to $errors and
    // suppress write.  If list does not exist, make a
    // new list and add a warning message to $warnings.
    // It is an error if the file last name component
    // is not BASENAME.dsc, where $name is -:BASENAME.
    //
    // If successful, this function returns the listname
    // of the list given the description, in the form
    // '-:basename'.  If unsuccessful, false is
    // returned.
    //
    function upload_list_description
	    ( $name, & $warnings, & $errors )
    {
        global $epm_data, $aid, $epm_name_re,
	       $epm_upload_maxsize;

        if ( ! isset ( $_FILES['uploaded_file'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	$upload = & $_FILES['uploaded_file'];
	$fname = $upload['name'];
	$errors_size = count ( $errors );

	$ferror = $upload['error'];
	if ( $ferror != 0 )
	{
	    switch ( $ferror )
	    {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
		    $errors[] = "$fname too large";
		    break;
		case UPLOAD_ERR_NO_FILE:
		    $errors[] = "no file choosen;"
			      . " try again";
		    break;
		case UPLOAD_ERR_PARTIAL:
		    $errors[] = "$fname upload failed;"
			      . " try again";
		    break;
		default:
		    $e = "uploading $fname, PHP upload"
		       . " error code $ferror";
		    WARN ( $e );
		    $errors[] = "EPM SYSTEM ERROR: $e";
	    }
	    return false;
	}

	$fext = pathinfo ( $fname, PATHINFO_EXTENSION );
	$fbase = pathinfo ( $fname, PATHINFO_FILENAME );

	list ( $project, $basename ) =
	    explode ( ':', $name );
	if ( "$fbase.$fext" != "$basename.dsc" )
	{
	    $errors[] = "$fbase.$fext is not"
		      . " $basename.dsc";
	    return false;
	}

	$fsize = $upload['size'];
	if ( $fsize > $epm_upload_maxsize )
	{
	    $errors[] =
		"uploaded file $fname too large;" .
		" limit is $epm_upload_maxsize";
	    return false;
	}

	$ftmp_name = $upload['tmp_name'];
	$dsc = @file_get_contents ( $ftmp_name );
	if ( $dsc === false )
	{
	    $m = "cannot read uploaded file"
	       . " from temporary";
	    $errors[] = "$m; try again";
	    WARN ( "$m $ftmp_name" );
	    return false;
	}
	$f = "accounts/$aid/+lists+/$fbase.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    make_new_list ( $fbase, $errors );
	        // This will check that $fname is
		// well formed EPM file name base.
	    if ( count ( $errors ) > $errors_size )
	        return false;
	    $warnings[] = "created list $fbase which"
	                . " did not previously exist";
	}

	write_list_description ( $f, $dsc, $errors );
	if ( count ( $errors ) > $errors_size )
	    return false;
	else
	    return ( "-:$fbase" );
    }

    // Copy $list into a new list with the given
    // $basename.  If $list is NULL, make a new empty
    // list.
    //
    // Returns mtime of new list if no errors and false
    // otherwise.
    //
    function copy_list
	    ( $list, $basename, & $warnings, & $errors )
    {
        global $epm_data, $aid, $epm_time_format;

	$f = "accounts/$aid/+lists+/$basename.list";
	if ( file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "Your $basename list file" .
			" already exists";
	    return false;
	}
	make_new_list ( $basename, $errors );
	if ( count ( $errors ) > 0 )
	    return false;

	if ( $list != NULL )
	    write_file_list ( $f, $list );

	$time = @filemtime ( "$epm_data/$f" );
	if ( $time === false )
	    ERROR ( "cannot stat $f" );

	$warnings[] = "<i>Your</i> $basename" .
	              " list has been created";
	return strftime ( $epm_time_format, $time );
    }

    // Execute $subop (keep, delete, cancel) for
    // the execute-publish POST.
    //
    // $list is the list of -:basename entries.  This
    // is checked for local lists and an error is
    // announced for each that is found.
    //
    // If no errors, copies -:basename list file to
    // $project:$basename list file, writing project
    // list file atomically, and then if $subop is
    // delete, unlinks the -:basename list file.
    //
    // But if the only error was failure to unlink the
    // local list, change $subop to keep.
    //
    // Returns mtime of list written to $project, or
    // false if no list was written (including the cases
    // of subop equal to `cancel' and there being errors
    // other than in unlink).
    //
    function execute_publish
	    ( $list, $basename, $project, & $subop,
	      & $warnings, & $errors )
    {
        global $epm_data, $aid, $own_re,
	       $epm_time_format;

	if ( $subop == 'cancel' ) return false;

	foreach ( $list as $e )
	{
	    list ( $time, $proj, $name )
		= $e;
	    if ( $proj == '-' )
	    {
		if ( count ( $errors ) == 0 )
		{
		    $errors[] =
			"A published list cannot" .
			" have local list entries.";
		    $errors[] =
			"The following entries" .
			" are local lists:";
		}
		$errors[] =
		    "    <i>Your</i> $name";
	    }
	}
	if ( count ( $errors ) > 0 ) return false;

	$p = "projects/$project";
	if ( ! is_dir ( "$epm_data/$p" ) )
	{
	    $errors[] = "project $project" .
			" no longer exists";
	    return false;
	}
	$privs = ['publish-all'];
	if ( preg_match ( $own_re, $basename ) )
	    $privs[] = 'publish-own';
	$projects = read_projects ( $privs );
	if ( ! in_array ( $project, $projects ) )
	{
	    $errors[] =
		"You no longer have" .
		" publication privileges" .
		" for project $project";
	    return false;
	}
	$f = "accounts/$aid/+lists+/$basename.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "your $basename list file" .
			" no longer exists";
	    return false;
	}

	if ( ! is_dir ( "$epm_data/$p/+lists+" ) )
	    @mkdir ( "$epm_data/$p/+lists+", 02770 );

	$contents =
	    @file_get_contents ( "$epm_data/$f" );
	if ( $contents === false )
	{
	    $errors[] = "cannot read $f";
	    return false;
	}

	$g = "$p/+lists+/$basename.list";
	$r = ATOMIC_WRITE ( "$epm_data/$g", $contents );
	if ( $r === false )
	{
	    $errors[] = "cannot write $g";
	    return false;
	}
	if ( $subop == 'delete' )
	{
	    $r = unlink ( "$epm_data/$f" );
	    if ( $r === false )
	    {
		$subop = 'keep';
		$errors[] = "failed to delete" .
		            " <i>Your</i> $basename" .
			    " list";
		$errors[] = "copy to $project" .
		            " $basename list" .
			    " successful";
	    }
	}

	$time = @filemtime ( "$epm_data/$g" );
	if ( $time === false )
	    ERROR ( "cannot stat $g" );
	return strftime ( $epm_time_format, $time );
    }

    // Execute $subop (keep, delete, cancel) for
    // the execute-unpublish POST.
    //
    // If no errors, copies project:basename list file
    // to -:$basename list file, reading project list
    // file atomically, and then if $subop is delete,
    // unlinks the project list file.
    //
    // But if the only error was failure to unlink
    // project list, change $subop to keep.
    //
    // Returns mtime of written local list, or false
    // if there no list was written (including the
    // cases of subop equal to `cancel' and there being
    // an error other than unlink).
    //
    function execute_unpublish
	    ( $basename, $project, & $subop,
	      & $warnings, & $errors )
    {
        global $epm_data, $aid, $own_re,
	       $epm_time_format;

	if ( $subop == 'cancel' ) return false;

	$p = "projects/$project";
	if ( ! is_dir ( "$epm_data/$p" ) )
	{
	    $errors[] = "project $project" .
			" no longer exists";
	    return false;
	}
	$privs = ['unpublish-all'];
	if ( preg_match ( $own_re, $basename ) )
	    $privs[] = 'unpublish-own';
	$projects = read_projects ( $privs );
	if ( ! in_array ( $project, $projects ) )
	{
	    $errors[] =
		"You no longer have" .
		" unpublication privileges" .
		" for project $project";
	    return false;
	}
	$f = "$p/+lists+/$basename.list";
	if ( ! file_exists ( "$epm_data/$f" ) )
	{
	    $errors[] = "$project $basename list file" .
			" no longer exists";
	    return false;
	}

	$contents = ATOMIC_READ ( "$epm_data/$f" );
	if ( $contents === false )
	{
	    $errors[] = "cannot read $f";
	    return false;
	}

	$g = "accounts/$aid/+lists+/$basename.list";
	if ( ! file_exists ( "$epm_data/$g" ) )
	{
	    make_new_list ( $basename, $errors );
	    if ( count ( $errors ) > 0 )
	        return false;
	}
	$r = @file_put_contents
		( "$epm_data/$g", $contents );

	if ( $r === false )
	{
	    $errors[] = "cannot write $g";
	    return false;
	}
	if ( $subop == 'delete' )
	{
	    $r = unlink ( "$epm_data/$f" );
	    if ( $r === false )
	    {
		$subop = 'keep';
		$errors[] = "failed to delete" .
		            " $project $basename list";
		$errors[] = "copy to <i>Your</i>" .
		            " $basename list" .
			    " successful";
	    }
	}

	$time = @filemtime ( "$epm_data/$g" );
	if ( $time === false )
	    ERROR ( "cannot stat $g" );
	return strftime ( $epm_time_format, $time );
    }

    if ( $epm_method != 'POST' )
        /* Do Nothing */;
    elseif ( isset ( $_POST['rw'] ) )
    {
        if ( $writable[0] || $writable[1] )
	    exit ( 'UNACCEPTABLE HTTP POST: rw' );
	require "$epm_home/include/epm_rw.php";
    }
    elseif ( ! isset ( $_POST['op'] ) )
	exit ( 'UNACCEPTABLE HTTP POST: no op' );
    elseif ( ! isset ( $_POST['indices'] ) )
	exit ( 'UNACCEPTABLE HTTP POST: no indices' );
    elseif ( ! isset ( $_POST['lengths'] ) )
	exit ( 'UNACCEPTABLE HTTP POST: no lengths' );
    elseif ( ! isset ( $_POST['edited'] ) )
	exit ( 'UNACCEPTABLE HTTP POST: no edited' );
    elseif ( ! isset ( $_POST['list'] ) )
	exit ( 'UNACCEPTABLE HTTP POST: no list' );
    else
    {
	$op = $_POST['op'];
	if ( ! in_array ( $op, ['save','finish','reset',
	                        'cancel','delete',
				'select','new',
				'dsc', 'publish',
				'unpublish',
				'execute-publish',
				'execute-unpublish'] ) )
	    exit ( "UNACCEPTABLE HTTP POST: $op" );
	$J = $_POST['list'];
	if ( ! in_array ( $J, [0,1] ) )
	    exit ( "UNACCEPTABLE HTTP POST: J $J" );

	$indices = explode ( ';', $_POST['indices'] );
	$lengths = explode ( ';', $_POST['lengths'] );
	$edited = explode ( ';', $_POST['edited'] );

	foreach ( [0,1] as $K )
	{
	    $lists[$K] = index_to_list ( $indices[$K] );
	    if ( ! preg_match ( '/^\d+$/',
	                        $lengths[$K] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " lengths[$K] = $lengths[$K]" );
	    if ( $lengths[$K] > count ( $lists[$K] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " lengths[$K] > count" );
	    if ( ! in_array ( $edited[$K],
	                      ['yes','no'] ) )
		exit ( "UNACCEPTABLE HTTP POST:" .
		       " edited[$K] = $edited[$K]" );
	}

	if ( $op == 'select' )
	{
	    if ( $writable[$J] )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' select writable' );

	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' select no name' );
	    $name = $_POST['name'];
	    if ( $name == '' )
	    	$names[$J] = '';
	    elseif ( ! isset ( $fmap[$name] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " select no fmap[$name]" );
	    elseif ( $name == $names[1 - $J] )
	        $errors[] = "cannot select list because"
		          . " then both lists would be"
			  . " the same";
	    else
	    {
	    	$names[$J] = $name;
		$lists[$J] = NULL;
	    }
	}
	elseif ( ! $rw && $_POST['op'] != 'select' )
	{
	    $errors[] = 'you are no longer in'
		      . ' read-write mode';
	}
	elseif ( $op == 'new' )
	{
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' new no name' );
	    $name = $_POST['name'];
	    if ( $names[$J] == '' )
	        $list = NULL;
	    else
		$list = array_slice
		    ( $lists[$J], 0, $lengths[$J] );
	    $time = copy_list
	        ( $list, $name, $warnings, $errors );
	    if ( $time !== false )
	    {
	        if ( $list == NULL )
		    $action = "$time $aid"
			    . " create-list"
			    . " - $name"
			    . PHP_EOL;
		else
		{
		    list ( $p, $b ) =
		        explode ( ':', $names[$J] );
		    $action = "$time $aid"
			    . " copy-list"
			    . " $p $b - $name"
			    . PHP_EOL;
		}
		$favorites = read_favorites_list
		    ( $warnings );
	    }
	}
	elseif ( $op == 'unpublish' )
	{
	    if ( $writable[$J] )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' unpublish writable' );
	    $pub_J = $J;
	    $pub_project = NULL;
	}
	elseif ( $op == 'execute-unpublish' )
	{
	    if ( ! isset ( $pub_J ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-unpublish no pub_J" );
	    if ( $J != $pub_J )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-unpublish J !=" .
		       " pub_J" );
	    elseif ( $writable[$pub_J] )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' publish writable' );
	    if ( isset ( $pub_project ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-unpublish" .
		       " set pub_project" );
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' execute-unpublish no' .
		       ' subop (name)' );
	    $subop = $_POST['name'];
	    if ( ! in_array ( $subop, ['keep', 'delete',
	                               'cancel'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-unpublish" .
		       " subop $subop" );
	    list ( $project, $basename ) =
	        explode ( ':', $names[$pub_J] );
	    $time = execute_unpublish
	        ( $basename, $project, $subop,
		  $warnings, $errors );
	    if ( $time !== false )
	    {
		if ( $subop == 'delete' )
		{
		    $names[$pub_J] = '';
		    $favorites = read_favorites_list
			( $warnings );
		    $action = "move";
		}
		else
		    $action = "copy";

		$action = "$time $aid"
			. " unpublish-$action"
			. " $project $basename"
			. PHP_EOL;
		$action_project = $project;

	    	$names[$J] = "-:$basename";
		$lists[$J] = NULL;
		// No need to update $favorites as
		// new list is excluded from
		// selectors.
	    }

	    $pub_J = NULL;
	}
	elseif ( ! $writable[$J] )
	    exit ( 'UNACCEPTABLE HTTP POST:' .
	           ' not writable' );
	elseif ( in_array ( $op,
	                    ['save','finish','dsc'] ) )
	{
	    $name = substr ( $names[$J], 2 );
	    if ( $op == 'dsc' )
	    {
		if ( upload_list_description
		         ( $names[$J],
			   $warnings, $errors ) )
		    $action = 'update-list';
	    }

	    if ( count ( $errors ) == 0 )
	    {
		$r = write_file_list
		    ( listname_to_filename
		          ( $names[$J] ),
		      array_slice
			  ( $lists[$J], 0,
			    $lengths[$J] ) );

		if ( $r )
		{
		    if ( $op == 'dsc' )
			$warnings[] =
			    "updated list $name has" .
			    " been saved";
		    $action = 'update-list';
		    $lists[$J] = NULL;
		}
		if ( $op == 'finish' )
		    $names[$J] = '';
		$edited[$J] = 'no';
		if ( isset ( $action ) )
		{
		    // $action == 'update-list'
		    //
		    $d = "accounts/$aid/+lists+";
		    $f = "$d/$name.list";

		    $time = @filemtime
			( "$epm_data/$f" );
		    if ( $time === false )
			ERROR ( "cannot stat $f" );
		    $time = strftime
			( $epm_time_format, $time );
		    $action = "$time $aid"
			    . " $action"
			    . " - $name"
			    . PHP_EOL;
		}
	    }
	}
	elseif ( $op == 'reset' )
	    $lists[$J] = NULL;
	elseif ( $op == 'cancel' )
	    $names[$J] = '';
	elseif ( $op == 'delete' )
	{
	    $name = substr ( $names[$J], 2 );
	    delete_list ( $name, $errors, true );
	    if ( count ( $errors ) == 0 )
	    {
		$time = strftime ( $epm_time_format );
		$action = "$time $aid"
			. " delete-list"
			. " - $name"
			. PHP_EOL;

		$names[$J] = '';
		$favorites = read_favorites_list
		    ( $warnings );
	    }
	}
	elseif ( $op == 'publish' )
	{
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' publish no project name' );
	    $pub_project = $_POST['name'];
	    $pub_J = $J;
	}
	elseif ( $op == 'execute-publish' )
	{
	    if ( ! isset ( $pub_J ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-publish no pub_J" );
	    if ( $J != $pub_J )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-publish J != pub_J" );
	    if ( ! isset ( $pub_project ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-publish" .
		       " no pub_project" );
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       ' execute-publish no' .
		       ' subop (name)' );
	    if ( $edited[$pub_J] == 'yes' )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-publish" .
		       " edited" );
	    $subop = $_POST['name'];
	    if ( ! in_array ( $subop, ['keep', 'delete',
	                               'cancel'] ) )
		exit ( 'UNACCEPTABLE HTTP POST:' .
		       " execute-publish" .
		       " subop $subop" );

	    $basename = substr ( $names[$pub_J], 2 );
	    $time = execute_publish
	        ( $lists[$pub_J], $basename,
		  $pub_project, $subop,
		  $warnings, $errors );
	    if ( $time !== false )
	    {
		if ( $subop == 'delete' )
		{
		    $names[$pub_J] = '';
		    $favorites = read_favorites_list
			( $warnings );
		    $action = "move";
		}
		else
		    $action = "copy";

		$action = "$time $aid"
			. " publish-$action"
			. " $pub_project $basename"
			. PHP_EOL;
		$action_project = $pub_project;
	    }

	    $pub_J = NULL;
	}
	else
	    exit ( 'UNACCEPTABLE HTTP POST:' .
	           " writable op $op" );

	if ( count ( $errors ) == 0
	     &&
	     isset ( $action ) )
	{
	    // Else if no errors but an action.
	    //
	    $places = [ "accounts/$aid" ];
	    if ( isset ( $action_project ) )
		$places[] = "projects/$action_project";
	    foreach ( $places as $place )
	    foreach ( [$place, "$place/+lists+"] as $d )
	    {
		$f = "$d/+actions+";
		$r = @file_put_contents
		    ( "$epm_data/$f", $action,
		      FILE_APPEND );
		if ( $r === false )
		    ERROR ( "cannot write $f" );
	    }
	}
    }

    $writable_count = 0;
    foreach ( [0,1] as $J )
    {
	$writable[$J] = false;
	if ( $names[$J] != '' )
	{
	    list ( $project, $basename ) =
	        explode ( ':', $names[$J] );
	    if (    $project == '-'
	         && $basename != '-'
		 && $rw )
	    {
		$writable[$J] = true;
	        ++ $writable_count;
	    }
	}

        if ( isset ( $lists[$J] ) ) continue;

	if ( $names[$J] == '' )
	    $lists[$J] = [];
	else
	    $lists[$J] = read_problem_list
	        ( $names[$J], $warnings );
	$lengths[$J] = count ( $lists[$J] );
	$edited[$J] = 'no';

    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.list0, div.list1 {
        width:50%;
	float:left;
	padding: 0px;
    }
    div.list0 .list-name, div.list0 .dsc-header {
        background-color: var(--bg-dark-tan);
    }
    div.list1 .list-name, div.list1 .dsc-header {
        background-color: var(--bg-dark-blue);
    }
    div.list0 .dsc-body, div.list0 .list-header,
                         div.list0 .problem {
        background-color: var(--bg-tan);
    }
    div.list1 .dsc-body, div.list1 .list-header,
                         div.list1 .problem {
        background-color: var(--bg-blue);
    }
    div.delete-header {
        background-color: var(--bg-yellow);
	padding: var(--large-font-size) 0px;
    }
    div.read-only-header, div.writable-header,
    			  div.delete-header,
                          div.list-name,
			  div.dsc-header {
        text-align: center;
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
	margin: 0px;
    }
    div.read-only-header, div.writable-header,
                          div.list-name {
	padding: var(--font-size);
    }
    table.problem {
	border: 1px solid black;
	border-radius: var(--radius);
	margin: 0px;
	width: 100%;
	/* border-radius does not apply to table
	 * elements when border-collapse is collapse
	 */
    }
    table.problem td {
        padding: var(--pad);
	font-size: var(--large-font-size);
    }
    div.dsc-header {
	padding: var(--pad);
    }
    div.dsc-body {
	border: 1px solid black;
	margin-top: var(--pad);
	text-align: left;
	padding: var(--pad);
    }
    div.dsc-body p, div.dsc-body pre {
        margin: 0px;
	padding: 0.25ex 0px;
    }
    div.dsc-body p {
	font-size: var(--large-font-size);
    }
    div.dsc-body pre {
	font-size: var(--font-size);
    }

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

</script>

</head>
<body>

<?php 

    // Form for submits other than upload of .dsc:
    //
    echo <<<EOT
    <form method='POST' action='list.php'
	  id='submit-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <input type='hidden' name='list' id='list'>
    <input type='hidden' name='lengths' id='lengths'>
    <input type='hidden' name='indices' id='indices'>
    <input type='hidden' name='edited' id='edited'>
    <input type='hidden' name='name' id='name'>
    </form>
EOT;

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>";
	echo "<strong>Errors:</strong>";
	echo "<div class='indented'>";
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<strong>Warnings:</strong>";
	echo "<div class='indented'>";
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }

    echo <<<EOT
    <div class='manage'>
    <form method='GET' action='list.php'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>
    <tr style='width:100%'>
EOT;

    if ( isset ( $pub_J ) )
    {
        if ( isset ( $pub_project ) )
	{
	    $name = substr ( $names[$pub_J], 2 );
		// Here $pub_J == $J and list $J is
		// writable.
	    $f = "projects/$pub_project/+lists+/" .
	         "$name.list";
	    if ( file_exists ( "$epm_data/$f" ) )
		$msg = "overwrite the $pub_project" .
		       " $name list with <i>Your</i>" .
		       " $name list";
	    else
		$msg = "make a new $pub_project $name" .
		       " list that is a copy of" .
		       " <i>Your</i> $name list";
	    echo <<<EOT
	    <div class='errors'><strong>
	    Do you want to $msg
	    <br>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-publish","$pub_J",
		            "delete")'>
		and then delete
		<i>Your</i> $name list?</button>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-publish","$pub_J",
		            "keep")'>
		or keep <i>Your</i> $name list
		instead?</button>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-publish","$pub_J",
		            "cancel")'>
		or cancel this operation completely?
		</button>
	    </strong></div>
EOT;
	}
	else
	{
	    list ( $project, $name ) =
	        explode ( ':', $names[$pub_J] );
		// Here $pub_J == $J and list $J is
		// NOT writable.
	    $f = "accounts/$aid/+lists+/" .
	         "$name.list";
	    if ( file_exists ( "$epm_data/$f" ) )
		$msg = "overwrite <i>Your</i> $name" .
		       " list with the $project $name" .
		       " list";
	    else
		$msg = "make a new <i>Your</i> $name" .
		       " list that is a copy of" .
		       " the $project $name list";
	    echo <<<EOT
	    <div class='errors'><strong>
	    Do you want to $msg
	    <br>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-unpublish",
		            "$pub_J","delete")'>
		and then delete the
		$project $name list?</button>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-unpublish",
		            "$pub_J","keep")'>
		or keep the $project $name list
		instead?</button>
	    <button type='button'
		    onclick=
		    'SUBMIT("execute-unpublish",
		            "$pub_J","cancel")'>
		or cancel this operation completely?
		</button>
	    </strong></div>
EOT;
	}
    }
    elseif ( $writable_count == 0 )
    {
	$login_title =
	    'Login Name; Click to See User Profile';
	echo <<<EOT
	<td style='text-align:left'>
	<button type='submit'
		formaction='user.php'
		title='$login_title'>
		$lname</button>
	</td>
	<td>
	<strong>Go To</strong>
	<button type='submit' formaction='project.php'>
	Project
	</button>
	<button type='submit'
	        formaction='favorites.php'>
	Edit Favorites
	</button>
	<strong>Page</strong>
	</td>
	<td style='text-align:right'>
	$RW_BUTTON
	<button type='button' id='refresh'
		onclick='location.replace
		    ("list.php?id=$ID")'>
	    &#8635;</button>
	<button type='button'
		onclick='HELP("list-page")'>
	    ?</button>
	</td>
EOT;
    }
    else
    {
        echo <<<EOT
	<td style='text-align:left'>
	<strong title='Login Name'>$lname</strong>
	</td>
	<td style='text-align:right'>
	<button type='button'
		onclick='HELP("list-page")'>
	    ?</button>
	</td>
EOT;
    }
    echo <<<EOT
    </tr>
    </table>
    </form>
    </div>
EOT;

    $elements = [];
    $upload_file_title = 'Selected List Description'
		       . ' (.dsc) File to be Uploaded';
    foreach ( [0,1] as $J )
    {
        $name = $names[$J];
	$sname = ( $name != '' ? $name : NULL );
	$options = list_to_options
	    ( $favorites, $sname, [$names[1 - $J]] );
	$publishable = [];
	    // List of projects to which list can be
	    // published (if its not modified).  Empty
	    // for unpublishable lists.
	$unpublishable = false;
	$pname = 'No List Selected';
	$description = '';
	$lines = '';
	if ( $name != '' )
	{
	    $lines = list_to_edit_rows
	        ( $elements, $lists[$J] );

	    list ( $project, $basename ) =
	        explode ( ':', $name );

	    if ( $basename == '-' )
	        $basename = '<i>Problems</i>';
	    elseif ( $writable[$J] )
	    {
	        $f = "accounts/$aid/+lists+/"
		   . "$basename.list";
		$description = read_list_description
		    ( $f );
		$privs = ['publish-all'];
		if ( preg_match ( $own_re, $basename ) )
		    $privs[] = 'publish-own';
		$publishable = read_projects ( $privs );
	    }
	    else
	    {
	        project_priv_map ( $map, $project );
		$privs = ['unpublish-all'];
		if ( preg_match ( $own_re, $basename ) )
		    $privs[] = 'unpublish-own';
		foreach ( $privs as $priv )
		{
		    if ( isset ( $map[$priv] )
		         &&
			 $map[$priv] == '+' )
			$unpublishable = true;
		}
	    }

	    if ( $project == '-' )
	        $project = '<i>Your</i>';
	    $pname = "$project $basename";

	    $copy_message = 'Copy To';
	}
	else
	    $copy_message = 'Create Empty';

	echo <<<EOT
	<div class='list$J'>
EOT;

	if ( isset ( $pub_J ) )
	{
	    // No header
	}
	elseif ( ! $rw )
	    echo <<<EOT
	    <div class='read-only-header list-header'>

	    <strong>Select List to Edit:</strong>
	    <select title='New Problem List to Edit'
		   onchange='SUBMIT("select","$J",
		                     event
				     .currentTarget
				     .value)'>
	    <option value=''>No List Selected</option>
	    $options
	    </select>
	    </div>
EOT;
	elseif ( ! $writable[$J] )
	{
	    echo <<<EOT
	    <div class='read-only-header list-header'>

	    <strong>Select List to Edit:</strong>
	    <select title='New Problem List to Edit'
		   onchange='SUBMIT("select","$J",
		                     event
				     .currentTarget
				     .value)'>
	    <option value=''>No List Selected</option>
	    $options
	    </select>
EOT;
	    if ( $unpublishable )
	        echo <<<EOT
		<br>
		<button type='button'
			onclick=
			  'SUBMIT("unpublish","$J")'>
		UNPUBLISH</button>
EOT;
	    echo <<<EOT

	    <br>
	    <strong>$copy_message New List:</strong>
	    <input type="text"
		   size="24"
		   placeholder="New Problem List Name"
		   title="New Problem List Name"
		   onkeydown='NEW(event,"$J")'>

	    </div>
EOT;
	}
	elseif ( $writable[$J] )
	{
	    $yes = ( $edited[$J] == 'yes' ?
	             'inline' : 'none' );
	    $no  = ( $edited[$J] == 'no' ?
	             'inline' : 'none' );
	    echo <<<EOT
	    <div id='write-header-$J'
	         class='writable-header list-header'>
	    <button type='button' id='save-button-$J'
	    	    style='display:$yes'
	            onclick='SUBMIT("save","$J")'>
	    SAVE</button>
	    <button type='button' id='reset-button-$J'
	    	    style='display:$yes'
	            onclick='SUBMIT("reset","$J")'>
	    RESET</button>
	    <button type='button' id='finish-button-$J'
	    	    style='display:$yes'
	            onclick='SUBMIT("finish","$J")'>
	    FINISH</button>
	    <button type='button' id='cancel-button-$J'
	            onclick='SUBMIT("cancel","$J")'>
	    CANCEL</button>
	    <button type='button'
	            onclick='DELETE_YES("$J")'>
	    DELETE</button>
EOT;
	    if ( count ( $publishable ) > 0 )
	    {
		$title = 'Select Project to Publish To';
		$project_options = '';
		foreach ( $publishable as $proj )
		    $project_options .=
		        "<option value='$proj'>" .
			"$proj</option>";
		echo <<<EOT
		<select
		     id='publish-button-$J'
		     name='project'
	    	     style='display:$no'
		     onchange='SUBMIT("publish","$J",
		                       event
				       .currentTarget
				       .value)'>
		     title='$title'>
		<option value=''>PUBLISH TO</option>
		$project_options
		</select>
EOT;
	    }
	    echo <<<EOT
	    <br>

	    <form method='POST' action='list.php'
		  enctype='multipart/form-data'
		  style='display:$no'
		  id='upload-form-$J'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden' name='op' value='dsc'>
	    <input type='hidden' name='list' value='$J'>
	    <input type='hidden' name='indices'
		   id='upload-indices-$J'>
	    <input type='hidden' name='lengths'
		   id='upload-lengths-$J'>
	    <input type='hidden' name='edited'
		   id='upload-edited-$J'>
	    <input type="hidden" name="MAX_FILE_SIZE"
		   value="$epm_upload_maxsize">
	    <label>
	    <strong>Upload $basename.dsc
	            Description File:</strong>
	    <input type="file" name="uploaded_file"
	           onchange='UPLOAD(event,"$J")'
		   title="$upload_file_title">
	    </label>
	    </form>
	    <br>
	    <strong>$copy_message New List:</strong>
	    <input type="text"
		   size="24"
		   placeholder="New Problem List Name"
		   title="New Problem List Name"
		   onkeydown='NEW(event,"$J")'>
	    </div>

	    <div id='delete-header-$J'
		 class='delete-header'
		 style='display:none'>
	    <strong>Do you really want to delete
	            $pname?</strong>
	    <button type='button'
	            onclick='SUBMIT("delete","$J")'>
		YES</button>
	    <button type='button'
	            onclick='DELETE_NO("$J")'>
		NO</button>

	    </div>

EOT;
	}
	$w = ( $writable[$J] ? 'yes' : 'no' );
	if ( isset ( $pub_J ) ) $w = 'no';
	echo <<<EOT
	<div id='list$J'
	     data-writable='$w' data-list='$J'>
	<div class='list-name'
	     ondrop='DROP(event)'
	     ondragover='ALLOWDROP(event)'>
	<strong>$pname</strong>
	</div>

	$lines
	</div>
EOT;
	if ( $description != '' )
	    echo <<<EOT
	    <div class='dsc-header'>
	    <strong>$pname List Description</strong>
	    <div class='dsc-body'>
	    $description
	    </div>
	    </div>
EOT;
	echo <<<EOT
	</div>
EOT;
    }

    echo <<<EOT
    <script>
    var names = ['{$names[0]}','{$names[1]}'];
    var lengths = ['{$lengths[0]}','{$lengths[1]}'];
    var indices = ['',''];
    var edited = ['{$edited[0]}','{$edited[1]}'];
    </script>
EOT;

    echo <<<EOT
    <script>
    function DELETE_YES ( J )
    {
	let write_header = document.getElementById
	    ( "write-header-" + J );
	let delete_header = document.getElementById
	    ( "delete-header-" + J );
	write_header.style.display = 'none';
	delete_header.style.display = 'block';
    }
    function DELETE_NO ( J )
    {
	let write_header = document.getElementById
	    ( "write-header-" + J );
	let delete_header = document.getElementById
	    ( "delete-header-" + J );
	write_header.style.display = 'block';
	delete_header.style.display = 'none';
    }
    function EDITING ( J )
    {
        edited[J] = 'yes';
	let save_button = document.getElementById
	    ( "save-button-" + J );
	save_button.style.display = 'inline';
	let reset_button = document.getElementById
	    ( "reset-button-" + J );
	reset_button.style.display = 'inline';
	let finish_button = document.getElementById
	    ( "finish-button-" + J );
	finish_button.style.display = 'inline';
	let publish_button = document.getElementById
	    ( "publish-button-" + J );
	if ( publish_button !== null )
	    publish_button.style.display = 'none';
	let upload_form = document.getElementById
	    ( "upload-form-" + J );
	upload_form.style.display = 'none';
    }

    let submit_form = document.getElementById
	( 'submit-form' );
    let op_in = document.getElementById ( 'op' );
    let list_in = document.getElementById ( 'list' );
    let lengths_in = document.getElementById
        ( 'lengths' );
    let indices_in = document.getElementById
        ( 'indices' );
    let edited_in = document.getElementById
        ( 'edited' );
    let name_in = document.getElementById ( 'name' );

    let on = 'black';
    let off = 'white';

    function BOX ( table )
    {
	let tbody = table.firstElementChild;
	let tr = tbody.firstElementChild;
	let td = tr.firstElementChild;
	let checkbox = td.firstElementChild;
	return checkbox;
    }

    for ( var J = 0; J <= 1; ++ J )
    {
	let list = document.getElementById
	    ( 'list' + J );
	let first = list.firstElementChild;
	var next = first.nextElementSibling;
	for ( I = 0; I < lengths[J]; ++ I )
	{
	    if ( next == null ) break;
	    BOX(next).style.backgroundColor = on;
	    next = next.nextElementSibling;
	}
    }

    function COMPUTE_INDICES()
    {
	for ( var J = 0; J <= 1; ++ J )
	{
	    let list = document.getElementById
		( 'list' + J );
	    let first = list.firstElementChild;
	    var next = first.nextElementSibling;

	    var ilist = [];
	    var length = 0;
	    while ( next != null )
	    {
		ilist.push ( next.id );
		let checkbox = BOX ( next );
		if (    checkbox.style.backgroundColor
		     == on )
		    ++ length;
		next = next.nextElementSibling;
	    }
	    lengths[J] = length;
	    indices[J] = ilist.join ( ':' );
	}
    }

    function SUBMIT ( op, list, name = '' )
    {
	COMPUTE_INDICES();
	op_in.value = op;
	list_in.value = list;
	indices_in.value = indices.join(';');
	lengths_in.value = lengths.join(';');
	edited_in.value = edited.join(';');
	name_in.value = name;
	submit_form.submit();
    }

    function UPLOAD ( event, J )
    {
	event.preventDefault();
	let submit_form = document.getElementById
		( 'upload-form-' + J );
	let indices_in = document.getElementById
		( 'upload-indices-' + J );
	let lengths_in = document.getElementById
		( 'upload-lengths-' + J );
	let edited_in = document.getElementById
		( 'upload-edited-' + J );
	COMPUTE_INDICES();
	indices_in.value = indices.join(';');
	lengths_in.value = lengths.join(';');
	edited_in.value = edited.join(';');
	submit_form.submit();
    }

    function NEW ( event, J )
    {
	if ( event.code === 'Enter' )
	{
	    event.preventDefault();
	    let new_in = event.currentTarget;
	    SUBMIT ( 'new', J, new_in.value );
	}
    }

    </script>
EOT;

if ( $rw )
    echo <<<EOT
    <script>
    function CHECK ( event )
    {
	event.preventDefault();
	let checkbox = event.currentTarget;
	let td = checkbox.parentElement;
	let tr = td.parentElement;
	let tbody = tr.parentElement;
	let table = tbody.parentElement;
	let div = table.parentElement;
	let writable = div.dataset.writable;
	if ( writable == 'no' ) return;

	EDITING ( div.dataset.list );

	if ( checkbox.style.backgroundColor == on )
	{
	    checkbox.style.backgroundColor = off;
	    var next = table.nextElementSibling;
	    while ( next != null
		    &&
		       BOX(next).style.backgroundColor
		    == on )
		next = next.nextElementSibling;
	    if ( next != table.nextElementSibling )
	    {
		if ( next == null )
		    div.appendChild ( table );
		else
		    div.insertBefore ( table, next );
	    }
	}
	else
	{
	    checkbox.style.backgroundColor = on;
	    var previous = table.previousElementSibling;
	    while ( previous != div.firstElementChild
		    &&
		       BOX(previous).style
		                    .backgroundColor
		    != on )
		previous =
		    previous.previousElementSibling;
	    if (    previous
	         != table.previousElementSibling )
	    {
		let next = previous.nextElementSibling;
		div.insertBefore ( table, next );
	    }
	}
    }

    var dragsrc = null;
	// Source (start) table of drag.  We cannot use
	// id because `copy' duplicates ids.

    function DRAGSTART ( event )
    {
	let table = event.currentTarget;
	let div = table.parentElement;
	let writable = div.dataset.writable;
	let effect = 'copy';
	if ( writable == 'yes' && ! event.ctrlKey )
	    effect = 'move';
	event.dataTransfer.dropEffect = effect;
	event.dataTransfer.setData ( 'effect', effect );
	dragsrc = table;
    }
    function DRAGEND ( event )
    {
	dragsrc = null;
    }
    function ALLOWDROP ( event )
    {
	event.preventDefault();
    }
    function DROP ( event )
    {
	let target = event.currentTarget;
	    // May be table or the header div above
	    // tables.
	let div = target.parentElement;
	let writable = div.dataset.writable;
	if ( writable == 'no' )
	{
	    event.preventDefault();
	    return;
	}

	EDITING ( div.dataset.list );
	let effect = event.dataTransfer.getData
	    ( 'effect' );
	let src = dragsrc;
	if ( effect == 'copy' )
	    src = src.cloneNode ( true );
	else
	    EDITING ( 1 - div.dataset.list );
	let next = target.nextElementSibling;
	if ( next == null )
	{
	    div.appendChild ( src );
	    if ( target != div.firstElementChild
		 &&
		    BOX(target).style.backgroundColor
		 != on )
		BOX(src).style.backgroundColor = off;
	}
	else
	{
	    div.insertBefore ( src, next );
	    if ( BOX(next).style.backgroundColor == on )
		BOX(src).style.backgroundColor = on;
	    else if ( target != div.firstElementChild
		      &&
			 BOX(target).style
			            .backgroundColor
		      != on )
		BOX(src).style.backgroundColor = off;
	}
    }

    </script>
EOT;
else
    echo <<<EOT
    <script>
    function CHECK ( event ) {}
    function DRAGSTART ( event )
    {
	event.preventDefault();
    }
    function DRAGEND ( event ) {}
    function ALLOWDROP ( event ) {}
    function DROP ( event ) {}
    </script>
EOT;

?>

</body>
</html>
