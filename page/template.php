<?php

    // File:	template.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Apr 30 01:28:43 EDT 2020

    // Edits problem option page.

    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    $method = $_SERVER['REQUEST_METHOD'];
    if ( $method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' . $method );

    // require "$epm_home/include/debug_info.php";

    $email = $_SESSION['EPM_EMAIL'];
    $uid = $_SESSION['EPM_UID'];
        // These are needed to require epm_template.php.

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

    // Compute data for Template Commands in $template_
    // cache.
    //
    load_template_cache();
    ksort ( $template_cache, SORT_NATURAL );

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    pre, button, input {
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
    div.title {
	background-color: #F2D9D9;
	padding-bottom: 5px;
        font-size: var(--large-font-size);
	width: 100%;
	text-align: center;
    }
    div.toc {
	background-color: #F2D9D9;
	padding-bottom: 5px;
    }
    div.description {
	background-color: #B3E6FF;
    }
    div.commands {
	background-color: #F5F81A;
	margin-left: 20px;
    }
    div.requires {
	background-color: #C0FFC0;
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
	if ( BODY.style.display == 'none' )
	{
	    BUTTON.style.backgroundColor = 'black';
	    BUTTON.title = "Hide " + thing;
	    BODY.style.display = 'block';
	}
	else
	{
	    BUTTON.style.backgroundColor = 'white';
	    BUTTON.title = "Show " + thing;
	    BODY.style.display = 'none';
	}
    }

</script>

</head>
<body>
<div class='root'>

<?php 

    if ( ! isset ( $_GET['subwindow'] ) )
	echo <<<EOT
	<div class='manage'>
	<form method='GET' style='margin-bottom:0'>
	<table style='width:100%'><tr>
	<td>
	<strong>User:</strong> <input type='submit'
	                value='$email'
			formaction='user.php'
			title='click to see user
			       profile'>
	</td>
	<td style='padding-left:20px'>
	<strong>Go To:</strong>
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
	</tr></table>
	</form>
	</div>
EOT;
    $template_help = HELP ( 'template-page' );
    echo <<<EOT
    <div class='toc'>
    <table style='width:100%'><tr>
    <td style='width:10%'></td>
    <td style='width:80%;text-align:center'>
    <h2>Template Viewer</h2></td>
    <td style='width:10%;text-align:right'>
    $template_help</td>
    </tr></table>
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
		     title='Show $tpretty Commands'
		     style='background-color:white;
		            border-style:none;
			    margin:2px'>
	     &nbsp;</button>
	     &nbsp;&nbsp;
	     <pre>$tpretty</pre>
	     </div>
EOT;
	$description .= <<<EOT
	     <div id='template{$tcount}_body'
	          style='display:none'
	          style='padding-bottom:5px'>
	     <strong>$tpretty:</strong>
	     <br>
	     <div class='commands'>
EOT;
        foreach ( $tcommands as $c )
	    $description .= "<pre>$c</pre><br>";
	$description .= "</div><div class='requires'>";
	foreach ( ['REQUIRES', 'CREATABLE',
	           'LOCAL-REQUIRES', 'REMOTE-REQUIRES']
			  as $key )
	{
	    if ( ! isset ( $j[$key] ) ) continue;
	    $description .= "$key: <pre>"
	                  . implode ( ", ", $j[$key] )
			  . "</pre><br>";
	}
	if ( isset ( $j['CONDITION'] ) )
	    $description .= "CONDITION: <pre>"
	                  . $j['CONDITION']
			  . "</pre><br>";
	$description .= "</div></div>";
    }
    echo <<<EOT
	 </div>
         <div class='description'>
	 $description
	 </div>
EOT;
?>


</div>
</body>
</html>

