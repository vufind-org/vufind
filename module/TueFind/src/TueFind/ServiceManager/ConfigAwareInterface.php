<?php

namespace TueFind\ServiceManager;

use VuFind\Config\PluginManager;

interface ConfigAwareInterface
{
    public function setConfig(PluginManager $config);
}
