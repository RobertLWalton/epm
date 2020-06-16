// Display Text, Points, Lines, and Arcs
//
// File:	epm_display.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Tue Jun 16 09:49:51 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// Compile with:
//
//	g++ -I /usr/include/cairo \
//	    -o hpcm_display \
//	    hpcm_display.cc -lcairo

#include <iostream>
#include <iomanip>
#include <fstream>
#include <algorithm>
#include <string>
#include <sstream>

#include <cstdlib>
#include <cstdio>
#include <cstring>
#include <cctype>
#include <cfloat>
#include <cmath>
#include <cassert>
using std::cout;
using std::endl;
using std::cerr;
using std::cin;
using std::ws;
using std::istream;
using std::ostream;
using std::ifstream;
using std::min;
using std::max;
using std::string;

extern "C" {
#include <unistd.h>
#include <cairo-pdf.h>
}

bool debug = false;
# define dout if ( debug ) cerr

const char * const documentation = "\n"
"hpcm_display [-RxC] [-debug] [file]\n"
"\n"
"    This program displays line drawings defined\n"
"    in the given file or standard input.  The file\n"
"    consists of pages each consisting of command\n"
"    lines followed by a line containing just `*'.\n"
"\n"
"    The display commands are chosen from among the\n"
"    following, where + denotes one or more\n"
"    qualifiers, and lower case letters denote para-\n"
"    meters:\n"
"\n"
"        H+ text1||text2||text3\n"
"            Display text as a header line at the\n"
"            top of a page.  text1 is left adjusted;\n"
"            text2 is centered; text3 is right adjus-\n"
"            ted.  Header lines for a page must be\n"
"            the first commands for the page and\n"
"            must be adjacent to each other.\n"
"            Qualifiers:\n"
"              S = 10 point font\n"
"              M = 14 point font (default)\n"
"              L = 18 point font\n"
"\n"
"        F+ text1||text2||text3\n"
"            Display text as a footer line at the\n"
"            bottom of a page.  text1 is left\n"
"            adjusted; text2 is centered; text3 is\n"
"            right adjusted.  Footer lines for a\n"
"            page must be the last commands for the\n"
"            page and must be adjacent to each other.\n"
"            Qualifiers:\n"
"              S = 10 point font\n"
"              M = 14 point font (default)\n"
"              L = 18 point font\n"
"\n"
"        T+ x y text\n"
"            Display text with center of text at"
                                         " (x,y).\n"
"            The text is everything on the input line\n"
"            after y, but with whitespace trimmed\n"
"            from its ends.\n"
"            Qualifiers:\n"
"              S = 8 point font (default)\n"
"              M = 12 point font\n"
"              L = 16 point font\n"
"\n"
"        M t b l r\n"
"            Set the margins for top (t), bottom\n"
"            (b), left (l), and right(r).  These\n"
"            default to 0.  Max of all M commands\n"
"            is used for each margin.\n"
"\n"
"        P+ x y\n"
"            Display a point at (x,y).  Qualifiers:\n"
"              S = small (default)\n"
"              M = medium\n"
"              L = large\n"
"              G|GG|GGG = color is light,medium,dark\n"
"                         gray (default is black)\n"
"\n"
"        L+ x1 y1 x2 y2\n"
"            Display a line from (x1,y1) to (x2,y2).\n"
"            Qualifiers:\n"
"              S = small width (default)\n"
"              M = medium width\n"
"              L = large width\n"
"              D = put dot on end(s)\n"
"              F = put forward arrow head at end(s)\n"
"              R = put rearward arrow head at end(s)\n"
"              B = put last dot or arrow head only at\n"
"                  beginning of oriented line\n"
"              E = put last dot or arrow head only at\n"
"                  end of oriented line\n"
"              G|GG|GGG = color is light,medium,dark\n"
"                         gray (default is black)\n"
"\n"
"        A+ x y xa ya r g1 g2\n"
"            Display elliptical arc centered at"
                                          " (x,y).\n"
