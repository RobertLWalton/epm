<?php

    // File:	downloads.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Sep 10 16:35:07 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays or downloads files in the
    // $epm_home/downloads directory.

    $epm_page_type = '+no-post+';
        // This page does no POSTing.
    require __DIR__ . '/index.php';

    // require "$epm_home/include/debug_info.php";

    if ( $epm_method != 'GET' )
        exit ( 'UNACCEPTABLE HTTP METHOD ' .
	       $epm_method );

?>

<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>

<style>
    :root {
	--font-size: 3.0vw;
	--large-font-size: 4.0vw;
	--indent: 4.0vw;
    }
    button {
        margin-left: var(--indent);
    }
    button, p {
        font-size: var(--font-size);
    }
    h2 {
        font-size: var(--large-font-size);
    }

</style>

<script>

    var controls_down = 0;
	// Non-zero if some control key is down.

    function KEYDOWN ( event )
    {
	if ( event.code == 'ControlLeft'
	     ||
	     event.code == 'ControlRight' )
	    ++ controls_down;
    }

    function KEYUP ( event )
    {
	if ( event.code == 'ControlLeft'
	     ||
	     event.code == 'ControlRight' )
	    -- controls_down;
    }

    window.addEventListener ( 'keydown', KEYDOWN );
    window.addEventListener ( 'keyup', KEYUP );

    function LOOK ( filename ) {

	var name = 'downloads/' + filename;
	var disposition = 'show';
	if ( controls_down != 0 )
	{
	    name = '_blank';
	    disposition = 'download';
	}
	var src = 'look.php'
	        + '?disposition=' + disposition
	        + '&location='
		+ encodeURIComponent ( '+home+' )
		+ '&filename='
		+ encodeURIComponent
		    ( 'downloads/' + filename );
	SHOW ( name, src );
    }

</script>

</head>
<body>

<p>
Click on file name to display file contents.
<p>
Click on file name while holding a control key down
to download the file.

<h2> Reverser Problem Solutions:</h2>
<p>
<button onclick='LOOK("reverser.c")'>
        reverser.c</button>
<button onclick='LOOK("reverser.cc")'>
        reverser.cc</button>
<button onclick='LOOK("reverser.java")'>
        reverser.java</button>
<button onclick='LOOK("reverser.py")'>
        reverser.py</button>
</ul>

<h2> Latex Template and Examples:</h2>
<p>
<button onclick='LOOK("template.tex")'>
     template.tex</button>
<button onclick='LOOK("reverser.tex")'>
     reverser.tex</button>

<h2> Generate Program Template and Examples:</h2>
<p>
<button onclick='LOOK("epm_generate.cc")'>
     epm_generate.cc</button>
<button onclick='LOOK("generate-annual.cc")'>
     generate-annual.cc</button>
<button onclick='LOOK("generate-valuable.cc")'>
     generate-valuable.cc</button>

<h2> Filter Program Template and Examples:</h2>
<p>
<button onclick='LOOK("epm_filter.cc")'>
     epm_filter.cc</button>
<button onclick='LOOK("filter-valuable.cc")'>
     filter-valuable.cc</button>

</body>
</html>


