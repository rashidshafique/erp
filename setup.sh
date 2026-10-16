#!/usr/bin/env bash
# One-time setup for a fresh copy. Run from this folder:  bash setup.sh
set -e
DB="${1:-foundry_erp}"
[ -f .env ] || cp .env.example .env
sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$DB/" .env && rm -f .env.bak
composer install
php artisan key:generate
psql postgres -tc "SELECT 1 FROM pg_roles WHERE rolname='foundry'" | grep -q 1 || psql postgres -c "CREATE ROLE foundry LOGIN SUPERUSER PASSWORD 'foundry';"
psql postgres -tc "SELECT 1 FROM pg_database WHERE datname='$DB'" | grep -q 1 || psql postgres -c "CREATE DATABASE $DB OWNER foundry;"
psql postgres -tc "SELECT 1 FROM pg_database WHERE datname='foundry_erp_test'" | grep -q 1 || psql postgres -c "CREATE DATABASE foundry_erp_test OWNER foundry;"
php artisan migrate --force
php artisan test
echo "Done. Start with:  php artisan serve   then open http://127.0.0.1:8000"
