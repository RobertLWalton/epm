<html>
<body>

<?php

if ( isset ( $_GET['message'] ) )
{
    $message = $_GET['message'];
    echo "<pre>$message</pre>\n";
    echo "<br><br>\n";
    echo "10pt 80 character line:<br>\n";
    echo "<pre style='font-size:10pt'>";
    echo "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789";
    echo "</pre><br>\n";
    echo "12pt 80 character line:<br>\n";
    echo "<pre style='font-size:12pt'>";
    echo "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789";
    echo "</pre><br>\n";
    echo "15pt 80 character line:<br>\n";
    echo "<pre style='font-size:15pt'>";
    echo "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789" .
         "0123456789";
    echo "</pre><br>\n";
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
    iframe.height = h/3;
    iframe.width = 800;
    iframe.style.cssFloat = "right";
    iframe.src =
        '/tinker/iframe.php?message=' + message
	+ " inner: " + h + "x" + w
	+ " iframe: " + iframe.height
	+ "x" + iframe.width;
    document.body.appendChild ( iframe );
}
</script>

<div style='float:left;width:48%'>
<button onclick='create_iframe("ONE")'>ONE</button><br>
<button onclick='create_iframe("TWO")'>TWO</button><br>
</div>
</body>
</html>

