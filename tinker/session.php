<?php
    session_start();
    if ( array_key_exists ( 'COUNT', $_SESSION ) )
    {
        $_SESSION['COUNT'] = 1 + $_SESSION['COUNT'];
    }
    else
    {
        $_SESSION['COUNT'] = 1;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// collect value of input field
	$_SESSION['NAME'] = $_REQUEST['name'];
    }
    if ( ! array_key_exists ( 'NAME', $_SESSION ) )
    {
        $VALUE = "";
    }
    else
    {
	$VALUE = $_SESSION['NAME'];
    }
?>
<html>
<body>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  Count: <?php echo $_SESSION['COUNT'];?><BR>
  Name: <input type="text" name="name"
         value=<?php echo $VALUE;?>>
  <input type="submit">
</form>

</body>
</html>

