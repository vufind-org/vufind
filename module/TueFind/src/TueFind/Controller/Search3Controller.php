<?php

namespace TueFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

class Search3Controller extends \VuFind\Controller\AbstractSolrSearch
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'Search3';
        parent::__construct($sm);
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('Search3');
        return isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation;
    }
}