"            The ellipse is first drawn with axes\n"
"            parallel to the x- and y-axes; with x\n"
"            semi-axis xa and y semi-axis ya.  Then\n"
"            the ellipse is rotated by angle r.  The\n"
"            angles g1 and g2 bound the arc ends\n"
"            before rotation by r.  Angles are in\n"
"            degrees. If g1 < g2 the arc goes\n"
"            counter-clock-wise; if g2 < g1 the arc\n"
"            goes clockwise. Any integer multiple of\n"
"            360 added to BOTH g1 and g2 does not\n"
"            affect the result.\n"
"\n"
"            For circular arc use a = b, r = 0.\n"
"            For entire ellipse use g1 = 0, g2 = 360.\n"
"            Qualifiers:\n"
"              S = small width (default)\n"
"              M = medium width\n"
"              L = large width\n"
"              D = put dot on end(s)\n"
"              F = put forward arrow head at end(s)\n"
"              R = put rearward arrow head at end(s)\n"
"              B = put last dot or arrow head only at\n"
"                  beginning of oriented arc\n"
"              E = put last dot or arrow head only at\n"
"                  end of oriented arc\n"
"              G|GG|GGG = color is light,medium,dark\n"
"                         gray (default is black)\n"
"\n"
"    With the -pdf option, pdf is written to the\n"
"    standard output.\n"
"\n"
"    With the -RxC option, pdf is written to the\n"
"    standard output as per the -pdf option, but with\n"
"    multiple testcase pages per physical paper page.\n"
"    Here R and C are very small integers, and there\n"
"    are R rows of C columns each of test cases on\n"
"    each physical page.\n"
"\n"
"    With the -X option, an X-window is opened and\n"
"    the first test case displayed.  Typing a car-\n"
"    riage return goes to the next test case, and\n"
"    typing control-C terminates the display.\n"
"\n"
"    The -I option (interactive) is like the -X\n"
"    option but does not require typing a carriage\n"
"    return to go to the next case, but instead goes\n"
"    to the next case as soon as it can read the\n"
"    first line of input for the next case.\n"
;

// Vectors:
//
struct vector { double x, y; };

vector operator + ( vector v1, vector v2 )
{
    vector r = { v1.x + v2.x, v1.y + v2.y };
    return r;
}

vector operator - ( vector v1, vector v2 )
{
    vector r = { v1.x - v2.x, v1.y - v2.y };
    return r;
}

vector operator - ( vector v )
{
    vector r = { - v.x, - v.y };
    return r;
}

vector operator * ( double s, vector v )
{
    vector r = { s * v.x, s * v.y };
    return r;
}

double operator * ( vector v1, vector v2 )
{
    return v1.x * v2.x + v1.y * v2.y;
}

// Rotate v by angle.
//
// WARING: ^ has lower precedence than + or -.
//
vector operator ^ ( vector v, double angle )
{
    double s = sin ( M_PI * angle / 180 );
    double c = cos ( M_PI * angle / 180 );
    vector r = { c * v.x - s * v.y,
                 s * v.x + c * v.y };
    return r;
}

// Current page data.
//
struct command { command * next; char command; };
enum width { SMALL = 1, MEDIUM = 2, LARGE = 3 };
enum head { NEITHER = 0, BEGIN = 1, END = 2, BOTH = 3 };
enum color { BLACK = 0, DARK_GRAY = 1,
             MEDIUM_GRAY = 2, LIGHT_GRAY = 3 };
struct qualifiers
{
    width w;
    head dot;
    head forward;
    head rearward;
    color c;
};
struct point : public command
{
    vector p; // At p.
    qualifiers q;
};
struct line : public command
{
    vector p1, p2;  // From p1 to p2.
    qualifiers q;
};
struct arc : public command
{
    vector c;  // Center.
    vector a;  // (x axis, y axis )
    double r;
    vector g;  // (g1,g2)
    qualifiers q;
};
struct text : public command
{
    vector p; // At p.
    string t; // Text to display.
    qualifiers q;
};
struct margin : public command
{
    double t, b, l, r;
};

// List of all commands:
//
command * commands;

// Delete first command.
//
void delete_command ( void )
{
    if ( commands == NULL ) return;
    command * next = commands->next;

    switch ( commands->command )
    {
    case 'P':
	delete (point *) commands;
	break;
    case 'L':
	delete (line *) commands;
	break;
    case 'A':
	delete (arc *) commands;
	break;
    case 'T':
	delete (text *) commands;
	break;
    case 'M':
	delete (margin *) commands;
	break;
    default:
	assert ( ! "deleting bad command" );
    }
    commands = next;
}

