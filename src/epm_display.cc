// Display Text, Points, Lines, Arcs, Etc.
//
// File:	epm_display.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sat Aug 29 05:29:29 EDT 2020
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// Compile with:
//
//	g++ [-O3] [-g] \
//	    -I /usr/include/cairo \
//	    -o hpcm_display \
//	    hpcm_display.cc -lcairo

// Table of Contents
//
//     Setup
//     Vectors
//     Basic Data
//     Documentation
//     Layout Section Data
//     Page Section Data
//     Read Routines
//     Page Draw Routines
//     Main Program

// Setup
// -----
//
#include <iostream>
#include <iomanip>
#include <fstream>
#include <map>
#include <algorithm>
#include <string>
#include <sstream>
#include <vector>

#include <cstdlib>
#include <cstdarg>
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
using std::istringstream;
using std::map;
using std::min;
using std::max;
using std::string;
using std::isnan;
// std::vector is not here as vector is used for
// another purpose.

extern "C" {
#include <unistd.h>
#include <cairo-pdf.h>
}

const size_t MAX_NAME_LENGTH = 40;
const double MAX_BODY_COORDINATE = 0.90 * DBL_MAX;
const int MAX_LEVEL = 100;
const char * whitespace = " \t\f\v\n\r";
const char * namechars = "abcdefghijklmnopqrstuvwxyz"
                         "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
			 "0123456789"
			 "-_";

bool debug = false;
# define dout if ( debug ) cerr

// Vectors:
//
struct vector { double x, y; };

inline vector operator + ( vector v1, vector v2 )
{
    vector r = { v1.x + v2.x, v1.y + v2.y };
    return r;
}

inline vector operator - ( vector v1, vector v2 )
{
    vector r = { v1.x - v2.x, v1.y - v2.y };
    return r;
}

inline vector operator - ( vector v )
{
    vector r = { - v.x, - v.y };
    return r;
}

inline vector operator * ( double s, vector v )
{
    vector r = { s * v.x, s * v.y };
    return r;
}

inline double operator * ( vector v1, vector v2 )
{
    return v1.x * v2.x + v1.y * v2.y;
}

// Rotate v by angle.
//
// WARING: ^ has lower precedence than + or -.
//
inline vector operator ^ ( vector v, double angle )
{
    double s = sin ( M_PI * angle / 180 );
    double c = cos ( M_PI * angle / 180 );
    vector r = { c * v.x - s * v.y,
                 s * v.x + c * v.y };
    return r;
}

inline ostream & operator <<
	( ostream & s, const vector & v )
{
    return s << "(" << v.x << "," << v.y << ")";
}

// Basic Data
// ----- ----

struct color
{
    const char * name;
    unsigned value;		// HTML value
    double red, green, blue;	// cairo value
};

color colors[] = {
#    include "epm_colors.h"
    , { "", 0 }, {"", 0 }
        // So we can print groups of 3.
};

void init_colors ( void )
{
    for ( color * c = colors; c->name[0] != 0; ++ c )
    {
        c->red = ( 0xFF & ( c->value >> 16 ) ) / 255.0;
        c->green = ( 0xFF & ( c->value >> 8 ) ) / 255.0;
        c->blue = ( 0xFF & ( c->value >> 0 ) ) / 255.0;
    }
}

// Return color from colors array with given name,
// or return NULL if none.
//
const color * find_color ( const char * name )
{
    for ( color * c = colors; c->name[0] != 0; ++ c )
    {
        if ( strcmp ( c->name, name ) == 0 )
	    return c;
    }
    return NULL;
}

const char * families[] = {
    "serif",
    "sans-serif",
    "monospace",
    NULL
};

// Verify family name and return it, or return NULL
// if name is not a family name.
//
const char * find_family ( const char * name )
{
    const char ** f = families;
    while ( * f )
    {
	if ( strcmp ( name, * f ) == 0 ) break;
    }
    return * f;
}

enum options {
    NO_OPTIONS          = 0,

    // Font Options:
    //
    BOLD		= 1 << 0,
    ITALIC		= 1 << 1,

    // Stroke Options:
    //
    DOTTED		= 1 << 2,
    DASHED		= 1 << 3,
    CLOSED		= 1 << 4,
    BEGIN_ARROW		= 1 << 5,
    MIDDLE_ARROW	= 1 << 6,
    END_ARROW		= 1 << 7,
    FILL_SOLID		= 1 << 8,
    FILL_CROSS		= 1 << 9,
    FILL_RIGHT		= 1 << 10,
    FILL_LEFT		= 1 << 11,

    // Text Options:
    //
    TOP 		= 1 << 12,
    BOTTOM		= 1 << 13,
    LEFT		= 1 << 14,
    RIGHT		= 1 << 15,
    BOX_WHITE		= 1 << 16,
    CIRCLE_WHITE	= 1 << 17,
    OUTLINE		= 1 << 18,
};
const char * optchar = "bi" ".-cbmesx/\\" "tblrxco";

// Print options for debugging:
//
ostream & operator << ( ostream & s, options opt )
{
    if ( opt == 0 ) return s;
    int len = strlen ( optchar );
    for ( int i = 0; i < len; ++ i )
        if ( opt & ( 1 << i ) ) s << optchar[i];
    return s;
}

struct margins
{
    double top, right, bottom, left;
};

// Print margins for debugging:
//
ostream & operator << ( ostream & s, const margins & m )
{
    s << m.top << " " << m.right << " " << m.bottom
               << " " << m.left;
    return s;
}

struct bounds
{
    vector ll, ur;
};

// Print bounds for debugging:
//
ostream & operator << ( ostream & s, const bounds & b )
{
    s << b.ll << " " << b.ur;
    return s;
}

// Documentation
// -------------

