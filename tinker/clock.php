<?php

if ( isset ( $_REQUEST['time'] ) )
{
    $time = intval ( $_REQUEST['time'] );
    while ( time() <= $time + 1 ) usleep ( 10000 );
    echo "$time " . time();
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
	var r = this.responseText.split ( " " );
	document.getElementById("oldtime")
	        .innerHTML = r[0];
	document.getElementById("newtime")
	        .innerHTML = r[1];
	send_time ( r[1] );
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

<p>Time:
&nbsp; &nbsp; &nbsp; &nbsp;
<span id="oldtime"></span>(old)
&nbsp; &nbsp; &nbsp; &nbsp;
<span id="newtime"></span>(new)
</p>

<?php echo "<script>send_time(" . time() . ")</script>"; ?>

</body>
</html>

