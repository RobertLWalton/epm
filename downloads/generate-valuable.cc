// Generate Valuable Circle Problem Data
//
// File:	generate-valuable.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Mon Jul 13 05:30:45 EDT 2020
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
"    Non-comment lines are replaced by zero or more\n"
"    lines of the form `x y v'.  If too many such\n"
"    lines are computed, lines beyond the allowable\n"
"    number are not output.\n"
"\n"
"    A line of the form:\n"
"\n"
"        x y v\n"
"\n"
"    denotes a single point value (x,y,v)\n"
"\n"
"    A line of the form:\n"
"\n"
"        !!R seed k xmin xmax ymin ymax vmin vmax\n"
"\n"
"    denotes k point value points with (x,y,v)\n"
"    randomly choosen in [xmin,xmax]X[ymin,ymax]X\n"
"    [vmin,vmax].\n"
"\n"
"    A line of the form:\n"
"\n"
"        !!S seed k xmin xmax ymin ymax rmin rmax vmin"
					   " vmax\n"
"\n"
"    denotes value points on k circles each with\n"
"    center randomly chosen within [xmin,xmax]X\n"
"    [ymin,ymax], radius randomly chosen in\n"
"    [rmin,rmax], and total value randomly choosen in\n"
"    [vmin,vmax].  The total value of a given circle\n"
"    is randomly distributed among the points on the\n"
"    circle boundary.  Circles are choosen so there\n"
"    are at least 4 points on their boundary.\n"
"\n"
"    Other lines beginning with `!!' are considered\n"
"    to be bad comment lines and cause a warning\n"
"    message to be output on the standard error.\n"
;

#include <algorithm>
#include <vector>
#include <utility>
using std::max;
using std::min;
using std::pair;
using std::vector;
using std::make_pair;

const int MAX_N = 1000;
const int MAX_XY = 1000;
const int MAX_V = 1e6;

// Input Data
//
int k, xmin, xmax, ymin, ymax, rmin, rmax, vmin, vmax;
unsigned long seed;

// Internal coordinates are [0,2*MAX_XY].
// External coordinates are [-MAX_XY,+MAX_XY].

// Computed Data
//
int n;
long long value[2*MAX_XY+1][2*MAX_XY+1];
    // value[x][y] is value at (x,y)

vector<pair<int,int> > offsets[3*MAX_XY];
    // offsets[r] is the list of offsets from a
    // circle center of radius r that give valid
    // point coordinates.

// This code adapted from solution of Daniel Chiu.
//
void compute_offsets() {
    for (int i=0;i<=2*MAX_XY;i++) {
        for (int j=i+1;j<=2*MAX_XY;j++) {
            int c = (int) sqrt(i*i+j*j);
            for (int k=max(c-2,0);k<=c+2;k++)
	    {
	        if (i*i+j*j!=k*k) continue;

		offsets[k].push_back(make_pair(i,j));
		offsets[k].push_back(make_pair(i,-j));
		offsets[k].push_back(make_pair(j,i));
		offsets[k].push_back(make_pair(-j,i));

		if ( i == 0 ) continue;

		offsets[k].push_back(make_pair(-i,j));
		offsets[k].push_back(make_pair(-i,-j));
		offsets[k].push_back(make_pair(j,-i));
		offsets[k].push_back(make_pair(-j,-i));
	    }
        }
    }
}

void R ( string line )
{
    char op[100];

    istringstream in ( line );
    in >> op >> seed >> k
       >> xmin >> xmax >> ymin >> ymax >> vmin >> vmax;
    if ( in.fail() )
	error ( "badly formatted"
	        " or too few parameters" );
    assert ( strcmp ( op, "!!R" ) == 0 );
    if ( seed < 1e8 || seed >= 1e9 )
	error ( "seed out of range" );
    if ( k < 1 || k > MAX_N )
	error ( "k out of range" );
    if ( xmin < -MAX_XY || xmin > MAX_XY )
	error ( "xmin out of range" );
    if ( xmax < -MAX_XY || xmax > MAX_XY )
	error ( "xmax out of range" );
    if ( xmax < xmin )
	error ( "xmax < xmin" );
    if ( vmin < 1 || vmin > MAX_V )
	error ( "vmin out of range" );
    if ( vmax < 1 || vmax > MAX_V )
	error ( "vmax out of range" );
    if ( vmax < vmin )
	error ( "vmax < vmin" );
    if ( ! in.eof() )
	error ( "extra stuff at end of line" );

    srandom ( seed );

    int k0 = 0;
    int tries = 0;
    while ( k0 < k )
    {
	int x = random ( xmin, xmax );
	int y = random ( ymin, ymax );
	int v = random ( vmin, vmax );
	x += MAX_XY;
	y += MAX_XY;
	if ( value[x][y] + v > MAX_V )
	    v = MAX_V - value[x][y];
	if ( v == 0 )
	{
	    if ( ++ tries > 100 ) break;
	    else continue;
	}
	if ( value[x][y] == 0 )
	{
	    if ( n >= MAX_N )
	    {
		if ( ++ tries > 100 ) break;
		else continue;
	    }
	    ++ n;
	}
	value[x][y] += v;
	++ k0;
    }
}

