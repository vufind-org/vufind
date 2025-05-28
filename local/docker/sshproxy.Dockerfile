FROM alpine:3.21

LABEL org.opencontainers.image.authors="Ronja Koistinen <ronja.koistinen@helsinki.fi>"
LABEL org.opencontainers.image.description="Local development SSH proxy"
LABEL org.opencontainers.image.vendor="National Library of Finland"

ENV SOCKS5_PROXY_PORT=1081
ENV SOCKS5_PROXY_DST=finna-pre-1
ENV SSH_USER=

RUN apk add --no-cache openssh-client

COPY sshproxy-start.sh /

CMD ["/sshproxy-start.sh"]
