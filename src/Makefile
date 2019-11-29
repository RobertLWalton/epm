# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Fri Nov 29 02:08:37 EST 2019
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score

epm_score:	epm_score.cc
	g++ -o epm_score epm_score.cc

clean:
	rm -f epm_score

