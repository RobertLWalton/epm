<?php
if ( ! isset ( $GLOBALS['epm_data'] ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );

if ( isset ( $sysfail ) )
    exit ( "FATAL SYSTEM ERROR: $sysfail\n" );

echo "SYSALERT: $sysalert\n";
?>
