<?php
    session_start();

    $bad_confirm = false;
    $begin_form =
	'<form method="post" action="' .
	$_SERVER['PHP_SELF'] . '">';
    $end_form = '</form>';

    $remote_addr = $_SERVER['REMOTE_ADDR'];

    if ( ! array_key_exists ( 'ipaddr', $_SESSION ) )
    {
        $_SESSION['ipaddr'] = $remote_addr;
    }
    else if ( $_SESSION['ipaddr'] != $remote_addr )
    {
        exit ( "ERROR: IP ADDRESS CHANGED" );
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ( array_key_exists
	              ( 'confirm', $_REQUEST ) )
	{
	    if (    $_SESSION['confirm']
	         == $_REQUEST['confirm'] )
	    {
	        header
		    ( "Location: /src/problems.php" );
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
	    echo 'Login:<br>';
	    echo 'Email Address:' .
	         ' <input type="email" name="email">';
	    echo $end_form;
	}
	else
	{
	    if ( $bad_confirm )
	    {
	        echo '<mark>CONFIRMATION NUMBER WAS' .
		     ' WRONG; TRY AGAIN</mark><br>';
	        echo 'A <mark>new</mark>';
	    }
	    else
	    {
	        echo 'A';
	    }
	    echo ' confirmation number has been mailed'
	         . ' to your email address.<br><br>';
	    echo 'Email Address: ' . $_SESSION['email']
	         . '&nbsp;&nbsp;/&nbsp;&nbsp;';
	    echo 'IP Address: ' . $_SESSION['ipaddr']
	         . '<br><br>';
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
