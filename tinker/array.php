<html>
<body>

<?php
$arr = array ( 1, 2, 3, 4 );
echo "arr[0] = {$arr[0]}<br>";
echo "arr[1] = {$arr[1]}<br>";
echo "arr[2] = {$arr[2]}<br>";
echo "arr[3] = {$arr[3]}<br>";
echo "arr[4] = {$arr[4]}<br>";
    // This last cause php server NOTICE message, that
    // cannot be seen by the user.
echo "undefined variable = $undef<br>";
    // This also causes php server NOTICE message, that
    // cannot be seen by the user.

if ( $undef == NULL )
    $test = 1;
else
    $test = 0;

echo "(undefined variable == NULL) = $test<br>";
?>

</body>
</html>

