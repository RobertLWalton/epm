<?php

// File:    maintenance_parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Fri Jun 26 02:52:44 EDT 2020

// The authors have placed EPM (its files and the
// content of these files) in the public domain; they
// make no warranty and accept no liability for EPM.

// Per web site EPM maintenance parameters.  An edited
// version of this file located in the web directory
// of the server.  This file is included by bin/epm and
// similar programs by
//
//    $epm_web = ...
//    require "$epm_web/parameters.php";
//    require "$epm_web/maintenance_parameters.php";

$epm_backup = $epm_home . '/../epm_backup';
    // The directory in which backups are placed.

// A backup backs up the contents of the $epm_data
// directory, as per, roughly:
//
//	cd $epm_data; tar zcf $epm_backup_directory .
//
// A backup is a level 0 or level 1 GNU gzip compressed
// tar.  After a level 0 is taken, following level 1's
// before the next level 0 are its children.  To restore
// a child you first reload its parent, and then the
// child.
//
// Backups are named `NAME-TIME-LEVEL.tgz'.  NAME is
// the value of $epm_backup_name.  TIME is the time
// in $epm_time_format.  Sorting the backup names gives
// a time-sorted order.  LEVEL is the backup level, 0 or
// 1.
//
// A level 0 .tgz file has an associated .snar file.
//
// When the number of backups exceeds ROUND, the value
// of $epm_backup_round, the oldest excess are declared
// obsolete.  When a level 0 and ALL its children are
// obsolete, they are all deleted.
//
$epm_backup_name = 'EPM-BACKUP';
$epm_backup_round = 30;
    // WARNING: $epm_backup_name should have only
    //          letters, digits, and `-', BUT NOT `_'.

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
