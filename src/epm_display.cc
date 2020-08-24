// Display Text, Points, Lines, Arcs, Etc.
//
// File:	epm_display.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Mon Aug 24 16:12:54 EDT 2020
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
#include <map>
#include <algorithm>
#include <string>
#include <sstream>

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

extern "C" {
#include <unistd.h>
#include <cairo-pdf.h>
}

const size_t MAX_NAME_LENGTH = 40;
const double MAX_BODY_COORDINATE = 1e30 ;

// Current page data.
//
struct color
{
    const char * name;
    unsigned value;
};

color colors[] = {
#    include "epm_colors.h"
    , { "", 0 }, {"", 0 }
        // So we can print groups of 3.
};

const char * families[] = {
    "serif",
    "sans-serif",
    "monospace",
    NULL
};

// Return color from colors array with given name,
// or if none, return color with name "".
//
const color & find_color ( const char * name )
{
    color * c = colors;
    while ( c->name[0] != 0 )
    {
        if ( strcmp ( c->name, name ) == 0 )
	    break;
    }
    return * c;
}

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

bool debug = false;
# define dout if ( debug ) cerr

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
"    of lines (solid, dashed, etc.) that can be used\n"
"    by commands within a page section.\n"
"\n"
"    Each page section specifies one page.\n"
"\n"
"    The page is divided into three sections: a\n"
"    head, a body, and a foot, in that order from\n"
"    top to bottom.\n"
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
"    The head and foot commands control font para-\n"
"    meters and output lines and extra space between\n"
"    lines.\n"
"\n"
"    For body commands, some numbers have units and\n"
"    some are `body coordinates'.  The latter are\n"
"    arbitrary, and are scaled automatically so the\n"
"    bounding box of all points with such coordi-\n"
"    nates, plus some space for margins, fits in the\n"
"    area between the head and foot.  The ratio of\n"
"    the X and Y scales defaults to 1, but can be\n"
"    set otherwise.\n"
"\n"
"    Numbers that represent lengths can have the `pt'\n"
"    suffix meaning `points' (1/72 inch), the `in'\n"
"    suffix meaning `inches', `em' meaning the\n"
"    font point size at the time the number used,\n"
"    or no suffix, meaning the number is a body\n"
"    coordinate (and must be associated with the X\n"
"    or Y axis).  Body coordinates are not permitted\n"
"    in the head or foot.\n"
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
"      section.  The `background', `scale', and"
				" `margin'\n"
