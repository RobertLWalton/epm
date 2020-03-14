<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Mar 14 13:41:29 EDT 2020

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
	    if ( $type == 'args' )
	    {
	        if ( $hasrange )
		    $errors[] =
		        "option $opt has type 'args'" .
			" and also has a 'range'";
	    }
	    elseif ( ! $hasrange )
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

    if ( $edit )
    {
    }
    else // if ! $edit
    {
	echo <<<EOT
        <h5>Values:</h5>
	<div class='indented'>
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
	    echo "<tr><td>$valname</td><td>";
	    if ( isset ( $d['values'] ) )
	    {
		echo "<table><tr>";
	        foreach ( $d['values'] as $val )
		    echo "<td><pre>$val</pre></td>";
		echo "</tr></table>";
	    }
	    elseif ( isset ( $d['type'] ) )
	    {
	        if ( isset ( $d['range'] ) )
		{
		    echo "<td style='text-align:" .
		         "right;padding-left:" .
			 "5px'><pre>$v</pre></td>";
		    $t = $d['type'];
		    $r = $d['range'];
		    echo "<td style='padding-left:" .
		         "10px'><pre>" .
		         $d['description'] .
			 "</pre>";
		    echo "<td style='padding-left:" .
		         "10px'><pre>" .
		         "($t number in range" .
			 " [{$r[0]},{$r[1]}])" .
			 "</pre></td>";
		}
	    }
	    else
	        echo "has neither 'values' or 'type'";
	    echo "</td></tr>";
	}
	echo "</table></div>";

	echo <<<EOT
        <h5>Command Arguments:</h5>
	<div class='indented'>
	<table>
EOT;
	foreach ( $argnames as $argname => $optlist )
	{
	    if ( count ( $optlist ) == 0 )
	        ERROR ( "\$argnames[$argname] is" .
		        " empty" );
	    echo "<tr><td>$argname</td>";
	    echo "<td colspan='10' style='" .
	         "padding-left:5px'><pre>" .
		 $options[$argname]['description'] .
		 "</pre></td>";
	    echo "</tr>";
	    foreach ( $optlist as $opt )
	    {
		$d = $options[$opt];
		$description = $d['description'];
		$default = $d['default'];
		$iv = $inherited[$opt];
		$vs = $d['values'];
		echo "<tr><td></td><td" .
	             " style='padding-left:5px'>";
		foreach ( $vs as $v )
	             echo "<pre style='border-style:" .
		          "solid;border-width:1px'>" .
			  " $v </pre>";
		echo "</td><td style='padding-left:" .
		     "5px'><pre>$description</pre>" .
		     "</td></tr>";
	    }
	}
	echo "</table></div>";
    }

?>


</body>
</html>
