# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Mon Jun 15 03:07:06 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

INSTALL_BIN = ../bin

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score epm_default_generate epm_default_filter \
               epm_default_monitor

epm_score:	epm_score.cc
	g++ -O3 -o epm_score epm_score.cc

epm_default_monitor:	epm_monitor.cc
	g++ -O3 -pthread -o epm_default_monitor \
	    epm_monitor.cc

epm_default_generate:	epm_generate.cc
	g++ -O3 -o epm_default_generate epm_generate.cc

epm_default_filter:	epm_filter.cc
	g++ -O3 -o epm_default_filter epm_filter.cc

clean:
	rm -f epm_score \
	      epm_default_generate \
	      epm_default_filter \
	      epm_default_monitor

install:	epm_score \
                epm_default_generate \
                epm_default_filter \
                epm_default_monitor
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_default_generate
	rm -f ${INSTALL_BIN}/epm_default_filter
	rm -f ${INSTALL_BIN}/epm_default_monitor
	cp epm_score ${INSTALL_BIN}
	cp epm_default_generate ${INSTALL_BIN}
	cp epm_default_filter ${INSTALL_BIN}
	cp epm_default_monitor ${INSTALL_BIN}
	chmod 755 ${INSTALL_BIN}/epm_score
	chmod 755 ${INSTALL_BIN}/epm_default_generate
	chmod 755 ${INSTALL_BIN}/epm_default_filter
	chmod 755 ${INSTALL_BIN}/epm_default_monitor
	-ls -l ${INSTALL_BIN}/epm_score*
	-ls -l ${INSTALL_BIN}/epm_default_generate*
	-ls -l ${INSTALL_BIN}/epm_default_filter*
	-ls -l ${INSTALL_BIN}/epm_default_monitor*

uninstall:
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_default_generate
	rm -f ${INSTALL_BIN}/epm_default_filter
	rm -f ${INSTALL_BIN}/epm_default_monitor
	-ls -l ${INSTALL_BIN}/epm_score*
	-ls -l ${INSTALL_BIN}/epm_default_generate*
	-ls -l ${INSTALL_BIN}/epm_default_filter*
	-ls -l ${INSTALL_BIN}/epm_default_monitor*
