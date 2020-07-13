// Generate Annual Return Problem Data
//
// File:	generate-annual.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Mon Jul 13 05:22:39 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// TEMPLATE for generate program from epm_generate.cc.

#include <iostream>
#include <string>
#include <sstream>
#include <iomanip>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cstdarg>
#include <cassert>
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
using std::string;
using std::istringstream;
using std::ws;

#define FOR0(i,n) for ( int i = 0; i < (n); ++ i )
#define FOR1(i,n) for ( int i = 1; i <= (n); ++ i )

bool debug = false;
# define dout if ( debug ) cout

int line_number = 0;
void error ( const char * format... )
{
    va_list args;
    va_start ( args, format );
    cerr << "ERROR: line: " << line_number << ": ";
    vfprintf ( stderr, format, args );
    cerr << endl;
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
// the range 0 .. 1.  To get random integers, use
// random ( n ) or random ( low, high ) below.
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
// Return a random number in the range [first,last].
//
inline long random ( long first, long last )
{
    assert ( first <= last );
    return first + random ( last - first + 1 );
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

char documentation [] =
"generate-valuable [-doc]\n"
"\n"
"    Copies standard input to standard output,\n"
"    removing lines that begin with `!!'.\n"
"\n"
"    Lines that begin with `!!##' are considered to\n"
"    be comment lines.\n"
"\n"
"    A line of the form:\n"
"\n"
"        !!G seed n y v A dmin dmax\n"
"\n"
"    denotes n lines for years y, y+1, ..., y+n-1\n"
"    with values v, B*v, B^2*v, B^3*v, ..., for\n"
"    B = 1 + A/100, and with the values dithered by\n"
"    adding a value uniformly distribted in the range\n"
"    [dmin,dmax].\n"
"\n"
"    Other lines beginning with `!!' are considered\n"
"    to be bad comment lines and cause a warning\n"
"    message to be output on the standard error.\n"
;

const int MAX_N = 30;
const int MAX_Y = 9999;
const double MAX_V = 1e6;
const double MAX_A = 100;
// Input Data
//
int n, y;
double v, A, dmin, dmax;
unsigned long seed;

void G ( string line )
{
    char op[100];

    istringstream in ( line );
    in >> op >> seed >> n >> y >> v
       >> A >> dmin >> dmax;
    if ( in.fail() )
	error ( "badly formatted"
	        " or too few parameters" );
    assert ( strcmp ( op, "!!G" ) == 0 );
    if ( seed < 1e8 || seed >= 1e9 )
	error ( "seed out of range" );

    if ( n < 1 || n > MAX_N )
	error ( "n out of range" );
    if ( y <= 0 || y > MAX_Y )
	error ( "y out of range" );
    if ( y + n - 1 > MAX_Y )
	error ( "y + n out of range" );
    if ( v <= 0 || v > MAX_V )
	error ( "v out of range" );
    if ( A < -MAX_A || A > MAX_A )
	error ( "A out of range" );
    if ( dmin < -MAX_V || dmin > MAX_V )
	error ( "dmin out of range" );
    if ( dmax < -MAX_V || dmax > MAX_V )
	error ( "dmax out of range" );
    if ( dmax < dmin )
	error ( "dmax < dmin" );
    if ( v + dmin <= 0 )
	error ( "v + dmin <= 0" );
    if ( v + dmax > MAX_V )
	error ( "v + dmax too large" );
    if ( ! in.eof() )
	error ( "extra stuff at end of line" );

    srandom ( seed );

    FOR0 ( i, n )
    {
	double d = dmin + ( dmax - dmin ) * drandom();
        double vout = v + d;
	if ( vout <= 0 )
	    error ( "v + dmin become too small" );
	if ( vout > MAX_V )
	    error ( "v + dmax become too large" );
	cout << y << " " << vout << endl;
	y += 1;
	v *= ( 1 + A/100 );
    }
}


// Main program.
//
int bad_comments = 0;    // Number of bad comment lines.
int bad_first;           // First bad comment line.
string line;
//
int main ( int argc, char ** argv )
{
    if (    argc > 1
         && strncmp ( argv[1], "-deb", 4 ) == 0 )
    {
        debug = true;
	-- argc, ++ argv;
    }
    if ( argc > 1 )
    {
	FILE * out = popen ( "less -F", "w" );
	fputs ( documentation, out );
	pclose ( out );
	return 0;
    }

    while ( getline ( cin, line ) )
    {
        ++ line_number;
	const char * p = line.c_str();
	if ( strncmp ( p, "!!##", 4 ) == 0 )
	    continue;
	else if ( strncmp ( p, "!!G", 3 ) == 0
	         && isspace ( p[3] ) )
	{
	    G ( line );
	    continue;
	}
	else if ( strncmp ( p, "!!", 2 ) == 0 )
	{
	    if ( bad_comments ++ == 0 )
	        bad_first = line_number;
	    continue;
	}
	else
	    cout << line << endl;
    }

    if ( bad_comments > 0 )
        cerr << "WARNING: there were " << bad_comments
	     << " bad comment lines, the first being"
	     << " line " << bad_first << endl;

    
    return 0;
}
