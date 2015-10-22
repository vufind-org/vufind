Mink Zombie.js Driver
=====================

[![Latest Stable Version](https://poser.pugx.org/behat/mink-zombie-driver/v/stable.svg)](https://packagist.org/packages/behat/mink-zombie-driver)
[![Latest Unstable Version](https://poser.pugx.org/behat/mink-zombie-driver/v/unstable.svg)](https://packagist.org/packages/behat/mink-zombie-driver)
[![Total Downloads](https://poser.pugx.org/behat/mink-zombie-driver/downloads.svg)](https://packagist.org/packages/behat/mink-zombie-driver)
[![Build Status](https://travis-ci.org/minkphp/MinkZombieDriver.svg?branch=master)](https://travis-ci.org/minkphp/MinkZombieDriver)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/minkphp/MinkZombieDriver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/minkphp/MinkZombieDriver/)
[![Code Coverage](https://scrutinizer-ci.com/g/minkphp/MinkZombieDriver/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/minkphp/MinkZombieDriver/)
[![License](https://poser.pugx.org/behat/mink-zombie-driver/license.svg)](https://packagist.org/packages/behat/mink-zombie-driver)

Installation & Compatibility
----------------------------

You need a working installation of [NodeJS](http://nodejs.org/) and
[npm](https://npmjs.org/). Install the
[zombie.js](http://zombie.labnotes.org) library through npm:

``` bash
$ npm install -g zombie
```

The driver requires zombie.js __version 2.0.0 or higher__.

Use [Composer](https://getcomposer.org/) to install all required PHP dependencies:

```bash
$ composer require --dev behat/mink behat/mink-zombie-driver
```

Usage Example
-------------

```php
<?php

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\ZombieDriver,
    Behat\Mink\Driver\NodeJS\Server\ZombieServer;

$host       = '127.0.0.1';
$port       = '8124';
$nodeBinary = '/usr/local/bin/node';

$mink = new Mink(array(
    'zombie' => new Session(new ZombieDriver(new ZombieServer(
        $host, $port, $nodeBinary
    ))),
));

$mink->setDefaultSessionName('zombie');

$session = $mink->getSession();
$session->visit('http://example.org');

$page = $session->getPage();
$elem = $page->find('css', 'h1');

echo $elem->getText();
```

Copyright
---------

Copyright (c) 2011-2012 Pascal Cremer <b00gizm@gmail.com>

Maintainers
-----------

* Alexander Obuhovich [aik099](http://github.com/aik099)
