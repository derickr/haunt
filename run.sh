#!/bin/bash

ABSOLUTE=`readlink -f $0`
DIR=`dirname $ABSOLUTE`
cd $DIR

export PATH=/usr/local/php/5.3dev/bin:/usr/local/bin:/usr/bin:/bin
php -ddate.timezone=Europe/London -dextension=cairo.so -dextension=php_gtk2.so run.php twitter-derickr.sqlite
