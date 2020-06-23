<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun 22 15:56:53 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Maintains problem lists.

    // See project.php for directory and files.

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //	   EPM_DATA NAMES
    //		[name1,name2] where nameJ is in the
    //		format PROJECT:BASENAME or is ''
    //		for no-list.
    //
    //     EPM_DATA ELEMENTS
    //		A list of the elements of the form
    //
    //			[TIME PROJECT PROBLEM]
    //
    //		that are displayed as list rows.  The
    //		The 'op' POST contains indices of these
    //		elements for the edited versions of each
    //		list.  The elements for both lists are
    //		included here in arbitrary order.


    // POST:
    // ----
    //
    // Each post may update one list and has the
    // following values.
    //
    //	    indices='index0;index1'
    //		Here indexJ is the indices in EPM_DATA
    //		ELEMENTS of list J, with the indices
    //		separated by `:', and '' denoting the
    //		empty list.
    //
    //	    lengths='length0;length1'
    //		The first lengthJ elements of list J are
    //		marked, and the rest are NOT marked.
    //
    //	    list=J
    //		List number (J = 0 or 1) affected by
    //		operation.
    //
    //	    name=NAME
    //		List name for `select' operation below,
    //		or basename for `new' operation below.
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
    //	    **	select	Set nameJ = NAME and load list J
    //			from file designated by NAME.
    //
    //	    **	new	Create a new list J with given
    //			NAME and load list J from the
    //			empty new list.
    //
    //  * Should be sent ONLY if list J is writable.
    // ** Should be sent ONLY if list J is read-only.

    $epm_page_type = '+main+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
        $_SESSION['EPM_DATA'] = [
	    'NAMES' => ['',''],
	    'ELEMENTS' => [] ];
    elseif ( ! isset ( $_SESSION['EPM_DATA'] ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $data = & $_SESSION['EPM_DATA'];
    $names = & $data['NAMES'];
    $elements = & $data['ELEMENTS'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    $lists = [NULL,NULL];
    $lengths = [0,0];
        // Lists to be given to list_to_edit_rows.
	// If $lists[J] not set by POST, will be set
	// according to files named by $names[J].
	// $lengths[J] is the number of marked
	// elements of $list[J].

    $favorites = favorites_to_list ( 'pull|push' );
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
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $I >= $limit )
		exit ( 'UNACCEPTABLE HTTP POST' );
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
    // suppress write.  If list does not exist, a
    // warning message is added to $warnings.  It is an
    // error if the file last name component is not
    // BASENAME.dsc, where $name is -:BASENAME.
    //
    // If successful, this function returns the listname
    // of the list given the description, in the form
    // '-:basename'.  If unsuccessful, false is
    // returned.
    //
    function upload_list_description
	    ( $name, & $warnings, & $errors )
    {
        global $epm_data, $uid, $epm_name_re,
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
	    return;
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
	$f = "users/$uid/+lists+/$fbase.list";
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

    if ( $epm_method == 'POST' )
    {
        if ( ! isset ( $_POST['op'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['indices'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['lengths'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['list'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$op = $_POST['op'];
	if ( ! in_array ( $op, ['save','finish','reset',
	                        'cancel','delete',
				'select','new',
				'dsc', 'publish',
				'unpublish'], true ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$J = $_POST['list'];
	if ( ! in_array ( $J, [0,1] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$K = 1 - $J;

	$indices = explode ( ';', $_POST['indices'] );
	$lengths = explode ( ';', $_POST['lengths'] );

	$lists[$K] = index_to_list ( $indices[$K] );
	if ( ! preg_match ( '/^\d+$/', $lengths[$K] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	if ( $lengths[$K] > count ( $lists[$K] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	if ( $op == 'save' || $op == 'finish' )
	{
	    $lists[$J] = index_to_list ( $indices[$J] );
	    if ( ! preg_match
	               ( '/^\d+$/', $lengths[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $lengths[$J] > count ( $lists[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    // Be sure all UNACCEPTABLE HTTP POST checks
	    // are done before we write the file.
	    //
	    write_file_list
		( listname_to_filename ( $names[$J] ),
		  array_slice
		      ( $lists[$J], 0, $lengths[$J] ) );
	    if ( $op == 'finish' )
	    {
	        $lists[$J] = NULL;
		$names[$J] = '';
	    }
	}
	elseif ( $op == 'reset' )
	    /* Do Nothing */;
	elseif ( $op == 'cancel' )
	    $names[$J] = '';
	elseif ( $op == 'delete'
	         ||
		 $op == 'publish'
		 ||
		 $op == 'unpublish' )
	{
	    list ( $user, $name ) =
	        explode ( ':', $names[$J] );
	    if ( $user != '-' )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    if ( $op == 'delete' )
	    {
		delete_list ( $name, $errors, true );
		if ( count ( $errors ) == 0 )
		{
		    $names[$J] = '';
		    $favorites = favorites_to_list
			( 'pull|push' );
		}
	    }
	    elseif ( $op == 'publish' )
		publish_list ( $name, $errors );
	    elseif ( $op == 'unpublish' )
		unpublish_list ( $name, $errors );
	}
	elseif ( $op == 'dsc' )
	{
	    upload_list_description
		( $names[$J], $warnings, $errors );
	}
	elseif ( $op == 'new' )
	{
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $name = $_POST['name'];
	    make_new_list ( $name, $errors );
	    if ( count ( $errors ) == 0 )
	    	$names[$J] = "-:$name";
		// No need to update $favorites as
		// new list is excluded from
		// selectors.
	}
	elseif ( $op == 'select' )
	{
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $name = $_POST['name'];
	    if ( ! isset ( $fmap[$name] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $name == $names[$K] )
	        $errors[] = "cannot select list because"
		          . " then both lists would be"
			  . " the same";
	    else
	    	$names[$J] = $name;
	}

	// If there were errors, restore $list[$J].
	//
	// Importantly, if there were errors no files
	// have been changed.
	//
	if ( count ( $errors ) > 0 )
	{
	    $lists[$J] = index_to_list ( $indices[$J] );
	    if ( ! preg_match
	               ( '/^\d+$/', $lengths[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $lengths[$J] > count ( $lists[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
    }

    $writable_count = 0;
    foreach ( [0,1] as $J )
    {
	if ( $names[$J] != '' )
	{
	    list ( $project, $basename ) =
	        explode ( ':', $names[$J] );
	    if ( $project == '-' && $basename != '-' )
	        ++ $writable_count;
	}

        if ( isset ( $lists[$J] ) ) continue;

	if ( $names[$J] == '' )
	    $lists[$J] = [];
	else
	    $lists[$J] = listname_to_list
	        ( $names[$J] );
	$lengths[$J] = count ( $lists[$J] );
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
    }
    div.dsc-body p, div.dsc-body pre {
        margin: 0px;
	padding: var(--pad);
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

    echo <<<EOT
    <form method='POST' action='list.php'
	  id='submit-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <input type='hidden' name='list' id='list'>
    <input type='hidden' name='lengths' id='lengths'>
    <input type='hidden' name='indices' id='indices'>
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
    <form method='GET'>
    <input type='hidden' name='id' value='$ID'>
    <table style='width:100%'>

    <tr id='not-edited' style='width:100%'>
    <td>
    <strong>User:</strong>
    <button type='submit'
    	   formaction='user.php'
           title='Click to See User Profile'>
	   $email</button>
    </td>
    <td>
    <strong>Go To</strong>
    <button type='submit' formaction='project.php'>
    Project
    </button>
    <button type='submit' formaction='favorites.php'>
    Edit Favorites
    </button>
    <strong>Page</strong>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("list-page")'>
	?</button>
    </td>
    </tr>

    <tr id='edited' style='width:100%;display:none'>
    <td>
    <strong>User:&nbsp;$email</strong>
    </td>
    <td>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("list-page")'>
	?</button>
    </td>
    </tr>

    </table>
    </form>
    </div>
EOT;
    $data['ELEMENTS'] = [];
    $elements = & $data['ELEMENTS'];
    $options = list_to_options
        ( $favorites, NULL, $names );
    $upload_file_title = 'Selected List Description'
		       . ' (.dsc) File to be Uploaded';
    foreach ( [0,1] as $J )
    {
        $name = $names[$J];
	$writable = 'no';
	$published = NULL;
	$pname = 'No List Selected';
	$description = '';
	$lines = '';
	if ( $name != '' )
	{
	    $lines = list_to_edit_rows
	        ( $elements, $lists[$J] );

	    list ( $project, $basename ) =
	        explode ( ':', $name );
	    if ( $project == '-' )
	    {
	        $project = '<i>Your</i>';
		$writable = 'yes';
	    }
	    if ( $basename == '-' )
	    {
	        $basename = '<i>Problems</i>';
		$writable = 'no';
	    }
	    elseif ( $writable == 'yes' )
	    {
	        $f = "users/$uid/+lists+/"
		   . "$basename.list";
		$description = read_list_description
		    ( $f );
		$g = "lists/$uid:$basename.list";
		if ( file_exists ( "$epm_data/$g" ) )
		    $published = 'yes';
		else
		    $published = 'no';
	    }
	    $pname = "$project $basename";
	}

	echo <<<EOT
	<div class='list$J'>
EOT;

	if ( $writable == 'no' )
	    echo <<<EOT
	    <div class='read-only-header list-header'>

	    <strong>Change To New List:</strong>
	    <input type="text"
		   size="24"
		   placeholder="New Problem List Name"
		   title="New Problem List Name"
		   onkeydown='NEW(event,"$J")'>

	    <br>

	    <strong>Select List to Edit:</strong>
	    <select title='New Problem List to Edit'
		   onclick='SELECT_LIST("$J")'>
	    $options
	    </select>
	    </div>
EOT;
	if ( $writable == 'yes' )
	{
	    echo <<<EOT
	    <div id='write-header-$J'
	         class='writable-header list-header'>
	    <button type='button'
	            onclick='SUBMIT("save","$J")'>
	    SAVE</button>
	    <button type='button'
	            onclick='SUBMIT("reset","$J")'>
	    RESET</button>
	    <button type='button'
	            onclick='SUBMIT("finish","$J")'>
	    FINISH</button>
	    <button type='button'
	            onclick='SUBMIT("cancel","$J")'>
	    CANCEL</button>
	    <button type='button'
	            onclick='DELETE("$J")'>
	    DELETE</button>
EOT;
	    if ( $published == 'no' )
	        echo <<<EOT
		<button type='button'
			onclick=
			  'SUBMIT("publish","$J")'>
		PUBLISH</button>
EOT;
	    elseif ( $published == 'yes' )
	        echo <<<EOT
		<button type='button'
			onclick=
			  'SUBMIT("unpublish","$J")'>
		UNPUBLISH</button>
EOT;
	    echo <<<EOT
	    <br>

	    <form method='POST' action='list.php'
		  enctype='multipart/form-data'
		  id='upload-form$J'>
	    <input type='hidden' name='id' value='$ID'>
	    <input type='hidden' name='op' value='dsc'>
	    <input type='hidden' name='list' value='$J'>
	    <input type='hidden' name='indices'
		   id='upload-indices$J'>
	    <input type='hidden' name='lengths'
		   id='upload-lengths$J'>
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
	echo <<<EOT
	<div id='list$J' data-writable='$writable'>
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
    </script>
EOT;

?>

<script>
function DELETE ( J )
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

let edited = document.getElementById ( 'edited' );
let not_edited = document.getElementById
    ( 'not-edited' );
let submit_form = document.getElementById
    ( 'submit-form' );
let op_in = document.getElementById ( 'op' );
let list_in = document.getElementById ( 'list' );
let lengths_in = document.getElementById ( 'lengths' );
let indices_in = document.getElementById ( 'indices' );
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
    let list = document.getElementById ( 'list' + J );
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
	    if ( checkbox.style.backgroundColor == on )
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
    name_in.value = name;
    submit_form.submit();
}

function UPLOAD ( event, J )
{
    event.preventDefault();
    let submit_form = document.getElementById
	    ( 'upload-form' + J );
    let indices_in = document.getElementById
	    ( 'upload-indices' + J );
    let lengths_in = document.getElementById
	    ( 'upload-lengths' + J );
    COMPUTE_INDICES();
    indices_in.value = indices.join(';');
    lengths_in.value = lengths.join(';');
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

function SELECT_LIST ( J )
{
    let select = event.currentTarget;
    SUBMIT ( 'select', J, select.value );
}

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
		   BOX(previous).style.backgroundColor
		!= on )
	    previous = previous.previousElementSibling;
	if ( previous != table.previousElementSibling )
	{
	    let next = previous.nextElementSibling;
	    div.insertBefore ( table, next );
	}
    }
    edited.style.display = 'table-row';
    not_edited.style.display = 'none';
}

var dragsrc = null;
    // Source (start) table of drag.
    // We cannot use id because `copy' duplicates ids.
var controls_down = 0;
    // Non-zero if some control key is down.

function KEYDOWN ( event )
{
    if ( event.code == 'ControlLeft'
         ||
	 event.code == 'ControlRight' )
        ++ controls_down;
}

function KEYUP ( event )
{
    if ( event.code == 'ControlLeft'
         ||
	 event.code == 'ControlRight' )
        -- controls_down;
}

window.addEventListener ( 'keydown', KEYDOWN );
window.addEventListener ( 'keyup', KEYUP );

function DRAGSTART ( event )
{
    let table = event.currentTarget;
    let div = table.parentElement;
    let writable = div.dataset.writable;
    let effect = 'copy';
    if ( writable == 'yes' && controls_down == 0 )
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
        // May be table or the header div above tables.
    let div = target.parentElement;
    let writable = div.dataset.writable;
    if ( writable == 'no' )
    {
        event.preventDefault();
	return;
    }
    let effect = event.dataTransfer.getData
        ( 'effect' );
    let src = dragsrc;
    if ( effect == 'copy' )
        src = src.cloneNode ( true );
    let next = target.nextElementSibling;
    if ( next == null )
    {
        div.appendChild ( src );
	if ( target != div.firstElementChild
	     &&
	     BOX(target).style.backgroundColor != on )
	    BOX(src).style.backgroundColor = off;

	edited.style.display = 'table-row';
	not_edited.style.display = 'none';
    }
    else
    {
	div.insertBefore ( src, next );
	if ( BOX(next).style.backgroundColor == on )
	    BOX(src).style.backgroundColor = on;
	else if ( target != div.firstElementChild
	          &&
	             BOX(target).style.backgroundColor
		  != on )
	    BOX(src).style.backgroundColor = off;

	edited.style.display = 'table-row';
	not_edited.style.display = 'none';
    }
}

</script>

</body>
</html>