// Print command for debugging.
//
ostream & print_command_and_qualifiers
	( ostream & s, char command,
	  const qualifiers & q )
{
    s << command
      << ( q.w == SMALL ?  "S" :
           q.w == MEDIUM ? "M" :
           q.w == LARGE ?  "L" :
                           "W?" )
      << ( q.dot == NEITHER ? "" :
           q.dot == BEGIN ?   "DB" :
           q.dot == END ?     "DE" :
           q.dot == BOTH ?    "D" :
	                      "D?" )
      << ( q.forward == NEITHER ? "" :
           q.forward == BEGIN ?   "FB" :
           q.forward == END ?     "FE" :
           q.forward == BOTH ?    "F" :
	                          "F?" )
      << ( q.rearward == NEITHER ? "" :
           q.rearward == BEGIN ?   "RB" :
           q.rearward == END ?     "RE" :
           q.rearward == BOTH ?    "R" :
	                           "R?" )
      << ( q.c == BLACK ? "" :
           q.c == DARK_GRAY ?   "GGG" :
           q.c == MEDIUM_GRAY ? "GG" :
           q.c == LIGHT_GRAY ?  "G" :
	                        "G?" );
    return s;
}
//
ostream & operator << ( ostream & s, const command & c )
{
    switch ( c.command )
    {
    case 'P':
    {
        point & P = * (point *) & c;
	return print_command_and_qualifiers
	       ( s, P.command, P.q )
	    << " " << P.p.x << " " << P.p.y;
    }
    case 'L':
    {
        line & L = * (line *) & c;
	return print_command_and_qualifiers
	       ( s, L.command, L.q )
	    << " " << L.p1.x << " " << L.p1.y
	    << " " << L.p2.x << " " << L.p2.y;
    }
    case 'A':
    {
        arc & A = * (arc *) & c;
	return print_command_and_qualifiers
	       ( s, A.command, A.q )
	    << " " << A.c.x << " " << A.c.y
	    << " " << A.a.x << " " << A.a.y
	    << " " << A.r
	    << " " << A.g.x << " " << A.g.y;
    }
    case 'T':
    {
        text & T = * (text *) & c;
	return print_command_and_qualifiers
	       ( s, T.command, T.q )
	    << " " << T.p.x << " " << T.p.y
	    << " " << T.t;
    }
    case 'M':
    {
        margin & M = * (margin *) & c;
	return s << M.command << " "
	         << M.t << " " << M.b << " "
		 << M.l << " " << M.r;
    }
    default:
        return s << "BAD COMMAND " << c.command;
    }
}

// Read page commands.  Return true if read and false if
// end of file.
//
int line_number = 0;

