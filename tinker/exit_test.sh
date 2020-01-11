trap 'echo $c $?' EXIT
c=B; set -e
c=1; echo 'hello'
c=2; xecho 'he he'
c=3; echo 'whowho'
c=D; exit 0
