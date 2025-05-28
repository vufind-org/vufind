#!/bin/sh
#
# Author: Ronja Koistinen <ronja.koistinen@helsinki.fi>
#
# Install socat, docker, docker-compose and docker buildx.
#
# For configuration, fill in:
#   * .env (see template in env.sample)
#   * local/db_root_passwd.secret
#   * ~/.ssh/ssh_config (including the jump sequence to $SOCKS5_PROXY_DST)
#
# Start the environment:
#   ./localdev-docker.sh up --build -d
#
# Run arbitrary commands in a running container:
#   ./localdev-docker.sh exec <container> <command>
#
# Stop the environment:
#   ./localdev-docker.sh down
#
# If you need root to run Docker, launch the script like this:
#   ./localdev-docker.sh --sudo <up|down|exec> <etc.>
#
# Also make sure the random user inside the container (www-data group) is able
# to write in this source tree.

set -eu

LISTEN_SOCK=./ssh-auth.sock
SUDO=

if [ "$1" = "--sudo" ]; then
    SUDO="sudo"
    shift
fi

if [ -e "$LISTEN_SOCK" -a ! -S "$LISTEN_SOCK" ]; then
    echo "Error: $LISTEN_SOCK exists but is not a socket" >&2
    exit 1
elif [ ! -e "$LISTEN_SOCK" ]; then
    # Clone the SSH agent socket to a predictable path we can mount in
    # the container
    socat -d0 -lf socat.log UNIX-LISTEN:"$LISTEN_SOCK",fork \
        UNIX-CONNECT:"$SSH_AUTH_SOCK" &
fi

exec $SUDO docker compose $@
