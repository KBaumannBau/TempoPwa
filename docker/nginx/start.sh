#!/bin/sh
# Start script for nginx:
#  1. choose the PWA mode (dev = Vite dev server, prod = static build)
#  2. generate optional basic auth from BASIC_AUTH_USERS ("user:pass,user:pass")
set -eu

case "${PWA_MODE:-dev}" in
  prod) cp /etc/nginx/pwa/pwa-prod.conf /etc/nginx/pwa.conf ;;
  *)    cp /etc/nginx/pwa/pwa-dev.conf  /etc/nginx/pwa.conf ;;
esac

if [ -n "${BASIC_AUTH_USERS:-}" ]; then
  : > /etc/nginx/.htpasswd
  echo "$BASIC_AUTH_USERS" | tr ',' '\n' | while read -r userpass; do
    [ -z "$userpass" ] && continue
    user="${userpass%%:*}"
    pass="${userpass#*:}"
    printf '%s:%s\n' "$user" "$(openssl passwd -apr1 "$pass")" >> /etc/nginx/.htpasswd
  done
  printf 'auth_basic "Tempo";\nauth_basic_user_file /etc/nginx/.htpasswd;\n' > /etc/nginx/basic-auth.conf
else
  echo 'auth_basic off;' > /etc/nginx/basic-auth.conf
fi

exec nginx -g 'daemon off;'
