#! /usr/bin/php
<?php

$iv = hex2bin ( "00000000000000000000000000000000" );
echo "IV " . strlen ( $iv ) . "\n";
$m = bin2hex ( random_bytes ( 16 ) );
echo "M $m\n";
$k = bin2hex ( random_bytes ( 16 ) );
echo "K $k\n";
$c = bin2hex ( openssl_encrypt ( $m, "aes-128-cbc", $k, OPENSSL_RAW_DATA, $iv ) );
echo "C $c\n";
$d = openssl_decrypt ( hex2bin( $c ), "aes-128-cbc", $k, OPENSSL_RAW_DATA, $iv );
echo "D $d\n";

?>
