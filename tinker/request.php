<html>
<body>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  Name: <input type="text" name="fname">
  <button name="button" value="1">HOHO</button>
  <!-- <input type="submit">
       As there is only one text field, carriage return on that
       will submit.
  -->
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // collect value of input field
    $name = $_REQUEST['fname'];
    if (empty($name)) {
	echo "Name is empty";
    } else {
	echo $name;
    }
}
?>

</body>
</html>
