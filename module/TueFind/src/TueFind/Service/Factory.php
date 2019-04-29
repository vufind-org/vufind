<?php
namespace TueFind\Service;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Service\Factory {

    /**
     * Construct the search service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \TueFindSearch\Service
     */
    public static function getSearchService(ServiceManager $sm)
    {
        return new \TueFindSearch\Service(
            new \Zend\EventManager\EventManager($sm->get('SharedEventManager'))
        );
    }

}