void read_qualifiers
    ( istream & in,
      qualifiers & q, bool heads_allowed = true )
{
    head * last_head = NULL;
    q.w = SMALL;
    q.dot = NEITHER;
    q.forward = NEITHER;
    q.rearward = NEITHER;
    q.c = BLACK;
    while ( ! isspace ( in.peek() ) )
    {
	int c = in.get();
	bool found = true;
	switch ( c )
	{
	case 'S': q.w = SMALL;
	          break;
	case 'M': q.w = MEDIUM;
	          break;
	case 'L': q.w = LARGE;
	          break;
	case 'G': q.c = (color) ( ( q.c + 3 ) % 4 );
	          break;
	default:  found = false;
	}
	if ( ! found && heads_allowed )
	{
	    found = true;
	    switch ( c )
	    {
	    case 'D': q.dot = BOTH;
		      last_head = & q.dot;
		      break;
	    case 'F': q.forward = BOTH;
		      last_head = & q.forward;
		      break;
	    case 'R': q.rearward = BOTH;
		      last_head = & q.rearward;
		      break;
	    case 'B':
	    case 'E':
		      if ( last_head == NULL )
			  cerr << "ERROR in line "
			       << line_number
			       << ": no preceeding"
			          " D, F, or R - `"
			       << (char) c
			       << "' ignored" << endl;
		      else if ( * last_head != BOTH )
			  cerr << "ERROR in line "
			       << line_number
			       << ": B and E conflict"
			          " - `"
			       << (char) c
			       << "' ignored" << endl;
		      else
			  * last_head =
			      ( c == 'B' ? BEGIN
			                 : END );
		      break;
	    default:  found = false;  
	    }
	}

	if ( ! found )
	    cerr << "ERROR in line " << line_number
		 << ": unknown qualifer `" << (char) c
		 << "' - ignored" << endl;
    }
}
void skip ( istream & in )
{
    int c;
    while ( c = in.peek(),
            isspace ( c ) && c != '\n' )
        in.get();
}
bool read_page ( istream & in )
{
    while ( commands != NULL )
        delete_command();
        
    bool done = false;
    bool started = false;
    bool past_header = false;
    while ( ! done )
    {
	int op = in.peek();

        if ( in.eof() )
	{
	    if ( ! started ) break;
	    else return false;
	}

	if ( op == 'H' && past_header )
	    break;
	if ( op != 'H' ) past_header = true;
	started = true;

	++ line_number;
	int errors = 0;
#	define ERROR(s) { \
	    cerr << "ERROR in line " << line_number \
	         << ": " << s << " - line ignored" \
		 << endl; \
	    ++ errors; \
	    }

	switch ( op )
	{
	case 'P':
	{
	    point & P = * new point();
	    P.next = commands;
	    commands = & P;
	    in >> P.command;
	    assert ( P.command == 'P' );
	    read_qualifiers ( in, P.q, false );
	    in >> P.p.x >> P.p.y;
	    break;
	}
	case 'L':
	{
	    line & L = * new line();
	    L.next = commands;
	    commands = & L;
	    in >> L.command;
	    assert ( L.command == 'L' );
	    read_qualifiers ( in, L.q );
	    in >> L.p1.x >> L.p1.y >> L.p2.x >> L.p2.y;
	    break;
	}
	case 'A':
	{
	    arc & A = * new arc();
	    A.next = commands;
	    commands = & A;
	    in >> A.command;
	    assert ( A.command == 'A' );
	    read_qualifiers ( in, A.q );
	    in >> A.c.x >> A.c.y >> A.a.x >> A.a.y
	       >> A.r >> A.g.x >> A.g.y;
	    if ( in.good() )
	    {
	        if ( A.a.x <= 0 )
		    ERROR ( "x semi-axis < 0" )
	        if ( A.a.y <= 0 )
		    ERROR ( "y semi-axis < 0" )
	    }
	    break;
	}
	case 'T':
	{
	    text & T = * new text();
	    T.next = commands;
	    commands = & T;
	    in >> T.command;
	    assert ( T.command == 'T' );
	    read_qualifiers ( in, T.q );
	    in >> T.p.x >> T.p.y;
	    while ( in.peek() != '\n' )
	        T.t.push_back ( in.get() );
	    int f = 0, l = T.t.size();
	    while ( f < l && isspace ( T.t[f] ) )
	        ++ f;
	    while ( f < l && isspace ( T.t[l-1] ) )
	        ++ l;
	    T.t = T.t.substr ( f, l - f );
	    break;
	}
	case 'M':
	{
	    margin & M = * new margin();
	    M.next = commands;
	    commands = & M;
	    in >> M.command;
	    assert ( M.command == 'M' );
	    in >> M.t >> M.b >> M.l >> M.r;
	    break;
	}
	default:
	{
	    cerr << "ERROR in line " << line_number
	         << "; cannot understand command `"
		 << (char) in.peek()
		 << "' line ignored" << endl;
	    string line;
	    getline ( in, line );
	    continue;
	}
	}
#       undef ERROR

	if ( ! in.good() )
	{
	    cerr << "ERROR in line " << line_number
		 << ": line - ignored"
		 << endl;
	    delete_command();

	    in.clear();
	    string extra;
	    getline ( in, extra );
	}
	else if ( errors > 0 )
	{
	    delete_command();

	    in.clear();
	    string extra;
	    getline ( in, extra );
	}
	else if ( skip ( in ), in.get() != '\n' )
	{
	    cerr << "ERROR in line " << line_number
		 << ": extra stuff at end of line"
		    " - ignored"
		 << endl;
	    string extra;
	    getline ( in, extra );
	    cerr << "STUFF: " << extra << endl;
	}
    }
    return true;
}

