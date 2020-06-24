// Educational Problem Manager Default Filter Program
//
// File:	epm_filter.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Jun 24 14:22:23 EDT 2020
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
"epm_default_filter [-doc]\n"
"\n"
"    Reads solution output from file descriptor 3\n"
"    and copies it to the standard output.\n"
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

// Put functions, data, and parameters that aid in
// processing the solution input or output here.

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

    // Put code to initialize problem computation here.

 
    // Read input.  You may assume it has integrity to
    // the extent that the problem generate program
    // checks input integrity.
    //
    // while ( cin >> ... )
    // {
    //     // Check input ranges; you can use asserts
    //     // here as problem solvers have no control
    //     // over this input stream.
    //     //
    //     assert ( ................... );
    //     ..................................
    // }

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

	// in >> ... >> ws;
	// if ( in.fail() || ! in.eof() )
	// {
	//     error ( "badly formated .sout line" );
	//     continue;
	// }
	// if ( ... < ... || ... > ... )
	// {
	//     error ( "cx out of range" );
	//     continue;
	// }

	// Delete or replace the following for
	// non-default filters.
	//
	cout << line << endl;
    }

    if ( bad_comments > 0 )
        cerr << "WARNING: there were " << bad_comments
	     << " bad comment lines, the first being"
	     << " line " << bad_first << endl;

    
    return 0;
}
