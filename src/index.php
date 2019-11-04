<?php
    session_start();

    $bad_confirm = false;
    $begin_form =
	'<form method="post" action="' .
	$_SERVER['PHP_SELF'] . '">';
    $end_form = '</form>';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ( array_key_exists
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
	else if ( array_key_exists
	              ( 'email', $_REQUEST ) )
	{
	    $_SESSION['email'] = $_REQUEST['email'];
	    $_SESSION['confirm'] = 
	        bin2hex ( random_bytes ( 8 ) );
	}
    }
?>
<html>
<body>


  <?php if ( $_SESSION['email'] == "" )
        {
	    echo $begin_form;
	    echo 'Email Address:' .
	         ' <input type="text" name="email">';
	    echo $end_form;
	}
	else
	{
	    if ( $bad_confirm )
	    {
	        echo 'CONFIRMATION NUMBER WAS WRONG;' .
		     ' TRY AGAIN<br>';
	    }
	    echo 'Email Address: ' . $_SESSION['email']
	         . '<br>';
	    echo $begin_form;
	    echo 'Confirmation Number:' .
	         ' <input type="text" name="confirm">'
		 . "<br>";
	    echo $end_form;
	    echo $begin_form;
	    echo '<button name="email" value="">' .
	         'Enter New Email Address</button>';
	    echo $end_form;
	    echo '<br>Confirmation Number is ' . 
	         $_SESSION["confirm"];

	}
  ?>
</form>

</body>
</html>
