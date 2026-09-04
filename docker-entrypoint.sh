#!/bin/sh
set -e
sed -i "s/80/${PORT:-10000}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