"      commands may also be used in a page section to\n"
"      override some layout parameters for the page.\n"
"      The `layout' command must be the first\n"
"      command of a layout section.  Parameter values\n"
"      not given in a layout section revert to their\n"
"      defaults (and NOT to their previous values).\n"
"\n"
"      layout [R C [HEIGHT WIDTH]]\n"
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
"      font NAME SIZE [COLOR] [OPT] [FAMILY] [SPACE]\n"
"        Defines a named font.\n"
"\n"
"        SIZE is the em square size of the font\n"
"             and is normally given in points;\n"
"             capital letters are typically 0.7em\n"
"             high and lower case x is typically\n"
"             0.5em high; defaults:\n"
"                14pt for head\n"
"                10pt for other lists\n"
"        OPT is some of (default is none of):\n"
"            b for bold\n"
"            i for italic\n"
"        FAMILY is one of:\n"
"            serif (the default)\n"
"            sans-serif\n"
"            monospace\n"
"        SPACE is the horizontal space\n"
"              between text lines and must have em\n"
"              units; default 1.15em\n"
"\n"
"      stroke NAME [WIDTH] [COLOR] [OPT]\n"
"        Defines a named line stroke.\n"
"\n"
"        WIDTH is the line width.  Default for non-\n"
"              fill lines is 1pt, and for fill\n"
"              lines is 0.\n"
"        OPT is one of:\n"
"            .   dotted line\n"
"            -   dashed line\n"
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
"        of body coordinates; defaults to 1.\n"
"\n"
"      margin ALL\n"
"      margin VERTICAL HORIZONTAL\n"
"      margin TOP RIGHT BOTTOM LEFT\n"
"        Sets the default margins for the body of\n"
"        each page.  Lengths must be absolute.\n"
"\n"
"    List Switching Commands:\n"
"    ---- --------- --------\n"
"\n"
"      These commands switch lists in page section.\n"
"      A section that does not begin with one of\n"
"      these commands or with a `layout' command is\n"
"      assumed to begin with a `level 50' command.\n"
"\n"
"        head\n"
"          Start or continue the head list.\n"
"\n"
"        foot\n"
"          Start or continue the foot list.\n"
"\n"
"        level N\n"
"          Start or continue list n, where\n"
"          1 <= n <= 100.  Push n into the level\n"
"          stack.\n"
"\n"
"        level\n"
"          Start or continue list m, where m is at\n"
"          the top of the level stack.  Pop the\n"
"          level stack.\n"
"\n"
"        There is an implicit `level 50' command\n"
"        just before the beginning of input.  A page\n"
"        need not have any head or foot.  Body coor-\n"
"        dinates are not permitted in the head or\n"
"        foot.\n"
"\n"
"\n"
"    Head and Foot Commands:\n"
"    ---- --- ---- --------\n"
"\n"
"        These commands can only appear on the head\n"
"        or foot list.\n"
"\n"
"        text TEXT1\\TEXT2\\TEXT3\n"
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
"        by backslashes (\\).  Lines are always\n"
"        centered with respect to each other.\n"
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
"      arc STROKE XC YC RX RY A [G1 G2]\n"
"        Draw an elliptical arc of given line type as\n"
"        follows.\n"
"\n"
"        First the a circular arc of unit radius is\n"
"        drawn with center (0,0).  The begin point\n"
"        is at angle G1 and the end point is at\n"
"        angle G2.  Any multiple of 360 added to G1\n"
"        or G2 does not affect the points, but may\n"
"        affect the arc direction.\n"
"\n"
"        Then transformations are applied.  First\n"
"        the X and Y axes are scaled by RX and RY;\n"
"        second the whole is rotated counter-clock-\n"
"        wise by A degrees, and lastly the whole is\n"
"        translated moving (0,0) to (XC,YC).\n"
"\n"
"        If G1 and G2 are not given they default to\n"
"        0 and 360 respectively.\n"
"\n"
"        A fill option in STROKE is only recognized\n"
"        if G1 and G2 are not given.\n"
"\n"
"      rectangle STROKE XMIN XMAX YMIN YMAX\n"
"        Draw a rectangle with given STROKE type and\n"
"        minumum and maximum X and Y coordinates.\n"
"        Arrow options in STROKE are not recognized.\n"
"\n"
"      ellipse STROKE XMIN XMAX YMIN YMAX\n"
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

ostream & operator << ( ostream & s, const vector & v )
{
    return s << "(" << v.x << "," << v.y << ")";
}

enum options {
    NO_OPTIONS          = 0,
    BOLD		= 1 << 0,
    ITALIC		= 1 << 1,
    DOTTED		= 1 << 2,
    DASHED		= 1 << 3,
    BEGIN_ARROW		= 1 << 4,
    MIDDLE_ARROW	= 1 << 5,
    END_ARROW		= 1 << 6,
    FILL_SOLID		= 1 << 7,
    FILL_CROSS		= 1 << 8,
    FILL_RIGHT		= 1 << 9,
    FILL_LEFT		= 1 << 10,
    TOP 		= 1 << 11,
    BOTTOM		= 1 << 12,
    LEFT		= 1 << 13,
    RIGHT		= 1 << 14,
    BOX_WHITE		= 1 << 15,
    CIRCLE_WHITE	= 1 << 16,
    OUTLINE		= 1 << 17,
};
const char * optchar = "bi.-bmesx/\\tblrxco";

// Print options for debugging:
//
ostream & operator << ( ostream & s, options opt )
{
    if ( opt == 0 ) return s;
    else s << " ";
    int len = strlen ( optchar );
    for ( int i = 0; i < len; ++ i )
        if ( opt & ( 1 << i ) ) s << optchar[i];
    return s;
}

// Layout Parameters
//
color L_background;
double L_scale;
double L_top, L_right, L_bottom, L_left; // In inches.
double L_height, L_width;
int R, C;

