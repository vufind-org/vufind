<?php
namespace TueFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

class Search3recordController extends \VuFind\Controller\AbstractRecord
{
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $this->layout()->searchClassId = $view->searchClassId = $this->searchClassId;
        $view->driver = $this->loadRecord();
        $view->user = $this->getUser();
        return $view;
   }
}
