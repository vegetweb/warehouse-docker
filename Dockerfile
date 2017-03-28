FROM ubuntu:16.04

RUN apt-get -y update && apt-get -y \
	install postgresql postgis \
	apache2 php ibapache2-mod-php7.0 php-zip php-curl php-gd php-ldap php-pgsql libapache2-mod-jk php-xml \
	unzip supervisor
RUN phpenmod xml

ADD indicia_setup.sh indicia_setup.sh
ADD postgres_setup.sh postgres_setup.sh
RUN chmod gou+x /postgres_setup.sh
RUN chmod gou+x /indicia_setup.sh

RUN mkdir -p /var/lock/apache2 /var/run/apache2 /var/log/supervisor

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir /var/www/html/indicia
RUN chown www-data:www-data /var/www/html/indicia
USER www-data

ADD warehouse-1.28.0-complete.zip /var/www/html/indicia
RUN cd /var/www/html/indicia && unzip warehouse-1.28.0-complete.zip
COPY database.php /var/www/html/indicia/application/config/database.php
COPY config.php /var/www/html/indicia/application/config/config.php

USER root
RUN /indicia_setup.sh
USER postgres

RUN    /etc/init.d/postgresql start &&\
    /postgres_setup.sh 

# Adjust PostgreSQL configuration so that remote connections to the
# database are possible.
RUN echo "host all  all    0.0.0.0/0  md5" >> /etc/postgresql/9.5/main/pg_hba.conf

# And add ``listen_addresses`` to ``/etc/postgresql/9.5/main/postgresql.conf``
RUN echo "listen_addresses='*'" >> /etc/postgresql/9.5/main/postgresql.conf

# Expose the PostgreSQL port
EXPOSE 80 5432
# Set the default command to run when starting the container
#CMD ["/usr/lib/postgresql/9.5/bin/postgres", "-D", "/var/lib/postgresql/9.5/main", "-c", "config_file=/etc/postgresql/9.5/main/postgresql.conf"]
USER root

CMD ["/usr/bin/supervisord"]
