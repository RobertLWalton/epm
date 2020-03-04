// Educational Problem Manager Scoring Program
//
// File:	epm_score.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Mar  4 03:07:16 EST 2020
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

unsigned const PROOF_LIMIT = 5;

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
"    and carriage return are considered illegal, and\n"
"    are treated as space characters that separate\n"
"    tokens.\n"
"\n"
"    Tokens are scanned left to right with longer\n"
"    tokens being preferred at each point.  When com-\n"
"    paring tokens, the type of the token in the test\n"
"    input determines the type of token expected,\n"
"    except that if the test token is an integer,\n"
"    then the expected token must be an integer,\n"
"    whereas if the test token is float, the\n"
"    expected token may be an integer or float\n"
"    number.\n"
"\f\n"
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
"\n"
"        number is not an integer*\n"
"        integer has high order zeros*\n"
"        integer has sign*\n"
"\n"
"        number has wrong number of decimal places*\n"
"        word letter cases do not match*\n"
"        token end columns are not equal*\n"
"\n"
"        illegal character in line*\n"
"        superflous blank line*\n"
"        missing blank line*\n"
"\n"
"    The error types marked with * are ignored by\n"
"    default, while all other error types are not\n"
"    ignorable.\n"
"\n"
"    The epm_score options are:\n"
"\n"
"    -blank\n"
"        Do NOT ignore `superflous blank line' and\n"
"        `missing blank line' errors.\n"
"\f\n"
"    -float A R\n"
"        When either test or output token is a float,\n"
"        and both are numbers, the two numbers are\n"
"        both converted to IEEE floating point and\n"
"        tested for equality.\n"
"\n"
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
"        decimal places expected is the number in\n"
"        the test number token.\n"
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
"\f\n"
"    -zeros\n"
"        Do NOT ignore `integer has high order\n"
"        zeros' errors.  The output integer token\n"
"        will not be allowed to have a high order\n"
"        zero unless the test integer token has a\n"
"        high order zero.\n"
"\n"
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
"    -illegal\n"
"        Do NOT ignore `illegal character in line'\n"
"        errors.\n"
"\n"
"    To compare two integers, any high order zeros\n"
"    and initial + sign are ignored, and -0 is\n"
"    treated as 0.  Integers may be arbitrarily long.\n"
"\n"
"    To compare numbers otherwise, they are convert-\n"
"    ed to IEEE 64 bit numbers.  It is possible that\n"
"    a converted number will be an infinity.\n"
"\f\n"
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

