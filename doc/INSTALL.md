# Installation instructions for OWST on Debian based systems

### This is a first draft!

<!--
    mkdir /usr/local/stow/owst
    /usr/local/bin/owst_tp.php
-->

Get files from git

    git clone --depth=1 https://github.com/clemens-it/owst

Owst requires the following packages. The setup desribed below relies on nginx
and PHP FPM, feel free to use any other service (e.g. apache, lighttp, fcgiwrap
with php-cgi, etc.) if you like.

    apt-get install libow-php7 php-cli php-fpm php-sqlite3 smarty3

With the following commands owst will be installed in the following places:
* Web interface: /srv/www/owst
* Logfile: /var/log/owst/
* Database and lock file: /var/lib/owst/
* Command line interface (CLI): /usr/local/stow/owst


Create dedicated user owst, create and set permissions for home directory,
database, log file.

    useradd --home /var/lib/owst --shell /bin/bash --system owst
    mkdir -m 0750 -p /var/lib/owst
    sqlite3 /var/lib/owst/owst.sq3 < doc/create_database.sql
    touch /var/lib/owst/owst.lock
    chown -R owst.root /var/lib/owst
    chmod 640 /var/lib/owst/*

    mkdir -m 0750 -p /var/log/owst
    chmod 640 /var/log/owst/owst.log > /var/log/owst/owst.log
    chown -R owst.root /var/log/owst/


Configuration for cron job, log rotation, php fpm.

    cp config/etc.cron.d.owst /etc/cron.d/local-owst
    cp config/logrotate.d.owst /etc/logrotate.d/owst
    cp config/etc.php.x.mods-available.owphp.ini /etc/php/7.3/mods-available
    cp config/etc.php.x.fpm.pool.d.owst.conf /etc/php/7.3/fpm/pool.d

    # Enable php module owphp, restart PHP FPM service
    phpenmod owphp.ini
    systemctl restart php7.3-fpm.service


Create and set permissions for web service directory, copy file for web
interface.

    mkdir -m 0750 -p /srv/www/owst
    chown root.www-data /srv/www
    chmod o+x /srv/www/
    chown owst.www-data /srv/www/owst/
    cp htdocs/* /srv/www/owst/


Configure nginx. Edit the site file through which you want to access the owst
web interface. In case of a default installation this would be
/etc/nginx/sites-enabled/default. Add the following snippet to the server
section.

    location ~ /owst/.*\.php$ {
        include snippets/fastcgi-php.conf;

        # With php-fpm (or other unix sockets):
        fastcgi_pass unix:/run/php/php-fpm.owst.sock;
    }


Using sudo instead of a dedicated PHP FPM (obsolete).

    config/etc.sudoers.d.owst


## Testing

Open the file /etc/owfs.conf in an editor and modify the line where the fake
one wire devices are configured. Add the device address 3A.554433221100.
Owserver will simulate a DS2413 Dual Channel Addressable Switch with this
address.

For example:

    server: FAKE = DS18S20,3A.554433221100


Restart owserver

    systemctl restart owserver.service


Insert fake switch into database

    echo "INSERT INTO switch (name, ow_type, ow_address, ow_pio) VALUES ('Test','DS2413','3A.554433221100','PIO.B');" | \
    sqlite3 /var/lib/owst/owst.sq3


Access the web interface with your browser:
http://<your.server.ip.address>/owst. You should see the One Wire Switch Timer
Controllisting the Test switch you previously inserted into the database. If
you reload the page multiple times the switch status should change randomly (as
expected with owserver faking the device).

By clicking on the listed switch you can add, edit and delete time programs.
Please note that time programs will not have an effect with a simulated one
wire device.
