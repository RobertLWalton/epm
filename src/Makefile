# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Thu Jun 11 12:39:09 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

INSTALL_BIN = /usr/bin

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score epm_default_generate epm_default_filter

epm_score:	epm_score.cc
	g++ -O3 -o epm_score epm_score.cc

epm_monitor:	epm_monitor.cc
	g++ -O3 -pthread -o epm_monitor epm_monitor.cc

epm_default_generate:	epm_cat.cc
	g++ -O3 -o epm_default_generate \
	    -DNAME=epm_default_generate -DFD=0 \
	    epm_cat.cc

epm_default_filter:	epm_cat.cc
	g++ -O3 -o epm_default_filter \
	    -DNAME=epm_default_filter -DFD=3 \
	    epm_cat.cc

clean:
	rm -f epm_score \
	      epm_default_generate \
	      epm_default_filter

install:	epm_score \
                epm_default_generate \
                epm_default_filter
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_default_generate
	rm -f ${INSTALL_BIN}/epm_default_filter
	cp epm_score ${INSTALL_BIN}
	cp epm_default_generate ${INSTALL_BIN}
	cp epm_default_filter ${INSTALL_BIN}
	chmod 755 ${INSTALL_BIN}/epm_score
	chmod 755 ${INSTALL_BIN}/epm_default_generate
	chmod 755 ${INSTALL_BIN}/epm_default_filter
	-ls -l ${INSTALL_BIN}/epm_score*
	-ls -l ${INSTALL_BIN}/epm_default_generate*
	-ls -l ${INSTALL_BIN}/epm_default_filter*

uninstall:
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_default_generate
	rm -f ${INSTALL_BIN}/epm_default_filter
	-ls -l ${INSTALL_BIN}/epm_score*
	-ls -l ${INSTALL_BIN}/epm_default_generate*
	-ls -l ${INSTALL_BIN}/epm_default_filter*
