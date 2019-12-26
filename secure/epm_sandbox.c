/* Educational Problem Manager Sandbox Program 
 *
 * File:	epm_sandbox.c
 * Authors:	Bob Walton (walton@deas.harvard.edu)
 * Date:	Thu Dec 26 01:28:40 EST 2019
 *
 * The authors have placed this program in the public
 * domain; they make no warranty and accept no liability
 * for this program.
 *
 * Adaped from hpcm_sandbox.c by the same author, which
 * was also placed in the public domain.
 */

#define _GNU_SOURCE
    /* Without this strsignal breaks with segmentation
     * fault.
     */

#include <stdlib.h>
#include <stdio.h>
#include <limits.h>
#include <string.h>
#include <ctype.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/resource.h>
#include <sys/wait.h>
#include <sys/param.h>
#include <sys/signal.h>
#include <fcntl.h>
#include <errno.h>
#include <pwd.h>

char documentation [] =
"epm_sandbox [options] program argument ...\n"
"\n"
"    This program first checks its arguments for\n"
"    options that set resource limits:\n"
"\n"
"      -cputime N     Cpu Time in Seconds\n"
"      -space N       Virtual Address Space Size,\n"
"                     in Bytes\n"
"      -datasize N    Data Area Size in Bytes\n"
"      -stacksize N   Stack Size in Bytes\n"
"      -filesize N    Output File Size in Bytes\n"
"      -core N        Core Dump Size in Bytes\n"
"      -openfiles N   Number of Open Files\n"
"      -processes N   Number of Processes\n"
"\n"
"    Here N is a non-negative decimal integer that\n"
"    can end with `k' to multiply it by 1024 or `m'\n"
"    to multiply it by 1024 * 1024 or `g' to multiply\n"
"    it by 1024 * 1024 * 1024 (`g' is only valid on\n"
"    64 bit computers).\n"
"\n"
"    There are also two other options:\n"
"\n"
"      -status STATUS-FILE\n"
"      -env ENV-PARAM\n"
"\n"
"    With a STATUS-FILE the status of the child proc-\n"
"    ess that executes `program ...' is written into\n"
"    STATUS-FILE as a single line containing the\n"
"    following fields, with fields separated by a\n"
"    single space character:\n"
"\f\n"
"        STATE      One of:\n"
"                     R  running\n"
"                     E  terminated with exit code\n"
"                     S  terminated with signal\n"
"        PID        process ID\n"
"        CPUTIME    -cputime limit (seconds)\n"
"        SPACE      -space limit (bytes)\n"
"        DATASIZE   -datasize limit (bytes)\n"
"        STACKSIZE  -stacksize limit (bytes)\n"
"        FILESIZE   -filesize limit (bytes)\n"
"        CORE       -core limit (bytes)\n"
"        OPENFILES  -openfiles limit\n"
"        PROCESSES  -processes limit\n"
"        EXITCODE   terminating exit code\n"
"        SIGNAL     terminating signal code\n"
"        USERTIME   user mode cpu time (sec)\n"
"        SYSTIME    system mode cpu time (sec)\n"
"        MAXRSS     max resident set size (kilobytes)\n"
"        STATE      copy of STATE.\n"
"\n"
"    All fields are integer except USERTIME and"
				" SYSTIME\n"
"    are floating point and unset limit fields are\n"
"    `unlimited'.  EXITCODE, SIGNAL, USERTIME,"
                                         " SYSTIME,\n"
