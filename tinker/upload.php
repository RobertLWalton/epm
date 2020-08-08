<?php
    echo '$_POST: '; print_r ( $_POST ); echo '<br>' . PHP_EOL;
    echo '$_FILES: '; print_r ( $_FILES ); echo '<br>' . PHP_EOL;
    $upload = $_FILES['uploaded_file'];
    foreach ( $upload['error'] as $key => $error )
    {
        if ( $error == UPLOAD_ERR_NO_FILE ) continue; 
	echo 'NAME: ' . $upload['name'][$key] . '<BR>';
	echo 'TYPE: ' . $upload['type'][$key] . '<BR>';
	echo 'SIZE: ' . $upload['size'][$key] . '<BR>';
        if ( $error != UPLOAD_ERR_OK )
	    echo 'ERROR: ' . $error . '<BR>';
	else
	{
	    $c = file_get_contents ( $upload['tmp_name'][$key] );
	    $c = htmlspecialchars ( $c );
	    echo "CONTENTS:<BR><PRE>$c</PRE>";
	}
    }
?>
    
<html>
<body>

<form enctype="multipart/form-data"
      action="upload.php" method="post">
<input type="hidden" name="file" value='FILE'>
<input type="hidden" name="more_files" value='MORE_FILES'>
<input type="hidden" name="op" value='OP'>
<input type="file" name="uploaded_file[]" title="files to upload" multiple>
<button type="submit" name="A" value='A'>A</button>
<button type="submit" name="B" value='B1'>B1</button>
<button type="submit" name="B" value='B2'>B2</button>
</form>

</body>
</html>
