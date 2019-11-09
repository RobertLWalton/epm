{

"COMMANDS": ["g++ OPT1 -o XXX XXX.cc OPT2 > XXX.cout 2> XXX.cerr]
"REQUIRES":["XXX.cc"],
"KEEP":    ["XXX","XXX.cout","XXX.cerr"],
"OPT1": [["-std=gnu++11",""]
         ["-Og", "-O3", ""],
         ["", "-Wpedantic"]],
"OPT2":  [["", "-lgsl"]]

}
