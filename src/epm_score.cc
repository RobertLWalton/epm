// Educational Problem Manager Scoring Program
//
// File:	epm_score.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Mar  4 20:02:19 EST 2020
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
using std::cin;
using std::cout;
using std::cerr;
using std::endl;
using std::string;
using std::vector;
using std::ifstream;
using std::max;

unsigned const LIMIT = 5;
char const ILLEGAL = '?';
    // May not be space character.

char documentation [] =
"epm_score [options] output_file test_file\n"
"\n"
"    The output_file is the filtered problem solu-\n"
"    tion output and the test_file is the filtered\n"
"    judge's solution output.  In the simplest case\n"
"    the filter simply removes comments.\n"
"\n"
"    This program outputs descriptions of errors\n"
"    found in the output_file by comparing it to the\n"
"    test file.  If there are no errors, this program\n"
"    outputs just the line `Completely Correct'.\n"
"\n"
"    The program exit code is 0 unless there are\n"
"    errors in the program arguments.  If there are\n"
"    just errors detected by comparing the output_\n"
"    file to the test file, the exit code is 0.\n"
"\n"
"    To find errors, file lines are parsed into\n"
"    tokens, and sucessive tokens of pairs of non-\n"
"    blank lines are matched.  A token is a word, a\n"
"    number, or a separator.   White-space is not\n"
"    part of any token, but the last column of each\n"
"    token is recorded and may be used to require the\n"
"    output file to have the same spacing as the test\n"
"    file.\n"
"\n"
"    A word is just a string of ASCII letters.  A\n"
"    number is a string with the syntax:\n"
"\n"
"        number ::= integer | float\n"
"\n"
"        integer ::= sign? digit+\n"
"\n"
"        float ::= integer fraction exponent?\n"
"                | sign? fraction exponent?\n"
"                | integer decimal-point exponent?\n"
"                | integer exponent\n"
"\n"
"        fraction ::= decimal-point digit+\n"
"\f\n"
"        exponent ::= 'e' integer\n"
"                   | 'E' integer\n"
"\n"
"    Note that any number with either a decimal\n"
"    point or an exponent is a float.\n"
"\n"
"    A separator is a string of non-whitespace, non-\n"
"    letter, non-digit ASCII characters that is not\n"
"    part of a number.\n"
"\n"
"    Non-ASCII characters and ASCII control charac-\n"
"    ters other than space, horizontal tab, newline,\n"
"    carriage return, vertical space, and form feed\n"
"    replaced by ? and then treated as separator\n"
"    characters.   Vertical space and form feed\n"
"    characters are treated as within-line space\n"
"    characters that do not change the column.\n"
"\n"
"    Tokens are scanned left to right with longer\n"
"    tokens being preferred at each point.  When com-\n"
"    paring tokens, the type of the token in the test\n"
"    input determines the type of token expected.\n"
"\n"
"    The types of errors detected, in (roughly) most-\n"
"    severe-first order, are:\n"
"\n"
"        output ends too soon\n"
"        superfluous lines at end of output\n"
"        tokens missing from end of line\n"
"        extra tokens at end of line\n"
"\n"
"        token is not a number\n"
"        token is not a word\n"
"        token is not a separator\n"
"\n"
"        unequal words\n"
"        unequal separators\n"
"        unequal numbers\n"
"        unequal integers\n"
"\f\n"
"        number is not an integer*\n"
"        integer has high order zeros*\n"
"        integer has sign*\n"
"\n"
"        number has wrong number of decimal places*\n"
"        word letter cases do not match*\n"
"        token end columns are not equal*\n"
"\n"
"        superflous blank line*\n"
"        missing blank line*\n"
"\n"
"    The error types marked with * are ignored by\n"
"    default, while all other error types are not\n"
"    ignorable.\n"
"\n"
"    Information about the first L error types\n"
"    discovered, in order of discovery, is printed.\n"
"    This includes details of the first instance of\n"
"    these error types.  L is called the error type\n"
"    limit; it defaults to 5.\n"
"\n"
"    The epm_score options are:\n"
"\n"
"    -limit L\n"
"        Reset the error type limit to L.\n"
"\n"
"    -blank\n"
"        Do NOT ignore `superflous blank line' and\n"
"        `missing blank line' errors.\n"
"\n"
"    -float A R\n"
"        When either test or output token is a float,\n"
"        and both are numbers, the two numbers are\n"
"        both converted to IEEE floating point and\n"
"        tested for equality.\n"
"\f\n"
"        If the absolute difference is larger than\n"
"        A, or the relative difference is larger\n"
"        than R, the two tokens are unequal.  See\n"
"        below for definition of relative difference.\n"
"        If either A or R is `-', the A or R test is\n"
"        disabled.\n"
"\n"
"        If this option is not given, it defaults to\n"
"        `-float 0 -'.\n"
"\n"
"        The -integer option below separately requi-\n"
"        es the output token to be an integer when\n"
"        the test token is an integer.\n"
"\n"
"    -places\n"
"        Do NOT ignore `number has wrong number of\n"
"        decimal places' errors.  The number of\n"
"        places expected for the output number is the\n"
"        the number of places for the test number\n"
"        token.\n"
"\n"
"    -integer\n"
"        Do NOT ignore `number is not an integer'\n"
"        errors.  This means that if the test token\n"
"        is an integer, the output token must be an\n"
"        integer.\n"
"\n"
"        When both `-integer' and `-float A R' are\n"
"        given, care must be taken that all test\n"
"        number tokens that are to be compared using\n"
"        -float contain a decimal point.\n"
"\n"
"    -zeros\n"
"        Do NOT ignore `integer has high order\n"
"        zeros' errors.  The output integer token\n"
"        will not be allowed to have a high order\n"
"        zero unless the test integer token has a\n"
"        high order zero.\n"
"\f\n"
"    -sign\n"
"        Do NOT ignore `integer has sign' errors.\n"
"        The output integer token will not be\n"
"        allowed to have a sign unless the test\n"
"        integer token has a sign.\n"
"\n"
"    -case\n"
"        Do NOT ignore `word letter cases do not\n"
"        match' errors.\n"
"\n"
"    -column\n"
"        Do NOT ignore `token end columns are not\n"
"        equal' errors.  When computing the column\n"
"        of a character, tabs are set every 8 col-\n"
"        umns.\n"
"\n"
"    To compare two integers, any high order zeros\n"
"    and initial + sign are ignored, and -0 is\n"
"    treated as 0.  Integers may be arbitrarily long.\n"
"\n"
"    To compare numbers otherwise, they are convert-\n"
"    ed to IEEE 64 bit numbers.  It is possible that\n"
"    a converted number will be an infinity.\n"
"\n"
"    The relative difference between two numbers x\n"
"    and y is:\n"
"\n"
"                        | x - y |\n"
"                     ----------------\n"
"                     max ( |x|, |y| )\n"
"\n"
"    and is never larger than 2 (unless x or y is an\n"
"    infinity).  If x == y == 0 this relative differ-\n"
"    ence is taken to be zero.\n"
"\n"
"    When + or - infinity is compared to any not-\n"
"    exactly-equal value, the differences are\n"
"    infinity, and when compared to an exactly-equal\n"
"    value, the differences are 0.\n"
;

