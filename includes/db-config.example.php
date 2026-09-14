<?php
// Copy this file to db-config.php in the site root (next to index.html).
// Git does not upload db-config.php — add it on the server via File Manager or FTP.
//
// Local XAMPP example:
//   host: 127.0.0.1
//   name: yamoonbaby-data
//   user: root
//   pass: your local MySQL password
//
// HostGator cPanel → MySQL Databases:
//   host: localhost
//   name: the FULL database name (usually cpaneluser_dbname)
//   user: the FULL username (usually cpaneluser_dbuser)
//   pass: the password you set when creating that MySQL user
//   Then add the user to the database with ALL PRIVILEGES.
return [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'cpaneluser_yamoonbaby',
    'user' => 'cpaneluser_yamoonbaby',
    'pass' => 'change-me',
    'charset' => 'utf8mb4',
];
