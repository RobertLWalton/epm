// Educational Programming Contest Scoring Program
//
// File:	epm_score.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sat Nov 30 07:41:33 EST 2019
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// This file is derived from scorediff.cc of the HPCM
// system written by the same author.

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
using std::cout;
using std::cerr;
using std::endl;
using std::string;
using std::vector;
using std::ifstream;
using std::max;

unsigned const PROOF_LIMIT = 5;

char documentation [] =
"epm_score [options] output_file test_file\n"
"\n"
"    Returns on a single line the score obtained by\n"
"    comparing the user's output_file to the judge's\n"
"    test_file.  Then returns proofs for the first\n"
"    several errors found, if any.\n"
"\n"
"    The possible score line values are:\n"
"\n"
"                Completely Correct\n"
"                Format Error\n"
"                Incomplete Output\n"
"                Incorrect Output\n"
"                Empty Output\n"
"\n"
"    To find differences, file lines are parsed into\n"
"    tokens, and sucessive tokens of the lines are\n"
"    matched.  A token is a word, a number, or a\n"
"    separator.\n"
"\n"
"    A word is just a string of ASCII letters.  A\n"
"    A number is a string with the syntax:\n"
"\n"
"        number ::= integer\n"
"                 | floating\n"
"\n"
"        integer ::= sign? digit+\n"
"\n"
"        floating ::= integer fraction exponent?\n"
"                   | sign? fraction exponent?\n"
"                   | integer decimal-point exponent?\n"
"                   | integer exponent\n"
"\n"
"        fraction ::= decimal-point digit+\n"
"\n"
"        exponent ::= 'e' integer\n"
"                   | 'E' integer\n"
"\n"
"    A separator is a string of non-whitespace, non-\n"
"    letters that is not part of a number.\n"
"\n"
"    The epm_score options are:\n"
"\n"
"    -blank\n"
"        If a blank line is matched to a non-blank\n"
"        line, the blank line is skipped.  With this\n"
"        option, such a skip is a Format Error.\n"
"\n"
"    -column\n"
"        For matching tokens, if they have different\n"
"        end column numbers it is a Format Error.\n"
"\n"
"    -case-format\n"
"        For matching word tokens, if they have\n"
"        matched letters of different case, it is a\n"
"        Format Error.\n"
"\n"
"    -case-incorrect\n"
"        For matching word tokens, if they have\n"
"        matched letters of different case, it is\n"
"        Incorrect Output.\n"
"\n"
"    -decimal\n"
"        For matching numbers, if they have different\n"
"        numbers of digits after the decimal point,\n"
"        of if either has an exponent, it is a For-\n"
"        matting Error.\n"
"\n"
"    -number A R\n"
"        All numbers, even integers, are converted\n"
"        to floating point.  Then if the difference\n"
"        between two matched numbers has absolute\n"
"        value greater than A or a relative value\n"
"        greater than R, the score is Incorrect Out-\n"
"        put, and otherwise the numbers are consider-\n"
"        equal.  See below for definition of relative\n"
"        error.  If either A or R is `-', it is\n"
"        ignored.\n"
"\n"
"    -float A R\n"
"        Ditto but apply only if the test file num-\n"
"        ber is floating point.\n"
"\n"
"    -exact\n"
"        All tokens must exactly match as character\n"
"        strings, else Incorrect Output.\n"
"\n"
"    To compare integers without a -number option,\n"
"    any high order zeros, initial + sign, or initial\n"
"    - sign before a zero integer are ignored.\n"
"\n"
"    To compare numbers otherwise, they are convert-\n"
"    ed to IEEE 64 bit numbers.  If neither is infin-\n"
"    ity and there is a -float or -number option,\n"
"    they are compared using the A and R given in\n"
"    that option.  Otherwise they must be equal.\n"
"\n"
"    The relative difference between two numbers x\n"
"    and y is:\n"
"\n"
"                        | x - y |\n"
"                     ----------------\n"
"                     max ( |x|, |y| )\n"
"\n"
"    and is never larger than 2.  If x == y == 0 this\n"
"    relative difference is taken to be zero.\n"
"\n"
"    For the purpose of computing the column of a\n"
"    character, tabs are set every 8 columns.\n"
;

