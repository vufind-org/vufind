<?php

namespace KrimDok\Search\Params;
use Zend\ServiceManager\ServiceLocatorInterface;

class PluginFactory extends \VuFind\Search\Params\PluginFactory
{
    /**
     * redirect to custom namespace solr params
     *
     * @param string $name          Name of service
     * @param string $requestedName Unfiltered name of service
     *
     * @return string               Fully qualified class name
     */
    protected function getClassName($name, $requestedName)
    {
        if ($requestedName == 'Solr') {
            return '\KrimDok\Search\Solr\Params';
        } else {
            return parent::getClassName($name, $requestedName);
        }
    }
}
