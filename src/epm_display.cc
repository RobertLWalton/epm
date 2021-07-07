// Display Text, Points, Lines, Arcs, Etc.
//
// File:	epm_display.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Wed Jul  7 17:39:55 EDT 2021
//
// The authors have placed this program in the public
// domain; they make no warranty and accept no liability
// for this program.

// Compile with:
//
//	g++ [-O3] [-g] \
//	    -I /usr/include/cairo \
//	    -o epm_display \
//	    epm_display.cc -lcairo

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
#include <math.h>  // Needed to force isnan to be in the
		   // global name space in CentOS 8 so
		   // we don't have to use std::isnan
		   // which will not work in CentOS 7.
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
using std::to_string;
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
typedef vector point;

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
    MIDDLE_ARROW	= 1 << 5,
    END_ARROW		= 1 << 6,
    FILL_SOLID		= 1 << 7,
    FILL_DOTTED		= 1 << 8,
    FILL_HORIZONTAL	= 1 << 9,
    FILL_VERTICAL	= 1 << 10,
    EXTEND_FOREWARD     = 1 << 11,
    EXTEND_BACKWARD     = 1 << 12,

    // Text Options:
    //
    TOP 		= 1 << 13,
    BOTTOM		= 1 << 14,
    LEFT		= 1 << 15,
    RIGHT		= 1 << 16,
    BOX_WHITE		= 1 << 17,
    CIRCLE_WHITE	= 1 << 18,

    // Text and Stroke Options
    //
    OUTLINE		= 1 << 19
};
const char * optchar = "bi" ".-cmesdhvfb" "tblrxco";
const options ARROW_OPTIONS = (options)
    ( MIDDLE_ARROW + END_ARROW );
const options FILL_OPTIONS = (options)
    ( FILL_SOLID + FILL_DOTTED + FILL_HORIZONTAL
                               + FILL_VERTICAL );
const options EXTEND_OPTIONS = (options)
    ( EXTEND_FOREWARD + EXTEND_BACKWARD );

const options DOTTED_DASHED_CONFLICT = (options)
    ( DOTTED + DASHED );
const options FILL_CONFLICT = (options)
    ( FILL_SOLID + FILL_DOTTED + FILL_HORIZONTAL
                 + FILL_VERTICAL );
const options EXTEND_CONFLICT = (options)
    ( EXTEND_FOREWARD + EXTEND_BACKWARD );
const options TOP_BOTTOM_CONFLICT = (options)
    ( TOP + BOTTOM );
const options LEFT_RIGHT_CONFLICT = (options)
    ( LEFT + RIGHT );
const options BOX_CIRCLE_CONFLICT = (options)
    ( BOX_WHITE + CIRCLE_WHITE );

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

struct color
{
    const char * name;
    unsigned value;		// HTML value
    double red, green, blue;	// cairo value
    cairo_pattern_t * dotted;
        // source with dots of this color
    cairo_pattern_t * horizontal;
        // source with horizontal stripes of this color
    cairo_pattern_t * vertical;
        // source with vertical stripes of this color
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
	c->dotted = NULL;
    }
}

