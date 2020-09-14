<?php

    // File:	template.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Sep 14 04:25:41 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Edits problem option page.

    $epm_page_type = '+no-post+';
        // This page does no POSTing.
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );

    require "$epm_home/include/epm_template.php";

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
    @media screen and ( max-width: 1279px ) {
	:root {
	    --font-size: 1.4vw;
	    --large-font-size: 1.6vw;
	    --indent: 1.6vw;
	}
    }
    @media screen and ( min-width: 1280px ) {
	:root {
	    width: 1280px;

	    --font-size: 16px;
	    --large-font-size: 20px;
	    --indent: 20px;
	}
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

    echo <<<EOT
    <div class='manage'>
    <table style='width:100%'>
    <tr>
    <td>
    <strong title='Login Name'>$lname</strong>
    </td>
    <td>
    </td>
    <td style='text-align:right'>
    <button type='button' id='refresh'
            onclick='location.replace
	        ("template.php")'>
	&#8635;</button>
    <button type='button'
            onclick='HELP("template-page")'>
	?</button>
    </td>
    </tr>

    <tr>
    <td colspan='3' style='text-align:center'>
    <h2>Template Viewer</h2></td>
    </tr></table>
    </div>
    <div class='toc'>
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
	           'LOCAL-REQUIRES', 'REMOTE-REQUIRES',
		   'KEEP', 'CHECKS']
			  as $key )
	{
	    if ( ! isset ( $j[$key] ) ) continue;
	    $keylist = [];
	    foreach ( $j[$key] as $item )
	    {
	        if ( is_array ( $item ) )
		    $keylist[] = "["
		               . implode ( ",", $item )
			       . "]";
		else
		    $keylist[] = $item;
	    }
	    $description .= "$key: <pre>"
	                  . implode ( ", ", $keylist )
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

