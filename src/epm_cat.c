// Educational Problem Manager Default Generate/Filter
// Program
//
// File:	epm_cat.c
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Mon Dec 16 07:39:40 EST 2019
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

#include <unistd.h>
#include <stdio.h>
#include <errno.h>
#include <string.h>

#ifndef IN_FD
#    error "Input file descriptor IN_FD not set."
#endif

#ifndef OUT_FD
#    error "Input file descriptor OUT_FD not set."
#endif

#define quote(x) #x

char documentation [] =
"epm_cat\n"
"\n"
"    Copies from file descriptor %d to file descrip-\n"
"    tor %d removing any string of characters of the\n"
"    form !!**...\\R where ... denotes a sequence of\n"
"    non-\\n, non-\\r characters and \\R denotes any\n"
"    of \\n, \\r, or \\r\\n.\n"
;


char buffer[4096];
char * p, * endp, *outp;
int removing = 0;
    // Current buffer is buffer..endp.
    // Current position is p.
    // Characters to be output are outp..p.
    // Removing is 0 if we are not removing characters
    //     and are looking for !!**; and is 1 if we
    //     are removing characters and looking for \R.

// Main program.
//
int main ( int argc, char ** argv )
{
    if ( argc > 1 )
    {
	fprintf ( stderr,
	          documentation, IN_FD, OUT_FD );
	return 0;
    }

    p = endp = outp = buffer;
    while ( 1 )
    {
	char * q = buffer;
        while ( p < endp ) * q ++ = * p ++;
	size_t c = read ( IN_FD, q,
	                    sizeof ( buffer )
			  - ( q - buffer ) );
	if ( c < 0 ) return errno;
	    // c == 0 means no more characters
	    // beyond what was left over from
	    // previous read.
	endp = q + c;
	p = outp = buffer;

	while ( p < endp )
	{
	    if ( removing )
	    {
		if ( * p == '\n' )
		    removing = 0, outp = ++ p;
		else if ( * p != '\r' )
		    ++ p;
		else if ( endp - p < 2 )
		    break;
		else
		{
		    ++ p;
		    if ( * p == '\n' ) ++ p;
		    outp = p, removing = 0;
		}
	    }
	    else
	    {
		if ( * p != '!' )
		    ++ p;
		else if ( endp - p < 4 )
		    break;
		else if ( p[1] == '!'
			  &&
			  p[2] == '*'
			  &&
			  p[3] == '*' )
		{
		    if ( outp < p )
		    {
			size_t r = write
			    ( OUT_FD, outp, p - outp );
			if ( r < 0 ) return errno;
		    }
		    removing = 1, p += 4;
		}
		else ++ p;
	    }
	}

	if ( ! removing )
	{
	    if ( c == 0 ) p = endp;
	    if ( outp < p )
	    {
		size_t r = write
		    ( OUT_FD, outp, p - outp );
		if ( r < 0 ) return errno;
	    }
	}

	if ( c == 0 ) break;

    }

    return 0;
}
