// Display Text, Points, Lines, Arcs, Etc.
//
// File:	epm_display.cc
// Authors:	Bob Walton (walton@deas.harvard.edu)
// Date:	Sat Aug 22 22:27:20 EDT 2020
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
using std::min;
using std::max;
using std::string;

extern "C" {
#include <unistd.h>
#include <cairo-pdf.h>
}

// Current page data.
//
struct color
{
    const char * name;
    unsigned value;
};

color colors = {
#    include "epm_colors.h"
    , { "", 0 }, {"", 0 }
        // So we can print groups of 3.
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

bool debug = false;
# define dout if ( debug ) cerr

const char * const documentation[2] = { "\n"
"hpcm_display [-RxC] [-debug] [file]\n"
"\n"
"    This program displays line drawings defined\n"
"    in the given file or standard input.  The file\n"
"    consists of pages each consisting of command\n"
"    lines followed by a line containing just `*'.\n"
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
"    Global Parameter Commands:\n"
"    ------ --------- --------\n"
"\n"
"        background COLOR\n"
"          Sets the background color for the entire\n"
"          page.  Default is `white'.\n"
"\n"
"        scale S\n"
"          s is the Y/X scale ratio for the scales\n"
"          of body coordinates; defaults to 1.\n"
"\n"
"        margin ALL\n"
"        margin VERTICAL HORIZONTAL\n"
"        margin TOP RIGHT BOTTOM LEFT\n"
"          Margins of space that surround the bound-\n"
"          ing box of the body coordinates.  Lines\n"
"          and text may overflow the bounding box\n"
"          into these margins.  The lengths may not\n"
"          be body coordinates.\n"
"\n"
"          If multiple `margin' commands are given,\n"
"          the maximum of each of the four margins\n"
"          is used.  Default is `margin 0.25in'.\n"
"\n"
"        layout R C [HEIGHT WIDTH]\n"
"          Causes multiple logical pages to be put on\n"
"          each physical page, shrinking the logical\n"
"          pages by an appropriate factor.  R and C\n"
"          are very small integers; there are R rows\n"
"          of C columns each of logical pages per\n"
"          physical page.\n"
"\n"
"          HEIGHT and WIDTH are the logical page\n"
"          height and width, and default to 11in and\n"
"          8.5in, respectively.\n"
"\n"
"          Default: layout 1 1 11in 8.5in\n"
"\n"          
"    These global commands can be given anywhere in\n"
"    the input, and are NOT placed on any list.\n"
"    unless otherwise indicated, the last value\n"
"    given for a parameter is used.\n"
"\n"
"\n"
"    List Switching Commands:\n"
"    ---- --------- --------\n"
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
"	   the top of the level stack.  Pop the\n"
"          level stack.\n"
"\n"
"        There is an implicit `level 50' command\n"
"        just before the beginning of input.  A page\n"
"        need not have any head or foot.  Body coor-\n"
"        dinates are not permitted in the head or\n"
"        foot.\n"
"\n"
"\n"
"    Context Parameter Commands:\n"
"    ------- --------- --------\n"
"\n"
"        font [COLOR] [OPTIONS] [FAMILY] SIZE [SPACE]\n"
"	    SIZE is the em square size of the font\n"
"                and is normally given in points;\n"
"                capital letters are typically 0.7em\n"
"                high and lower case x is typically\n"
"                0.5em high; defaults:\n"
"                   14pt for head\n"
"                   10pt for other lists\n"
"           SPACE is the horizontal space\n"
"                 between text lines; default 1.15em\n"
"	    OPTIONS is some of:\n"
"		b for bold\n"
"		i for italic\n"
"	    FAMILY is one of:\n"
"		serif (the default)\n"
"		sans-serif\n"
"		monospace\n"
"\n"
"        Context parameter commands can occur in any\n"
"        list and apply to subsequent commands in the\n"
"        list until the next context parameter com-\n"
"        of the same command name.\n"
"\n"
"\n"
"    Head and Foot Commands:\n"
"    ---- --- ---- --------\n"
"\n"
"        text TEXT1\\TEXT2\\TEXT3\n"
"           Display text as a line.  TEXT1 is left\n"
"           adjusted; TEXT2 is centered; TEXT3 is\n"
"           right adjusted.\n"
"\n"
"        space SPACE\n"
"           Insert whitespace of SPACE height.\n"
"\n"
"        These commands can only appear on the head or\n"
"        or foot list.\n"
"\n"
"\n"
"    Body Commands:\n"
"    ---- --------\n"
"\n"
"\n"
"        text [COLOR] [OPTIONS] [SPACE] X Y TEXT\\...\n"
"          Display text at point (X,Y) which must\n"
"          be in body coordinates.\n"
"          OPTIONS are:\n"
"            t  display 0.15em below y\n"
"            b  display 0.15em above y\n"
"               If neither option, vertically\n"
"               center on y.\n"
"            l  display 0.25em to left of x\n"
"            r  display 0.25em to right of x\n"
"               If neither option, horizontally\n"
"               center on x.\n"
"            x  make the bounding box of the text\n"
"               white\n"
"            c  make the bounding circle of the text\n"
"               white\n"
"            o  outline the bounding box or circle\n"
"               with a line of width 1pt\n"
"          SPACE is the horizontal space between\n"
"          text lines; default 1.15em.\n"
"\n"
"          The TEXT is broken into lines separated\n"
"          by backslashes (\\).  Lines are always\n"
"          centered with respect to each other.\n"
"\n"
"        path [COLOR] [OPTION] [START STOP] [X Y]"
                                        " [WIDTH]\n"
"          Begin a path at (X,Y) which must be in\n"
"          body coordinates.\n"
"          WIDTH is the line width (default 1pt).\n"
"          OPTION is one of:\n"
"            .   dotted line\n"
"            -   dashed line\n"
"            s   fill with solid color\n"
"            x   fill with cross hatch\n"
"            /   fill with right leaning hatch\n"
"            \\   fill with left leaning hatch\n"
"          START/STOP specify special path ends as\n"
"          follows:\n"
"            n   nothing special\n"
"            a   arrow\n"
"            r   reverse arrow\n"
"            d   dot (size 3 * line width)\n"
"\n"
"          The path consists of the p command and\n"
"          subsequent path continuation commands in\n"
"          its list until the next p command.\n"
"\n"
"          If X, Y not given, the next command in\n"
"          path must be `a' or `e'.\n"
"\n"
"        line X Y\n"
"          Continue path by straight line to (X,Y)\n"
"          which must be in body coordinates.\n"
"\n"
"        curve X1 Y1 X2 Y2 X3 Y3\n"
"          Continue path by a curve to (X3,Y3)\n"
"          with control points (X1,Y1) and (X2,Y2)\n"
"          where all points must be in body coordi-\n"
"          nates.\n"
"\n"
"        arc XC YC R [G1 G2]\n"
"          Start or continue path by a circular arc\n"
"          with center (XC,YC), radius R, and if\n"
"          given start point at angle G1 and stop\n"
"          point at angle G2.  If G1, G2 are not\n"
"          given, this must be the only command\n"
"          in a path for which X, Y were NOT given\n"
"          in the path's p command.  In this case\n"
"          the entire path is the circle drawn by\n"
"          this command.\n"
"\n"
"          G1 and G2 are in degrees and must not be\n"
"          equal.  If G1 < G2 the arc goes counter-\n"
"          clockwise; if G2 < G1 the arc goes clock-\n"
"          wise. Any integer multiple of 360 added\n"
"          to G1 or G2 does not affect the designa-\n"
"          ted points.\n"
"\n"
"        ellipse XC YC RX RY A [G1 G2]\n"
"          Like `a' but for an elliptical arc drawn\n"
"          as follows.\n"
"\n"
"          First the arc is drawn as per `a' with\n"
"          unit radius.  Then a transformation is\n"
"          applied centered on (XC,YC).  The X-axis\n"
"          is expanded by scale factor RX, the Y-axis\n"
"          is expanded by scale factor RY, and then\n"
"          the whole is rotated by `A' degrees.\n"
"\n"
"    The -RxC option overrides the `layout' command.\n"
} ;

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

ostream & s << ( ostream & s, const vector & v )
{
    return s << "(" << v.x << "," << v.y << ")";
}

struct command { command * next; char command; };

enum options {
    BOLD		= 1 << 0,
    ITALIC		= 1 << 1,
    TOP 		= 1 << 2,
    BOTTOM		= 1 << 3,
    LEFT		= 1 << 4,
    RIGHT		= 1 << 5,
    BOX_WHITE		= 1 << 6,
    CIRCLE_WHITE	= 1 << 7,
    OUTLINE		= 1 << 8,
    DOTTED		= 1 << 9,
    DASHED		= 1 << 10,
    FILL_SOLID		= 1 << 11,
    FILL_CROSS		= 1 << 12,
    FILL_RIGHT		= 1 << 13,
    FILL_LEFT		= 1 << 14,
    START_NONE		= 1 << 15,
    START_ARROW		= 1 << 16,
    START_REVERSE	= 1 << 17,
    START_DOT		= 1 << 18,
    STOP_NONE		= 1 << 19,
    STOP_ARROW		= 1 << 20,
    STOP_REVERSE	= 1 << 21,
    STOP_DOT		= 1 << 22
};
char optchar[21] = "bitblrxco.-sx/\\nardnard";
struct font : public command
{
    color c;
    options o;
	static options opt = ( BOLD + ITALIC );
    double size;
    double space;  // in ems
    const char * family_name;
    const char * cairo_family_name;
        // These last are not allocated.
};
struct text : public command
{
    color c;
    options o;
	static options opt =
	    ( TOP + BOTTOM + LEFT + RIGHT +
	      BOX_WHITE + CIRCLE_WHITE + OUTLINE );
    double space;  // in ems.
    vector p;
    const char * text;
    text ( void )
    {
        this->text = NULL;
    }
    ~ text ( void )
    {
        delete[] this->text;
    }
};
struct path : public command
{
    color c;
    options o;
	static options opt =
	    ( DOTTED + DASHED +
	      FILL_SOLID + FILL_CROSS +
	      FILL_RIGHT + FILL_LEFT );
	static options start_opt =
	    ( START_ARROW + START_REVERSE + START_DOT );
	static options stop_opt =
	    ( START_ARROW + START_REVERSE + START_DOT );
    vector p;
    double width;
};
struct line : public command
{
    vector p;
};
struct curve : public command
{
    vector p1, p2, p3;
};
struct arc : public command
{
    vector c;
    double r;
    double g1, g2;
};
struct ellipse : public command
{
    vector c;
    vector r;
    double a;
    double g1, g2;
};
struct dot : public command
{
    color c;
    vector p;
    double r;
};

// Layout Parameters
//
color L_background;
double L_scale;
double L_top, L_right, L_bottom, L_left; // In inches.
double L_height, L_width;
int R, C;

struct font
{
    char name[100];
    color c;
    options o;
    char * family;
    char cairo_family[100];
    double size;
    double space;
};

struct line
{
    char name[100];
    color c;
    options o;
    double width;
};

typedef map<const char *, const font *> font_dt;
typedef map<const char *, const line *> line_dt;
font_dt font_dict;
line_dt line_dict;
typedef font_dt::iterator font_it;
typedef line_dt::iterator line_it;

// Make a new font with given name, discarding any
// previous font with that name.
//
make_font ( const char * name, color c, options o,
            const char * family,
	    double size, double space )
{
    font_it it = font_dict.find ( name );
    if ( it != font_dict.end() )
        font_dict.erase ( it );
    font f * = new font;
    assert (    strlen ( name ) + 1
             <= sizeof ( f->name ) );
    strcpy ( f->name, name );
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

// Make a new line with given name, discarding any
// previous line with that name.
//
make_line ( const char * name, color c, options o,
	    double width )
{
    line_it it = line_dict.find ( name );
    if ( it != line_dict.end() )
        line_dict.erase ( it );
    line l * = new line;
    assert (    strlen ( name ) + 1
             <= sizeof ( l->name ) );
    strcpy ( l->name, name );
    l->c = c;
    l->o = o;
    l->width = width;

    line_dict[l->name] = l;
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

    for ( line_dt::iterator it = line_dict.begin();
          it != line_dict.end(); ++ it )
        delete (line *) it->second;

    font_dictionary.clear();
    line_dictionary.clear();

    black = find_color ( "black" );

    if ( C == 1 )
    {
        make_font ( "large-bold", black,
	            BOLD, "serif",
	            14.0/72, 1.15 );
        make_font ( "bold", black,
	            BOLD, "serif",
	            12.0/72, 1.15 );
        make_font ( "small-bold", black,
	            BOLD, "serif",
	            10.0/72, 1.15 );
        make_font ( "large", black,
	            0, "serif",
	            14.0/72, 1.15 );
        make_font ( "normal", black,
	            0, "serif",
	            12.0/72, 1.15 );
        make_font ( "small", black,
	            0, "serif",
	            10.0/72, 1.15 );
	make_line ( "wide", blank,
		    0, 2.0/72 );
	make_line ( "normal", blank,
		    0, 1.0/72 );
	make_line ( "narrow", blank,
		    0, 0.5/72 );
	make_line ( "wide-dashed", blank,
		    DASHED, 2.0/72 );
	make_line ( "normal-dashed", blank,
		    DASHED, 1.0/72 );
	make_line ( "narrow-dashed", blank,
		    DASHED, 0.5/72 );
    }
    else
    {
        make_font ( "large-bold", black,
	            BOLD, "serif",
	            12.0/72, 1.15 );
        make_font ( "bold", black,
	            BOLD, "serif",
	            10.0/72, 1.15 );
        make_font ( "small-bold", black,
	            BOLD, "serif",
	            8.0/72, 1.15 );
        make_font ( "large", black,
	            0, "serif",
	            12.0/72, 1.15 );
        make_font ( "normal", black,
	            0, "serif",
	            10.0/72, 1.15 );
        make_font ( "small", black,
	            0, "serif",
	            8.0/72, 1.15 );
	make_line ( "wide", blank,
		    0, 1.0/72 );
	make_line ( "normal", blank,
		    0, 0.5/72 );
	make_line ( "narrow", blank,
		    0, 0.25/72 );
	make_line ( "wide-dashed", blank,
		    DASHED, 1.0/72 );
	make_line ( "normal-dashed", blank,
		    DASHED, 0.5/72 );
	make_line ( "narrow-dashed", blank,
		    DASHED, 0.25/72 );
    }
}

// Page Data
//
color background;
double scale;
double top, right, bottom, left; // In inches.
double height, width;


// List of all commands in a page:
//
command * head = NULL, * foot = NULL,
        * level[101] = { NULL };

// Delete all commands in a list and set list NULL.
//
void delete_commands ( command * & list )
{
    while ( list )
    {
	switch ( list->command )
	{
	case 'f':
	    delete (font *) list;
	    break;
	case 't':
	    delete (text *) list;
	    break;
	case 'p':
	    delete (path *) list;
	    break;
	case 'l':
	    delete (line *) list;
	    break;
	case 'c':
	    delete (curve *) list;
	    break;
	case 'a':
	    delete (arc *) list;
	    break;
	case 'e':
	    delete (ellipse *) list;
	    break;
	case 'd':
	    delete (dot *) list;
	    break;
	default:
	    assert ( ! "deleting bad command" );
	}
	list = next;
    }
    list = NULL;
}

void init_page ( void )
{
    background = G_background;
    scale = G_scale;
    height = G_height / R;
    width = G_width / C;
    top = G_top / R;
    bottom = G_bottom / R;
    right = G_right / C;
    left = G_left / C;

    delete_commands ( head );
    delete_commands ( foot );
    for ( int i = 1; i <= 100; ++ i )
        delete_commands ( level[i] );
}

// Print options for debugging:
//
ostream & poptions ( ostream & s, options opt )
{
    if ( opt == 0 ) return s;
    else s << " ";
    int s = strlen ( optchar );
    for ( int i = 0; i < s; ++ i )
        if ( opt & ( 1 << i ) ) s << optchar[i];
    return s;
}


// Print command for debugging.
//
ostream & print_command ( ostream & s, command * com )
{
    switch ( c->command )
    {
    case 'f':
        {
	    font * f = (font *) com;
	    s << "font" << f->c.name
	      << poptions ( f->o & font::opt )
	      << " " << f->size * 72 << "pt"
	      << " " << f->space << "em"
	      << " " << f->p.x
	      << " " << f->p.y
	      << " " << f->family;
	}
	break;
    case 't':
        {
	    text * t = (text *) com;
	    s << "text" << t->c.name
	      << poptions ( t->o & text::opt )
	      << " " << t->space << "em"
	      << " " << t->p.x
	      << " " << t->p.y
	      << " " << t->text;
	}
	break;
    case 'p':
        {
	    path * p = (path *) com;
	    s << "path" << p->c.name
	      << poptions ( p->o & path::opt )
	      << poptions ( p->o & path::start_opt )
	      << poptions ( p->o & path::stop_opt )
	      << " " << p->p.x
	      << " " << p->p.y
	      << " " << p->width * 72 << "pt";
	}
	break;
    case 'l':
        {
	    line * l = (line *) com;
	    s << "line"
	      << " " << l->p.x
	      << " " << l->p.y;
	}
	break;
    case 'c':
        {
	    curve * c =  (curve *) com;
	    s << "curve"
	      << " " << l->p1.x
	      << " " << l->p1.y
	      << " " << l->p2.x
	      << " " << l->p2.y
	      << " " << l->p3.x
	      << " " << l->p3.y;
	}
	break;
    case 'a':
        {
	    arc * a = (arc *) com;
	    s << "arc"
	      << " " << a->c.x
	      << " " << a->c.y
	      << " " << a->r
	      << " " << a->g1
	      << " " << a->g2;
	}
	break;
    case 'e':
        {
	    ellipse * e = (ellipse *) com;
	    s << "ellipse"
	      << " " << e->c.x
	      << " " << e->c.y
	      << " " << e->r.x
	      << " " << e->r.y
	      << " " << e->a
	      << " " << e->g1
	      << " " << e->g2;
	}
	break;
    case 'd':
        {
	    dot * d = (dot *) com;
	    s << "dot"
	      << " " << d->c.name
	      << " " << d->p.x
	      << " " << d->p.y
	      << " " << d->r;
	}
	break;
    default:
	cout << "bad command" << com->command;
    }
    return s;
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
string line;
unsigned line_number = 0;
const int MAX_LINE_LENGTH = 56;

void error ( const char * format, va_list args )
{
    cerr << "ERROR in line " << line_number
         << ":" << endl << "    " << line << endl;
    fprintf ( stderr, "    " );
    p += vsprintf ( p, format, args );
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

const char * whitespace = " \t\f\v\n\r";
bool read_page ( istream & in )
{
    init_page();

    while ( true )
    {
        getline ( in, line );
	if ( ! in )
	{
	    cerr << "WARNING: unexpected end of file;"
	         << " * inserted" << endl;
	    line = "*";
	}
	else
	    ++ line_number;

	// Skip comments.
	//
	size_t first = line.find_first_not_of
	    ( whitespace );
	if ( first == string::npos ) continue;
	if ( line[first] == '#' ) continue;
	if ( line[first] == '!' ) continue;

	istringstream lin ( line );

	string op;
	in >> op;

	if ( op == "background" )
	{
	}
	else if ( op == "scale" )
	{
	}
	else if ( op == "margin" )
	{
	}
	else if ( op == "layout" )
	{
	}
	else if ( op == "head" )
	{
	}
	else if ( op == "foot" )
	{
	}
	else if ( op == "level" )
	{
	}
	else if ( op == "font" )
	{
	}
	else if ( op == "text" )
	{
	}
	else if ( op == "space" )
	{
	}
	else if ( op == "path" )
	{
	}
	else if ( op == "line" )
	{
	}
	else if ( op == "curve" )
	{
	}
	else if ( op == "arc" )
	{
	}
	else if ( op == "ellipse" )
	{
	}
	else if ( op == "dot" )
	{
	}
	else if ( op == "*" )
	    break;
	else
	{
	    error ( "cannot understand %s;"
	            "line ignored", op.c_str() );
	    continue;
	}
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