// For pdf output, units are 1/72".

// You MUST declare the entire paper size, 8.5x11",
// else you get a non-centered printout.

const double page_height = 11*72;	    // 11.0"
const double page_width = 8*72 + 72/2;	    // 8.5"

const double top_margin = 36;		    // 0.5"
const double bottom_margin = 36;	    // 0.5"
const double side_margin = 54;		    // 0.75"
const double separation = 8;		    // 8/72"
const double title_large_font_size = 16;    // 16/72"
const double title_small_font_size = 10;    // 10/72"
const double text_large_font_size = 16;     // 16/72"
const double text_medium_font_size = 12;    // 12/72"
const double text_small_font_size = 8;      // 8/72"

const double page_line_size = 1;    	    // 1/72"
const double page_dot_size = 2;    	    // 2/72"

const double print_box = page_width - 2 * side_margin;

// PDF Options
//
int R = 1, C = 1;

// Parse -LBRxC and return true on success and false
// on failure.
//
bool pdfoptions ( const char * name )
{
    long R = 1, C = 1;
    while ( * name )
    {
	if ( '0' <= * name && * name <= '9' )
	{
	    char * endp;
	    R = strtol ( name, & endp, 10 );
	    if ( endp == name ) return false;
	    if ( R < 1 || R > 30 ) return false;
	    name = endp;
	    if ( * name ++ != 'x' ) return false;
	    C = strtol ( name, & endp, 10 );
	    if ( endp == name ) return false;
	    if ( C < 1 || C > 30 ) return false;
	    name = endp;
	}
	else return false;
    }
    ::R = R;
    ::C = C;
    return true;
}

// cairo_write_func_t to write data to cout.
//
cairo_status_t write_to_cout
    ( void * closure,
      const unsigned char * data, unsigned int length )
{
    cout.write ( (const char *) data, length );
    return CAIRO_STATUS_SUCCESS;
}

// Drawing data.
//
cairo_t * title_c;
double title_font_size,
       title_left, title_top, title_height, title_width,
       graph_top, graph_height,
       graph_left, graph_width;
cairo_t * graph_c;
double xscale, yscale, left, bottom;
double dot_size, line_size;

double xmin, xmax, ymin, ymax;
    // Bounds on x and y over all commands.
    // Used to set scale.  Does NOT account
    // for width of lines or points.  Bounds
    // entire ellipse and not just the arc.

double tmax, bmax, lmax, rmax;
    // Margins for top(t), bottom(b), left(l), and
    // right(r).  Max of all margins is used.  Margins
    // are in same units as xmin, ... .

void compute_bounds ( void )
{
    xmin = ymin = DBL_MAX;
    xmax = ymax = DBL_MIN;
    tmax = bmax = lmax = rmax = 0;

#   define BOUND(v) \
	 dout << "BOUND " << (v).x << " " << (v).y \
	      << endl; \
         if ( (v).x < xmin ) xmin = (v).x; \
         if ( (v).x > xmax ) xmax = (v).x; \
         if ( (v).y < ymin ) ymin = (v).y; \
         if ( (v).y > ymax ) ymax = (v).y;

    for ( command * c = commands; c != NULL;
                                  c = c->next )
    {
        switch ( c->command )
	{
	case 'P':
	{
	    point & P = * (point *) c;
	    BOUND ( P.p );
	    break;
	}
	case 'L':
	{
	    line & L = * (line *) c;
	    BOUND ( L.p1 );
	    BOUND ( L.p2 );
	    break;
	}
	case 'A':
	{
	    // Compute bounding rectangle of ellipse,
	    // rotate it by A.r, translate it by A.c,
	    // and bound the corners.
	    //
	    arc & A = * (arc *) c;
	    vector d1 = A.a;
	    vector d2 = { d1.x, - d1.y };
	    vector ll = - d1;
	    vector lr =   d2;
	    vector ur =   d1;
	    vector ul = - d2;
	    BOUND ( A.c + ( ll^A.r ) );
	    BOUND ( A.c + ( lr^A.r ) );
	    BOUND ( A.c + ( ur^A.r ) );
	    BOUND ( A.c + ( ul^A.r ) );
	    break;
	}
	case 'T':
	{
	    // Add text center point to bounds.  We
	    // CANNOT compute bounding rectangle for
	    // text as text extent is in different units
	    // than our bounds and we do not know
	    // conversion factors at this time.
	    // 
	    text & T = * (text *) c;
	    BOUND ( T.p );
	    break;
	}
	case 'M':
	{
	    // Max margins.
	    // 
	    margin & M = * (margin *) c;
	    if ( M.t > tmax ) tmax = M.t;
	    if ( M.b > bmax ) bmax = M.b;
	    if ( M.l > lmax ) lmax = M.l;
	    if ( M.r > rmax ) rmax = M.r;
	    break;
	}
	default:
	    assert ( ! "bounding bad command" );
	}
    }
#   undef BOUND

    xmin -= lmax; xmax += rmax;
    ymin -= bmax; ymax += tmax;

    dout <<  "XMIN " << xmin << " XMAX " << xmax
         << " YMIN " << ymin << " YMAX " << ymax
         << endl; 
}

