#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

JWT_DIR="/var/www/html/config/jwt"
mkdir -p "$JWT_DIR"
chown -R www-data:www-data "$JWT_DIR"

if [ ! -f "$JWT_DIR/private.pem" ] || [ ! -f "$JWT_DIR/public.pem" ]; then
  if [ -z "${JWT_PASSPHRASE:-}" ]; then
    echo "ERROR: JWT_PASSPHRASE is not set"; exit 1
  fi

  echo "Generating JWT keypair (PKCS#8)…"
  openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 \
    -aes-256-cbc -out "$JWT_DIR/private.pem" \
    -pass pass:"$JWT_PASSPHRASE"

  openssl pkey -in "$JWT_DIR/private.pem" -passin pass:"$JWT_PASSPHRASE" \
    -pubout -out "$JWT_DIR/public.pem"

  chown www-data:www-data "$JWT_DIR"/private.pem "$JWT_DIR"/public.pem
  chmod 600 "$JWT_DIR/private.pem"
  chmod 644 "$JWT_DIR/public.pem"
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "⚠️ migrations skipped"
php bin/console assets:install public --symlink --relative || echo "⚠️ assets install skipped"
php bin/console cache:warmup --env=prod || true

exec apache2-foreground
