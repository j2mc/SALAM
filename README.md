# SALAM
Simple Agent-Less Availability Monitor

## Debian Install Instructions
Install and secure Database:
```
apt install mariadb-client mariadb-server
mysql_secure_installation
```
This walks you through setting a root password, disabling remote access and removing unneeded items

Install PHP, nmap and git(also installs Apache for you), then clone repo:
```
apt install php7.0 php7.0-mysql php7.0-xml nmap git
cd /var/www
git clone https://github.com/j2mc/SALAM.git
```

Setup SALAM:
```
cd SALAM
mysql -u root -p < salam.sql
nano library/settings.ini
```
Change from/to email addresses, everything else you can leave as default

Configure Sendmail(EXIM): `dpkg-reconfigure exim4-config`

Setup Apache:
`nano /etc/apache2/sites-available/000-default.conf` change DocumentRoot to `/var/www/SALAM`

Then restart Apache `systemctl restart apache2`

Set SALAM to run in the background:
`crontab -e -u www-data`
Add `* * * * * php /var/www/SALAM/backend.php` to bottom of file