void S ( string line )
{
    char op[100];

    istringstream in ( line );
    in >> op >> seed >> k
       >> xmin >> xmax >> ymin >> ymax
       >> rmin >> rmax >> vmin >> vmax;
    if ( in.fail() )
	error ( "badly formatted"
	        " or too few parameters" );
    assert ( strcmp ( op, "!!S" ) == 0 );
    if ( seed < 1e8 || seed >= 1e9 )
	error ( "seed out of range" );
    if ( k < 1 || k > MAX_N )
	error ( "k out of range" );
    if ( xmin < -MAX_XY || xmin > MAX_XY )
	error ( "xmin out of range" );
    if ( xmax < -MAX_XY || xmax > MAX_XY )
	error ( "xmax out of range" );
    if ( xmax < xmin )
	error ( "xmax < xmin" );
    if ( rmin < 1 || rmin > 3*MAX_XY )
	error ( "rmin out of range" );
    if ( rmax < 1 || rmax > 3*MAX_XY )
	error ( "rmax out of range" );
    if ( rmax < rmin )
	error ( "rmax < rmin" );
    if ( vmin < 1 || vmin > MAX_V )
	error ( "vmin out of range" );
    if ( vmax < 1 || vmax > MAX_V )
	error ( "vmax out of range" );
    if ( vmax < vmin )
	error ( "vmax < vmin" );
    if ( ! in.eof() )
	error ( "extra stuff at end of line" );

    srandom ( seed );

    FOR0 ( k0, k )
    {
	int cx, cy, r;
	int x[3*MAX_XY], y[3*MAX_XY];
	int m;
	    // Value is to be distri-
	    // buted among the
	    // value[x[i]][y[i]]
	    // for 0 <= i < m.
	while ( true )
	{
	    cx = random ( xmin, xmax );
	    cy = random ( ymin, ymax );
	    r  = random ( rmin, rmax );
	    m = 0;
	    FOR0 ( p, offsets[r].size() )
	    {
		pair<int,int> o =
		    offsets[r][p];
		int vx = cx + o.first;
		int vy = cy + o.second;
		if ( vx < - MAX_XY )
		    continue;
		if ( vx > + MAX_XY )
		    continue;
		if ( vy < - MAX_XY )
		    continue;
		if ( vy > + MAX_XY )
		    continue;
		x[m] = vx + MAX_XY;
		y[m] = vy + MAX_XY;
		++ m;
	    }
	    if ( m >= 4 ) break;
	}

	// Distribute v in 1/1000'th
	// parts among the m locations.
	//
	int v = random ( vmin, vmax );
	int dv = v / 1000;
	if ( dv == 0 ) dv = 1;

	int tries = 0;
	while ( v > 0 )
	{
	    int j = random ( m );
	    if ( v < dv ) dv = v;
	    if ( value[x[j]][y[j]] + dv > MAX_V )
		dv = MAX_V - value[x[j]][y[j]];
	    if ( dv == 0 )
	    {
		if ( ++ tries > 100 ) break;
		else continue;
	    }
	    if (    value[x[j]][y[j]]
		 == 0 )
	    {
		if ( n >= MAX_N )
		{
		    if ( ++ tries > 100 ) break;
		    else continue;
		}
		++ n;
	    }
	    value[x[j]][y[j]] += dv;
	    v -= dv;
	}
    }
}

void input_value_point ( string line )
{
    char op[100];

    int x, y, v;
    istringstream in ( line );
    in >> x >> y >> v;
    if ( in.fail() )
	error ( "badly formatted"
	        " or too few parameters" );
    if ( x < -MAX_XY || x > MAX_XY )
	error ( "x out of range" );
    if ( y < -MAX_XY || y > MAX_XY )
	error ( "y out of range" );
    if ( v < 1 || v > MAX_V )
	error ( "v out of range" );
    if ( xmax < -MAX_XY || xmax > MAX_XY )
	error ( "xmax out of range" );

    x += MAX_XY;
    y += MAX_XY;
    if ( value[x][y] + v > MAX_V )
        v = MAX_V - value[x][y];
    if ( v == 0 ) return;
    if ( value[x][y] == 0 )
    {
	if ( n >= MAX_N ) return;
	++ n;
    }
    value[x][y] += v;
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

    compute_offsets();

    FOR0(x,2*MAX_XY+1) FOR0(y,2*MAX_XY+1)
	value[x][y] = 0;
 
    while ( getline ( cin, line ) )
    {
        ++ line_number;
	const char * p = line.c_str();
	if ( strncmp ( p, "!!##", 4 ) == 0 )
	    continue;
	else if ( strncmp ( p, "!!S", 3 ) == 0
	         && isspace ( p[3] ) )
	{
	    S ( line );
	    continue;
	}
	else if ( strncmp ( p, "!!R", 3 ) == 0
	         && isspace ( p[3] ) )
	{
	    R ( line );
	    continue;
	}
	else if ( strncmp ( p, "!!", 2 ) == 0 )
	{
	    if ( bad_comments ++ == 0 )
	        bad_first = line_number;
	    continue;
	}
	else
	    input_value_point ( line );
    }

    FOR0 ( x , 2*MAX_XY + 1 )
    FOR0 ( y , 2*MAX_XY + 1 )
    {
	if ( value[x][y] == 0 ) continue;
	printf ( "%6d %6d %8lld\n",
		 x - MAX_XY, y - MAX_XY,
		 value[x][y] );
    }

    if ( bad_comments > 0 )
        cerr << "WARNING: there were " << bad_comments
	     << " bad comment lines, the first being"
	     << " line " << bad_first << endl;

    
    return 0;
}
