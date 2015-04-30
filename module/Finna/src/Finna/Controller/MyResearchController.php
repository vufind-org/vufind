<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{

    /**
     * Send list of checked out books to view.
     * Added profile to view, so borrow blocks can be shown.
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        $view = parent::checkedoutAction();

        $patron = $this->catalogLogin();
        if (is_array($patron)) {
            $catalog = $this->getILS();
            $profile = $catalog->getMyProfile($patron);
            $view->profile = $profile;
        }

        return $view;
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        $view = parent::mylistAction();
        if (!$user = $this->getUser()) {
            return $view;
        }

        $view->sortList = $this->createSortList();

        return $view;
    }

    /**
     * Gather user profile data
     *
     * @return mixed
     */
    public function profileAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $values = $this->getRequest()->getPost();
        if ($this->formWasSubmitted('save_my_profile')) {
            $validator = new \Zend\Validator\EmailAddress();
            if ($validator->isValid($values->email)) {
                $user = $this->getUser();
                $user->email = $values->email;
                if (isset($values->due_date_reminder)) {
                    $user->finna_due_date_reminder = $values->due_date_reminder;
                }
                $user->save();
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            } else {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('profile_update_failed');
            }
        }

        if (is_array(parent::catalogLogin())) {
            $view = parent::profileAction();
            $profile = $view->profile;

            if ($this->formWasSubmitted('profile_password_change')) {
                $this->processPasswordChange($profile, $values);
            }
            if ($this->formWasSubmitted('save_libary_profile')) {
                $this->processLibraryDataUpdate($profile, $values);
            }

            $view->profile = $profile;
            // Todo: get actual value for password change option
            $view->changePassword = false;
        } else {
            $view = $this->createViewModel();
        }
        return $view;
    }

    /**
     * Library information address change form
     *
     * @return mixed
     */
    public function changeProfileAddressAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);

        if ($this->formWasSubmitted('addess_change_request')) {
            // ToDo: address request send
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Address request send.');
        }

        $view = $this->createViewModel();
        $view->profile = $profile;
        $view->setTemplate('myresearch/change-address-settings');
        return $view;
    }

    /**
     * Messaging settings change form
     *
     * @return mixed
     */
    public function changeMessagingSettingsAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);
        $translator = $this->getServiceLocator()->get('translator');

        if ($this->formWasSubmitted('messaging_update_request')) {
            // ToDo: messaging update request send
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Messaging update request send.');
        }

        $view = $this->createViewModel();

        if (isset($profile['messagingServices'])) {
            $view->services = $profile['messagingServices'];
            $emailDays = array();
            foreach (array(1, 2, 3, 4, 5) as $day) {
                if ($day == 1) {
                    $label = $translator
                        ->translate("messaging_settings_num_of_days");
                } else {
                    $label = $translator
                        ->translate("messaging_settings_num_of_days_plural");
                    $label = str_replace('{1}', $day, $label);
                }
                $emailDays[] = $label;
            }

            $view->emailDays = $emailDays;
            $view->days = [1, 2, 3, 4, 5];
        }
        $view->setTemplate('myresearch/change-messaging-settings');
        return $view;
    }

    /**
     * Delete own account form
     *
     * @return mixed
     */
    public function deleteOwnAccountAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel();
        if ($this->formWasSubmitted('submit')) {
            $view->success = $this->processDeleteOwnAccount();
        } elseif ($this->formWasSubmitted('reset')) {
            return $this->redirect()->toRoute(
                'default', ['controller'=> 'MyResearch', 'action' => 'Profile']
            );
        }
        $view->setTemplate('myresearch/delete-own-account');
        $view->token = $this->getSecret($this->getUser());
        return $view;
    }

    /**
     * Return the Favorites sort list options.
     *
     * @return array
     */
    public static function getFavoritesSortList()
    {
        return [
            'saved' => 'sort_saved',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'date' => 'sort_year asc',
            'format' => 'sort_format',
        ];
    }

    /**
     * Create sort list for public list page.
     * If no sort option selected, set first one from the list to default.
     *
     * @return array
     */
    protected function createSortList()
    {
        $sortOptions = self::getFavoritesSortList();
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;
        if (!$sort) {
            reset($sortOptions);
            $sort = key($sortOptions);
        }
        $sortList = [];
        foreach ($sortOptions as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'selected' => $key === $sort,
            ];
        }

        return $sortList;
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        $view = parent::holdsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Send list of storage retrieval requests to view
     *
     * @return mixed
     */
    public function storageRetrievalRequestsAction()
    {
        $view = parent::storageRetrievalRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Send list of ill requests to view
     *
     * @return mixed
     */
    public function illRequestsAction()
    {
        $view = parent::illRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Order available records to beginning of the record list
     *
     * @param type $recordList list to order
     *
     * @return type
     */
    protected function orderAvailability($recordList)
    {
        $availableRecordList = [];
        $recordListBasic = [];
        foreach ($recordList as $item) {
            if (isset($item->getExtraDetail('ils_details')['available'])
                && $item->getExtraDetail('ils_details')['available']
            ) {
                $availableRecordList[] = $item;
            } else {
                $recordListBasic[] = $item;
            }
        }
        return array_merge($availableRecordList, $recordListBasic);
    }

    /**
     * Utility function for generating a token.
     *
     * @param object $user current user
     *
     * @return string token
     */
    protected function getSecret($user)
    {
        $data = array('id' => $user->id,
                      'firstname' => $user->firstname,
                      'lastname' => $user->lastname,
                      'email' => $user->email,
                      'created' => $user->created,
                     );
        $token = new \VuFind\Crypt\HMAC('usersecret');
        return $token->generate(array_keys($data), $data);
    }

    /**
     * Change patron's password (PIN code)
     *
     * @param type $profile patron data
     * @param type $values  form values
     *
     * @return type
     */
    protected function processPasswordChange($profile, $values)
    {
        if ($values['new-password'] == $values['new-password-2']) {
            // ToDo: Save password
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Passwords ok');
        } else {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Passwords fail');
        }
    }

    /**
     * Change phone number and email from library info.
     *
     * @param type $profile patron data
     * @param type $values  form values
     *
     * @return type
     */
    protected function processLibraryDataUpdate($profile, $values)
    {
        $validator = new \Zend\Validator\EmailAddress();
        if ($validator->isValid($values->profile_email)) {
            // ToDo: Save mail
        }
        // ToDo: Save phone $values->profile_tel
    }

    /**
     * Delete user account for MyResearch module
     *
     * @return boolean
     */
    protected function processDeleteOwnAccount()
    {
        $user = $this->getUser();

        if (!$user) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('You must be logged in first');
            return false;
        }

        $token = $this->getRequest()->getPost('token', null);
        if (empty($token)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Missing token');
            return false;
        }
        if ($token !== $this->getSecret($user)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Invalid token');
            return false;
        }

        $success = $user->anonymizeAccount();

        if (!$success) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('delete_account_failure');
        }
        return $success;
    }

}