const char * const documentation[2] = { "\n"
"hpcm_display [-debug] [file]\n"
"\n"
"    This program displays line drawings defined\n"
"    in the given file or standard input.  The file\n"
"    consists of sections each consisting of command\n"
"    lines followed by a line containing just `*'.\n"
"\n"
"    There are two kinds of sections: layout"
                                        " sections,\n"
"    and page sections.  A layout section redefines\n"
"    the context for page sections.  This context\n"
"    includes default background color and margins\n"
"    for pages, and descriptions of fonts and types\n"
"    of line strokes (solid, dashed, etc.) that can\n"
"    be used by commands within a page section.\n"
"\n"
"    Each page section specifies one page.\n"
"\n"
"    The page is divided into three sections: a\n"
"    head, a body, and a foot, in that order from\n"
"    top to bottom.  (But not necessarily in that\n"
"    order in the input).\n"
"\n"
"    The command lines for a page are read and saved\n"
"    in lists.  There is one list for the head and\n"
"    one list for the foot.  After these are read the\n"
"    height of the head and foot are computed.  The\n"
"    remaining page height is assigned to the body.\n"
"\n"
"    There are as many as 100 lists for the body,\n"
"    numbered 1 through 100.  These lists are execu-\n"
"    ted in the order 1, 2, ..., 100, with each over-\n"
"    laying the output of the previous lists.  So,\n"
"    for example, a white bounding box for some text\n"
"    can overlay a line that the text describes.\n"
"\n"
"    The head and foot commands output centered text\n"
"    lines and extra space between these lines.\n"
"\n"
"    For body commands, some numbers have units and\n"
"    some do not.  Body coordinates has not units\n"
"    and can be scaled automatically so the bounding\n"
"    box of all points with such coordinates fits\n"
"    in the area between the head and foot.  The\n"
"    ratio of X and Y scales, which with automatic\n"
"    scaling could be anything, can be forced to a\n"
"    particular value, such as 1.\n"
"\n"
"    Numbers that represent lengths can have the `pt'\n"
"    suffix meaning `points' (1/72 inch) or the `in'\n"
"    suffix meaning `inches'.   Some number, such as\n"
"    those used to determine text line spacing, have\n"
"    `em' units.  One em is the font size at the time\n"
"    the number used.  Body coordinates have no\n"
"    units, are associated with the X or Y axis, and\n"
"    are not permitted in the head or foot.\n"
"\n"
"    Many commands have a `color' parameter.  The\n"
"    possible colors are:\n"
"\n",
"\n"
"    These are defined as per the HTML standard.\n"
"\n"
"    After all the commands have been read into\n"
"    lists, the lists are executed.  Context para-\n"
"    meters are always set to their defaults at the\n"
"    beginning of each list.\n"
"\n"
"    The commands are described below.  Upper case\n"
"    identifiers in a command designate parameters.\n"
"\n"
"    Layout Commands:\n"
"    ------ --------\n"
"\n"
"      These commands can be given in a layout\n"
"      section.  The `background', `scale',"
                                    " `margins',\n"
"      and `bounds' commands may also be used in a\n"
"      page section to override the associated\n"
"      layout parameters for the page.\n"
"\n"
"      The `layout' command must be the first\n"
"      command of a layout section.  Parameter values\n"
"      not given in a layout section revert to their\n"
"      defaults (and NOT to their previous values).\n"
"\n"
"      layout [R C [HEIGHT WIDTH]]\n"
"      layout R C HEIGHT WIDTH ALL\n"
"      layout R C HEIGHT WIDTH VERTICAL HORIZONAL\n"
"      layout R C HEIGHT WIDTH TOP RIGHT BOTTOM LEFT\n"
"        Must be first command of a layout section.\n"
"\n"
"        May cause multiple logical pages to be put\n"
"        on each physical page, if R != 1 or C != 1.\n"
"        There are R rows of C columns each of\n"
"        logical pages per physical page.  Defaults\n"
"        are R = 1, C = 1.\n"
"\n"
"        HEIGHT and WIDTH are the physical page\n"
"        height and width, and default to 11in and\n"
"        8.5in, respectively.\n"
"\n"
"        ALL is the physical page margin on all"
                                         " sides.\n"
"        VERTICAL is the top and bottom margin, while\n"
"        HORIZONTAL is the left and right margin.\n"
"        TOP, RIGHT, BOTTOM, and LEFT are the 4\n"
"        specific margins.  Defaults are TOP = 0.5in,\n"
"        BOTTOM = 0.5in, LEFT = 0.75in, RIGHT =\n"
"        0.75in.\n"
"\n"
"      font NAME SIZE [COLOR] [OPT] [FAMILY] [SPACE]\n"
"        Defines a named font.\n"
"\n"
"        SIZE is the em square size of the font\n"
"             and is normally given in points;\n"
"             capital letters are typically 0.7em\n"
"             high and lower case x is typically\n"
"             0.5em high\n"
"        OPT is some of (default is none of):\n"
"            b for bold\n"
"            i for italic\n"
"        FAMILY is one of:\n"
"            serif (the default)\n"
"            sans-serif\n"
"            monospace\n"
"        SPACE is the horizontal space\n"
"              between text lines and must have em\n"
"              units; default 1.15em, meaning 1.15\n"
"              time SIZE.\n"
"\n"
"      stroke NAME [WIDTH] [COLOR] [OPT]\n"
"        Defines a named line stroke.\n"
"\n"
"        WIDTH is the line width.  Default for non-\n"
"              fill lines is 1pt, and for fill\n"
"              lines is 0pt.\n"
"        OPT is one of:\n"
"            .   dotted line\n"
"            -   dashed line\n"
"            c   close path\n"
"            b   arrow head at beginning of segment\n"
"            m   arrow head in middle of segment\n"
"            e   arrow head at end of segment\n"
"            s   fill with solid color\n"
"            x   fill with cross hatch\n"
"            /   fill with right leaning hatch\n"
"            \\   fill with left leaning hatch\n"
"\n"
"      background COLOR\n"
"        Sets the default background color for each\n"
"        page.  Default is `white'.\n"
"\n"
"      scale S\n"
"        Sets the default scale S for each page.\n"
"        S is the Y/X scale ratio for the scales\n"
"        of body coordinates; defaults to NAN which\n"
"        allows whatever ratio fills the entire body\n"
"        of the page.\n"
"\n"
"      margins ALL\n"
"      margins VERTICAL HORIZONTAL\n"
"      margins TOP RIGHT BOTTOM LEFT\n"
"        Sets the default margins for the body of\n"
"        each page.  Margins are lengths.\n"
"\n"
"    List Switching Commands:\n"
"    ---- --------- --------\n"
"\n"
"      These commands switch lists in a page section.\n"
"      A page section begins with an implicit"
                         " `level 50'\n"
"      command.\n"
"\n"
"        head\n"
"          Start or continue the head list.\n"
"\n"
"        foot\n"
"          Start or continue the foot list.\n"
"\n"
"        level N\n"
"          Start or continue list n, where\n"
"          1 <= n <= 100.  Push previous level into\n"
"          the level stack.\n"
"\n"
"        level\n"
"          Start or continue list m, where m is at\n"
"          the top of the level stack.  Pop the\n"
"          level stack.\n"
"\n"
"        A page need not have any head or foot.\n"
"        Body coordinates are not permitted in the\n"
"        head or foot.\n"
"\n"
"\n"
"    Head and Foot Commands:\n"
"    ---- --- ---- --------\n"
"\n"
"        These commands can only appear on the head\n"
"        or foot list.\n"
"\n"
"        text FONT TEXT1\\TEXT2\\TEXT3\n"
"        text FONT TEXT1\\TEXT3\n"
"        text FONT TEXT2\n"
"           Display text as a line.  TEXT1 is left\n"
"           adjusted; TEXT2 is centered; TEXT3 is\n"
"           right adjusted.\n"
"\n"
"        space SPACE\n"
"           Insert whitespace of SPACE height.\n"
"\n"
"\n"
"    Page Commands:\n"
"    ---- --------\n"
"\n"
"      These commands can only appear in a page\n"
"      section.\n"
"\n"
"      text FONT [OPT] X Y TEXT\\...\n"
"        Display text at point (X,Y) which must\n"
"        be in body coordinates.\n"
"        FONT is font name.\n"
"        OPT are some of:\n"
"          t  display 0.15em below y\n"
"          b  display 0.15em above y\n"
"             If neither option, vertically\n"
"             center on y.\n"
"          l  display 0.25em to left of x\n"
"          r  display 0.25em to right of x\n"
"             If neither option, horizontally\n"
"             center on x.\n"
"          x  make the bounding box of the text\n"
"             white\n"
"          c  make the bounding circle of the text\n"
"             white\n"
"          o  outline the bounding box or circle\n"
"             with a line of width 1pt\n"
"\n"
"        The TEXT is broken into lines separated\n"
"        by backslashes (\\).  With `l' lines are\n"
"        right adjusted; with `r' they are left\n"
"        adjusted; and with neither they are"
                                   " centered.\n"
"\n"
"      start STROKE X Y\n"
"        Begin a path at (X,Y) which must be in\n"
"        body coordinates.\n"
"\n"
"      line X Y\n"
"        Continue path by straight line to (X,Y)\n"
"        which must be in body coordinates.\n"
"\n"
"      curve X1 Y1 X2 Y2 X3 Y3\n"
"        Continue path by a curve to (X3,Y3)\n"
"        with control points (X1,Y1) and (X2,Y2)\n"
"        where all points must be in body coordi-\n"
"        nates.  If path previously ended at (X0,Y0),\n"
"        the curve will be tangent to (X0,Y0)-(X1,Y1)\n"
"        at (X0,Y0), and will be tangent to (X2,Y2)-\n"
"        (X3,Y3) at (X3,Y3).\n"
"\n"
"      end\n"
"        Ends the current path.  This is implied by\n"
"        any command at the same level that does not\n"
"        continue the path (i.e., any command but\n"
"        `line' or `curve').\n"
"\n"
"        If the path stroke has a fill or close\n"
"        option, this command draws a line from the\n"
"        end of the path to its beginning, in order\n"
"        to close the path.\n"
"\n"
"      arc STROKE XC YC RX RY [A [G1 G2]]\n"
"      arc STROKE XC YC R\n"
"        Draw an elliptical arc of given line type as\n"
"        follows.\n"
"\n"
"        First the a circular arc of unit radius is\n"
"        drawn with center (0,0).  The begin point\n"
"        is at angle G1 and the end point is at\n"
"        angle G2.  If G2 > G1 the arc goes counter-\n"
"        clockwise; if G2 < G1 the arc goes clock-\n"
"        wise.  Any multiple of 360 added to G1 or G2\n"
"        does not affect the points, but may affect\n"
"        the arc direction.\n"
"\n"
"        Then transformations are applied.  First\n"
"        the X and Y axes are scaled by RX and RY;\n"
"        second the whole is rotated counter-clock-\n"
"        wise by A degrees, and lastly the whole is\n"
"        translated moving (0,0) to (XC,YC).\n"
"\n"
"        If R is given, RX = RY = R and the arc is a\n"
"        complete circle.  Otherwise the arc is part\n"
"        of an allipse, A defaults to 0, G1 defaults\n"
"        to 0, and G2 defaults to 360.\n"
"\n"
"        A fill or close option in STROKE draws a\n"
"        straight line from G1 to G2 (of possibly\n"
"        zero length).\n"
"\n"
"      arc RX RY A G1 G2\n"
"        Continue the current path with an arc such\n"
"        that the point designated by G1 is the last\n"
"        point on the path so far.  The path is con-\n"
"        tinued from this point to the point designa-\n"
"        ted by G2.  The arc is drawn as for the\n"
"        previous arc command.\n"
"\n"
"      rectangle STROKE XMIN YMIN XMAX YMAX\n"
"        Draw a rectangle with given STROKE type and\n"
"        minumum and maximum X and Y coordinates.\n"
"        Arrow options in STROKE are not recognized.\n"
"\n"
"      ellipse STROKE XMIN YMIN XMAX YMAX\n"
"        Draw an ellipse with given STROKE type and\n"
"        minumum and maximum X and Y coordinates.\n"
"        The ellipse axes are parallel to the X and\n"
"        Y axes.  Arrow options in STROKE are not\n"
"        recognized.\n"
} ;

