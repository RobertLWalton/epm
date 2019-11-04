<?php

    // File:	index.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Mon Nov  4 14:02:23 EST 2019

    session_start();

    $bad_email = false;
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
        exit ( 'ERROR: SESSION IP ADDRESS CHANGED;' .
	       ' RESTART BROWSER' );
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
	    // Enter a New Email Address button sends
	    // `email' == "".
	    //
	    $EMAIL = filter_var
	        ( $_REQUEST['email'],
		  FILTER_SANITIZE_EMAIL );
	    if ( filter_var ( $EMAIL,
	                     FILTER_VALIDATE_EMAIL )
		 || $EMAIL == "" )
	    {
		$_SESSION['email'] = $EMAIL;
		$_SESSION['confirm'] = 
		    bin2hex ( random_bytes ( 8 ) );
	    }
	    else
	    {
	        $bad_email = true;
	    }
	}
    }
?>

<html>
<body>


  <?php if ( $_SESSION['email'] == "" )
        {
	    if ( $bad_email )
	    {
	        echo '<mark>EMAIL ADDRESS WAS' .
		     ' MALFORMED; TRY AGAIN</mark>' .
		     '<br><br>';
	    }

	    echo $begin_form;
	    echo '<h2>Login:</h2>';
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
