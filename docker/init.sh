#!/bin/bash
php artisan migrate:fresh --seed
service nginx start
php-fpm
