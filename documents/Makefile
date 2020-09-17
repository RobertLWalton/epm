# EPM Documentation Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Thu Sep 17 08:40:04 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

SRC = ../src
SECURE = ../secure
PRINT = ../bin/epm_print

all:	epm_design.pdf epm_display.pdf epm_score.pdf \
        epm_sandbox.pdf

# For vimake
#
epm_design:	epm_design.pdf

epm_design.pdf:		epm_design.tex
	pdflatex -record -interaction nonstopmode \
	         -no-shell-escape epm_design.tex

epm_display.pdf:		${SRC}/epm_display.cc
	make -C ${SRC} epm_display
	${PRINT} epm_display

epm_score.pdf:		${SRC}/epm_score.cc
	make -C ${SRC} epm_score
	${PRINT} epm_score

epm_sandbox.pdf:		${SECURE}/epm_sandbox.c
	make -C ${SECURE} epm_sandbox
	${PRINT} epm_sandbox

clean:
	rm -f *.pdf