# define CONVERT(p) \
    left + ((p).x - xmin) * xscale, \
    bottom - ((p).y - ymin) * yscale

// Set color of graph_c.
//
void set_color ( color c )
{
    double rgb = 0.25 * c;
    cairo_set_source_rgb ( graph_c, rgb, rgb, rgb );
}

// Draw dot at position p with width w.
//
void draw_dot ( vector p, width w )
{
    cairo_arc
        ( graph_c, CONVERT(p), w * dot_size, 0, 2*M_PI);
    cairo_fill ( graph_c );
}

// Draw dot at position p with direction d and width w.
//
void draw_arrow ( vector p, vector d, width w )
{
    d = ( 1 / sqrt ( d * d ) ) * d;
    d = ( min ( xmax - xmin, ymax - ymin ) / 25 ) * d;
    vector d1 = d^20;
    vector d2 = d^(-20);
    cairo_move_to ( graph_c, CONVERT(p-d1) );
    cairo_line_to ( graph_c, CONVERT(p) );
    cairo_line_to ( graph_c, CONVERT(p-d2) );
    cairo_set_line_width ( graph_c, w * line_size );
    cairo_stroke ( graph_c );
}

void draw_text ( vector p, width w, string t )
{
    vector pc = { CONVERT(p) };
    double font_size =
        ( w == SMALL ?  text_small_font_size :
          w == MEDIUM ? text_medium_font_size :
          w == LARGE ?  text_large_font_size :
                        0 );
    cairo_set_font_size ( graph_c, font_size );
    assert (    cairo_status ( graph_c )
	     == CAIRO_STATUS_SUCCESS );

    cairo_text_extents_t te;
    cairo_text_extents ( graph_c, t.c_str(), & te );
    assert (    cairo_status ( graph_c )
	     == CAIRO_STATUS_SUCCESS );
    cairo_move_to
	( graph_c, 
	  pc.x - te.width/2, pc.y + te.height/2 );
    cairo_show_text ( graph_c, t.c_str() );
    assert (    cairo_status ( graph_c )
	     == CAIRO_STATUS_SUCCESS );

}

