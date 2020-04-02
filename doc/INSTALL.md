# Installation instructions for OWST on Debian based systems

## Requirements
Owst requires the following packages. The setup desribed below relies on nginx
and PHP FPM, feel free to use any other service (e.g. apache, lighttp, fcgiwrap
with php-cgi, etc.) if you like.

    apt-get install libow-php7 php-cli php-fpm php-sqlite3 smarty3 at


## Get files from git

    git clone --depth=1 https://github.com/clemens-it/owst
	 cd owst


## Install files
With the following commands owst will be installed in the following places:
* Web interface: /srv/www/owst
* Logfile: /var/log/owst/
* Database and lock file: /var/lib/owst/
* Command line interface (CLI): /opt/owst


###Create dedicated user owst, create and set permissions for home directory, database, log file.

    useradd --home /var/lib/owst --shell /bin/bash --system owst
    mkdir -m 0750 -p /var/lib/owst
    sqlite3 /var/lib/owst/owst.sq3 < doc/create_database.sql
    touch /var/lib/owst/owst.lock
    chown -R owst.root /var/lib/owst
    chmod 640 /var/lib/owst/*

    mkdir -m 0750 -p /var/log/owst
    chmod 640 /var/log/owst/owst.log > /var/log/owst/owst.log
    chown -R owst.root /var/log/owst/


###Create directory for command line interface and copy files.

    mkdir -p -m 0755 /opt/owst
    cp -aL cli/* /opt/owst/
    chown -R owst.root /opt/owst


###Configuration for cron job, log rotation, php fpm.

    install -m 0644 -o root -g root config/etc.cron.d.owst /etc/cron.d/owst
    install -m 0644 -o root -g root config/etc.logrotate.d.owst /etc/logrotate.d/owst
    install -m 0644 -o root -g root config/etc.php.x.mods-available.owphp.ini /etc/php/7.3/mods-available/owphp.ini
    install -m 0644 -o root -g root config/etc.php.x.fpm.pool.d.owst.conf /etc/php/7.3/fpm/pool.d/owst

    # Enable php module owphp, restart PHP FPM service
    phpenmod owphp.ini
    systemctl restart php7.3-fpm.service


###Create and set permissions for web service directory, copy file for web interface.

    mkdir -m 0750 -p /srv/www/owst
    chown root.www-data /srv/www
    chmod o+x /srv/www/
    chown owst.www-data /srv/www/owst/
    cp htdocs/* /srv/www/owst/


##Configure nginx
Edit the site file through which you want to access the owst web interface. In
case of a default installation this would be /etc/nginx/sites-enabled/default.
Add the following snippet to the server section. Restart nginx afterwards.

```
# Protect files from direct web access (optional)
location ~ /owst/(include|lib|smarty) {
	deny all;
}

# Enable php-fpm (with dedicated owst user) for php files
location ~ /owst/.*\.php$ {
   include snippets/fastcgi-php.conf;
   fastcgi_pass unix:/run/php/php-fpm.owst.sock;
}
```

```
systemctl restart nginx.service
```

# Using sudo instead of a dedicated PHP FPM (obsolete).

The so called 'sudo model' supports a configuration where the web scripts are
not executed by the same user as the one who executes the command line
interface to turn on or off the switches. This is the case if PHP is run by the
webserver's user, executing the web interface's PHP scripts as user www-data.
Since Cron and At sometimes are disabled for the user www-data, or you simply
don't want the CLI to be run by this user, the possibility has been created to
feed the CLI jobs into the At-queue of another, dedicated, user. This is done
by using sudo.

Possible scenarios where the web scripts are executed with the webserver's user
could be if PHP is run as an Apache module, or if having more than one than one
daemon for FastCGI or PHP FPM is not possible for memory reasons (for example
on a Raspberry Pi). 

In case the sudo model is to be deployed, remove the comments in
htdocs/include/config.php in front of `$cfg['at_user']` and in front of
`$cfg['at_cmd_line'], $cfg['atq_cmd_line'], and $cfg['atempty_cmd_line']` **below**
`$cfg['at_user']`. Additionally, sudo needs to be configured, so install the
following file.

    install -m 0640 -o root -g root  config/etc.sudoers.d.owst /etc/sudoers.d/owst


# Testing with a Simulated One Wire Device

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
