<html>
<body>
<script>
// window.open will be ignored if not inside onclick function.
//
function new_window ( message ) {
    let x = 3 * window.outerHeight/ 4;
    let y = window.outerWidth/ 2;
    window.open('twopage.php?pop='
    			+ message + ' ' + x + ' ' + y,
                'MY WINDOW',
		'height=' + x + ',width=' + y);
}
</script>

<?php

$pop = NULL;
if ( isset ( $_REQUEST['pop'] ) )
    $pop = $_REQUEST['pop'];

if ( isset ( $pop ) )
    echo $pop;
else
{
    echo "<button onclick='new_window(\"ONE\")'>ONE</button><br>\n";
    echo "<button onclick='new_window(\"TWO\")'>TWO</button><br>\n";
}


?>
</body>
</html>
