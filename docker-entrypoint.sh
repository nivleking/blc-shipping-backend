#!/bin/sh
set -e

echo "Waiting for MySQL to be ready..."
until php -r "try {new PDO('mysql:host=mysql;port=3306', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'connected';} catch (\PDOException \$e) {echo \$e->getMessage(); exit(1);}" | grep -q 'connected'; do
  echo "MySQL is unavailable - sleeping 2 seconds"
  sleep 2
done

echo "MySQL is up - checking if database exists"
if ! php -r "try {new PDO('mysql:host=mysql;dbname=${DB_DATABASE};port=3306', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'exists';} catch (\PDOException \$e) {exit(1);}" | grep -q 'exists'; then
  echo "Database ${DB_DATABASE} doesn't exist or credentials are incorrect"
  exit 1
fi

echo "Database is ready - executing migrations"
php artisan migrate --force

echo "Starting Laravel server"
php artisan serve --host=0.0.0.0 --port=8000
