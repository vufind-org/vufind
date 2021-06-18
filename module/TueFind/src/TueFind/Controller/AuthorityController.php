<?php

namespace TueFind\Controller;

class AuthorityController extends \VuFind\Controller\AuthorityController {
    protected function getUserAccessState($authorityId, $userId = null): array
    {
        $table = $this->getTable('user_authority');
        $row = $table->getByAuthorityId($authorityId);

        $result = ['availability' => null, 'access_state' => null];
        if ($row == null) {
            // Nobody got permission yet, feel free to take it
            $result['availability'] = 'free';
        } else {
            $result['access_state'] = $row->access_state;
            if (isset($userId) && ($userId == $row->user_id)) {
                $result['availability'] = 'mine';
            } else {
                $result['availability'] = 'other';
            }
        }

        return $result;
    }

    public function recordAction()
    {
        $gndNumber = $this->params()->fromQuery('gnd');
        if ($gndNumber != null) {
            $driver = $this->serviceLocator->get(\TueFind\Record\Loader::class)->loadAuthorityRecordByGNDNumber($gndNumber, 'SolrAuth');
        } else {
            $id = $this->params()->fromQuery('id');
            $driver = $this->serviceLocator->get(\VuFind\Record\Loader::class)
                ->load($id, 'SolrAuth');
        }

        $user = $this->getUser();
        $request = $this->getRequest();
        $tabs = $this->getRecordTabManager()->getTabsForRecord($driver, $request);
        return $this->createViewModel(['driver' => $driver,
                                       'tabs' => $tabs,
                                       'user' => $user,
                                       'user_access' => $this->getUserAccessState($driver->getUniqueId(), $user->id ?? null)]);
    }

    public function requestAccessAction()
    {
        $authorityId = $this->params()->fromRoute('authority_id');
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        if ($this->params()->fromPost('request') == 'yes') {
            $table = $this->getTable('user_authority');
            $table->addRequest($user->id, $authorityId);

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
            $config = $this->getConfig();
            $mailer = $this->serviceLocator->get(\VuFind\Mailer\Mailer::class);
            $mailer->send($config->Site->email, $config->Site->email_from, 'A user has requested access to an authority dataset', $message);
        }

        return $this->createViewModel(['user_access' => $this->getUserAccessState($authorityId, $user->id)]);
    }
}
