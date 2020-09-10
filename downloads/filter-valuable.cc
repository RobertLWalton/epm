// Filter Valuable Circle Problem Data
//
// File:	filter-valuable.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Jun 24 04:09:23 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// TEMPLATE for filter program from epm_filter.cc.

#include <streambuf>
#include <iostream>
#include <string>
#include <sstream>
#include <iomanip>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cstdarg>
#include <cassert>
extern "C" {
#include <unistd.h>
}
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
using std::streambuf;
using std::istream;
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
    cout << "ERROR: line: " << line_number << ": ";
    vprintf ( format, args );
    cout << endl;
}
 
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


// Stream for reading solution output.
//
inbuf inBUF ( 3 );
istream out ( & inBUF );


char documentation [] =
"filter-valuable\n"
"\n"
"    Reads solution input from standard input and\n"
"    solution output from file descriptor 3.  Then\n"
"    computes value of solution circle and outputs\n"
"    it.  If solution circle is invalid, outputs\n"
"    error messages instead.\n"
"\n"
"    Lines that begin with `!!**' are considered to\n"
"    be comment lines.\n"
"\n"
"    Other lines beginning with `!!' are considered\n"
"    to be bad comment lines and cause a warning\n"
"    message to be output on the standard error.\n"
"\n"
"    All comment lines are written to the standard\n"
"    output, as epm_score ignores them.\n"
;

# include <algorithm>
# include <vector>
# include <utility>
# include <cmath>
using std::max;
using std::min;
using std::pair;
using std::vector;
using std::make_pair;

const int MAX_N = 1000;
const int MAX_XY = 1000;
const int MAX_V = 1e6;

// Internal coordinates are [0,2*MAX_XY].
// External coordinates are [-MAX_XY,+MAX_XY].

// Input Data
//
int n;
long long value[2*MAX_XY+1][2*MAX_XY+1];
    // value[x][y] is value at (x,y)

// Computed Data:
// //
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
 
    // Read input.  You may assume it has integrity to
    // the extent that the problem generate program
    // checks input integrity.
    //
    int x, y, v;
    while ( cin >> x >> y >> v )
    {
        assert ( - MAX_XY <= x && x <= MAX_XY );
        assert ( - MAX_XY <= y && y <= MAX_XY );
        assert ( 1 <= v && v <= MAX_V );
	assert ( value[x+MAX_XY][y+MAX_XY] == 0 );
	value[x+MAX_XY][y+MAX_XY] = v;
    }

    // Get and check output.
    //
    string line;
    while ( getline ( out, line ) )
    {
        ++ line_number;
	const char * p = line.c_str();
	if ( strncmp ( p, "!!**", 4 ) == 0 )
	{
	    cout << line << endl;
	    continue;
	}
	else if ( strncmp ( p, "!!", 2 ) == 0 )
	{
	    if ( bad_comments ++ == 0 )
	        bad_first = line_number;
	    cout << line << endl;
	    continue;
	}

	istringstream in ( line );

	int cx, cy, r;
	in >> cx >> cy >> r >> ws;
	if ( in.fail() || ! in.eof() )
	{
	    error ( "badly formated .sout line" );
	    continue;
	}
	if ( cx < - MAX_XY || cx > + MAX_XY )
	{
	    error ( "cx out of range" );
	    continue;
	}
	if ( cy < - MAX_XY || cy > + MAX_XY )
	{
	    error ( "cy out of range" );
	    continue;
	}
	if ( r < 1 || r >= 3*MAX_XY )
	{
	    error ( "r out of range" );
	    continue;
	}

	long long v = 0;
	FOR0 ( i, offsets[r].size() )
	{
	    pair<int,int> offset = offsets[r][i];
	    int x = cx + offset.first;
	    int y = cy + offset.second;
	    if ( x < - MAX_XY ) continue;
	    if ( x > + MAX_XY ) continue;
	    if ( y < - MAX_XY ) continue;
	    if ( y > + MAX_XY ) continue;
	    x += MAX_XY;
	    y += MAX_XY;
	    v += value[x][y];
	}
	cout << v << endl;
    }

    if ( bad_comments > 0 )
        cerr << "WARNING: there were " << bad_comments
	     << " bad comment lines, the first being"
	     << " line " << bad_first << endl;

    
    return 0;
}
