<?php
    session_start();

    $EMAIL = "";
    $bad_confirm = false;

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ( array_key_exists ( 'email', $_REQUEST ) )
	{
	    $EMAIL = $_REQUEST['email'];
	    $_SESSION['confirm'] = 
	        bin2hex ( random_bytes ( 8 ) );
	}
	else if ( array_key_exists
	              ( 'confirm', $_REQUEST ) )
	{
	    if (    $_SESSION['confirm']
	         == $_REQUEST['confirm'] )
	    {
	        header ( "Location: /problems.php" );
		exit;
	    }
	    $bad_confirm = true;
	    $_SESSION['confirm'] = 
	        bin2hex ( random_bytes ( 8 ) );
	}
    }
?>
<html>
<body>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  <?php if ( $EMAIL == "" )
        {
	    echo 'Email Address:' .
	         ' <input type="text" name="email">';
	}
	else
	{
	    if ( bad_confirm )
	    {
	        echo 'CONFIRMATION NUMBER WAS WRONG;' .
		     ' TRY AGAIN<br>';
	    }
	    echo 'Email Address: ' . $EMAIL . '<br>';
	    echo 'Confirmation Number:' .
	         ' <input type="text" name="confirm">'
		 . "<br>";
	    echo '<button name="email" value="">' .
	         'Enter New Email Address</button>';
	    echo '<br>Confirmation Number is ' . 
	         $_SESSION["confirm"];

	}
  ?>
</form>

</body>
</html>
