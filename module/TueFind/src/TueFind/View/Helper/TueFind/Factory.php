<?php

namespace TueFind\View\Helper\TueFind;

use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Construct the TueFind helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return TueFind
     */
    public static function getTueFind(ServiceManager $sm)
    {
        return new TueFind($sm);
    }
}
