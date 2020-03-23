<html>
<body>
<script>
// window.open will be ignored if not inside onclick function.
//
var w = window;
function new_window ( message ) {
    let x = window.outerHeight / 2;
    let y = window.outerWidth / 2;
    w = w.open('clone.php?message=' + message );
//    w = w.open('clone.php?message=' + message,
//                '+blank',
//		'height=' + x + ',width=' + y);
//    If you give height and width you get a popup window,
//    BUT, you can only have one popup window; trying to
//    add another will just replace the first.
}
</script>

<?php

$pop = NULL;
if ( isset ( $_REQUEST['message'] ) )
    $message = $_REQUEST['message'];

if ( isset ( $message ) )
    echo $message;

echo "<button onclick='new_window(\"ONE\")'>ONE</button><br>\n";
echo "<button onclick='new_window(\"TWO\")'>TWO</button><br>\n";


?>
</body>
</html>

