<?php

namespace VuFind\Search\EPF;

use VuFindSearch\ParamBag;

class Params extends \VuFind\Search\EDS\AbstractEDSParams
{

    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        // The documentation says that 'view' is optional, 
        // but omitting it causes an error.
        // https://connect.ebsco.com/s/article/Publication-Finder-API-Reference-Guide-Search
        $view = $this->getEpfView();
        $backendParams->set('view', $view);

        $this->createBackendFilterParameters($backendParams);

        return $backendParams;
    }

    public function getView()
    {
        $viewArr = explode('|', $this->view ?? '');
        return $viewArr[0];
    }

    public function getEpfView() {
        return $this->options->getEpfView();
    }

}