// Options:
//
bool debug = false;
bool blank_opt = false;
bool column_opt = false;
bool case_format_opt = false;
bool case_incorrect_opt = false;
bool decimal_opt = false;
bool number_opt = false;
double number_A, number_R;
bool float_opt = false;
bool exact_opt = false;

enum token_type {
    NO_TOKEN = 0,
    WORD_TOKEN, SEPARATOR_TOKEN, INTEGER_TOKEN,
		FLOAT_TOKEN,
    EOL_TOKEN };

// Type names for debugging only.
//
const char * token_type_name[] = {
    "no-token",
    "word", "separator", "integer", "float",
    "end-of-line" };

struct file
    // Information about one of the input files (output
    // or test file).
{
    ifstream stream;	// Input stream.
    char * filename;	// File name.
    const char * id;	// Either "Ouput File" or
    			// "Test File".

    string line;	// Current line if file not
    			// at_end or at beginning.
    int line_number;	// After end of file this is
    			// number of lines in file + 1.
			// 0 if file before first line.
    bool at_end;	// True if file is at end.
    bool is_blank;	// True if line is blank and
    			// file is NOT at end.

    // Token description.
    //
    token_type type;	// Type of token.
    int start, end;	// Token is line[start,end-1].
    int column;		// Column within the line of
    			// the last character of the
			// the current token.  The first
			// column is 1.

};

// The two files.
//
file files[2];
file & output = files[0];;
file & test = files[1];;

vector<string> format_errors;
vector<string> incorrect_errors;
int number_format_proofs = 0;
int number_incorrect_proofs = 0;

void check_incorrect ( void )
{
    if ( incorrect_errors.size() > 0 )
    {
        cout << "Incorrect Output";
	for ( int i = 0;
	      i < incorrect_errors.size(); ++ i )
	    cout << incorrect_errors[i] << endl;
	exit ( 1 );
    }
}
void check_format ( void )
{
    if ( format_errors.size() > 0 )
    {
        cout << "Format Error";
	for ( int i = 0;
	      i < format_errors.size(); ++ i )
	    cout << format_errors[i] << endl;
	exit ( 1 );
    }
}

// Put first 40 characters of string in truncate_buffer
// and if string has more than 40 characters, follow
// it with "...".  Return address of truncate_buffer.
//
char truncate_buffer[50];
const char * truncate ( const string & s )
{
    int n = s.size();
    if ( n > 40 )
    {
        strncpy ( truncate_buffer, s.c_str(), 40 );
	strcpy ( truncate_buffer + 40, "..." );
    }
    else
        strcpy ( truncate_buffer, s.c_str() );
    return truncate_buffer;
}

void error_lines ( vector<string> & e )
{
    if ( & e == & incorrect_errors )
    {
	if ( number_incorrect_proofs >= PROOF_LIMIT )
	    check_incorrect();  // does not return
	++ number_incorrect_proofs;
    }
    else
    {
	if ( number_format_proofs >= PROOF_LIMIT )
	    return;
	++ number_format_proofs;
    }

    char buffer[1000];
    for ( int i = 0; i < 2; ++ i )
    {
        sprintf ( buffer, "%s line %d: %s",
	          files[i].id,
		  files[i].line_number,
		  files[i].at_end ?
		    "<end of file>" :
		     truncate ( files[i].line ) );
	assert ( strlen ( buffer ) <= 80 );
	e.push_back ( string ( buffer ) );
    }
}

void error_message
        ( vector<string> & e, const char * format,
	                      va_list args )
{
    char buffer[1000];
    strcpy ( buffer, "    " );
    vsprintf ( buffer + 4, format, args );
    assert ( strlen ( buffer ) <= 80 );
    e.push_back ( string ( buffer ) );
}

void error_message
        ( vector<string> & e, const char * format... )
{
    va_list args;
    va_start ( args, format );
    error_message ( e, format, args );
    va_end ( args );
}