void draw_page ( void )
{
    // Set up point scaling.  Insist on a margin
    // of 4 * line_size to allow lines to be
    // inside graph box.
    //
    double dx = xmax - xmin;
    double dy = ymax - ymin;
    if ( dx == 0 ) dx = 1;
    if ( dy == 0 ) dy = 1;
    xscale =
	( graph_width - 4 * line_size ) / dx;
    yscale =
	( graph_height - 4 * line_size ) / dy;

    // Make the scales the same.
    //
    if ( xscale > yscale )
	xscale = yscale;
    else if ( xscale < yscale )
	yscale = xscale;

    // Compute left and bottom of graph so as
    // to center graph.
    //
    left = graph_left
	+ 0.5 * (   graph_width
		  - ( xmax - xmin ) * xscale );
    bottom = graph_top + graph_height
	- 0.5 * (   graph_height
		  - ( ymax - ymin ) * yscale );

    dout << "LEFT " << left
	 << " XSCALE " << xscale
	 << " BOTTOM " << bottom
	 << " YSCALE " << yscale
	 << endl;

    // Execute drawing commands.
    //
    for ( command * c = commands; c != NULL;
				  c = c->next )
    {
	dout << "EXECUTE " << * c << endl;
	switch ( c->command )
	{
	case 'P':
	{
	    point & P = * (point *) c;
	    set_color ( P.q.c );
	    draw_dot ( P.p, P.q.w );
	    break;
	}
	case 'L':
	{
	    line & L = * (line *) c;
	    set_color ( L.q.c );
	    cairo_move_to
		( graph_c,
		  CONVERT(L.p1) );
	    cairo_line_to
		( graph_c,
		  CONVERT(L.p2) );
	    cairo_set_line_width
		( graph_c,
		  L.q.w * line_size );
	    cairo_stroke ( graph_c );

	    if ( L.q.dot & BEGIN )
		draw_dot ( L.p1, L.q.w );
	    if ( L.q.dot & END )
		draw_dot ( L.p2, L.q.w );
	    if ( L.q.forward & BEGIN )
		draw_arrow
		    ( L.p1, L.p2 - L.p1,
		      L.q.w );
	    if ( L.q.forward & END )
		draw_arrow
		    ( L.p2, L.p2 - L.p1,
		      L.q.w );
	    if ( L.q.rearward & BEGIN )
		draw_arrow
		    ( L.p1, L.p1 - L.p2,
		      L.q.w );
	    if ( L.q.rearward & END )
		draw_arrow
		    ( L.p2, L.p1 - L.p2,
		      L.q.w );
	    break;
	}
	case 'A':
	{
	    arc & A = * (arc *) c;
	    set_color ( A.q.c );

	    double g1 = M_PI * A.g.x / 180;
	    double g2 = M_PI * A.g.y / 180;
	    double r  = M_PI * A.r   / 180;

	    cairo_matrix_t saved_matrix;
	    cairo_get_matrix
		( graph_c, & saved_matrix );
	    cairo_translate
		( graph_c, CONVERT(A.c) );
	    cairo_rotate
		( graph_c, - r );
	    cairo_scale
		( graph_c,
		  A.a.x * xscale,
		  - A.a.y * yscale );

	    cairo_new_path ( graph_c );
	    cairo_arc
		( graph_c,
		  0, 0, 1,
		  min ( g1, g2 ),
		  max ( g1, g2 ) );

	    cairo_set_matrix
		( graph_c, & saved_matrix );
	    cairo_set_line_width
		( graph_c,
		  A.q.w * line_size );
	    cairo_stroke ( graph_c );

	    double s1 = sin ( g1 );
	    double c1 = cos ( g1 );
	    double s2 = sin ( g2 );
	    double c2 = cos ( g2 );
	    vector p1 =
		{ c1 * A.a.x,
		  s1 * A.a.y };
	    vector d1 =
		{ - s1 * A.a.x,
		    c1 * A.a.y };
	    vector p2 =
		{ c2 * A.a.x,
		  s2 * A.a.y };
	    vector d2 =
		{ - s2 * A.a.x,
		    c2 * A.a.y };
	    p1 = A.c + ( p1 ^ A.r );
	    p2 = A.c + ( p2 ^ A.r );
	    d1 = d1 ^ A.r;
	    d2 = d2 ^ A.r;

	    if ( A.q.dot & BEGIN )
		draw_dot ( p1, A.q.w );
	    if ( A.q.dot & END )
		draw_dot ( p2, A.q.w );
	    if ( A.q.forward & BEGIN )
		draw_arrow
		    ( p1, d1, A.q.w );
	    if ( A.q.forward & END )
		draw_arrow
		    ( p2, d2, A.q.w );
	    if ( A.q.rearward & BEGIN )
		draw_arrow
		    ( p1, - d1, A.q.w );
	    if ( A.q.rearward & END )
		draw_arrow
		    ( p2, - d2, A.q.w );
	    break;
	}
	case 'T':
	{
	    text & T = * (text *) c;
	    draw_text ( T.p, T.q.w, T.t );
	    break;
	}
	case 'M':
	    break;
	}
    }
}

void print_documentation ( int exit_code )
{
    FILE * out = popen ( "less -F", "w" );
    fputs ( documentation, out );
    pclose ( out );
    exit ( exit_code );
}

