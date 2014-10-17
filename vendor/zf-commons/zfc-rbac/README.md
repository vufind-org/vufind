# ZfcRbac

[![Master Branch Build Status](https://secure.travis-ci.org/ZF-Commons/zfc-rbac.png?branch=master)](http://travis-ci.org/ZF-Commons/zfc-rbac)
[![Coverage Status](https://coveralls.io/repos/ZF-Commons/zfc-rbac/badge.png)](https://coveralls.io/r/ZF-Commons/zfc-rbac)
[![Latest Stable Version](https://poser.pugx.org/zf-commons/zfc-rbac/v/stable.png)](https://packagist.org/packages/zf-commons/zfc-rbac)
[![Latest Unstable Version](https://poser.pugx.org/zf-commons/zfc-rbac/v/unstable.png)](https://packagist.org/packages/zf-commons/zfc-rbac)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/ZF-Commons/zfc-rbac/badges/quality-score.png?s=685a2b34dc626a0af9934f9c8d246b68a8cac884)](https://scrutinizer-ci.com/g/ZF-Commons/zfc-rbac/)
[![Total Downloads](https://poser.pugx.org/zf-commons/zfc-rbac/downloads.png)](https://packagist.org/packages/zf-commons/zfc-rbac)

ZfcRbac is an access control module for Zend Framework 2, based on the RBAC permission model.

## Requirements

- PHP 5.4 or higher
- [Rbac component](https://github.com/zf-fr/rbac): this is actually a prototype for the ZF3 Rbac component.
- [Zend Framework 2.2 or higher](http://www.github.com/zendframework/zf2)

> If you are looking for older version of ZfcRbac, please refer to the 0.2.x branch.
> If you are using ZfcRbac 1.0, please upgrade to 2.0.

## Optional

- [DoctrineModule](https://github.com/doctrine/DoctrineModule): if you want to use some built-in role and permission providers.
- [ZendDeveloperTools](https://github.com/zendframework/ZendDeveloperTools): if you want to have useful stats added to
the Zend Developer toolbar.

## Upgrade

You can find an [upgrade guide](UPGRADE.md) to quickly upgrade your application from major versions of ZfcRbac.

## Installation

ZfcRbac only officially supports installation through Composer. For Composer documentation, please refer to
[getcomposer.org](http://getcomposer.org/).

Install the module:

```sh
$ php composer.phar require zf-commons/zfc-rbac:~2.3
```

Enable the module by adding `ZfcRbac` key to your `application.config.php` file. Customize the module by copy-pasting
the `zfc_rbac.global.php.dist` file to your `config/autoload` folder.

## Documentation

The official documentation is available in the [/docs](/docs) folder.

You can also find some Doctrine entities in the [/data](/data) folder that will help you to more quickly take advantage
of ZfcRbac.
