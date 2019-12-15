// Educational Problem Manager Default Generate/Filter
// Program
//
// File:	epm_cat.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sun Dec 15 05:53:58 EST 2019
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

#include <cstdlib>
#include <iostream>
#include <string>
#include <vector>
#include <fstream>
#include <algorithm>
#include <cctype>
#include <cstring>
#include <cmath>
#include <cstdarg>
#include <cassert>
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
using std::string;
using std::vector;
using std::istream;
using std::ifstream;
using std::max;

unsigned const PROOF_LIMIT = 5;

char documentation [] =
"epm_cat\n"
"\n"
"    Copies standard input to standard output\n"
"    removing any string of characters of the form\n"
"    !!**...\\n."
;

// Main program.
//
int main ( int argc, char ** argv )
{
    string line;
    while ( getline ( cin, line ) )
    {
        const char * beg_p = line.c_str();
	const char * p = beg_p;
	while ( true )
	{
	    while ( * p && * p != '!' ) ++ p;
	    if ( * p == 0 ) break;
	    if ( strncmp ( p, "!!**", 4 ) == 0 )
	        break;
	    ++ p;
	}
	if ( * p )
	    cout << line.substr ( 0, p - beg_p );
	else
	    cout << line << endl;
    }
    return 0;
}
