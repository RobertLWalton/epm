<?php

// File:    maintenance_parameters.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Sun Jun 28 13:02:06 EDT 2020

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

// To set up an EPM instance you need the following
// directories:
//
//   H	The `epm' home directory containing `page',
//      `template', etc subdirectories.
//   W	$_SERVER['DOCUMENT_ROOT']/ROOT.  Directory in
//       which you place an edited copy of this file.
//   D	Directory that will contain data.  See below on
//      how to choose the name of this directory.
//
// You also need to put the UNIX account you are using
// in the web server's group.  The directories above
// will get this group and g+s permission so all their
// descendants will get the web server's group.  Note
// that H can be shared among serval EPM servers with
// different W an D, as long as all use the same web
// server group.
//
//   IMPORTANT:
//       None of the above directories should be a
//       descendant of another of these directories.
//
//   IMPORTANT:
//	o+x permissions must be set on D and ALL its
//      ancestors, because running JAVA in epm_sandbox
//      requires that the path to the JAVA .class file
//      be traversable by `others'.  Because of this,
//      the last component of the name D should have a
//      12 digit random number in it that is unique to
//      W, and the parent of this last component should
//      have o-r permissions so the name D acts like an
//      impenatrable password.
//
//   IMPORTANT:
//	Ancestors of D must have o+x permission.
//	Ancestors of H and W must have either o+x
//	permission, or have the web server's group and
//	g+x permission.
//   
// During setup the above rules will be checked. During
// setup and subsequent execution:
//
//   * The above directories and all their descendants
//     will be given the web server's group.
//
//   * The above directories and all their descendant
//     directories will be given g+srx permission.
//
//   * H and all its descendant directories get
//     g-w,g+rx,o-rw,o+x permissions.
//
//   * D and all its descendant directories get
//     g+wx,o-rw,o+x permissions.
//
//   * The descendent non-directories of H and W get
//     g-wx,g+r,o-rwx permission, except that
//     executables (all installed in H/bin) get
//     g+x permission.
//
//   * The descendent non-directories of D get
//     g-x,g+rw,o-rwx permission, except that
//     executables get go+x permission.
//
//   * Note: W has no descendant directories.
//     
// Then to install:
//
//   * Populate H from github.
//   * Create W and D
//   * Install an edited version of epm_parameters.php
//     in W.
//   * Install an edited version of
//     epm_maintenance parameters.php in W.
//   * Then execute:     	     H/bin/epm setup W
//   * Then perform the actions in:  D/TODO



//
//	chgrp WEB-SERVERS-GROUP \
//	      R `find H` D
//	chmod g+s \
//	      R `find H -type d` D
//	chmod g-w R `find H`
//	chmod g+w D
//
//	cd R
//	ln -s H/page .
//	ln -s H/page/index.php .
//	cp -p H/page/parameters.php .
//	chmod u+w parameters.php
//	<edit R/parameters.php>


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