// Options:
//
bool debug = false;
bool float_opt = false;
double number_A = 0;
double number_R = NAN;
    // Do disable A or R, set these to NAN.
long int limit = LIMIT;
    // Limit on error_type_stack length.

enum token_type {
    NO_TOKEN = 0,
    WORD, SEPARATOR, INTEGER, FLOAT, EOL };

// Type names.
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

    // Illegal character information.
    //
    long long illegal_count;
    			// Number of illegal characters
			// found in the file.
    int illegal_line_number;
    			// Line number of first line
			// with illegal character (or
			// 0 if no illegal characters).

    // Token description.
    //
    token_type type;	// Type of token.
    int start, end;	// Token is line[start,end-1].
    int column;		// Column within the line of
    			// the last character of the
			// the current token.  The first
			// column is 1.
    int places;		// Number of decimal places if
    			// type == FLOAT.  0 if type ==
			// INTEGER.
    bool has_sign;	// When type == INTEGER, true
    			// iff token has sign.
    bool has_high_zero;	// When type == INTEGER, true
    			// iff token has high order
			// zero.
    char token[81];	// Copy of token for error
    			// messages, computed by the
			// `token' function.  Middle
			// of long tokens is elided by
			// `...'s.

};

// The two files.
//
file files[2];
file & output = files[0];
file & test = files[1];

