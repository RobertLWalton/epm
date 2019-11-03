<html>
<body>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  Name: <input type="text" name="fname">
  <input type="submit">
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // collect value of input field
    $name = $_REQUEST['fname'];
    if ( preg_match ( "/^[a-zA-Z]*$/", $name ) == 1 )
    {
        echo $name . " contains only letters";
    } else {
        echo $name . " contains a non-letter";
    }
}
?>

</body>
</html>

