<?php

namespace TueFind\Controller;

class AuthorityController extends \VuFind\Controller\AuthorityController
{

    protected $fallbackDefaultTab = 'Details';

    /**
     * VuFind's Authority Controller does not support multiple tabs,
     * so We need to add tab navigation, partially taken from the RecordController.
     */
    public function recordAction()
    {
        $view = parent::recordAction();

        $id = $this->params()->fromQuery('id');
        $driver = $this->serviceLocator->get('VuFind\Record\Loader')
            ->load($id, 'SolrAuth');
        $request = $this->getRequest();
        $rtpm = $this->serviceLocator->get('VuFind\RecordTab\PluginManager');
        $details = $rtpm->getTabDetailsForRecord(
            $driver, $this->getRecordTabConfig(), $request,
            $this->fallbackDefaultTab
        );

        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

        $view->activeTab = $parameters['tab'] ?? $this->fallbackDefaultTab;
        $view->allTabs = $details['tabs'];
        $view->defaultTab = $details['default'] ? $details['default'] : false;
        $view->backgroundTabs = $rtpm->getBackgroundTabNames(
            $driver, $this->getRecordTabConfig()
        );

        return $view;
    }
}
