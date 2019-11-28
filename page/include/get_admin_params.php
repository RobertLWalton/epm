<?php

// File:    get_admin_params.php
// Author:  Robert L Walton <walton@acm.org>
// Date:    Wed Nov 27 00:10:15 EST 2019

if ( ! isset ( $epm_data ) )
    exit ( 'ACCESS ERROR: $epm_data not set' );
if ( ! isset ( $userid ) )
    exit ( 'ACCESS ERROR: $userid not set' );
if ( ! isset ( $_SESSION["epm_root"] ) )
    exit ( 'ACCESS ERROR: $_SESSION["epm_root"] not set' );
$epm_root = $_SESSION['epm_root'];

// Get administrative parameters:
//
$file = "$epm_data/admin/admin.params";
$params = [];
if ( is_readable ( $file ) )
{
    $contents = file_get_contents ( $file );
    if ( ! $contents )
        exit ( "cannot read $file" );
    $params = json_decode ( $contents, true );
    if ( ! $params )
        exit ( "cannot decode json $file" );
}

// Set administrative parameter defaults:
//
if ( ! isset ( $params['upload_target_ext'] ) )
    $params['upload_target_ext'] = [
        "c" =>  "",
	"cc" => "",
	"java" => "class",
	"py" => "pyc",
	"tex" => "pdf",
	"in" => "out"];

if ( ! isset ( $params['display_file_ext'] ) )
    $params['display_file_ext'] = [
        "c", "cc", "java", "py", "tex",
	"in", "gin", "out", "fout", "test", "ftest",
	"err", "log", "fls"];

if ( ! isset ( $params['upload_maxsize'] ) )
    $params['upload_maxsize'] = 262144; // 256 kb

if ( ! isset ( $params['template_dirs'] ) )
    $params['template_dirs'] =
        [ "$epm_root/template" ];

// Get user administrative parameter overrides:
//
$file = "$epm_data/admin/user$userid.params";
$uparams = [];
if ( is_readable ( $file ) )
{
    $contents = file_get_contents ( $file );
    if ( ! $contents )
        exit ( "cannot read $file" );
    $uparams = json_decode ( $contents, true );
    if ( ! $uparams )
        exit ( "cannot decode json $file" );
}

foreach ( $uparams as $key => $value )
    $params[$key] = $value;

$_SESSION['epm_admin_params'] = $params;

?>
