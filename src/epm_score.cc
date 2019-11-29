// Educational Programming Contest Scoring Program
//
// File:	epm_score.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Fri Nov 29 17:36:31 EST 2019
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// This file is derived from scorediff.cc of the HPCM
// system written by the same author.

#include <cstdlib>
#include <climits>
#include <iostream>
#include <iomanip>
#include <string>
#include <fstream>
#include <cctype>
#include <cstring>
#include <cmath>
#include <cassert>
using std::cout;
using std::cerr;
using std::endl;
using std::string;
using std::ifstream;

// Name defined in include file that is used below and
// needs to be changed in its usage below.
//
#ifdef INFINITY
#    undef INFINITY
#    endif
#define INFINITY Infinity

// Maximum size of a token, or of whitespace preceding
// a token.
//
unsigned const MAX_SIZE = 1100000;

// Default maximum number of proof lines containing any
// one type of difference.
//
unsigned const PROOF_LIMIT = 10;

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
"        All tokens must be identical as character\n"
"        strings and have identical ending columns,\n"
"        else the score is Incorrect Output.\n"
"\n"
"    -exact-no-digit\n"
"        Ditto but apply only if the test file line\n"
"        contains no digits.\n"
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
bool blank_opt = false;
bool column_opt = false;
bool decimal_opt = false;
bool case_opt = false;
bool number_opt = false;
double number_A, number_R;
bool float_opt = false;
bool exact_opt = false;
bool exact_no_numbers_opt = false;

enum token_type {
    NO_TOKEN = 0, WORD_TOKEN, SEPARATOR_TOKEN, INTEGER_TOKEN,
    FLOAT_TOKEN, EOF_TOKEN };

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
    const char * id;	// Short name of file for
    			// debugging: "out" or "test".

    string line;	// Current line.
    int line_number;
    bool is_blank;	// True if line is blank.
    bool has_digit;	// True if line has digit.
    			// Only computed if +exact-no-
			// numbers option given.

    // Token description.
    //
    token_type type;	// Type of token.
    int start, end;	// Token is line[start,end-1].
    int column;		// Column within the line of
    			// the last character of the
			// the current token.  The first
			// column is 0.
};

// The two files.
//
file output;
file test;

