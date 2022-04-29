<?php

    // File:	favorites.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Apr 29 16:47:12 EDT 2022

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Maintains favorites list of problem lists.

    // See doc/epm_admin.pdf for directory and file
    // formats.

    // Session Data
    // ------- ----

    // Session data is in $data as follows:
    //
    //	   $data LIST
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
    //		or accounts/AID.

    // POST:
    //
    // Each post may save the +favorites+ file and
    // has the following values:
    //
    //	   indices=INDICES
    //		Here INDICES are the indices in
    //		$data LIST of the elements to be
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
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    require "$epm_home/include/epm_list.php";

    if ( $epm_method == 'GET' ) $data['LIST'] = [];
    $list = & $data['LIST'];

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    if ( $epm_method != 'POST' )
        /* Do Nothing */;
    elseif ( isset ( $_POST['rw'] ) )
	require "$epm_home/include/epm_rw.php";
    elseif ( !isset ( $_POST['op'] ) )
	exit ( 'UNACCEPTABLE HTTP POST' );
    elseif ( !isset ( $_POST['indices'] ) )
	exit ( 'UNACCEPTABLE HTTP POST' );
    elseif ( ! $rw )
        $errors[] = 'you are no longer in read-write'
	          . ' mode';
    else
    {
	$op = $_POST['op'];
	if ( ! in_array ( $op, ['save','reset'] ) )
	    exit ( 'UNACCEPTABLE HTTP POST' );

	if ( $op == 'save' )
	{
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
		( "accounts/$aid/+lists+/+favorites+",
		  $flist );
	}
    }

    // Build $inmap containing list of all lists
    // in the forms:
    //
    //		-:- => TIME,
    //		PROJECT:- => TIME,
    //		-:BASENAME => TIME,
    //		PROJECT:BASENAME => TIME,
    //		    (for projects with `show' priv)
    //
    // in the order indicated.  TIME is the mtime
    // of the accounts's +actions+ file for -:-, the
    // PROJECT directory for PROJECT:-, and the list
    // itself for the other cases.
    // 
    $inmap = [];
    $time = @filemtime
        ( "$epm_data/accounts/$aid/+actions+" );
    if ( $time === false ) $time = time();
    $time = date ( $epm_time_format, $time );
    $inmap["-:-"] = $time;
    $projects = read_projects ( ["show"] );
    foreach ( $projects as $project )
    {
        $time = @filemtime
	    ( "$epm_data/projects/$project" );
	if ( $time === false )
	    ERROR ( "cannot stat projects/$project" );
	$time = date ( $epm_time_format, $time );
        $inmap["$project:-"] = $time;
    }
    $d = "accounts/$aid/+lists+";
    $fnames = @scandir ( "$epm_data/$d" );
    if ( $fnames !== false )
        foreach ( $fnames as $fname )
	{
	    $ext = pathinfo
	        ( $fname, PATHINFO_EXTENSION );
	    if ( $ext != 'list' ) continue;
	    $basename = pathinfo
	        ( $fname, PATHINFO_FILENAME );
	    $time = @filemtime
	        ( "$epm_data/$d/$fname" );
	    if ( $time === false )
		ERROR ( "cannot stat $d/$fname" );
	    $inmap["-:$basename"] =
	        date ( $epm_time_format, $time );
	}

    foreach ( $projects as $project )
    {
	$g = glob ( "$epm_data/projects/$project/" .
		    "+lists+/" . "*.list" );
	foreach ( $g as $fname )
	{
	    $basename = basename ( $fname, ".list" );
	    $time = filemtime ( $fname );
	    $inmap["$project:$basename"] =
	        date ( $epm_time_format, $time );
	}
    }

    $favorites = read_favorites_list ( $warnings );

    // Build $fmap containing list of all lists
    // in the form NAME:BASENAME => TIME.  The
    // first elements are taken from the +favorites+
    // file, excluding names of lists that no longer
    // exist.  There are $fcount such elements, and
    // these will be marked initially as being in the
    // `Favorites'.  TIMEs are taken from $inmap.
    //
    $fmap = [];
    $fcount = 0;
    foreach ( $favorites as $e )
    {
        list ( $time, $name, $basename ) = $e;
	$key = "$name:$basename";
	if ( ! isset ( $inmap[$key] ) ) continue;
	$fmap[$key] = $inmap[$key];
	++ $fcount;
    }
    foreach ( $inmap as $key => $time )
    {
        if ( ! isset ( $fmap[$key] ) )
	    $fmap[$key] = $time;
    }

    $list = [];
    foreach ( $fmap as $key => $time )
    {
        list ( $name, $basename ) =
	    explode ( ':', $key );
	$list[] = [$time, $name, $basename];
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
	background-color: var(--bg-tan);
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

    $login_title =
        'Login Name; Click to See User Profile';
    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>

    <tr id='not-edited' style='width:100%'>
    <form method='GET' action='favorites.php'>
    <input type='hidden' name='id' value='$ID'>
    <td>
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
    <button type='submit' formaction='list.php'>
    Edit Lists
    </button>
    <strong>Page</strong>
    </td>
    </td>
    <td>
    </td><td style='text-align:right'>
    $RW_BUTTON
    <button type='button' id='refresh'
            onclick='location.replace
	        ("favorites.php?id=$ID")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("favorites-page")'>
	?</button>
    </td>
    </form>
    </tr>

    <tr id='edited' style='width:100%;display:none'>
    <td>
    <input type='hidden' name='id' value='$ID'>
    <strong title='Login Name'>$lname</strong>
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
    $off = 'white';
    $on = 'black';
    $c = -1;
    foreach ( $list as $e )
    {
        ++ $c;
        list ( $time, $name, $basename ) = $e;
	$listname = "$name:$basename";
	$filename = listname_to_filename ( $listname );
	$description = '';
	if ( isset ( $filename ) )
	    $description = read_list_description
	        ( $filename );
	if ( $name == '-' )
	    $name = '<i>Your</i>';
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
	    $name $basename $time</td>
	</tr></table>
EOT;
	$description_html =
	    description_to_HTML ( $description );
        if ( $description_html != '' )
	    echo <<<EOT
	    <div class='list-description'>
	    $description_html
	    </div>
EOT;

	echo <<<EOT
	</div>
EOT;
    }
    echo <<<EOT
    </div>

EOT;

    if ( $rw )
        echo <<<EOT

	<script>
	let edited = document.getElementById
	    ( 'edited' );
	let not_edited = document.getElementById
	    ( 'not-edited' );
	let lists = document.getElementById ( 'lists' );
	let off = 'white';
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
		        BOX(des).style.backgroundColor
		     != on )
		    src_box.style.backgroundColor = off;
		}
	    else
	    {
		div.insertBefore ( src, next );
		if (    BOX(next).style.backgroundColor
		     == on )
		    src_box.style.backgroundColor = on;
		else if ( des != div.firstElementChild
			  &&
			     BOX(des).style
			             .backgroundColor
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
			   BOX(next).style
			            .backgroundColor
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
		var previous =
		    src.previousElementSibling;
		while (    previous
		        != div.firstElementChild
			&&
			   BOX(previous).style
					.backgroundColor
			!= on )
		    previous =
			previous.previousElementSibling;
		if (    previous
		     != src.previousElementSibling )
		{
		    let next =
		        previous.nextElementSibling;
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
		let color =
		    BOX(div).style.backgroundColor;
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
    else
        echo <<<EOT
	<script>
	function DRAGSTART ( event, c )
	{
	    event.preventDefault();
	}
	function ALLOWDROP ( event ) {}
	function DROP ( event ) {}
	function CHECK ( event, c ) {}
	function SUBMIT ( op ) {}
	</script>
EOT;

?>

</body>
</html>
