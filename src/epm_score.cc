// Educational Programming Contest Scoring Program
//
// File:	epm_score.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sat Nov 30 00:45:50 EST 2019
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

// Name defined in include file that is used below and
// needs to be changed in its usage below.
//
#ifdef INFINITY
#    undef INFINITY
#    endif
#define INFINITY Infinity

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
"                Formatting Error\n"
"                Empty Output\n"
"                Incomplete Output\n"
"                Incorrect Output\n"
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
"        option, such a skip is a Formatting Error.\n"
"\n"
"    -column\n"
"        For matching tokens, if they have different\n"
"        end column numbers it is a Formatting Error.\n"
"\n"
"    -case-formatting\n"
"        For matching word tokens, if they have\n"
"        matched letters of different case, it is a\n"
"        Formatting Error.\n"
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
"        error.  If either A or R is `-', it is ignor-\n"
"        ed.\n"
"\n"
"    -float A R\n"
"        Ditto but apply only if the test file num-\n"
"        ber is floating point.\n"
"\n"
"    -exact\n"
"        All tokens must exactly match as character\n"
"        strings, else Incorrect Output.\n"
"\n"
"    Without number or float options, numbers must\n"
"    be exactly equal.\n"
"\n"
"    To compare numbers, they are converted to IEEE\n"
"    64 bit numbers if either is floating point.  It\n"
"    is a program error if the test file number con-\n"
"    verts to an infinity, and Incorrect Output if\n"
"    the output file number converts to an infinity.\n"
"\n"
"    To compare integers without a +number option,\n"
"    any high order zeros, initial + sign, or initial\n"
"    - sign before a zero integer are ignored.\n"
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
bool decimal_opt = false;
bool case_opt = false;
bool number_opt = false;
double number_A, number_R;
bool float_opt = false;
bool exact_opt = false;

enum token_type {
    NO_TOKEN = 0, WORD_TOKEN, SEPARATOR_TOKEN, INTEGER_TOKEN,
    FLOAT_TOKEN, EOL_TOKEN, EOF_TOKEN };

// Type names for debugging only.
//
const char * token_type_name[] = {
    "no-token",
    "word", "separator", "integer", "float",
    "eof" };

