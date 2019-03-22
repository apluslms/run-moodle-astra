#!/bin/bash

OS=$(uname -s)
# use aplus as the project name since the run-mooc-grader container
# has hardcoded the aplus network in the launch of grader containers
COMPOSE_PROJECT_NAME=aplus
compose_file='docker-compose-moodle.yml'
compose_cmd="docker-compose -f $compose_file"
if [ "$1" = unittest ]; then
  shift
  compose_cmd="${compose_cmd} -f docker-compose-moodle-phpunit.yml"
fi
MOODLE_DOCKER_WEB_HOST='localhost'
MOODLE_DOCKER_WEB_PORT='8050'
DOCKER_SOCK=/var/run/docker.sock
[ -e "$DOCKER_SOCK" ] || { echo "ERROR: docker socket $DOCKER_SOCK doesn't exists. Do you have docker-ce installed?." >&2; exit 1; }
USER_ID=$(id -u)
USER_GID=$(id -g)

if [ $USER_ID -eq 0 ] || [ "$OS" = 'Darwin' ]; then
    DOCKER_GID=0
    if ! [ -e $DOCKER_SOCK ]; then
        echo "No docker socket detected in $DOCKER_SOCK. Is docker installed and active?" >&2
    fi
else
    DOCKER_GID=$(stat -c '%g' $DOCKER_SOCK)
    if ! [ -w $DOCKER_SOCK ]; then
        echo "Your user does not have write access to docker." >&2
        echo "It is recommended that you add yourself to that group (sudo adduser $USER docker; and then logout and login again)." >&2
        echo "Alternatively, you can execute this script as sudo." >&2
        exit 1
    fi
fi

if [ -z $(which docker-compose) ]; then
    echo "ERROR: Unable to find docker-compose. Are you sure it is installed and on your path?" >&2
    exit 1
fi

DATA_PATH=_data
ACOS_LOG_PATH=_data/acos
has_acos_log=$(grep -F "$ACOS_LOG_PATH" "$compose_file"|grep -vE '^\s*#')
[ "$has_acos_log" ] && mkdir -p "$ACOS_LOG_PATH"

export COMPOSE_PROJECT_NAME USER_ID USER_GID DOCKER_GID MOODLE_DOCKER_WEB_PORT MOODLE_DOCKER_WEB_HOST

pid=
keep=
onexit() {
    trap - INT
    [ "$pid" ] && kill $pid || true
    wait
    if [ "$keep" = "" ]; then
        clean
    else
        echo "Data was not removed. You can remove it with: $0 --clean"
    fi
    rm -rf /tmp/aplus || true
    exit 0
}

clean() {
    echo " !! Removing persistent data !! "
    $compose_cmd down --volumes
    if [ "$DATA_PATH" -a -e "$DATA_PATH" ]; then
        echo "Removing $DATA_PATH"
        rm -rf "$DATA_PATH" || true
    fi
}


while [ "$1" ]; do
    case "$1" in
        -c|--clean)
            clean
            exit 0
            ;;
        *)
            echo "Invalid option $1" >&2
            exit 1
            ;;
    esac
    shift
done


mkdir -p /tmp/aplus
trap onexit INT
$compose_cmd up & pid=$!

while kill -0 $pid 2>/dev/null; do
    echo "  Press Q or ^C to quit and remove data"
    echo "  Press S or ESC to quit and keep data"
    read -rsn1 i
    if [ "$i" = 'q' -o "$i" = 'Q' ]; then
        break
    elif [ "$i" = 's' -o "$i" = 'S' -o "$i" = $'\e' ]; then
        keep="x"
        break
    fi
done
onexit

