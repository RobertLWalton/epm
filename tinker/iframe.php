<html>
<body>

<?php

if ( isset ( $_GET['message'] ) )
{
    $message = $_GET['message'];
    echo "<pre>$message</pre>\n";
    echo "<br><br>\n";
    echo "80 character line:<br>\n";
    echo "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789";
    echo "</body>\n";
    echo "</html>\n";
    exit;
}
?>

<script>

var iframe;

function create_iframe ( message ) {
    if ( iframe != undefined ) iframe.remove();

    let h = window.innerHeight;
    let w = window.innerWidth;
    iframe = document.createElement("IFRAME");
    iframe.height = 95*h/100;
    iframe.width = 700;
    iframe.style.cssFloat = "right";
    iframe.src =
        '/tinker/iframe.php?message=' + message
	+ " inner: " + h + "x" + w
	+ " outer: " + window.outerHeight
	+ "x" + window.outerWidth;
    document.body.appendChild ( iframe );
}
</script>

<div style='float:left;width:48%'>
<button onclick='create_iframe("ONE")'>ONE</button><br>
<button onclick='create_iframe("TWO")'>TWO</button><br>
</div>
</body>
</html>