// Open files for reading.
//
void open_files ( const char * output_file_name,
                  const char * test_file_name )
{
    for ( int i = 0; i < 2; ++ i )
    {
	file & f = files[i];
        f.at_end = f.is_blank = false;
	f.line_number = 0;
	f.type = NO_TOKEN;
	f.illegal_count = 0;
	f.illegal_line_number = 0;

	const char * file_name;
	if ( i == 0 )
	{
	    f.id = "Output File";
	    file_name = output_file_name;
	}
	else
	{
	    f.id = "Test File";
	    file_name = test_file_name;
	}
	f.stream.open ( file_name );
	if ( ! f.stream ) {
	    cout << "ERROR: not readable: "
		 << file_name << endl;
	    exit ( 1 );
	}
    }
}

// Get next line.  If end of file, set at_end and clear
// is_blank.  If called when file is at_end, to nothing.
// If file not at_end, set token type to NO_TOKEN,
// set is_blank, and initialize start, end, and column
// to 0.  Information about illegal character is also
// maintained.
//
void get_line ( file & f )
{
    if ( f.at_end ) return;
    ++ f.line_number;
    if ( ! getline ( f.stream, f.line ) )
         f.at_end = true, f.is_blank = false;
    else
    {
        // We do a fast scan to check for illegal
	// characters before replacing any, as
	// replacement may take time.
	//
        const char * p = f.line.c_str();
	f.is_blank = true;
	bool has_illegal = false;
	while ( ! has_illegal && * p )
	{
	    char c = * p;
	    switch ( c )
	    {
	        case ' ':
		case '\t':
		case '\n':
		case '\r':
		    break;
		default:
		    f.is_blank = false;
		    has_illegal =
		        ( c < ' ' || c > '~' );
	    }
	}

	if ( has_illegal )
	{
	    size_t s = f.line.size();
	    for ( size_t i = 0; i < s; ++ i )
	    {
		char & c = f.line[i];
		switch ( c )
		{
		    case ' ':
		    case '\t':
		    case '\n':
		    case '\r':
			break;
		    default:
			if ( c < ' ' || c > '~' )
			{
			    c = ILLEGAL;
			    if (    ++ f.illegal_count
			         == 1 )
				f.illegal_line_number
				    = f.line_number;
			}
		}
	    }
	}

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
	                 f.line.c_str() )
	 << endl;
}

// Return representation of f's token that has at most
// width characters.  If token is longer then width,
// cut characters out of the middle of the token and
// replace them with `...'.  Returned value is only
// valid until next call to this function with same f.
//
const char * token ( file & f, int width = 20 )
{
    assert ( f.type != NO_TOKEN );
    assert ( width >= 15 );
    assert ( width <= 80 );
    if ( f.type == EOL )
        return "<end-of-line>";

    int w = f.end - f.start;
    const char * s = f.line.c_str();
    if ( w <= width )
    {
        strncpy ( f.token, s + f.start, w );
	f.token[w] = 0;
    }
    else
    {
        w = ( width - 3 ) / 2;
	strncpy ( f.token, s + f.start, w );
	strcpy ( f.token + w, "..." );
	strncpy ( f.token + w + 3, s + f.end - w, w );
	f.token[2*w+3] = 0;
    }
    return f.token;
}

struct error_type * last = NULL;
    // Tail of chain of error_types, in most important
    // last order.
struct error_type
{
    const char * title;
    char buffer[4096];
        // Error output for first error of this kind.
    const char * option_name;
        // Option name to NOT ignore this error.
	// NULL if no such option.
    bool ignore;
        // Set to ignore this error.
    long long count;
        // Number of errors of this kind detected so
	// far.
    error_type * previous;
        // Pointer to previous error_type in chain.

    error_type ( const char * title,
                 const char * option_name = NULL )
    {
        this->title = title;
        this->option_name = option_name;
	ignore = ( option_name != NULL );
	buffer[0] = 0;
	count = 0;
	previous = last;
	last = this;
    }
};