void print_documentation ( int exit_code )
{
    FILE * out = popen ( "less -F", "w" );
    fputs ( documentation[0], out );

    int len = sizeof ( colors ) / sizeof ( color );
    for ( int i = 0; i + 3 <= len; i += 3 )
	fprintf ( out, "    %-16s%-16s%-16s\n",
	          colors[i].name,
	          colors[i+1].name,
	          colors[i+2].name );

    fputs ( documentation[1], out );
    pclose ( out );
    exit ( exit_code );
}

// Layout Section Data
// ------ ------- ----

// Layout Parameters
//
int R, C;
double L_height, L_width;
margins L_margins;

// Default page parameters from layout section.
//
double D_scale;
const color * D_background;
margins D_margins;
bounds D_bounds;

options font_options = (options) ( BOLD + ITALIC );
struct font
{
    string name;
    double size;
    const color * c;
    options o;
    const char * family;
    double space;

    char cairo_family[MAX_NAME_LENGTH+1];
    cairo_font_slant_t cairo_slant;
    cairo_font_weight_t cairo_weight;
};

options stroke_options = (options)
    ( DOTTED + DASHED + CLOSED +
      BEGIN_ARROW + MIDDLE_ARROW + END_ARROW +
      FILL_SOLID + FILL_CROSS +
      FILL_RIGHT + FILL_LEFT );
struct stroke
{
    string name;
    const color * c;
    options o;
    double width;
};

typedef map<string, const font *> font_dt;
typedef map<string, const stroke *> stroke_dt;
font_dt font_dict;
stroke_dt stroke_dict;
typedef font_dt::iterator font_it;
typedef stroke_dt::iterator stroke_it;

// Make a new font with given name, discarding any
// previous font with that name.  Return the new
// font.
//
const font * make_font ( string name,
		         double size,
                         const color * c,
			 options o,
                         const char * family,
	                 double space )
{
    assert ( c != NULL );

    font_it it = font_dict.find ( name );
    if ( it != font_dict.end() )
    {
	delete it->second;
        font_dict.erase ( it );
    }
    font * f = new font;
    f->name = name;
    f->c = c;
    f->o = o;
    f->family = family;
    // Putting cairo: in front of these does not
    // work, so cairo_family == family.
    assert (    strlen ( family ) + 7
             <= sizeof ( f->cairo_family ) );
    sprintf ( f->cairo_family, "%s", family );
    f->size = size;
    f->space = space;

    f->cairo_slant =
        ( o & ITALIC ? CAIRO_FONT_SLANT_ITALIC :
                       CAIRO_FONT_SLANT_NORMAL );
    f->cairo_weight =
        ( o & BOLD ? CAIRO_FONT_WEIGHT_BOLD :
                     CAIRO_FONT_WEIGHT_NORMAL );

    font_dict[f->name] = f;

    return f;
}

// Make a new stroke with given name, discarding any
// previous stroke with that name.  Return the new
// stroke.
//
const stroke * make_stroke ( string name,
                             double width,
		             const color * c,
			     options o )
{
    assert ( c != NULL );

    stroke_it it = stroke_dict.find ( name );
    if ( it != stroke_dict.end() )
    {
	delete it->second;
        stroke_dict.erase ( it );
    }
    stroke * s = new stroke;
    s->name = name;
    s->c = c;
    s->o = o;
    s->width = width;

    stroke_dict[s->name] = s;

    return s;
}

// Print fonts for debugging.
//
void print_font ( const font * f )
{
    cout << "font " << f->name
	 << " " << 72 * f->size << "pt"
	 << " " << f->c->name
	 << " " << f->o
	 << " " << f->family
	 << " " << f->space << "em"
	 << endl;
}

// Print strokes for debugging.
//
void print_stroke ( const stroke * s )
{
    cout << "stroke " << s->name
	 << " " << 72 * s->width << "pt"
	 << " " << s->c->name
	 << " " << s->o
	 << endl;
}


void init_layout ( int R, int C )
{
    ::R = R;
    ::C = C;
    L_height = 11.0;
    L_width = 8.5;
    L_margins.top = 0.5;
    L_margins.right = 0.75;
    L_margins.bottom = 0.5;
    L_margins.left = 0.75;

    D_scale = NAN;
    D_background = NULL;
    D_margins = { 0, 0, 0, 0 };
    D_bounds = { { NAN, NAN }, { NAN, NAN } };

    for ( font_it it = font_dict.begin();
          it != font_dict.end(); ++ it )
        delete (font *) it->second;

    for ( stroke_it it = stroke_dict.begin();
          it != stroke_dict.end(); ++ it )
        delete (stroke *) it->second;

    font_dict.clear();
    stroke_dict.clear();

    const color * black = find_color ( "black" );
    assert ( black != NULL );

    if ( C == 1 )
    {
        make_font ( "large-bold", 14.0/72, black,
	            BOLD, "serif",
	            1.15 );
        make_font ( "bold", 12.0/72, black,
	            BOLD, "serif", 1.15 );
        make_font ( "small-bold", 10.0/72, black,
	            BOLD, "serif", 1.15 );
        make_font ( "large", 14.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );
        make_font ( "normal", 12.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );
        make_font ( "small", 10.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );
	make_stroke ( "wide", 2.0/72,
	               black, NO_OPTIONS );
	make_stroke ( "normal", 1.0/72,
	               black, NO_OPTIONS );
	make_stroke ( "narrow", 0.5/72,
	               black, NO_OPTIONS );
	make_stroke ( "wide-dashed", 2.0/72,
	              black, DASHED );
	make_stroke ( "normal-dashed", 1.0/72,
	              black, DASHED );
	make_stroke ( "narrow-dashed", 0.5/72,
	              black, DASHED );
    }
    else
    {
        make_font ( "large-bold", 12.0/72, black,
	            BOLD, "serif", 1.15 );
        make_font ( "bold", 10.0/72, black,
	            BOLD, "serif", 1.15 );
        make_font ( "small-bold", 8.0/72, black,
	            BOLD, "serif", 1.15 );
        make_font ( "large", 12.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );
        make_font ( "normal", 10.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );
        make_font ( "small", 8.0/72, black,
	            NO_OPTIONS, "serif", 1.15 );

	make_stroke ( "wide", 1.0/72,
	               black, NO_OPTIONS );
	make_stroke ( "normal", 0.5/72,
	               black, NO_OPTIONS );
	make_stroke ( "narrow", 0.25/72,
	               black, NO_OPTIONS );
	make_stroke ( "wide-dashed", 1.0/72,
	              black, DASHED );
	make_stroke ( "normal-dashed", 0.5/72,
	              black, DASHED );
	make_stroke ( "narrow-dashed", 0.25/72,
	              black, DASHED );
    }
}

// Page Section Data
// ---- ------- ----
//
struct command
{
    char c;
    bool continued;
        // Start, line, etc command that is continued
	// until the next `end' command.
    command * next;
};

options text_options = (options)
    ( TOP + BOTTOM + LEFT + RIGHT +
      BOX_WHITE + CIRCLE_WHITE + OUTLINE );
struct text : public command // == 't'
{
    const font * f;
    options o;
    vector p;
    string t;
};
struct space : public command // == 'S'
{
    double s;
};
struct start : public command // == 's'
{
    const stroke * s;
    vector p;
};
struct line : public command // == 'l'
{
    vector p;
};
struct curve : public command // == 'c'
{
    vector p[3];
};
struct end : public command // == 'e'
{
};
struct arc : public command // == 'a'
{
    const stroke * s;
    vector c;
    vector r;
    double a;
    double g1, g2;
};
struct rectangle : public command // == 'r'
{
    const stroke * s;
    vector p[4];
};

// Page Parameters.
//
const color * P_background;
double P_scale;
margins P_margins;
bounds P_bounds;
double P_height, P_width;


// List of all commands in a page.  If not NULL, the
// list variable points at the last command which
// points at the first command with its next member,
// so the commands are in a circular list.
//
command * head = NULL, * foot = NULL,
        * level[101] = { NULL };


// Print all commands in a list for debugging.
//
void print_commands ( command * list )
{
    command * current = list;
    do
    {
        current = current->next;

	switch ( current->c )
	{
	case 't':
	{
	    text * t = (text *) current;
	    cout << "text " << t->f->name
	         << " " << t->o
		 << " " << t->p
		 << " " << t->t
		 << endl;
	    break;
	}
	case 'S':
	{
	    space * s = (space *) current;
	    cout << "space " << s->s << "in"
		 << endl;
	    break;
	}
	case 's':
	{
	    start * s = (start *) current;
	    cout << "start " << s->s->name
		 << " " << s->p
		 << endl;
	    break;
	}
	case 'l':
	{
	    line * l = (line *) current;
	    cout << "line " << l->p
		 << endl;
	    break;
	}
	case 'c':
	{
	    curve * c = (curve *) current;
	    cout << "curve " << c->p[0]
	         << " " << c->p[1]
	         << " " << c->p[2]
		 << endl;
	    break;
	}
	case 'e':
	    cout << "end" << endl;
	    break;
	case 'a':
	{
	    arc * a = (arc *) current;
	    if ( a->s == NULL )
		cout << "arc"
		     << " " << a->r
		     << " " << a->a
		     << " " << a->g1
		     << " " << a->g2
		     << endl;
	    else
		cout << "arc " << a->s->name
		     << " " << a->c
		     << " " << a->r
		     << " " << a->a
		     << " " << a->g1
		     << " " << a->g2
		     << endl;
	    break;
	}
	case 'r':
	{
	    rectangle * r = (rectangle *) current;
	    cout << "rectangle " << r->s->name
	         << " " << r->p[0]
	         << " " << r->p[1]
	         << " " << r->p[2]
	         << " " << r->p[3]
		 << endl;
	    break;
	}
	default:
	    cout << "bad command " << current->c
	         << endl;
	}

    } while ( current != list );
}

