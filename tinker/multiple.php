<html>
<body>

<p> Multiple does not work very well?

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
Enter text: <input type="file" name="foo" multiple><br>
<input type="submit">
</form>

<?php
   if ($_SERVER["REQUEST_METHOD"] == "POST")
	print_r ( $_REQUEST );
?>
</body>
</html>