options font_options = (options) ( BOLD + ITALIC );
struct font
{
    char name[MAX_NAME_LENGTH+1];
    color c;
    options o;
    const char * family;
    char cairo_family[MAX_NAME_LENGTH+1];
    double size;
    double space;
};

options stroke_options = (options)
    ( DOTTED + DASHED +
      BEGIN_ARROW + MIDDLE_ARROW + END_ARROW +
      FILL_SOLID + FILL_CROSS +
      FILL_RIGHT + FILL_LEFT );
struct stroke
{
    char name[MAX_NAME_LENGTH+1];
    color c;
    options o;
    double width;
};

typedef map<const char *, const font *> font_dt;
typedef map<const char *, const stroke *> stroke_dt;
font_dt font_dict;
stroke_dt stroke_dict;
typedef font_dt::iterator font_it;
typedef stroke_dt::iterator stroke_it;

// Make a new font with given name, discarding any
// previous font with that name.
//
void make_font ( string name,
		 double size,
                 color c, options o,
                 const char * family,
	         double space )
{
    const char * n = name.c_str();
    font_it it = font_dict.find ( n );
    if ( it != font_dict.end() )
        font_dict.erase ( it );
    font * f = new font;
    assert (    strlen ( n ) + 1
             <= sizeof ( f->name ) );
    strcpy ( f->name, n );
    f->c = c;
    f->o = o;
    f->family = family;
    assert (    strlen ( family ) + 7
             <= sizeof ( f->cairo_family ) );
    sprintf ( f->cairo_family, "cairo:%s", family );
    f->size = size;
    f->space = space;

    font_dict[f->name] = f;
}

// Make a new stroke with given name, discarding any
// previous stroke with that name.
//
void make_stroke ( string name,
                   double width, color c, options o )
{
    const char * n = name.c_str();
    stroke_it it = stroke_dict.find ( n );
    if ( it != stroke_dict.end() )
        stroke_dict.erase ( it );
    stroke * s = new stroke;
    assert (    strlen ( n ) + 1
             <= sizeof ( s->name ) );
    strcpy ( s->name, n );
    s->c = c;
    s->o = o;
    s->width = width;

    stroke_dict[s->name] = s;
}

// Print fonts for debugging.
//
void print_fonts ( void )
{
    for ( font_dt::iterator it = font_dict.begin();
          it != font_dict.end(); ++ it )
    {
        const font * f = it->second;
	cout << "font " << f->name
	     << " " << 72 * f->size << "pt"
	     << " " << f->c.name
	     << " " << f->o
	     << " " << f->family
	     << " " << f->space << "em"
	     << endl;
    }
}

// Print strokes for debugging.
//
void print_strokes ( void )
{
    for ( stroke_dt::iterator it = stroke_dict.begin();
          it != stroke_dict.end(); ++ it )
    {
        const stroke * s = it->second;
	cout << "stroke " << s->name
	     << " " << 72 * s->width << "pt"
	     << " " << s->c.name
	     << " " << s->o
	     << endl;
    }
}


