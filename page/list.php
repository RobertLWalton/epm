<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat May 16 18:50:36 EDT 2020

    // Maintains problem lists.

    // See project.php for directory and files.

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //     EPM_DATA ID
    //	   	32 hex digit random number used to
    //		verify POSTs to this page.
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
    //		or
    //			[TIME PROJECT BASENAME]
    //
    //		that are displayed as list rows.  The
    //		The 'op' POST contains indices of these
    //		elements for the edited versions of each
    //		list and stack.  The elements for both
    //		lists are included here in arbitrary
    //		order.


    // POST:
    // ----
    //
    // Each post may update the lists and has the
    // following values.  J is 0 or 1.
    //
    //	    ID=<value of EPM_DATA ID>
    //
    //	    indices='index1;index2'
    //		Here indexJ is the indices in EPM_DATA
    //		ELEMENTS of list J, with the indices
    //		separated by `:', and '' denoting the
    //		empty list.
    //
    //	    lengths='length1;length2'
    //		The first lengthJ elements of list J are
    //		marked, and the rest are NOT marked.
    //
    //	    list=J
    //		List number (J = 0 or 1) affected by
    //		operation.
    //
    //	    name=NAME
    //		List name for change operation below, or
    //		basename for new operation below.
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
    //	    **	change	Set nameJ = NAME and load list J
    //			from file designated by NAME.
    //
    //	    **	new	Create a new list J with given
    //			NAME and load list J from the
    //			empty new list.
    //
    //	    **	dsc	Upload description file and load
    //			list J from the list designated
    //			by the description file.  How-
    //			ever, if the list of the des-
    //			cription file is the list of
    //			list 1-J, this is an error.
    //
    //  * Should be sent ONLY if list J HAS been modified.
    // ** Should be send ONLY if list J has NOT been
    //    modified.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( $method == 'GET' )
        $_SESSION['EPM_DATA'] = [
	    'ID' => bin2hex ( random_bytes ( 16 ) ),
	    'NAMES' => ['',''],
	    'ELEMENTS' => [] ];

    $data = & $_SESSION['EPM_DATA'];
    $id = $data['ID'];
    $names = & $data['NAMES'];
    $elements = & $data['ELEMENTS'];

    if ( $method == 'POST'
         &&
	 ( ! isset ( $_POST['ID'] )
	   ||
	   $_POST['ID'] != $id ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $id = bin2hex ( random_bytes ( 16 ) );
    $data['ID'] = $id;

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
    // are HTML rows of the form:
    //
    //		<table id='I' class='problem'
    //                 draggable='true'
    //		       ondragover='ALLOWDROP(event)'
    //		       ondrop='DROP(event)'
    //                 ondragstart='DRAGSTART(event)'
    //                 ondragend='DRAGEND(event)'>
    //		<tr>
    //		<td style='width:10%;text-align:left'>
    //		<span class='checkbox'
    //		      onclick='CHECK(event)'
    //		      >&nbsp;</span></td>
    //		<td style='width:30%;text-align:center'
    //              onclick='DUP(event)'>
    //		$project $problem $time</td>
    //		</tr></table>
    //
    // where if PROJECT is '-' it is replaced by
    // '<i>Your</i>' in the string, TIME is the first
    // 10 characters of the time (just the day part),
    // and I is the index of the element in the
    // $elements list.
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
    	    <span class='checkbox'
    	          onclick='CHECK(event)'
		  >&nbsp;</span></td>
    	    <td style='width:80%;text-align:center'
    	        onclick='DUP(event)'>
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
    // warning message is added to $warnings.
    //
    // If successful, this function returns the listname
    // of the list given the description, in the form
    // '-:basename'.  If unsuccessful, false is
    // returned.
    //
    function upload_list_description
	    ( & $warnings, & $errors )
    {
        global $epm_data, $uid, $epm_name_re,
	       $epm_upload_maxsize;

        if ( ! isset ( $_FILES['uploaded_file'] ) )
	{
	    $errors[] = "no file choosen; try again";
	    return;
	}

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

	if ( $fext != 'dsc' )
	{
	    $errors[] = "$fname has wrong extension"
	              . " (should be .dsc)";
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
	$f = "users/$uid/+indices+/$fbase.index";
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

    if ( $method == 'POST' )
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
				'change','new',
				'dsc'], true ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$J = $_POST['list'];
	if ( ! in_array ( $J, [0,1] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$K = 1 - $J;

	// For list description upload, if list is
	// for $names[$K] exchange $J and $K.
	//
	if ( $op == 'dsc' )
	{
	    if ( ! isset ( $_FILES['uploaded_file']
	                          ['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $name = $_FILES['uploaded_file']['name'];
	    $basename =
	        pathinfo ( $name, PATHINFO_FILENAME );
	    if ( $names[$K] == "-:$basename" )
	    {
	        $J = 1 - $J;
		$K = 1 - $K;
	    }
	}

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
	    if ( ! preg_match ( '/^\d+$/', $lengths[$J] ) )
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
	elseif ( $op == 'delete' )
	{
	    delete_list ( $names[$J], $errors, true );
	    if ( count ( $errors ) == 0 )
	    {
		$names[$J] = '';
		$favorites = favorites_to_list
		    ( 'pull|push' );
	    }
	}
	elseif ( $op == 'dsc' )
	{
	    $name = upload_list_description
	        ( $warnings, $errors );
	    if ( $name !== false )
	        $names[$J] = $name;
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
	elseif ( $op == 'change' )
	{
	    if ( ! isset ( $_POST['name'] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $name = $_POST['name'];
	    if ( ! isset ( $fmap[$name] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $name == $names[$K] )
	        $errors[] = "cannot change list so that"
		          . " both lists are the same"
			  . " list";
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
	    if ( ! preg_match ( '/^\d+$/', $lengths[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    if ( $lengths[$J] > count ( $lists[$J] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}
    }

    foreach ( [0,1] as $J )
    {
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
        width:48%;
	float:left;
	padding: 1%;
    }
    div.list0 {
        background-color: var(--bg-tan);
    }
    div.list1 {
        background-color: var(--bg-blue);
    }
    th, td {
        font-size: var(--large-font-size);
    }
    div.push-pull-list, div.edit-list {
	background-color: #F2D9D9;
    }
    div.list-description-header {
	background-color: #FF8080;
	text-align: center;
	padding: 10px;
    }
    div.list-description {
	background-color: #FFCCCC;
    }
    div.list-description p, div.list-description pre {
        margin: 0px;
        padding: 10px 0px 0px 10px;
    }
    span.checkbox {
        height: 15px;
        width: 30px;
	display: inline-block;
	margin-right: 3px;
	border: 1px solid;
	border-radius: 7.5px;
    }
    span.selected-project {
	color: red;
	display:inline-block;
	font-weight: bold;
    }
    label.select-project {
	color: red;
	display:inline-block;
    }
    #stack-table {
	background-color: #E6FF99;
	float: left;
	width: 50%;
        font-size: var(--large-font-size);
    }
    #list-table {
	background-color: #B3FFB3;
	float: left;
	width: 50%;
        font-size: var(--large-font-size);
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
    <input type='hidden' name='ID' value='$id'>
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

    $list_help = HELP ( 'list-page' );
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <form>
    <label>
    <strong>User:</strong>
    <input type='submit' value='$email'
	   formaction='user.php'
	   formmethod='GET'
           title='Click to See User Profile'>
    </label>
    </form>
    </td>
    <td>
    </td>
    <td>
    </td><td style='text-align:right'>
    $list_help</td>
    </tr>
    </table>
    </div>
EOT;
    $data['ELEMENTS'] = [];
    $elements = & $data['ELEMENTS'];
    $options = list_to_options ( $favorites, $names );
    $upload_title = 'Upload Selected List'
		  . ' Description (.dsc) File';
    $upload_file_title = 'Selected List Description'
		       . ' (.dsc) File to be Uploaded';
    foreach ( [0,1] as $J )
    {
        $name = $names[$J];
	$writable = 'no';
	$pname = 'No List Selected';
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
	    $pname = "$project $basename";
	}

	echo <<<EOT
	<div class='list$J'>

	<div style='text-align:center'>

	<form method='POST' action='list.php'
	      enctype='multipart/form-data'
	      id='upload-form$J'>
	<input type='hidden' name='ID' value='$id'>
	<input type='hidden' name='op' value='dsc'>
	<input type='hidden' name='list' value='$J'>
	<input type='hidden' name='indices'
	       id='upload-indices$J'>
	<input type='hidden' name='lengths'
	       id='upload-lengths$J'>
	<input type="hidden" name="MAX_FILE_SIZE"
	       value="$epm_upload_maxsize">
	<label>
	<button type='button'
		onclick='UPLOAD(event,"$J")'
		title='$upload_title'>
	Upload Description
	</button>
	<strong>:</strong>
	<input type="file" name="uploaded_file"
	       title="$upload_file_title">
	</label>
	</form>

	<br>

	<strong>Change To New List:</strong>
	<input type="text"
	       id='new$J'
	       size="24"
               placeholder="New Problem List Name"
               title="New Problem List Name"
	       onkeydown='NEW(event,"$J")'>

	<br>

	<input type='button'
	       onclick='CHANGE_LIST("$J")'
	       value='Change To'
	       title='Change Problem List'>
	<strong>:</strong>
	<select id='change$J'
	        title='New Problem List to Edit'>
	$options
	</select>
	<br>
EOT;
	if ( $writable == 'yes' )
	{
	    echo <<<EOT
	    <button type='button'
	            onclick='SUBMIT("save","$J")'>
	    SAVE</button>
	    <button type='button'
	            onclick='SUBMIT("finish","$J")'>
	    FINISH</button>
	    <button type='button'
	            onclick='SUBMIT("reset","$J")'>
	    RESET</button>
	    <button type='button'
	            onclick='SUBMIT("cancel","$J")'>
	    CANCEL</button>
	    <button type='button'
	            onclick='SUBMIT("delete","$J")'>
	    DELETE</button>
EOT;
	}
	echo <<<EOT
	</div>
	<div id='list$J' data-writable='$writable'>
	<div style='text-align:center'
	     ondrop='DROP(event)'
	     ondragover='ALLOWDROP(event)'>
	<strong class='list-name'>$pname</strong>
	</div>

	$lines
	</div>
	</div>
EOT;
    }

    echo <<<EOT
    <script>
    var names = ['{$names[0]}','{$names[1]}'];
    var lengths = ['{$lengths[0]}','{$lengths[1]}'];
    var ops = ['KEEP','KEEP'];
    </script>
EOT;

?>

<script>
let submit_form = document.getElementById
    ( 'submit-form' );
let op_in = document.getElementById ( 'op' );
let list_in = document.getElementById ( 'list' );
let lengths_in = document.getElementById ( 'lengths' );
let indices_in = document.getElementById ( 'indices' );
let name_in = document.getElementById ( 'name' );

let on = 'black';
let off = 'transparent';

for ( var J = 0; J <= 1; ++ J )
{
    let list = document.getElementById ( 'list' + J );
    let first = list.firstElementChild;
    var next = first.nextElementSibling;
    if ( next == null ) continue;
    for ( I = 0; I < lengths[J]; ++ I )
    {
        let tbody = next.firstElementChild;
        let tr = tbody.firstElementChild;
        let td = tr.firstElementChild;
        let span = td.firstElementChild;
	span.style.backgroundColor = on;
	next = next.nextElementSibling;
    }
}

function SPAN ( table )
{
    let tbody = table.firstElementChild;
    let tr = tbody.firstElementChild;
    let td = tr.firstElementChild;
    let span = td.firstElementChild;
    return span;
}

var lengths = [0,0];
var indices = ['',''];
function COMPUTE_INDICES()
{
    for ( var J = 0; J <= 1; ++ J )
    {
        let list = document.getElementById
	    ( 'list' + J );
	let first = list.firstElementChild;
	var next = first.nextElementSibling;
	if ( next == null ) continue;

	var ilist = [];
	var length = 0;
	while ( next != null )
	{
	    ilist.push ( next.id );
	    let span = SPAN ( next );
	    if ( span.style.backgroundColor == on )
	        ++ length;
	    next = next.nextElementSibling;
	}
	lengths[J] = length;
	indices[J] = ilist.join ( ':' );
    }
}

function SUBMIT(op,list,name = '')
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
    if ( event.keyCode === 13 )
    {
	event.preventDefault();
        let new_in = document.getElementById
	    ( 'new' + J );
	SUBMIT ( 'new', J, new_in.value );
    }
}

function CHANGE_LIST ( J )
{
    let ch = document.getElementById ( 'change' + J );
    SUBMIT ( 'change', J, ch.value );
}

function CHECK ( event )
{
    event.preventDefault();
    let span = event.currentTarget;
    let td = span.parentElement;
    let tr = td.parentElement;
    let tbody = tr.parentElement;
    let table = tbody.parentElement;
    let div = table.parentElement;
    if ( span.style.backgroundColor == on )
    {
	span.style.backgroundColor = off;
	var next = table.nextElementSibling;
	while ( next != null
		&&
		   SPAN(next).style.backgroundColor
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
	span.style.backgroundColor = on;
	var previous = table.previousElementSibling;
	while ( previous != div.firstElementChild
	        &&
		   SPAN(previous).style.backgroundColor
		!= on )
	    previous = previous.previousElementSibling;
	if ( previous != table.previousElementSibling )
	{
	    let next = previous.nextElementSibling;
	    div.insertBefore ( table, next );
	}
    }
}

var dragsrc = null;
    // Source (start) table of drag.
    // We cannot use id because of DUP.

function DRAGSTART ( event )
{
    let table = event.currentTarget;
    let id = table.id;
    let div = table.parentElement;
    event.dataTransfer.setData ( "xx", "xx" );
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
    let src = dragsrc;
    let srcdiv = dragsrc.parentElement;
    let srcwritable = srcdiv.dataset.writable;
    if ( srcwritable == 'no' )
        src = src.cloneNode ( true );
    let next = target.nextElementSibling;
    if ( next == null )
    {
        div.appendChild ( src );
	if ( target != div.firstElementChild
	     &&
	     SPAN(target).style.backgroundColor != on )
	    SPAN(src).style.backgroundColor = off;
	}
    else
    {
	div.insertBefore ( src, next );
	if ( SPAN(next).style.backgroundColor == on )
	    SPAN(src).style.backgroundColor = on;
	else if ( target != div.firstElementChild
	          &&
	             SPAN(target).style.backgroundColor
		  != on )
	    SPAN(src).style.backgroundColor = off;
    }
}
function DUP ( event )
{
    let td = event.currentTarget;
    let tr = td.parentElement;
    let tbody = tr.parentElement;
    let table = tbody.parentElement;
    let div = table.parentElement;
    let writable = div.dataset.writable;
    if ( writable == 'no' )
    {
        event.preventDefault();
	return;
    }
    let new_table = table.cloneNode ( true );
    div.insertBefore ( new_table, table );
}

</script>

</body>
</html>
