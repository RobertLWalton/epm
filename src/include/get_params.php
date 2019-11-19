<?php

// File:    get_params.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Tue Nov 19 06:11:40 EST 2019

if ( ! isset ( $epm_data ) )
    exit ( 'SYSTEM ERROR: $epm_data not set' );

$file = "$epm_data/admin/admin.params";
if ( is_readable ( $file ) )
{
    $contents = file_get_contents ( $file );
    if ( $contents )
        $params = json_decode ( $contents, true );
}
if ( ! isset ( $params ) ) $params = [];
$_SESSION['epm_admin_params'] = $params;

$userid = $_SESSION['userid'];
$file = "$epm_data/users/user$userid/user.params";
if ( is_readable ( $file ) )
{
    $contents = file_get_contents ( $file );
    if ( $contents )
        $params = json_decode ( $contents, true );
}
if ( ! isset ( $params ) ) $params = [];
$_SESSION['epm_user_params'] = $params;

?>