void set_fill
	( cairo_t * context, const color * c,
	                     options o )
{
    cairo_pattern_t ** p = ( cairo_pattern_t ** )
        ( o & FILL_DOTTED     ? & c->dotted :
          o & FILL_HORIZONTAL ? & c->horizontal :
          o & FILL_VERTICAL   ? & c->vertical :
	  NULL );
    assert ( p != NULL );

    if ( * p == NULL )
    {
	// Banding seems unavoidable on displays but
	// seems to be no problem on printers.  No
	// effect was observed in experiments to adjust
	// sizes to an integral number of pixels.
	//
	cairo_surface_t * s =
	    cairo_pdf_surface_create ( NULL, 2, 2 );
	cairo_t * temp = cairo_create ( s );
	cairo_set_source_rgb
	    ( temp, c->red, c->green, c->blue );
	if ( o & FILL_DOTTED )
	    cairo_rectangle ( temp, 0, 0, 1, 1 );
	else if ( o & FILL_HORIZONTAL )
	    cairo_rectangle ( temp, 0, 0, 2, 1 );
	else
	    cairo_rectangle ( temp, 0, 0, 1, 2 );
	cairo_fill ( temp );
	assert (    cairo_status ( temp )
		 == CAIRO_STATUS_SUCCESS );
	cairo_destroy ( temp );
        * p = cairo_pattern_create_for_surface ( s );
	cairo_pattern_set_extend
	    ( * p, CAIRO_EXTEND_REPEAT );
	cairo_surface_destroy ( s );
	assert (    cairo_pattern_status ( * p )
		 == CAIRO_STATUS_SUCCESS );
    }
    cairo_set_source ( context, * p );
    assert (    cairo_status ( context )
	     == CAIRO_STATUS_SUCCESS );
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
    point ll, ur;
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

const char * const documentation[] = { "\n"
"epm_display [-debug] [file]\n"
"\n"
"    This program displays line drawings defined\n"
"    in the given file or standard input.  The file\n"
"    consists of sections each consisting of command\n"
"    lines followed by a line containing just `*'.\n"
"\n"
"    There are two kinds of sections:\n"
"\n"
"        * layout sections, which describe physical\n"
"          pages (e.g., 8.5in x 11.0in pages), and\n"
"          provide context values for logical pages\n"
"          (e.g., fonts, default background color)\n"
"\n"
"        * page sections, which descibe logical\n"
"          pages (e.g., as in 2 logical pages per\n"
"          physical page).\n"
"\n"
"    A physical page is divided into two parts: a\n"
"    title and beneath that a grid of logical pages.\n"
"    Some of the layout command lines produce text\n"
"    and space in the title.  These are saved in a\n"
"    list and used to output a title for each physi-\n"
"    cal page.  The height of the title can be com-\n"
"    puted from this list.\n"
"\n"
"    A logical page is divided into three parts: a\n"
"    head, a body, and a foot, in that order from\n"
"    top to bottom.  (But not necessarily in that\n"
"    order in the input).\n"
"\n"
"    The page section command lines that draw are\n"
"    saved in lists.  There is one list for the\n"
"    head and one list for the foot.  The height of\n"
"    the head and foot are can be computed from\n"
"    these lists.  The remaining page height is\n"
"    assigned to the body.\n"
"\n"
"    There are as many as 100 lists for the body,\n"
"    numbered 1 through 100.  These lists are execu-\n"
"    ted in the order 1, 2, ..., 100, with each over-\n"
"    laying the output of the previous lists.  So,\n"
"    for example, a white bounding box for some text\n"
"    can overlay a line that the text describes.\n"
"\n"
"    The head and foot commands output text lines\n"
"    and extra space between these lines.  The text\n"
"    lines contain up to three parts: one left\n"
"    aligned, one centered, and one right aligned.\n"
"    The title commands in the layout section are\n"
"    similar but affect the physical page title\n"
"    instead.  In a title text command, `###' is\n"
"    replaced by the current physical page number.\n"
"\n"
"    The body commands output text, lines, curves,\n"
"    and arcs.\n"
"\n"
"    For body commands, some numbers have units and\n"
"    some do not.  Body coordinates have no units\n"
"    and can be scaled automatically so the bounding\n"
"    box of all points with such coordinates fits\n"
"    in the area between the head and foot.  The\n"
"    ratio of X and Y scales, which with automatic\n"
"    scaling could be anything, can be forced to a\n"
"    particular value, such as 1.\n"
"\n"
"    Numbers that represent lengths can have the `pt'\n"
"    suffix meaning `points' (1/72 inch) or the `in'\n"
"    suffix meaning `inches'.   Some numbers, such as\n"
"    those used to determine text line spacing, have\n"
"    `em' units.  One em is the font size (specifi-\n"
"    cally the font height) at the time the number\n"
"    used.\n"
"\n"
"    Body coordinates have no units and are"
                                      " associated\n"
"    with the X or Y axes of the body (body coordi-\n"
"    nates are not permitted in the head or foot).\n"
"    The X-axis goes from left to right, and the Y-\n"
"    axis goes from bottom to top.   Angles are\n"
"    counter-clockwise from the positive X-axis.\n"
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
"    The -debug option can be used to output the data\n"
"    used to draw to the standard error.\n"
"\n"
"    The commands are described below.  Upper case\n"
"    identifiers in a command designate parameters.\n"
"\n"
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
"      layout [R C [WIDTH HEIGHT]]\n"
"      layout R C WIDTH HEIGHT ALL\n"
"      layout R C WIDTH HEIGHT VERTICAL HORIZONAL\n"
"      layout R C WIDTH HEIGHT TOP RIGHT BOTTOM LEFT\n"
"        Must be first command of a layout section.\n"
"\n"
"        May cause multiple logical pages to be put\n"
"        on each physical page, if R != 1 or C != 1.\n"
"        There are R rows of C columns each of\n"
"        logical pages per physical page.  Defaults\n"
"        are R = 1, C = 1.\n"
"\n"
"        WIDTH and HEIGHT are the physical page\n"
"        height and width, and default to 11.0in and\n"
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
"        OPT is some of:\n"
"            .   dotted line\n"
"            -   dashed line\n"
"                 (. - conflict; if neither line\n"
"                  is solid)\n"
"            c   close path\n"
"                 (implied by s d h v)\n"
"            m   arrow head in middle of segment\n"
"            e   arrow head at end of segment\n"
"            s   fill with solid color\n"
"            d   fill with dots\n"
"            h   fill with horizontal bars\n"
"            v   fill with vertical bars\n"
"                 (s d h v conflict)\n"
"            f   extend infinite line forward\n"
"            b   extend infinite line backward\n"
"                 (f b conflict; if neither line\n"
"                  extends in both directions)\n"
"\n"
"      background COLOR\n"
"        Sets the default background color for each\n"
"        logical page.  Default is `white'.\n"
"\n"
"      margins ALL\n"
"      margins VERTICAL HORIZONTAL\n"
"      margins TOP RIGHT BOTTOM LEFT\n"
"        Sets the default margins for the body of\n"
"        each logical page.  Margins are lengths.\n"
"\n"
"      bounds LLX LLY URX URY\n"
"        Sets the default bounding points of the body\n"
"        of each logical page.  The body coordinates\n"
"        of the lower left corner of the body are\n"
"        (LLX,LLY), and the body coordinates of the\n"
"        upper right corner are (URX,URY).\n"
"\n"
"        If this is not set for a logical page, the\n"
"        bounds are automatically computed, but this\n"
"        does not always give good results.  If the\n"
"        bounds are automatically computed, URX and\n"
"        URY will be greater than LLX and LLY respec-\n"
"        tively.  This need not be the case if the\n"
"        bounds are given by a `bounds' command.\n"
"\n"
"      scale S\n"
"        Sets the default scale S for each logical\n"
"        page body.  This is the ratio of Y length in\n"
"        inches per unit of Y body coordinate to X \n"
"        length in inches per unit of X body coordi-\n"
"        nate.  Must be > 0.  Frequently S = 1 is\n"
"        desired.\n"
"\n"
"        If this is set, the body will either be\n"
"        made narrower or shorter to accommodate the\n"
"        given value of S.  If this is set to NAN,\n"
"        the default, the body will be the entire\n"
"        space between the head or foot and S will\n"
"        be set to whatever this implies.\n"
"\n"
"    Also see Title, Head, and Foot Commands below.\n"
"\n"
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
"          Start or continue the head list.  If the\n"
"          previous list is a level list, push it\n"
"          into the level stack.\n"
"\n"
"        foot\n"
"          Start or continue the foot list.  If the\n"
"          previous list is a level list, push it\n"
"          into the level stack.\n"
"\n"
"        level N\n"
"          Start or continue list n, where"
                                " 1 <= n <= 100.\n"
"          If the previous list is level list, push\n"
"          it into the level stack.\n"
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
"    Title, Head, and Foot Commands:\n"
"    ------ ----- --- ---- --------\n"
"\n"
"        These commands can only appear in a layout\n"
"        section or on a head or foot list.  In the\n"
"        layout section they describe the physical\n"
"        page title.\n"
"\n"
"        text FONT TEXT1\\TEXT2\\TEXT3\n"
"        text FONT TEXT1\\TEXT3\n"
"        text FONT TEXT2\n"
"           Display text as a line.  TEXT1 is left\n"
"           adjusted; TEXT2 is centered; TEXT3 is\n"
"           right adjusted.  FONT is a font name.\n"
"           In the layout section, `###' in the TEXT\n"
"           is replaced by the current page number\n"
"           whenever the command is used.\n"
"\n"
"        space SPACE\n"
"           Insert whitespace of SPACE height, which\n"
"           must be a length.\n"
"\n"
"\n"
"    Logical Page Commands:\n"
"    ------- ---- --------\n"
"\n"
"      The below commands can only appear in a page\n"
"      section.  The `background', `margins',\n"
"      `scale', and `bounds' commands described above\n"
"      can also appear in a page section, and when\n"
"      they do appear, they apply to the whole"
                                          " logical\n"
"      page, and not to any particular part of the\n"
"      logical page.\n"
"\n"
"      text FONT [COLOR] [OPT] X Y TEXT\\...\n"
"        Display text at point (X,Y) which must\n"
"        be in body coordinates.\n"
"        FONT is font name.\n"
"        COLOR, if given, overrides COLOR of FONT.\n"
"        OPT are some of:\n"
"          t  display about 0.5em above Y\n"
"          b  display about 0.5em below Y\n"
"             If neither option, vertically\n"
"             center on Y.\n"
"                (t b conflict)\n"
"          l  display about 0.5em to left of X\n"
"          r  display about 0.5em to right of X\n"
"             If neither option, horizontally\n"
"             center on X.\n"
"                (l r conflict)\n"
"          x  make the bounding box of the text\n"
"             white\n"
"          c  make the bounding circle of the text\n"
"             white\n"
"                (x c conflict)\n"
"          o  outline the bounding box or circle\n"
"             with a black line of width 1pt\n"
"\n"
"        The TEXT is broken into lines separated\n"
"        by backslashes (\\).  Lines are centered\n"
"        if there is a bounding box or circle.\n"
"        Otherwise with `l' lines are right"
                        "adjusted;\n"
"        with `r' they are left adjusted; and with\n"
"        neither they are centered.\n"
"\n"
"      start STROKE [COLOR] [OPT] X Y\n"
"        Begin a path at (X,Y) which must be in\n"
"        body coordinates.  COLOR and OPT, if given,\n"
"        override the COLOR and OPTions of STROKE.\n"
"        Infinite line options are ignored.\n"
"\n"
"      line X Y\n"
"        Continue path by straight line to (X,Y)\n"
"        which must be in body coordinates.\n"
"\n"
"      curve X1 Y1 X2 Y2 X3 Y3\n"
"        Continue path by a cubic Bezier curve to\n"
"        (X3,Y3) with control points (X1,Y1) and\n"
"        (X2,Y2).  All points must be in body coordi-\n"
"        nates.  If path previously ended at (X0,Y0),\n"
"        the curve will be tangent to (X0,Y0)-(X1,Y1)\n"
"        at (X0,Y0), and will be tangent to (X2,Y2)-\n"
"        (X3,Y3) at (X3,Y3).\n"
"\n"
"      end\n"
"        Ends the current path.  This is implied by\n"
"        any command at the same level that does not\n"
"        continue the path (i.e., any command but\n"
"        `line', `curve', or (un-centered) `arc').\n"
"\n"
"        If the path stroke has a fill or close\n"
"        option, this command draws a line from the\n"
"        end of the path to its beginning, in order\n"
"        to close the path.\n"
"\n"
"      arc STROKE [COLOR] [OPT] XC YC RX RY"
                                   " [A [G1 G2]]\n"
"      arc STROKE [COLOR] [OPT] XC YC R\n"
"        Draw an elliptical arc of given STROKE type.\n"
"        COLOR and OPT, if given, override the COLOR\n"
"        and OPTions of STROKE.  Arrow and infinite\n"
"        line options are ignored.\n"
"\n"
"        First the a circular arc of unit radius is\n"
"        drawn with center (0,0).  The begin point\n"
"        is at angle G1 and the end point is at\n"
"        angle G2.  If G2 > G1 the arc goes counter-\n"
"        clockwise; if G2 < G1 the arc goes clock-\n"
"        wise.  Any multiple of 360 added to G1 or G2\n"
"        does not affect the points, but may affect\n"
"        the arc direction.  Angles are measured\n"
"        counter-clockwise from the positive X-axis.\n"
"\n"
"        Then transformations are applied.  First\n"
"        the X and Y axes are scaled by RX and RY;\n"
"        second the whole is rotated counter-clock-\n"
"        wise by A degrees, and lastly the whole is\n"
"        translated moving (0,0) to (XC,YC).\n"
"\n"
"        If R is given, RX = RY = R and the arc is a\n"
"        complete circle.  Otherwise the arc is part\n"
"        of an ellipse, A defaults to 0, G1 defaults\n"
"        to 0, and G2 defaults to 360.\n"
"\n"
"        All numbers are either in body coordinates\n"
"        or are angles in degrees, except that R may\n"
"        have units (e.g., pt).\n"
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
"      rectangle STROKE [COLOR] [OPT] XC YC"
				    " WIDTH HEIGHT\n"
"        Draw a rectangle of given STROKE type.\n"
"        COLOR and OPT, if given, override the COLOR\n"
"        and OPTions of STROKE.  The rectangle has\n"
"        center (XC,YC) and given WIDTH and HEIGHT.\n"
"        All numbers, including WIDTH and HEIGHT, are\n"
"        in body coordinates.  Arrow options and\n"
"        infinite line options are ignored.\n"
"\n"
"      infline STROKE [COLOR] [OPT] X Y A\n"
"        Draw an infinite line, that is, a line one\n"
"        or both of whose ends are at the boundary.\n"
"        The line goes though point (X,Y) and has\n"
"        direction A.  X and Y are in body coordi-\n"
"        nates and A is in degrees.  Close and\n"
"        fill options are ignored.\n"
"\n"
"        With the f option the infinite line extends\n"
"        from (X,Y) in the +A direction.  With the b\n"
"        option the infinite line extends from (X,Y)\n"
"        in the -A direction.  With neither option\n"
"        the infinite line extends in both"
				" directions.\n"
"        In all cases the line is truncated so as to\n"
"        not go outside the boundary.  Any arrows are\n"
"        place at the end or midpoint of the"
                                " truncated\n"
"        line.\n"
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

// Title Parameters.
//
struct command;	    // Defined in Page Section Data
void delete_commands ( command * & list );  // Ditto
double compute_height ( command * list );   // Ditto
command * title = NULL;
double title_height;

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
    const color * col;
    options opt;
    const char * family;
    double space;

    char cairo_family[MAX_NAME_LENGTH+1];
    cairo_font_slant_t cairo_slant;
    cairo_font_weight_t cairo_weight;
};

options stroke_options = (options)
    ( DOTTED + DASHED + CLOSED +
      ARROW_OPTIONS +
      FILL_OPTIONS +
      EXTEND_OPTIONS + OUTLINE );
struct stroke
{
    string name;
    const color * col;
    options opt;
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
                         const color * col,
			 options opt,
                         const char * family,
	                 double space )
{
    assert ( col != NULL );

    font_it it = font_dict.find ( name );
    if ( it != font_dict.end() )
    {
	delete it->second;
        font_dict.erase ( it );
    }
    font * f = new font;
    f->name = name;
    f->col = col;
    f->opt = opt;
    f->family = family;
    // Putting cairo: in front of these does not
    // work, so cairo_family == family.
    assert (    strlen ( family ) + 7
             <= sizeof ( f->cairo_family ) );
    sprintf ( f->cairo_family, "%s", family );
    f->size = size;
    f->space = space;

    f->cairo_slant =
        ( opt & ITALIC ? CAIRO_FONT_SLANT_ITALIC :
                         CAIRO_FONT_SLANT_NORMAL );
    f->cairo_weight =
        ( opt & BOLD ? CAIRO_FONT_WEIGHT_BOLD :
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
		             const color * col,
			     options opt )
{
    assert ( col != NULL );

    stroke_it it = stroke_dict.find ( name );
    if ( it != stroke_dict.end() )
    {
	delete it->second;
        stroke_dict.erase ( it );
    }
    stroke * s = new stroke;
    s->name = name;
    s->col = col;
    s->opt = opt;
    s->width = width;

    stroke_dict[s->name] = s;

    return s;
}

// Print font for debugging.
//
void print_font ( const font * f )
{
    cerr << "    font " << f->name
	 << " " << 72 * f->size << "pt"
	 << " " << f->col->name
	 << " " << f->opt
	 << " " << f->family
	 << " " << f->space << "em"
	 << endl;
}

// Print stroke for debugging.
//
void print_stroke ( const stroke * s )
{
    cerr << "    stroke " << s->name
	 << " " << 72 * s->width << "pt"
	 << " " << s->col->name
	 << " " << s->opt
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
    delete_commands ( title );

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
	make_stroke ( "wide-dotted", 2.0/72,
	              black, DOTTED );
	make_stroke ( "normal-dotted", 1.0/72,
	              black, DOTTED );
	make_stroke ( "narrow-dotted", 0.5/72,
	              black, DOTTED );
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
	make_stroke ( "wide-dotted", 1.0/72,
	              black, DOTTED );
	make_stroke ( "normal-dotted", 0.5/72,
	              black, DOTTED );
	make_stroke ( "narrow-dotted", 0.25/72,
	              black, DOTTED );
    }
    make_stroke ( "solid", 0,
		  black, FILL_SOLID );
    make_stroke ( "cross-hatch", 0,
		  black, FILL_DOTTED );
    make_stroke ( "left-hatch", 0,
		  black, FILL_VERTICAL );
    make_stroke ( "right-hatch", 0,
		  black, FILL_HORIZONTAL );
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

    // The following are only used by some commands.
    //
    const font * fnt;
    const stroke * str;
    const color * col;
    options opt;
        // When fnt or str is set, col and opt default
	// to those of fnt or str.
};

options text_options = (options)
    ( TOP + BOTTOM + LEFT + RIGHT +
      BOX_WHITE + CIRCLE_WHITE + OUTLINE );
struct text : public command // == 't'
{
    point p;
    string t;
};
struct space : public command // == 'S'
{
    double s;
};
struct start : public command // == 's'
{
    point p;
};
struct line : public command // == 'l'
{
    point p;
};
struct curve : public command // == 'c'
{
    point p[3];
};
struct end : public command // == 'e'
{
};
struct arc : public command // == 'a'
{
    // Data if not continuing ( s != NULL )
    //
    point c;

    // Data if R has units ( ! isnan ( R ) )
    //
    double R;   // In pt.

    // Data if continuing or not continuing.
    //
    vector r;
    double a;
    double g1, g2;
};
struct rectangle : public command // == 'r'
{
    point c;
    double width, height;
};
struct infline : public command // == 'i'
{
    point p;
    double A;
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
	    cerr << "    text " << t->fnt->name;
	    if ( t->col != NULL )
	        cerr << " " << t->col->name;
	    if ( t->opt != NO_OPTIONS )
	        cerr << " " << t->opt;
	    cerr << " " << t->p
		 << " " << t->t
		 << endl;
	    break;
	}
	case 'S':
	{
	    space * s = (space *) current;
	    cerr << "    space " << s->s << "in"
		 << endl;
	    break;
	}
	case 's':
	{
	    start * s = (start *) current;
	    cerr << "    start " << s->str->name;
	    if ( s->col != NULL )
	        cerr << " " << s->col->name;
	    if ( s->opt != NO_OPTIONS )
	        cerr << " " << s->opt;
	    cerr << " " << s->p
		 << endl;
	    break;
	}
	case 'l':
	{
	    line * l = (line *) current;
	    cerr << "    line " << l->p
		 << endl;
	    break;
	}
	case 'c':
	{
	    curve * c = (curve *) current;
	    cerr << "    curve " << c->p[0]
	         << " " << c->p[1]
	         << " " << c->p[2]
		 << endl;
	    break;
	}
	case 'e':
	    cerr << "    end" << endl;
	    break;
	case 'a':
	{
	    arc * a = (arc *) current;
	    if ( a->str == NULL )
		cerr << "    arc"
		     << " " << a->r
		     << " " << a->a
		     << " " << a->g1
		     << " " << a->g2
		     << endl;
	    else
	    {
		cerr << "    arc " << a->str->name;
		if ( a->col != NULL )
		    cerr << " " << a->col->name;
		if ( a->opt != NO_OPTIONS )
		    cerr << " " << a->opt;
		cerr << " " << a->c;
		if ( ! isnan ( a->R ) )
		    cerr << " " << a->R << "pt"
		         << endl;
		else
		    cerr << " " << a->r
		         << " " << a->a
		         << " " << a->g1
		         << " " << a->g2
		         << endl;
	    }
	    break;
	}
	case 'r':
	{
	    rectangle * r = (rectangle *) current;
	    cerr << "    rectangle " << r->str->name;
	    if ( r->col != NULL )
		cerr << " " << r->col->name;
	    if ( r->opt != NO_OPTIONS )
		cerr << " " << r->opt;
	    cerr << " " << r->c
	         << " " << r->width
	         << " " << r->height
		 << endl;
	    break;
	}
	case 'i':
	{
	    infline * il = (infline *) current;
	    cerr << "    infline " << il->str->name;
	    if ( il->col != NULL )
		cerr << " " << il->col->name;
	    if ( il->opt != NO_OPTIONS )
		cerr << " " << il->opt;
	    cerr << " " << il->p
	         << " " << il->A
		 << endl;
	    break;
	}
	default:
	    cerr << "    bad command " << current->c
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

	switch ( current->c )
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
	case 'i':
	    delete (infline *) current;
	    break;
	default:
	    assert ( ! "deleting bad command" );
	}
	current = next;

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
                        - L_margins.bottom
			- title_height;
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