"    and MAXRSS are unused and 0 if STATE is R.\n"
"    If beginning and ending STATE do not match,\n"
"    or STATUS-FILE is empty, re-read file as a race\n"
"    condition is likely.\n"
"\n"
"    Without any -env options, the environment in\n"
"    which `program ...' executes is empty.  There\n"
"    can be zero or more `-env ENV-PARAM' options,\n"
"    each of which adds its ENV-PARAM to the environ-\n"
"    ment in which `program ...' executes.\n"
"\f\n"
"    If `program' is not in the current directory,\n"
"    it is looked up using epm_sandbox's environment\n"
"    PATH variable after the manner of the UNIX shell\n"
"    commands.  If epm_sandbox is executed with root\n"
"    effective user ID, then `program', the directory\n"
"    containing it, and any directories needed to\n"
"    locate `program', must all have o+x permission.\n"
"    If `program' is in the current directory, only\n"
"    it and the current directory need o+x permis-\n"
"    sion.\n"
"\n"
"    Epm_sandbox forks, the parent waits for the\n"
"    child, and the child executes `program ...'.  If\n"
"    epm_sandbox's effective user ID is `root', any\n"
"    supplementary groups are eliminated from the\n"
"    child and the real and effective user and group\n"
"    IDs of the child are changed to those of the\n"
"    `sandbox' account, as looked up in /etc/passwd.\n"
"\n"
"    Normally the `sandbox' user is not allowed to\n"
"    log in and owns no useful files or directories.\n"
"\n"
"    The child's resource limits and environment are\n"
"    set according to the options and defaults, and\n"
"    the program is executed with the given argu-\n"
"    ments.\n"
"\n"
"    If the child terminates with a SIGKILL signal\n"
"    and USERTIME + SYSTIME > CPUTIME, the signal\n"
"    is changed to SIGXCPU.\n"
"\n"
"    The parent returns the child exit code if the\n"
"    child does not terminate with a signal, and\n"
"    returns 128 + the possibly changed signal number\n"
"    if the child does terminate with a signal.\n"
"\f\n"
"    If the child terminates with a signal, and\n"
"    there is NO STATUS-FILE, the parent prints an\n"
"    error message using the possibly changed signal\n"
"    number and strsignal(3) to standard error.\n"
"\n"
"    Epm_sandbox will write an error message on the\n"
"    standard error output and exit with exit code 1\n"
"    if any system call or option is in error.\n"
;

void errno_exit ( char * m )
{
    fprintf ( stderr, "epm_sandbox: system call error:"
                      " %s:\n    %s\n",
		      m, strerror ( errno ) );
    exit ( 1 );
}

uid_t euid, egid;
    /* Effective IDs of epm_sandbox. */

int debug = 0;

int is_executable ( const char * program )
{
    struct stat s;
    if ( stat ( program, & s ) < 0 )
    {
        if ( debug ) fprintf
	    ( stderr, "could not stat %s\n", program );
	return 0;
    }
    else if ( euid == 0 )
	return ( ( S_IXOTH & s.st_mode ) != 0 );
    else if ( euid == s.st_uid
	      &&
	      ( S_IXUSR & s.st_mode ) != 0 )
	return 1;
    else if ( egid == s.st_gid
	      &&
	      ( S_IXGRP & s.st_mode ) != 0 )
	return 1;
    else if ( ( S_IXUSR & s.st_mode ) != 0 )
	return 1;
    else
        return 0;
}

/* Information for STATUS-FILE. */

pid_t child;  /* Child PID. */

/* Options with default values. */

rlim_t cputime = RLIM_INFINITY;
rlim_t space = RLIM_INFINITY;
rlim_t datasize = RLIM_INFINITY;
rlim_t stacksize = RLIM_INFINITY;
rlim_t filesize = RLIM_INFINITY;
rlim_t core = RLIM_INFINITY;
rlim_t openfiles = RLIM_INFINITY;
rlim_t processes = RLIM_INFINITY;

/* Write status line into status_fd.
 */
int status_fd;  /* status_file descriptor */
int write_status
    ( char STATE, int EXITCODE, int SIGNAL,
      double USERTIME, double SYSTIME, long MAXRSS )
{
    char status_line[4000];
    char * p = status_line;
    p += sprintf ( p, "%c %ld", STATE, (long) child );

    if ( cputime == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) cputime );

    if ( space == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) space );

    if ( datasize == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) datasize );

    if ( stacksize == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) stacksize );

    if ( filesize == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) filesize );

    if ( core == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) core );

    if ( openfiles == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) openfiles );

    if ( processes == RLIM_INFINITY )
        p += sprintf ( p, " unlimited" );
    else
        p += sprintf
	    ( p, " %lu", (unsigned long ) processes );

    p += sprintf ( p, " %d %d %.6f %.6f %lu %c\n",
                   EXITCODE, SIGNAL, USERTIME, SYSTIME,
		   MAXRSS, STATE );
    if ( ftruncate ( status_fd, 0 ) < 0 )
	errno_exit ( "truncating STATUS-FILE" );
    if ( write ( status_fd, status_line,
                            p - status_line ) < 0 )
	errno_exit ( "writing STATUS-FILE" );
}


