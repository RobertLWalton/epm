<?php

// File:    maintenance_parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sat Jun 20 03:31:36 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// Per web site EPM maintenance parameters.  An edited
// version of this file located in the admin directory
// of the server.  This file is included by bin/epm and
// similar programs by
//
//    $epm_web = ...
//    require "$epm_web/parameters.php";
//    require
//	"$epm_data/admin/maintenance_parameters.php";

$epm_library = $epm_home . '/../epm_public';
    // $epm_library/projects is the project library from
    // which projects and problems may be imported and
    // to which they may be exported.  It is generally
    // under git, but EPM does not execute git per se.
    //
    // WARNING:
    //   This is only a test setting.  Reset this to
    //   the location of the project library.

?>