struct file
    // Information about one of the input files (output
    // or test file).
{
    ifstream stream;	// Input stream.
    char * filename;	// File name.
    const char * id;	// Either "Ouput File" or
    			// "Test File".

    string line;	// Current line.
    int line_number;	// After end of file this is
    			// number of lines in file + 1.
    bool is_blank;	// True if line is blank.

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
int number_proofs = 0;

void check_errors ( void )
{
    if ( incorrect_errors.size() > 0 )
    {
        cout << "Incorrect Output";
	for ( int i = 0;
	      i < incorrect_errors.size(); ++ i )
	    cout << incorrect_errors[i] << endl;
	exit ( 1 );
    }
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
    if ( ++ number_proofs > PROOF_LIMIT )
        check_errors();  // does not return

    char buffer[1000];
    for ( int i = 0; i < 2; ++ i )
    {
        sprintf ( buffer, "%s line %d: %s",
	          files[i].id,
		  files[i].line_number,
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
    int n = sprintf ( buffer, "    %s %s token:",
                      f.id, token_type_name[f.type] );
    int len = f.end - f.start;
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

// Get next line.  If end of file, set token type
// to EOF_TOKEN.  If called when token type is
// EOF_TOKEN, does nothing.  If not end of file,
// set token type to NO_TOKEN and initialize
// start, end, and column to 0.
//
void get_line ( file & f )
{
    if ( f.type == EOF_TOKEN ) return;
    ++ f.line_number;
    if ( ! getline ( f.stream, f.line ) )
         f.type = EOF_TOKEN;
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
         << ": " << truncate ( f.line )
	 << endl;
}

// Get next token.  If end of file, does nothing.
// Updates f.column to last column of token and
// sets token f.start and f.end.
//
void get_token ( file & f )
{
    if ( f.type == EOF_TOKEN ) return;
    const char * lp = f.line.c_str();
    const char * p = lp + f.end;
    bool point_found;  // Declare here before jumps.
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


// TBD

// Tests two numbers just scanned for the output and
// test files to see if there is a computable differ-
// ence.  If so, calls found_difference (FLOAT) (or
// found_difference (INTEGER)), and updates `float_
// absdiff_maximum' and `float_reldiff_maximum' (or
// `integer_absdiff_maximum' and `integer_reldiff_
// maximum') by writing the differences just found
// into these variables iff the new differences are
// larger than the previous values of these variables.
//
// Also calls found_difference for DECIMAL, EXPONENT,
// or SIGN if the two number `decimals' or `has_expo-
// nent' file members are unequal, or the number signs
// are unequal (where a sign is the first character of a
// token if that is `+' or `-' and is `\0' otherwise)
// and the numbers are both integers.
//
// If there is no computable difference, calls found_
// difference(INFINITY) instead.  This happens if one of
// the numbers is not `finite' or their difference is
// not `finite'.
// 
void diffnumber ()
{
    if ( ! isfinite ( output.number )
	 ||
	 ! isfinite ( test.number ) )
    {
	found_difference ( INFINITY );
	return;
    }

    double absdiff =
	( output.number - test.number );
    if ( ! isfinite ( absdiff ) )
    {
	found_difference ( INFINITY );
	return;
    }
    if ( absdiff < 0 ) absdiff = - absdiff;

    double abs1 = output.number;
    if ( abs1 < 0 ) abs1 = - abs1;

    double abs2 = test.number;
    if ( abs2 < 0 ) abs2 = - abs2;

    double max = abs1;
    if ( max < abs2 ) max = abs2;

    double reldiff = absdiff == 0.0 ?
                     0.0 :
		     absdiff / max;

    if ( ! isfinite ( reldiff ) )
    {
        // Actually, this should never happen.

	found_difference ( INFINITY );
	return;
    }

    if ( output.is_float || test.is_float )
    {
	found_difference ( FLOAT, absdiff, reldiff );

	if ( absdiff > float_absdiff_maximum )
	    float_absdiff_maximum = absdiff;

	if ( reldiff > float_reldiff_maximum )
	    float_reldiff_maximum = reldiff;
    }
    else
    {
	found_difference ( INTEGER, absdiff, reldiff );

	if ( absdiff > integer_absdiff_maximum )
	    integer_absdiff_maximum = absdiff;

	if ( reldiff > integer_reldiff_maximum )
	    integer_reldiff_maximum = reldiff;

	char oc = output.token[0];
	char tc = test.token[0];

	if ( oc != '-' && oc != '+' ) oc = 0;
	if ( tc != '-' && tc != '+' ) tc = 0;

	if ( oc != tc )
	    found_difference ( SIGN );
    }

    if ( output.decimals != test.decimals )
	found_difference ( DECIMAL );

    if ( output.has_exponent != test.has_exponent )
	found_difference ( EXPONENT );
}

// Main program.
//
int main ( int argc, char ** argv )
{

    // Process options.

    while ( argc >= 4 && argv[1][0] == '-' )
    {

	char * name = argv[1] + 1;

        if (    strcmp ( "float", name ) == 0
	     || strcmp ( "integer", name ) == 0 )
	{
	    // special case.

	    double absdiff_limit = -1.0;
	    double reldiff_limit = -1.0;

	    if (    isdigit ( argv[2][0] )
	         || argv[2][0] == '.' )
	    {
		absdiff_limit = atof ( argv[2] );
		++ argv, -- argc;
		if ( argc < 3 ) break;
	    }
	    else if ( strcmp ( "-", argv[2] ) == 0 )
	    {
		++ argv, -- argc;
		if ( argc < 3 ) break;
	    }

	    if (    isdigit ( argv[2][0] )
	         || argv[2][0] == '.' )
	    {
		reldiff_limit = atof ( argv[2] );
		++ argv, -- argc;
		if ( argc < 3 ) break;
	    }
	    else if ( strcmp ( "-", argv[2] ) == 0 )
	    {
		++ argv, -- argc;
		if ( argc < 3 ) break;
	    }

	    if ( name[0] == 'f' )
	    {
	    	float_absdiff_limit = absdiff_limit;
	    	float_reldiff_limit = reldiff_limit;
	    }
	    else
	    {
	    	integer_absdiff_limit = absdiff_limit;
	    	integer_reldiff_limit = reldiff_limit;
	    }
	}
        else if ( strncmp ( "doc", name, 3 ) == 0 )
	{
	    // Any -doc* option prints documentation
	    // and exits with error status.
	    //
	    cout << documentation;
	    exit (1);
	}

	int i; for ( i = 0; i < MAX_DIFFERENCE; ++ i )
	{
	    if ( differences[i].name == NULL )
	        continue;
	    if ( strcmp ( differences[i].name, name )
	         == 0 ) break;
	}

	assert ( argc >= 3 );

    	if ( i < MAX_DIFFERENCE )
	    differences[i].proof_limit
	    	= isdigit ( argv[2][0] ) ?
		  (unsigned) atol ( argv[2] ) :
		  0;
    	else if ( strcmp ( "all", name ) == 0 )
	{
	    int limit = isdigit ( argv[2][0] ) ?
		        (unsigned) atol ( argv[2] ) :
		        0;
	    for ( int j = 0; j < MAX_DIFFERENCE; ++ j )
		differences[j].proof_limit = limit;
	}
        else if ( strcmp ( "nosign", name ) == 0 )
	    nosign = true;
        else if ( strcmp ( "nonumber", name ) == 0 )
	    nonumber = true;
        else if ( strcmp ( "filtered", name ) == 0 )
	    filtered = true;
        else if ( strcmp ( "debug", name ) == 0 )
	    debug = true;
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

    // Print documentation and exit with error status
    // unless there are exactly two program arguments
    // left.

    if ( argc != 3 )
    {
        cout << documentation;
	exit (1);
    }

    // Open files.

    open ( output, argv[1], "out" );
    open ( test, argv[2], "test" );

    // Loop that reads the two files and compares their
    // tokens, recording any differences found.

    bool done		= false;

    bool last_match_was_word_diff	= false;

    while ( ! done )
    {
	bool skip_whitespace_comparison	= false;

	// Scan next tokens.
	//
	if ( output.type != test.type )
	{
	    // Type differences for current tokens have
	    // not yet been announced by calling found_
	    // difference.

	    bool announced[MAX_TOKEN];
	    for ( int i = 0; i < MAX_TOKEN; ++ i )
	        announced[i] = false;
	    if ( output.type < test.type )
	    {
	        while ( output.type < test.type )
		{
		     if ( ! announced[output.type] )
		     {
			found_difference
			    ( type_mismatch
				( output.type,
				  test.type ) );
		        announced[output.type] = true;
		     }
		     scan_token ( output );
		}
	    }
	    else
	    {
	        while ( test.type < output.type )
		{
		     if ( ! announced[test.type] )
		     {
			found_difference
			    ( type_mismatch
				( output.type,
				  test.type ) );
		        announced[test.type] = true;
		     }
		     scan_token ( test );
		}
	    }
	    skip_whitespace_comparison = true;
	}
	else if ( last_match_was_word_diff
		  && (    output.remainder
		          != test.remainder
		       ||
		       before_nl ( output )
			   != before_nl ( test ) ) )
	{
	    assert (    ! output.remainder
	    	     || ! test.remainder );

	    // If the last two tokens had a word diff-
	    // erence and one is a remainder or a
	    // number, or one is followed by a new line
	    // and the other is not, discard the
	    // remainder, the one not followed by a new
	    // line, or the word (non-number), leaving
	    // the other token for the next match.

	    if ( output.remainder )
		scan_token ( output );
	    else if ( test.remainder )
		scan_token ( test );
	    else if (      before_nl ( test )
	              && ! before_nl ( output ) )
		scan_token ( output );
	    else if (    ! before_nl ( test )
	              &&   before_nl ( output ) )
		scan_token ( test );
	}
	else
	{
	    scan_token ( output );
	    scan_token ( test );
	}

	// Compare tokens.  Type mismatch is handled
	// at beginning of containing loop.
	//
	if ( output.type != test.type ) continue;

	last_match_was_word_diff = false;
        switch ( output.type ) {

	case EOF_TOKEN:
		done = true;
	case BOG_TOKEN:
	case BOC_TOKEN:
		break;

	case NUMBER_TOKEN:
	case WORD_TOKEN:

	    // If both tokens are words and one is
	    // longer than the other, split the longer
	    // word.  If we get a word diff, we will
	    // undo the split.

	    if ( output.type == WORD_TOKEN )
	    {
		if ( output.length < test.length )
		    split_word ( test, output.length );
		else if ( test.length < output.length )
		    split_word ( output, test.length );
	    }

	    // Compare tokens for match that is either
	    // exact or exact but for letter case.

	    char * tp1 = output.token;
	    char * tp2 = test.token;
	    char * endtp2 = tp2 + test.length;
	    bool token_match_but_for_letter_case =
	        ( output.length == test.length );
	    bool token_match =
	        token_match_but_for_letter_case;

	    while ( tp2 < endtp2
	            && token_match_but_for_letter_case )
	    {
		if ( * tp1 != * tp2 )
		{
		    token_match = false;
		    token_match_but_for_letter_case =
			( toupper ( * tp1 )
			  == toupper ( * tp2 ) );
		}
		++ tp1, ++ tp2;
	    }

	    if ( token_match_but_for_letter_case )
	    {
		if ( ! token_match )
		    found_difference
		        ( output.type != NUMBER_TOKEN ?
			      LETTER_CASE :
			  output.is_float ?
			      FLOAT :
			      INTEGER );
	    }

	    else if ( output.type == NUMBER_TOKEN )
	    {
	        // Tokens are not equal with letter case
		// ignored, but both are numbers.

	        assert ( test.type == NUMBER_TOKEN );
		diffnumber ();
	    }

	    else
	    {
	        // Tokens are not equal with letter case
		// ignored, and both are words.

		assert ( test.type == WORD_TOKEN );

	    	undo_split ( test );
	    	undo_split ( output );

		found_difference ( WORD );
		last_match_was_word_diff = true;
	    }

	    break;
     	}

	// The rest of the loop compares columns and
	// whitespace.  If we are skipping whitespace
	// comparisons because one file is longer than
	// the other, continue loop here.

	if ( skip_whitespace_comparison ) continue;

	assert ( output.type == test.type );

	// Compare column numbers.  This is done after
	// token comparison so that the results of word
	// splitting can be taken into account in token
	// ending column numbers.  It is not done if
	// both files have BOC_TOKENs, BOG_TOKENS, or
	// EOF_TOKENs.

	if (    output.type != EOF_TOKEN
	     && output.type != BOG_TOKEN
	     && output.type != BOC_TOKEN
	     && output.column != test.column )
	    found_difference ( COLUMN );

        // Compare whitespace preceding tokens.  This is
	// done after token comparison so that the
	// results of word splitting can be taken into
	// account in token ending column numbers.

	if ( (    output.whitespace[0] != 0
	       && test.whitespace[0]   == 0 )
	     ||
	     (    output.whitespace[0] == 0
	       && test.whitespace[0]   != 0 ) )
	    found_difference ( SPACEBREAK );
	else if ( output.newlines != test.newlines )
	    found_difference ( LINEBREAK );
	else
	{
	    char * wp1		= output.whitespace;
	    char * wp2		= test.whitespace;
	    int newlines	= 0;

	    while (1) {
		while ( * wp1 == * wp2 ) {
		    if ( * wp1 == 0 ) break;
		    if ( * wp1 == '\n' ) ++ newlines;
		    ++ wp1, ++ wp2;
		}

		if ( * wp1 == * wp2 ) break;

		// Come here if a difference in white-
		// space has been detected.  `newlines'
		// is the number of newlines scanned so
		// far.

		// Skip to just before next newline or
		// string end in each whitespace.

		while ( * wp1 && * wp1 != '\n' ) ++ wp1;
		while ( * wp2 && * wp2 != '\n' ) ++ wp2;

		assert ( output.newlines
		         == test.newlines );

		if ( newlines == output.newlines )
		{
		    bool output_is_float =
		        output.type == NUMBER_TOKEN
			&& output.is_float;
		    bool test_is_float =
		        test.type == NUMBER_TOKEN
			&& test.is_float;

		    if (    ! output_is_float
		         && ! test_is_float )
			found_difference
			    ( newlines == 0 ?
			      WHITESPACE :
			      BEGINSPACE );
		}
		else if ( newlines == 0 )
			found_difference ( ENDSPACE );
		else
		    found_difference ( LINESPACE );
	    }
	}
    }

    // The file reading loop is done because we have
    // found matched EOF_TOKENS.  Output fake eof-eof
    // difference.
    //
    assert ( output.type == EOF_TOKEN );
    assert ( test.type == EOF_TOKEN );
    found_difference
	( type_mismatch ( EOF_TOKEN, EOF_TOKEN ) );

    // Differences are now recorded in memory, ready for
    // outputting.

    // Produce first output line listing all found
    // differences, regardless of the proofs to be
    // output.  As these are output, their found flags
    // are cleared.  Differences with smaller
    // OGN:OCN-TGN:TCN markers are printed first.

    bool any = false;	// True if anything printed.
    while ( true )
    {
	// Find the lowest OGN,OCN,TGN,TCN 4-tuple among
	// all differences that that remain to be
	// printed.
	//
	int output_group;
	int output_case;
	int test_group;
	int test_case;
	bool found = false;
	for ( int i = 0; i < MAX_DIFFERENCE; ++ i )
	{
	    if ( ! differences[i].found ) continue;

	    if ( ! found )
	    {
		found = true;
		/* Drop through */
	    }
	    else if (   differences[i].output_group
		      > output_group )
		continue;
	    else if (   differences[i].output_group
		      < output_group )
		/* Drop through */;
	    else if (   differences[i].output_case
		      > output_case )
		continue;
	    else if (   differences[i].output_case
		      < output_case )
		/* Drop through */;
	    else if (   differences[i].test_group
		      > test_group )
		continue;
	    else if (   differences[i].test_group
		      < test_group )
		/* Drop through */;
	    else if (   differences[i].test_case
		      > test_case )
		continue;
	    else if (   differences[i].test_case
		      < test_case )
		/* Drop through */;
	    else continue;

	    output_group =
		differences[i].output_group;
	    output_case  =
		differences[i].output_case;
	    test_group   =
		differences[i].test_group;
	    test_case    =
		differences[i].test_case;
	}
	if ( ! found ) break;

	// Print out the OGN:OCN-TGN:TCN marker, and
	// then all the differences for this marker,
	// clearing the found flags of differences
	// printed.
	//
	if ( any ) cout << " ";
	any = true;
	cout << output_group << ":" << output_case
	     << "-"
	     << test_group << ":" << test_case;
	for ( int i = 0; i < MAX_DIFFERENCE; ++ i )
	{
	    if ( ! differences[i].found ) continue;

	    if (    differences[i].output_group
	         != output_group ) continue;
	    if (    differences[i].output_case
	         != output_case ) continue;
	    if (    differences[i].test_group
	         != test_group ) continue;
	    if (    differences[i].test_case
	         != test_case ) continue;

	    differences[i].found = false;

	    cout << " " << differences[i].name;
	    if ( i == FLOAT )
		cout << " " << float_absdiff_maximum
		     << " " << float_reldiff_maximum;
	    else if ( i == INTEGER )
		cout << " " << integer_absdiff_maximum
		     << " " << integer_reldiff_maximum;
	}
    }

    cout << endl;

    // Output proof lines.

    for ( proof_line * pline = first_proof_line;
          pline != NULL;
	  pline = pline->next )
    {
        cout << pline->output_line << " "
             << pline->test_line;

	// Output proofs within a proof line.

	int last_output_column	= -2;
	int last_test_column	= -2;

	for ( proof * p = pline->proofs;
	      p != NULL;
	      p = p->next )
	{
	    if ( last_output_column
	             != p->output_token_end_column
	         ||
		 last_test_column
	             != p->test_token_end_column )
	    {
		cout << " "
		     << p->output_token_begin_column
		     << " "
		     << p->output_token_end_column;
		cout << " "
		     << p->test_token_begin_column
		     << " "
		     << p->test_token_end_column;

		last_output_column =
		    p->output_token_end_column;
		last_test_column =
		    p->test_token_end_column;
	    }

	    cout << " " << differences[p->type].name;
	    if (    p->type == FLOAT
	         || p->type == INTEGER )
	    {
		cout << " " << p->absdiff;
		cout << " " << p->reldiff;
	    }
	}

	cout << endl;
    }

    // Return from main function without error.

    return 0;
}
