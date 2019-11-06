<html>
<body>

<?php
$null_var = NULL;
$empty_string_var = '';
$zero_var = 0;
$isset_null_var = isset($null_var) ? 'yes' : 'no';
$isset_unset_var = isset($unset_var) ? 'yes' : 'no';
$isset_empty_string_var = isset($empty_string_var) ? 'yes' : 'no';
$isset_zero_var = isset($zero_var) ? 'yes' : 'no';
echo "isset(\$null_var) = $isset_null_var<br>";
echo "isset(\$unset_var) = $isset_unset_var<br>";
echo "isset(\$empty_string_var) = $isset_empty_string_var<br>";
echo "isset(\$zero_var) = $isset_zero_var<br>";
?>

</body>
</html>

