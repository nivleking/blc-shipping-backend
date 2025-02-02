#!/bin/bash
until php artisan migrate:status > /dev/null 2>&1; do
  echo "Waiting for database connection..."
  sleep 5
done