void error_token
        ( vector<string> & e, file & f )
{
    char buffer[1000];
    int n = sprintf ( buffer, "    %s %s token",
                      f.id, token_type_name[f.type] );
    int len = f.end - f.start;
    if ( len > 0 ) n += sprintf ( buffer + n, ": " );
    if ( len <= 40 )
        strncpy ( buffer + n, f.line.c_str() + f.start,
	                      len );
    else
    {
        strncpy ( buffer + n,
	          f.line.c_str() + f.start, 20 );
        strcpy ( buffer + n + 20, "..." );
        strncpy ( buffer + n + 23,
	          f.line.c_str() + f.end - 20, 20 );
    }
    e.push_back ( string ( buffer ) );
}

void non_token_error
        ( vector<string> & e, const char * format... )
{
    error_lines ( e );
    va_list args;
    va_start ( args, format );
    error_message ( e, format, args );
    va_end ( args );
}

void token_error
        ( vector<string> & e, const char * format... )
{
    error_lines ( e );
    error_token ( e, output );
    va_list args;
    va_start ( args, format );
    error_message ( e, format, args );
    va_end ( args );
    error_token ( e, test );
}

// Open file for reading.
//
void open ( file & f, char * filename )
{
    f.filename = new char [strlen ( filename ) + 1 ];
    strcpy ( f.filename, filename );
    f.id = ( & f == & output ? "Output File" :
                               "Test File" );

    f.stream.open ( filename );
    if ( ! f.stream ) {
        cout << "ERROR: not readable: "
	     << filename << endl;
    	exit ( 1 );
    }

    f.line_number	= 0;
    f.type		= NO_TOKEN;
}

// Get next line.  If end of file, set at_end and
// is_blank.  If called when file is at_end, to nothing.
// If file not at_end, set token type to NO_TOKEN,
// set is_blank, and initialize start, end, and column
// to 0.
//
void get_line ( file & f )
{
    if ( f.at_end ) return;
    ++ f.line_number;
    if ( ! getline ( f.stream, f.line ) )
         f.at_end = true;
    else
    {
        const char * p = f.line.c_str();
	f.is_blank = true;
	while ( f.is_blank && * p )
	    f.is_blank = isspace(*p++);
	f.type   = NO_TOKEN;
	f.start  = 0;
	f.end    = 0;
	f.column = 0;
    }

    if ( ! debug ) return;

    cout << f.id
         << " " << f.line_number
         << ": "
	 << ( f.at_end ? "<end-of-file>" :
	                 truncate ( f.line ) )
	 << endl;
}

