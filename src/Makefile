# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Sun Dec 15 06:10:29 EST 2019
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

INSTALL_BIN = /usr/bin

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score epm_cat

epm_score:	epm_score.cc
	g++ -o epm_score epm_score.cc

epm_cat:	epm_cat.cc
	g++ -o epm_cat epm_cat.cc

clean:
	rm -f epm_score epm_cat

install:	epm_score epm_cat
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_cat
	cp epm_score ${INSTALL_BIN}
	cp epm_cat ${INSTALL_BIN}
	chmod 755 ${INSTALL_BIN}/epm_score
	chmod 755 ${INSTALL_BIN}/epm_cat
	ls -l ${INSTALL_BIN}/epm_score*
	ls -l ${INSTALL_BIN}/epm_cat*

uninstall:
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	rm -f ${INSTALL_BIN}/epm_cat
	ls -l ${INSTALL_BIN}/epm_score*
	ls -l ${INSTALL_BIN}/epm_cat*