// Error Types in least serious first order (so when
// chaining from last to first its most serious first
// order).
//
error_type superfluous_blank_line
    ( "Superfluous Blank Line", "blank" );
error_type missing_blank_line
    ( "Missing Blank Line", "blank" );

error_type token_end_columns_are_not_equal
    ( "Token End Columns Are Not Equal", "column" );
error_type word_letter_cases_do_not_match
    ( "Word Letter Cases Do Not Match", "case" );
error_type number_has_wrong_number_of_places
    ( "Number Has Wrong Number of Decimal Places",
      "places" );

error_type integer_has_sign
    ( "Integer Has Sign", "sign" );
error_type integer_has_high_order_zeros
    ( "Integer Has High Order Zeros", "zeros" );
error_type token_is_not_an_integer
    ( "Token Is Not An Integer", "integer" );

error_type unequal_integers
    ( "Unequal Integers" );
error_type unequal_numbers
    ( "Unequal Numbers" );
error_type unequal_separators
    ( "Unequal Separators" );
error_type unequal_words
    ( "Unequal Words" );

error_type token_is_not_a_separator
    ( "Token Is Not A Separator" );
error_type token_is_not_a_word
    ( "Token Is Not A Word" );
error_type token_is_not_a_number
    ( "Token Is Not A Number" );

error_type extra_tokens_at_end_of_line
    ( "Extra Tokens At End Of Line" );
error_type tokens_missing_from_end_of_line
    ( "Missing Tokens From End Of Line" );

error_type superfluous_lines_at_end_of_output
    ( "Superfluous Lines At End Of Output" );
error_type output_ends_too_soon
    ( "Output Ends To Soon" );

// Stack of error_types.  An error_type is pushed into
// this stack when first encountered, using error_type_
// count as the length of the stack.  If stack length
// becomes above limit, the program gives up before
// processing the next line pair.
//
error_type * error_type_stack[100];
int error_type_count = 0;

// Write error message content describing current lines
// for both files into buffer denoted by p and return
// pointer to NUL at end of content.
//
char * print_file_lines ( char * p )
{
    for ( int i = 0; i < 2; ++ i )
    {
        p += sprintf ( p, "  %s line %d: ",
	               files[i].id,
		       files[i].line_number );
	if ( files[i].at_end )
	    p += sprintf ( p, "<end-of-file>\n" );
	else
	{
	    int w = files[i].line.size();
	    const char * s = files[i].line.c_str();
	    if ( w <= 40 )
	        p += sprintf ( p, "%s\n", s );
	    else
	    {
	        strncpy ( p, s, 37 );
		strcpy ( p + 37, "...\n" );
		p += 41;
	    }
	}
    }
    return p;
}

// Increment e.count, and return if new count is > 1.
// Else put e on error_type_stack and write an error
// message to be output into e.buffer.
//
// Message begins with current file lines, indented by
// 2 spaces.  This is followed by printf of format...
// preceded by 4 spaces and followed by a newline.
//
// If more than one line is to be output by printf, each
// non-last line must be indicated by '\n    '
// (including 4 spaces).
//
void error ( error_type & e, const char * format... )
{
    if ( ++ e.count > 1 ) return;

    error_type_stack[error_type_count++] = & e;

    char * p = print_file_lines ( e.buffer );
    strcpy ( p, "    " );
    p += 4;
    va_list args;
    va_start ( args, format );
    p += vsprintf ( p, format, args );
    va_end ( args );
    * p ++ = '\n';
    * p = 0;
}

