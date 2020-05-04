<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon May  4 17:39:54 EDT 2020

    // Edits problem option page.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( ! isset ( $_SESSION['EPM_PROBLEM'] ) )
    {
	header ( 'Location: /page/problem.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' && $method != 'POST' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // require "$epm_home/include/debug_info.php";

    $problem = $_SESSION['EPM_PROBLEM'];
    $email = $_SESSION['EPM_EMAIL'];
    $uid = $_SESSION['EPM_UID'];
    $probdir = "users/$uid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
    {
	// Some other session deleted the problem;
	// let problem.php deal with it.
	//
	header ( 'Location: /page/problem.php' );
	exit;
    }

    require "$epm_home/include/epm_template.php";

    $lock_desc = NULL;
    function shutdown ()
    {
        global $lock_desc;
	if ( isset ( $lock_desc ) )
	    flock ( $lock_desc, LOCK_UN );
    }
    register_shutdown_function ( 'shutdown' );
    $lock_desc =
	fopen ( "$epm_data/$probdir/+lock+", "w" );
    flock ( $lock_desc, LOCK_EX );

    $errors = [];    // Error messages to be shown.
    $warnings = [];  // Warning messages to be shown.

    // If $errors is not empty, call ERROR with $errors
    // in error message.
    //
    function check_errors ( $name )
    {
        global $errors;
	if ( count ( $errors ) == 0 ) return;

        $m = "Errors in $name:";
	foreach ( $errors as $e )
	    $m .= "\n    $e";
	ERROR ( $m );
    }

    // Set options to the json of $epm_home/template/
    // template.optn with overrides (as a 2D matrix)
    // from $epm_data/template/template.optn and
    // $epm_data/admin/users/$uid/template.optn.
    //
    // Also set the following:
    //
    //    $valnames maps VALNAME => OPT-LIST
    //    $argnames maps ARGNAME => OPT-LIST
    //
    // where the OPT in OPT-LIST have the given
    // 'valname' or 'argname'.  These maps are sorted
    // alphabetically by key.
    //
    // Lastly, check format of option templates and
    // report errors by appending to $errors.  Template
    // errors will result in a call to ERROR below.

    get_template_optn();
    check_template_optn ( $errors );
    check_errors ( 'option_templates' );

    $argnames = [];
    $valnames = [];
    foreach ( $template_optn as $opt => $description )
    {
	if ( isset ( $description['argname'] ) )
	    $argnames[$description['argname']][] =
		$opt;
	if ( isset ( $description['valname'] ) )
	    $valnames[$description['valname']][] =
		$opt;
    }
    ksort ( $valnames, SORT_NATURAL );
    ksort ( $argnames, SORT_NATURAL );

    $optmap = [];
    foreach ( $template_optn as $opt => $value )
    {
	if ( isset ( $value['default'] ) )
	    $optmap[$opt] = $value['default'];
    }
    check_optmap
        ( $optmap, 'template default', $errors );
    check_errors ( 'template default values' );

    $dirs = array_reverse
        ( find_ancestors ( $probdir ) );
    load_optmap ( $optmap, $dirs, $problem, $errors );
    check_optmap ( $optmap, 'inherited', $errors );
    check_errors ( 'template inherited values' );

    $inherited = $optmap;

    load_optmap
        ( $optmap, [$probdir], $problem, $errors );
    check_optmap ( $optmap, 'local', $errors );

    $defaults = $optmap;
    $edit = false;
        // False to display options, true to edit them.
    
    // If editing, $optmap is updated and errors are
    // checked.  If there are no errors, elements of
    // $optmap that are != to corresponding elements
    // of $inherited are written to $probdir/
    // $problem.optn.  If there are errors $optmap, is
    // reset to $defaults and editing begins anew.

    if ( isset ( $_POST['edit'] ) )
        $edit = true;
    elseif ( isset ( $_POST['update'] ) )
    {
	// Errors are appended to $errors even if they
	// indicate the POST is UNACCEPTABLE.
	//
	foreach ( $optmap as $opt => $value )
	{
	    if ( isset ( $_POST[$opt] ) )
	        $optmap[$opt] = trim ( $_POST[$opt] );
	}
	$errors = [];
	    // Clear previous $optmap errors.
	check_optmap ( $optmap, 'update', $errors );
	if ( count ( $errors ) > 0 )
	{
	    $optmap = $defaults;
	    $errors[] = "update is cancelled";
	}
	else
	{
	    $new_opts = [];
	    foreach ( $optmap as $opt => $value )
	    {
	        if ( $value != $inherited[$opt] )
		    $new_opts[$opt] = $value;
	    }
	    $f = "$probdir/$problem.optn";
	    if ( count ( $new_opts ) == 0 )
	        @unlink ( "$epm_data/$f" );
	    else
	    {
		$j = json_encode
		    ( $new_opts, JSON_PRETTY_PRINT );
		$r = @file_put_contents
			  ( "$epm_data/$f", $j );
		if ( $r === false )
		    ERROR ( "cannot write $f" );
	    }
	}
    }
    elseif ( isset ( $_POST['cancel'] ) )
        /* Do Nothing */;
    elseif ( isset ( $_POST['reset-all'] ) )
    {
	$f = "$probdir/$problem.optn";
	@unlink ( "$epm_data/$f" );
	$optmap = $inherited;
    }
?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    .mono {
	display:inline;
	font-family: "Courier New", Courier, monospace;
        font-size: var(--font-size);
    }
    .right-adjust {
	text-align:right;
    }
    .center {
	text-align: center;
    }
    td.inherited {
        background-color: #FFBF80;
	border-style: solid;
	border-width: 1px;
	text-align:right;
	padding-left:5px;
    }
    td.local {
        background-color: #99FF99;
	border-style: solid;
	border-width: 1px;
	text-align:right;
	padding-left:5px;
    }
    input.inherited {
        background-color: #FFBF80;
	border-style: solid;
	border-width: 1px;
	margin-left:5px;
    }
    input.local {
        background-color: #99FF99;
	border-style: solid;
	border-width: 1px;
	margin-left:5px;
    }
    td.argument {
	padding-left: 5px;
    }
    input.argument {
        position: absolute;
	opacity: 0;
	cursor: pointer;
    }
    input.argument:checked ~ pre {
        background-color: #99FF99;
    }
    pre.unused {
        background-color: #FFFFFF;
	border-style: solid;
	border-width: 1px;
    }
    pre.inherited {
        background-color: #FFBF80;
	border-style: solid;
	border-width: 1px;
    }
    pre.local {
        background-color: #99FF99;
	border-style: solid;
	border-width: 1px;
    }
    div.errors {
	background-color: #F5F81A;
    }
    div.warnings {
	background-color: #FFC0FF;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
    }
    div.values {
	background-color: #FFE6F0;
    }
    div.arguments {
	background-color: #ECFFE6;
    }
    pre.problem {
        color: #CC00FF;
        font-size: var(--large-font-size);
    }
</style>

<script>
    function TOGGLE_BODY ( name, thing )
    {
	var BUTTON = document.getElementById
		( name + '_button' );
	var MARK = document.getElementById
		( name + '_mark' );
	var BODY = document.getElementById
		( name + '_body' );
	if ( BODY.style.display == 'none' )
	{
	    MARK.innerHTML = "&uarr;";
	    BUTTON.title = "Hide " + thing;
	    BODY.style.display = 'block';
	}
	else
	{
	    MARK.innerHTML = "&darr;";
	    BUTTON.title = "Show " + thing;
	    BODY.style.display = 'none';
	}
    }

    function CHANGE_BACKGROUND ( count )
    {
        var ITEM = document.getElementById
	    ( 'value' + count );
	ITEM.style.backgroundColor = '#99FF99';
    }
    function SET_INHERITED ( count, inherited )
    {
        var ITEM = document.getElementById
	    ( 'value' + count );
	ITEM.value = inherited;
	ITEM.style.backgroundColor = '#FFBF80';
    }

    var template_window = null;

    function TEMPLATE_WINDOW ( ) {
	var src = '/page/template.php?subwindow';
	if (    template_window == null
	     || template_window.closed )
	{
	    var x = screen.width - 1200;
	    var y = screen.height - 800;
	    w = window.open
		( src, 'template_window',
		  'height=800px,width=1200px,' +
		  'screenX=' + x + 'px,' +
		  'screenY=' + y + 'px' );
	}
	else
	{
	    template_window.location.href = src;
	    template_window.location.reload();
	}
    }

    function UNLOAD () {
	if ( template_window != null
	     &&
	     ! template_window.closed )
	    template_window.close()
    }

</script>

</head>
<body>
<div class='root'>

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

    $option_help = HELP ( 'option-page' );
    echo <<<EOT
    <div class='manage'>
    <form method='GET' style='margin-bottom:0'>
    <table style='width:100%'>
    <td>
    <strong>User:</strong>
    <input type='submit' value='$email'
                    formaction='user.php'
                    title='click to see user profile'>
    </td>
    <td style='padding-left:var(--indent)'>
    <strong>Go To:</strong>
    <button type='submit'
            formaction='problem.php'>Problem Page
    </button>
    &nbsp;&nbsp;
    <button type='submit'
            formaction='run.php'>Run Page
    </button>
    &nbsp;&nbsp;
    <button type='button' onclick='TEMPLATE_WINDOW()'>
        Show Templates
    </button>
    </td>
    <td style='padding-left:var(--indent)'>
    <strong>Current Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td><td style='text-align:right'>
    $option_help</td>
    </table>
    </form>
    <br>
    <form action='option.php' method='POST'
          onkeydown='return event.key != "Enter"'>
	  <!-- onkeydown keeps text area enter key
	       from triggering submit -->
    <!-- This form lasts till the end of the
         document -->
    <div class='center'>
EOT;
    if ( $edit )
        echo "<button type='submit'" .
	     " name='update' value='update'>" .
	     "Update Options</button>" .
	     "<pre>    </pre>" .
             "<button type='submit'" .
	     " name='cancel' value='cancel'>" .
	     "Cancel Edit</button>" .
	     "<pre>    </pre>" .
             "<button type='submit'" .
	     " name='reset-all' value='reset-all'>" .
	     "Reset All to Inherited Values</button>";
    else
        echo "<button type='submit' name='edit'" .
	     " value='edit'>Edit Options</button>";

    $values_help = HELP ( 'option-numbers' );
    echo <<<EOT
    </div></div>
    <div class='values'>
    <table style='width:100%'><tr>
    <td>
    <button type='button'
	    id='values_button'
	    onclick='TOGGLE_BODY
		 ("values", "Number Options")'
	    title='Show Number Options'>
	    <pre id='values_mark'>&uarr;</pre>
	    </button>
    &nbsp;
    <strong>Number Options:</strong>
    </td><td style='text-align:right'>
    $values_help</td>
    </tr></table>
    <div class='indented' id='values_body'>
    <table>
EOT;
    $count = 0;
    foreach ( $valnames as $valname => $optlist )
    {
	if ( count ( $optlist ) != 1 )
	    ERROR ( "\$valnames[$valname] = [" .
		    implode ( ",", $optlist ) .
		    "] should have single" .
		    " element" );
	$count += 1;
	$opt = $optlist[0];
	$d = $template_optn[$opt];
	$description = $d['description'];
	$default = $d['default'];
	$iv = $inherited[$opt];
	$v = $optmap[$opt];
	$t = $d['type'];
	$r = $d['range'];
	$des = $d['description'];
	$c = 'local';
	if ( $v == $iv ) $c = 'inherited';
	echo <<<EOT
	<tr><td>$valname</td><td>
EOT;
	if ( $edit )
	{
	    echo "<input name='$opt' value='$v'" .
	         " type='text' size='10'" .
		 " class='$c right-adjust'" .
		 " id='value$count' onkeydown=" .
		 "'(CHANGE_BACKGROUND($count))'>" .
		 "<button type='button'" .
		 " onclick='SET_INHERITED" .
		 "($count,\"$iv\")'>&#8635;</button>";
	}
	else
	    echo "<td class='$c'>" .
	         "<pre>$v</pre></td>";
	echo <<<EOT
	<td style='padding-left:10px'>
	<div class='mono'>
	$des; $t in [{$r[0]},{$r[1]}]</div>
	</td></tr>
EOT;
    }
    echo "</table></div></div>";

    $arguments_help = HELP ( 'option-arguments' );
    echo <<<EOT
    <br>
    <div class='arguments'>
    <table style='width:100%'><tr>
    <td>
    <button type='button'
	    id='arguments_button'
	    onclick='TOGGLE_BODY
		 ("arguments",
		  "Argument Options")'
	    title='Show Argument Options'>
	    <pre id='arguments_mark'>&uarr;</pre>
	    </button>
    &nbsp;
    <strong>Argument Options:</strong>
    </td><td style='text-align:right'>
    $arguments_help</td>
    </tr></table>
    <div class='indented' id='arguments_body'>
    <table>
EOT;
    foreach ( $argnames as $argname => $optlist )
    {
	if ( count ( $optlist ) == 0 )
	    ERROR ( "\$argnames[$argname] is" .
		    " empty" );
	$des = $template_optn[$argname]['description'];
	echo <<<EOT
	<tr><td>$argname</td>
	<td style='padding-left:5px'>
	<div class='mono'>$des</div>
	</td></tr>
EOT;
	foreach ( $optlist as $opt )
	{
	    $d = $template_optn[$opt];
	    $des = $d['description'];
	    $iv = $inherited[$opt];
	    $vv = $optmap[$opt];
	    if ( isset ( $d['values'] ) )
		$vs = $d['values'];
	    else
		$vs = NULL;
	    echo "<tr><td></td>" .
		 "<td class='argument'>";
	    if ( isset ( $vs ) )
		foreach ( $vs as $v )
		{
		    $c = 'unused';
		    $chk = '';
		    if ( $v == $iv )
			$c = 'inherited';
		    elseif ( $v == $vv )
		    {
		        if ( $edit )
			    $chk = 'checked';
			else
			    $c = 'local';
		    }
		    if ( $v == '' )
			$v = '     ';
		    if ( $edit )
			echo "<label>" .
			     "<input class='argument'" .
			     " name='$opt'" .
			     " value='$v'" .
			     " type='radio' $chk>" .
			     "<pre class='$c'>" .
			     " $v </pre>" .
			     "</label>";
		    else
			echo "<pre class='$c'>" .
			     " $v </pre>";
		}
	    else
	    {
		$c = 'local';
		if ( $vv == $iv )
		    $c = 'inherited';
		if ( $vv == '' )
		    $vv = '     ';
		if ( $edit )
		    echo "<input class='$c'" .
		         " name='$opt'" .
			 " value='$vv'" .
			 " type='text'" .
			 " size='40'>";
		else
		    echo "<pre class='$c'>" .
			 " $vv </pre>";
	    }

	    echo "<div class='mono'" .
	         " style='padding-left:5px'>" .
		 "$des</div>";
	    echo "</td></tr>";
	}
    }
    echo "</table></div></div></form>";
?>


</div>
</body>
</html>
