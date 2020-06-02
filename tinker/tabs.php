<html>
<body>

<?php session_start(); ?>
Session ID: <?php echo ( session_id() ); ?>
<br>
Current Window Name: <span id='window-name'></span>
&nbsp; &nbsp;
<span id='ping' style='color:red'></span>
<br>
Opened by <span id='my-opener'>...</span>
<br>
Name Argument: <input type='text' id='name' size='24'>
<br>
<button type='button' onclick='NEW_WINDOW()'>Make New Window</button>
<br>
<button type='button' onclick='CLOSE_WINDOW()'>Close Window</button>
<br>
<button type='button' onclick='PING_WINDOW()'>Ping Window</button>
<br>
<button type='button' onclick='CHANGE_NAME()'>
        Change Current Window Name</button>
<br>
<button type='button' onclick='FOCUS()'>
        Focus Named Window</button>
<br>
<button type='button' onclick='GET_HREF()'>
        GET HREF</button>
&nbsp;&nbsp;&nbsp;
The href of <span id='href-name'>...</span>
is <span id='href-value'>...</span>
<br>
<button type='button' onclick='GET_OPENER()'>
        GET OPENER</button>
&nbsp;&nbsp;&nbsp;
The name of the opener of <span id='opener-name'>...</span>
is <span id='opener-value'>...</span>



<script>
let window_name = document.getElementById ( 'window-name' );
let my_opener = document.getElementById ( 'my-opener' );
let name = document.getElementById ( 'name' );
let ping = document.getElementById ( 'ping' );
let href_name = document.getElementById ( 'href-name' );
let href_value = document.getElementById ( 'href-value' );
let opener_name = document.getElementById ( 'opener-name' );
let opener_value = document.getElementById ( 'opener-value' );

my_opener.innerText = 'NONE;'
if ( window.opener != null )
    my_opener.innerText = window.opener.name;

console.log ( 'WINDOW-NAME ' + window.name );
window_name.innerText = window.name;

document.onvisibilitychange = function() {
    if ( document.visibilityState != 'visible' )
        return;
    window_name.innerText = window.name;
    if ( sessionStorage.getItem ( 'ping' ) != null )
        ping.innerText = 'PINGED';
    else
        ping.innerText = '';
    sessionStorage.removeItem ( 'ping' );
}

function NEW_WINDOW() {
    let w = window.open('/tinker/tabs.php', name.value, '' );
}
function CLOSE_WINDOW() {
    let w = window.open('', name.value, '' );
    w.close();
}
function PING_WINDOW() {
    let w = window.open('', name.value, '' );
    w.sessionStorage.setItem ( 'ping', '1' );
}
function CHANGE_NAME()
{
    window.name = name.value;
    window_name.innerText = window.name;
}
function FOCUS() {
    let w = window.open('', name.value, '' );
}
function GET_HREF() {
    let w = window.open('', name.value, '' );
    href_name.innerText = name.value;
    href_value.innerText = w.location.href;
}
function GET_OPENER() {
    let w = window.open('', name.value, '' );
    opener_name.innerText = name.value;
    opener_value.innerText = w.opener.name;
    console.log ( 'OPENER ' + w.opener );
    console.log ( 'OPENER NAME ' + w.opener.name );
}
// Note:  w = window.open ( ... ) forces focus to w
//	  and window.focus() does nothing to
//	  restore focus to the current window.
</script>

</body>
</html>