// Get next token.  If file at_end or file type is
// EOL do nothing.  Otherwise set f.type, f.column,
// f.start, f.end, f.column, f.places, f.has_sign,
// and f.has_high_zero.
//
void get_token ( file & f )
{
    if ( f.at_end ) return;
    if ( f.type == EOL ) return;

    const char * lp = f.line.c_str();
    const char * p = lp + f.end;
    const char * q;    // Ditto.
    while ( * p && isspace ( * p ) )
    {
        if ( * p == ' ' ) ++ f.column;
	else if ( * p == '\t' )
	    f.column += 8 - ( f.column % 8 );
	++ p;
    }

    f.start = p - lp;

    if ( * p == 0 )
    {
        f.type = EOL;
	goto TOKEN_DONE;
    }
    if ( isalpha ( * p ) )
    {
	++ p;
	while ( isalpha ( * p ) ) ++ p;
	f.type = WORD;
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
        f.type = SEPARATOR;
	goto TOKEN_DONE;
    }

    // p must be start of number.
    //
    f.type = INTEGER;
    f.places = 0;
    f.has_high_zero = false;
    f.has_sign = false;

    if ( * p == '+' || * p == '-' )
        f.has_sign = true, ++ p;
    if ( * p == '.' )
    {
	++ p;
	f.type = FLOAT;
    }
    assert ( isdigit ( * p ) );
        // There must be at least one digit as we were
	// at start of number.
    if ( * p == '0' && isdigit ( p[1] ) )
        f.has_high_zero = true;
    q = p;
    ++ p;
    while ( isdigit ( * p ) ) ++ p;
    if ( f.type == FLOAT ) f.places = p - q;
    if ( * p == '.' )
    {
        if ( f.type == FLOAT )
	{
	    // point seen before in token
	    //
	    goto TOKEN_DONE;
	}
	f.type = FLOAT;
	++ p;
	q = p;
	while ( isdigit ( * p ) ) ++ p;
	f.places = p - q;
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
	    f.type = FLOAT;
	    goto TOKEN_DONE;
	}
	p = q;
    }

TOKEN_DONE:

    f.end = p - lp;
    f.column += f.end - f.start;


    if ( debug )
	cout << f.id
	     << " " << f.line_number
	     << ":" << f.column
	     << " " << token_type_name[f.type]
	     << " " << token ( f, 40 ) << endl;
}

// Computes the floating point value of the current
// number token.  May be + - INFINITY.
//
double number ( file & f )
{
    assert ( f.type == INTEGER
             ||
	     f.type == FLOAT );
    int len = f.end - f.start;
    char buffer[len+1];
    strncpy ( buffer, f.line.c_str() + f.start, len );
    buffer[len] = 0;
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
    if ( p1 == e1 || p2 == e2 ) return false;
        // One integer zero.
    if ( s1 != s2 ) return false;
        // Non-zero integers of different sign.
    if ( ( e1 - p1 ) != ( e2 - p2 ) ) return false;
        // Non-zero integers of different length.
    return strncmp ( p1, p2, p1 - e1 ) == 0;
}

// Compare number tokens using IEEE floating point.
// Also check number of decimal places if necessary.
//
void compare_numbers ( void )
{
    double n1 = number ( output );
    double n2 = number ( test );

    // Note: if n1 and n2 are both infinities of the
    // same sign, they compare equal.
    //
    // Note: no double can be > NAN.
    //
    if ( n1 != n2
	 &&
	 fabs ( n1 - n2 ) > number_A )
	error ( unequal_numbers,
		"output token %s and test token %s"
		" are unequal numbers,\n    their"
		" absolute difference %g is > %g",
		token ( output ),
		token ( test ),
		fabs ( n1 - n2 ),
		number_A );
    else if ( n1 != n2 )
    {
	double divisor =
	    max ( fabs ( n1 ), fabs ( n2 ) );
	double r = fabs ( n1 - n2 )
		 / divisor;
	if ( r > number_R )
	    error ( unequal_numbers,
		    "output token %s and test token"
		    " %s are unequal numbers,\n    "
		    "their relative difference %g"
		    " is > %g",
		    token ( output ),
		    token ( test ),
		    r, number_R );
    }
    if ( output.places != test.places
	 &&
	 !  number_has_wrong_number_of_places.ignore )
	error
	( number_has_wrong_number_of_places,
	  "    output token %s and test"
	  " token %s have different numbers"
	  " of decimal places",
	  token ( output ),
	  token ( test ) );
}

