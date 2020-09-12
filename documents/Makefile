all:	epm_design.pdf epm_score.pdf

# For vimake
epm_design:	epm_design.pdf


epm_design.pdf:		epm_design.tex
	pdflatex -record -interaction nonstopmode \
	         -no-shell-escape epm_design.tex

epm_score.pdf:		../bin/epm_score
	D=`date -r ../src/epm_score.cc +'%D %T'` \
	echo "DATE $$D"
	../bin/epm_score -doc | \
	    enscript \
	        -2r \
		-F Courier-Bold12 \
		-f Courier10 \
		--margins=48:48:: \
		-b 'epm_score|$$D|$$% of $$=' \
		-p - | \
	    ps2pdf - epm_score.pdf


