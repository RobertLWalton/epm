<html>
<body>

<?php
$out = array();
$ret = 4;
exec ( "ls -lt", $out, $ret );
foreach ( $out as $value ) {
    echo $value . "<br>";
}
echo ( "return code = " . $ret );

?>

</body>
</html>

