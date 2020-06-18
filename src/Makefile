# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Thu Jun 18 14:44:26 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

INSTALL_BIN = ../bin
INSTALL_DEFAULT = ../default

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

install:	epm_score \
                epm_display \
                epm_default_generate \
                epm_default_filter
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	test -d ${INSTALL_DEFAULT}
	test -w ${INSTALL_DEFAULT}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_display
	rm -f ${INSTALL_DEFAULT}/epm_default_generate
	rm -f ${INSTALL_DEFAULT}/epm_default_filter
	cp epm_score ${INSTALL_BIN}
	cp epm_display ${INSTALL_BIN}
	cp epm_default_generate ${INSTALL_DEFAULT}
	cp epm_default_filter ${INSTALL_DEFAULT}
	chmod 755 ${INSTALL_BIN}/epm_score
	chmod 755 ${INSTALL_BIN}/epm_display
	chmod 755 ${INSTALL_DEFAULT}/epm_default_generate
	chmod 755 ${INSTALL_DEFAULT}/epm_default_filter
	-ls -l ${INSTALL_BIN}/epm_score*
	-ls -l ${INSTALL_BIN}/epm_display*
	-ls -l ${INSTALL_DEFAULT}/epm_default_generate*
	-ls -l ${INSTALL_DEFAULT}/epm_default_filter*
