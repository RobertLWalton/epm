<?php

    // File:	favorites.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Jun  6 14:16:54 EDT 2020

    // Maintains favorites list of problem lists.

    // See doc/epm_admin.pdf for directory and file
    // formats.

    // Session Data
    // ------- ----

    // Session data is in EPM_DATA as follows:
    //
    //	   EPM_DATA LIST
    //		List whose elements correspond to lines
    //		that may be included in the favorites
    //		list.  Each element has the form:
    //
    //		    [TIME PROJECT BASENAME]
    //
    //		naming a list and giving its current
    //		modification time, for problem lists
    //		the modification time is that of the
    //		list directory, either projects/PROJECT
    //		or users/UID.

    // POST:
    //
    // Each post may save the +favorites+ file and
    // has the following values:
    //
    //	   indices=INDICES
    //		Here INDICES are the indices in
    //		EPM_DATA LIST of the elements to be
    //		included in +favorites+, in order,
    //		separated by ':'s, with '' denoting
    //		the empty list.
    //
    //	    op=OPERATION
    //		OPERATION is one of:
    //
    //		save	Update +favorites+ from INDICES
    //
    //		reset	Just reload page as per GET.

    $epm_page_type = '+main+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' )
        $_SESSION['EPM_DATA'] = [
	    'LIST' => [] ];
    elseif ( ! isset ( $_SESSION['EPM_DATA'] ) )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $data = & $_SESSION['EPM_DATA'];
    $list = & $data['LIST'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    if ( $epm_method == 'POST' )
    {
        if ( !isset ( $_POST['op'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( !isset ( $_POST['indices'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

        $op = $_POST['op'];
	if ( ! in_array ( $op, ['save','reset'],
			       true ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	if ( $op == 'save' )
	{
	    $list = $data['LIST'];
	    $count = count ( $list );
	    $flist = [];
	    $indices = $_POST['indices'];
	    $favs = ( $indices == '' ? [] :
	              explode ( ':', $indices ) );
	    foreach ( $favs as $index )
	    {
		if ( $index == '' ) continue;
		if ( ! preg_match ( '/\d+/', $index ) )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		if ( $index >= $count )
		    exit ( 'UNACCEPTABLE HTTP POST' );
		$flist[] = $list[$index];
	    }
	    write_file_list
		( "users/$uid/+lists+/+favorites+",
		  $flist );
	}
    }

    // Build $inmap containing list of all lists
    // in the form PROJECT:BASENAME => TIME.  Lists
    // of problems with BASENAME = '-' are first.
    //

    $inmap = [];
    $time = strftime ( $epm_time_format );
    $inmap["-:-"] = $time;
    $projects = read_projects ( 'list|push|pull' );
    foreach ( $projects as $project )
        $inmap["$project:-"] = $time;

    // Add .list files in $dirname using $project
    // as project name.
    //
    function append_listnames
	    ( & $inmap, $project, $dirname )
    {
	global $epm_data, $epm_time_format;

        $fnames = @scandir ( "$epm_data/$dirname" );
	if ( $fnames === false ) return;
	foreach ( $fnames as $fname )
	{
	    $ext = pathinfo
	        ( $fname, PATHINFO_EXTENSION );
	    if ( $ext != 'list' ) continue;
	    $basename = pathinfo
	        ( $fname, PATHINFO_FILENAME );
	    $time = @filemtime
	        ( "$epm_data/$dirname/$fname" );
	    if ( $time === false )
	        $time = time();
	    $inmap["$project:$basename"] =
	        strftime ( $epm_time_format, $time );
	}
    }
    append_listnames
        ( $inmap, '-', "users/$uid/+lists+" );
    foreach ( $projects as $project )
        append_listnames
	    ( $inmap, $project,
	      "/projects/$project/+lists+" );

    $favorites = read_file_list
        ( "users/$uid/+lists+/+favorites+" );

    // Build $fmap containing list of all lists
    // in the form PROJECT:BASENAME => TIME.  The
    // first elements are taken from the +favorites+
    // file, excluding names of lists that no longer
    // exist.  There are $fcount such elements, and
    // these will be marked initially as being in the
    // `Favorites'.  TIMEs are mod times of the list
    // files, taken from $inmap.
    //
    $fmap = [];
    $fcount = 0;
    foreach ( $favorites as $e )
    {
        list ( $time, $project, $basename ) = $e;
	$key = "$project:$basename";
	if ( ! isset ( $inmap[$key] ) ) continue;
	$fmap[$key] = $inmap[$key];
	++ $fcount;
    }
    foreach ( $inmap as $key => $time )
    {
        if ( ! isset ( $fmap[$key] ) )
	    $fmap[$key] = $time;
    }

    $data['LIST'] = [];
    $list = & $data['LIST'];
    foreach ( $fmap as $key => $time )
    {
        list ( $project, $basename ) =
	    explode ( ':', $key );
	$list[] = [$time, $project, $basename];
    }

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    div.lists {
	background-color: white;
	margin: 0px;
    }
    div.favorites-title {
	background-color: var(--bg-violet);
	border-radius: var(--radius);
	text-align: center;
	margin: 0px;
	padding: var(--font-size) 0
	         var(--font-size) 0;
        font-size: var(--large-font-size);
    }
    div.list-line {
	background-color: var(--bg-orange);
	border: 1px solid black;
	border-radius: var(--radius);
	border-collapse: collapse;
    }
    table.list-line-header {
	text-align: center;
	width: 100%;
	padding: 2px;
        font-size: var(--large-font-size);
    }
    div.list-description {
	background-color: var(--bg-green);
	margin-left: var(--indent);
        font-size: var(--font-size);
    }
    div.list-description p, div.list-description pre {
        margin: 0px;
        padding: var(--pad);
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

    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>

    <tr id='not-edited' style='width:100%'>
    <form method='GET'>
    <td>
    <input type='hidden' name='id' value='$ID'>
    <label>
    <strong>User:</strong>
    <input type='submit' value='$email'
	   formaction='user.php'
           title='Click to See User Profile'>
    </label>
    </td>
    <td>
    <strong>Go To</strong>
    <button type='submit' formaction='project.php'>
    Project
    </button>
    <button type='submit' formaction='list.php'>
    Edit Lists
    </button>
    <strong>Page</strong>
    </td>
    </form>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("favorites-page")'>
	?</button>
    </td>
    </tr>

    <tr id='edited' style='width:100%;display:none'>
    <td>
    <input type='hidden' name='id' value='$ID'>
    <strong>User:&nbsp;$email</strong>
    </td>
    <td>
    <button type='button'
	    onclick='SUBMIT("save")'>
	    SAVE</button>
    <button type='button'
	    onclick='SUBMIT("reset")'>
	    RESET</button>
    <form method='POST' action='favorites.php'
	  id='submit-form'>
    <input type='hidden' name='id' value='$ID'>
    <input type='hidden' name='op' id='op'>
    <input type='hidden' name='indices'
	   id='indices'>
    </form>
    </td>
    <td>
    </td><td style='text-align:right'>
    <button type='button'
            onclick='HELP("favorites-page")'>
	?</button>
    </td>
    </tr>

    </table>
    </div>

    <div id='lists' class='lists'>

    <div class='favorites-title'
         ondrop='DROP(event)'
	 ondragover='ALLOWDROP(event)'>
         Favorites</div>
EOT;
    $off = 'transparent';
    $on = 'black';
    $c = -1;
    foreach ( $list as $e )
    {
        ++ $c;
        list ( $time, $project, $basename ) = $e;
	$listname = "$project:$basename";
	$filename = listname_to_filename ( $listname );
	$description = '';
	if ( isset ( $filename ) )
	    $description = read_list_description
	        ( $filename );
	if ( $project == '-' )
	    $project = '<i>Your</i>';
	if ( $basename == '-' )
	    $basename = '<i>Problems<i>';
	$time = substr ( $time, 0, 10 );

	$switch = ( $c < $fcount ? $on : $off );

	echo <<<EOT
	<div id='$c' class='list-line'
	     draggable='true'
	     ondragover='ALLOWDROP(event)'
             ondrop='DROP(event)'
	     ondragstart='DRAGSTART(event,$c)'>
	<table style='width:100%'
	       class='list-line-header'>
	<tr>
	<td style='width:10%;text-align:left'>
	<div class='checkbox'
	      onclick='CHECK(event,"$c")'
	      style='background-color:$switch'>
	      </div></td>
	<td style='width:80%;text-align:center'>
	    $project $basename $time</td>
	</tr></table>
EOT;
        if ( $description != '' )
	    echo <<<EOT
	    <div class='list-description'>
	    $description
	    </div>
EOT;

	echo <<<EOT
	</div>
EOT;
    }
    echo <<<EOT
    </div>

    <script>

    let edited = document.getElementById ( 'edited' );
    let not_edited = document.getElementById
	( 'not-edited' );
    let lists = document.getElementById ( 'lists' );
    let off = 'transparent';
    let on = 'black';

    let submit_form = document.getElementById
	( 'submit-form' );
    let op_in = document.getElementById ( 'op' );
    let indices_in = document.getElementById
        ( 'indices' );

function BOX ( div )
    {
	let table = div.firstElementChild;
	let tbody = table.firstElementChild;
	let tr = tbody.firstElementChild;
	let td = tr.firstElementChild;
	let checkbox = td.firstElementChild;
	return checkbox;
    }

    function DRAGSTART ( event, c )
    {
        event.dataTransfer.setData ( "id", c );
    }
    function ALLOWDROP ( event )
    {
        event.preventDefault();
    }
    function DROP ( event )
    {
        event.preventDefault();
	id = event.dataTransfer.getData ( "id" );
	let des = event.currentTarget;
	let src = document.getElementById ( id );
	let src_box = BOX ( src );
	let div = des.parentElement;

	let next = des.nextElementSibling;
	if ( next == null )
	{
	    div.appendChild ( src );
	    if ( des != div.firstElementChild
		 &&
		 BOX(des).style.backgroundColor != on )
		src_box.style.backgroundColor = off;
	    }
	else
	{
	    div.insertBefore ( src, next );
	    if ( BOX(next).style.backgroundColor == on )
		src_box.style.backgroundColor = on;
	    else if ( des != div.firstElementChild
		      &&
			 BOX(des).style.backgroundColor
		      != on )
		src_box.style.backgroundColor = off;
	}
	edited.style.display = 'table-row';
	not_edited.style.display = 'none';
    }

    function CHECK ( event, c )
    {
        event.preventDefault();
	let checkbox = event.currentTarget;
	let src = document.getElementById ( c );
	let div = src.parentElement;

	if ( checkbox.style.backgroundColor == on )
	{
	    checkbox.style.backgroundColor = off;
	    var next = src.nextElementSibling;
	    while ( next != null
		    &&
		       BOX(next).style.backgroundColor
		    == on )
		next = next.nextElementSibling;
	    if ( next != src.nextElementSibling )
	    {
		if ( next == null )
		    div.appendChild ( src );
		else
		    div.insertBefore ( src, next );
	    }
	}
	else
	{
	    checkbox.style.backgroundColor = on;
	    var previous = src.previousElementSibling;
	    while ( previous != div.firstElementChild
		    &&
		       BOX(previous).style
		                    .backgroundColor
		    != on )
		previous =
		    previous.previousElementSibling;
	    if (    previous
	         != src.previousElementSibling )
	    {
		let next = previous.nextElementSibling;
		div.insertBefore ( src, next );
	    }
	}

	edited.style.display = 'table-row';
	not_edited.style.display = 'none';
    }

    function SUBMIT ( op )
    {
	var list = [];
	for ( var i = 1; i < lists.children.length;
	                 ++ i )
	{
	    let div = lists.children[i];
	    let color = BOX(div).style.backgroundColor;
	    if ( color == on )
	        list.push ( div.id );
	    else
	        break;
	}
	indices_in.value = list.join ( ':' );
        op_in.value = op;
	submit_form.submit();
    }

    </script>
EOT;

?>

</body>
</html>
