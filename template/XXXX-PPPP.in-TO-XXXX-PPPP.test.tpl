{

"COMMANDS": ["SANDBOX PPPP < XXXX-PPPP.in > XXXX-PPPP.test 2> XXXX-PPPP.err"]
"REQUIRES":["XXXX-PPPP.in", "PPPP"],
"CHECKS":   ["cmp -s XXXX-PPPP.err /dev/null"],
"KEEP":    ["XXXX-PPPP.test","XXXX-PPPP.err"],

}


