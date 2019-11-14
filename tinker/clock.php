<?php

if ( isset ( $_REQUEST['time'] ) )
{
    $time = intval ( $_REQUEST['time'] );
    while ( time() <= $time ) usleep ( 10000 );
    echo "" . time();
    exit;
}

?>

<html>
<head>
<script>
var xhttp = new XMLHttpRequest();
xhttp.onreadystatechange = function()
{
    if (    this.readyState == 4
	 && this.status == 200 )
    {
	document.getElementById("showtime").innerHTML =
	    this.responseText;
	send_time ( this.responseText );
    }
};

function send_time ( time )
{
    xhttp.open
        ( 'GET', "clock.php?time=" + time, true );
    xhttp.send();
}
    
</script>
</head>

<body>

<p>Time: <span id="showtime"></span></p>

<?php echo "<script>send_time(" . time() . ")</script>"; ?>

</body>
</html>

