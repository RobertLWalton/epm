<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Fri Mar 13 16:32:52 EDT 2020

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

    // Problem Parameters:
    //
    if ( ! isset ( $problem_params ) )
    {
	$f = "$probdir/$problem-$uid.params";
	if ( is_readable ( "$epm_data/$f" ) )
	    $problem_params = get_json ( $epm_data, $f );
	else
	    $problem_params = [];
    }
    if ( isset ( $problem_params['remote_dirs'] ) )
	$remote_dirs = $problem_params['remote_dirs'];
    else
	$remote_dirs = [];

    // Function to get and decode json file, which must
    // be readable.  It is a fatal error if the file
    // cannot be read or decoded.
    //
    // The file name is $r/$file, where $r is either
    // $epm_home or $epm_data and will NOT appear in any
    // error message.
    //
    function get_json ( $r, $file )
    {
	$f = "$r/$file";
	$c = @file_get_contents ( $f );
	if ( $c === false )
	    ERROR ( "cannot read readable $file" );
	$c = preg_replace ( '#(\R|^)\h*//.*#', '', $c );
	    // Get rid of `//...' comments.
	$j = json_decode ( $c, true );
	if ( $j === NULL )
	{
	    $m = json_last_error_msg();
	    ERROR
		( "cannot decode json in $file:" .
		  PHP_EOL . "    $m" );
	}
	return $j;
    }

    // Function to pretty print a template.  Changes
    // XXXX:YYYY:ZZZZ to XXXX => YYYY (ZZZZ).
    //
    function pretty_template ( $template )
    {
	if ( ! preg_match ( '/^([^:]+):([^:]+):(.*)$/',
			    $template, $matches ) )
	    return $template;
	$r = "{$matches[1]} => {$matches[2]}";
	if ( $matches[3] != "" )
	    $r = "$r ({$matches[3]})";
	return $r;
    }

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
	    if ( ! $hasdefault )
		$errors[] =
		    "option $opt has NO default";
	    if ( ! $hasrange )
		$errors[] =
		    "option $opt has NO range";
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
    foreach ( $options as $opt => $value )
    {
        if ( isset ( $value['values'] ) )
	    $values[$opt] = $value['values'][0];
	elseif ( isset ( $value['default'] ) )
	    $values[$opt] = $value['default'];
	else
	    $errors[] = "template $key has neither"
	              . " 'values' or 'default'";
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

    // If editing, $values is update and marked as
    // which is marked as changed if there is an update.
    // Then if there are no errors, $values is written
    // and becomes the new default.

    if ( isset ( $_POST['submit'] ) )
    {
	foreach ( $values as $opt => $value )
	{
	    if ( ! isset ( $_POST[$opt] ) )
		exit ( 'UNACCEPTABLE HTTP POST' );
	    $v = $_POST[$opt];
	    if ( $v == $value ) continue;
		// No need for checking.

	    $d = $options[$opt];
	    $name = ( isset ( $d['valname'] ) ?
	              $d['valname'] :
		      $d['argname'] );
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
		    $tn = 'a natural number';
		}
		elseif ( $t == 'an integer' )
		{
		    $re = '/^(|+|-)\d+$/';
		    $tn = 'integer';
		}
		elseif ( $t == 'float' )
		{
		    $re = '/^(|+|-)\d+'
		        . '(|\.\d+)'
		        . '(|(e|E)(|+|-)\d+)/';
		    $tn = 'a float';
		}
		elseif ( $t == 'args' )
		{
		    $re = '/^(\s|\w)*$/';
		    $tn = 'an argument';
		}
		if ( ! preg_match ( $re, $v ) )
		{
		    $errors[] = "$v in $name"
		              . " is not $tn";
		    continue;
		}
		else
		{
		    $values[$opt] = $v;
		    $changed = true;
		}
	    }
	}
    }

    if ( $method == 'POST' && ! $post_processed )
        exit ( 'UNACCEPTABLE HTTP POST' );


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
    pre.red {
        color: #BB0000;
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
    div.run_list {
	background-color: #F2D9D9;
	clear: both;
    }
    div.run {
	background-color: #C0FFC0;
	clear: both;
    }
    div.file {
	background-color: #C0FFC0;
	clear: both;
    }
    div.indented {
	margin-left: 20px;
    }
</style>

<script>

    function TOGGLE ( s, c )
    {
	var SWITCH = document.getElementById ( s );
	var CONTENTS = document.getElementById ( c );
	if ( CONTENTS.hidden )
	{
	    SWITCH.innerHTML = "&uarr;";
	    CONTENTS.hidden = false;
	}
	else
	{
	    SWITCH.innerHTML = "&darr;";
	    CONTENTS.hidden = true;
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
    </div>
EOT;

?>


</body>
</html>
