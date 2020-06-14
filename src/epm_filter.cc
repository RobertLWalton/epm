// Educational Problem Manager Default Filter Program
//
// File:	epm_filter.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sun Jun 14 05:58:18 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// TEMPLATE for filter program.

#include <streambuf>
#include <iostream>
#include <string>
#include <cstring>
#include <cstdio>
extern "C" {
#include <unistd.h>
}
using std::cout;
using std::cerr;
using std::endl;
using std::streambuf;
using std::istream;
using std::string;
 
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

inbuf inBUF ( 3 );
istream in ( & inBUF );

int line_number = 0;
int bad_comments = 0;    // Number of bad comment lines.
int bad_first;           // First bad comment line.
 
// Get next line into `line', identifying bad comments.
// Return true on success and false on EOF.
//
string line;
bool get_line ( void )
{
    while ( getline ( in, line ) )
    {
        ++ line_number;
	const char * p = line.c_str();
	if ( strncmp ( p, "!!", 2 ) == 0
	     &&
	     strncmp ( p, "!!**", 4 ) != 0 )
	{
	    if ( bad_comments ++ == 0 )
	        bad_first = line_number;
	}
	return true;
    }
    return false;
}

char documentation [] =
"    Copies from file descriptor 3 to standard out-\n"
"    put, counting bad comment lines, which begin\n"
"    with `!!' NOT followed by `**'.  Bad comment\n"
"    lines cause a warning message to be output on\n"
"    the standard error.\n"
;

// Main program.
//
int main ( int argc, char ** argv )
{
    if ( argc > 1 )
    {
	FILE * out = popen ( "less -F", "w" );
	fputs ( documentation, out );
	pclose ( out );
	return 0;
    }

    while ( get_line() ) cout << line << endl;

    if ( bad_comments > 0 )
        cerr << "WARNING: there were " << bad_comments
	     << " bad comment lines, the first being"
	     << " line " << bad_first << endl;

    return 0;
}
