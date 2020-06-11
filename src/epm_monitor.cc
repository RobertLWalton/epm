// Educational Problem Manager Default Monitor Program
//
// File:	epm_monitor.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Jun 10 21:49:11 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// Begin TEMPLATE for monitor program input/output.

#include <streambuf>
#include <iostream>
extern "C" {
#include <unistd.h>
}
using std::streambuf;
using std::istream;
using std::ostream;
 
// Class for reading from file descriptor.
//
// putback is not supported.  Read errors are treated
// as end-of-file.
//
class inbuf : public streambuf
{
    char buffer[4096];
    bool eof;
    int fd;

  public:

    void setfd ( int _fd )
    {
        setg ( buffer, buffer, buffer );
	eof = false;
	fd = _fd;
    }

  protected:

    virtual int underflow ( void )
    {

	if ( eof ) return EOF;
	ssize_t c = read ( fd, buffer, 4096 );
	if ( c <= 0 )
	{
	    eof = true; return EOF;
	}
	setg ( buffer, buffer, buffer + c );
	return buffer[0];
    }
};
 
// Class for writing to file descriptor.
//
class outbuf : public streambuf
{
    char buffer[4096];
    bool eof;
    int fd;

  public:

    void setfd ( int _fd )
    {
        setp ( buffer, buffer );
	eof = false;
	fd = _fd;
    }

  protected:


    virtual int sync ( void )
    {
	if ( eof ) return -1;
        size_t s = pptr() - buffer;
	ssize_t s = write ( fd, buffer, s );
	if ( s <= 0 )
	{
	    eof = true; return -1;
	}
	setp ( buffer, buffer + 4096 );
	return 0;
    }
    virtual int overflow ( int c )
    {
    	if ( sync() < 0 ) return EOF;
	buffer[0] = c;
	return buffer[0];
    }
};

// Random Number Generator
// ------ ------ ---------

// We embed the random number generator so it cannot
// change on us.  This is the same as lrand48 in 2016.
// Note that for this random number generator the
// low order k bits produced depend only on the low
// order k bits of the seed.  Thus the generator is
// only good at generating floating point numbers in
// the range 0 .. 1.  To get small random numbers, use
// random ( n ) below.
//
# include <cmath>
# define random RANDOM
# define srandom SRANDOM
    // To avoid conflict with libraries.
unsigned long long last_random_number;
const unsigned long long MAX_RANDOM_NUMBER =
	( 1ull << 32 ) - 1;
void srandom ( unsigned long long seed )
{
    seed &= MAX_RANDOM_NUMBER;
    last_random_number = ( seed << 16 ) + 0x330E;
}
// Return floating point random number in range [0 .. 1)
//
inline double drandom ( void )
{
    last_random_number =
        0x5DEECE66Dull * last_random_number + 0xB;
    unsigned long long v =
          ( last_random_number >> 16 )
	& MAX_RANDOM_NUMBER;
    return (double) v / (MAX_RANDOM_NUMBER + 1 );
}
// Return a random number in the range 0 .. n - 1.
//
inline unsigned long random ( unsigned long n )
{
    return (unsigned long) floor ( drandom() * n );
}

// Shuffle vector v of n elements randomly.
//
template <typename T> inline void shuffle
	( T * v, int n )
{
    for ( int i = 0; i < n; ++ i )
    {
        int j = random ( n - i );
	swap ( v[i], v[i+j] );
    }
}

// Subprocess Management
// --------- ----------

// Error function for OS system calls.  Write to cerr to
// generate `program crashed' score.
//
void syserror ( const char * action )
{
    cerr << "ERROR " << action << ": "
         << strerror ( errno ) << endl;
    exit ( 1 );
}

// Establish subprocess.  The subprocess executes the
// command in argv (which normally equals argv + 1
// from `main').
//
// There is no ANSI compatible way to connect pipes
// and iostreams, so we have to use cstdio.
//
const char * subprogram_name; // Save of argv[0] by
			      // start_subprocess.
pid_t subprocess;	// PID of subprocess.
tobuf outBUF;
istream to ( & toBUF ); // Stream for writing to
			// subprocess input.
frombuf inBUF;
istream from ( & fromBUF ); // Stream for reading from
			    // subprocess output.

void start_subprocess ( char * const * argv )
{
    subprogram_name = argv[0];

    int infd[2],	// Input to subprocess.
	outfd[2];	// Output from subprocess.
    if ( pipe ( infd ) < 0 )
	syserror ( "OPENING PIPE" );
    if ( pipe ( outfd ) < 0 )
	syserror ( "OPENING PIPE" );

    subprocess = fork ();
    if ( subprocess < 0 ) syserror ( "FORKING" );
    if ( subprocess == 0 )
    {
	dup2 ( infd[0], 0 );
	dup2 ( outfd[1], 1 );
	close ( infd[0] );
	close ( infd[1] );
	close ( outfd[0] );
	close ( outfd[1] );
	execvp ( argv[0], argv );
	syserror ( "EXECUTING SUBPROCESS" );
    }
    fromBuf.setfd ( outfd[0] );
    toBuf.setfd ( infd[1] );
    close ( outfd[1] );
    close ( infd[0] );
}

// End TEMPLATE for generate/filter program input.

using std::cout;
using std::endl;

#ifndef NAME
#    error "Program name NAME not set."
#endif

#ifndef FD
#    error "Input file descriptor FD not set."
#endif

char documentation [] =
"%s\n"
"\n"
"    Copies from file descriptor %d to file descrip-\n"
"    tor 1 removing comments from lines.  A line\n"
"    beginning with !!** is a comment line and is\n"
"    deleted (not copied).  Any character sequence\n"
"    of the form [**...**] within a line is a com-\n"
"    ment and is deleted.  These last comments\n"
"    CANNOT be nested: once [** is recognized as the\n"
"    start of a comment, the next **] terminates the\n"
"    comment, even if the comment contains other [**\n"
"    sequences, and if there is no **] following a\n"
"    in a line, then the [** does not begin a com-\n"
"    ment.\n"
;

# define quote(x) str(x)
# define str(x) #x

// Main program.
//
int main ( int argc, char ** argv )
{
    if ( argc > 1 )
    {
	fprintf ( stderr,
	          documentation, quote(NAME), FD );
	return 0;
    }

    inBUF.setfd ( FD );
    while ( get_line() ) cout << line << endl;

    return 0;
}

