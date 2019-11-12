<?php
echo "SESSION: "; print_r ( $_SESSION ); echo "<br><br>";
echo "REQUEST: "; print_r ( $_REQUEST ); echo "<br><br>";
echo "POST: "; print_r ( $_POST ); echo "<br><br>";
echo "GET: "; print_r ( $_GET ); echo "<br><br>";
$__server = [];
$__server['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'];
$__server['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
$__server['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
$__server['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
$__server['PHP_SELF'] = $_SERVER['PHP_SELF'];
$__server['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'];
$__server['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'];
echo "SERVER: "; print_r ( $__server ); echo "<br><br>";
?>
