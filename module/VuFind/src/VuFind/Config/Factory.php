<?php

namespace VuFind\Config;

use Zend\Config\Factory as Base;
use Zend\Config\Reader\Ini as IniReader;
use Symfony\Component\Yaml\Yaml as YamlParser;
use Zend\Config\Reader\Yaml as YamlReader;

class Factory extends Base
{
    /**
     * @var IniReader
     */
    protected static $iniReader;

    public static function init()
    {
        static::$iniReader = new IniReader;
        $yamlReader = new YamlReader([YamlParser::class, 'parse']);
        static::registerReader('ini', static::$iniReader);
        static::registerReader('yaml', $yamlReader);
    }

    public static function getIniReader(): IniReader
    {
        return static::$iniReader;
    }
}