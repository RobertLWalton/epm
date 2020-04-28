<?php

    // File:	favorites.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Apr 28 16:55:53 EDT 2020

    // Edits +favorites+ list.  See project.php for
    // file formats.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];

    require "$epm_home/include/epm_list.php";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    if ( $method == 'GET' )
        $_SESSION['EPM_FAVORITES'] = [];
    elseif ( ! isset ( $_SESSION['EPM_FAVORITES'] ) )
        exit ( 'UNACCEPTABLE HTTP POST' );
    $data = & $_SESSION['EPM_FAVORITES'];

    if ( $method == 'GET' )
        /* Do nothing */;
    elseif ( ! isset ( $_POST['ID'] ) )
        exit ( 'UNACCEPTABLE HTTP POST' );
    elseif ( $_POST['ID'] != $data['ID'] )
        exit ( 'UNACCEPTABLE HTTP POST' );

    $data['ID'] = bin2hex ( random_bytes ( 16 ) );
    $id = $data['ID'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    if ( $method == 'POST' )
    {
        if ( !isset ( $_POST['goto'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );
        if ( !isset ( $_POST['indices'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

        $goto = $_POST['goto'];
	if ( $goto == 'cancel' )
	{
	    header ( 'Location: /page/project.php' );
	    exit;
	}
	$list = $data['LIST'];
	$count = count ( $list );
	$flist = [];
	$favs = explode ( ',', $_POST['favorites'] );
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
	    ( "users/$uid/+indices+/+favorites+",
	      $flist );
    }

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
	    if ( $ext != 'index' ) continue;
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

    // Build $inmap containing list of all lists
    // in the form PROJECT:BASENAME => TIME.  Lists
    // of problems with BASENAME = '-' are first.
    //

    $inmap = [];
    $time = strftime ( $epm_time_format );
    $inmap["-:-"] = $time;
    $projects = read_projects ( 'index|push|pull' );
    foreach ( $projects as $project )
        $inmap["$project:-"] = $time;
    append_listnames
        ( $inmap, '-', "users/$uid/+indices+" );
    foreach ( $projects as $project )
        append_listnames
	    ( $inmap, $project,
	      "/projects/$project/+indices+" );

    $favorites = read_file_list
        ( "users/$uid/+indices+/+favorites+" );

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
<style>
    @media screen and ( max-width: 1365px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	}
    }
    @media screen and ( min-width: 1366px ) {
	:root {
	    --font-size: 16px;
	    --large-font-size: 20px;
	    width: 1366px;
	    font-size: var(--font-size);
	    overflow: scroll;
	}
    }
    h5 {
        font-size: var(--large-font-size);
	margin: 0 0 0 0;
	display:inline;
    }
    pre, form {
	display:inline;
        font-size: var(--font-size);
    }
    input, button, select {
	border-width: 2px;
	padding: 1px 6px 1px 6px;
	margin: 2px 3px 2px 3px;
	display:inline;
        font-size: var(--font-size);
    }
    span.problem {
	display:inline;
        font-size: var(--large-font-size);
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    div.errors, div.notices {
	background-color: #F5F81A;
    }
    div.warnings {
	background-color: #FFC0FF;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
	padding-top: 5px;
    }
    div.favorites-title {
	background-color: #FFC0FF;
	border-radius: 10px;
	text-align: center;
	width: 100%;
	padding: 10px 0px 10px 0px;
        font-size: var(--large-font-size);
    }
    div.lists {
	background-color: white;
	margin: 0px;
    }
    div.list {
	background-color: #FFB0B0;
	border: 1px solid black;
	border-radius: 10px;
	border-collapse: collapse;
    }
    table.list-description-header {
	text-align: center;
	width: 100%;
	padding: 2px;
        font-size: var(--large-font-size);
    }
    div.list-description {
	background-color: #FFCCCC;
	margin-left: 20px;
        font-size: var(--font-size);
    }
    div.list-description p, div.list-description pre {
        margin: 0px;
        padding: 5px 0px 5px 10px;
    }
    span.checkbox {
        height: 15px;
        width: 30px;
	display: inline-block;
	margin-right: 3px;
	border: 1px solid;
	border-radius: 7.5px;
    }

</style>

<script>
var LOG = function(message) {};
<?php if ( $epm_debug )
          echo "LOG = console.log;" . PHP_EOL ?>

function FAIL ( message )
{
    // Alert must be scheduled as separate task.
    //
    LOG ( "call to FAIL: " + message );
<?php
    if ( $epm_debug )
        echo <<<'EOT'
	    setTimeout ( function () {
		alert ( message );
		window.location.reload ( true );
	    });
EOT;
    else
        echo <<<'EOT'
	    throw "CALL TO FAIL: " + message;
EOT;
?>
}
</script>

</head>

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>";
	echo "<h5>Errors:</h5>";
	echo "<div class='indented'>";
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>";
	echo "<h5>Warnings:</h5>";
	echo "<div class='indented'>";
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>";
	echo "<br></div></div>";
    }

    $favorites_help = HELP ( 'favorites-page' );
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <form>
    <label>
    <h5>User:</h5> <input type='submit' value='$email'
		    formaction='user.php'
		    formmethod='GET'
                    title='Click to See User Profile'>
    </label>
    </form>
    </td>
    <td>
    <form>
    <button type='button'
	    onclick='SUBMIT("update")'>
	    Update</button>
    <button type='button'
	    onclick='SUBMIT("finish")'>
	    Finish</button>
    <button type='button'
	    onclick='SUBMIT("cancel")'>
	    Cancel</button>
    </form>

    <form method='POST' action='favorites.php'
	  id='submit-form'>
    <input type='hidden' name='ID' value='$id'>
    <input type='hidden' name='goto' id='goto'>
    <input type='hidden' name='indices'
	   id='indices'>
    </form>
    </td>
    <td>
    </td><td style='text-align:right'>
    $favorites_help</td>
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
	else
	    $basename = preg_replace
	        ( '/-/', ' ', $basename );
	$time = substr ( $time, 0, 10 );

	$switch = ( $c < $fcount ? $on : $off );

	echo <<<EOT
	<div id='$c' class='list'
	     draggable='true'
	     ondragover='ALLOWDROP(event)'
             ondrop='DROP(event)'
	     ondragstart='DRAGSTART(event,$c)'>
	<table style='width:100%'
	       class='list-description-header'>
	<tr>
	<td style='width:10%;text-align:left'>
	<span class='checkbox'
	      onclick='CHECK(event,"$c")'
	      style='background-color:$switch'>
	      &nbsp;
	      </span></td>
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

    let lists = document.getElementById ( 'lists' );
    let off = 'transparent';
    let on = 'black';

    let submit_form = document.getElementById
	( 'submit-form' );
    let goto = document.getElementById ( 'goto' );
    let indices = document.getElementById ( 'indices' );

    function BOXFROMDIV ( div )
    {
	let table = div.firstElementChild;
	let tbody = table.firstElementChild;
	let tr = tbody.firstElementChild;
	let td = tr.firstElementChild;
	let span = td.firstElementChild;
	return span;
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
	let des = event.target;
	while ( des.tagName != 'DIV' )
	    des = des.parentElement;
	let src = document.getElementById ( id );
	let next = des.nextElementSibling;
	let src_box = BOXFROMDIV ( src );
	if ( next == null )
	{
	    if ( lists.firstElement != des )
		src_box.style.backgroundColor =
		    BOXFROMDIV(des).style
		                   .backgroundColor;
	    lists.appendChild ( src );
	}
	else
	{
	    src_box.style.backgroundColor =
	        BOXFROMDIV(next).style.backgroundColor;
	    lists.insertBefore ( src, next );
	}
    }

    function CHECK ( event, c )
    {
        event.preventDefault();
	let checkbox = event.target;
	let src = document.getElementById ( c );
	if ( checkbox.style.backgroundColor == on )
	{
	    checkbox.style.backgroundColor = off;
	    var des = src;
	    while ( true )
	    {
	        des = des.nextElementSibling;
		if ( des == null ) break;

		let box = BOXFROMDIV ( des );
		if ( box.style.backgroundColor == off )
		    break;
	    }
	    if ( des == null )
	        lists.appendChild ( src );
	    else
	        lists.insertBefore ( src, des );
	}
	else
	{
	     des = lists.children[1];
	     checkbox.style.backgroundColor = on;
	     lists.insertBefore ( src, des );
	}
    }

    function SUBMIT ( to )
    {
        goto.value = to;
	submit_form.submit();
    }

    </script>
EOT;

?>

</body>
</html>