// Process units for double read with get_double.
// Return true on success and false if error message.
// Units of "" do not change value, but low and high
// are checked.
//
bool process_units ( const char * name, double & var,
                     double low, double high )
{
    if ( units == "" )
        /* do nothing */;
    else if ( units == "pt" )
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
    if ( units == "" )
    {
        error ( "%s should have units", name );
	return false;
    }
    return process_units ( name, var, low, high );
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

// Check if there are conflicts in options, and remove
// all but the one with the lowest order bit.  Print
// error messages for options removed.
//
void check_conflicts
	( options & opt, options conflicts )
{
    int first = -1;
    int next = -1;
    while ( opt & conflicts )
    {
        ++ next;
	options mask = (options) ( 1 << next );
	if ( ( conflicts & mask ) == 0 )
	    continue;
	conflicts = (options) ( conflicts & ~ mask );
	if ( ( opt & mask ) == 0 )
	    continue;
	if ( first == -1 ) first = next;
	else
	{
	    error ( "option %c conflicts with %c;"
	            " %c ignored", optchar[next],
		    optchar[first], optchar[next] );
	    opt = (options) ( opt & ~ mask );
	}
    }
}

// Attach command to end of current_list.  Returns
// false if this cannot be done because command is
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
	    dout << endl << "Layout:" << endl;

	    s = LAYOUT;
	    current_list = & title;

	    long R, C;
	    double  WIDTH, HEIGHT; 

	    if ( read_long ( "R", R, 1, 40 )
	         &&
		 read_long ( "C", C, 1, 20, false ) )
	    {
	        init_layout ( R, C );
		if ( read_length
		         ( "WIDTH", WIDTH,
			   1e-12, 1000 )
		     &&
		     read_length
			 ( "HEIGHT", HEIGHT,
			   1e-12, 1000, false ) )
		{
		    L_width = WIDTH;
		    L_height = HEIGHT;

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
	        WIDTH = ( OPT & FILL_OPTIONS ?
		          0 : 1.0/72 );

	    check_conflicts
	        ( OPT, DOTTED_DASHED_CONFLICT );
	    check_conflicts
	        ( OPT, FILL_CONFLICT );

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
	            ( "S", S, 10e-12, 1000, false ) )
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
	    if ( ! in_head_or_foot )
		level_stack.push_back
		    ( current_list - level );
	    current_list = & head;
	    in_head_or_foot = true;
	    in_body = false;
	}
	else if ( op == "foot" && s == PAGE )
	{
	    if ( ! in_head_or_foot )
		level_stack.push_back
		    ( current_list - level );
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
		if ( ! in_head_or_foot )
		    level_stack.push_back
			( current_list - level );
		current_list = & level[N];
	    }
	    in_head_or_foot = false;
	    in_body = true;
	}
	else if ( op == "text" )
	{
	    const font * FONT;
	    const color * COLOR;
	    options OPT = NO_OPTIONS;
	    double X = 0, Y = 0;
	    string TEXT;

	    if ( ! read_font ( "FONT", FONT, false ) )
	        continue;
	    COLOR = FONT->col;
	    // Unlike non-text drawing commands,
	    // options are logical OR of font and
	    // optional options.
	    if ( in_body )
	    {
		read_color ( "COLOR", COLOR );
	        read_options
		    ( "OPT", OPT, text_options );
		OPT != FONT->opt;
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

	    check_conflicts
	        ( OPT, TOP_BOTTOM_CONFLICT );
	    check_conflicts
	        ( OPT, LEFT_RIGHT_CONFLICT );
	    check_conflicts
	        ( OPT, BOX_CIRCLE_CONFLICT );

	    text * t = new text;
	    attach ( t, 't' );
	    t->fnt = FONT;
	    t->col = COLOR;
	    t->opt = OPT;
	    t->p = { X, Y };
	    t->t = TEXT;
	}
	else if ( op == "space" && ! in_body )
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
	    const color * COLOR;
	    options OPT;
	    double X, Y;
	    if ( ! read_stroke
	               ( "STROKE", STROKE, false ) )
	        continue;
	    COLOR = STROKE->col;
	    OPT = STROKE->opt;
	    read_color ( "COLOR", COLOR );
	    read_options
	        ( "OPT", OPT, stroke_options ); 
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

	    check_conflicts
	        ( OPT, DOTTED_DASHED_CONFLICT );
	    check_conflicts
	        ( OPT, FILL_CONFLICT );

	    start * st = new start;
	    attach ( st, 's', true );
	    st->str = STROKE;
	    st->col = COLOR;
	    st->opt = OPT;
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
	    const color * COLOR = NULL;
	    options OPT = NO_OPTIONS;
	    double XC = 0, YC = 0, RX, RY, R = NAN,
	           A = 0, G1 = 0, G2 = 360;

	    if ( read_stroke ( "STROKE", STROKE ) )
	    {
		COLOR = STROKE->col;
		OPT = STROKE->opt;
		read_color ( "COLOR", COLOR );
		read_options
		    ( "OPT", OPT, stroke_options );
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
		if ( ! get_double() )
		{
		    error ( "R or RX is missing" );
		    continue;
		}
		if ( units != "" )
		{
		    if ( ! process_units
		               ( "R", R, 0, 1 ) )
			continue;
		}
		else
		{
		    if ( ! process_units
		        ( "R/RX", RX,
			   0, + MAX_BODY_COORDINATE ) )
			continue;
		    if ( ! read_double
			       ( "RY", RY,
				 0,
				 + MAX_BODY_COORDINATE )
		       )
			RY = RX;
		    else
		    if ( read_double
			     ( "A", A,
			       - 1000 * 360,
			       + 1000 * 360 )
			 &&
			 read_double
			     ( "G1", G1,
			       - 1000 * 360,
			       + 1000 * 360 )
			 &&
			 ! read_double
			     ( "G2", G2,
			       - 1000 * 360,
			       + 1000 * 360,
			       false ) )
			continue;
		}
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

	    check_conflicts
	        ( OPT, DOTTED_DASHED_CONFLICT );
	    check_conflicts
	        ( OPT, FILL_CONFLICT );

	    arc * a = new arc;
	    attach ( a, 'a',
	             STROKE == NULL,
		     STROKE == NULL );
	    a->str = STROKE;
	    a->col = COLOR;
	    a->opt = OPT;
	    a->c = { XC, YC };
	    a->R = ( isnan ( R ) ? NAN : 72 * R );
	    a->r = { RX, RY };
	    a->a = A;
	    a->g1 = G1;
	    a->g2 = G2;
	}
	else if ( op == "rectangle" && in_body )
	{
	    const stroke * STROKE;
	    const color * COLOR;
	    options OPT;
	    double XC, YC, WIDTH, HEIGHT;

	    if ( ! read_stroke
	               ( "STROKE", STROKE, false ) )
		continue;
	    COLOR = STROKE->col;
	    OPT = STROKE->opt;
	    read_color ( "COLOR", COLOR );
	    read_options ( "OPT", OPT, stroke_options );
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
	               ( "WIDTH", WIDTH,
			 0, + MAX_BODY_COORDINATE,
			 false ) )
		continue;
	    if ( ! read_double
	               ( "HEIGHT", HEIGHT,
			 0, + MAX_BODY_COORDINATE,
			 false ) )
		continue;

	    check_conflicts
	        ( OPT, DOTTED_DASHED_CONFLICT );
	    check_conflicts
	        ( OPT, FILL_CONFLICT );

	    rectangle * r = new rectangle;
	    attach ( r, 'r' );
	    r->str = STROKE;
	    r->col = COLOR;
	    r->opt = OPT;
	    r->c = { XC, YC };
	    r->width = WIDTH;
	    r->height = HEIGHT;
	}
	else if ( op == "infline" && in_body )
	{
	    const stroke * STROKE;
	    const color * COLOR;
	    options OPT;
	    double X, Y, A;

	    if ( ! read_stroke
	               ( "STROKE", STROKE, false ) )
		continue;
	    COLOR = STROKE->col;
	    OPT = STROKE->opt;
	    read_color ( "COLOR", COLOR );
	    read_options ( "OPT", OPT, stroke_options );
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
	    if ( ! read_double
	               ( "A", A,
		         - 1000 * 360, + 1000 * 360,
			 false ) )
		continue;

	    check_conflicts
	        ( OPT, DOTTED_DASHED_CONFLICT );
	    check_conflicts
	        ( OPT, EXTEND_CONFLICT );

	    infline * il = new infline;
	    attach ( il, 'i' );
	    il->str = STROKE;
	    il->col = COLOR;
	    il->opt = OPT;
	    il->p = { X, Y };
	    il->A = A;
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
	    else if ( s == LAYOUT )
	        title_height = compute_height ( title );

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

    if ( debug && s == LAYOUT )
	cerr << "    R = " << R <<
		   " C = " << C << endl
	     << "    Physical Page:" << endl
	     << "        width "
	     << L_width << "in" << endl
	     << "        height "
	     << L_height << "in" << endl
	     << "        margins"
	     << " " << L_margins.top << "in"
	     << " " << L_margins.right << "in"
	     << " " << L_margins.bottom << "in"
	     << " " << L_margins.left << "in"
	     << endl
	     << "    Logical Page Defaults:"
	     << endl
	     << "        margins"
	     << " " << D_margins.top << "in"
	     << " " << D_margins.right << "in"
	     << " " << D_margins.bottom << "in"
	     << " " << D_margins.left << "in"
	     << endl
	     << "        bounds"
	     << " " << D_bounds.ll << " - "
	     << " " << D_bounds.ur
	     << endl
	     << "        scale " << D_scale
	     << endl
	     << "        background "
	     << ( D_background == NULL ? "none" :
	          D_background->name )
	     << endl;

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
	    h += t->fnt->size * t->fnt->space;
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

// Return midpoint of Bezier cubic curve computed from
// 4 points.  Also return the direction dv of the
// curve at the midpoint.
//
point midpoint
	( vector & dv,
	  point p1, point p2, point p3, point p4 )
{
    p1 = 0.5 * ( p1 + p2 );
    p2 = 0.5 * ( p2 + p3 );
    p3 = 0.5 * ( p3 + p4 );
    p1 = 0.5 * ( p1 + p2 );
    p2 = 0.5 * ( p2 + p3 );
    dv = p2 - p1;
    return 0.5 * ( p1 + p2 );
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
    xmax = ymax = - DBL_MAX;
    int count = 0;

#   define BOUND(v) \
	if ( xmin > (v).x ) xmin = (v).x; \
	if ( xmax < (v).x ) xmax = (v).x; \
	if ( ymin > (v).y ) ymin = (v).y; \
	if ( ymax < (v).y ) ymax = (v).y; \
	++ count

    for ( int i = 1; i <= MAX_LEVEL; ++ i )
    {
	command * current = level[i];
	point p = { NAN, NAN };
	    // Current location of path in body
	    // coordinates.
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
		p = s->p;
		break;
	    }
	    case 'l':
	    {
		line * l = (line *) current;
		BOUND ( l->p );
		p = l->p;
		break;
	    }
	    case 'c':
	    {
		curve * c = (curve *) current;
		vector dv;
		p = midpoint
		    ( dv,
		      p, c->p[0], c->p[1], c->p[2] );
		BOUND ( p );
		BOUND ( c->p[2] );
		p = c->p[2];
		break;
	    }
	    case 'e':
		break;
	    case 'a':
	    {
		arc * a = (arc *) current;
		point c;
		if ( a->str != NULL )
		{
		    c = a->c;
		    if ( ! isnan ( a->R ) )
			a->r.x = a->r.y = 0;
			// Include center in bounds
			// but treat radius as 0.
		}
		else
		{
		    point ux = { 1, 0 };

		    point p1 = ux ^ a->g1;
		    p1 = { a->r.x * p1.x,
		           a->r.y * p1.y };
		    p1 = p1 ^ a->a;

		    point p2 = ux ^ a->g2;
		    p2 = { a->r.x * p2.x,
		           a->r.y * p2.y };
		    p2 = p2 ^ a->a;

		    // c + p1 == p
		    // new p = c + p2
		    //
		    c = p - p1;
		    p = c + p2;
		}
		vector d[4] = {
		    { - a->r.x, - a->r.y },
		    { + a->r.x, - a->r.y },
		    { + a->r.x, + a->r.y },
		    { - a->r.x, + a->r.y } };
		for ( int j = 0; j < 4; ++ j )
		{
		    point q = c + ( d[j]^(a->a) );
			// ^ has lower precedence
			// than +
		    BOUND ( q );
		}
		break;
	    }
	    case 'r':
	    {
		rectangle * r = (rectangle *) current;
		vector d[4] = {
		    { - r->width / 2, - r->height / 2 },
		    { + r->width / 2, - r->height / 2 },
		    { + r->width / 2, + r->height / 2 },
		    { - r->width / 2, + r->height / 2 }
		};
		for ( int j = 0; j < 4; ++ j )
		{
		    BOUND ( r->c + d[j] );
		}
		break;
	    }
	    case 'i':
	    {
		infline * il = (infline *) current;
		BOUND ( il->p );
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
        cerr << endl << name << ":" << endl;

    cairo_get_matrix ( context, & matrix );
    vector x = { matrix.xx, matrix.yx };
    vector y = { matrix.xy, matrix.yy };
    vector t = { matrix.x0, matrix.y0 };
    cerr << x << "*x + " << y << "*y + " << t << endl;
}

// Print current path for debugging.
//
void print_path ( cairo_t * context,
                  const char * name = NULL )
{
    cairo_path_t * path;
    cairo_path_data_t * data;

    if ( name != NULL )
        cerr << endl << name << ":" << endl;

    path = cairo_copy_path ( context );
    for ( int i = 0; i < path->num_data; )
    {
        data = & path->data[i];
	i += data->header.length;
	switch ( data->header.type )
	{
	case CAIRO_PATH_MOVE_TO:
	{
	    point p = { data[1].point.x,
	                data[1].point.y };
	    cerr << "move to " << p << endl;
	    break;
	}
	case CAIRO_PATH_LINE_TO:
	{
	    point p = { data[1].point.x,
	                data[1].point.y };
	    cerr << "line to " << p << endl;
	    break;
	}
	case CAIRO_PATH_CURVE_TO:
	{
	    point p[3] = {
	        { data[1].point.x, data[1].point.y },
	        { data[2].point.x, data[2].point.y },
	        { data[3].point.x, data[3].point.y } };
	    cerr << "curve to"
	         << " " << p[0]
	         << " " << p[1]
	         << " " << p[2]
		 << endl;
	    break;
	}
	case CAIRO_PATH_CLOSE_PATH:
	{
	    cerr << "close" << endl;
	    break;
	}
	default:
	    cerr << "unknown path element type "
	         << data->header.type << endl;
        }
    }
    cairo_path_destroy ( path );
    cerr << "Matrix: ";
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
      double left, double top, double width,
      int page = 0 )
{

    if ( debug && list != NULL )
    {
        cerr << endl << name << ":" << endl;
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
	    const font * f = t->fnt;
	    top += f->size * f->space; 
	    const color * col = t->col;

	    string s = t->t;

	    if ( page > 0 )
	    {
	        string ps = to_string ( page );
		while ( true )
		{
		    size_t pos = s.find ( "###" );
		    if ( pos == string::npos )
		        break;
		    s.replace ( pos, 3, ps );
		}
	    }


	    string tx[3];
	    size_t pos1 = s.find_first_of ( '\\' );
	    size_t pos2 = s.find_last_of ( '\\' );
	    if ( pos1 == string::npos )
	        tx[1] = s;
	    else
	    {
	        tx[0] = s.substr ( 0, pos1 );
		tx[2] = s.substr ( pos2 + 1 );
		if ( pos1 != pos2 )
		    tx[1] = s.substr
		        ( pos1 + 1, pos2 - pos1 - 1 );
	    }

	    cairo_set_source_rgb
	        ( context,
		  col->red, col->green, col->blue );
		           
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

// Convert point p to x, y cairo coordinate
// pair.  p is in body coordinates with y increasing
// from bottom to top.  Cairo coordinates are in
// pt units with y increasing from top to bottom.
//
# define CONVERT(p) \
    left + ((p).x - xleft) * xscale, \
    bottom - ((p).y - ybottom) * yscale

// Ditto but for vector v and not a point.
//
# define SCALE(v) \
    (v).x * xscale, - (v).y * yscale

void apply_stroke ( const command * c )
{
    const stroke * str = c->str;
    const color * col = c->col;
    options opt = c->opt;

    cairo_set_line_width
	( context, 72 * str->width );
    if ( opt & ( FILL_DOTTED |
	         FILL_HORIZONTAL |
	         FILL_VERTICAL ) )
	set_fill ( context, col, opt );
    else
	cairo_set_source_rgb
	    ( context,
	      col->red, col->green, col->blue );
    if ( opt & CLOSED )
	cairo_close_path ( context );
    if ( opt & FILL_OPTIONS )
    {
	if ( opt & OUTLINE )
	{
	    cairo_fill_preserve ( context );
	    if ( ( opt & CLOSED ) == 0 )
		cairo_close_path ( context );
	    if ( opt & FILL_SOLID )
		cairo_set_source_rgb
		    ( context, 0, 0, 0 );
	    else
		cairo_set_source_rgb
		    ( context, col->red,
		      col->green, col->blue ); 
	    cairo_stroke ( context );
	}
	else
	    cairo_fill ( context );
    }
    else if ( opt & DASHED )
    {
	double dashes[2] = { 4, 2 };
	cairo_set_dash ( context, dashes, 2, 0 );
	cairo_stroke ( context );
	cairo_set_dash ( context, NULL, 0, 0 );
    }
    else if ( opt & DOTTED )
    {
	double dashes[2] = { 0, 3 };
	    // dot separation is 2pt
	cairo_set_line_cap
	    ( context, CAIRO_LINE_CAP_ROUND );
	cairo_set_dash ( context, dashes, 2, 0 );
	cairo_stroke ( context );
	cairo_set_dash ( context, NULL, 0, 0 );
	cairo_set_line_cap
	    ( context, CAIRO_LINE_CAP_BUTT );
    }
    else
	cairo_stroke ( context );
}

// Draw arrow head at p in direction dv with wing
// length 6pt.
//
void draw_arrow ( point p, vector dv )
{
    p = { CONVERT ( p ) };
    dv = { SCALE ( dv ) };
    dv = ( 6 / sqrt ( dv*dv ) ) * dv;
    point p1 = p + ( dv ^ +135 );
    point p2 = p + ( dv ^ -135 );
    cairo_move_to ( context, p1.x, p1.y );
    cairo_line_to ( context, p.x, p.y );
    cairo_line_to ( context, p2.x, p2.y );
}

// Draw all the arrows for one stroke that started at s.
// The stroke should already have been drawn.
//
void draw_arrows ( const start * s )
{
    options opt = s->opt;
    if ( ( opt & ARROW_OPTIONS ) == 0 )
        return;
    const color * col = s->col;

    cairo_new_path ( context );
    point p = s->p;
    const command * current = s;
    bool done = false;
    while ( ! done )
    {
        switch ( current->c )
	{
	case 'e':
	{
	    done = true;

	    if (    ( opt & ( FILL_OPTIONS | CLOSED ) )
	         == 0 )
	        break;
	    if ( opt & MIDDLE_ARROW )
	        draw_arrow ( 0.5 * ( p + s->p ),
		             s->p - p );
	    if ( opt & END_ARROW )
	        draw_arrow ( s->p, s->p - p );
	    break;
	}
	case 'l':
	{
	    const line * l = (const line *) current;
	    if ( opt & MIDDLE_ARROW )
	        draw_arrow ( 0.5 * ( p + l->p ),
		             l->p - p );
	    if ( opt & END_ARROW )
	        draw_arrow ( l->p, l->p - p );
	    p = l->p;
	    break;
	}
	case 'c':
	{
	    const curve * c = (const curve *) current;
	    if ( opt & MIDDLE_ARROW )
	    {
	        vector dv;
		point pmid = midpoint
		    ( dv,
		      p, c->p[0], c->p[1], c->p[2] );
	        draw_arrow ( pmid, dv );
	    }
	    if ( opt & END_ARROW )
	        draw_arrow ( c->p[2],
		             c->p[2] - c->p[1] );
	    p = c->p[2];
	    break;
	}
	case 'a':
	{
	    const arc * a = (const arc *) current;
	    point ux = { 1, 0 };

	    vector v1 = ux ^ a->g1;
	    v1 = { a->r.x * v1.x,
		   a->r.y * v1.y };
	    v1 = v1 ^ a->a;

	    // c + v1 == p
	    //
	    point c = p - v1;  // Center

	    if ( opt & MIDDLE_ARROW )
	    {
		double gmid = ( a->g1 + a->g2 ) / 2;
		vector v = ux ^ gmid;
		vector dv;
		if ( a->g1 > a->g2 )
		    dv = v ^ -90;
		else
		    dv = v ^ +90;
		v = { a->r.x * v.x, a->r.y * v.y };
		dv = { a->r.x * dv.x, a->r.y * dv.y };
		v = v ^ a->a;
		dv = dv ^ a->a;
		draw_arrow ( c + v, dv );
	    }

	    vector v = ux ^ a->g2;
	    v = { a->r.x * v.x, a->r.y * v.y };
	    v = v ^ a->a;

	    if ( opt & END_ARROW )
	    {
		vector dv;
		if ( a->g1 > a->g2 )
		    dv = ux ^ ( a->g2 - 90 );
		else
		    dv = ux ^ ( a->g2 + 90 );
		dv = { a->r.x * dv.x, a->r.y * dv.y };
		dv = dv ^ a->a;
		draw_arrow ( c + v, dv );
	    }

	    p = c + v;
	    break;
	}
	}

	current = current->next;
    }
    if ( ( opt & FILL_SOLID )
         &&
	 ( opt & OUTLINE ) )
	cairo_set_source_rgb ( context, 0, 0, 0 );
    else
	cairo_set_source_rgb
	    ( context,
	      col->red, col->green, col->blue );
    cairo_set_line_width
        ( context, 72 * s->str->width );
    cairo_stroke ( context );
}

void draw_level ( int i )
{
    if ( debug && level[i] != NULL )
    {
        cerr << endl
	     << "Level " << i << ":"
	     << endl;
	print_commands ( level[i] );
    }

    command * current = level[i];
    const start * s;
    if ( current != NULL ) do
    {
        current = current->next;
	switch ( current->c )
	{
	case 't':
	{
	    text * t = (text *) current;
	    const font * f = t->fnt;
	    const color * c = t->col;
	    point p = { CONVERT ( t->p ) };
	    double h = 72 * f->size * f->space;
	    double delta = 72 * 0.5 * f->size;

	    std::vector<string> tx;
	    size_t beg = 0;
	    while ( true )
	    {
		size_t end =
		    t->t.find_first_of ( '\\', beg );
	        if ( end == string::npos )
		{
		    tx.push_back
		        ( t->t.substr ( beg ) );
		    break;
		}
		else
		{
		    tx.push_back
		        ( t->t.substr
			      ( beg, end - beg ) );
		    beg = end + 1;
		}
	    }
	    int n = tx.size();
	    double box_width = 0;
	    double box_height = n * h;

	    std::vector<double> tx_width;
	    cairo_select_font_face
	        ( context, f->cairo_family,
		           f->cairo_slant,
			   f->cairo_weight );
	    cairo_set_font_size
	        ( context, 72 * f->size );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    cairo_text_extents_t te;
	    for ( int i = 0; i < n; ++ i )
	    {
		cairo_text_extents
		    ( context, tx[i].c_str(), & te );
		tx_width.push_back ( te.width );
		if ( box_width < te.width )
		    box_width = te.width;
	    }
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    point box_ul = { 0, 0 };
	    options align = NO_OPTIONS;

	    double m = ( t->opt & BOX_WHITE ? 2 : 1 );
	        // Allow for extra size of rectangle.
	    if ( t->opt & BOTTOM )
	        box_ul.y = p.y + m * delta - 0.3 * h;
	    else if ( t->opt & TOP )
	        box_ul.y = p.y - n * h - m * delta;
	    else
	        box_ul.y = p.y - n * h / 2 - 0.2 * h;
	    if ( t->opt & LEFT )
	    {
	        box_ul.x = p.x - box_width - m * delta;
		align = RIGHT;
	    }
	    else if ( t->opt & RIGHT )
	    {
	        box_ul.x = p.x + m * delta;
		align = LEFT;
	    }
	    else
	        box_ul.x = p.x - box_width / 2;

	    if ( t->opt & CIRCLE_WHITE )
	    {
	        align = NO_OPTIONS;
		double RADIUS = 0;
		double dh = box_height / 2;
		for ( int i = 0; i < n; ++ i )
		{
		    double dw = tx_width[i] / 2;
		    double dist =
		        sqrt ( dh*dh + dw*dw );
		    if ( RADIUS < dist ) RADIUS = dist;
		    dh -= h;
		    dist = sqrt ( dh*dh + dw*dw );
		    if ( RADIUS < dist ) RADIUS = dist;
		}
		RADIUS += 0.2 * delta;
		cairo_new_path ( context );
		cairo_arc ( context,
		            box_ul.x + box_width / 2,
			    box_ul.y + box_height / 2
			             + 0.2 * h,
			    RADIUS, 0, 2 * M_PI );
		if ( t->opt & OUTLINE )
		{
		    cairo_fill_preserve ( context );
		    cairo_set_source_rgb
			( context, 0, 0, 0 );
		    cairo_set_line_width
		        ( context, 1 );  // 1pt
		    cairo_stroke_preserve ( context );
		}
		cairo_set_source_rgb
		    ( context, 1, 1, 1 );
		cairo_fill ( context );
	    }
	    else if ( t->opt & BOX_WHITE )
	    {
		align = NO_OPTIONS;
		cairo_new_path ( context );
		cairo_rectangle
		    ( context,
		      box_ul.x - delta,
		      box_ul.y,
		      box_width + 2 * delta,
		      box_height + delta );
		if ( t->opt & OUTLINE )
		{
		    cairo_set_source_rgb
			( context, 0, 0, 0 );
		    cairo_set_line_width
		        ( context, 1 );  // 1pt
		    cairo_stroke_preserve ( context );
		}
		cairo_set_source_rgb
		    ( context, 1, 1, 1 );
		cairo_fill ( context );
	    }
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    cairo_set_source_rgb
	        ( context, t->col->red,
		  t->col->green, t->col->blue );
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );

	    // text x position is:
	    //     txx + txw * line-width
	    //
	    double txx;
	    double txw;
	    if ( align == LEFT )
	        txx = box_ul.x, txw = 0;
	    else if ( align == RIGHT )
	        txx = box_ul.x + box_width, txw = -1;
	    else
	        txx = box_ul.x + box_width / 2,
		txw = -0.5;
	    for ( int i = 0; i < n; ++ i )
	    {
	        cairo_move_to
		    ( context,
		      txx + txw * tx_width[i],
		      box_ul.y + ( i + 1 ) * h );
		cairo_show_text
		    ( context, tx[i].c_str() );
	    }
	    assert (    cairo_status ( context )
		     == CAIRO_STATUS_SUCCESS );
	    break;
	}
	case 's':
	{
	    s = (start *) current;
	    cairo_new_path ( context );
	    cairo_move_to
	        ( context, CONVERT ( s->p ) );
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
	    draw_arrows ( s );
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

	    if ( a->str == NULL )
	    {
	        point p1;
		cairo_get_current_point
		    ( context, & p1.x, & p1.y );

		vector p2 = { 1, 0 };
		p2 = p2 ^ a->g1;
		p2 = { a->r.x * p2.x, a->r.y * p2.y };
		p2 = p2 ^ a->a;
		// If we knew the center c in body
		// coordinates, then we want
		//     CONVERT ( c + p2 ) == p1.
		// Therefore we want to translate by
		//     CONVERT ( c ) =
		//         p1 - SCALE ( p2 )
		// where 
		//   CONVERT ( c + p2 ) =
		//       CONVERT ( c ) + SCALE ( p2 )
		//
		p2 = { SCALE ( p2 ) };
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
	    if ( isnan ( a->R ) )
	    {
		cairo_scale
		    ( context, xscale, - yscale );
		cairo_rotate
		    ( context, M_PI * a->a / 180 );
		cairo_scale ( context, a->r.x, a->r.y );
	    }
	    else
		cairo_scale
		    ( context, a->R, a->R );

	    // (x,y) in the arc plane means (x,-y) in
	    // our plane, and angle A in the arc plane
	    // means angle -A in our plane.  We draw
	    // the arc and then flip the y axis.
	    //
	    cairo_scale ( context, 1, -1 );
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
	    if ( a->str != NULL )
	        apply_stroke ( a );
	    break;
	}
	case 'r':
	{
	    rectangle * r = (rectangle *) current;
	    point c = { CONVERT ( r->c ) };
	    vector d =
	        { - fabs ( xscale ) * r->width / 2,
		  - fabs ( yscale ) * r->height / 2 };
	    cairo_rectangle
	        ( context, c.x + d.x, c.y + d.y,
		           fabs ( xscale) * r->width,
		           fabs ( yscale) * r->height );
	    apply_stroke ( r );
	    break;
	}
	case 'i':
	{
	    infline * il = (infline *) current;
	    cairo_new_path ( context );
	    point p = il->p;
	    double A = ( M_PI / 180 ) * il->A;
	    vector v = { cos ( A ), sin ( A ) };
	    // Line is p + t*v for real t
	    // Compute values of t for which line enters
	    // and leaves bounding box.
	    double tenter = - INFINITY;
	    double texit = + INFINITY;

	    if ( v.x == 0 )
	    {
	        if ( p.x < P_bounds.ll.x
		     ||
		     p.x > P_bounds.ur.x )
		    tenter = + INFINITY,
		    texit = -INFINITY;
		    // Line misses bounding box.
	    }
	    else if ( v.x > 0 )
	    {
	        // p.x + t * v.x = bound x
	        double t =
		    ( P_bounds.ll.x - p.x ) / v.x;
		if ( tenter < t ) tenter = t;
	        t = ( P_bounds.ur.x - p.x ) / v.x;
		if ( texit > t ) texit = t;
	    }
	    else // if ( v.x < 0 )
	    {
	        // p.x + t * v.x = bound x
	        double t =
		    ( P_bounds.ur.x - p.x ) / v.x;
		if ( tenter < t ) tenter = t;
	        t = ( P_bounds.ll.x - p.x ) / v.x;
		if ( texit > t ) texit = t;
	    }

	    if ( v.y == 0 )
	    {
	        if ( p.y < P_bounds.ll.y
		     ||
		     p.y > P_bounds.ur.y )
		    tenter = + INFINITY,
		    texit = -INFINITY;
		    // Line misses bounding box.
	    }
	    else if ( v.y > 0 )
	    {
	        // p.y + t * v.y = bound y
	        double t =
		    ( P_bounds.ll.y - p.y ) / v.y;
		if ( tenter < t ) tenter = t;
	        t = ( P_bounds.ur.y - p.y ) / v.y;
		if ( texit > t ) texit = t;
	    }
	    else // if ( v.y < 0 )
	    {
	        // p.y + t * v.y = bound y
	        double t =
		    ( P_bounds.ur.y - p.y ) / v.y;
		if ( tenter < t ) tenter = t;
	        t = ( P_bounds.ll.y - p.y ) / v.y;
		if ( texit > t ) texit = t;
	    }
	    if ( il->opt & EXTEND_FOREWARD )
	    {
	        if ( tenter < 0 ) tenter = 0;
	    }
	    if ( il->opt & EXTEND_BACKWARD )
	    {
	        if ( texit > 0 ) texit = 0;
	    }
	    if ( tenter <= texit )
	    {
	        point penter = p + tenter * v;
	        point pexit = p + texit * v;
		cairo_new_path ( context );
		cairo_move_to
		    ( context, CONVERT ( penter ) );
		cairo_line_to
		    ( context, CONVERT ( pexit ) );
		apply_stroke ( il );
		if ( il->opt & ARROW_OPTIONS )
		{
		    cairo_new_path ( context );
		    point pmiddle =
		        0.5 * ( penter + pexit );
		    if ( il->opt & MIDDLE_ARROW )
			draw_arrow ( pmiddle, v );
		    if ( il->opt & END_ARROW )
			draw_arrow ( pexit, v );

		    // This cannot be done by apply
		    // stoke as that may contain
		    // dashes or dots.
		    //
		    cairo_set_source_rgb
			( context, il->col->red,
			  il->col->green,
			  il->col->blue );
		    cairo_set_line_width
		        ( context,
			  72 * il->str->width );
		    cairo_stroke ( context );
		}
	    }
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
	if ( count == 0 )
	    fatal ( "zero points available to"
	            " automatically compute bounding"
		    " box", count );
    }

    double dx = P_bounds.ur.x - P_bounds.ll.x;
    double dy = P_bounds.ur.y - P_bounds.ll.y;
    if ( dx == 0 ) dx = dy;
    if ( dy == 0 ) dy = dx;
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
        point ll = { body_left,
	             body_top + body_height };
	point ur = { body_left + body_width,
	             body_top };
	ll = 72 * ll;
	ur = 72 * ur;

	point cll = { CONVERT ( P_bounds.ll ) };
	point cur = { CONVERT ( P_bounds.ur ) };

	// cerr << "PHYSICAL " << ll << " - " << ur
	//      << endl;
	// cerr << "CONVERTED " << cll << " - " << cur
	//      << endl;

	cerr << endl << "Logical Page:" << endl
	     << "    width " << P_width << "in"
	     << " height " << P_height << "in"
	     << endl
	     << "    margins"
	     << " " << P_margins.top << "in"
	     << " " << P_margins.right << "in"
	     << " " << P_margins.bottom << "in"
	     << " " << P_margins.left << "in"
	     << endl
	     << "    bounds"
	     << " " << P_bounds.ll << " - "
	     << " " << P_bounds.ur
	     << endl
	     << "    scale " << P_scale
	     << endl
	     << "    background "
	     << ( P_background == NULL ? "none" :
	          P_background->name )
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
    cout.write ( (const char *) data, length );
    if ( ! cout )
	return CAIRO_STATUS_WRITE_ERROR;
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
	    cerr << "Cannot understand -" << name
	         << endl << endl;
	    exit (1);
	}

	++ argv, -- argc;
    }

    // Exit with error status unless there is at most
    // one program argument left.

    if ( argc > 2 )
    {
	cerr << "Wrong number of arguments."
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
	    cerr << "Cannot open " << file << endl;
	    exit ( 1 );
	}
	in = & fin;
    }

    init_layout ( 1, 1 );
    if ( debug )
    {
	cerr << endl << "Default Fonts:" << endl;
	for ( font_it it = font_dict.begin();
	      it != font_dict.end(); ++ it )
	    print_font ( it->second );

	cerr << endl << "Default Strokes:" << endl;
	for ( stroke_it it = stroke_dict.begin();
	      it != stroke_dict.end(); ++ it )
	    print_stroke ( it->second );
    }

    page = cairo_pdf_surface_create_for_stream
		( write_to_cout, NULL,
		  72 * L_width, 72 * L_height );
    context = cairo_create ( page );

    section s = LAYOUT;
    int page_number = 0;
    while ( s == LAYOUT )
    {
	cairo_pdf_surface_set_size
	    ( page, 72 * L_width, 72 * L_height ); 
	assert (    cairo_status ( context )
		 == CAIRO_STATUS_SUCCESS );

	double left = L_margins.left;
	double top = L_margins.top + title_height;
	int curR = 0, curC = 0;
	while ( true )
	{
	    s = read_section ( * in );
	    if ( s != PAGE ) break;
	    if ( curR == 0 && curC == 0 )
	    {
	        draw_head_or_foot
		    ( title, "Title",
		      left, L_margins.top,
                      L_width - L_margins.left
                              - L_margins.right,
		      ++ page_number );
	    }
	    draw_page ( left + curC * P_width,
	                top + curR * P_height );
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

    dout << bytes << " bytes of pdf available" << endl;

    // Return from main function without error.

    return 0;
}
