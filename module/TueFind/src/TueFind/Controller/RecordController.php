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
        $tuefind = $this->serviceLocator->get('ViewHelperManager')->get('tuefind');
        $showDspaceLink = false;
        $dspacelink = "";

        $routeParams = $tuefind->getRouteParams();
        $recordId = $routeParams['id'];

        $this->loadRecord();
        if (isset($this->driver->isFallback) && $this->driver->isFallback) {
            $params = [ 'driver' => $this->driver,
                        'originalId' => $this->params()->fromRoute('id', $this->params()->fromQuery('id'))];
            $helper = $this->serviceLocator->get('ViewHelperManager')->get('HelpText');
            $template = $helper->getTemplate('record_id_changed', 'static');
            if ($template) {
                $view = $this->createViewModel($params);
                $view->setTemplate($template[1]);
                $this->getResponse()->setStatusCode(301);
                $this->showDspaceLink = $showDspaceLink;
                $this->dspacelink = $dspacelink;
                return $view;
            }
        } else {
            $view = parent::homeAction();

            $config = $tuefind->getConfig('tuefind');
            $publication = $tuefind->getPublicationByControlNumber($recordId);
            if (isset($publication)) {
                $showDspaceLink = true;
                $dspaceServer = $config->Publication->dspace_url_base;
                $dspacelink = $dspaceServer."/items/".$publication->external_document_guid;
            }

            $view->showDspaceLink = $showDspaceLink;
            $view->dspacelink = $dspacelink;
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
        return $this->createViewModel(['driver' => $this->driver, 'user' => $user]);
    }
}
