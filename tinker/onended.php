<html>
<body>

<p>
Putting an onended="submit()" attribute in an input tag
works on Chrome and Mozilla, but according to HTML manual
onended is only supported for audio and video.
</p>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
  Text: <input onended="submit()" type="text" name="text"
         placeholder="some text"><br>
  Button 1: <button name="button-1" value="1">BUTTON 1 </button><br>
  Button 2: <button name="button-2" value="2">BUTTON 2 </button><br>
  Button 3: <button name="button-3" value="3">BUTTON 3 </button><br>
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    print_r ( $_POST );
}
?>

</body>
</html>

