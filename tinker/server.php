<HTML>
<?php
echo "DOCUMENT_ROOT = " . $_SERVER['DOCUMENT_ROOT'];
echo "<br>";
echo "PATH_TRANSLATED = " . $_SERVER['PATH_TRANSLATED'];
echo "<br>";
echo "PHP_SELF = " . $_SERVER['PHP_SELF'];
echo "<br>";
echo "SCRIPT_NAME = " . $_SERVER['SCRIPT_NAME'];
echo "<br>";
echo "SCRIPT_FILENAME = " . $_SERVER['SCRIPT_FILENAME'];
echo "<br>";
echo "SERVER_NAME = " . $_SERVER['SERVER_NAME'];
echo "<br>";
echo "SERVER_ADDR = " . $_SERVER['SERVER_ADDR'];
echo "<br>";
echo "HTTP_HOST = " . $_SERVER['HTTP_HOST'];
echo "<br>";
echo "HTTP_REFERER = " . $_SERVER['HTTP_REFERER'];
echo "<br>";
echo "HTTP_USER_AGENT = " . $_SERVER['HTTP_USER_AGENT'];
echo "<br>";
echo "HTTPS = " . ( $_SERVER['HTTPS'] ? "yes" : "no" );
echo "<br>";
echo "REMOTE_ADDR = " . $_SERVER['REMOTE_ADDR'];
echo "<br>";
echo "REMOTE_HOST = " . $_SERVER['REMOTE_HOST'];
echo "<br>";
echo "REMOTE_PORT = " . $_SERVER['REMOTE_PORT'];
echo "<br>";
echo "REQUEST_URI = " . $_SERVER['REQUEST_URI'];
echo "<br>";
echo "REQUEST_TIME = " . date ( DATE_COOKIE, $_SERVER['REQUEST_TIME'] );
?>
</HTML>

