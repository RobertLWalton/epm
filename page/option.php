<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Mar 15 13:54:06 EDT 2020

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
    $description_types =
        ['natural', 'integer', 'float', 'args'];
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
    }
    foreach ( $options as $opt => $description )
    {
	if ( ! isset ( $description['description'] ) )
	    $errors[] =
	        "option $opt has NO description";

	$hasvalues = isset ( $description['values'] );
	$hastype = isset ( $description['type'] );
	$hasdefault = isset ( $description['default'] );
	$hasrange = isset ( $description['range'] );
	if ( $hasvalues && $hastype )
	    $errors[] = "option $opt has BOTH"
	              . " 'values' AND 'type'";
	elseif ( $hasvalues )
	{
	    if ( ! $hasdefault )
		$errors[] =
		    "option $opt has NO default";
	    else
	    {
	        $values = $description['values'];
	        $default = $description['default'];
		if ( ! in_array ( $default, $values ) )
		    $errors[] =
			"option $opt default $default" .
			" is not allowed";
	    }
	}
	elseif ( $hastype )
	{
	    $type = $description['type'];
	    if ( ! $hasdefault )
		$errors[] =
		    "option $opt has NO default";
	    if ( ! $hasrange )
		$errors[] = "option $opt has NO range";
	    if ( $hasdefault && $hasrange )
	    {
	        $range = $description['range'];
	        $default = $description['default'];
		if ( $default < $range[0]
		     ||
		     $default > $range[1] )
		    $errors[] =
			"option $opt default $default" .
			" is out of range";
	    }
	}
    }
    ksort ( $valnames, SORT_NATURAL );
    ksort ( $argnames, SORT_NATURAL );

    if ( count ( $errors ) > 0 )
    {
        $m = "Errors in option templates:";
	foreach ( $errors as $e )
	    $m .= "\n    $e";
	ERROR ( $m );
    }

    // Set the following:
    //
    //    $values maps NAME => CURRENT-OPTION-VALUE
    //    $inherited maps NAME => INHERITED-OPTION-VALUE
    //    $default maps NAME => DEFAULT-OPTION-VALUE
    //
    // where NAME is option name (e.g., "g++std"),
    // CURRENT-OPTION-VALUE is current value of option,
    // which is value from template overridden by
    // any values from $problem.optn files,
    // INHERITED-OPTION-VALUE is ditto but excludes
    // values from $probdir/$problem.opt, and $defaults
    // is initial value of $values.
    //
    $values = [];
    foreach ( $options as $opt => $value )
    {
	if ( isset ( $value['default'] ) )
	    $values[$opt] = $value['default'];
    }
    foreach ( array_reverse ( $remote_dirs ) as $dir )
    {
	$f = "$dir/$problem.optn";
        if ( ! is_readable ( "$epm_data/$f" ) )
	    continue;
	$j = get_json ( $epm_data, $f );
	foreach ( $j as $opt => $value )
	{
	    if ( isset ( $values[$opt] ) )
		$values[$opt] = $value;
	    else
	        $errors[] = "option $opt in $f is not"
		          . " in templates";
	}
    }

    $inherited = $values;

    $f = "$probdir/$problem.optn";
    if ( is_readable ( "$epm_data/$f" ) )
    {
	$j = get_json ( $epm_data, $f );
	foreach ( $j as $opt => $value )
	{
	    if ( isset ( $values[$opt] ) )
		$values[$opt] = $value;
	    else
	        $errors[] = "option $opt in $f is not"
		          . " in templates";
	}
    }

    $default = $values;
    $changed = false;
        // True if $values != $default.
    $edit = false;
        // False to display options, true to edit them.

    // If editing, $values is updated and $changed is
    // set to true whenever an element of $values is
    // actually changed (not just set to its old value).
    // Then if there are no errors, elements of $values
    // that are != to corresponding elements of
    // $inherited are written to $probdir/$problem.optn.
    // If there are errors $values, is discarded and
    // editing begins from the $defaults.

    if ( isset ( $_POST['edit'] ) )
        $edit = true;
    elseif ( isset ( $_POST['update'] ) )
    {
	// Errors are appended to $errors even if they
	// indicate the POST is UNACCEPTABLE.
	//
	foreach ( $values as $opt => $value )
	{
	    if ( ! isset ( $_POST[$opt] ) )
	    {
		$errors[] = "option $opt has no value"
		          . " in POST";
		continue;
	    }
	    $v = $_POST[$opt];
	    if ( $v == $value ) continue;
		// No need for checking.

	    $d = $options[$opt];
	    $name = ( isset ( $d['valname'] ) ?
	              $d['valname'] :
		      isset ( $d['argname'] ) ?
		      $d['argname'] :
		      NULL );
	    if ( ! isset ( $name ) )
	    {
	        $errors[] = "option $opt has neither"
		          . " 'valname' nor 'argname'";
		continue;
	    }
	    if ( isset ( $d['values'] ) )
	    {
	        if ( ! in_array ( $v, $d['values'] ) )
		    $errors[] = "$v is not a valid"
		              . " value for $name";
		else
		{
		    $values[$opt] = $v;
		    $changed = true;
		}
	    }
	    elseif ( isset ( $d['type'] ) )
	    {
	        $t = $d['type'];
		if ( $t == 'natural' )
		{
		    $re = '/^\d+$/';
		    $tn = 'not a natural number';
		}
		elseif ( $t == 'an integer' )
		{
		    $re = '/^(|+|-)\d+$/';
		    $tn = 'not an integer';
		}
		elseif ( $t == 'float' )
		{
		    $re = '/^(|+|-)\d+'
		        . '(|\.\d+)'
		        . '(|(e|E)(|+|-)\d+)/';
		    $tn = 'not a float';
		}
		elseif ( $t == 'args' )
		{
		    $re = '/^[-+_@=/.,A-Za-z0-9\h]*$/';
		    $tn = 'contains special character'
		        . ' other than - + _ @ = / . ,';
		}
		if ( ! preg_match ( $re, $v ) )
		{
		    $errors[] = "$v in $name"
		              . " is $tn";
		    continue;
		}
		else
		{
		    $values[$opt] = $v;
		    $changed = true;
		}
	    }
	    else
	        $errors[] = "option $opt has neither"
		          . " 'values' nor 'type'";
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
	padding-bottom: 20px;
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

    echo <<<EOT
    <div class='manage'>
    <form method='GET'>
    <table>
    <td>
    <h5>User:</h5> <input type='submit' value='$email'
                    formaction='user.php'
                    title='click to see user profile'>
    </td>
    <td style='padding-left:50px'>
    <button type='submit'
            formaction='problem.php'>Go To Problem Page
    </button>
    </td>
    <td style='padding-left:50px'>
    <h5>Current Problem:</h5>&nbsp;
    <pre class='problem'>$problem</pre></b>
    </td>
    </table>
    </form>
    <br>
    <form action='option.php' method='POST'>
    <!-- This form lasts till the end of the
         document -->
EOT;
    if ( $edit )
        echo "<button type='submit' name='update'" .
	     " value='update'>Update</button>";
    else
        echo "<button type='submit' name='edit'" .
	     " value='edit'>Edit</button>";
    echo "</div>";

    echo <<<EOT
    <button type='button'
	    id='values_button'
	    onclick='TOGGLE_BODY
		 ("values", "Values")'
	    title='Show Values'>
	    <pre id='values_mark'>&darr;</pre>
	    </button>
    &nbsp;
    <h5>Values:</h5>
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
	$v = $values[$opt];
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
		 " class='$c'>";
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

    echo <<<EOT
    <br>
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
	    $vv = $values[$opt];
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
			$chk = 'checked';
		    if ( $v == '' )
			$v = '     ';
		    if ( true )
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
