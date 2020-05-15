<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri May 15 17:38:16 EDT 2020

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
    //		[name1,name2] where nameI is in the
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
    // following values.
    //
    //	     ops='op1;op2' where opI is one of:
    //
    //		SAVE	Save used portion of list in
    //			list file.  Keep list if
    //			nameI is current list name,
    //			or change if otherwise.
    //		KEEP	Ditto but do not save in file.
    //		CHANGE	Change list to file contents
    //			of nameI (which might be
    //			current list or '').
    //		DELETE	Delete file of current list.
    //			nameI must match current list.
    //
    //	     indices='i1;i2'
    //		where iI is the indices in EPM_DATA
    //		ELEMENTS of list I, with the indices
    //		separated by `:', and ':' denoting the
    //		empty list.  Not used by RESET or
    //		DELETE.
    //
    //	     lengths='len1;len2'
    //		The number of elements actually in list
    //		I is lenI; the remaining elements have
    //		been removed.  Not used by RESET or
    //		DELETE.
    //
    //	     names='name1;name2'
    //		New values for EPM_DATA NAMES.  These
    //		are to be installed after opI is
    //		executed.  NameI is '' to mean no-list.

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
    $names = $data['NAMES'];
    $elements = $data['ELEMENTS'];

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
        // Lists to be given to list_to_edit_rows.
	// If $lists[J] not set by POST, will be set
	// according to $names[J].

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

    if ( $method == 'POST' )
    {
        if ( ! isset ( $_POST['ops'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['indices'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['lengths'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( ! isset ( $_POST['names'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
	$ops = explode ( ';', $_POST['ops'] );
	$indices = explode ( ';', $_POST['indices'] );
	$lengths = explode ( ';', $_POST['lengths'] );
	$new_names = explode
	    ( ';', $_POST['names'] );
	if ( $new_names[0] == $new_names[1]
	     &&
	     $new_names[0] != '' )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	// First check ops, collect lists for SAVE and
	// KEEP, and check $indices and lengths in these
	// cases, and lastly check $new_names for
	// SAVE.
	//
	foreach ( [0,1] as $J )
	{
	    if (    $ops[$J] == 'SAVE'
	         || $ops[$J] == 'KEEP' )
	    {
	        $lists[$J] = [];
	        $list = & $lists[$J];
		if ( $indices[$J] == '' )
		    $indices = [];
		else
		    $indices = explode
		        ( ':', $indices[$J] );
		foreach ( $indices as $I )
		{
		    if ( ! preg_match
		               ( '/^\d+$/', $I ) )
			exit
			  ( 'UNACCEPTABLE HTTP POST' );
		    $list[] = $elements[$I];
		}
		if ( ! preg_match
			   ( '/^\d+$/', $lengths[$J] ) )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		if ( $lengths[$J] > count ( $list ) )
		    exit ( 'UNACCEPTABLE HTTP POST' );
	    }

	    if ( $ops[$J] == 'SAVE'
	         &&
		 $names[$J] == '' )
		exit ( 'UNACCEPTABLE HTTP POST' );

	    $new_name = $new_names[$J];
	    if ( $new_name != ''
		 &&
		 ! isset ( $fmap[$new_name] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	}

	foreach ( [0,1] as $J )
	{
	    if ( $ops[$J] == 'SAVE' )
	    {
	        write_file_list
		    ( filename_from_listname
		          ( $names[$J], $lists[$J] ) );
	    }
	    $names[$J] = $new_names[$J];
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
    <input type='hidden' name='ops' id='ops'
           value='KEEP;KEEP'>
    <input type='hidden' name='lengths' id='lengths'
           value='0;0'>
    <input type='hidden' name='indices' id='indices'
           value=';'>
    <input type='hidden' name='names' id='names'
           value=';'>
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
    </td>
    <td>
    </td><td style='text-align:right'>
    $list_help</td>
    </tr>
    </table>
    </form>
    </div>
EOT;
    $data['ELEMENTS'] = [];
    $elements = & $data['ELEMENTS'];
    $options = list_to_options ( $favorites, $names );
    foreach ( [0,1] as $J )
    {
        $name = $names[$J];
	$writable = 'no';
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
	}

	echo <<<EOT
	<div class='list$J' id='list$J'
	     data-writable='$writable'>

	<div style='text-align:center'
	     ondrop='DROP(event)'
	     ondragover='ALLOWDROP(event)'>

	<strong>Change To New List:</strong>
	<input type="text"
	       id='new$J'
	       size="24"
               placeholder="New Problem List Name"
               title="New Problem List Name"
	       onkeydown='NEW(event)'>
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
	if ( $name == '' )
	{
	    echo <<<EOT
	    <strong>No List Selected</strong>
EOT;
	}
	elseif ( $writable == 'no' )
	    echo <<<EOT
	    <strong>$project $basename</strong>
EOT;
	else
	{
	    echo <<<EOT
	    <strong>$project $basename:</strong>
	    <pre>  </pre>
	    <button type='button'
	            onclick='SAVE("$J")'>
	    SAVE</button>
	    <button type='button'
	            onclick='FINISH("$J")'>
	    FINISH</button>
	    <button type='button'
	            onclick='RESET("$J")'>
	    RESET</button>
	    <button type='button'
	            onclick='CANCEL("$J")'>
	    CANCEL</button>
	    <button type='button'
	            onclick='DELETE("$J")'>
	    DELETE</button>
EOT;
	}
	echo <<<EOT
	</div>

	$lines
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
let submit_form = document.getElementById ( 'submit-form' );
let ops_input = document.getElementById ( 'ops' );
let lengths_input = document.getElementById
    ( 'lengths' );
let indices_input = document.getElementById
    ( 'indices' );
let names_input = document.getElementById ( 'names' );

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

function SUBMIT()
{
    lengths = [0,0];
    var indices = ['',''];
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
    ops_input.value = ops.join ( ';' );
    indices_input.value = indices.join ( ';' );
    lengths_input.value = lengths.join ( ';' );
    names_input.value = names.join ( ';' );
    submit_form.submit();
}
function CHANGE_LIST ( J )
{
    let ch = document.getElementById ( 'change' + J );
    names[J] = ch.value;
    ops[J] = 'RESET';
    SUBMIT();
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
