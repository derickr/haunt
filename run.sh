#!/bin/bash

ABSOLUTE=`readlink -f $0`
DIR=`dirname $ABSOLUTE`
cd $DIR

export PATH=/usr/local/php/5.6dev/bin:/usr/local/bin:/usr/bin:/bin
php -n -dextension=oauth.so -dphp-gtk.codepage=UTF-8 -ddate.timezone=Europe/London -dextension=php_gtk2.so run.php twitter-derickr.sqlite
