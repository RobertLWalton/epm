<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed May 13 13:41:25 EDT 2020

    // Maintains problem lists.

    // See project.php for directory and files.

    // Session Data
    // ------- ----

    // Session data is in EPM_LIST as follows:
    //
    //     EPM_LIST ID
    //	   	32 hex digit random number used to
    //		verify POSTs to this page.
    //
    //	   EPM_LIST NAMES
    //		[name1,name2] where nameI is in the
    //		format PROJECT:BASENAME or is NULL
    //		for no-list.
    //
    //     EPM_LIST ELEMENTS
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
    // following values.  Here space separation items
    // must be a single space character.
    //
    //	    op='op1 op2' where opI is one of:
    //
    //		KEEP	Keep list as is.
    //		SAVE	Save used portion of list in
    //			list file.
    //		FINISH	Ditto but set list name to NULL.
    //		CANCEL	Just set list name to NULL.
    //		RESET	Reset list to initial contents.
    //
    //	     elements='e1 e2'
    //		where eI is the indices in EPM_LIST
    //		ELEMENTS of list I, with the indices
    //		separated by `:'.
    //
    //	     length='len1 len2'
    //		The number of elements actually in list
    //		I is lenI; the remaining elements have
    //		been removed.
    //
    //	     names='name1 name2'
    //		New values for EPM_LIST NAMES.  These
    //		are to be installed after opI is
    //		executed, and should be the same as the
    //		old names unless opI is FINISH or
    //		CANCEL.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";
    require "$epm_home/include/epm_template.php";
        // This last is only needed by merge function.

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( $method == 'GET' )
        $_SESSION['EPM_PROJECT'] = [
	    'ID' => bin2hex ( random_bytes ( 16 ) ),
	    'OP' => NULL,
	    'LIST' => NULL ];

    $data = & $_SESSION['EPM_PROJECT'];
    $id = $data['ID'];
    $op = $data['OP'];
    $list = $data['LIST'];

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
    $delete_list = NULL;
        // Set to list to be deleted.  Causes delete
	// OK question.
    $compile_next = false;
    	// Set to cause first element of EPM_PROJECT
	// CHECKED-PROBLEMS to be compiled.

    // Return a list whose elements have the form:
    //
    //		[TIME - PROBLEM]
    //
    // for all PROBLEMs users/UID/PROBLEM where TIME
    // is the modification time of the problem +changes+
    // file, or is the current time if there is no
    // such file.  Sort by TIME.
    //
    function problems_to_edit_list()
    {
	global $epm_data, $uid, $epm_name_re,
	       $epm_time_format;

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

	    $g = "$f/$problem/+changes+";
	    $time = @filemtime ( "$epm_data/$g" );
	    if ( $time === false ) $time = time();
	    $pmap[$problem] = $time;
	}
	arsort ( $pmap, SORT_NUMERIC );
	$list = [];
	foreach ( $pmap as $problem => $time )
	    $list[] = [strftime ( $epm_time_format,
	                          $time ),
		       '-', $problem];
	return $list;
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
	    make_new_list ( $fname, $errors );
	        // This will check that $fname is
		// well formed EPM file name base.
	    if ( count ( $errors ) > $errors_size )
	        return false;
	    $warnings[] = "created list $fbase which"
	                . " does not previously exist";
	}

	write_list_description ( $f, $dsc, $errors );
	if ( count ( $errors ) > $errors_size )
	    return false;
	else
	    return ( "-:$fbase" );
    }

    // Given a list of elements of the form
    //
    //		[TIME PROJECT PROBLEM]
    //
    // where PROJECT may be '-', add the elements to the
    // $elements list and return a string whose segments
    // are HTML rows of the form:
    //
    //		<tr class='edit-row'>
    //		<td></td>
    //		<td data-index='I' class='edit-name'>
    //		PROJECT PROBLEM TIME
    //		</td>
    //		</tr>
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
	          <tr>
		  <td><button type='button'
		              onclick='DELETE(this)'>
		      &Chi;</button></td>
		  <td data-index='$I' class='edit-name'
		       onclick='DUP(this)'>
		  $project $problem $time
		  </td>
		  </tr>
