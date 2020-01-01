<?php

if ( isset ( $_POST['time'] ) )
{
    $time = intval ( $_POST['time'] );
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
    if (    this.readyState === XMLHttpRequest.DONE
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
    xhttp.open ( 'POST', "clock.php", true );
    xhttp.setRequestHeader
        ( "Content-Type",
	  "application/x-www-form-urlencoded" );
    xhttp.send ( "time=" + time );
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

