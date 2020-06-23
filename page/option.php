<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Jun 22 20:47:48 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Edits problem option page.

    $epm_page_type = '+problem+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    // require "$epm_home/include/debug_info.php";

    if ( ! isset ( $_REQUEST['problem'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to option.php" );
    elseif ( ! isset ( $_SESSION['EPM_UID'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to option.php" );
    elseif ( ! isset ( $_SESSION['EPM_EMAIL'] ) )
	exit ( "ACCESS: illegal $epm_method" .
	       " to option.php" );

    $uid = $_SESSION['EPM_UID'];
    $email = $_SESSION['EPM_EMAIL'];
    $problem = $_REQUEST['problem'];
    $probdir = "users/$uid/$problem";

    if ( ! is_dir ( "$epm_data/$probdir" ) )
        exit ( "problem $problem no longer exists" );

    require "$epm_home/include/epm_template.php";
    $_SESSION['EPM_PERMISSION']['option']['template'] =
        true;

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
	    touch ( "$epm_data/$probdir/+altered+" );
	}
    }
    elseif ( isset ( $_POST['cancel'] ) )
        /* Do Nothing */;
    elseif ( isset ( $_POST['reset-all'] ) )
    {
	$f = "$probdir/$problem.optn";
	@unlink ( "$epm_data/$f" );
	touch ( "$epm_data/$probdir/+altered+" );
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
	if ( event.keyCode == 13 )
	    event.preventDefault();
    }
    function SET_INHERITED ( count, inherited )
    {
        var ITEM = document.getElementById
	    ( 'value' + count );
	ITEM.value = inherited;
	ITEM.style.backgroundColor = '#FFBF80';
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

    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <td style='width:30%;text-align:left'>
    <strong>User:&nbsp;$email</strong>
    </td>
EOT;
    if ( $edit )
        echo <<<EOT
	<td style='width:25%'>
	</td>
EOT;
    else
    	echo <<<EOT
	<td style='width:25%'>
	<strong>Go To</strong>
	<form method='GET'>
	<input type='hidden'
	       name='problem' value='$problem'>
	<input type='hidden' name='id' value='$ID'>
	<button type='submit'
		formaction='problem.php'>Problem
	</button>
	<button type='submit'
		formaction='run.php'>Run
	</button>
	</form>
	<strong>Page</strong>
	</td>
EOT;
    echo <<<EOT
    <td style='width=15%''>
    <button type='button'
            onclick='VIEW("template.php")'>
    View Templates</button>
    </td>
    <td style='text-align:right;width=30%'>
    <strong>Current Problem:</strong>&nbsp;
    <pre class='problem'>$problem</pre>
    <pre>   </pre>
    <button type='button'
            onclick='HELP("option-page")'>
	?</button>
    </td>
    </table>
    </div>

    <form action='option.php' method='POST'
          onkeydown='return event.key != "Enter"'>
	  <!-- onkeydown keeps text area enter key
	       from triggering submit -->
    <!-- This form lasts till the end of the
         document -->
    <input type='hidden'
           name='problem' value='$problem'>
    <input type='hidden' name='id' value='$ID'>
    <div class='center manage'>
EOT;
    if ( $edit )
        echo <<<EOT
	<button type='submit'
	        name='update' value='update'>
	    Update Options</button>
	<pre>    </pre>
        <button type='submit'
	        name='cancel' value='cancel'>
	    Cancel Edit</button>
	<pre>    </pre>
        <button type='submit'
	        name='reset-all' value='reset-all'>
	    Reset All to Inherited Values</button>
EOT;
    else
        echo <<<EOT
	<button type='submit' name='edit'
	        value='edit'>Edit Options</button>
EOT;

    echo <<<EOT
    </div>
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
    <button type='button'
            onclick='HELP("option-numbers")'>
	?</button>
    </td>
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

    echo <<<EOT
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
    <button type='button'
            onclick='HELP("option-arguments")'>
	?</button>
    </td>
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