// Main program.
//
int main ( int argc, char ** argv )
{
    cairo_surface_t * page = NULL;
    bool interactive = false;

    // Process options.

    bool RxC_found = false;
    while ( argc >= 2 && argv[1][0] == '-' )
    {

	char * name = argv[1] + 1;

        if ( strncmp ( "deb", name, 3 ) == 0 )
	    debug = true;
        else if ( strncmp ( "doc", name, 3 ) == 0 )
	{
	    // Any -doc* option prints documentation
	    // and exits.
	    //
	    print_documentation ( 0 );
	}
        else if ( pdfoptions ( name ) )
	{
	    if ( RxC_found )
	    {
		cout << "At most one -RxC allowed"
		     << endl;
		exit (1);
	    }
	    RxC_found = true;
	}
	else
	{
	    cout << "Cannot understand -" << name
	         << endl << endl;
	    exit (1);
	}

	++ argv, -- argc;
    }

    page = cairo_pdf_surface_create_for_stream
		( write_to_cout, NULL,
		  page_width, page_height );

    // Exit with error status unless there is exactly
    // one program argument left.

    if ( argc > 2 )
    {
	cout << "Wrong number of arguments."
	     << endl;
	exit (1);
    }

    // Open file.
    //
    ifstream in;
    const char * file = NULL;
    if ( argc == 2 )
    {
        file = argv[1];
	in.open ( file );
	if ( ! in )
	{
	    cout << "Cannot open " << file << endl;
	    exit ( 1 );
	}
    }

    // 4 is the max number of allowed cairo contexts.
    // This seems to be undocumented?
    //
    title_c = cairo_create ( page );
    cairo_set_source_rgb ( title_c, 0.0, 0.0, 0.0 );
    cairo_select_font_face ( title_c, "sans-serif",
                             CAIRO_FONT_SLANT_NORMAL,
			     CAIRO_FONT_WEIGHT_BOLD );
    title_font_size =
        ( C == 1 ? title_large_font_size :
	           title_small_font_size );
    cairo_set_font_size ( title_c, title_font_size );
    assert (    cairo_status ( title_c )
	     == CAIRO_STATUS_SUCCESS );

    graph_c = cairo_create ( page );
    cairo_set_source_rgb ( graph_c, 0.0, 0.0, 0.0 );
    cairo_select_font_face ( graph_c, "sans-serif",
                             CAIRO_FONT_SLANT_NORMAL,
			     CAIRO_FONT_WEIGHT_BOLD );
    assert (    cairo_status ( graph_c )
	     == CAIRO_STATUS_SUCCESS );

    double case_width = page_width
		      - 2 * side_margin
		      - ( C - 1 ) * separation;
    case_width /= C;
    double case_height =
	page_height - top_margin
		    - bottom_margin
		    - ( R - 1 ) * separation; 
    case_height /= R;

    title_width = case_width;
    title_height = 2 * title_font_size;

    graph_height = case_height
		 - 2 * title_font_size;
    graph_width = case_width;

    line_size = page_line_size;
    dot_size = page_dot_size;

    int curR = 1, curC = 1;
    while ( read_page
		( file != NULL ?
		  * (istream *) & in :
		  cin ) )
    {
	title_top = top_margin
		  + case_height * ( curR - 1 )
		  + separation * ( curR - 1 );
	title_left = side_margin
		   + case_width * ( curC - 1 )
		   + separation * ( curC - 1 );

	graph_top = top_margin
		  + case_height * ( curR - 1 )
		  + separation * ( curR - 1 )
		  + 2 * title_font_size;
	graph_left = side_margin
		   + case_width * ( curC - 1 )
		   + separation * ( curC - 1 );

	compute_bounds();
	draw_page();
	if ( ++ curC > C )
	{
	    curC = 1;
	    if ( ++ curR > R )
	    {
		curR = 1;
		cairo_show_page ( title_c );
	    }
	}
    }
    if ( curR != 1 || curC != 1 )
	cairo_show_page ( title_c );

    cairo_destroy ( title_c );
    cairo_destroy ( graph_c );
    cairo_surface_destroy ( page );

    // Return from main function without error.

    return 0;
}