// Get next token.  If file at_end or file type is
// EOL_TOKEN do nothing.  Otherwise set f.type,
// f.column, f.start, and f.end.
//
void get_token ( file & f )
{
    if ( f.at_end ) return;
    if ( f.type == EOL_TOKEN ) return;

    const char * lp = f.line.c_str();
    const char * p = lp + f.end;
    bool point_found;  // Declare here before goto's.
    const char * q;    // Ditto.
    while ( * p && isspace ( * p ) )
    {
        if ( * p == ' ' ) ++ f.column;
	else if ( * p == '\t' )
	    f.column += 8 - ( f.column % 8 );
	else if ( * p == '\f' )
	    non_token_error
	        ( format_errors,
	          "form feed character in column %d",
		  f.column );
	else if ( * p == '\v' )
	    non_token_error
	        ( format_errors,
	          "vertical space character"
		  " in column %d", f.column );
	// else its carriage return and we ignore it.
    }

    f.start = p - lp;
    if ( * p == 0 )
    {
        f.type = EOL_TOKEN;
	goto TOKEN_DONE;
    }
    if ( isalpha ( * p ) )
    {
	++ p;
	while ( isalpha ( * p ) ) ++ p;
	f.type = WORD_TOKEN;
	goto TOKEN_DONE;
    }
    q = p;
    while ( * p && ! isdigit ( * p )
		&& ! isalpha ( * p )
		&& ! isspace ( * p ) )
	 ++ p;
    if ( isdigit ( * p ) )
    {
	if ( p > q && p[-1] == '.' ) -- p;
	if ( p > q && (    p[-1] == '+'
			|| p[-1] == '-' ) )
	    -- p;
    }
    if ( p > q )
    {
        f.type = SEPARATOR_TOKEN;
	goto TOKEN_DONE;
    }

    // p must be start of number.
    //
    if ( * p == '+' || * p == '-' ) ++ p;
    point_found = ( * p == '.' );
    if ( point_found ) ++ p;
    while ( isdigit ( * p ) ) ++ p;
        // There must be at least one digit as we were
	// at start of number.
    if ( * p == '.' )
    {
        if ( point_found )
	{
	    f.type = FLOAT_TOKEN;
	    goto TOKEN_DONE;
	}
	point_found = true;
	++ p;
	while ( isdigit ( * p ) ) ++ p;
    }
    if ( * p == 'e' || * p == 'E' )
    {
	q = p;
        ++ p;
	if ( * p == '+' || * p == '-' ) ++ p;
	if ( isdigit ( * p ) )
	{
	    ++ p;
	    while ( isdigit ( * p ) ) ++ p;
	    f.type = FLOAT_TOKEN;
	    goto TOKEN_DONE;
	}
	p = q;
    }
    f.type = ( point_found ? FLOAT_TOKEN
                           : INTEGER_TOKEN );

TOKEN_DONE:

    f.end = p - lp;
    f.column += f.end - f.start;

    if ( ! debug ) return;

    cout << f.id
         << " " << f.line_number
         << ":" << f.column
	 << " " << token_type_name[f.type]
	 << " ";
    for ( int i = f.start; i < f.end; ++ i )
    {
        if ( i > f.start + 40 )
	{
	    cout << "...";
	    break;
	}
	cout << f.line[i];
    }
    cout << endl;
}

// Computes the floating point value of the current
// number token.  May be + - INFINITY.
//
double number ( file & f )
{
    assert ( f.type == INTEGER_TOKEN
             ||
	     f.type == FLOAT_TOKEN );
    int len = f.end - f.start;
    char buffer[len+1];
    strncpy ( buffer, f.line.c_str() + f.start, len );
    char * endp;
    double r = strtod ( buffer, & endp );
    assert ( * endp == 0 );
    if ( r == + HUGE_VAL ) r = + INFINITY;
    else
    if ( r == - HUGE_VAL ) r = - INFINITY;
    return r;
}

// Tests two integer tokens of arbitrary length for
// equality.  Ignores initial + sign, initial - sign
// for zeros, and high order zeros.
//
bool integers_are_equal ( void )
{
    const char * p1 =
        output.line.c_str() + output.start;
    const char * e1 =
        output.line.c_str() + output.end;
    const char * p2 =
        test.line.c_str() + test.start;
    const char * e2 =
        test.line.c_str() + test.end;

    int s1 = +1, s2 = +1;
    if ( * p1 == '+' ) ++ p1;
    else if ( * p1 == '-' ) s1 = -1, ++ p1;
    if ( * p2 == '+' ) ++ p2;
    else if ( * p2 == '-' ) s2 = -1, ++ p2;
    while ( * p1 == '0' && p1 < e1 ) ++ p1;
    while ( * p2 == '0' && p2 < e2 ) ++ p2;
    if ( p1 == e1 && p2 == e2 ) return true;
        // Both integers zero.
    if ( p1 != e1 || p2 == e2 ) return false;
        // One integer zero.
    if ( s1 != s2 ) return false;
        // Non-zero integers of different sign.
    if ( ( e1 - p1 ) != ( e2 - p2 ) ) return false;
        // Non-zero integers of different length.
    return strncmp ( p1, p2, p1 - e1 ) == 0;
}

