<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Mar 16 03:15:02 EDT 2020

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

    require "$epm_home/include/epm_make.php";
        // We do not need most of epm_make.php, but
	// since editing options is not done that
	// frequently, the extra overhead of loading
	// a lot of stuff we do not need is not really
	// harmful.

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
    function check_errors ()
    {
        global $errors;
	if ( count ( $errors ) == 0 ) return;

        $m = "Errors in option templates:";
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
    //
    $optn_files = [];
    $optn_files[] =
	[$epm_home, "template/template.optn"];
    $optn_files[] =
	[$epm_data, "template/template.optn"];
    $optn_files[] =
	[$epm_data, "admin/users/$uid/template.optn"];

    $options = [];
    $argnames = [];
    $valnames = [];
    $description_keys =
        ['argname', 'valname', 'description',
	 'values','type','range','default'];
    $type_re =
        ['natural' => '/^\d+$/',
	 'integer' => '/^(|\+|-)\d+$/',
	 'float' => '/^(|\+|-)\d+(|\.\d+)'
	          . '(|(e|E)(|\+|-)\d+)$/'];
    foreach ( $optn_files as $e )
    {
	$r = $e[0];
	$f = $e[1];
	if ( ! is_readable ( "$r/$f" ) ) continue;
	$j = get_json ( $r, $f );

	// template.optn values are 2D arrays.
	//
	foreach ( $j as $opt => $description )
	{
	    foreach ( $description as $key => $value )
	    {
	        if ( ! in_array
		           ( $key, $description_keys ) )
		    $errors[] =
		        "invalid description key $key" .
			" for option $opt in $f";
		else
		    $options[$opt][$key] = $value;
	    }
	}
    }
    foreach ( $options as $opt => $description )
    {
	if ( ! isset ( $description['description'] ) )
	    $errors[] =
	        "option $opt has NO description";

	$isarg = isset ( $description['argname'] );
	$isval = isset ( $description['valname'] );
	if ( $isarg && $isval )
	    $errors[] = "option $opt has BOTH"
	              . " 'argname' AND 'valname'";
	elseif ( $isarg )
	    $argnames[$description['argname']][] =
		$opt;
	elseif ( $isval )
	    $valnames[$description['valname']][] =
		$opt;

	$hasvalues = isset ( $description['values'] );
	$hastype = isset ( $description['type'] );
	$hasdefault = isset ( $description['default'] );
	$hasrange = isset ( $description['range'] );

	if ( $isval )
	{
	    if ( ! $hasdefault )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'default'";
	    if ( ! $hasrange )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'range'";
	    if ( ! $hastype )
		$errors[] = "option $opt has 'valname'"
		          . " but no 'type'";
	    if ( $hasvalues )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'values'";
	}
	elseif ( $isarg )
	{
	    if ( ! $hasdefault )
		$errors[] = "option $opt has 'argname'"
		          . " but no 'default'";
	    if ( $hastype )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'type'";
	    if ( $hasrange )
		$errors[] = "option $opt has 'valname'"
		          . " and also has 'range'";
	}
	else
	{
	    if ( $hasvalues )
		$errors[] = "option $opt has 'values'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hastype )
		$errors[] = "option $opt has 'type'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hasrange )
		$errors[] = "option $opt has 'range'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	    if ( $hasdefault )
		$errors[] = "option $opt has 'default'"
		          . " but has neither"
			  . " 'argname' or 'valname'";
	}
    }
    check_errors();
    ksort ( $valnames, SORT_NATURAL );
    ksort ( $argnames, SORT_NATURAL );

    // Add to $errors any errors found in the
    // $optmap of $opt => $value.  Assumes templates
    // have already been checked.  Error messages
    // complain about `$name values'.
    //
    function check_optmap ( & $optmap, $name )
    {
	global $type_re, $errors, $options;

        foreach ( $optmap as $opt => $value )
	{
	    $d = & $options[$opt];
	    if ( isset ( $d['values'] ) )
	    {
	        $values = $d['values'];
		if ( ! in_array ( $value, $values ) )
		    $errors[] = "option $opt $name"
		              . " value '$value' is not"
			      . " in option `values'";
	    }
	    elseif ( isset ( $d['type'] ) )
	    {
	        $type = $d['type'];
		$re = $type_re[$type];
		if ( ! preg_match ( $re, $value ) )
		    $errors[] =
			"option $opt $name value" .
			" '$value' has illegal" .
			" format for its type $type";
		else
		{
		    $r = $d['range'];
		    if ( $value < $r[0] )
			$errors[] =
			    "option $opt $name value" .
			    " '$value' is too small";
		    elseif ( $value > $r[1] )
			$errors[] =
			    "option $opt $name value" .
			    " '$value' is too large";
		}
	    }
	    else
	    {
		$re = '/^[-\+_@=\/:\.,A-Za-z0-9\h]*$/';
		if ( ! preg_match ( $re, $value ) )
		    $errors[] =
			"option $opt $name value" .
			" '$value' contains a" .
			" special character other" .
		        " than - + _ @ = / : . ,";
	    }
	}
    }

    $optmap = [];
    foreach ( $options as $opt => $value )
    {
	if ( isset ( $value['default'] ) )
	    $optmap[$opt] = $value['default'];
    }
    check_optmap ( $optmap, 'template default' );
    check_errors();

    foreach ( array_reverse ( $remote_dirs ) as $dir )
    {
	$f = "$dir/$problem.optn";
        if ( ! is_readable ( "$epm_data/$f" ) )
	    continue;
	$j = get_json ( $epm_data, $f );
	foreach ( $j as $opt => $value )
	{
	    if ( isset ( $optmap[$opt] ) )
		$optmap[$opt] = $value;
	    else
	        $errors[] = "option $opt in $f is not"
		          . " in templates";
	}
    }
    check_optmap ( $optmap, 'inherited' );
    check_errors();

    $inherited = $optmap;

    $f = "$probdir/$problem.optn";
    if ( is_readable ( "$epm_data/$f" ) )
    {
	$j = get_json ( $epm_data, $f );
	foreach ( $j as $opt => $value )
	{
	    if ( isset ( $optmap[$opt] ) )
		$optmap[$opt] = $value;
	    else
	        $errors[] = "option $opt in $f is not"
		          . " in templates";
	}
    }
    check_optmap ( $optmap, 'local' );

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
	check_optmap ( $optmap, 'update' );
	if ( count ( $errors ) > 0 )
	{
	    $optmap = $defaults;
	    $edit = true;
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
	        unlink ( "$epm_data/$f" );
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

    $debug = ( $epm_debug != ''
               &&
	       preg_match ( $epm_debug, $php_self ) );
	// True to enable javascript logging.


?>

<html>
<head>
<style>
    .no-margin {
	margin: 0 0 0 0;
    }
    h5 {
        font-size: 14pt;
	margin: 0 0 0 0;
	display:inline;
    }
    pre, b, button, input, select, u {
	display:inline;
        font-size: 12pt;
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    .right-adjust {
	text-align:right;
    }
    .center {
	margin-left: auto;
	margin-right: auto;
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
    pre.problem {
        color: #CC00FF;
        font-size: 14pt;
    }
    div.indented {
	margin-left: 20px;
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
	if ( BODY.hidden )
	{
	    MARK.innerHTML = "&uarr;";
	    BUTTON.title = "Hide " + thing;
	    BODY.hidden = false;
	}
	else
	{
	    MARK.innerHTML = "&darr;";
	    BUTTON.title = "Show " + thing;
	    BODY.hidden = true;
	}
    }
</script>

</head>
<body>

<?php 

    if ( count ( $errors ) > 0 )
    {
	echo "<div class='errors'>" .  PHP_EOL;
	echo "<h5>Errors:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $errors as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }
    if ( count ( $warnings ) > 0 )
    {
	echo "<div class='warnings'>" .  PHP_EOL;
	echo "<h5>Warnings:</h5>" . PHP_EOL;
	echo "<div class='indented'>" . PHP_EOL;
	foreach ( $warnings as $e )
	    echo "<pre>$e</pre><br>" . PHP_EOL;
	echo "<br></div></div>" . PHP_EOL;
    }

    $option_help = HELP ( 'option-page' );
    echo <<<EOT
    <div class='manage'>
    <form method='GET' style='margin-bottom:0'>
    <table style='width:100%'>
    <td>
    <h5>User:</h5> <input type='submit' value='$email'
                    formaction='user.php'
                    title='click to see user profile'>
    </td>
    <td style='padding-left:20px'>
    <h5>Go To:</h5>
    <button type='submit'
            formaction='problem.php'>Problem Page
    </button>
    &nbsp;&nbsp;
    <button type='submit'
            formaction='run.php'>Run Page
    </button>
    </td>
    <td style='padding-left:20px'>
    <h5>Current Problem:</h5>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td><td style='text-align:right'>
    $option_help</td>
    </table>
    </form>
    <br>
    <form action='option.php' method='POST'>
    <!-- This form lasts till the end of the
         document -->
    <div class='center' style='width:100px'>
EOT;
    if ( $edit )
        echo "<button type='submit' name='update'" .
	     " value='update'>Update</button>";
    else
        echo "<button type='submit' name='edit'" .
	     " value='edit'>Edit</button>";

    $values_help = HELP ( 'option-values' );
    echo <<<EOT
    </div></div>
    <table style='width:100%'><tr>
    <td>
    <button type='button'
	    id='values_button'
	    onclick='TOGGLE_BODY
		 ("values", "Values")'
	    title='Show Values'>
	    <pre id='values_mark'>&darr;</pre>
	    </button>
    &nbsp;
    <h5>Values:</h5>
    </td><td style='text-align:right'>
    $values_help</td>
    </tr></table>
    <div class='indented' id='values_body'>
    <table>
EOT;
    foreach ( $valnames as $valname => $optlist )
    {
	if ( count ( $optlist ) != 1 )
	    ERROR ( "\$valnames[$valname] = [" .
		    implode ( ",", $optlist ) .
		    "] should have single" .
		    " element" );
	$opt = $optlist[0];
	$d = $options[$opt];
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
		 " class='$c right-adjust'>";
	}
	else
	    echo "<td class='$c'>" .
	         "<pre>$v</pre></td>";
	echo <<<EOT
	<td style='padding-left:10px'>
	<pre>$des; $t in [{$r[0]},{$r[1]}]</pre>
	</td></tr>
EOT;
    }
    echo "</table></div>";

    $arguments_help = HELP ( 'option-arguments' );
    echo <<<EOT
    <br>
    <table style='width:100%'><tr>
    <td>
    <button type='button'
	    id='arguments_button'
	    onclick='TOGGLE_BODY
		 ("arguments",
		  "Command Arguments")'
	    title='Show Command Arguments'>
	    <pre id='arguments_mark'>&darr;</pre>
	    </button>
    &nbsp;
    <h5>Command Arguments:</h5>
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
	$des = $options[$argname]['description'];
	echo <<<EOT
	<tr><td>$argname</td>
	<td colspan='10' style='padding-left:5px'>
	<pre>$des</pre></td></tr>
EOT;
	foreach ( $optlist as $opt )
	{
	    $d = $options[$opt];
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

	    echo "<pre> $des </pre>";
	    echo "</td></tr>";
	}
    }
    echo "</table></div></form>";

?>


</body>
</html>
