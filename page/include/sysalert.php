<?php
// if ( ! isset ( $GLOBALS['epm_root'] ) )
    // $epm_root not set' );

if ( isset ( $sysfail ) )
    exit ( "FATAL SYSTEM ERROR: $sysfail\n" );
if ( isset ( $sysalert ) )
    echo "SYSALERT: $sysalert\n";
?>
