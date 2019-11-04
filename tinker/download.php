<html>
<body>

<?php
$out = array();
$ret = 4;
exec ( "ls -lt", $out, $ret );
if ( $ret != 0 )
{
    echo "there are no files<br>";
}
else
{
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// collect value of input field
	$c = $_REQUEST['Button'];
	echo "Selected: $out[$c]<br>";
    }

    echo "<form action=/download.php method=post><table>\n";
    $limit = count ( $out );
    for ( $c = 1; $c < $limit; $c++ )
    {
	echo "<tr><td>" . $out[$c] . "</td><td>";
	echo "<button type=submit name=Button value=$c>D</button>";
	echo "</td></tr>\n";
        $c = $c + 1;
    }
    echo "</table></form>\n";
}
?>


</body>
</html>
