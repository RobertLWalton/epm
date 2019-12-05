# EPM Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Thu Dec  5 03:04:52 EST 2019
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

INSTALL_BIN = /usr/bin

# Kill all implicit rules
#
.SUFFIXES:

all: epm_score

epm_score:	epm_score.cc
	g++ -o epm_score epm_score.cc

clean:
	rm -f epm_score

install:	epm_score
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	cp epm_score ${INSTALL_BIN}
	chmod 755 ${INSTALL_BIN}/epm_score
	ls -l ${INSTALL_BIN}/epm_score*

uninstall:
	test `id -un` = root
	test -d ${INSTALL_BIN}
	test -w ${INSTALL_BIN}
	rm -f ${INSTALL_BIN}/epm_score
	ls -l ${INSTALL_BIN}/epm_score*
