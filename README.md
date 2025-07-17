Evo-CMS
===

Requirements
---
* PHP 7.1
* MySQL 5.5 or MariaDB 5.5 or Sqlite3
* Web server - Config provided for Apache and Nginx

Nginx configuration
---

````
location ~ /\.ht.+$ {
   deny all;
   return 404;
}

location ~ ^/db-.+$ {
   deny all;
   return 404;
}

location ~*  ^/assets/ {
	expires 2h;
}

try_files $uri $uri/ /index.php?p=$uri&$args;

error_page 404 /index.php;
include error-pages;
include php-fpm;
````
