// Educational Problem Manager Default Generate/Filter
// Program
//
// File:	epm_cat.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sun Dec 22 18:48:15 EST 2019
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// Begin TEMPLATE for generate/filter program input.

#include <iostream>
#include <string>
#include <cstring>
 
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

    void open ( int _fd )
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
	if ( c < 0 )
	{
	    eof = true; return EOF;
	}
	setg ( buffer, buffer, buffer + c );
    }
};

inbuf inBUF;
istream in ( & inBUF );

// Get next line into `line', deleting comments.
// Return true on success and false on EOF.
//
string line;
bool get_line ( void )
{
    while ( getline ( in, line ) )
    {
	const char * p = line.c_str();
	if ( strncmp ( p, "!!**", 4 ) continue;
	const char * bp = strstr ( p, "[**" );
	if ( bp == NULL ) return true;
	const char * ep = strstr ( bp + 3, "**]" );
	if ( ep == NULL ) return true;
	buffer[line.size() + 1];
	char * q = buffer;
	while ( true )
	{
	    strncpy ( q, p, bp - p );
	    q += bp - p;
	    p = ep + 3;
	    bp = strstr ( p, "[**" );
	    if ( bp == NULL ) break;
	    ep = strstr ( bp + 3, "**]" );
	    if ( ep == NULL ) break;
	}
	strcpy ( q, p );
	line = buffer;
	return true;
    }
    return false;
}

// End TEMPLATE for generate/filter program input.

#include <cstdio>

#ifndef FD
#    error "Input file descriptor FD not set."
#endif

char documentation [] =
"epm_cat\n"
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

// Main program.
//
int main ( int argc, char ** argv )
{
    if ( argc > 1 )
    {
	fprintf ( stderr,
	          documentation, FD );
	return 0;
    }

    while ( get_line() ) cout << line << endl;

    return 0;
}
