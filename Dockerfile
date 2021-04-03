FROM debian:buster
RUN sed -i s@/deb.debian.org/@/mirrors.aliyun.com/@g /etc/apt/sources.list
RUN sed -i s@/security.debian.org/@/mirrors.aliyun.com/@g /etc/apt/sources.list
RUN apt-get update
RUN apt-get install -y --no-install-recommends nginx php-fpm php-curl
ADD ./search-relay.php /var/www/html/
ADD ./nginx.conf /etc/nginx/sites-enabled/default
ADD ./entrypoint.sh /tmp
WORKDIR /var/www/html/
### Allow PHP log to pipe to Nginx output
RUN sed -i -e '/error_log/c error_log \/dev\/stdout;' /etc/nginx/nginx.conf
CMD /tmp/entrypoint.sh
