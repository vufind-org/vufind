<?php

namespace TueFind\Controller;

class AuthorityController extends \VuFind\Controller\AuthorityController {
    /**
     * Allow lookup directly via GND number
     */
    public function recordAction()
    {
        $gndNumber = $this->params()->fromQuery('gnd');
        if ($gndNumber != null) {
            $driver = $this->serviceLocator->get(\TueFind\Record\Loader::class)->loadAuthorityRecordByGNDNumber($gndNumber, 'SolrAuth');
            $request = $this->getRequest();
            $tabs = $this->getRecordTabManager()->getTabsForRecord($driver, $request);
            return $this->createViewModel(['driver' => $driver, 'tabs' => $tabs]);
        }

        return parent::recordAction();
    }
}
