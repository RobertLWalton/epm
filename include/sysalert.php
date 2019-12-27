<?php
// if ( ! isset ( $GLOBALS['epm_home'] ) )
    // $epm_home not set' );

if ( isset ( $sysfail ) )
{
    $h = htmlspecialchars ( $sysfail );
    exit ( "FATAL SYSTEM ERROR: $h\n" );
}
if ( isset ( $sysalert ) )
{
    $h = htmlspecialchars ( $sysalert );
    echo "SYSALERT: $h\n";
}
?>
