# EPM Secure Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Thu Jun 25 04:26:43 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

# Kill all implicit rules
#
.SUFFIXES:

BIN=../bin
# BIN=/usr/sbin

all: epm_sandbox

epm_sandbox:	epm_sandbox.c
	gcc -o epm_sandbox epm_sandbox.c

install:	${BIN}/epm_sandbox

${BIN}/epm_sandbox:	epm_sandbox
	test `id -un` = root
	test -d ${BIN}
	test -w ${BIN}
	rm -f ${BIN}/epm_sandbox
	cp -p epm_sandbox ${BIN}/epm_sandbox
	chmod 775 ${BIN}/epm_sandbox
	chown root ${BIN}/epm_sandbox
	chmod u+s ${BIN}/epm_sandbox
	ls -l ${BIN}/epm_sandbox*

uninstall:
	test `id -un` = root
	test -d ${BIN}
	test -w ${BIN}
	rm -f ${BIN}/epm_sandbox

clean:
	rm -f epm_sandbox
