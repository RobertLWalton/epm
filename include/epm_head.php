<?php

// File:    epm_head.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu May  7 15:34:29 EDT 2020

// HTML code included at the beginning of the <head>
// section of each page.

$title = pathinfo ( $epm_self, PATHINFO_FILENAME );
$title = ucfirst ( $title);
$title = "EPM $title Page";

echo "<title>$title</title>";

?>

<style>
    @media screen and ( max-width: 1365px ) {
	:root {
	    --font-size: 1.1vw;
	    --large-font-size: 1.3vw;
	    --indent: 1.3vw;
	}
    }
    @media screen and ( min-width: 1366px ) {
	:root {
	    width: 1366px;

	    --font-size: 16px;
	    --large-font-size: 20px;
	    --indent: 20px;
	}
    }

    :root {
	/* Background Colors (Light)
	 */
	--bg-cyan: #96F9F3;
	--bg-tan: #F2D9D9;
	--bg-green: #C0FFC0;
	--bg-blue: #B3E6FF;
	--bg-violet: #FFCCFF;
	--bg-yellow: #F5F81A;
	--bg-orange: #FFB0B0;
	/* Highlight Colors
	 */
	--hl-orange: #FF6347;
	--hl-purple: #CC00FF;
	--hl-red: #FF003D;
    }

    .indented {
	margin-left: var(--indent);
    }
    strong {
        font-size: var(--large-font-size);
	font-weight: bold;
    }
    pre.problem {
        color: var(--hl-purple);
        font-size: var(--large-font-size);
	padding: 0px 5px 0px 5px;
	border: 2px solid red;
    }
    form {
	margin: 0px;
	padding: 0px;
	display:inline;
    }
    button, input, select {
	display:inline;
        font-size: var(--font-size);
	margin-bottom: 5px;
    }
    pre {
	display:inline;
        font-size: var(--font-size);
	font-family: "Courier New", Courier, monospace;
    }
    div.errors, div.notices {
	background-color: var(--bg-yellow);
	padding-bottom: 5px;
    }
    div.warnings {
	background-color: #FFC0FF;
	padding-bottom: 5px;
    }
    div.manage {
	background-color: var(--bg-cyan);
	padding-bottom: 5px;
    }
</style>
