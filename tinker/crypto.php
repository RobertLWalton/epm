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

/*

// WARNING: async/await is not currently (Jan 2020)
// supported by Internet Explorer.  However Promises
// are also NOT supported by Internet Explorer, which
// has its own different thing.
//
async function compute_crypto()
{
    var kj = await window.crypto.subtle.importKey
	( "raw", hex2ArrayBuffer ( k ), "AES-CBC",
	  true, ["encrypt", "decrypt"] );
    var cjb = await window.crypto.subtle.encrypt
        ( { name: "AES-CBC", iv }, kj,
	  hex2ArrayBuffer ( m ) );
    var cjh = ArrayBuffer2hex ( cjb );
    console.log ( "cjh = " + cjh );
    var djb = await window.crypto.subtle.decrypt
        ( { name: "AES-CBC", iv }, kj,
	  hex2ArrayBuffer ( cjh ) );
    var djh = ArrayBuffer2hex ( djb );
    console.log ( "djh = " + djh );
}

compute_crypto();
// This runs compute_crypto as a separate task.

*/

var IMPORTKEY, ENCRYPT, DECRYPT;

if ( window.msCrypto !== undefined )
{

    IMPORTKEY = function
	    ( format, keyData, algorithm, extractable,
	      usages, callback )
    {
	window.msCrypto.subtle.importKey
	    ( format, keyData, algorithm, extractable,
		      usages )
	    .oncomplete
	        ( function(r)
		  { callback ( r.target.result ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'key import failed' ) ) } )
    }

    ENCRYPT = function
	    ( algorithm, key, data, callback )
    {
	window.msCrypto.subtle.encrypt
	    ( algorithm, key, data )
	    .oncomplete
	        ( function(r)
		  { callback ( r.target.result ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'encryption failed' ) ) } )
    }

    DECRYPT = function
	    ( algorithm, key, data, callback )
    {
	window.msCrypto.subtle.decrypt
	    ( algorithm, key, data )
	    .oncomplete
	        ( function(r)
		  { callback ( r.target.result ) } )
	    .onerror
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'decryption failed' ) ) } )
    }

} else {

    IMPORTKEY = function
	    ( format, keyData, algorithm, extractable,
	      usages, callback )
    {
	window.crypto.subtle.importKey
	    ( format, keyData, algorithm, extractable,
		      usages )
	    .then ( callback )
	    .catch
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'key import failed' ) ) } )
    }

    ENCRYPT = function
	    ( algorithm, key, data, callback )
    {
	window.crypto.subtle.encrypt
	    ( algorithm, key, data )
	    .then ( callback )
	    .catch
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'encryption failed' ) ) } )
    }

    DECRYPT = function
	    ( algorithm, key, data, callback )
    {
	window.crypto.subtle.decrypt
	    ( algorithm, key, data )
	    .then ( callback )
	    .catch
	        ( function(r)
		  { callback
		      ( new Error
		          ( 'decryption failed' ) ) } )
    }

}

var kj, cjh, djh;

function STEP1()
{
    IMPORTKEY
	( "raw", hex2ArrayBuffer ( k ), "AES-CBC",
	  true, ["encrypt", "decrypt"],
	  STEP2 );
}

function STEP2 ( key )
{
    if ( key instanceof Error )
    {
        alert ( key.message );
	return;
    }
    kj = key;
    ENCRYPT
        ( { name: "AES-CBC", iv }, kj,
	  hex2ArrayBuffer ( m ),
	  STEP3 );
}

function STEP3 ( encrypted )
{
    if ( encrypted instanceof Error )
    {
        alert ( encrypted.message );
	return;
    }
    cjh = ArrayBuffer2hex ( encrypted );
    console.log ( "cjh = " + cjh );
    DECRYPT
        ( { name: "AES-CBC", iv }, kj,
	  hex2ArrayBuffer ( cjh ),
	  STEP4 );
}

function STEP4 ( decrypted )
{
    if ( decrypted instanceof Error )
    {
        alert ( decrypted.message );
	return;
    }
    djh = ArrayBuffer2hex ( decrypted );
    console.log ( "djh = " + djh );
    console.assert ( m == mbh, "m != mbh" );
    console.assert ( k == kbh, "k != kbh" );
    console.assert ( c == cjh, "c != cjh" );
    console.assert ( d == djh, "d != djh" );
}

STEP1();


</script>
