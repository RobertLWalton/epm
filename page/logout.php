<?php

    // File:	logout.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sat Jun  6 04:46:45 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    $epm_page_type = '+init+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";
    session_unset();
    header ( 'Location: /page/login.php' );
    exit;
?>
