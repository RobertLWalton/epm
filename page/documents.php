<?php

    // File:	documents.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Tue Sep 29 05:48:51 EDT 2020

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

    function LOOK
	    ( event, filename, dir = 'documents' ) {

	var name = dir + '/' + filename;
	var src = 'look.php'
	        + '?disposition=show'
	        + '&location='
		+ encodeURIComponent ( '+home+' )
		+ '&filename='
		+ encodeURIComponent
		    ( dir + '/' + filename );
	AUX ( event, src, name );
    }

</script>

</head>
<body>

<p>
Click on a file name to display the document in the
(one and only) auxilary
window.  Holding down an alt key while doing this will
create a distinct window for the document.

<dl>

<dt><button onclick='AUX(event,"guide.html","+guide+")'>
    Guide</button></dt>
<dd>Introductory Guide for Users.</dd>

<dt><button onclick='AUX(event,"help.html","+help+")'>
    Help Page</button></dt>
<dd>Complete User Documentation.</dd>

<dt><button onclick='LOOK(event,"epm_design.pdf")'>
    EPM Design Document</button></dt>
<dd>Details of the EPM design for maintainers
    of EPM.</dd>

<dt><button onclick='LOOK(event,"epm_display.pdf")'>
    EPM Display Program Document</button></dt>
<dd>Details of the epm_display program used to create
    graphic displays for problems.</dd>

<dt><button onclick='LOOK(event,"epm_score.pdf")'>
    EPM Scoring Program Document</button></dt>
<dd>Details of the epm_score program used to score
    problem submissions.</dd>

<dt><button onclick='LOOK(event,"epm_sandbox.pdf")'>
    EPM Sandbox Program Document</button></dt>
<dd>Details of the epm_sandbox program used to sandbox
    user submitted program binaries during
    execution.</dd>

<dt><button onclick='LOOK(event,"epm_run.pdf")'>
    EPM Run Program Document</button></dt>
<dd>Details of the epm_run program used by the Run Page
    to execute EPM runs.</dd>

<dt><button onclick='LOOK(event,"epm_latex.pdf")'>
    EPM Latex Program Document</button></dt>
<dd>Run pdflatex with appropriate options and scan
    errors recorded in the .log file into the .terr
    file.</dd>

<dt><button onclick='LOOK(event,"epm.pdf")'>
    EPM Installation Program Document</button></dt>
<dd>Details of the epm program used to install and
    update EPM.</dd>

</dl>


</body>
</html>
