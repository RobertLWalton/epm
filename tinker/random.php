<html>
<body>

<?php
$str = random_bytes ( 8 );
?>

\x80\x81\x82 = <?php echo ( bin2hex ( "\x80\x81\x82" ) ); ?><br>
RANDOM = <?php echo ( bin2hex ( $str ) ); ?><br>

</body>
</html>

