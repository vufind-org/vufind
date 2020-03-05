<?php

namespace TueFind\Search\Search2;

class Options extends \VuFind\Search\Search2\Options
{
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
    }


    public function getAdvancedSearchAction()
    {
        return false;
    }
}
