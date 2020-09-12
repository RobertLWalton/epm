<?php

    // File:	documents.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Sep 12 07:09:38 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Displays PDF files in the $epm_home/documents
    // directory.

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
    dt  {
        margin-top: var(--indent);
    }
    button {
        margin-left: var(--indent);
        font-size: var(--font-size);
    }
    dd  {
        font-size: var(--font-size);
    }
    h2 {
        font-size: var(--large-font-size);
    }

</style>

<script>

    function LOOK ( filename, dir = 'documents' ) {

	var name = dir + '/' + filename;
	var src = 'look.php'
	        + '?disposition=show'
	        + '&location='
		+ encodeURIComponent ( '+home+' )
		+ '&filename='
		+ encodeURIComponent
		    ( dir + '/' + filename );
	SHOW ( name, src );
    }

</script>

</head>
<body>

<dl>

<dt><button onclick='SHOW("_blank","guide.html")'>
    Guide</button></dt>
<dd>Introductory Guide for Users.</dd>

<dt><button onclick='SHOW("_blank","help.html")'>
    Help Page</button></dt>
<dd>Complete User Documentation.</dd>

<dt><button onclick='LOOK("epm_design.pdf")'>
    EPM Design Document</button></dt>
<dd>Details of the EPM design for maintainers of EPM.</dd>

<dt>

</dl>


</body>
</html>



