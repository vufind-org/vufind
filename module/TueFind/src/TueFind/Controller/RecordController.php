<?php

namespace TueFind\Controller;

class RecordController extends \VuFind\Controller\RecordController {
    /**
     * Show redirect page if FallbackLoader was active
     *
     * @return mixed
     */
    public function homeAction()
    {

        $user = $this->getUser();
        $this->loadRecord();

        if (isset($this->driver->isFallback) && $this->driver->isFallback) {
            $params = [ 'driver' => $this->driver,
                        'originalId' => $this->params()->fromRoute('id', $this->params()->fromQuery('id'))];

            $view = $this->createViewModel($params);
            $view->setTemplate('content/snippets/record_id_changed');
            $this->getResponse()->setStatusCode(301);
            $view->user = $user;
            return $view;
        } else {
            $view = parent::homeAction();
            $view->user = $user;
            return $view;
        }

        return $this->showTab(
            $this->params()->fromRoute('tab', $this->getDefaultTab())
        );
    }

    public function publishAction()
    {
        $user = $this->getUser();
        if (!$user)
            return $this->forceLogin();
        $this->loadRecord();

        $recordLanguages = $this->driver->tryMethod('getLanguages');
        $supportPublicationLanguages = false;
        if (in_array("German", $recordLanguages) || in_array("English", $recordLanguages)) {
            $supportPublicationLanguages = true;
        }
        
        return $this->createViewModel(['driver' => $this->driver, 'user' => $user, 'supportPublicationLanguages' => $supportPublicationLanguages]);
    }

}
