<?php

namespace TueFind\Controller;

class AuthorityController extends \VuFind\Controller\AuthorityController {

    use TabsTrait;

    /**
     * This action needs to be overwritten because it is meant to be a redirect
     * to the recordAction under certain circumstances (especially tabs).
     * Therefore we need to make sure that additional parameters
     * like GND number or the active tab will not be lost.
     */
    public function homeAction()
    {
        $tab = $this->params()->fromRoute('tab', false);

        // If we came in with a record ID, forward to the record action:
        if ($id = $this->params()->fromRoute('id', false)) {
            $this->getRequest()->getQuery()->set('id', $id);
            $this->getRequest()->getQuery()->set('tab', $tab);
            return $this->forwardTo('Authority', 'Record');
        } elseif ($gndNumber = $this->params()->fromQuery('gnd')) {
            $this->getRequest()->getQuery()->set('gnd', $gndNumber);
            $this->getRequest()->getQuery()->set('tab', $tab);
            return $this->forwardTo('Authority', 'Record');
        }

        // Default behavior:
        return parent::homeAction();
    }

    public function loadRecord()
    {
        $gndNumber = $this->params()->fromQuery('gnd');
        if ($gndNumber != null) {
            $this->driver = $this->serviceLocator->get(\TueFind\Record\Loader::class)->loadAuthorityRecordByGNDNumber($gndNumber, 'SolrAuth');
        } else {
            $id = $this->params()->fromQuery('id');
            $this->driver = $this->serviceLocator->get(\VuFind\Record\Loader::class)
                ->load($id, 'SolrAuth');
        }
        return $this->driver;
    }

    public function recordAction()
    {
        $driver = $this->loadRecord();
        $user = $this->getUser();
        $request = $this->getRequest();
        $view = $this->showTab($this->params()->fromQuery('tab', $this->getDefaultTab()));
        $view->driver = $driver;
        $view->user = $user;
        return $view;
    }

    public function requestAccessAction()
    {
        $authorityId = $this->params()->fromRoute('authority_id');
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        if ($this->params()->fromPost('request') == 'yes') {
            $userAuthorityTable = $this->getTable('user_authority');
            $userAuthorityTable->addRequest($user->id, $authorityId);

            // body
            $renderer = $this->getViewRenderer();
            $message = $renderer->render(
                'Email/authority-request-access.phtml',
                [
                    'userName' => $user->username,
                    'userEmail' => $user->email,
                    'authorityUrl' => $this->getServerUrl('solrauthrecord') . $authorityId,
                    'processRequestUrl' => $this->getServerUrl('adminfrontend-showuserauthorities'),
                ]
            );

            // receivers
            $userTable = $this->getTable('user');
            $receivingUsers = $userTable->getByRight('user_authorities');
            $receivers = new \Laminas\Mail\AddressList();
            foreach ($receivingUsers as $receivingUser) {
                $receivers->add($receivingUser->email);
            }

            $config = $this->getConfig();
            if (count($receivers) == 0) {
                $receivers = $config->Site->email;
            }

            // send mail
            $mailer = $this->serviceLocator->get(\VuFind\Mailer\Mailer::class);
            $mailer->send($receivers, $config->Site->email_from, 'A user has requested access to an authority dataset', $message);
        }

        return $this->createViewModel(['userId' => $user->id]);
    }
}
