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

        $view = parent::homeAction();
        $view->user = $user;
        return $view;
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
