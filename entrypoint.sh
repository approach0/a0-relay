set -x
# inject php-fpm environment
FPM_CONF='/etc/php/7.3/fpm/pool.d/www.conf'
test -z "$A0_SEARCHD" || echo "env[A0_SEARCHD] = $A0_SEARCHD" >> $FPM_CONF
test -z "$A0_QRYLOGD" || echo "env[A0_QRYLOGD] = $A0_QRYLOGD" >> $FPM_CONF
tail -2 $FPM_CONF

/etc/init.d/php7.3-fpm start
# overwrite default log file /var/log/nginx/error.log
nginx -c /etc/nginx/nginx.conf -g 'daemon off;'
set +x
