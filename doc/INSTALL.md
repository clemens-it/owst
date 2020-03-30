# Installation instructions for OWST on Debian based systems

### This is a first draft!

<!--
    mkdir /usr/local/stow/owst
    /usr/local/bin/owst_tp.php
-->

Get files from git

    git clone --depth=1 https://github.com/clemens-it/owst

Owst requires the following packages. The setup desribed below relies on nginx and PHP FPM, feel free to use any other service (e.g. apache, lighttp, fcgiwrap with php-cgi, etc.) if you like.

	apt-get install libow-php7 php-cli php-fpm php-sqlite3

With the following commands owst will be installed in the following places:
* Web interface: /srv/www/owst
* Logfile: /var/log/owst/
* Database and lock file: /var/lib/owst/
* Command line interface (CLI): /usr/local/stow/owst


Create user owst, create and set permissions for home directory, database, log file

    useradd --home /var/lib/owst --shell /bin/bash --system owst
    mkdir -m 0750 /var/lib/owst
    sqlite3 /var/lib/owst/owst.sq3 < config/database.sql
    touch /var/lib/owst/owst.lock
    chown -R owst.owst /var/lib/owst
    chmod 640 /var/lib/owst/*

    mkdir -p 0750 /var/log/owst
    chmod 640 /var/log/owst/owst.log > /var/log/owst/owst.log
    chown -R owst.root /var/log/owst/


Configuration for cron job, log rotation, php fpm

    cp config/etc.cron.d.owst /etc/cron.d/local-owst
    cp config/logrotate.d.owst /etc/logrotate.d/owst
    cp config/etc.php.x.mods-available.owphp.ini /etc/php/7.3/mods-available
    cp config/etc.php.x.fpm.pool.d.owst.conf /etc/php/7.3/fpm/pool.d

	 # Enable php module owphp, restart PHP FPM service
    phpenmod owphp.ini
    systemctl restart php7.3-fpm.service


Create and set permissions for web service directory
    mkdir -p /srv/www/owst
    chown root.www-data /srv/www
    chmod o+x /srv/www/
    chown owst.www-data /srv/www/owst/
    chmod 0750 /srv/www/owst

Using sudo instead of a dedicated PHP FPM
    config/etc.sudoers.d.owst
