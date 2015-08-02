Additional Installation Methods
===============================

Install from Phar
=================

Each release includes an "oclc-auth.phar" file that includes all of the files needed to run the Auth library and all of its dependencies:

- Guzzle for HTTP requests: http://docs.guzzlephp.org

- Symfony Class Loader

- Symfony Event Handler

Simply download the phar and include it in your project.

.. code:: php

   require_once('phar://PATH_TO_THE_PHAR/oclc-auth.phar');

You can import the various classes into your code

.. code:: php

   use OCLC\Auth\WSKey;
   use OCLC\User;

Install from zip
================

Each release includes an "oclc-auth.zip" file that includes all of the files needed to run the Auth library and all its dependecies:

- Guzzle for HTTP requests: http://docs.guzzlephp.org

- Symfony Class Loader

- Symfony Event Handler

Simply download it and include the autoloader in your project.
Example:

.. code:: php

   require_once '/PATH_TO_LIBRARY/autoload.php';

You can import the various classes into your code

.. code:: php

   use OCLC\Auth\WSKey;
   use OCLC\User;
