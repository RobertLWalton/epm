// Educational Problem Manager Default Monitor Program
//
// File:	epm_monitor.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Thu Jun 11 22:48:27 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// You can copy the following code into any monitor.

#include <streambuf>
#include <iostream>
#include <cstring>
#include <cstdio>
#include <cstdlib>
extern "C" {
#include <unistd.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <sys/stat.h>
#include <signal.h>
#include <fcntl.h>
#include <errno.h>
}
using std::streambuf;
using std::istream;
using std::ostream;
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
 
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

    inbuf ( int _fd )
    {
        setg ( buffer, buffer, buffer );
	eof = false;
	fd = _fd;
    }
    int close ( void )
    {
        return ::close ( fd );
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

    outbuf ( int _fd )
    {
        setp ( buffer, buffer );
	eof = false;
	fd = _fd;
    }
    int close ( void )
    {
	sync();
        return ::close ( fd );
    }

  protected:


    virtual int sync ( void )
    {
	if ( eof ) return -1;
        size_t s = pptr() - buffer;
	if ( s > 0 )
	{
	    // For some reason there is a problem
	    // with writing 0 bytes.
	    //
	    ssize_t r = write ( fd, buffer, s );
	    if ( r <= 0 )
	    {
		eof = true; return -1;
	    }
	}
	setp ( buffer, buffer + 4096 );
	return 0;
    }
    virtual int overflow ( int c )
    {
    	if ( sync() < 0 ) return EOF;
	buffer[0] = c;
	pbump ( 1 );
	    // The C++ documentation incorrectly states
	    // that this need not be done.
	return buffer[0];
    }
};

outbuf toBUF ( 4 );
ostream to ( & toBUF ); // Stream for writing to
                        // subprocess input.
inbuf fromBUF ( 3 );
istream from ( & fromBUF ); // Stream for reading from
                            // subprocess output.

// Error function for OS system calls.
//
void syserror ( const char * action )
{
    cerr << "ERROR " << action << ": "
         << strerror ( errno ) << endl;
    exit ( 1 );
}


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

// The reminder of this code will need to be replaced
// for monitors other than this.

char documentation [] =
"epm_monitor [-trace] 4>SOL-INPUT 3< SOL-OUTPUT \n"
"\n"
"    Assumes SOL-OUTPUT is a fifo connected to the\n"
"    solution program standard output and SOL-INPUT\n"
"    is a fifo connected to the solution program\n"
"    standard input.  This monitor program copies its\n"
"    standard input to the solution input, removing\n"
"    comment lines as it does so.  This monitor prog-\n"
"    also copies the solution standard output to the\n"
"    standard output of the monitor program.\n"
"\n"
"    More specifically, the monitor program begins\n"
"    by reading its entire standard input up to an\n"
"    end-of-file, copying lines beginning with `!!' \n"
"    to the monitor program standard output, and buf-\n"
"    fering other lines.  If -trace is given, these\n"
"    other lines are copied to the standard output\n"
"    with the preface `!!>>'.  An input line begin-\n"
"    ning with `!!' that is not followed by `##' will\n"
"    trigger a warning message to the standard\n"
"    error.\n"
"\n"
"    Then the monitor program establishes a thread\n"
"    that copies the buffered input lines to the solu-\n"
"    tion standard input, throttling when the solu-\n"
"    tion pauses to compute or write output.\n"
"\n"
"    Lastly the monitor program copies the solution\n"
"    standard output to the monitor standard output.\n"
"    If any line of this output begins with `!!' not\n"
"    followed by `**', the monitor writes a warning\n"
"    to its standard error.\n"
"\n"
"    Note that the epm_score scoring program ignores\n"
"    all lines beginning with `!!'.\n"
"\f\n"
"    Note also that when opening named fifos, order\n"
"    matters.  Thus given pipes named IN and OUT,\n"
"\n"
"        cat <IN >OUT &\n"
"        epm_monitor >IN <OUT\n"
"\n"
"   works because the IN fifo is opened first by\n"
"   BOTH processes, but\n"
"\n"
"        cat <IN >OUT &\n"
"        epm_monitor <OUT >IN\n"
"\n"
"   hangs up because each process begins by opening\n"
"   a DIFFERENT fifo, so each process gets hung up\n"
"   waiting for the other end of these fifos to open.\n"
;