// Main program.
//
int main ( int argc, char ** argv )
{

    // Process options.

    while ( argc >= 2 && argv[1][0] == '-' )
    {

	char * name = argv[1] + 1;

        if ( strncmp ( "doc", name, 3 ) == 0 )
	{
	    // Any -doc* option prints documentation
	    // and exits with no error.
	    //
	    FILE * out = popen ( "less -F", "w" );
	    fputs ( documentation, out );
	    pclose ( out );
	    exit ( 0 );
	}
        else if ( strcmp ( "debug", name ) == 0 )
	    debug = true;
        else if ( strcmp ( "blank", name ) == 0 )
	    debug = blank_opt;
        else if ( strcmp ( "column", name ) == 0 )
	    debug = column_opt;
        else if ( strcmp ( "decimal", name ) == 0 )
	    debug = decimal_opt;
        else if ( strcmp ( "case-format", name ) == 0 )
	    debug = case_format_opt;
        else if (    strcmp ( "case-incorrect", name )
	          == 0 )
	    debug = case_incorrect_opt;
        else if ( strcmp ( "exact", name ) == 0 )
	    debug = exact_opt;
        else if (    strcmp ( "float", name ) == 0
	          || strcmp ( "number", name ) == 0 )
	{
	    // special case.

	    if ( name[0] == 'f' ) float_opt = true;
	    else number_opt = true;

	    double number_A = -1.0;
	    double number_R = -1.0;

	    if ( argc < 2 ) break;
	    if ( strcmp ( "-", argv[2] ) != 0 )
	    {
	        char * endp;
		number_A = strtod ( argv[2], & endp );
		if ( * endp )
		{
		    cerr << "Unrecognized A in"
		            " -number or -float: "
			 << argv[2] << endl;
		    exit ( 1 );
		}
	    }
	    if ( argc < 2 ) break;
	    if ( strcmp ( "-", argv[2] ) != 0 )
	    {
	        char * endp;
		number_R = strtod ( argv[2], & endp );
		if ( * endp )
		{
		    cerr << "Unrecognized R in"
		            " -number or -float: "
			 << argv[2] << endl;
		    exit ( 1 );
		}
	    }
	}
	else
	{
	    cerr << "Unrecognized option -"
		 << name
		 << endl;
	    exit (1);
	}

        ++ argv, -- argc;
	if ( isdigit ( argv[1][0] ) )
		++ argv, -- argc;
    }

    if ( float_opt && number_opt )
    {
	cerr << "cannot have BOTH -number AND -float"
	     << endl;
	exit ( 1 );
    }

    if (    ( float_opt || number_opt )
         && number_A == -1 && number_R == -1 )
    {
	cerr << "BOTH A and R cannot be `-'"
	     << endl;
	exit ( 1 );
    }

    if ( case_format_opt && case_incorrect_opt )
    {
	cerr << "cannot have BOTH -case-format AND"
	        " -case-incorrect" << endl;
	exit ( 1 );
    }

    if ( exact_opt && case_format_opt )
    {
	cerr << "cannot have BOTH -exact AND"
	        " -case-format" << endl;
	exit ( 1 );
    }

    if ( exact_opt && decimal_opt )
    {
	cerr << "cannot have BOTH -exact AND"
	        " -decimal" << endl;
	exit ( 1 );
    }

    if ( exact_opt && number_opt )
    {
	cerr << "cannot have BOTH -exact AND"
	        " -number" << endl;
	exit ( 1 );
    }

    if ( exact_opt && float_opt )
    {
	cerr << "cannot have BOTH -exact AND"
	        " -float" << endl;
	exit ( 1 );
    }

    if ( argc == 1 )
    {
        cerr << "output and test file names missing"
	     << endl;
	exit ( 1 );
    }
    if ( argc == 2 )
    {
        cerr << "test file name missing"
	     << endl;
	exit ( 1 );
    }

    // Open files.

    open ( output, argv[1] );
    open ( test, argv[2] );

    // Loop through lines.
    //
    while ( true )
    {
        get_line ( output );
	get_line ( test );

	if ( output.is_blank
	     &&
	     test.is_blank  )
	    continue;
	if ( output.is_blank )
	{
	    if ( blank_opt )
	        non_token_error
		    ( format_errors,
		      "superfluous blank line" );
	    while ( output.is_blank )
	        get_line ( output );
	}
	else if ( test.is_blank )
	{
	    if ( blank_opt )
	        non_token_error
		    ( format_errors,
		      "missing blank line" );
	    while ( test.is_blank )
	        get_line ( test );
	}
		    
	if ( output.at_end && test.at_end )
	{
	    check_incorrect();
	    check_format();
	    // If these return there are no incorrect
	    // or format errors.
	    cout << "Completely Correct" << endl;
	    exit ( 0 );
	}

	if ( output.at_end )
	{
	    check_incorrect();
	    // If this return there are no incorrect
	    // errors.
	    cout << "Incomplete Output" << endl;
	    exit ( 0 );
	}

	if ( test.at_end )
	{
	    non_token_error
	        ( incorrect_errors,
	          "extra line at end of output" );
	    check_incorrect();
	    // This does NOT return.
	}

	// Loop to check tokens of non-blank lines.
	//
	while ( true )
	{
	    get_token ( output );
	    get_token ( test );

	    if ( output.type == EOL_TOKEN
	         &&
		 test.type == EOL_TOKEN )
	        break;

	    if ( output.type == EOL_TOKEN )
	    {
	        token_error
		    ( incorrect_errors,
		      "does not match" );
		break;
	    }
	    if ( test.type == EOL_TOKEN )
	    {
	        token_error
		    ( incorrect_errors,
		      "does not match" );
		break;
	    }

	    bool output_is_number =
	        ( output.type == INTEGER_TOKEN
		  ||
		  output.type == FLOAT_TOKEN );
	    bool test_is_number =
	        ( test.type == INTEGER_TOKEN
		  ||
		  test.type == FLOAT_TOKEN );
	    int output_len = output.end - output.start;
	    int test_len   = test.end - test.start;
	    const char * p1 = output.line.c_str()
	                    + output.start;
	    const char * p2 = output.line.c_str()
	                    + output.start;

	    if ( ! output_is_number
	         ||
		 ! test_is_number )
	    {
		if ( output.type != test.type )
		{
		    token_error
			( incorrect_errors,
			  "does not match" );
		    continue;
		}

		if ( output_len != test_len )
		{
		    token_error
			( incorrect_errors,
			  "does not match" );
		    continue;
		}

		if (    strncmp ( p1, p2, output_len )
		     == 0 )
		    continue;

		if ( output.type != WORD_TOKEN
		     ||
		     case_incorrect_opt
		     ||
		        strncasecmp
			    ( p1, p2, output_len )
		     != 0 )

		{
		    token_error
			( incorrect_errors,
			  "does not match" );
		    continue;
		}

		if ( case_format_opt )
		{
		    token_error
			( format_errors,
			  "does not match letter"
			  " case of" );
		}
		continue;
	    }

	    // Both tokens are numbers.
	    //
	    if ( output.type == INTEGER_TOKEN
	         &&
		 test.type == INTEGER_TOKEN
		 &&
		 ! number_opt )
	    {
	        if ( ! integers_are_equal() )
		{
		    token_error
			( incorrect_errors,
			  "does not equal" );
		}
		continue;
	    }

	    double n1 = number ( output );
	    double n2 = number ( test );

	    if ( n1 == n2 ) continue;

	    if ( n1 == + INFINITY
	         ||
		 n1 == - INFINITY
	         ||
		 n2 == + INFINITY
	         ||
		 n2 == - INFINITY
		 ||
		 ( ! float_opt && ! number_opt )
		 ||
		 (    ! number_opt
		   && test.type != FLOAT_TOKEN ) )
	    {
		token_error
		    ( incorrect_errors,
		      "does not equal" );
		continue;
	    }

	    double diff = fabs ( n1 - n2 );
	    if ( diff == 0 ) continue;
	    if ( diff <= number_A ) continue;
	    double divisor =
	        max ( fabs ( n1 ), fabs ( n2 ) );
	    double r = diff / divisor;
	    if ( r <= number_R ) continue;
	    token_error
		( incorrect_errors,
		  "is not sufficiently equal to" );
	}
    }

    // Return from main function without error.

    return 0;
}
