<?php

    // File:	template.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Mar 22 13:18:55 EDT 2020

    // Edits problem option page.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( ! isset ( $_SESSION['EPM_PROBLEM'] ) )
    {
	header ( 'Location: /page/problem.php' );
	exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // require "$epm_home/include/debug_info.php";

    $problem = $_SESSION['EPM_PROBLEM'];
    $email = $_SESSION['EPM_EMAIL'];
    $uid = $_SESSION['EPM_UID'];
    $probdir = "users/$uid/$problem";
        // These are needed to require epm_make.php.

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
	// since looking at templates is not done that
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

    // Compute data for Template Commands in $template_
    // cache.
    //
    load_template_cache();
    ksort ( $template_cache, SORT_NATURAL );

?>

<html>
<head>
<style>
    @media screen and ( max-width: 1023px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	}
    }
    @media screen and ( min-width: 1024 ) {
	:root {
	    --font-size: 16px;
	    --large-font-size: 20px;
	    width: 1280px;
	    font-size: var(--font-size);
	    overflow: scroll;
	}
    }
    .indented {
	margin-left: 20px;
    }
    h5 {
        font-size: var(--large-font-size);
	margin: 0 0 0 0;
	display:inline;
    }
    pre, button {
	display:inline;
        font-size: var(--font-size);
    }
    pre {
	font-family: "Courier New", Courier, monospace;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
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
<div class='root'>

<?php 

    $template_help = HELP ( 'template-page' );
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
    &nbsp;&nbsp;
    <button type='submit'
            formaction='option.php'>Option Page
    </button>
    </td>
    <td style='text-align:right'>
    $template_help</td>
    </table>
    </form>
    </div>
    <br>
EOT;
    $tcount = 0;
    $description = "";
    foreach ( $template_cache as $template => $e )
    {
        $j = get_template_json ( $template );
	if ( ! isset ( $j['COMMANDS'] ) ) continue;
	$tpretty = pretty_template ( $template );
	$tcommands = $j['COMMANDS'];
	$tcount += 1;
	echo <<<EOT
	     <div style='display:inline-block;
	                 padding-left:5px'>
	     <button type='button'
	             id='template{$tcount}_button'
		     onclick='TOGGLE_BODY
		         ( "template$tcount",
			   "$tpretty Commands" )'
		     title='Show $tpretty Commands'>
	     <pre id='template{$tcount}_mark'
	          >&darr;</pre>
	     </button>
	     &nbsp;&nbsp;
	     <pre>$tpretty</pre>
	     </div>
EOT;
	$description .= <<<EOT
	     <div id='template{$tcount}_body' hidden
	          style='padding-bottom:5px'>
	     <h5>$tpretty:</h5>
	     <br>
	     <div class='indented'>
EOT;
         foreach ( $tcommands as $c )
	     $description .= "<pre>$c</pre><br>";
	 $description .= "</div></div>";
    }
    echo <<<EOT
         <div>
	 $description
	 </div>
EOT;
?>


</div>
</body>
</html>

