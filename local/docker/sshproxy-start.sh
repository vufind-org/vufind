#!/bin/sh

set -eu

# we copy these to get around the ssh client's owner, group and mode checks
install -o 0 -g 0 -m 0600 /tmp/ssh_known_hosts /etc/ssh/
install -o 0 -g 0 -m 0600 /tmp/ssh.conf /etc/ssh/ssh_config.d/

exec /usr/bin/ssh -v -N -g -D "$SOCKS5_PROXY_PORT" -i /root/identity \
    -J "$SSH_JUMP_SEQUENCE" "$SOCKS5_PROXY_DST"
