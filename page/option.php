<?php

    // File:	option.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Mar 12 20:51:43 EDT 2020

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

    // Process template option files, with overriding
    // files processed last.
    //
    $optn_files = [];
    $optn_files[] =
	[$epm_home, "template/template.optn"];
    $optn_files[] =
	[$epm_data, "template/template.optn"];
    $optn_files[] =
	[$epm_data, "/users/$uid/template.optn"];

    $options = [];
    foreach ( $optn_files as $e )
    {
	$r = $e[0];
	$f = $e[1];
	if ( ! is_readable ( "$r/$f" ) ) continue;
	$j = get_json ( $r, $f );

	// template.optn values are 2D arrays.
	//
	foreach ( $j as $opt => $description )
	foreach ( $description as $key => $value )
	    $options[$opt][$key] = $value;
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

    // TBD
    //
    if ( isset ( $_POST['submit'] ) )
    {
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
