{

"COMMANDS": ["pdflatex -record -no-shell-escape -interaction nonstopmode >XXX.texout 2>XXX.texerr XXX.tex"],
"REQUIRES":["XXX.tex"],
"KEEP":    ["XXX.pdf","XXX.texout", "XXX.texerr", "XXX.log","XXX.fls"],
"OPTIONS": []

}