// Main program.
//
int main ( int argc, char ** argv )
{

    // Process options.

    while ( argc >= 2 && argv[1][0] == '-' )
    {

	const char * name = argv[1] + 1;

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
        else if ( strncmp ( "deb", name, 3 ) == 0 )
	    debug = true;
	else if ( strcmp ( "limit", name ) == 0 )
	{
	    if ( limit != LIMIT )
	    {
	        cerr << "too many " << argv[1]
		     << " options";
		exit ( 1 );
	    }

	    ++ argv, -- argc;
	    if ( argc < 2 ) break;
	    char * endp;
	    limit = strtol ( argv[1], & endp, 10 );
	    if ( * endp || limit < 0 )
	    {
		cerr << "Unrecognized L for"
			" -limit: "
		     << argv[1] << endl;
		exit ( 1 );
	    }
	}
        else if ( strcmp ( "float", name ) == 0 )
	{
	    if ( float_opt )
	    {
	        cerr << "too many " << argv[1]
		     << " options";
		exit ( 1 );
	    }
	    float_opt = true;

	    ++ argv, -- argc;
	    if ( argc < 2 ) break;
	    if ( strcmp ( "-", argv[1] ) == 0 )
	        number_A = NAN;
	    else
	    {
	        char * endp;
		number_A = strtod ( argv[1], & endp );
		if ( * endp )
		{
		    cerr << "Unrecognized A for"
		            " -float: "
			 << argv[1] << endl;
		    exit ( 1 );
		}
	    }

	    ++ argv, -- argc;
	    if ( argc < 2 ) break;
	    if ( strcmp ( "-", argv[1] ) == 0 )
	        number_R = NAN;
	    else
	    {
	        char * endp;
		number_R = strtod ( argv[1], & endp );
		if ( * endp )
		{
		    cerr << "Unrecognized R for"
		            " -float: "
			 << argv[1] << endl;
		    exit ( 1 );
		}
	    }
	}
	else
	{
	    error_type * ep = last;
	    bool found = false;
	    while ( ep != NULL )
	    {
		error_type & e = * ep;
		if ( e.option_name == NULL ) continue;
	        if (    strcmp ( name, e.option_name )
		     != 0 )
		    continue;
		if ( ! e.ignore )
		{
		    cerr << "too many " << argv[1]
			 << " options";
		    exit ( 1 );
		}
		e.ignore = false;
		found = true;
	    }
	    if ( ! found )
	    {
		cerr << "Unrecognized option -"
		     << name
		     << endl;
		exit (1);
	    }
	}

        ++ argv, -- argc;
    }

    if ( argc < 3 )
    {
        cerr << "file name(s) missing"
	     << endl;
	exit ( 1 );
    }
    if ( argc > 3 )
    {
        cerr << "too many arguments"
	     << endl;
	exit ( 1 );
    }

    bool ignore_blank =
        superfluous_blank_line.ignore;
    bool ignore_case =
	word_letter_cases_do_not_match.ignore;
    bool ignore_column =
	token_end_columns_are_not_equal.ignore;
    bool ignore_integer =
	token_is_not_an_integer.ignore;
    bool ignore_high_zero =
	integer_has_high_order_zeros.ignore;
    bool ignore_sign =
	integer_has_sign.ignore;

    // Open files.

    open_files ( argv[1], argv[2] );

    // Loop through lines.
    //
    while ( true )
    {
        get_line ( output );
	get_line ( test );

	if ( error_type_count > limit )
	{
	    char buffer[4096];
	    print_file_lines ( buffer );
	    cout << error_type_count
	         << " Types of Errors Detected"
		 << endl
		 << "Giving Up At:"
		 << endl
		 << buffer;
	    break;
	}

	if ( output.is_blank
	     &&
	     test.is_blank  )
	    continue;
	if ( output.is_blank )
	{
	    if ( ! ignore_blank )
	        error ( superfluous_blank_line,
		        "superfluous blank line" );
	    while ( output.is_blank )
	        get_line ( output );
	}
	else if ( test.is_blank )
	{
	    if ( ! ignore_blank )
	        error ( missing_blank_line,
		        "missing blank line" );
	    while ( test.is_blank )
	        get_line ( test );
	}
		    
	if ( output.at_end && test.at_end )
	    break;

	if ( output.at_end )
	{
	    error ( output_ends_too_soon,
	            "output ends too soon" );
	    break;
	}

	if ( test.at_end )
	{
	    error ( superfluous_lines_at_end_of_output,
	            "superfluous lines at end of"
		    " output" );
	    break;
	}

	// Loop to check tokens of non-blank lines.
	// 
	while ( true )
	{
	    get_token ( output );
	    get_token ( test );

	    if ( output.type == EOL
	         &&
		 test.type == EOL )
	        break;

	    if ( output.type == EOL )
	    {
		error ( tokens_missing_from_end_of_line,
		        "test token %s and (any)"
			" following test tokens missing"
			" from end of output line",
			token ( test ) );
		break;
	    }
	    if ( test.type == EOL )
	    {
		error ( extra_tokens_at_end_of_line,
		        "extra output token %s and"
			" (any) following extra output"
			" tokens at end of output line",
			token ( output ) );
		break;
	    }

	    if (    ! ignore_column
	         && output.column != test.column )
		error ( token_end_columns_are_not_equal,
		        "output token %s does not end"
			" in the same column as test"
			" token %s",
			token ( output ),
			token ( test ) );

	    if ( test.type == INTEGER )
	    {
		if ( output.type == FLOAT )
		{
		    if ( ! ignore_integer )
			error
			  ( token_is_not_an_integer,
			    "output token %s should be"
			    " an integer because test"
			    " token %s is an integer",
			    token ( output ),
			    token ( test ) );
		    compare_numbers();
		}
	        else if ( output.type != INTEGER )
		    error ( token_is_not_a_number,
		            "output token %s is not"
			    " a number"
			    " (should be %s)",
			    token ( output ),
			    token ( test ) );
		else
		{
		    if ( ! integers_are_equal() )
		      error
		        ( unequal_integers,
			  "output integer token %s is"
			  " not equal to test integer"
			  " token %s",
			  token ( output ),
			  token ( test ) );
		    if ( output.has_high_zero
			 &&
			 ! test.has_high_zero
		         &&
			 ! ignore_high_zero )
		      error
			( integer_has_high_order_zeros,
			  "output integer token %s has"
			  " high order zeros"
			  " unlike test token %s",
			  token ( output ),
			  token ( test ) );
		    if ( output.has_sign
		         &&
			 ! test.has_sign
			 &&
			 ! ignore_sign )
		      error
			( integer_has_sign,
			  "output integer token %s has"
			  " sign"
			  " unlike test token %s",
			  token ( output ),
			  token ( test ) );
		}
	    }
	    else if ( test.type == FLOAT )
	    {
	        if ( output.type != FLOAT
		     &&
		     output.type != INTEGER )
		    error ( token_is_not_a_number,
			    "output token %s is not"
			    " a number (should be %s)",
			    token ( output ),
			    token ( test ) );
		else
		    compare_numbers();
	    }
	    else if ( test.type != output.type )
	    {
		if ( test.type == WORD )
		    error ( token_is_not_a_word,
		            "output token %s is not"
			    " a word (should be %s)",
			    token ( output ),
			    token ( test ) );
		else // test.type == SEPARATOR
		    error ( token_is_not_a_separator,
		            "output token %s is not"
			    " a separator"
			    " (should be %s)",
			    token ( output ),
			    token ( test ) );
	    }
	    else
	    {
		int output_len =
		    output.end - output.start;
		int test_len =
		    test.end - test.start;
		const char * p1 = output.line.c_str()
				+ output.start;
		const char * p2 = test.line.c_str()
				+ test.start;
		if ( test_len != output_len
		     ||
		        strncasecmp ( p1, p2, test_len )
		     != 0 )
		    error ( test.type == WORD ?
		                unequal_words :
		                unequal_separators,
		            "output token %s and"
			    " test token %s are"
			    " unequal %ss",
			    token ( output ),
			    token ( test ),
			    token_type_name
			        [test.type] );
		else
		if ( ! ignore_case
		     &&
		     test.type == WORD
		     &&
		     strncmp ( p1, p2, test_len ) != 0 )
		    error
		      ( word_letter_cases_do_not_match,
		        "output token %s and test"
			" token %s do not have matching"
			" letter cases",
			token ( output ),
			token ( test ) );
	    }

	}
    }

    // TBD

    // Return from main function without error.

    return 0;
}
