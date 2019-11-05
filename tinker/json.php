<html>
<body>

<?php

    $arr = array ( 'a' => 1, 'b' => [2,3,4] );
    $arr_json  = json_encode ( $arr );
    $arr_decoded  = json_decode ( $arr_json, true );
    print_r ( $arr );
    echo '<br>';
    echo "JSON: $arr_json<br>";
    print_r ( $arr_decoded );

?>
    
</body>
</html>