// Delete all commands in a list and set list NULL.
//
void delete_commands ( command * & list )
{
    command * current = list;
    if ( current != NULL ) do
    {
        command * next = current->next;

	switch ( list->c )
	{
	case 't':
	    delete (text *) current;
	    break;
	case 'S':
	    delete (space *) current;
	    break;
	case 's':
	    delete (start *) current;
	    break;
	case 'l':
	    delete (line *) current;
	    break;
	case 'c':
	    delete (curve *) current;
	    break;
	case 'e':
	    delete (end *) current;
	    break;
	case 'a':
	    delete (arc *) current;
	    break;
	case 'r':
	    delete (rectangle *) current;
	    break;
	default:
	    assert ( ! "deleting bad command" );
	}

    } while ( current != list );

    list = NULL;
}

void init_page ( void )
{
    P_background = D_background;
    P_scale = D_scale;
    P_margins = D_margins;
    P_bounds = D_bounds;

    P_height = L_height - L_margins.top
                        - L_margins.bottom;
    P_height /= R;
    P_width = L_width - L_margins.left
                      - L_margins.right;
    P_width /= C;

    delete_commands ( head );
    delete_commands ( foot );
    for ( int i = 1; i <= MAX_LEVEL; ++ i )
        delete_commands ( level[i] );
}


// Read Routines
// ---- --------

// Current command line for error routines.
//
string comline;
unsigned line_number = 0;

// Current line and token for read routines.
//
istringstream lin;
string token;
long token_long;
double token_double;
string units;
    // Token is next sequence of non-whitespace
    // characters, or "" if none;
    //
    // Get_long sets token_long to integer at
    // beginning of token and sets units to remainder
    // of token, or returns false if no integer
    // at beginning of token.
    //
    // Get_double sets token_double similarly.

// Helper function for error functions.
//
void error ( const char * format, va_list args )
{
    cerr << "ERROR in line " << line_number
         << ":" << endl << "    " << comline << endl;
    fprintf ( stderr, "    " );
    vfprintf ( stderr, format, args );
    fprintf ( stderr, "\n" );
}

// Output non-fatal error to cerr.
//
void error ( const char * format... )
{
    va_list args;
    va_start ( args, format );
    error ( format, args );
    va_end ( args );
}

// Output fatal error to cerr and exit(1).
//
void fatal ( const char * format... )
{
    cerr << "FATAL ";
    va_list args;
    va_start ( args, format );
    error ( format, args );
    va_end ( args );
    exit ( 1 );
}

// If there is currently no token, get the next token.
// Set token = "" if there is no next token.  Return
// true iff there is a next token.
//
inline bool get_token ( void )
{
    if ( token != "" ) return true;

    lin >> token;
    if ( lin.fail() )
    {
        // lin >> token fails if it finds no non-white-
	// space character before eof.
	//
        token = "";
	return false;
    }
    else
        return true;
}

// If there is a next token with an integer at its
// beginning, return the integer in token_long and the
// remainder of the token after the integer in `units',
// skip over the token, and return true.
//
// Otherwise do nothing but return false.
//
inline bool get_long ( void )
{
    if ( ! get_token() ) return false;
    char * endp;
    const char * beginp = token.c_str();
    token_long = strtol ( beginp, & endp, 10 );
    if ( beginp == endp ) return false;
    units = token.substr ( endp - beginp );
    token = "";
    return true;
}

// Ditto for double.
//
inline bool get_double ( void )
{
    if ( ! get_token() ) return false;
    char * endp;
    const char * beginp = token.c_str();
    token_double = strtod ( beginp, & endp );
    if ( beginp == endp ) return false;
    units = token.substr ( endp - beginp );
    token = "";
    return true;
}

