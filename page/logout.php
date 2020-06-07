<?php

    // File:	logout.php
    // Author:	Robert L Walton <walton@acm.org>
    // Date:	Sun Jun  7 03:46:18 EDT 2020

    // The authors have placed EPM (its files and the
    // content of these files) in the public domain;
    // they make no warranty and accept no liability
    // for EPM.

    $epm_page_type = '+init+';
    require "{$_SERVER['DOCUMENT_ROOT']}/index.php";

    if ( isset ( $_GET['answer'] )
	 &&
	 $_GET['answer'] == 'YES' )
    {
	session_unset();
	header ( "Location: /page/login.php" );
	exit;
    }

?>
<html>
<head>
<?php require "$epm_home/include/epm_head.php"; ?>
<style>
button.marked {
    background-color: yellow;
}
</style>
</head>
<body>

<div class='manage'>
<table style='width:100%'>
<td style='text-align:left'>
Do you really want to log out?
<button class='marked' type='button' onclick='NO()'>
    NO</button>
<button class='marked' type='button' onclick='YES()'>
    YES</button>
</td>
<td style='text-align:right'>
<button type='button'
	onclick='HELP("logout-page")'>
    ?</button>
</td>
</tr>
</table>

<?php
    $r = '<script>let problems=[';
    $s = '';
    foreach ( $_SESSION['EPM_ID_GEN']
              as $key => $value )
    {
        if ( preg_match ( $epm_name_re, $key ) )
	{
	    $r .= "$s'$key'";
	    $s = ',';
	}
    }
    $r .= '];</script>';
    echo $r;
?>

<script>
let ID = '<?php echo $ID; ?>';
function NO()
{
    location.assign ( '/page/project.php?id=' + ID );
}
function YES()
{
    window.open ( '', '+help+', '' ).close();
    window.open ( '', '+view+', '' ).close();
    for ( i = 0; i < problems.length; ++ i )
        window.open ( '', problems[i], '' ).close();
    location.assign
        ( '/page/logout.php?answer=YES&id=' + ID );
}
</script>
</body>
</html>
