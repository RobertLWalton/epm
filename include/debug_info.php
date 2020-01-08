<?php
echo "SESSION: "; print_r ( $_SESSION ); echo "<br><br>";

echo "REQUEST: "; print_r ( $_REQUEST ); echo "<br>";
echo "POST: "; print_r ( $_POST ); echo "<br>";
echo "GET: "; print_r ( $_GET ); echo "<br>";
echo "FILES: "; print_r ( $_FILES ); echo "<br>";
echo "COOKIE: "; print_r ( $_COOKIE ); echo "<br>";
$__server = [];
$__server['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'];
$__server['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
$__server['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
$__server['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
$__server['PHP_SELF'] = $_SERVER['PHP_SELF'];
$__server['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'];
$__server['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'];
$__server['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'];
echo "SERVER: "; print_r ( $__server ); echo "<br>";
echo "UMASK: "; printf ( '0%o', umask() ); echo "<br>";
?>
