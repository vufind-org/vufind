<?php

namespace TueFind\ServiceManager;

use VuFind\Config\PluginManager;

trait ConfigAwareTrait
{
    /**
     * Config plugin manager
     *
     * @var PluginManager
     */
    protected $config = null;

    /**
     * Set plugin manager
     *
     * @param $manager PluginManager
     */
    public function setConfig(PluginManager $manager)
    {
        $this->config = $manager;
    }

    /**
     * Get config object.
     *
     * @var string
     *
     * @return mixed
     */
    protected function getConfig($id='config')
    {
        return $this->config->get($id);
    }
}
