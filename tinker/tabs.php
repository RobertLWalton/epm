<html>
<body>

Window Name: <span id='window-name'></span>
<br>
<button type='button' onclick='NEW_WINDOW()'>Make New Window</button>
or <button type='button' onclick='CLOSE_WINDOW()'>Close Window</button>
or <button type='button' onclick='SET_WINDOW()'>Set Window</button>
with name <input type='text' id='name' size='24'>
<br>
<button type='button' onclick='SET()'>Set</button>
session storage to value <input type='text' id='set' size='24'>
<br>
<button type='button' onclick='GET()'>Get</button>
session storage value: <span id='get'></span>

<script>
// window.open will be ignored if not inside onclick function.
//
let window_name = document.getElementById ( 'window-name' );
let name = document.getElementById ( 'name' );
let set = document.getElementById ( 'set' );
let get = document.getElementById ( 'get' );

window_name.innerText = window.name;

function NEW_WINDOW() {
    let w = window.open('/tinker/tabs.php', name.value, '' );
    w.sessionStorage.setItem ( 'tabs', set.value );
}
function CLOSE_WINDOW() {
    let w = window.open('', name.value, '' );
    w.close();
}
function SET_WINDOW() {
    let w = window.open('', name.value, '' );
    w.sessionStorage.setItem ( 'tabs', set.value );
}
function SET()
{
    sessionStorage.setItem ( 'tabs', set.value );
}
function GET()
{
    get.innerText = sessionStorage.getItem ( 'tabs' );
}
</script>

</body>
</html>
