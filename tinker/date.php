<html>
<body>
<?php

    $time = time();
    $date = strftime ( '%FT%T%z', $time );
    $converted = strtotime ( $date );
    echo "Date: $date<br>";
    echo "Time: $time =? $converted" .
         " = Converted Date<br>";
?>
</body>
</html>