/* Main program.
*/
int main ( int argc, char ** argv )
{
    rlim_t max_value = RLIM_INFINITY;

    const char * status_file = NULL;

    int env_max_size = 100;
    char ** env =
        realloc ( NULL,   ( env_max_size + 1 )
	                * sizeof (const char *) );
        /* Environment of program.  Expanded if
	 * necessary in units of 100 entries. */
    int env_size = 0;
    
    char * program = NULL;
        /* Program name after lookup using PATH. */

    euid = geteuid();
    egid = getegid();
    
    /* Consume the options. */

    int index = 1;
	/* Index of next argv to process. */

    while ( index < argc )
    {
        rlim_t * option;

        if ( strcmp ( argv[index], "-debug" )
	     == 0 )
	{
	    debug = 1;
	    ++ index;
	    continue;
	}
        else if ( strcmp ( argv[index], "-status" )
	     == 0 )
	{
	    ++ index;
	    if ( index >= argc )
	    {
		fprintf ( stderr,
			  "epm_sandbox: Too few"
			  " arguments\n" );
		exit (1);
	    }
	    status_file = argv[index++];
	    continue;
	}
        else if ( strcmp ( argv[index], "-env" )
	     == 0 )
	{
	    ++ index;
	    if ( index >= argc )
	    {
		fprintf ( stderr,
			  "epm_sandbox: Too few"
			  " arguments\n" );
		exit (1);
	    }
	    if ( env_size >= env_max_size )
	    {
	        env_max_size += 100;
	        env = realloc
		    ( env,   ( env_max_size + 1 )
	                   * sizeof (const char *) );
	    }
	    env[env_size++] = argv[index++];
	    continue;
	}

	/* Remaining options set `option' and fall
	 * through.
	 */
        else if ( strcmp ( argv[index], "-cputime" )
	     == 0 )
	    option = & cputime;
        else if ( strcmp ( argv[index], "-space" )
	     == 0 )
	    option = & space;
        else if ( strcmp ( argv[index], "-datasize" )
	     == 0 )
	    option = & datasize;
        else if ( strcmp ( argv[index], "-stacksize" )
	     == 0 )
	    option = & stacksize;
        else if ( strcmp ( argv[index], "-filesize" )
	     == 0 )
	    option = & filesize;
        else if ( strcmp ( argv[index], "-core" )
	     == 0 )
	    option = & core;
        else if ( strcmp ( argv[index], "-openfiles" )
	     == 0 )
	    option = & openfiles;
        else if ( strcmp ( argv[index], "-processes" )
	     == 0 )
	    option = & processes;
        else break;

	/* Come here to process numeric options. */

	++ index;

	if ( index >= argc )
	{
	    fprintf ( stderr,
	              "epm_sandbox: Too few"
		      " arguments\n" );
	    exit (1);
	}

	/* Compute the number. */

	{
	    char * s = argv[index];
	    rlim_t n = 0;
	    int c;
	    int digit_found = 0;

	    while ( c = * s ++ )
	    {
	        if ( c < '0' || c > '9' ) break;
		digit_found = 1;

		if ( n > ( ( max_value - 9 ) / 10 ) )
		{
		    fprintf ( stderr,
			      "epm_sandbox: Number"
			      " too large: %s\n",
			      argv[index] );
		    exit (1);
		}
		n = 10 * n + ( c - '0' );
	    }

	    if ( c == 'g' )
	    {
		if ( sizeof ( rlim_t ) <= 4 )
		{
		    fprintf ( stderr,
			      "epm_sandbox: g not"
			      " valid on 32 bit"
			      " computer: %s\n",
			      argv[index] );
		    exit (1);
		}
	        c = * s ++;
		if ( n > ( max_value >> 30 ) )
		{
		    fprintf ( stderr,
			      "epm_sandbox: Number"
			      " too large: %s\n",
			      argv[index] );
		    exit (1);
		}
		n <<= 30;
	    } else if ( c == 'm' )
	    {
	        c = * s ++;
		if ( n > ( max_value >> 20 ) )
		{
		    fprintf ( stderr,
			      "epm_sandbox: Number"
			      " too large: %s\n",
			      argv[index] );
		    exit (1);
		}
		n <<= 20;
	    } else if ( c == 'k' )
	    {
	        c = * s ++;
		if ( n > ( max_value >> 10 ) )
		{
		    fprintf ( stderr,
			      "epm_sandbox: Number"
			      " too large: %s\n",
			      argv[index] );
		    exit (1);
		}
		n <<= 10;
	    }

	    if ( c != 0 || ! digit_found )
	    {
		fprintf ( stderr,
			  "epm_sandbox: Bad number:"
			  " %s\n",
			  argv[index] );
		exit (1);
	    }

	    * option = n;
        }

	++ index;
    }

    /* If the program name is not left, or if it 
       matches -doc*, print doc. */

    if (    index >= argc
	 || strncmp ( "-doc", argv[index], 4 ) == 0 )
    {
	FILE * out = popen ( "less -F", "w" );
	fputs ( documentation, out );
	pclose ( out );
	exit ( 1 );
    }

    /* Look up program in PATH. */

    if ( is_executable ( argv[index] ) )
        program = argv[index];
    else
    {
	const char * PATH = getenv ( "PATH" );
	if ( PATH == NULL || PATH[0] == 0 )
	{
	    fprintf ( stderr,
		      "epm_sandbox: PATH environment"
		      " variable is missing or"
		      " empty\n" );
	    exit (1);
	}
	program = malloc
	    (   strlen ( PATH )
	      + strlen ( argv[index] ) + 1 );
	const char * p = PATH;
	int found = 0;
	while ( ! found && * p != 0 )
	{
	    char * q = program;
	    while ( * p && * p != ':' )
		* q ++ = * p ++;
	    if ( q == program ) * q ++ = '.';
	    if ( * p == ':' ) ++ p;
	    * q ++ = '/';
	    strcpy ( q, argv[index] );

	    found = is_executable ( program );
	}

	if ( ! found )
	{
	    fprintf ( stderr,
		      "epm_sandbox: could not find"
		      " executable program file %s\n"
		      "    in current directory or"
		      " PATH directories\n",
		      argv[index] );
	    exit (1);
	}
    }

    child = fork ();

    if ( child < 0 )
	errno_exit ( "fork" );

    if ( child != 0 )
    {
	/* Parent executes this. */

	if ( status_file != NULL )
	{
	    status_fd =
		open ( status_file,
		       O_WRONLY|O_CREAT|O_TRUNC,
		       0640 );

	    if ( status_fd < 0 )
		errno_exit
		    ( "opening STATUS-FILE" );

	    write_status ( 'R', 0, 0, 0, 0, 0 );
	}

	int status;

	if ( wait ( & status ) < 0 )
	    errno_exit ( "wait" );

	int signaled = WIFSIGNALED ( status );
	int SIGNAL =
	    ( signaled ? WTERMSIG ( status ) : 0 );
	int EXITCODE =
	    ( signaled ? 0 : WEXITSTATUS ( status ) );

	struct rusage usage;
	if ( getrusage ( RUSAGE_CHILDREN,
			 & usage ) < 0 )
	    errno_exit
		( "genrusage RUSAGE_CHILDREN" );

	double USERTIME = usage.ru_utime.tv_sec
		        + 1e-6 * usage.ru_utime.tv_usec;
	double SYSTIME = usage.ru_stime.tv_sec
		       + 1e-6 * usage.ru_stime.tv_usec;

        if ( signaled && SIGNAL == SIGKILL
	              && USERTIME + SYSTIME >= cputime )
	    SIGNAL = SIGXCPU;


	long MAXRSS = usage.ru_maxrss;

	if ( status_file != NULL )
	{
	    write_status ( ( signaled ? 'S' : 'E' ),
	                   EXITCODE, SIGNAL,
			   USERTIME, SYSTIME,
			   MAXRSS );
	    if ( close ( status_fd ) < 0 )
		errno_exit
		    ( "closing STATUS-FILE" );
	}
	else if ( signaled )
	    fprintf ( stderr,
		      "epm_sandbox: Child"
		      " terminated with signal:"
		      " %s\n",
		      strsignal ( SIGNAL ) );

	if ( signaled )
	    exit ( 128 + SIGNAL );
	else
	    exit ( EXITCODE );
    }

    /* Child continues execution here.
    */

    if ( euid == 0 ) {

        /* Execute if effective user is root. */

	gid_t groups [1];

	/* Clear the supplementary groups. */

	if ( setgroups ( 0, groups ) < 0 )
	    errno_exit ( "root setgroups" );

	/* Set the effective user and group ID to
	   that of the `sandbox' user.
	*/
	while ( 1 )
	{
	    struct passwd * p;

            p = getpwent ();

	    if ( p == NULL )
	    {
	        fprintf ( stderr, "epm_sandbox: Could"
				  " not find `sandbox'"
		                  " in /etc/passwd\n" );
		exit ( 1 );
	    }

	    if ( strcmp ( p->pw_name, "sandbox" )
	         == 0 )
	    {
		/* Set group ID first so as not to
		 * disturb root euid prematurely.
		 */
		if ( setregid ( p->pw_gid , p->pw_gid )
		     < 0 )
		     errno_exit ( "root setregid" );
		if ( setreuid ( p->pw_uid, p->pw_uid )
		     < 0 )
		     errno_exit ( "root setreuid" );

		endpwent ();
		break;
	    }
	}

	/* End root execution. */
    }

    if ( debug )
    {
        fprintf ( stderr,
	          "epm_sandbox: uid is now %d\n",
		  getuid() );
        fprintf ( stderr,
	          "epm_sandbox: gid is now %d\n",
		  getgid() );
        fprintf ( stderr,
	          "epm_sandbox: euid is now %d\n",
		  geteuid() );
        fprintf ( stderr,
	          "epm_sandbox: egid is now %d\n",
		  getegid() );
    }

    {
        /* Set the resource limits */

	struct rlimit limit;

	limit.rlim_cur = cputime;
	limit.rlim_max = (cputime == RLIM_INFINITY ?
	                  cputime : cputime + 5 );
	/* rlim_cur is when the SIGXCPU signal is sent,
	 * and rlim_max is when the SIGKILL signal is
	 * sent.  Cases have been observed in which
	 * the usage.ru_{u,s}time sum as read by this
	 * program does not quite exceed the rlim_{cur,
	 * max} limit when the SIG{XCPU,KILL} signal is
	 * sent.  SIGKILL is turned into SIGXCPU by code
	 * elsewhere in this program if the usage.ru_
	 * {u,s}time sum exceeds cputime.  To make this
	 * work, we must be sure SIGKILL is sent well
	 * after the sum exceeds the limit, so we add
	 * 5 seconds to rlim_max.
	 */
        if ( setrlimit ( RLIMIT_CPU, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_CPU" );

#	ifdef RLIMIT_AS
	limit.rlim_cur = space;
	limit.rlim_max = space;
        if ( setrlimit ( RLIMIT_AS, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_AS" );
#	endif

	limit.rlim_cur = datasize;
	limit.rlim_max = datasize;
        if ( setrlimit ( RLIMIT_DATA, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_DATA" );

	limit.rlim_cur = stacksize;
	limit.rlim_max = stacksize;
        if ( setrlimit ( RLIMIT_STACK, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_STACK" );

	limit.rlim_cur = filesize;
	limit.rlim_max = filesize;
        if ( setrlimit ( RLIMIT_FSIZE, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_FSIZE" );

	limit.rlim_cur = core;
	limit.rlim_max = core;
        if ( setrlimit ( RLIMIT_CORE, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_CORE" );

	limit.rlim_cur = ( openfiles == RLIM_INFINITY ?
	                   getdtablesize() :
			   openfiles );
	limit.rlim_max = limit.rlim_cur;
        if ( setrlimit ( RLIMIT_NOFILE, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_NOFILE" );

#	ifdef RLIMIT_NPROC
	limit.rlim_cur = ( processes == RLIM_INFINITY ?
			   10000 : processes );
	limit.rlim_max = limit.rlim_cur;
        if ( setrlimit ( RLIMIT_NPROC, & limit ) < 0 )
	    errno_exit
	        ( "setrlimit RLIMIT_NPROC" );
#	endif
    }

    /* Execute program with arguments and optional
       environment.
    */

    execve ( program, argv + index, env );

    /* If execve fails, print error messages. */

    fprintf ( stderr, "epm_sandbox: could not:"
    		      " execute %s\n",
		      argv[index] );
    errno_exit ( "execve" );
}
