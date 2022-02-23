<?php

// File:    epm_head.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed Feb 23 10:45:50 EST 2022

// The authors have placed EPM (its files and the
// content of these files) in the public domain;
// they make no warranty and accept no liability
// for EPM.

// HTML code included at the beginning of the <head>
// section of each page.

$title = pathinfo ( $epm_self, PATHINFO_FILENAME );
$title = ucfirst ( $title);
if ( isset ( $problem ) )
    $title = "EPM $problem $title Page";
else
    $title = "EPM $title Page";

echo "<title>$title</title>";

?>

<style>
    @media screen and ( max-width: 1365px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	    --indent: 1.3vw;
	    --radius: 0.55vw;
	    --pad: 0.55vw;
	    --border-width: 0.25vw;
	}
    }
    @media screen and ( min-width: 1366px ) {
	:root {
	    width: 1366px;

	    --font-size: 16px;
	    --large-font-size: 20px;
	    --indent: 20px;
	    --radius: 8px;
	    --pad: 8px;
	    --border-width: 3px;
	}
    }

    :root {
	font-family: "Times New Roman", Times, serif;
	/* Background Colors (Light)
	 */
	--bg-cyan: #96F9F3;
	--bg-tan: #F2D9D9;
	--bg-dark-tan: #E5B3B3;
	--bg-green: #C0FFC0;
	--bg-dark-green: #00FF00;
	--bg-blue: #B3E6FF;
	--bg-dark-blue: #80D4FF;
	--bg-violet: #FFCCFF;
	--bg-yellow: #F5F81A;
	--bg-orange: #FFCC00;
	/* Highlight Colors
	 */
	--hl-orange: #FF6347;
	--hl-purple: #CC00CC;
	--hl-red: #FF0000;
	--hl-blue: #0000FF;
    }

    .indented {
	margin-left: var(--indent);
    }
    .center {
        text-align: center;
    }
    strong {
        font-size: var(--large-font-size);
	font-weight: bold;
    }
    pre.problem {
        color: var(--hl-purple);
        font-size: var(--large-font-size);
	padding: 0px calc(0.5*var(--font-size))
	         0px calc(0.5*var(--font-size));
	border: 2px solid red;
    }
    form {
	margin:  0px;
	padding: 0px;
	display: inline;
    }
    button, input, select {
	display:inline;
        font-size: var(--font-size);
	margin-bottom: calc(0.5*var(--font-size));
    }
    button {
        padding: 1px 6px;
	border-width: var(--border-width);
	border-color: white;
    }
    select, input {
        padding: 1px 6px;
	border-width: var(--border-width);
	border-color: gainsboro;
	background-color: white;
    }
    pre {
	display:inline;
        font-size: var(--font-size);
	font-family: "Courier New", Courier, monospace;
    }
    div.errors, div.notices {
	background-color: var(--bg-yellow);
	padding-bottom: calc(0.5*var(--font-size));
    }
    div.warnings {
	background-color: var(--bg-orange);
	padding-bottom: calc(0.5*var(--font-size));
    }
    div.notice {
	background-color: var(--bg-violet);
	padding-bottom: calc(0.5*var(--font-size));
    }
    div.manage {
	background-color: var(--bg-cyan);
	padding: calc(0.5*var(--font-size)) 0px;
    }
    div.checkbox {
        height: var(--font-size);
        width: calc(2*var(--font-size));
	display: inline-block;
	border: 2px solid black;
	border-radius: var(--radius);
	background-color: white;
	cursor: crosshair;
    }
    div.list-description {
	background-color: var(--bg-green);
	margin-left: var(--indent);
        font-size: var(--font-size);
    }
    div.list-description p, div.list-description pre {
        margin: 0px;
        padding: 0.25ex var(--pad);
    }
    .terms {
	font-size: var(--large-font-size);
	width: calc(100%-var(--pad));
	background-color: var(--bg-orange);
	border: var(--pad) solid red;
	padding-top: calc(0.5*var(--pad));
	padding-left: var(--pad);
    }
</style>
