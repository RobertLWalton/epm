# EPM Secure Source Code Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Mon Nov 25 19:15:11 EST 2019
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.
#
# Adapted from hpcm/secure/src/Makefile by the same
# author.

# Kill all implicit rules
#
.SUFFIXES:

SECURE_BIN=/usr/sbin

all: epm_sandbox

root_install:	${SECURE_BIN}/epm_sandbox_secure
root_uninstall:	${SECURE_BIN}_uninstall

${SECURE_BIN}/epm_sandbox_secure:	\
		${SECURE_BIN}/epm_sandbox.compiled
	test `id -un` = root
	test -d ${SECURE_BIN}
	test -w ${SECURE_BIN}
	-mv -f ${SECURE_BIN}/epm_sandbox_secure \
	       ${SECURE_BIN}/epm_sandbox_secure-
	cp -p ${SECURE_BIN}/epm_sandbox.compiled \
	      ${SECURE_BIN}/epm_sandbox_secure
	chmod 755 ${SECURE_BIN}/epm_sandbox_secure
	chmod u+s ${SECURE_BIN}/epm_sandbox_secure
	ls -l ${SECURE_BIN}/epm_sandbox*

${SECURE_BIN}/epm_sandbox.compiled:	epm_sandbox.c
	test `id -un` = root
	test -d ${SECURE_BIN}
	test -w ${SECURE_BIN}
	rm -f epm_sandbox
	make --no-print-directory epm_sandbox
	-mv -f ${SECURE_BIN}/epm_sandbox.compiled \
	       ${SECURE_BIN}/epm_sandbox.compiled-
	mv -f epm_sandbox \
	      ${SECURE_BIN}/epm_sandbox.compiled
	chmod 644 ${SECURE_BIN}/epm_sandbox.compiled
	ls -l ${SECURE_BIN}/epm_sandbox.*

${SECURE_BIN}_uninstall:
	test `id -un` = root
	test -d ${SECURE_BIN}
	test -w ${SECURE_BIN}
	-mv -f ${SECURE_BIN}/epm_sandbox_secure- \
	       ${SECURE_BIN}/epm_sandbox_secure
	-mv -f ${SECURE_BIN}/epm_sandbox.compiled- \
	       ${SECURE_BIN}/epm_sandbox.compiled
	ls -l ${SECURE_BIN}/epm_sandbox*

epm_sandbox:	epm_sandbox.c
	gcc -o epm_sandbox epm_sandbox.c

clean:
	rm -f epm_sandbox