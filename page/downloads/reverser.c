/* Solution to the Word Order Reverser Problem
 *
 * File:	reverser.c
 * Authors:	Bob Walton (walton@seas.harvard.edu)
 * Date:	Sun Jun 21 14:08:10 EDT 2020
 *
 * The authors have placed this program in the public
 * domain; they make no warranty and accept no liability
 * for this program.
 */


#include <stdlib.h>
#include <stdio.h>
#include <ctype.h>
#include <assert.h>

int debug = 0;
#define dprintf if ( debug ) printf

char line[82];

/* Function to find and print the first word BEFORE q,
 * where q points into `line'.  It is a program error
 * if there is no such word.  Returns pointer to the
 * first character of the word printed.
 */
char * print_substitute_word ( char * q )
{
    char * p, save;

    /* Move q backward to point just after word.
     */
    while ( 1 )
    {
        assert ( q > line );
	if ( isalpha ( q[-1] ) ) break;
	-- q;
    }
    p = q;  /* p points just after word. */

    /* Move q to point at 1st character of word.
     */
    -- q;
    while ( q > line && isalpha ( q[-1] ) ) -- q;

    /* Print word and return.
     */
    save = * p;
    * p = 0;
    printf ( "%s", q );
    * p = save;
    return q;
}

int main ( int argc, char * argv[] )
{
    debug = ( argc > 1 );

    while ( fgets ( line, sizeof ( line ), stdin ) )
    {
	char * p, * q;

	/* When debugging print the input as well as
	 * the output.
	 */
	dprintf ( "!!**%s", line );

	/* Special code to introduce errors in some
	 * test cases:
	 */
	    #define ONE_OF_THE_BELOW
	    #ifdef INCORRECT
		if ( line[0] == 'p' ) line[0] = 'q';
	    #endif
	    #ifdef INCOMPLETE
		if ( line[0] == 'p' ) exit ( 0 );
	    #endif
	    #ifdef FORMAT_ERROR
		if ( line[0] == 'p' ) printf ( " " );
	    #endif
	    #ifdef NO_OUTPUT
		exit ( 0 );
	    #endif
	    #ifdef INFINITE_LOOP
		if ( line[0] == 'p' ) while ( 1 );
	    #endif
	    #ifdef INFINITE_OUTPUT
		if ( line[0] == 'p' )
		    while ( 1 ) printf ( "MORE\n" );
	    #endif

    	/* Set p to beginning of line and q to end of
	 * line.
	 */
	p = line, q = line;
	while ( * q ) ++ q;

	/* Print line substituting for words.
	 */
	while ( * p )
	{
	    if ( isalpha ( * p ) )
	    {
		/* At start of word. Skip word.
		 */
	        while ( isalpha ( * p ) ) ++ p;

		/* Substitute for skipped word.
		 */
		q = print_substitute_word ( q );
	    }
	    else
	    {
	        /* Print and skip non-letter.
		 */
	        printf ( "%c", * p ++ );
	    }
	}
    }

    return 0;
}
