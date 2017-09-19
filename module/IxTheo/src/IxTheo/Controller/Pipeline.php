<?php

namespace IxTheo\Controller;
use VuFind\Controller\AbstractBase;

class Pipeline extends AbstractBase {
    function homeAction() {
        $view = $this->createViewModel();
        $view->setTemplate('pipeline/chart');
        return $view;
    }
}
