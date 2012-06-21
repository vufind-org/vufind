VuFind
======

Introduction
------------
VuFind is an open source discovery environment for searching a collection of
records.  To learn more, visit http://vufind.org.


Installation
------------

WARNING: VuFind 2.0 is a work in progress.

Instructions
------------

1. Install PHP 5.3 and MySQL; the existing installation instructions for VuFind 1.x
will help you set up these dependencies (see http://vufind.org/wiki/installation).

2. Copy the VuFind 2 code to /usr/local/vufind2 (or another directory of your
choice).

3. Run the VuFind2 install script:

cd /usr/local/vufind2
php install.php

If you are testing VuFind 2 on a server that is also running VuFind 1.x, be sure you
choose a base path for the URL that does not conflict with VuFind 1.x (/vufind2
is a good choice).

4. Load VuFind2's Apache configuration file using the instructions displayed by the
install script.

5. Ensure that your web server can write to the "local" directories

sudo chown -R www-data:www-data /usr/local/vufind2/local

(Note that this step may vary depending on your operating system -- you may need to
replace www-data with apache).

6. To finish configuration, navigate to http://your-vufind-server/vufind2/Install and
follow the on-screen instructions.  If you are upgrading from VuFind 1.x, navigate to
http://your-vufind-server/vufind2/Upgrade instead.

7. If you run into problems, uncomment the "SetEnv VUFIND_ENV development" line in
/usr/local/vufind2/local/httpd-vufind.conf and restart Apache to see more detailed
error messages.

--------------------------------

You should now have a working VuFind 2.0 instance running at
http://your-vufind-server/vufind2