// Read long integer without units.
//
// If the next token does not begin with an integer,
// leave the token alone are return false.  If in
// addition missing_allowed is false, print an error
// message of the form `NAME is missing'.
//
// Otherwise check for errors and if none set var
// to the integer and return true.  If there are
// errors leave var alone and return false.  But
// in these cases the token is skipped over.
//
// For this function the possible errors are having
// units or the integer being outside the range
// [low,high].
//
bool read_long ( const char * name, long & var,
                 long low, long high,
                 bool missing_allowed = true )
{
    if ( ! get_long() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( units != "" )
    {
	error ( "%s should not have units %s",
	        name, units.c_str() );
	return false;
    }
    if ( token_long < low || token_long > high )
    {
        error ( "%s out of range [%ld,%ld]",
	        name, low, high );
	return false;
    }
    var = token_long;
    return true;
}

// Read double without units or scale.
//
// Similar to read_long.
//
bool read_double ( const char * name, double & var,
                   double low, double high,
                   bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( units != "" )
    {
	error ( "%s should not have units %s",
	        name, units.c_str() );
	return false;
    }
    if ( token_double < low || token_double > high )
    {
        error ( "%s out of range [%g,%g]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

// Read double length with units.
//
// Similar to read_long but for double and requires
// either `pt' or `in' units, setting var to the
// value in inches.
//
bool read_length ( const char * name, double & var,
                   double low, double high,
                   bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( units == "pt" )
        token_double /= 72;
    else if ( units != "in" )
    {
	error ( "%s should have pt or in units",
	        name );
	return false;
    }
    if ( token_double < low || token_double > high )
    {
        error ( "%s out of range [%gin,%gin]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

// Read double with em units.
//
// Similar to read_long but for double and requires
// `em' units.  Sets var to the value in these units.
//
bool read_em ( const char * name, double & var,
               double low, double high,
               bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( units != "em" )
    {
	error ( "%s should have em units", name );
	return false;
    }
    if ( token_double < low || token_double > high )
    {
        error ( "%s out of range [%gem,%gem]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

// If no next token exits return false.  If in addition
// missing_allowed is false, print an error message of
// the form `NAME is missing'.
//
// Otherwise check for errors and if none set var to the
// next token and return true, but if errors leave var
// alone and return false.  In either case the token is
// skipped over.
//
// The possible error is a token longer than MAX_NAME_
// LENGTH.
//
bool read_name ( const char * name,
                 string & var,
                 bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( token.length() > MAX_NAME_LENGTH )
    {
	string s = token.substr ( 0, MAX_NAME_LENGTH )
	                .append ( "..." );
	error ( "%s value %s too long a name",
	        name, s.c_str() );
	token = "";
	return false;
    }
    if (    token.find_first_not_of ( namechars )
         != string::npos )
    {
	error ( "%s has character other than"
	        " letter, digit, `-', or `_'", name );
	token = "";
	return false;
    }
    var = token;
    token = "";
    return true;
}

// Read margin parameters.  The paramter names are
// ALL, VERTICAL, HORIZONTAL, TOP, RIGHT, BOTTOM, and
// LEFT, as appropriate.  Set default values before
// calling.
//
// Begins by reading a length as per read_length
// and passing that the missing_allowed parameter.
// Returns false if that returns false.
//
// Otherwise continues reading the remaining 0 to 3
// margins using read_length, allowing them to be
// missing and stopping at the first one that is
// missing.  The case of 3 values total generates a
// `LEFT is missing' error message but sets the left
// margin equal to the right margin.
//
bool read_margins ( margins & var,
                    bool missing_allowed = true )
{
    if ( ! read_length ( "ALL", var.top, 0, 100,
                         missing_allowed ) )
        return false;
    if ( ! read_length ( "HORIZONTAL", var.right,
                          0, 100 ) )
	var.right = var.bottom = var.left
	          = var.top;
    else if ( ! read_length ( "BOTTOM", var.bottom,
                               0, 100 ) )
	var.bottom = var.top, var.left = var.right;
    else if ( ! read_length ( "LEFT", var.bottom,
                               0, 100, false ) )
	var.left = var.right;
    return true;
}

// If there is no next token or the next token is not
// a color name, exits and returns false.  If the token
// exists it is NOT skipped.  If in addition missing_
// allowed is false, prints an error message of the
// form `NAME is missing'.
//
// Otherwise stores the color in var and returns true.
//
bool read_color ( const char * name,
                  const color * & var,
                  bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    const color * val = find_color ( token.c_str() );
    if ( val == NULL )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    var = val;
    token = "";
    return true;
}

// Ditto but for font family names instead of colors.
//
bool read_family ( const char * name,
                   const char * & var,
                   bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    const char * val = find_family ( name );
    if ( val == NULL )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    var = val;
    token = "";
    return true;
}

// Ditto but for options instead of colors.
//
// To be recognized as options, a token must contain
// only option characters enabled by allowed_options.
//
// If the token is a legal set of options, but has
// duplicate characters, an error message is produced
// but the token is accepted as if the duplicates were
// removed.
//
bool read_options ( const char * name,
                    options & var,
		    options allowed_options,
                    bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    options val = NO_OPTIONS;

    int char_count = 0;
    int opt_count = 0;
    for ( int i = 0; optchar[i] != 0; ++ i )
    {
        if ( ( allowed_options & ( 1 << i ) ) == 0 )
	    continue;
	size_t pos = token.find_first_of ( optchar[i] );
	if ( pos != string::npos )
	{
	    ++ opt_count;
	    val = (options) ( val | ( 1 << i ) );
	    do
	    {
		++ char_count;
	        pos = token.find_first_of
		    ( optchar[i], pos + 1 );
	    } while ( pos != string::npos );
	}
    }
    if ( char_count != token.length() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    if ( char_count != opt_count )
        error ( "duplicate option flags in %s value %s",
	        name, token.c_str() );

    var = val;
    token = "";
    return true;
}

// Like read_color but for font names looked up in the
// dictionary of fonts defined by the last layout
// section.
//
bool read_font ( const char * name,
	         const font * & var,
	         bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    font_it val = font_dict.find ( token );
    if ( val == font_dict.end() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    var = val->second;
    token = "";
    return true;
}

// Like read_color but for stroke names looked up in the
// dictionary of stokes defined by the last layout
// section.
//
bool read_stroke ( const char * name,
	           const stroke * & var,
	           bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    stroke_it val = stroke_dict.find ( token );
    if ( val == stroke_dict.end() )
    {
        if ( ! missing_allowed )
	    error ( "%s missing", name );
	return false;
    }
    var = val->second;
    token = "";
    return true;
}

// Read what is left in lin.  If token != "", generates
// an error message indicating the token is being
// ignored.  Trims whitespace from ends of output.  May
// produce "".
//
void read_text ( const char * name,
		 string & text )
{
    if ( token != "" )
        error ( "%s ignored", token.c_str() );
     
    lin >> ws;
    getline ( lin, text );
    size_t last = text.find_last_not_of
	( whitespace );
    if ( last != string::npos )
        text.erase ( last + 1 );
}

// If there are any tokens left, generates and erro
// message noting they should not exist.
//
void check_extra ( void )
{
    if ( get_token() )
	error ( "extra stuff %s... at end of line",
		token.c_str() );
}

// Attach command to end of current_list.  Returns
// false if this cannot be dones because command is
// continuing and has no previous start.  This
// cannot happen if continuing is false.
//
command ** current_list;
inline bool attach ( command * com, char c,
                     bool continued = false,
	             bool continuing = false )
{
    command * last = * current_list;
    if ( last == NULL )
    {
        if ( continuing )
	{
	    error ( "need a `start' command before"
	            " this command" );
	    return false;
	}
	* current_list = com->next = com;
    }
    else
    {
        if ( continuing && ! last->continued )
	{
	    error ( "need a `start' command before"
	            " this command" );
	    return false;
	}
        else if ( ! continuing && last->continued )
	{
	    error ( "missing `end' inserted" );
	    end * e = new end;
	    e->c = 'e';
	    e->continued = false;
	    e->next = last->next;
	    last->next = e;
	    last = * current_list = e;
	 }
	 com->next = last->next;
	 last->next = com;
	 * current_list = com;
    }
        
    com->c = c;
    com->continued = continued;
    return true;
}

// Read section.  Return END_OF_FILE if no section
// left in input, or LAYOUT if section was layout
// section, or PAGE if section was page section.
//
enum section { END_OF_FILE, LAYOUT, PAGE };
section read_section ( istream & in )
{

    section s = END_OF_FILE;
    bool in_body = false;
    bool in_head_or_foot = false;

    const color * black = find_color ( "black" );
    assert ( black != NULL );

    std::vector<long> level_stack;

    while ( true )
    {
        getline ( in, comline );
	if ( ! in )
	{
	    if ( s == END_OF_FILE ) return s;

	    cerr << "WARNING: unexpected end of file;"
	         << " * inserted" << endl;
	    comline = "*";
	}
	++ line_number;

	// Skip comments.
	//
	size_t first = comline.find_first_not_of
	    ( whitespace );
	if ( first == string::npos ) continue;
	if ( comline[first] == '#' ) continue;
	if ( comline[first] == '!' ) continue;

	lin.clear();
	lin.str ( comline );
	token = "";

	string op;
	lin >> op;

	if ( s == END_OF_FILE )
	{
	    // First op of section.
	    //
	    if ( op != "layout" )
	    {
	        s = PAGE;
		init_page();
		current_list = & level[50];
		in_body = true;
	    }
	}

	if ( op == "layout" && s == END_OF_FILE )
	{
	    s = LAYOUT;

	    dout << endl << "Layout:" << endl;

	    long R, C;
	    double HEIGHT, WIDTH; 

	    if ( read_long ( "R", R, 1, 40 )
	         &&
		 read_long ( "C", C, 1, 20, false ) )
	    {
	        init_layout ( R, C );
		if ( read_length
		         ( "HEIGHT", HEIGHT,
			   1e-12, 1000 )
		     &&
		     read_length
			 ( "WIDTH", WIDTH,
			   1e-12, 1000, false ) )
		{
		    L_height = HEIGHT;
		    L_width = WIDTH;

		    read_margins ( L_margins );
		}
	    }
	    else
	    {
	        init_layout ( 1, 1 );
		continue;
	    }
	}
	else if ( op == "font" && s == LAYOUT )
	{
	    string NAME;
	    double SIZE;
	    const color * COLOR = black;
	    options OPT = NO_OPTIONS;
	    const char * FAMILY = "serif";
	    double SPACE = 1.15;

	    if ( ! read_name ( "NAME", NAME, false ) )
	        continue;
	    if ( ! read_length ( "SIZE", SIZE,
	                         3.0/72, 30, false ) )
	        continue;

	    read_color ( "COLOR", COLOR );
	    read_options ( "OPT", OPT, font_options );
	    read_family ( "FAMILY", FAMILY );
	    read_em ( "SPACE", SPACE, 1, 100 );

	    const font * f = make_font
	        ( NAME, SIZE, COLOR, OPT,
		  FAMILY, SPACE );
	    if ( debug ) print_font ( f );
	}
	else if ( op == "stroke" && s == LAYOUT )
	{
	    string NAME;
	    double WIDTH = -1;
	    const color * COLOR = black;
	    options OPT = NO_OPTIONS;

	    if ( ! read_name ( "NAME", NAME, false ) )
	        continue;

	    read_length ( "WIDTH", WIDTH, 0, 1 );
	    read_color ( "COLOR", COLOR );
	    read_options ( "OPT", OPT, stroke_options );

	    if ( WIDTH == -1 )
	        WIDTH = ( OPT & ( FILL_SOLID |
		                  FILL_CROSS |
				  FILL_RIGHT |
				  FILL_LEFT ) ?
		          0 : 1.0/72 );


	    const stroke * strk =
	        make_stroke ( NAME, WIDTH, COLOR, OPT );
	    if ( debug ) print_stroke ( strk );
	}
	else if ( op == "background" )
	{
	    const color * COLOR;
	    if ( ! read_color
	               ( "COLOR", COLOR, false ) )
	        continue;
	    if ( s == LAYOUT )
	        D_background = COLOR;
	    else
	        P_background = COLOR;
	}
	else if ( op == "scale" )
	{
	    double S;
	    if ( ! read_double
	            ( "S", S, 0.001, 1000, false ) )
	        continue;
	    if ( s == LAYOUT )
	        D_scale = S;
	    else
	        P_scale = S;
	}
	else if ( op == "margins" )
	{
	    if ( s == LAYOUT )
	        read_margins ( D_margins, false );
	    else
	        read_margins ( P_margins, false );
	}
	else if ( op == "bounds" )
	{
	    bounds b;
	    if ( ! read_double ( "LLX", b.ll.x,
			         - MAX_BODY_COORDINATE,
			         + MAX_BODY_COORDINATE,
			         false ) )
		continue;
	    if ( ! read_double ( "LLY", b.ll.y,
			         - MAX_BODY_COORDINATE,
			         + MAX_BODY_COORDINATE,
			         false ) )
		continue;
	    if ( ! read_double ( "URX", b.ur.x,
			         - MAX_BODY_COORDINATE,
			         + MAX_BODY_COORDINATE,
			         false ) )
		continue;
	    if ( ! read_double ( "URX", b.ur.y,
			         - MAX_BODY_COORDINATE,
			         + MAX_BODY_COORDINATE,
			         false ) )
		continue;
	    if ( s == LAYOUT )
	        D_bounds = b;
	    else
	        P_bounds = b;
	}
	else if ( op == "head" && s == PAGE )
	{
	    current_list = & head;
	    in_head_or_foot = true;
	    in_body = false;
	}
	else if ( op == "foot" && s == PAGE )
	{
	    current_list = & foot;
	    in_head_or_foot = true;
	    in_body = false;
	}
	else if ( op == "level" && s == PAGE )
	{
	    long N;
	    if ( ! read_long ( "N", N, 1, MAX_LEVEL ) )
	    {
	        if ( level_stack.empty() )
		    error ( "level stack is empty" );
		else
		{
		    N = level_stack.back();
		    current_list = & level[N];
		    level_stack.pop_back();
		}
	    }
	    else
	    {
	        level_stack.push_back
		    ( current_list - level );
		current_list = & level[N];
	    }
	    in_head_or_foot = false;
	    in_body = true;
	    dout << "current level is "
	         << current_list - level << endl;
	}
	else if ( op == "text" && s == PAGE )
	{
	    const font * FONT;
	    options OPT = NO_OPTIONS;
	    double X = 0, Y = 0;
	    string TEXT;

	    if ( ! read_font ( "FONT", FONT, false ) )
	        continue;
	    if ( in_body )
	    {
	        read_options
		    ( "OPT", OPT, text_options );
		if ( ! read_double
		           ( "X", X,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false ) )
		    continue;
		if ( ! read_double
		           ( "Y", Y,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false ) )
		    continue;
	    }
	    read_text ( "TEXT", TEXT );

	    text * t = new text;
	    attach ( t, 't' );
	    t->f = FONT;
	    t->o = OPT;
	    t->p = { X, Y };
	    t->t = TEXT;
	}
	else if ( op == "space" && in_head_or_foot )
	{
	    double SPACE;
	    if ( ! read_length ( "SPACE", SPACE,
	                         0, 100, false ) )
		continue;

	    space * sp = new space;
	    attach ( sp, 'S' );
	    sp->s = SPACE;
	}
	else if ( op == "start" && in_body )
	{
	    const stroke * STROKE;
	    double X, Y;
	    if ( ! read_stroke
	               ( "STROKE", STROKE, false )
		 ||
		 ! read_double
		       ( "X", X,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false )
		 ||
		 ! read_double
		       ( "Y", Y,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
	        continue;

	    start * st = new start;
	    attach ( st, 's', true );
	    st->s = STROKE;
	    st->p = { X, Y };
	}
	else if ( op == "line" && in_body )
	{
	    double X, Y;
	    if ( ! read_double
		       ( "X", X,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false )
		 ||
		 ! read_double
		       ( "Y", Y,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
	        continue;

	    line * l = new line;
	    if ( ! attach ( l, 'l', true, true ) )
	    {
	        delete l;
	        continue;
	    }
	    l->p = { X, Y };
	}
	else if ( op == "curve" && in_body )
	{
	    curve * c = new curve;
	    bool OK = true;
	    for ( int i = 0; i < 3; ++ i )
	    {
	        char Xname[3], Yname[3];
		sprintf ( Xname, "X%d", i + 1 );
		sprintf ( Yname, "Y%d", i + 1 );
	        if ( ! read_double
			   ( Xname, c->p[i].x,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false )
		     ||
		     ! read_double
			   ( Yname, c->p[i].y,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false ) )
		{
		    OK = false;
		    break;
		}
	    }
	    if ( ! OK )
	    {
	        delete c;
		continue;
	    }

	    if ( ! attach ( c, 'c', true, true ) )
	        delete c;
	}
	else if ( op == "end" && in_body )
	{
	    end * e = new end;
	    if ( ! attach ( e, 'e', false, true ) )
	        delete e;
	}
	else if ( op == "arc" && in_body )
	{
	    const stroke * STROKE = NULL;
	    double XC = 0, YC = 0, RX, RY,
	           A = 0, G1 = 0, G2 = 360;

	    if ( read_stroke ( "STROKE", STROKE ) )
	    {
		if ( ! read_double
			   ( "XC", XC,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false ) )
		    continue;
		if ( ! read_double
			   ( "YC", YC,
			     - MAX_BODY_COORDINATE,
			     + MAX_BODY_COORDINATE,
			     false ) )
		    continue;
		if ( ! read_double
			   ( "R", RX,
			     0, + MAX_BODY_COORDINATE,
			     false ) )
		    continue;
		if ( ! read_double
			   ( "RY", RY,
			     0,
			     + MAX_BODY_COORDINATE ) )
		    RY = RX;
		else
		if ( read_double
			 ( "A", A,
			   - 1000 * 360, + 1000 * 360 )
		     &&
		     read_double
			 ( "G1", G1,
			   - 1000 * 360, + 1000 * 360 )
		     &&
		     ! read_double
			 ( "G2", G2,
			   - 1000 * 360, + 1000 * 360,
			   false ) )
		    continue;
	    }
	    else // No STROKE
	    if ( ! read_double
		       ( "RX", RX,
			 0, + MAX_BODY_COORDINATE,
			 false )
		 ||
	         ! read_double
		       ( "RY", RY,
			 0, + MAX_BODY_COORDINATE,
			 false )
		 ||
	         ! read_double
		       ( "A", A,
		         - 1000 * 360, + 1000 * 360,
			 false )
		 ||
		 ! read_double
		       ( "G1", G1,
		         - 1000 * 360, + 1000 * 360,
			 false )
		 ||
		 ! read_double
		       ( "G2", G2,
		         - 1000 * 360, + 1000 * 360,
		         false ) )
		continue;

	    arc * a = new arc;
	    attach ( a, 'a',
	             STROKE == NULL,
		     STROKE == NULL );
	    a->s = STROKE;
	    a->c = { XC, YC };
	    a->r = { RX, RY };
	    a->a = A;
	    a->g1 = G1;
	    a->g2 = G2;
	}
	else if ( op == "rectangle" && in_body )
	{
	    const stroke * STROKE;
	    double XMIN, XMAX, YMIN, YMAX;

	    if ( ! read_stroke
	               ( "STROKE", STROKE, false ) )
		continue;
	    if ( ! read_double
	               ( "XMIN", XMIN,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "YMIN", YMIN,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "XMAX", XMAX,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "YMAX", YMAX,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;

	    if ( XMIN > XMAX )
	    {
	        error ( "XMIN > XMAX" );
		continue;
	    }
	    if ( YMIN > YMAX )
	    {
	        error ( "YMIN > YMAX" );
		continue;
	    }

	    rectangle * r = new rectangle;
	    attach ( r, 'r' );
	    r->s = STROKE;
	    r->p[0] = { XMIN, YMIN };
	    r->p[1] = { XMAX, YMIN };
	    r->p[2] = { XMAX, YMAX };
	    r->p[3] = { XMIN, YMAX };
	}
	else if ( op == "ellipse" && in_body )
	{
	    const stroke * STROKE;
	    double XMIN, XMAX, YMIN, YMAX;

	    if ( ! read_stroke
	               ( "STROKE", STROKE, false ) )
		continue;
	    if ( ! read_double
	               ( "XMIN", XMIN,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "YMIN", YMIN,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "XMAX", XMAX,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "YMAX", YMAX,
			 - MAX_BODY_COORDINATE,
			 + MAX_BODY_COORDINATE,
			 false ) )
		continue;

	    if ( XMIN > XMAX )
	    {
	        error ( "XMIN > XMAX" );
		continue;
	    }
	    if ( YMIN > YMAX )
	    {
	        error ( "YMIN > YMAX" );
		continue;
	    }
	    double XC = ( XMAX + XMIN ) / 2;
	    double YC = ( YMAX + YMIN ) / 2;
	    double RX = ( XMAX - XMIN ) / 2;
	    double RY = ( YMAX - YMIN ) / 2;

	    arc * a = new arc;
	    attach ( a, 'a' );
	    a->s = STROKE;
	    a->c = { XC, YC };
	    a->r = { RX, RY };
	    a->a = 0;
	    a->g1 = 0;
	    a->g2 = 360;
	}
	else if ( op == "*" )
	{
	    if (    s == PAGE
	         && (* current_list) != NULL
	         && (* current_list)->continued )
	    {
		error ( "missing `end' inserted" );
		end * e = new end;
		attach ( e, 'e', false, true );
	     }
	    break;
	}
	else
	{
	    const char * place =
	        ( s != PAGE ? "layout section" :
	          current_list == & head ? "page head" :
	          current_list == & foot ? "page foot" :
		                  "page body" );
	    error ( "cannot understand %s"
	            " in %s; line ignored",
		    op.c_str(), place );
	    continue;
	}
	check_extra();
    }

    return s;
}

// Page Draw Routines
// ---- ---- --------

// Compute height of head or foot.
//
double compute_height ( command * list )
{
    double h = 0;
    command * current = list;
    if ( current == NULL ) return 0;
    do
    {
        current = current->next;
	if ( current->c == 't' )
	{
	    text * t = (text *) current;
	    h += t->f->size * t->f->space;
	}
	else if ( current->c == 'S' )
	{
	    space * s = (space *) current;
	    h += s->s;
	}
	else
	    assert ( ! "height bad command" );

    } while ( current != list );
    return h;
}

// Compute the bounding box of all the body points
// in the level lists.  Text, wide lines, and exotic
// curves may go outside the box.  For arcs, the
// corners of the bounding rectangle are used.
//
// Returns number of points checked.
//
int compute_bounding_box ( void )
{
    double & xmin = P_bounds.ll.x;
    double & ymin = P_bounds.ll.y;
    double & xmax = P_bounds.ur.x;
    double & ymax = P_bounds.ur.y;
    xmin = ymin = DBL_MAX;
    xmax = ymax = DBL_MIN;
    int count = 0;

#   define BOUND(v) \
	if ( xmin > v.x ) xmin = v.x; \
	if ( xmax < v.x ) xmax = v.x; \
	if ( ymin > v.y ) ymin = v.y; \
	if ( ymax < v.y ) ymax = v.y; \
	++ count

    for ( int i = 1; i <= MAX_LEVEL; ++ i )
    {
	command * current = level[i];
	if ( current != NULL ) do
	{
	    current = current->next;
	    switch ( current->c )
	    {
	    case 't':
	    {
		text * t = (text *) current;
		BOUND ( t->p );
		break;
	    }
	    case 's':
	    {
		start * s = (start *) current;
		BOUND ( s->p );
		break;
	    }
	    case 'l':
	    {
		line * l = (line *) current;
		BOUND ( l->p );
		break;
	    }
	    case 'c':
	    {
		curve * c = (curve *) current;
		BOUND ( c->p[0] );
		BOUND ( c->p[1] );
		BOUND ( c->p[2] );
		break;
	    }
	    case 'e':
		break;
	    case 'a':
	    {
		arc * a = (arc *) current;
		vector d[4] = {
		    { - a->r.x, - a->r.y },
		    { + a->r.x, - a->r.y },
		    { + a->r.x, + a->r.y },
		    { - a->r.x, + a->r.y } };
		if ( a->s != NULL )
		    for ( int j = 0; j < 4; ++ j )
		    {
			vector p = a->c
			         + ( d[j]^(a->a) );
			    // ^ has lower precedence
			    // than +
			BOUND ( p );
		    }
		else
		{
		    // TBD
		}
		break;
	    }
	    case 'r':
	    {
		rectangle * r = (rectangle *) current;
		BOUND ( r->p[0] );
		BOUND ( r->p[1] );
		BOUND ( r->p[2] );
		BOUND ( r->p[3] );
		break;
	    }
	    default:
		assert ( ! "bounding bad command" );
	    }
	} while ( current != level[i] );
    }
#   undef BOUND

    return count;
}


// Drawing data.
//
cairo_t * context;

// Print current matrix for debugging.
//
void print_matrix ( cairo_t * context,
                    const char * name = NULL )
{
    cairo_matrix_t matrix;

    if ( name != NULL )
        cout << endl << name << ":" << endl;

    cairo_get_matrix ( context, & matrix );
    vector x = { matrix.xx, matrix.yx };
    vector y = { matrix.xy, matrix.yy };
    vector t = { matrix.x0, matrix.y0 };
    cout << x << "*x + " << y << "*y + " << t << endl;
}

// Print current path for debugging.
//
void print_path ( cairo_t * context,
                  const char * name = NULL )
{
    cairo_path_t * path;
    cairo_path_data_t * data;

    if ( name != NULL )
        cout << endl << name << ":" << endl;

    path = cairo_copy_path ( context );
    for ( int i = 0; i < path->num_data; )
    {
        data = & path->data[i];
	i += data->header.length;
	switch ( data->header.type )
	{
	case CAIRO_PATH_MOVE_TO:
	{
	    vector p = { data[1].point.x,
	                 data[1].point.y };
	    cout << "move to " << p << endl;
	    break;
	}
	case CAIRO_PATH_LINE_TO:
	{
	    vector p = { data[1].point.x,
	                 data[1].point.y };
	    cout << "line to " << p << endl;
	    break;
	}
	case CAIRO_PATH_CURVE_TO:
	{
	    vector p[3] = {
	        { data[1].point.x, data[1].point.y },
	        { data[2].point.x, data[2].point.y },
	        { data[3].point.x, data[3].point.y } };
	    cout << "curve to"
	         << " " << p[0]
	         << " " << p[1]
	         << " " << p[2]
		 << endl;
	    break;
	}
	case CAIRO_PATH_CLOSE_PATH:
	{
	    cout << "close" << endl;
	    break;
	}
	default:
	    cout << "unknown path element type "
	         << data->header.type << endl;
        }
    }
    cairo_path_destroy ( path );
    cout << "Matrix: ";
    print_matrix ( context );
}

// These units are in inches with y increasing from
// top to bottom.
//
double head_left, head_top, head_height, head_width,
       body_top, body_height, body_left, body_width,
       foot_top, foot_height, foot_left, foot_width;

void draw_head_or_foot
    ( command * list, const char * name,
      double left, double top, double width )
{

    if ( debug && list != NULL )
    {
        cout << endl << name << ":" << endl;
	print_commands ( list );
    }

    double center = 72 * ( left + width / 2 );

    command * current = list;
    if ( current != NULL ) do
    {
        current = current->next;
	switch ( current->c )
	{
	case 'S':
	{
	    space * s = (space *) current;
	    top += s->s;
	    break;
	}
	case 't':
	{
	    text * t = (text *) current;
	    const font * f = t->f;
	    top += f->size * f->space; 
	    const color * c = f->c;

	    string tx[3];
	    size_t pos1 = t->t.find_first_of ( '\\' );
	    size_t pos2 = t->t.find_last_of ( '\\' );
	    if ( pos1 == string::npos )
	        tx[1] = t->t;
	    else
	    {
	        tx[0] = t->t.substr ( 0, pos1 );
		tx[2] = t->t.substr ( pos2 + 1 );
		if ( pos1 != pos2 )
		    tx[1] = t->t.substr
		        ( pos1 + 1, pos2 - pos1 - 1 );
	    }

	    cairo_set_source_rgb
	        ( context, c->red, c->green, c->blue );
		           
	    cairo_select_font_face
	        ( context, f->cairo_family,
		           f->cairo_slant,
			   f->cairo_weight );
	    cairo_set_font_size
	        ( context, 72 * f->size );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    for ( int i = 0; i < 3; ++ i )
	    {
	        if ( tx[i] == "" ) continue;
		cairo_text_extents_t te;
		cairo_text_extents
		    ( context, tx[i].c_str(), & te );
		assert (    cairo_status ( context )
			 == CAIRO_STATUS_SUCCESS );
		cairo_move_to
		    ( context, 
		      i == 0 ? 72 * left :
		      i == 1 ? center - te.width/2 :
		               72 * ( left + width )
			       - te.width,
		      72 * top );
		cairo_show_text
		    ( context, tx[i].c_str() );
		assert (    cairo_status ( context )
			 == CAIRO_STATUS_SUCCESS );
	    }
	    break;
	}
	default:
	    assert ( ! "bad draw head/foot command" );
	}

    } while ( current != list );
}

// (xleft,ybottom) is the lower left point of the body
// in body coordinates.
//
double xleft, ybottom;

// (left,bottom) is the lower left point of the body
// in physical coordinates.
//
// The unit of these numbers is pt (1/72 inch) with y
// increasing from top to bottom.
//
double xscale, yscale, left, bottom;

// Convert vector point p to x, y cairo coordinate
// pair.  p is in body coordinates with y increasing
// from bottom to top.  Cairo coordinates are in
// pt units with y increasing from top to bottom.
//
# define CONVERT(p) \
    left + ((p).x - xleft) * xscale, \
    bottom - ((p).y - ybottom) * yscale

inline void apply_stroke ( const stroke * s )
{
    cairo_set_line_width
	( context, 72 * s->width );
    const color * c = s->c;
    cairo_set_source_rgb
	( context, c->red, c->green, c->blue );
    if ( s->o & CLOSED )
	cairo_close_path ( context );
    if ( s->o & ( FILL_SOLID |
		  FILL_CROSS |
		  FILL_RIGHT |
		  FILL_LEFT ) )
	cairo_fill ( context );
    else
	cairo_stroke ( context );
}

void draw_level ( int i )
{
    if ( debug && level[i] != NULL )
    {
        cout << endl
	     << "Level " << i << ":"
	     << endl;
	print_commands ( level[i] );
    }

    command * current = level[i];
    const stroke * s;
    if ( current != NULL ) do
    {
        current = current->next;
	switch ( current->c )
	{
	case 't':
	{
	    text * t = (text *) current;
	    const font * f = t->f;
	    const color * c = f->c;

	    cairo_set_source_rgb
	        ( context, c->red, c->green, c->blue );
		           
	    cairo_select_font_face
	        ( context, f->cairo_family,
		           f->cairo_slant,
			   f->cairo_weight );
	    cairo_set_font_size
	        ( context, 72 * f->size );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    cairo_text_extents_t te;
	    cairo_text_extents
	        ( context, t->t.c_str(), & te );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );
	    vector pc = { CONVERT ( t->p ) };
	    cairo_move_to
		( context, 
		  pc.x - te.width/2,
		  pc.y + 0.4 * te.height ); 
	    cairo_show_text ( context, t->t.c_str() );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );
	    break;
	}
	case 's':
	{
	    start * st = (start *) current;
	    s = st->s;
	    cairo_new_path ( context );
	    cairo_move_to
	        ( context, CONVERT ( st->p ) );
	    break;
	}
	case 'l':
	{
	    line * l = (line *) current;
	    cairo_line_to
	        ( context, CONVERT ( l->p ) );
	    break;
	}
	case 'c':
	{
	    curve * c = (curve *) current;
	    cairo_curve_to
	        ( context, CONVERT ( c->p[0] ),
		           CONVERT ( c->p[1] ),
			   CONVERT ( c->p[2] ) ); 
	    break;
	}
	case 'e':
	{
	    apply_stroke ( s );
	    break;
	}
	case 'a':
	{
	    arc * a = (arc *) current;
	        // Because cairo y increases from top
		// to bottom, cairo angles are negatives
		// of our angles.

	    cairo_matrix_t matrix;
	    cairo_get_matrix ( context, & matrix );

	    if ( a->s == NULL )
	    {
	        vector p1;
		cairo_get_current_point
		    ( context, & p1.x, & p1.y );
		vector p2 = { 1, 0 };
		p2 =
		    { fabs ( xscale ) * a->r.x * p2.x,
		      fabs ( yscale ) * a->r.y * p2.y };
		p2 = p2 ^ a->g1;

		cairo_translate
		    ( context,
		      p1.x - p2.x, p1.y - p2.y );
	    }
	    else
	    {
		cairo_new_path ( context );
		cairo_translate
		    ( context, CONVERT ( a->c ) );
	    }
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );
	    cairo_rotate
	        ( context, - M_PI * a->a / 180 );
	    cairo_scale
	        ( context,
		  fabs ( xscale ) * a->r.x,
		  fabs ( yscale ) * a->r.y );
	    if ( a->g1 < a->g2 )
		cairo_arc_negative
		    ( context, 0, 0, 1,
		      - M_PI * a->g1 / 180,
		      - M_PI * a->g2 / 180 );
	    else if ( a->g1 > a->g2 )
		cairo_arc
		    ( context, 0, 0, 1,
		      - M_PI * a->g1 / 180,
		      - M_PI * a->g2 / 180 );
	    cairo_set_matrix ( context, & matrix );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );
	    if ( a->s != NULL ) apply_stroke ( a->s );
	    break;
	}
	case 'r':
	{
	    rectangle * r = (rectangle *) current;
	    break;
	}
	default:
	    assert ( ! "bad draw level command" );
	}

    } while ( current != level[i] );
}

void draw_page ( double P_left, double P_top )
{
    if ( P_background != NULL )
    {
        const color * c = P_background;
	cairo_set_source_rgb
	    ( context, c->red, c->green, c->blue );
	cairo_rectangle
	    ( context,
	      72 * P_left, 72 * P_top,
	      72 * P_width, 72 * P_height );
	cairo_fill ( context );
    }
    head_top = P_top + P_margins.top / 2;
    head_height = compute_height ( head );
    body_top = P_top + P_margins.top + head_height;

    foot_height = compute_height ( foot );
    foot_top = P_top + P_height - foot_height
             - P_margins.bottom / 2;
    body_height = P_top + P_height - foot_height
                - P_margins.bottom
		- body_top;

    head_left = P_left;
    head_width = P_width;
    foot_left = P_left;
    foot_width = P_width;
    body_left = P_left + P_margins.left;
    body_width = P_width - P_margins.left
                         - P_margins.right;

    // First compute xscale and yscale in inch
    // coordinates.
    //
    if ( isnan ( P_bounds.ll.x ) )
    {
        int count = compute_bounding_box();
	if ( count < 4 )
	    fatal ( "only %d points available to"
	            " automatically compute bounding"
		    " box (need >= 4)", count );
    }

    double dx = P_bounds.ur.x - P_bounds.ll.x;
    double dy = P_bounds.ur.y - P_bounds.ll.y;
    if ( dx == 0 ) dx = 1;
    if ( dy == 0 ) dy = 1;
    xscale = body_width / dx;
    yscale = body_height / dy;

    if ( ! isnan ( P_scale ) )
    {
	double ratio = fabs ( yscale )
	             / fabs ( xscale );
	if ( ratio < P_scale )
	{
	    // Must decrease magnitude of xscale.
	    //
	    xscale *= ratio / P_scale;
	    double new_body_width = dx * xscale;
	    body_left +=
		( body_width - new_body_width ) / 2;
	    body_width = new_body_width;
	}
	else if ( ratio > P_scale )
	{
	    // Must decrease magnitude of yscale.
	    //
	    yscale *= P_scale / ratio;
	    double new_body_height = dy * yscale;
	    body_top +=
		  ( body_height - new_body_height )
		/ 2;
	    body_height = new_body_height;
	}
    }


    left = body_left;
    bottom = body_top + body_height;
    xleft = P_bounds.ll.x;
    ybottom = P_bounds.ll.y;

    left *= 72;
    bottom *= 72;
    xscale *= 72;
    yscale *= 72;

    if ( debug )
    {
        vector ll = { body_left,
	              body_top + body_height };
	vector ur = { body_left + body_width,
	              body_top };
	ll = 72 * ll;
	ur = 72 * ur;

	vector cll = { CONVERT ( P_bounds.ll ) };
	vector cur = { CONVERT ( P_bounds.ur ) };

	cout << endl << "Page:" << endl;
	cout << "BOUNDS:    " << P_bounds.ll << " - "
	     << P_bounds.ur << endl;
	cout << "PHYSICAL:  " << ll << " - " << ur
	     << endl;
	cout << "CONVERTED: " << cll << " - " << cur
	     << endl;
    }

    draw_head_or_foot
        ( head, "Head",
	  head_left, head_top, head_width );
    draw_head_or_foot
        ( foot, "Foot",
	  foot_left, foot_top, foot_width );
    for ( int i = 1; i <= MAX_LEVEL; ++ i )
        draw_level ( i );
}


// Obsolete Code
// -------- ----

#ifdef OBSOLETE

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
	switch ( c->c )
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
#endif

// Main Program
// ---- -------

// cairo_write_func_t to write data to cout.
//
unsigned long bytes = 0;
cairo_status_t write_to_cout
    ( void * closure,
      const unsigned char * data, unsigned int length )
{
    bytes += length;
    if ( ! debug )
    {
	cout.write ( (const char *) data, length );
	if ( ! cout )
	    return CAIRO_STATUS_WRITE_ERROR;
    }
    return CAIRO_STATUS_SUCCESS;
}

// Main program.
//
int main ( int argc, char ** argv )
{
    init_colors();

    cairo_surface_t * page = NULL;

    // Process options.

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
	else
	{
	    cout << "Cannot understand -" << name
	         << endl << endl;
	    exit (1);
	}

	++ argv, -- argc;
    }

    // Exit with error status unless there is at most
    // one program argument left.

    if ( argc > 2 )
    {
	cout << "Wrong number of arguments."
	     << endl;
	exit (1);
    }

    // Open file.
    //
    istream * in = & cin;
    ifstream fin;
    const char * file = NULL;
    if ( argc == 2 )
    {
        file = argv[1];
	fin.open ( file );
	if ( ! fin )
	{
	    cout << "Cannot open " << file << endl;
	    exit ( 1 );
	}
	in = & fin;
    }

    init_layout ( 1, 1 );
    if ( debug )
    {
	cout << endl << "Fonts:" << endl;
	for ( font_it it = font_dict.begin();
	      it != font_dict.end(); ++ it )
	    print_font ( it->second );

	cout << endl << "Strokes:" << endl;
	for ( stroke_it it = stroke_dict.begin();
	      it != stroke_dict.end(); ++ it )
	    print_stroke ( it->second );
    }

    page = cairo_pdf_surface_create_for_stream
		( write_to_cout, NULL,
		  72 * L_width, 72 * L_height );
    context = cairo_create ( page );

    section s = LAYOUT;
    while ( s == LAYOUT )
    {
	cairo_pdf_surface_set_size
	    ( page, 72 * L_width, 72 * L_height ); 
	assert (    cairo_status ( context )
		 == CAIRO_STATUS_SUCCESS );

	double left = L_margins.left;
	double top = L_margins.top;
	double width = ( L_width - L_margins.left
	                         - L_margins.right )
		     / C;
	double height = ( L_height - L_margins.top
	                           - L_margins.bottom )
		      / R;
	int curR = 0, curC = 0;
	while ( true )
	{
	    s = read_section ( * in );
	    if ( s != PAGE ) break;
	    draw_page ( left + curC * width,
	                top + curR * height );
	    if ( ++ curC >= C )
	    {
		curC = 0;
		if ( ++ curR >= R )
		{
		    curR = 0;
		    cairo_show_page ( context );
		}
	    }
	}

	if ( curR != 0 || curC != 0 )
	    cairo_show_page ( context );
    }

    cairo_destroy ( context );
    cairo_surface_destroy ( page );

    dout << bytes << " bytes of pdf" << endl;

    // Return from main function without error.

    return 0;
}
