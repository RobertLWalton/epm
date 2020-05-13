<?php

    // File:	list.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Wed May 13 17:31:16 EDT 2020

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
    //		SAVE	Save used portion of list in
    //			list file.
    //		RESET	Reset list to file contents.
    //		DELETE	Delete file.
    //		KEEP	Keep list as is.
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
    //		executed.  NameI is ignored if opI is
    //		RESET.  The value ':' means NULL.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( $method == 'GET' )
        $_SESSION['EPM_LIST'] = [
	    'ID' => bin2hex ( random_bytes ( 16 ) ),
	    'NAMES' => [NULL,NULL],
	    'ELEMENTS' => [] ];

    $data = & $_SESSION['EPM_LIST'];
    $id = $data['ID'];
    $names = $data['NAMES'];

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
    //                 ondragstart='DRAGSTART(event,"I")'
    //                 onclick='DUP(event)'>
    //		<tr>
    //		<td style='width:10%;text-align:left'>
    //		<span class='checkbox'
    //		      onclick='CHECK(event,"I")'>
    //		&nbsp;
    //		</span></td>
    //		<td style='width:30%;text-align:center'>
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
    	           ondragstart='DRAGSTART(event,"$I")'
    	           onclick='DUP(event)'>
    	    <tr>
    	    <td style='width:10%;text-align:left'>
    	    <span class='checkbox'
    	          onclick='CHECK(event,"$I")'>
    	    &nbsp;
    	    </span></td>
    	    <td style='width:80%;text-align:center'>
    	    $project $problem $time</td>
    	    </tr></table>
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
    $options = favorites_to_options ( 'pull|push' );
    $data['ELEMENTS'] = [];
    $elements = & $data['ELEMENTS'];
    foreach ( [0,1] as $J )
    {
        $name = $names[$J];
	echo <<<EOT
	<div class='list$J' id='list$J'>
	<div style='text-align:center'>
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
	       title='$change_to_title'>
	<strong>:</strong>
	<select id='change$J'
	        title='New Problem List to Edit'>
	$options
	</select>
	<br>
EOT;
	if ( ! isset ( $name ) )
	    echo <<<EOT
	    <strong>No List Selected</strong>
EOT;
	else
	{
	    $lines = list_to_element_rows
	        ( $elements,
		  listname_to_list ( $name ) );
	    echo <<<EOT
	    <strong>$name:</strong>
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

?>

</body>
</html>
