<html>
<body>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  Name: <input type="text" name="fname">
  <button name="button" value="1">HOHO</button>
  <!-- <input type="submit">
       As there is only one text field, carriage return on that
       will submit.
  -->
  <input type="hidden" name="fname" value="HIDDEN">
  <!-- the hidden attribute does not prevent the one text
       field from causing submit on carriage return
  -->
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    print_r ( $_REQUEST );
}
?>

</body>
</html>
