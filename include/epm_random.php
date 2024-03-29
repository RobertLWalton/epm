<?php

    // File:	epm_random.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Thu Jun  1 18:07:14 EDT 2023

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    // Rarely used functions for generating random
    // numbers.

    if ( ! isset ( $epm_data ) )
	exit ( 'ACCESS ERROR: $epm_data not set' );

    // Return a string of 16 random bytes.  Similar
    // to random_bytes(16) but does not use entropy
    // and instead uses a cryptographic pseudo-random
    // number generator based on aes-128-cbc and
    // seeded on first use for an EPM server from
    // /dev/random.  Data is stored between calls
    // in admin/+random+.
    //
    // Errors cause exit with error message.  None
    // are likely if the EPM server is not completely
    // broken.
    //
    // The data stored in admin/+random+ consists
    // of a 16-byte random binary `key' that is never
    // changed after first initialization, followed by
    // a 16-byte pseudo-random binary `value' that is
    // update every time a new random number is needed.
    //
    // The value is updated by adding bytes from
    // microtime to its last byte and then encrypting
    // it with the key.
    //
    function random_16_bytes()
    {
        global $epm_data;

        $f = 'admin/+random+';

	$first_time = ! file_exists ( "$epm_data/$f" );
	    // Compute this before fopen of $f.

	$wdesc = @fopen
	    ( "$epm_data/$f",
	      ( $first_time ? 'wb' : 'r+b' ) );
	if ( $wdesc === false )
	    exit ( "cannot create $f" );
	$r = @flock ( $wdesc, LOCK_EX );
	if ( $r === false )
	    exit ( "cannot lock $f" );

	if ( $first_time )
	{
	    $rdesc = @fopen ( '/dev/random', 'r' );
	    if ( $rdesc === false )
	        exit ( 'cannot open /dev/random for' .
		       ' reading' );
	    $g = '/dev/random';
	    $data = @fread ( $rdesc, 32 );
	    fclose ( $rdesc );
	}
	else
	{
	    $data = @fread ( $wdesc, 32 );
	    $r = @fseek ( $wdesc, 0 );
	    if ( $r === false )
		exit ( "cannot seek to beginning of" .
		       " $f" );
	    $g = $f;
	}

	if ( $data === false
	     ||
	     strlen ( $data ) != 32 )
	    exit ( "cannot read 32 bytes from $g" );

	$utime = microtime();
	$addend = (int) ( $utime[0] * (1 << 16) );
	$addend = $addend + ( $addend >> 8 );
	$last_byte = (int) unpack
	    ( 'C', substr ( $data, 31, 1 ) );
	$last_byte = ( $last_byte + $addend ) & 0xFF;
	$data = substr ( $data, 0, 31 )
	      . pack ( 'C', $last_byte );
	
	$iv = hex2bin
	    ( '00000000000000000000000000000000' );
	$encrypted = @openssl_encrypt
	      ( substr ( $data, 16, 16 ),
	        'aes-128-cbc',
		substr ( $data, 0, 16 ),
		OPENSSL_RAW_DATA, $iv );
	    // The @ suppresses the warning about the
	    // empty (zero) iv.
	$data = substr ( $data, 0, 16 )
	      . substr ( $encrypted, 0, 16 );
	    // openssl_encrypt returns 32 bytes, the
	    // last 16 of which are an encryption of
	    // padding, which we ignore.

	$r = @fwrite ( $wdesc, $data, 32 );
	if ( $r != 32  )
	    exit ( "cannot write 32 bytes to $f" );
	fclose ( $wdesc );

	return substr ( $data, 16, 16 );
    }

    // Return an id_gen vector.  This vector
    // consists of
    //
    //		[VALUE, KEY, IV]
    //
    // where VALUE is the next ID value to be returned
    // encoded in 16 pseudo-random bytes, and successive
    // VALUEs are generated by encoding the preceeding
    // VALUE with the KEY and IV.  The IV is zero.
    //
    function init_id_gen()
    {
	$iv = hex2bin
	    ( '00000000000000000000000000000000' );
        return [ random_16_bytes(),
	         random_16_bytes(),
		 $iv ];
    }

    // Delay by calling usleep until $sec seconds after
    // the time the last 16-byte random number was
    // generated by random_16_bytes or the last time
    // this function was called.  This can be used to
    // throttle attempts to log in or generate new
    // windows.
    //
    function delay_random ( $sec )
    {
        global $epm_data;
        $time = @filemtime
	    ( "$epm_data/admin/+random+" );
	if ( $time === false )
	    $time = 0;
	else
	    @touch
		( "$epm_data/admin/+random+" );
	$elapsed = time() - $time;
	if ( $elapsed >= $sec ) return;
	usleep ( ( $sec - $elapsed ) * 1000000 );
	return;
    }
?>
