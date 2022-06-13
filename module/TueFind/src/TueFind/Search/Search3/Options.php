<?php

namespace TueFind\Search\Search3;

class Options extends \TueFind\Search\Search2\Options
{
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->mainIni = $this->searchIni = $this->facetsIni = 'Search3';
        parent::__construct($configLoader);
    }


    public function getAdvancedSearchAction()
    {
        return false;
    }

    public function getVersionsAction()
    {
        return $this->displayRecordVersions ? 'search3-versions' : false;
    }

    public function getSearchAction()
    {
        return 'search3-results';
    }


}