enum token_type {
    NO_TOKEN = 0,
    WORD_TOKEN, SEPARATOR_TOKEN, INTEGER_TOKEN,
		FLOAT_TOKEN,
    EOL_TOKEN };

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

    // Token description.
    //
    token_type type;	// Type of token.
    int start, end;	// Token is line[start,end-1].
    int column;		// Column within the line of
    			// the last character of the
			// the current token.  The first
			// column is 1.
    int places;		// Number of decimal places if
    			// type == FLOAT_TOKEN.  0 for
			// type == INTEGER_TOKEN.
    bool has_sign;	// When type == INTEGER_TOKEN,
                        // true iff token has sign.
    bool has_high_zero;	// When type == INTEGER_TOKEN,
                        // true iff token has high order
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
file & output = files[0];;
file & test = files[1];;

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
    if ( f.type == EOL_TOKEN )
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

    error_type ( const char * option_name = NULL )
    {
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
error_type superfluous_blank_line ( "blank" );
error_type missing_blank_line ( "blank" );
error_type illegal_character_in_line
		( "illegal-character" );

error_type token_end_columns_are_not_equal
		( "column" );
error_type word_letter_cases_do_not_match ( "case" );
error_type number_has_wrong_number_of_places
		( "places" );

error_type integer_has_sign ( "sign" );
error_type integer_has_high_order_zeros ( "zeros" );
error_type token_is_not_an_integer
		( "integer" );

error_type unequal_integers;
error_type unequal_numbers;
error_type unequal_separators;
error_type unequal_words;

error_type token_is_not_a_separator;
error_type token_is_not_a_word;
error_type token_is_not_a_number;

error_type extra_tokens_at_end_of_line;
error_type tokens_missing_from_end_of_line;

error_type superfluous_lines_at_end_of_output;
error_type output_ends_too_soon;

// Increment e.count, and return if new count is > 1.
// Else write error message to be output into e.buffer.
// Message begins with current file lines, and is
// followed by printf of format... preceded by 4 spaces
// and followed by a newline.  If more than one line
// is to be output, each non-last line must be indicated
// by '\n    ' (including 4 spaces).
//
void error ( error_type & e, const char * format... )
{
    if ( ++ e.count > 1 ) return;

    char * p = e.buffer;
    for ( int i = 0; i < 2; ++ i )
    {
        p += sprintf ( p, "%s line %d: ",
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
    strcpy ( p, "    " );
    p += 4;
    va_list args;
    va_start ( args, format );
    p += vsprintf ( p, format, args );
    va_end ( args );
    * p ++ = '\n';
    * p = 0;
}


// Get next line.  If end of file, set at_end and clear
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
         f.at_end = true, f.is_blank = false;
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
	                 f.line.c_str() )
	 << endl;
}

// Get next token.  If file at_end or file type is
// EOL_TOKEN do nothing.  Otherwise set f.type,
// f.column, f.start, f.end, f.column, f.places,
// f.has_sign, and f.has_high_zero.
//
void get_token ( file & f )
{
    if ( f.at_end ) return;
    if ( f.type == EOL_TOKEN ) return;

    const char * lp = f.line.c_str();
    const char * p = lp + f.end;
    bool point_found;  // Declare here before goto's.
    const char * q;    // Ditto.
    while ( * p && (    ! isgraph ( * p )
                     || ! isascii ( * p ) ) )
    {
        if ( * p == ' ' ) ++ f.column;
	else if ( * p == '\t' )
	    f.column += 8 - ( f.column % 8 );
	else if ( * p == '\f' )
	    error ( illegal_character_in_line,
	            "form feed character in column %d",
		    f.column );
	else if ( * p == '\v' )
	    error ( illegal_character_in_line,
	            "vertical space character in"
		    " column %d", f.column );
	else if ( ! isascii ( * p ) )
	{
	    error ( illegal_character_in_line,
	            "non-ASCII character %X in"
		    " column %d",
		    (unsigned char) * p, f.column );
	    ++ f.column;
	}
	else if ( * p != '\r' && ! isgraph ( * p ) )
	    error ( illegal_character_in_line,
	            "ASCII control character %X in"
		    " column %d",
		    (unsigned char) * p, f.column );
	// else its carriage return and we ignore it.
	
	++ p;
    }

    f.start = p - lp;

    if ( * p == 0 )
    {
        f.type = EOL_TOKEN;
	goto TOKEN_DONE;
    }
    if ( isalpha ( * p ) && isascii ( * p ) )
    {
	++ p;
	while ( isalpha ( * p ) && isascii ( * p ) )
	    ++ p;
	f.type = WORD_TOKEN;
	goto TOKEN_DONE;
    }
    q = p;
    while ( * p && ! isdigit ( * p )
		&& ! isalpha ( * p )
		&& ! isspace ( * p )
		&& isascii ( * p ) )
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
    f.type = INTEGER_TOKEN;
    f.places = 0;
    f.has_high_zero = false;
    f.has_sign = false;

    if ( * p == '+' || * p == '-' )
        f.has_sign = true, ++ p;
    if ( * p == '.' )
    {
	++ p;
	f.type = FLOAT_TOKEN;
    }
    assert ( isdigit ( * p ) );
        // There must be at least one digit as we were
	// at start of number.
    if ( * p == '0' && isdigit ( p[1] ) )
        f.has_high_zero = true;
    q = p;
    ++ p;
    while ( isdigit ( * p ) ) ++ p;
    if ( f.type == FLOAT_TOKEN ) f.places = p - q;
    if ( * p == '.' )
    {
        if ( f.type == FLOAT_TOKEN )
	{
	    // point seen before in token
	    //
	    goto TOKEN_DONE;
	}
	f.type = FLOAT_TOKEN;
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
	    f.type = FLOAT_TOKEN;
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
    assert ( f.type == INTEGER_TOKEN
             ||
	     f.type == FLOAT_TOKEN );
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
		    "    output token %s and"
		    " test token %s are unequal"
		    " numbers,\n"
		    "    their relative"
		    " difference %g is > %g",
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
        else if ( strcmp ( "float", name ) == 0 )
	{
	    // special case.
	    //
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
		    cerr << "Unrecognized A in"
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
		    cerr << "Unrecognized R in"
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

    // Open files.

    open_files ( argv[1], argv[2] );

    // Loop through lines.
    //
    bool ignore_blank = superfluous_blank_line.ignore;
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
		error ( tokens_missing_from_end_of_line,
		        "token %s and following tokens"
			" missing from end of output"
			" line", token ( test ) );
		break;
	    }
	    if ( test.type == EOL_TOKEN )
	    {
		error ( extra_tokens_at_end_of_line,
		        "extra token %s and following"
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

	    if ( test.type == INTEGER_TOKEN )
	    {
		if ( output.type == FLOAT_TOKEN )
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
	        else if ( output.type != INTEGER_TOKEN )
		    error ( token_is_not_an_integer,
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
			  " (unlike test token %s)",
			  token ( output ),
			  token ( test ) );
		    if ( output.has_high_zero
		         &&
			 ! test.has_sign
			 &&
			 ! ignore_high_zero )
		      error
			( integer_has_sign,
			  "output integer token %s has"
			  " sign"
			  " (unlike test token %s)",
			  token ( output ),
			  token ( test ) );
		}
	    }
	    else if ( test.type == FLOAT_TOKEN )
	    {
	        if ( output.type != FLOAT_TOKEN
		     &&
		     output.type != INTEGER_TOKEN )
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
		if ( test.type == WORD_TOKEN )
		    error ( token_is_not_a_word,
		            "output token %s is not"
			    " a word (should be %s)",
			    token ( output ),
			    token ( test ) );
		if ( test.type == WORD_TOKEN )
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
		    error ( test.type == WORD_TOKEN ?
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
		     test.type == WORD_TOKEN
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