#include <string>
#include <vector>
using std::string;
using std::vector;

// Main program.
//
bool trace = false;    // Trace option.
int line_number = 0;   // Line number in output.
int in_bad_count = 0;  // Count bad input comments.
int in_bad_first;      // First bad input comment
		       // line number.
int out_bad_count = 0; // Count bad output comments.
int out_bad_first;     // First bad output comment
		       // line number.
vector<string> input;  // Non-comment input lines.
// Function to run in thread copying input to the
// subprocess.
//
void * copy ( void * )
{
    for ( int i = 0; i < input.size(); ++ i )
        to << input[i] << endl;
    if ( ! to )
        cerr << "ERROR copying lines to subprocess"
	     << endl;
    toBUF.close();
    pthread_exit ( NULL );
}

int main ( int argc, char ** argv )
{
    // Process options.

    while ( argc >= 2 )
    {
        ++ argv, -- argc;
        if ( strncmp ( "-doc", argv[0], 4 ) == 0 )
	{
	    // Any -doc* option prints documentation
	    // and exits with no error.
	    //
	    FILE * out = popen ( "less -F", "w" );
	    fputs ( documentation, out );
	    pclose ( out );
	    exit ( 0 );
	}
        else if ( strcmp ( "-trace", argv[0] ) == 0 )
	    trace = true;
	else if ( argv[0][0] == '-' )
	{
	    cerr << "ERROR: cannot understand "
	         << argv[0] << " " << argv[0][0] << endl;
	    exit ( 1 );
	}
	else
	    break;
    }

    // Read input.
    //
    input.clear();
    string line;
    while ( getline ( cin, line ) )
    {
	const char * p = line.c_str();
	if ( p[0] != '!' || p[1] != '!' )
	{
	    input.push_back ( line );
	    if ( trace )
	    {
	        cout << "!!>>" << line << endl;
		++ line_number;
	    }
	}
	else
	{
	    cout << line << endl;
	    ++ line_number;
	    if ( p[2] != '#' || p[3] != '#' )
	    {
	        if ( in_bad_count ++ == 0 )
		    in_bad_first = line_number;
	    }
	}
    }
    if ( cin.bad() || line.size() != 0 )
        cerr << "WARNING: input did not end properly"
	        " (e.g., with a line feed)" << endl;

    if ( in_bad_count > 0 )
        cerr << "WARNING: input contains "
	     << in_bad_count << " illegal comments;"
	     << endl
	     << "         the first is at output line "
	     << in_bad_first << endl;

    // Create input copying thread.
    //
    pthread_t thread;
    if (   pthread_create ( & thread, NULL, copy, NULL )
         < 0 )
        syserror ( "pthread_create" );

    // Copy subprocess output to standard output.
    //
    while ( getline ( from, line ) )
    {
        cout << line << endl;
	++ line_number;
	const char * p = line.c_str();
	if ( p[0] != '!' || p[1] != '!' ) continue;
	if ( p[2] == '*' && p[3] == '!' ) continue;
	if ( out_bad_count ++ == 0 )
	    out_bad_first = line_number;
    }
    if ( from.bad() || line.size() != 0 )
        cerr << "WARNING: output did not end properly"
	        " (e.g., with a line feed)" << endl;
    if ( out_bad_count > 0 )
        cerr << "WARNING: output contains "
	     << out_bad_count << " illegal comments;"
	     << endl
	     << "         the first is at output line "
	     << out_bad_first << endl;

    // Cleanup.
    //
    fromBUF.close();
    exit ( 0 );
}