void init_layout ( int R, int C )
{
    L_background = find_color ( "white" );
    L_scale = 1.0;
    L_height = 11.0;
    L_width = 8.5;
    L_top = L_right = L_bottom = L_left = 0.25;
    ::R = R;
    ::C = C;

    for ( font_dt::iterator it = font_dict.begin();
          it != font_dict.end(); ++ it )
        delete (font *) it->second;

    for ( stroke_dt::iterator it = stroke_dict.begin();
          it != stroke_dict.end(); ++ it )
        delete (stroke *) it->second;

    font_dict.clear();
    stroke_dict.clear();

    color black = find_color ( "black" );

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

// Page Data
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
struct dot : public command // == 'd'
{
    const stroke * s;
    vector p;
    double r;
};
struct rectangle : public command // == 'r'
{
    stroke * s;
    vector p[4];
};
struct ellipse : public command // == 'p'
{
    stroke * s;
    vector c;
    vector r;
};

color background;
double scale;
double top, right, bottom, left; // In inches.
double height, width;


// List of all commands in a page.  The list variable
// points at the last element which points at the first
// element with its next member.
//
command * head = NULL, * foot = NULL,
        * level[101] = { NULL };


// Print all commands in a list for debugging.
//
void print_commands ( command * & list )
{
    if ( list == NULL ) return;
    command * current = list;
    command * first = current->next;
    do
    {
        command * next = current->next;

	switch ( next->c )
	{
	case 't':
	{
	    text * t = (text *) next;
	    cout << "text " << t->f->name
	         << " " << t->o
		 << " " << t->p
		 << " " << t->t
		 << endl;
	    break;
	}
	case 'S':
	{
	    space * s = (space *) next;
	    cout << "space " << s->s << "in"
		 << endl;
	    break;
	}
	case 's':
	{
	    start * s = (start *) next;
	    cout << "start " << s->s->name
		 << " " << s->p
		 << endl;
	    break;
	}
	case 'l':
	{
	    line * l = (line *) next;
	    cout << "line " << l->p
		 << endl;
	    break;
	}
	case 'c':
	{
	    curve * c = (curve *) next;
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
	    arc * a = (arc *) next;
	    cout << "arc " << a->s->name
	         << " " << a->c
	         << " " << a->r
	         << " " << a->a
	         << " " << a->g1
	         << " " << a->g2
		 << endl;
	    break;
	}
	case 'd':
	{
	    dot * d = (dot *) next;
	    cout << "dot " << d->s->name
	         << " " << d->p
	         << " " << d->r
		 << endl;
	    break;
	}
	case 'r':
	{
	    rectangle * r = (rectangle *) next;
	    cout << "rectangle " << r->s->name
	         << " " << r->p[0]
	         << " " << r->p[1]
	         << " " << r->p[2]
	         << " " << r->p[3]
		 << endl;
	    break;
	}
	case 'p':
	{
	    ellipse * e = (ellipse *) next;
	    cout << "ellipse " << e->s->name
	         << " " << e->c
	         << " " << e->r
		 << endl;
	    break;
	}
	default:
	    cout << "bad command " << next->c
	         << endl;
	}

	current = next;

    } while ( current != first );
}

// Delete all commands in a list and set list NULL.
//
void delete_commands ( command * & list )
{
    command * last = list;
    if ( last == NULL ) return;

    command * current = last;
    do
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
	case 'd':
	    delete (dot *) current;
	    break;
	case 'r':
	    delete (rectangle *) current;
	    break;
	case 'p':
	    delete (ellipse *) current;
	    break;
	default:
	    assert ( ! "deleting bad command" );
	}

	current = next;
    } while ( current != last );

    list = NULL;
}

void init_page ( void )
{
    background = L_background;
    scale = L_scale;
    height = L_height / R;
    width = L_width / C;
    top = L_top / R;
    bottom = L_bottom / R;
    right = L_right / C;
    left = L_left / C;

    delete_commands ( head );
    delete_commands ( foot );
    for ( int i = 1; i <= 100; ++ i )
        delete_commands ( level[i] );
}


// Current command line for error routines.
//
string comline;
unsigned line_number = 0;

void error ( const char * format, va_list args )
{
    cerr << "ERROR in line " << line_number
         << ":" << endl << "    " << comline << endl;
    fprintf ( stderr, "    " );
    vfprintf ( stderr, format, args );
    fprintf ( stderr, "\n" );
}

void error ( const char * format... )
{
    va_list args;
    va_start ( args, format );
    error ( format, args );
    va_end ( args );
}

void fatal ( const char * format... )
{
    cerr << "FATAL ";
    va_list args;
    va_start ( args, format );
    error ( format, args );
    va_end ( args );
    exit ( 1 );
}

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

// If there is currently no token, get the next token.
// Set token = "" if there is no next token.  Return
// true iff there is a next token.
//
bool get_token ( void )
{
    if ( token != "" ) return true;

    lin >> token;
    if ( lin.fail() )
    {
        token = "";
	return false;
    }
    else
        return true;
}

