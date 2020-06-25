# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Wed Jun 24 23:09:31 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

BIN = ../bin
DEFAULT = ../default

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score epm_display \
     epm_default_generate epm_default_filter

epm_score:	epm_score.cc
	g++ -O3 -o epm_score epm_score.cc

epm_display:	epm_display.cc
	g++ -I /usr/include/cairo \
	    -O3 -o epm_display \
	    epm_display.cc -lcairo

epm_monitor:	epm_monitor.cc
	g++ -O3 -pthread -o epm_monitor \
	    epm_monitor.cc

epm_default_generate:	epm_generate.cc
	g++ -O3 -o epm_default_generate epm_generate.cc

epm_default_filter:	epm_filter.cc
	g++ -O3 -o epm_default_filter epm_filter.cc

clean:
	rm -f epm_score \
	      epm_display \
	      epm_monitor \
	      epm_default_generate \
	      epm_default_filter

${BIN}/epm_score:	epm_score
	test -d ${BIN}
	test -w ${BIN}
	rm -f ${BIN}/epm_score
	cp -p epm_score ${BIN}
	chmod 775 ${BIN}/epm_score
	-ls -l ${BIN}/epm_score*

${BIN}/epm_display:	epm_display
	test -d ${BIN}
	test -w ${BIN}
	rm -f ${BIN}/epm_display
	cp -p epm_display ${BIN}
	chmod 775 ${BIN}/epm_display
	-ls -l ${BIN}/epm_display*

${DEFAULT}/epm_default_generate:	\
		epm_default_generate
	test -d ${DEFAULT}
	test -w ${DEFAULT}
	rm -f ${DEFAULT}/epm_default_generate
	cp -p epm_default_generate ${DEFAULT}
	chmod 775 ${DEFAULT}/epm_default_generate
	-ls -l ${DEFAULT}/epm_default_generate*

${DEFAULT}/epm_default_filter:	epm_default_filter
	test -d ${DEFAULT}
	test -w ${DEFAULT}
	rm -f ${DEFAULT}/epm_default_filter
	cp -p epm_default_filter ${DEFAULT}
	chmod 775 ${DEFAULT}/epm_default_filter
	-ls -l ${DEFAULT}/epm_default_filter*

install:	${BIN}/epm_score \
		${BIN}/epm_display \
		${DEFAULT}/epm_default_generate \
		${DEFAULT}/epm_default_filter