// Open file for reading.
//
void open ( file & f, char * filename, const char * id )
{
    f.filename = new char [strlen ( filename ) + 1 ];
    strcpy ( f.filename, filename );
    f.id = id;

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
// EOF_TOKEN, does nothing.
//
void get_line ( file & f )
{
    if ( f.type == EOF_TOKEN ) return;
    if ( ! getline ( f.stream, f.line ) )
         f.type = EOF_TOKEN;
    else
    {
        const char * p = f.line.c_str();
	f.is_blank = true;
	while ( f.is_blank && * p )
	    f.is_blank = isspace(*p++);
	if ( exact_no_numbers_opt )
	{
	    -- p;
	    f.has_digit = false;
	    while ( ! f.has_digit && * p )
	        f.has_digit = isdigit(*p++);
	}
    }
}

// Routines to announce errors and exit program.
//
void whitespace_too_long ( file & f ) {
    cerr << "Whitespace too long in line "
         << f.line
	 << " of "
         << f.filename
	 << endl;
    exit (1);
}
void token_too_long ( file & f ) {
    cerr << "Token too long in line "
         << f.line
	 << " of "
         << f.filename
	 << endl;
    exit (1);
}
void bad_filtered_mark ( file & f ) {
    cerr << "Bad filtered file line mark"
            " beginning line "
         << f.line
	 << " of "
         << f.filename
	 << endl;
    exit (1);
}

// Produce debug output for a scaned token.
//
void debug_output ( file & f )
{
    cout << f.id
         << " " << f.line
         << " " << f.column - f.length + 1
         << " " << f.column
	 << " " << f.group_number
	 << ":" << f.case_number
	 << " " << strlen ( f.whitespace )
	 << "/" << f.newlines
	 << " " << token_type_name[f.type]
	 << " ";
    char * endp = f.token + f.length;
    for ( char * p = f.token; p < endp; ++ p )
    {
        if ( 040 <= * p && * p < 0177 )
	    cout << * p;
	else if ( * p < 040 )
	    cout << "^" << * p + '@';
	else if ( * p >= 0177 )
	{
	    int d0 = ( * p >> 4 ) & 0xF;
	    int d1 = * p & 0xF;
	    char c0 = d0 < 10 ? '0' + d0
	                      : 'A' - 10 + d0;
	    char c1 = d1 < 10 ? '0' + d1
	                      : 'A' - 10 + d1;
	    cout << "\\x" << c0 << c1;
	}
    }
    cout << endl;
}

// Scan next token in a file.  EOF_TOKEN is returned
// repeatedly at end of file.  -debug option output is
// produced.
//
void zero_proof_lines ( void );
void scan_token ( file & f )
{
    if ( f.type == EOF_TOKEN ) return;
    if ( f.boc_next )
    {
	// Current token is part of BOG/BOC pair.
	//
	assert ( f.type == BOG_TOKEN );
        f.boc_next	= false;
	++ f.case_number;
	f.type		= BOC_TOKEN;
	f.whitespace[0]	= 0;
	f.newlines	= 0;
	if ( debug ) debug_output ( f );
	return;
    }

    f.before_nl		= false;
    f.not_before_nl	= false;

    if ( f.remainder_length != 0 )
    {
        assert ( f.type == WORD_TOKEN );

	char * p = f.token + f.length;
	char * q = f.token;
	char * endq = q + f.remainder_length;
	while ( q < endq ) * q ++ = * p ++;
	f.length		= f.remainder_length;

	f.column		+= f.remainder_length;
	f.remainder_length	= 0;

	f.whitespace[0]		= 0;
	f.newlines		= 0;
	f.remainder		= true;

	if ( debug ) debug_output ( f );
	return;
    }

    f.remainder = false;

    int c = get_character ( f );
    int column = f.column + 1;
    
    // Scan whitespace.
    //
    char * wp = f.whitespace;
    char * endwp = wp + MAX_SIZE;

    f.newlines = 0;

    // Set in case BOG/BOC/EOF_TOKEN found.
    //
    f.length	= 0;

    while ( isspace ( c ) ) {

	if ( wp >= endwp ) whitespace_too_long ( f );
	* wp ++ = c;

        if ( c == '\n' ) {
	    column = -1;
	    ++ f.newlines;
	    ++ f.line;
	    if ( filtered ) {
	        switch ( get_character ( f ) ) {
		case '+':	++ f.group_number;
				f.case_number = 0;
				f.type = BOG_TOKEN;
				zero_proof_lines();
				f.column = column;
				f.boc_next = true;
				* wp = 0;
				if ( debug )
				    debug_output ( f );
				return;
		case '-':	++ f.case_number;
				f.type = BOC_TOKEN;
				f.column = column;
				* wp = 0;
				if ( debug )
				    debug_output ( f );
				return;
		case '|':	++ f.group_number;
				f.case_number = 0;
				f.type = BOG_TOKEN;
				zero_proof_lines();
				f.column = column;
				* wp = 0;
				if ( debug )
				    debug_output ( f );
				return;
		case '.':	break;
		case EOF:	break;
		default:	bad_filtered_mark ( f );
		}
	    }
	}
	else if ( c == '\t' )
	    column += 7 - ( column % 8 );
	else if ( c == '\f' ) -- column;
	else if ( c == '\v' ) -- column;
	else if ( c == '\r' ) column = -1;
	else if ( c == '\b' && column >= 1 )
	    column -= 2;

	// Note: terminals, unlike printers, generally
	// do not treat \f as going back to the first
	// column, so we do not here.

	c = get_character ( f );
	++ column;
    }

    // Come here then c is the first character of the
    // token.

    * wp = 0;

    if ( c == EOF ) {
    	f.type = EOF_TOKEN;
	f.column = -- column;
	if ( debug ) debug_output ( f );
	return;
    }

    // Come here when c is the first character of a
    // word or number token.

    char * tp = f.token;
    char * endtp = tp + MAX_SIZE;
    int decimals = -1;

    if ( nonumber ) goto word;

    switch ( c ) {

    case '+':
    case '-':
	if ( nosign ) goto word;

	* tp ++ = c;

	c = get_character ( f );
	++ column;

	// f.token now holds a sign.

	switch ( c ) {
	case '.':
	    ++ decimals;

	    * tp ++ = c;

	    c = get_character ( f );
	    ++ column;

	    // f.token now holds a sign followed by a
	    // decimal point.

	    if ( ! isdigit ( c ) ) goto word;
	    break;

	default:
	    // Here f.token holds just a sign.

	    if ( ! isdigit ( c ) ) goto word;
	}
	break;

    case '.':
	* tp ++ = c;
	c = get_character ( f );
	++ column;
	++ decimals;

	// f.token now holds just a decimal point.

	if ( ! isdigit ( c ) ) goto word;
	break;

    default:
	// Here f.token is empty.

        if ( ! isdigit ( c ) ) goto word;
	break;
    }

    // Come here when c is the first digit of a number.

    * tp ++ = c;
    c = get_character ( f );
    ++ column;

    // Get rest of mantissa.
    //
    while ( true ) {
        if ( isdigit ( c ) ) {
	    if ( decimals >= 0 ) ++ decimals;
	} else if ( c == '.' ) {
	    if ( decimals < 0 ) ++ decimals;
	    else break;
	} else break;

	if ( tp < endtp ) * tp ++ = c;
	else token_too_long ( f );
	c = get_character ( f );
	++ column;
    }

    // Get exponent if present.
    //
    f.has_exponent = false;

    if ( c == 'e' || c == 'E' ) {

	// Save tp and column in case we want to back
	// up to this point.

        char * ep = tp;
	int ecolumn = column;

	if ( tp < endtp ) * tp ++ = c;
	else token_too_long ( f );

	c = get_character ( f );
	++ column;

	// f.token now holds a mantissa followed by an
	// `e' or `E'.

	if ( c == '+' || c == '-' ) {
	    if ( tp < endtp ) * tp ++ = c;
	    else token_too_long ( f );

	    c = get_character ( f );
	    ++ column;
	}

	// f.token now holds a mantissa followed by an
	// `e' or `E' and possibly then followed by
	// a sign.

	if ( ! isdigit ( c ) ) {
	    // No digit next: backup.

	    assert ( f.backp == f.endbackp );
	    int len = tp - ep;
	    memcpy ( f.backup, ep, len );
	    f.backp = f.backup;
	    f.endbackp = f.backup + len;
	    tp = ep;
	    column = ecolumn;
	} else {
	    // Exponent first digit next: scan rest of
	    // exponent.

	    do {
		if ( tp < endtp ) * tp ++ = c;
		else token_too_long ( f );
		c = get_character ( f );
		++ column;
	    } while ( isdigit ( c ) );
	    f.has_exponent = true;
	}
    }

    // End of number token.  c is first character beyond
    // number token, and if the backup string is not
    // empty, it was set just above and c should be
    // APPENDED to it.

    f.type	= NUMBER_TOKEN;
    f.length	= tp - f.token;
    f.column	= -- column;
    f.decimals	= decimals;
    f.is_float	= ( f.decimals >= 0 ) || f.has_exponent;

    // Put c into backup.

    if ( c != EOF ) {
	if ( f.backp == f.endbackp )
	    f.backp = f.endbackp = f.backup;
	* f.endbackp ++ = c;
    }

    // Convert number token to floating point using
    // strtod.

    char * e;
    f.token[f.length] = 0;
    f.number = strtod ( f.token, & e );
    assert ( e == tp || ! isfinite ( f.number ) );
    	//
    	// If number is too large then f.number is
	// set to an infinity and e is not set to
	// the end of the number; which is probably
	// a bug in strtod.

    if ( debug ) debug_output ( f );
    return;

// Come here if we have concluded that the characters
// scanned into f.token so far are part of a word,
// and c is the next character of the word or is a
// whitespace character or is the beginning of a number
// token or is an EOF.  In the cases where c is not the
// next character of the word, f.token is not empty at
// this point.
//
word:

    while ( true ) {
        if (    isspace ( c )
	     || ( isdigit ( c ) && ! nonumber )
	     || c == EOF ) break;

	switch ( c ) {

	case '+':
	case '-':
	    if ( nosign ) break;
	case '.':
	    if ( nonumber ) break;

	    // Possible first character of number.

	    // Save tp and column in case we want to
	    // backup to this point.

	    char * np = tp;
	    int ncolumn = column;
	    int oldc = c;

	    if ( tp < endtp ) * tp ++ = c;
	    else token_too_long ( f );

	    c = get_character ( f );
	    ++ column;

	    // f.token now holds a word followed by a
	    // sign or decimal point.

	    if ( c == '.' && oldc != '.' ) {
		if ( tp < endtp ) * tp ++ = c;
		else token_too_long ( f );

		c = get_character ( f );
		++ column;
	    }

	    // f.token now holds a word followed by a
	    // sign and then possibly a decimal point,
	    // or followed by just a decimal point.

	    if ( isdigit ( c ) ) {
	        // Found digit and hence number: backup.

		assert ( f.backp == f.endbackp );
		int len = tp - np;
		memcpy ( f.backup, np, len );
		f.backp = f.backup;
		f.endbackp = f.backup + len;
		tp = np;
		column = ncolumn;
		goto end_word;
	    } else continue;
	        // No digit; we are still in word.  Go
		// check c for possible number beginning
		// character (it might be `.' or sign).
	}

	if ( tp < endtp ) * tp ++ = c;
	else token_too_long ( f );

	c = get_character ( f );
	++ column;
    }

end_word:

    // End of word.  c is first character beyond the
    // word, and if the backup string is not empty,
    // it was set just above and c should be APPENDED
    // to it.

    f.type	= WORD_TOKEN;
    f.length	= tp - f.token;
    f.column	= -- column;

    assert ( f.length  > 0 );

    // Put c into backup.

    if ( c != EOF ) {
	if ( f.backp == f.endbackp )
	    f.backp = f.endbackp = f.backup;
	* f.endbackp ++ = c;
    }

    if ( debug ) debug_output ( f );
    return;
}

// Split word token so first part has n characters.
//
void split_word ( file & f, int n )
{
    assert ( f.type == WORD_TOKEN );
    assert ( n < f.length );

    f.remainder_length = f.length - n;
    f.length = n;
    f.column -= f.remainder_length;

    f.before_nl		= false;
    f.not_before_nl	= true;
}

// Undo a token split.  Does nothing if file
// token is not split.
//
void undo_split ( file & f )
{
    if ( f.remainder_length != 0 )
    {
	f.length += f.remainder_length;
	f.column += f.remainder_length;
	f.remainder_length = 0;
	f.before_nl	= false;
	f.not_before_nl	= false;
    }
}

// Sets f.before_nl and f.not_before_nl according to
// what comes next in the input stream.  Sets backup to
// some portion of the whitespace that comes next, plus
// possibly a following non-whitespace character.
//
// If f.before_nl or f.not_before_nl is already set,
// nothing needs to be done.
//
// If f.type == EOF_TOKEN assumes EOF is next thing
// in input string.
//
// Returns f.before_nl.
//
bool before_nl ( file & f )
{
    if ( f.before_nl || f.not_before_nl )
        return f.before_nl;

    if ( f.type == EOF_TOKEN ) 
    {
        f.before_nl = true;
	return true;
    }

    // Scan characters to answer question.  Start by
    // scanning characters in backup, and then add to
    // backup until we scan the first non-whitespace
    // character or '\n' or EOF.  Set before_nl if we
    // find an `\n' or EOF, and not_before_nl other-
    // wise.
    //
    char * p = f.backp;
    int c;
    while ( true )
    {
	// Get next character.
	//
	if ( p < f.endbackp ) c = * p ++;
        else if ( p >= f.backup + sizeof ( f.backup ) )
	    whitespace_too_long ( f );
	else
	{
	    c = f.stream.get();

	    if ( c == EOF ) {
		f.before_nl = true;
		return true;
	    }

	    * f.endbackp ++ = c;
	    p = f.endbackp;
	}

    	if ( ! isspace ( c ) )
	{
	    f.not_before_nl = true;
	    return false;
	}
	else if ( c == '\n' )
	{
	    f.before_nl = true;
	    return true;
        }
    }
}

// Possible difference types.  The first group
// have indices computed by the function that
// followed the enum definition.
//
enum difference_type {
    LINEBREAK = MAX_TOKEN * MAX_TOKEN,
    SPACEBREAK,
    WHITESPACE,
    BEGINSPACE,
    LINESPACE,
    ENDSPACE,
    FLOAT,
    INTEGER,
    DECIMAL,
    EXPONENT,
    SIGN,
    INFINITY,
    LETTER_CASE,
    COLUMN,
    WORD,
    MAX_DIFFERENCE
};
//
inline difference_type type_mismatch 
	( token_type TYPE1, token_type TYPE2 )
{
    return difference_type
    		( TYPE1 * MAX_TOKEN + TYPE2 );
}

// Difference data.
//
struct difference
    // Information about one type of difference.
{
    const char * name;
    	// Name of difference type.

    bool	found;
        // True if difference of this type has been
	// found.

    unsigned	output_group;
    unsigned	output_case;
    unsigned	test_group;
    unsigned	test_case;
	// Number to print the marker:
	//
	//    OGN:OCN-TGN:TCN

    int		last_output_line;
    int		last_test_line;
       // Line numbers of last proof output that
       // contains this type of difference.  Zero
       // if no proof containing this difference
       // has been output.

    unsigned	proof_lines;
       // Number of proof lines containing a proof of
       // this difference type.  Incremented conceptual-
       // ly at the end of a line in either file if a
       // proof for a difference of this type has been
       // output for that line.  In actual practice, the
       // incrementing is not done till the next differ-
       // ence of this type is found.

    unsigned	proof_limit;
       // If not greater than proof_lines, suppresses
       // further output of proofs of this difference
       // type.
};

// Information on the various differences found.
//
#define DIFFERENCE_FILLER \
        false,0,0,0,0,0,0,0,PROOF_LIMIT
difference differences[] = {
    { NULL,		DIFFERENCE_FILLER },
    { "word-number",	DIFFERENCE_FILLER },
    { "word-boc",	DIFFERENCE_FILLER },
    { "word-bog",	DIFFERENCE_FILLER },
    { "word-eof",	DIFFERENCE_FILLER },
    { "number-word",	DIFFERENCE_FILLER },
    { NULL,		DIFFERENCE_FILLER },
    { "number-boc",	DIFFERENCE_FILLER },
    { "number-bog",	DIFFERENCE_FILLER },
    { "number-eof",	DIFFERENCE_FILLER },
    { "boc-word",	DIFFERENCE_FILLER },
    { "boc-number",	DIFFERENCE_FILLER },
    { NULL,		DIFFERENCE_FILLER },
    { "boc-bog",	DIFFERENCE_FILLER },
    { "boc-eof",	DIFFERENCE_FILLER },
    { "bog-word",	DIFFERENCE_FILLER },
    { "bog-number",	DIFFERENCE_FILLER },
    { "bog-boc",	DIFFERENCE_FILLER },
    { NULL,		DIFFERENCE_FILLER },
    { "bog-eof",	DIFFERENCE_FILLER },
    { "eof-word",	DIFFERENCE_FILLER },
    { "eof-number",	DIFFERENCE_FILLER },
    { "eof-boc",	DIFFERENCE_FILLER },
    { "eof-bog",	DIFFERENCE_FILLER },
    { "eof-eof",	DIFFERENCE_FILLER },
    { "linebreak",	DIFFERENCE_FILLER },
    { "spacebreak",	DIFFERENCE_FILLER },
    { "whitespace",	DIFFERENCE_FILLER },
    { "beginspace",	DIFFERENCE_FILLER },
    { "linespace",	DIFFERENCE_FILLER },
    { "endspace",	DIFFERENCE_FILLER },
    { "float",		DIFFERENCE_FILLER },
    { "integer",	DIFFERENCE_FILLER },
    { "decimal",	DIFFERENCE_FILLER },
    { "exponent",	DIFFERENCE_FILLER },
    { "sign",		DIFFERENCE_FILLER },
    { "infinity",	DIFFERENCE_FILLER },
    { "letter-case",	DIFFERENCE_FILLER },
    { "column",		DIFFERENCE_FILLER },
    { "word",		DIFFERENCE_FILLER }
};
#undef DIFFERENCE_FILLER

// Function to zero difference proof_lines counts.
//
void zero_proof_lines ( void )
{
    int i; for ( i = 0; i < MAX_DIFFERENCE; ++ i )
	differences[i].proof_lines == 0;
}

// Maximum numeric differences found so far.
//
double float_absdiff_maximum	= 0.0;
double float_reldiff_maximum	= 0.0;
double integer_absdiff_maximum	= 0.0;
double integer_reldiff_maximum	= 0.0;

// Numeric differences less than or equal to these are
// NOT output as proofs.
//
double float_absdiff_limit	= -1.0;
double float_reldiff_limit	= -1.0;
double integer_absdiff_limit	= -1.0;
double integer_reldiff_limit	= -1.0;

struct proof
    // A description of one single proof to be output.
{
    difference_type	type;
        // Difference type.

    int			output_token_begin_column;
    int			output_token_end_column;
    int			test_token_begin_column;
    int			test_token_end_column;
        // Column numbers.

    double		absdiff;
    double		reldiff;
    	// Numeric differences for numeric difference
	// types.

    proof *		next;
        // Next proof in list of proofs on one proof
	// line.
};

struct proof_line
    // A description of one single line of proofs that
    // is to be output.
{
    int			output_line;
    int			test_line;
        // Line numbers.

    proof *		proofs;
        // First proof on this line.

    proof_line *	next;
        // Next proof line to be output.
};

proof_line *	first_proof_line	= NULL;
proof_line *	last_proof_line		= NULL;
    // First and last proof lines being output.

proof *		last_proof		= NULL;
    // Last proof being output on last proof
    // line being output.

// Output a new proof.  Use current line and
// column numbers.
//
inline void output_proof
	( difference_type type,
	  double absdiff = 0.0,
	  double reldiff = 0.0 )
{

    if ( last_proof_line == NULL
	 ||
         last_proof_line->output_line
	 != output.line
	 ||
	 last_proof_line->test_line
	 != test.line )
    {
        proof_line * pline	= new proof_line;

	pline->output_line	= output.line;
	pline->test_line	= test.line;
	pline->proofs		= NULL;
	pline->next		= NULL;

	last_proof		= NULL;

	if ( last_proof_line == NULL )
	    first_proof_line		= pline;
	else
	    last_proof_line->next	= pline;

	last_proof_line		= pline;
    }

    proof * p	= new proof;

    p->type			= type;
    p->output_token_begin_column
    				= output.column -
				  output.length + 1;
    p->output_token_end_column	= output.column;
    p->test_token_begin_column	= test.column -
				  test.length + 1;
    p->test_token_end_column	= test.column;
    p->absdiff			= absdiff;
    p->reldiff			= reldiff;
    p->next			= NULL;

    if ( last_proof == NULL )
	last_proof_line->proofs	= p;
    else
        last_proof->next	= p;

    last_proof		= p;

    difference & d = differences[type];

    d.last_output_line = output.line;
    d.last_test_line   = test.line;
}

// Record a found difference.
//
inline void found_difference
	( difference_type type,
	  double absdiff = 0.0,
	  double reldiff = 0.0 )
{
    if (    type == FLOAT
	 && ( absdiff <= float_absdiff_limit
	      ||
	      reldiff <= float_reldiff_limit ) )
        return;
    if (    type == INTEGER
	 && ( absdiff <= integer_absdiff_limit
	      ||
	      reldiff <= integer_reldiff_limit ) )
        return;

    difference & d = differences[type];

    if ( ! d.found )
    {
        d.output_group = output.group_number;
        d.output_case  = output.case_number;
        d.test_group   = test.group_number;
        d.test_case    = test.case_number;
    }
    d.found = true;

    // Conceptually, d.proof_lines is incremented
    // at the end of a proof line containing an
    // output of a proof of the given `type'.  But
    // in practice, to reduce coding complexity,
    // the incrementing is deferred until the next
    // proof of this type is discovered, and then
    // the incrementing is done here.
    //
    if ( d.last_output_line != 0
	 && ( d.last_output_line != output.line
	     ||
	     d.last_test_line != test.line ) )
	++ d.proof_lines;

    if ( d.proof_lines < d.proof_limit )
	output_proof ( type, absdiff, reldiff );
}

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
