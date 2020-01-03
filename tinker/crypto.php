<?php

$iv = hex2bin ( "00000000000000000000000000000000" );
echo "IV length " . strlen ( $iv ) . "<br>";
$m = bin2hex ( random_bytes ( 16 ) );
echo "M $m<br>";
$k = bin2hex ( random_bytes ( 16 ) );
echo "K $k<br>";
$c = bin2hex ( openssl_encrypt
                   ( hex2bin ( $m ), "aes-128-cbc",
		     hex2bin ( $k ), OPENSSL_RAW_DATA, $iv ) );
echo "C $c<br>";
$d = bin2hex ( openssl_decrypt
                   ( hex2bin ( $c ), "aes-128-cbc",
                     hex2bin ( $k ), OPENSSL_RAW_DATA, $iv ) );
echo "D $d<br>";
echo "M " . ( $m == $d ? "==" : "!=" ) . " D<br>";

echo <<<EOT
<script>
var m = "$m";
var k = "$k";
var c = "$c";
var d = "$d";
console.log ( "m = " + m );
console.log ( "k = " + k );
console.log ( "c = " + c );
console.log ( "d = " + d );
</script>
EOT

?>

<script>
function ArrayBuffer2hex ( buffer )
{
    return Array
        .from ( new Uint8Array(buffer) )
	.map ( b => b.toString(16).padStart(2,'0') )
        .join('');
}

function hex2ArrayBuffer ( hex )
{
    var length = hex.length / 2;
    var result = new ArrayBuffer ( length );
    var view = new Uint8Array ( result );
    for ( var i = 0; i < length; ++ i )
        view[i] = parseInt ( hex.substr ( 2*i, 2 ), 16 );
    return result;
}

var mb = hex2ArrayBuffer ( m );
var mbh = ArrayBuffer2hex ( mb );
console.log ( "mbh = " + mbh );

var kb = hex2ArrayBuffer ( k );
var kbh = ArrayBuffer2hex ( kb );
console.log ( "kbh = " + kbh );

var cb = hex2ArrayBuffer ( c );
var cbh = ArrayBuffer2hex ( cb );
console.log ( "cbh = " + cbh );

var iv = new Uint8Array ( 16 );
var ivh = ArrayBuffer2hex ( iv );
console.log ( "ivh = " + ivh );

var kp = window.crypto.subtle.importKey
    ( "raw", hex2ArrayBuffer ( k ), "AES-CBC",
      true, ["encrypt", "decrypt"] );
kp.then(KEY_DONE);

var keysave;
function KEY_DONE ( key )
{
    keysave = key;
    var cp = window.crypto.subtle.encrypt
        ( { name: "AES-CBC", iv }, key,
	  hex2ArrayBuffer ( m ) );
    cp.then ( C_DONE );
}

function C_DONE ( cb )
{
    var cj = ArrayBuffer2hex ( cb );
    console.log ( "cj = " + cj );
    var dp = window.crypto.subtle.decrypt
        ( { name: "AES-CBC", iv }, keysave,
	  hex2ArrayBuffer ( cj ) );
    dp.then ( D_DONE );
}
function D_DONE ( db )

{
    var dj = ArrayBuffer2hex ( db );
    console.log ( "dj = " + dj );
}


</script>
