{

"COMMANDS": ["g++ OPT1 -o PPPP PPPP.cc OPT2 > PPPP.cout 2> PPPP.cerr]
"REQUIRES":["PPPP.cc"],
"KEEP":    ["PPPP","PPPP.cout","PPPP.cerr"],
"OPT1": [["-std=gnu++11",""]
         ["-Og", "-O3", ""],
         ["", "-Wpedantic"]],
"OPT2":  [["", "-lgsl"]]

}
