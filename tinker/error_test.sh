trap 'echo $c $? >testexitcode' EXIT
c=B; set -e
c=1; echo 'hello'
c=2; echo 'he he'
c=D; exit 0