EOT;
	}
	return $r;
    }

    function execute_edit ( & $errors )
    {
        global $data, $list;

	if ( ! isset ( $_POST['list'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	if ( ! isset ( $_POST['stack'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	$elements = $data['ELEMENTS'];
	$bound = count ( $elements );

	$fname = listname_to_filename ( $list );
	$sname = listname_to_filename ( '+stack+' );

	$indices = $_POST['stack'];
	$indices = ( $indices == '' ? [] :
	             explode ( ':', $indices ) );
	    // explode ( ':', '' ) === ['']
	$slist = [];
	foreach ( $indices as $index )
	{
	    if ( $index < 0 || $index >= $bound )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $slist[] = $elements[$index];
	}

	if ( isset ( $fname ) )
	{
	    $indices = $_POST['list'];
	    $indices = ( $indices == '' ? [] :
			 explode ( ':', $indices ) );
	    $flist = [];
	    foreach ( $indices as $index )
	    {
	        if ( $index < 0 || $index >= $bound )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		$flist[] = $elements[$index];
	    }
	    write_file_list ( $fname, $flist );
	        // Don't write until all UNACCEPTABLE
		// HTTP POST checks done.
	}

	write_file_list ( $sname, $slist );
    }

    if ( $method == 'POST' )
    {
        if ( ! isset ( $_POST['ops'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['elements'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['lengths'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['names'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.op th {
        font-size: var(--large-font-size);
	text-align: left;
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
    span.problem-checkbox {
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

<?php 

    if ( isset ( $delete_list ) )
    {
	list ( $project, $basename ) =
	    explode ( ':', $delete_list );
	if ( $project == '-' )
	    $project = '<i>Your</i>';
	if ( $basename == '-' )
	    $basename = '<i>Problems</i>';
	echo <<<EOT
	<div class='errors'>
	<strong>Do you really want to delete
	        $project $basename?</strong>
	<form action='project.php' method='POST'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit' name='delete-list'
	       value='YES'>
	<input type='submit' name='delete-list'
	       value='NO'>
	</form>
	<br></div>
EOT;
    }
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

    $project_help = HELP ( 'list-page' );
    echo <<<EOT
    <div class='manage'>
    <form>
    <table style='width:100%'>
    <tr>
    <td>
    <label>
    <strong>User:</strong>
    <input type='submit' value='$email'
	   formaction='user.php'
	   formmethod='GET'
           title='Click to See User Profile'>
    </label>
    </td>
    <td>
    <div id='done-response' style='display:none'>
    <strong>Done!</strong>
    <button type='submit'
	    formaction='project.php'
	    formmethod='GET'>
	    Continue</button>
    <pre>    </pre>
    </div>
    <div id='check-proposed-display'
         style='display:none'>
    <span class='problem-checkbox'
	  id='check-proposed'
	  onclick='CHECK(this)'>&nbsp;</span>
    <strong>Check Proposed Actions</strong>
    <pre>    </pre>
    </div>
    <strong>Go To</strong>
    <button type='submit'
	    formaction='problem.php'
	    formmethod='GET'>
	    Problem</button>
    <strong>Page</strong>
    </td>
    <td>
    </td><td style='text-align:right'>
    $project_help</td>
    </tr>
    </table>
    </form>
    </div>
EOT;
    if ( $op == 'edit' )
    {
	$edit_help = HELP ( 'problem-lists' );

	$data['ELEMENTS'] = [];
	$elements = & $data['ELEMENTS'];

	$list_rows = list_to_edit_rows
	    ( $elements, listname_to_list ( $list ) );
	$is_read_only = 'false';
	$delete_list_ok = false;
	$description = '';

	list ( $project, $basename ) =
	    explode ( ':', $list );
	if ( $project == '-' )
	    $project = '<i>Your</i>';
	if ( $basename == '-' )
	{
	    $basename =
		'<i>Problems</i> (read-only)';
	    $is_read_only = 'true';
	}
	else
	{
	    $delete_list_ok = true;
	    $description = read_list_description
		( listname_to_filename ( $list ) );
	}
	$name = "$project $basename";

	$stack_rows = list_to_edit_rows
	    ( $elements,
	      listname_to_list ( '+stack+' ) );

	$options = favorites_to_options ( 'pull|push' );

	$finish_title = 'Save Changes and Return to'
	              . ' Project Page';
	$cancel_title = 'DISCARD Changes and Return to'
	              . ' Project Page';
	$clear_title = 'Move Overstrikes to End of'
	             . ' Stack and Remove Duplicate'
		     . ' Overstrikes';
	$change_to_title = 'Save Changes and Change the'
	                 . ' List Being Edited';

	echo <<<EOT
	<div class='edit-list'>
	<div style='display:inline'>

	<form method='POST' action='project.php'
	      id='submit-form'>
	<input type='hidden' name='ID' value='$id'>
	<input type='hidden' name='update' id='update'>
	<input type='hidden' name='new-list'
	       id='new-list'>
	<input type='hidden' name='basename'
	       id='basename'>
	<input type='hidden' name='stack' value=''
	       id='stack-args'>
	<input type='hidden' name='list' value=''
	       id='list-args'>
	</form>

	<input type='button'
	       onclick='UPDATE_EDIT ( "update" )'
	       value='Save'
	       title='Save Changes'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "done" )'
	       value='Finish'
	       title='$finish_title'>
	<form method='POST' action='project.php'
	      style='display:inline'>
	<input type='hidden' name='ID' value='$id'>
	<input type='submit'
	       name='cancel'
	       value='Cancel'
	       title='$cancel_title'>
	</form>
	<input type='button'
	       onclick='CLEAR_EDIT()'
	       value='Clear Strikes'
	       title='$clear_title'>
	<input type='button'
	       onclick='UPDATE_EDIT ( "change-list" )'
	       value='Change To'
	       title='$change_to_title'>
	<strong>:</strong>
	<select id='change-list'
	        title='New Problem List to Edit'>
	$options
	</select>
	<b>Create New List:</b>
	<input type="text"
	       id='created-basename'
	       size="24"
               placeholder="New Problem List Name"
               title="New Problem List Name"
	       onkeydown='CREATE_NEW_LIST(event)'>
EOT;
	if ( $delete_list_ok )
	    echo <<<EOT
	    <form method='POST' action='project.php'
		  style='display:inline'>
	    <input type='hidden' name='ID' value='$id'>
	    <button type='submit'
		    name='delete-list'
		    value='Delete List'
		    title='Delete List Being Edited'>
	    Delete $name</button>
	    </form>
EOT;
	$list_head_class = "class='edit-row'";
	if ( $is_read_only )
	    $list_head_class = "";
	echo <<<EOT
	</div>
	<div style='display:inline;float:right'>
	$edit_help
	</div>
	</div>

	<div>
	<table id='stack-table'>
	<tr class='edit-row'>
	<th colspan=2><i>Stack</i></th></tr>
	$stack_rows
	</table>
	<table id='list-table'>
	<tr $list_head_class>
	<th colspan=2>$name</th></tr>
	$list_rows
	</table>
	</div>
EOT;
	if ( $description != '' )
	    echo <<<EOT
	    <div style='display:table;clear:both'>
	    </div>
	    <div class='list-description-header'>
	    <strong>$name Description</strong>
	    </div>
	    <div class='list-description'>
	    $description
	    </div>
EOT;

	echo <<<EOT
	<script>

	let is_read_only = $is_read_only;

	let stack_table = document.getElementById
	    ( 'stack-table' );
	let stack_rows = stack_table.rows;
	let list_table = document.getElementById
	    ( 'list-table' );
	let list_rows = list_table.rows;

	let delete_button =
	    "<button type='button'" +
		   " onclick='DELETE(this)'>" +
	    "&Chi;</button>";

	if ( is_read_only )
	    for ( var i = 1; i < list_rows.length;
	                     ++ i )
		list_rows[i].children[0].style.display =
		    'none';
	else
	    for ( var i = 0; i < list_rows.length;
	                     ++ i )
	    {
	        var tr = list_rows[i];
		tr.setAttribute ( 'class', 'edit-row' );
	    }

	function make_draggable ( element )
	{
	    element.setAttribute
	        ( 'draggable', 'true' );

	    // Mozilla but not Chrome requires the
	    // following:
	    //
	    element.addEventListener ( "dragstart",
		function(event) {
		    event.dataTransfer.setData
		        ('text/plain',null);
		}, false );
	}

	for ( var i = 1; i < list_rows.length;
			 ++ i )
	    make_draggable ( list_rows[i] );

	for ( var i = 0; i < stack_rows.length;
			 ++ i )
	{
	    var tr = stack_rows[i];
	    tr.setAttribute ( 'class', 'edit-row' );
	    if ( i != 0 )
		make_draggable ( tr );
	}

	var drag_start = null;
	document.addEventListener ( "drag",
	    function(event) {}, false );
	document.addEventListener ( "dragstart",
	    function(event) {
		drag_start = event.target;
	    }, false );
	document.addEventListener ( "dragover",
	    function(event) {
	        event.preventDefault();
	    }, false );
	document.addEventListener ( "drop",
	    function(event) {
	        event.preventDefault();
		var t = event.target;
		while ( t != undefined
		        &&
			t.className != 'edit-row' )
		    t = t.parentElement;

		if ( t != undefined )
		{
		    let drag_table =
		        drag_start.parentElement
				  .parentElement;
		    let tr =
		        drag_start.cloneNode ( true );
		    let des_table =
		        t.parentElement.parentElement;
		    let des_rows = des_table.rows;
		    let des_body = des_table.tBodies[0];
		    let des_index = t.rowIndex + 1;

		    if ( drag_table == stack_table
		         ||
			 ! is_read_only )
		        drag_table.deleteRow
			    ( drag_start.rowIndex );
		    if ( des_index < des_rows.length )
		        des_body.insertBefore
			    ( tr, des_rows[des_index] );
		    else
		        des_body.appendChild ( tr );
		    tr.children[0].innerHTML =
		        delete_button;
		    tr.children[0].style.display =
		        'inline';
		    tr.children[1].style
		                  .textDecoration =
		        'none';
		    tr.setAttribute
		        ( 'class', 'edit-row' );
		    make_draggable ( tr );
		}
	    }, false );


	function DELETE ( button )
	{
	    let tr = button.parentElement.parentElement;
	    let text = tr.children[1];
	    if (    text.style.textDecoration
		 == 'line-through' )
		text.style.textDecoration = 'none';
	    else
		text.style.textDecoration =
		    'line-through';
	}

	function DUP ( td )
	{
	    let tr = td.parentElement;
	    let tbody = tr.parentElement;
	    let table = tbody.parentElement;
	    if ( table == list_table
	         &&
		 is_read_only )
	        return;

	    let new_tr = tr.cloneNode ( true );
	    tbody.insertBefore ( new_tr, tr );
	    new_tr.children[1].style.textDecoration =
	        'none';
	    make_draggable ( new_tr );
	}

	function CLEAR_EDIT ()
	{
	    var list = [];
	    var index = [];
	    for ( var i = 1; i < list_rows.length; )
	    {
		var tr = list_rows[i];
		var text = tr.children[1];
		var tbody = tr.parentElement;
		if (    text.style.textDecoration
		     == 'line-through' )
		     list.push
		         ( tbody.removeChild ( tr ) );
		else
		{
		    let k = text.dataset.index;
		    index[k] = true;
		    ++ i;
		}
	    }
	    for ( var i = 1; i < stack_rows.length; )
	    {
		var tr = stack_rows[i];
		var text = tr.children[1];
		var tbody = tr.parentElement;
		if (    text.style.textDecoration
		     == 'line-through' )
		     list.push
		         ( tbody.removeChild ( tr ) );
		else
		{
		    let k = text.dataset.index;
		    index[k] = true;
		    ++ i;
		}
	    }
	    let stack_body = stack_table.tBodies[0];
	    for ( var i = 0; i < list.length; ++ i )
	    {
		let k = list[i].children[1]
		               .dataset.index;
		if ( index[k] != true )
		{
		    stack_body.appendChild ( list[i] );
		    index[k] = true;
		}
	    }
	}

	// Send `submit' post with stack and list ids.
	//
	function UPDATE_EDIT ( kind )
	{
	    let submit_form = document.getElementById
		( 'submit-form' );
	    let update = document.getElementById
		( 'update' );
	    let stack_args = document.getElementById
		( 'stack-args' );
	    let list_args = document.getElementById
		( 'list-args' );
	    let new_list = document.getElementById
		( 'new-list' );
	    let change_list = document.getElementById
		( 'change-list' );

	    update.value = kind;
	    new_list.value = change_list.value;

	    var stack = [];
	    var indices = [];
	    for ( var i = 1; i < stack_rows.length;
	                     ++ i )
	    {
	        let tr = stack_rows[i];
		let text = tr.children[1];
		if (    text.style.textDecoration
		     == 'line-through' )
		    continue;
		let index = text.dataset.index;
		if ( indices[index] == true )
		    continue;
		stack.push ( index );
		indices[index] = true;
	    }
	    stack_args.value = stack.join(':');

	    if ( ! is_read_only )
	    {
		var list = [];
		indices = [];
		for ( var i = 1; i < list_rows.length;
				 ++ i )
		{
		    let tr = list_rows[i];
		    let text = tr.children[1];
		    if (    text.style.textDecoration
			 == 'line-through' )
			continue;
		    let index = text.dataset.index;
		    if ( indices[index] == true )
			continue;
		    list.push ( index );
		    indices[index] = true;
		}
		list_args.value = list.join(':');
	    }
	    submit_form.submit();
	}

	function CREATE_NEW_LIST ( event )
	{
	    if ( event.keyCode === 13 )
	    {
	        event.preventDefault();
		let created_basename =
		    document.getElementById
			( 'created-basename' );
		let basename =
		    document.getElementById
			( 'basename' );
		basename.value =
		    created_basename.value;
		UPDATE_EDIT ( 'create-list' );

	    }
	}

	</script>
EOT;
    }

?>

</body>
</html>
