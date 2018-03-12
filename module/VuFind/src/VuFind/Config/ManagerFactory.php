<?php

namespace VuFind\Config;

use Interop\Container\ContainerInterface;

class ManagerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        if (!file_exists(Manager::CONFIG_CACHE_DIR)) {
            mkdir(Manager::CONFIG_CACHE_DIR, 0700);
        }
        return new Manager;
    }
}
