# EPM Documentation Makefile
#
# File:		Makefile
# Authors:	Bob Walton (walton@deas.harvard.edu)
# Date:		Sat Sep 12 13:15:00 EDT 2020
#
# The authors have placed this program in the public
# domain; they make no warranty and accept no liability
# for this program.

SRC = ../src

all:	epm_design.pdf epm_display.pdf epm_score.pdf

# For vimake
#
epm_design:	epm_design.pdf

epm_design.pdf:		epm_design.tex
	pdflatex -record -interaction nonstopmode \
	         -no-shell-escape epm_design.tex

epm_display.pdf:		${SRC}/epm_display.cc
	make -C ${SRC} epm_display
	epm_print epm_display

epm_score.pdf:		${SRC}/epm_score.cc
	make -C ${SRC} epm_score
	epm_print epm_score
