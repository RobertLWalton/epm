<?php

// File:    epm_head.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue May  5 14:17:06 EDT 2020

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
    .indented {
	margin-left: var(--indent);
    }
    strong {
        font-size: var(--large-font-size);
	font-weight: bold;
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
	background-color: #F5F81A;
	padding-bottom: 5px;
    }
    div.warnings {
	background-color: #FFC0FF;
	padding-bottom: 5px;
    }
    div.manage {
	background-color: #96F9F3;
	padding-bottom: 5px;
    }
</style>
