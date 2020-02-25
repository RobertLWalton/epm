<?php
    echo '$_POST: '; print_r ( $_POST ); echo '<br>' . PHP_EOL;
    echo '$_FILES: '; print_r ( $_FILES ); echo '<br>' . PHP_EOL;
    $upload = $_FILES['uploaded_file'];
    if ( $upload['error'] == 0 )
    {
        $c = file_get_contents ( $upload['tmp_name'] );
	echo 'SIZE: ' . strlen ( $c ) . '<br>' . PHP_EOL;
	$c .= 'EOT' . PHP_EOL;
	$c = htmlspecialchars ( $c );
	echo "<pre>$c</pre>";
    }
?>
    
<html>
<body>

<form enctype="multipart/form-data"
      action="upload.php" method="post">
<input type="hidden" name="file" value='FILE'>
<input type="hidden" name="more_files" value='MORE_FILES'>
<input type="hidden" name="op" value='OP'>
<input type="file" name="uploaded_file" title="file to upload">
<button type="submit" name="A" value='A'>A</button>
<button type="submit" name="B" value='B1'>B1</button>
<button type="submit" name="B" value='B2'>B2</button>
</form>

</body>
</html>
