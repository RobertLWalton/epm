<html>
<body>

Current Window Name: <span id='window-name'></span>
&nbsp; &nbsp;
<span id='ping' style='color:red'></span>
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



<script>
let window_name = document.getElementById ( 'window-name' );
let name = document.getElementById ( 'name' );
let ping = document.getElementById ( 'ping' );
let href_name = document.getElementById ( 'href-name' );
let href_value = document.getElementById ( 'href-value' );

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
// Note:  w = window.open ( ... ) forces focus to w
//	  and window.focus() does nothing to
//	  restore focus to the current window.
</script>

</body>
</html>
