<html>
<!-- none of the following work; see console.log -->
<body>
<button type='button'
        onclick='CLOSE()'>
Close this Window
</button>
<button type='button'
        onclick='CLOSE_FIDDLE()'>
Close this Window after Fiddling
</button>

<script>
function CLOSE()
{
    window.close();
}
function CLOSE_FIDDLE()
{
    w = window.open ( '', window.name, '' );
    w.close();
}
</script>
</body>
</html>
