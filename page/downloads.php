<?php

    // File:	downloads.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Sep 14 05:53:11 EDT 2020

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

    function LOOK (event, filename ) {

	var name = 'downloads/' + filename;
	var disposition = 'show';
	if ( event.ctrlKey )
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
	if ( disposition == 'download' )
	    window.open ( src, '_blank' );
	else
	    AUX ( event, src, name );
    }

</script>

</head>
<body>

<p>
Click on a file name to display the file contents in the
(one and only) auxilary
window.  Holding down an alt key while doing this will
create a distinct window for the file contents.
<p>
Click on a file name while holding a control key down
to download the file.

<h2> Reverser Problem Solutions:</h2>
<p>
<button onclick='LOOK(event,"reverser.c")'>
        reverser.c</button>
<button onclick='LOOK(event,"reverser.cc")'>
        reverser.cc</button>
<button onclick='LOOK(event,"reverser.java")'>
        reverser.java</button>
<button onclick='LOOK(event,"reverser.py")'>
        reverser.py</button>
</ul>

<h2> Latex Template and Examples:</h2>
<p>
<button onclick='LOOK(event,"template.tex")'>
     template.tex</button>
<button onclick='LOOK(event,"reverser.tex")'>
     reverser.tex</button>

<h2> Generate Program Template and Examples:</h2>
<p>
<button onclick='LOOK(event,"epm_generate.cc")'>
     epm_generate.cc</button>
<button onclick='LOOK(event,"generate-annual.cc")'>
     generate-annual.cc</button>
<button onclick='LOOK(event,"generate-valuable.cc")'>
     generate-valuable.cc</button>

<h2> Filter Program Template and Examples:</h2>
<p>
<button onclick='LOOK(event,"epm_filter.cc")'>
     epm_filter.cc</button>
<button onclick='LOOK(event,"filter-valuable.cc")'>
     filter-valuable.cc</button>

</body>
</html>


