<?php
namespace KrimDok\Controller\Plugin;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Plugin\Factory
{
    /**
     * Construct the NewItems plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reserves
     */
    public static function getNewItems(ServiceManager $sm)
    {
        $search = $sm->getServiceLocator()->get('VuFind\Config')->get('searches');
        $config = isset($search->NewItem)
            ? $search->NewItem : new \Zend\Config\Config([]);
        return new NewItems($config);
    }
}