// Get token, get integer from beginning of token,
// set units to remainder of token, set token to
// missing, and return true.
//
// Or return false and leave token alone if there
// is no next token or no integer at beginning of
// next token.
//
bool get_long ( void )
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
bool get_double ( void )
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
bool read_long ( const char * name, long & var,
                 long low, long high,
                 bool missing_allowed = true )
{
    if ( ! get_long() )
    {
        if ( missing_allowed ) return true;
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

// Read double body coordinates without units or scale.
//
bool read_double ( const char * name, double & var,
                   double low, double high,
                   bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( missing_allowed ) return true;
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
        error ( "%s out of range [%f,%f]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

bool read_name ( const char * name,
                 string & var,
                 bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
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
    var = token;
    token = "";
    return true;
}

// Read double length with units.
//
bool read_length ( const char * name, double & var,
                   double low, double high,
                   bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( missing_allowed ) return true;
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
        error ( "%s out of range [%fin,%fin]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

// Read double with em units.
//
bool read_em ( const char * name, double & var,
               double low, double high,
               bool missing_allowed = true )
{
    if ( ! get_double() )
    {
        if ( missing_allowed ) return true;
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
        error ( "%s out of range [%fem,%fem]",
	        name, low, high );
	return false;
    }
    var = token_double;
    return true;
}

bool read_color ( const char * name, color & var,
                  bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    color val = find_color ( token.c_str() );
    if ( val.name == "" )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    var = val;
    token = "";
    return true;
}

bool read_family ( const char * name,
                   const char * & var,
                   bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    const char * val = find_family ( name );
    if ( val == NULL )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    var = val;
    token = "";
    return true;
}

bool read_options ( const char * name,
                    options & var,
		    options allowed_options,
                    bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
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
        if ( missing_allowed ) return true;
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

bool read_font ( const char * name,
	         const font * & var,
	         bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    font_it val = font_dict.find ( token.c_str() );
    if ( val == font_dict.end() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    var = val->second;
    token = "";
    return true;
}

bool read_stroke ( const char * name,
	           const stroke * & var,
	           bool missing_allowed = true )
{
    if ( ! get_token() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    stroke_it val = stroke_dict.find ( token.c_str() );
    if ( val == stroke_dict.end() )
    {
        if ( missing_allowed ) return true;
	error ( "%s missing", name );
	return false;
    }
    var = val->second;
    token = "";
    return true;
}

// Read what is left in lin.  Assumes token == "".
//
void read_text ( const char * name,
		 string & text )
{
    if ( token != "" )
        error ( "%s ignored", token.c_str() );
     
    getline ( lin, text );
}

void check_extra ( void )
{
    if ( get_token() )
	error ( "extra stuff %s... at end of line",
		token.c_str() );
}

// Read section.
//
enum section { END_OF_FILE, LAYOUT, PAGE };
const char * whitespace = " \t\f\v\n\r";
command ** current_list;

// Attach command to end of current_list.  Returns
// false if this cannot be dones because command is
// continuing and has no previous start.  This
// cannot happen if continuing is false.
//
bool attach ( command * com, char c,
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

section read_section ( istream & in )
{

    section s = END_OF_FILE;

    color black = find_color ( "black" );

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
	else
	    ++ line_number;

	// Skip comments.
	//
	size_t first = comline.find_first_not_of
	    ( whitespace );
	if ( first == string::npos ) continue;
	if ( comline[first] == '#' ) continue;
	if ( comline[first] == '!' ) continue;

	lin.str ( comline );
	token = "";

	string op;
	lin >> op;

	if ( s == END_OF_FILE )
	{
	    // First op of section.
	    //
	    if ( op == "layout" )
	        s = LAYOUT;
	    else
	    {
	        s = PAGE;
		init_page();
		current_list = & level[50];
	    }
	}

	if ( op == "layout" && s == LAYOUT )
	{
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
	    color COLOR = black;
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

	    make_font ( NAME, SIZE, COLOR, OPT,
	                FAMILY, SPACE );
	}
	else if ( op == "stroke" && s == LAYOUT )
	{
	    string NAME;
	    double WIDTH = -1;
	    color COLOR = black;
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


	    make_stroke ( NAME, WIDTH, COLOR, OPT );
	}
	if ( op == "background" )
	{
	    color COLOR;
	    if ( ! read_color ( "COLOR", COLOR, false ) )
	        continue;
	    if ( s == LAYOUT )
	        L_background = COLOR;
	    else
	        background = COLOR;
	}
	else if ( op == "scale" )
	{
	    double S;
	    if ( ! read_double
	            ( "S", S, 0.001, 1000, false ) )
	        continue;
	    if ( s == LAYOUT )
	        L_scale = S;
	    else
	        scale = S;
	}
	else if ( op == "margin" )
	{
	    double TOP, RIGHT, BOTTOM, LEFT;
	    if ( ! read_length
	               ( "TOP", TOP, 0, 100, false ) )
	        continue;
	    if ( ! read_length
	               ( "RIGHT", RIGHT, 0, 100 ) )
	        RIGHT = BOTTOM = LEFT = TOP;
	    else
	    if ( ! read_length
	               ( "BOTTOM", BOTTOM, 0, 100 ) )
		BOTTOM = TOP, LEFT = RIGHT;
	    if ( ! read_length
	               ( "LEFT", LEFT, 0, 100, false ) )
		LEFT = RIGHT;
	    if ( s == LAYOUT )
	    {
	        if ( L_top < TOP ) L_top = TOP;
	        if ( L_right < RIGHT ) L_right = RIGHT;
	        if ( L_bottom < BOTTOM ) L_bottom = BOTTOM;
	        if ( L_left < LEFT ) L_left = LEFT;
	    }
	    else
	    {
	        if ( top < TOP ) top = TOP;
	        if ( right < RIGHT ) right = RIGHT;
	        if ( bottom < BOTTOM ) bottom = BOTTOM;
	        if ( left < LEFT ) left = LEFT;
	    }
	}
	else if ( op == "head" && s == PAGE )
	{
	    current_list = & head;
	}
	else if ( op == "foot" && s == PAGE )
	{
	    current_list = & foot;
	}
	else if ( op == "level" && s == PAGE )
	{
	    long N;
	    if ( ! read_long ( "N", N, 1, 100, false ) )
	        continue;
	    current_list = & level[N];
	}
	else if ( op == "text" && s == PAGE )
	{
	    const font * FONT;
	    options OPT = NO_OPTIONS;
	    double X = 0, Y = 0;
	    string TEXT;

	    if ( ! read_font ( "FONT", FONT, false ) )
	        continue;
	    if ( current_list != & head
	         &&
		 current_list != & foot )
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
	else if ( op == "space" && s == PAGE )
	{
	    if ( current_list != & head
	         &&
		 current_list != & foot )
	    {
	        error ( "command not allowed in body" );
		continue;
	    }

	    double SPACE;
	    if ( ! read_length ( "SPACE", SPACE,
	                         0, 100, false ) )
		continue;

	    space * sp = new space;
	    attach ( sp, 'S' );
	    sp->s = SPACE;
	}
	else if ( op == "start" && s == PAGE )
	{
	    if ( current_list == & head
	         ||
		 current_list == & foot )
	    {
	        error ( "command not allowed in head"
		        " or foot" );
		continue;
	    } 
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
	else if ( op == "line" && s == PAGE )
	{
	    if ( current_list == & head
	         ||
		 current_list == & foot )
	    {
	        error ( "command not allowed in head"
		        " or foot" );
		continue;
	    } 
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
	else if ( op == "curve" && s == PAGE )
	{
	    if ( current_list == & head
	         ||
		 current_list == & foot )
	    {
	        error ( "command not allowed in head"
		        " or foot" );
		continue;
	    } 

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
		    continue;
	    }
	    if ( ! OK )
	    {
	        delete c;
		continue;
	    }

	    if ( ! attach ( c, 'c', true, true ) )
	    {
	        delete c;
	        continue;
	    }
	}
	else if ( op == "arc" && s == PAGE )
	{
	}
	else if ( op == "ellipse" && s == PAGE )
	{
	}
	else if ( op == "dot" && s == PAGE )
	{
	}
	else if ( op == "*" )
	    break;
	else
	{
	    error ( "cannot understand %s"
	            " in %s section;"
	            " line ignored",
		    op.c_str(),
		    s == LAYOUT ? "layout" : "page" );
	    continue;
	}
	check_extra();
    }
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
        switch ( c->c )
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
