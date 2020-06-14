// Educational Problem Manager Default Generate Program
//
// File:	epm_generate.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sun Jun 14 05:54:45 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// TEMPLATE for generate program.

#include <iostream>
#include <string>
#include <cstdio>
#include <cstring>
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
using std::string;

int line_number = 0;
int bad_comments = 0;    // Number of bad comment lines.
int bad_first;           // First bad comment line.
 
// Get next line into `line', deleting comments.
// Return true on success and false on EOF.
//
string line;
bool get_line ( void )
{
    while ( getline ( cin, line ) )
    {
        ++ line_number;
	const char * p = line.c_str();
	if ( strncmp ( p, "!!##", 4 ) == 0 ) continue;
	if ( strncmp ( p, "!!", 2 ) == 0 )
	{
	    if ( bad_comments ++ == 0 )
	        bad_first = line_number;
	    continue;
	}
	return true;
    }
    return false;
}

char documentation [] =
"    Copies standard input to standard output,\n"
"    removing lines the begin with `!!'.  Such\n"
"    lines that do NOT begin with `!!##' are\n"
"    considered to be bad comment lines, and cause\n"
"    a warning message to be output on the standard\n"
"    error.\n"
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
