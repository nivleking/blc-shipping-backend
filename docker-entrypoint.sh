#!/bin/sh
set -e

echo "Connecting to host MySQL on port 3307..."
until php -r "try {new PDO('mysql:host=host.docker.internal;port=3307', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'connected';} catch (\PDOException \$e) {echo \$e->getMessage(); exit(1);}" | grep -q 'connected'; do
  echo "Host MySQL is unavailable - sleeping 2 seconds"
  sleep 2
done

echo "Host MySQL is up - ensuring database exists"
# No need to create database - assuming it already exists on the host

echo "Database is ready - executing migrations"
php artisan migrate --force  # Removed fresh and seed to be safer with production DB

echo "Starting Laravel server"
php artisan serve --host=0.0.0.0 --port=8000
