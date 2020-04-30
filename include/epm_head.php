<?php

// File:    epm_head.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Thu Apr 30 01:40:00 EDT 2020

// HTML code included in the <head> section of each
// page.

?>

<title>Educational Program Manager (EPM)</title>

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
</style>
