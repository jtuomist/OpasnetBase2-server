# OpasnetBase2-server

A web based application for cataloging and serving datasets. 

## Requirements
 - Web server
 - PHP
 - PHP Composer
 - MongoDB server
 - SQL server
 
## Install

*Note: these instructions may be out of date*
1. Copy files to a secure location
2. Ensure the files in the offline folder are not going to be accessible (.htaccess for Apache2 included)
3. Modify user names, passwords/secrets and permissions in offline/establish.sql (these are required from interfacing applications)
4. Create a new SQL database and import offline/establish.sql
5. Create a new mongdodb database (probably don't need to initialize)
6. Edit and rename config.dist.php to config.php
7. Run PHP composer to download the php mongodb driver
8. Copy files to a location accessible to the internet