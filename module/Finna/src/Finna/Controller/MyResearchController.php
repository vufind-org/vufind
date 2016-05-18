<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{
    use OnlinePaymentControllerTrait;
    use CatalogLoginTrait;

    /**
     * Catalog Login Action
     *
     * @return mixed
     */
    public function catalogloginAction()
    {
        $result = parent::catalogloginAction();

        if (!($result instanceof \Zend\View\Model\ViewModel)) {
            return $result;
        }

        // Try to find the original action and map it to the corresponding menu item
        // since we were probably forwarded here.
        $requestedAction = '';
        $router = $this->getEvent()->getRouter();
        if ($router) {
            $route = $router->match($this->getRequest());
            if ($route) {
                $requestedAction = $route->getParam('action');
                switch ($requestedAction) {
                case 'ILLRequests':
                    break;
                case 'CheckedOut':
                    $requestedAction = 'checkedout';
                    break;
                default:
                    $requestedAction = lcfirst($requestedAction);
                    break;
                }
            }
        }
        $result->requestedAction = $requestedAction;

        return $result;
    }

    /**
     * Send list of checked out books to view.
     * Added profile to view, so borrow blocks can be shown.
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyTransactions');
        if ($view === false) {
            $view = parent::checkedoutAction();
            $view->profile = $this->getCatalogProfile();
            $transactions = count($view->transactions);
            $renewResult = $view->renewResult;
            if (isset($renewResult) && is_array($renewResult)) {
                $renewedCount = 0;
                $renewErrorCount = 0;
                foreach ($renewResult as $renew) {
                    if ($renew['success']) {
                        $renewedCount++;
                    } else {
                        $renewErrorCount++;
                    }
                }
                $flashMsg = $this->flashMessenger();
                if ($renewedCount > 0) {
                    $msg = $this->translate(
                        'renew_ok', ['%%count%%' => $renewedCount,
                        '%%transactionscount%%' => $transactions]
                    );
                    $flashMsg->setNamespace('info')->addMessage($msg);
                }
                if ($renewErrorCount > 0) {
                    $msg = $this->translate(
                        'renew_failed',
                        ['%%count%%' => $renewErrorCount]
                    );
                    $flashMsg->setNamespace('error')->addMessage($msg);
                }
            }
            // Handle sorting
            $currentSort = $this->getRequest()->getQuery('sort', 'duedate');
            $view->sortList = [
                'duedate' => [
                    'desc' => 'Due Date',
                    'url' => '?sort=duedate',
                    'selected' => $currentSort == 'duedate'
                ],
                'title' => [
                    'desc' => 'Title',
                    'url' => '?sort=title',
                    'selected' => $currentSort == 'title'
                ]
            ];

            $date = $this->getServiceLocator()->get('VuFind\DateConverter');
            $sortFunc = function ($a, $b) use ($currentSort, $date) {
                $aDetails = $a->getExtraDetail('ils_details');
                $bDetails = $b->getExtraDetail('ils_details');
                if ($currentSort == 'title') {
                    $aTitle = is_a($a, 'VuFind\\RecordDriver\\SolrDefault')
                         && !is_a($a, 'VuFind\\RecordDriver\\Missing')
                         ? $a->getSortTitle() : '';
                    if (!$aTitle) {
                        $aTitle = isset($aDetails['title'])
                            ? $aDetails['title'] : '';
                    }
                    $bTitle = is_a($b, 'VuFind\\RecordDriver\\SolrDefault')
                         && !is_a($b, 'VuFind\\RecordDriver\\Missing')
                         ? $b->getSortTitle() : '';
                    if (!$bTitle) {
                        $bTitle = isset($bDetails['title'])
                            ? $bDetails['title'] : '';
                    }
                    $result = strcmp($aTitle, $bTitle);
                    if ($result != 0) {
                        return $result;
                    }
                }

                try {
                    $aDate = isset($aDetails['duedate'])
                        ? $date->convertFromDisplayDate('U', $aDetails['duedate'])
                        : 0;
                    $bDate = isset($bDetails['duedate'])
                        ? $date->convertFromDisplayDate('U', $bDetails['duedate'])
                        : 0;
                } catch (Exception $e) {
                    return 0;
                }

                return $aDate - $bDate;
            };

            $transactions = $view->transactions;
            usort($transactions, $sortFunc);
            $view->transactions = $transactions;
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
        $user = $this->getUser();

        if ($results = $view->results) {
            $list = $results->getListObject();

            // Redirect anonymous users and list visitors to public list URL
            if ($list && $list->isPublic()
                && (!$user || $user->id != $list->user_id)
            ) {
                return $this->redirect()->toRoute('list-page', ['lid' => $list->id]);
            }
        }

        if (!$user) {
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
        if ($this->formWasSubmitted('saveUserProfile')) {
            $validator = new \Zend\Validator\EmailAddress();
            if ($validator->isValid($values->email)) {
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

        $view = parent::profileAction();
        $profile = $view->profile;

        if ($this->formWasSubmitted('saveLibraryProfile')) {
            $this->processLibraryDataUpdate($profile, $values, $user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('profile_update');
            $view = parent::profileAction();
            $profile = $view->profile;
        }

        $parentTemplate = $view->getTemplate();
        // If returned view is not profile view, show it below our profile part.
        if ($parentTemplate != '' && $parentTemplate != 'myresearch/profile') {
            $childView = $this->createViewModel();
            $childView->setTemplate('myresearch/profile');

            $compoundView = $this->createViewModel();
            $compoundView->addChild($childView, 'child');
            $compoundView->addChild($view, 'parent');

            return $compoundView;
        }

        // Check if due date reminder settings should be displayed
        $config = $this->getConfig();
        $view->hideDueDateReminder = $user->finna_due_date_reminder == 0
            && isset($config->Site->hideDueDateReminder)
            && $config->Site->hideDueDateReminder;

        // Check whether to hide email address in profile
        $view->hideProfileEmailAddress
            = isset($config->Site->hideProfileEmailAddress)
            && $config->Site->hideProfileEmailAddress;

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
        $view = $this->createViewIfUnsupported('updateAddress', true);
        if ($view) {
            return $view;
        }
        $updateConfig = $catalog->checkFunction('updateAddress', $patron);
        $profile = $catalog->getMyProfile($patron);
        $fields = [];
        if (!empty($updateConfig['fields'])) {
            foreach ($updateConfig['fields'] as $fieldConfig) {
                list($label, $field) = explode(':', $fieldConfig);
                $fields[$field] = ['label' => $label];
            }
        }
        if (empty($fields)) {
            $fields = [
                'address1' => ['label' => 'Address'],
                'zip' => ['label' => 'Zip'],
                'city' => ['label' => 'City'],
                'country' => ['label' => 'Country']
            ];

            if (false === $catalog->checkFunction('updateEmail', $patron)) {
                $fields['email'] = ['label' => 'Email'];
            }
            if (false === $catalog->checkFunction('updatePhone', $patron)) {
                $fields['phone'] = ['label' => 'Phone'];
            }
        }

        $view = $this->createViewModel();
        $view->fields = $fields;

        if ($this->formWasSubmitted('address_change_request')) {
            $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

            $config = $this->getILS()->getConfig('updateAddress', $patron);
            if (!isset($config['emailAddress'])) {
                throw new \Exception(
                    'Missing emailAddress in ILS updateAddress settings'
                );
            }
            $recipient = $config['emailAddress'];

            $this->sendChangeRequestEmail(
                $patron, $profile, $data, $fields, $recipient,
                'Osoitteenmuutospyyntö', 'change-address'
            );
            $this->flashMessenger()
                ->addSuccessMessage('request_change_done');
            $view->requestCompleted = true;
        }

        $view->profile = $profile;
        $view->config = $updateConfig;
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
        $view = $this->createViewModel();

        if ($this->formWasSubmitted('messaging_update_request')) {
            $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

            $data['pickUpNotice'] = $this->translate(
                'messaging_settings_method_' . $data['pickUpNotice'],
                null,
                $data['pickUpNotice']
            );
            $data['overdueNotice'] = $this->translate(
                'messaging_settings_method_' . $data['overdueNotice'],
                null,
                $data['overdueNotice']
            );
            if ($data['dueDateAlert'] == 0) {
                $data['dueDateAlert']
                    = $this->translate('messaging_settings_method_none');
            } elseif ($data['dueDateAlert'] == 1) {
                $data['dueDateAlert']
                    = $this->translate('messaging_settings_num_of_days');
            } else {
                $data['dueDateAlert'] = $this->translate(
                    'messaging_settings_num_of_days_plural',
                    ['%%days%%' => $data['dueDateAlert']]
                );
            }

            $config = $this->getILS()->getConfig('updateMessagingSettings', $patron);
            if (!isset($config['emailAddress'])) {
                throw new \Exception(
                    'Missing emailAddress in ILS updateMessagingSettings'
                );
            }
            $recipient = $config['emailAddress'];

            $this->sendChangeRequestEmail(
                $patron,  $profile, $data, [], $recipient,
                'Viestiasetusten muutospyyntö', 'change-messaging-settings'
            );
            $this->flashMessenger()
                ->addSuccessMessage('request_change_done');
            $view->requestCompleted = true;
        }

        if (isset($profile['messagingServices'])) {
            $view->services = $profile['messagingServices'];
            $emailDays = [];
            foreach ([1, 2, 3, 4, 5] as $day) {
                if ($day == 1) {
                    $label = $this->translate('messaging_settings_num_of_days');
                } else {
                    $label = $this->translate(
                        'messaging_settings_num_of_days_plural',
                        ['%%days%%' => $day]
                    );
                }
                $emailDays[] = $label;
            }

            $view->emailDays = $emailDays;
            $view->days = [1, 2, 3, 4, 5];
            $view->profile = $profile;
        }
        $view->setTemplate('myresearch/change-messaging-settings');
        return $view;
    }

    /**
     * Delete account form
     *
     * @return mixed
     */
    public function deleteAccountAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel();
        $view->accountDeleted = false;
        $view->token = $this->getSecret($this->getUser());
        if ($this->formWasSubmitted('submit')) {
            $success = $this->processDeleteAccount();
            if ($success) {
                $view->accountDeleted = true;
                $view->redirectUrl = $this->getAuthManager()->logout(
                    $this->getServerUrl('home')
                );
            }
        } elseif ($this->formWasSubmitted('reset')) {
            return $this->redirect()->toRoute(
                'default', ['controller' => 'MyResearch', 'action' => 'Profile']
            );
        }
        $view->setTemplate('myresearch/delete-account');
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
            'id desc' => 'sort_saved',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'year' => 'sort_year asc',
            'format' => 'sort_format',
        ];
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyHolds');
        if ($view === false) {
            $view = parent::holdsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Save alert schedule for a saved search into DB
     *
     * @return mixed
     */
    public function savesearchAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        $schedule = $this->params()->fromQuery('schedule', false);
        $sid = $this->params()->fromQuery('searchid', false);

        if ($schedule !== false && $sid !== false) {
            $search = $this->getTable('Search');
            $baseurl = rtrim($this->getServerUrl('home'), '/');
            $row = $search->select(
                ['id' => $sid, 'user_id' => $user->id]
            )->current();
            if ($row) {
                $row->setSchedule($schedule, $baseurl);
            }
            return $this->redirect()->toRoute('search-history');
        } else {
            parent::savesearchAction();
        }
    }

    /**
     * Send list of storage retrieval requests to view
     *
     * @return mixed
     */
    public function storageRetrievalRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('StorageRetrievalRequests', true);
        if ($view === false) {
            $view = parent::storageRetrievalRequestsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Send list of ill requests to view
     *
     * @return mixed
     */
    public function illRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('ILLRequests', true);
        if ($view === false) {
            $view = parent::illRequestsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Send list of fines to view
     *
     * @return mixed
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyFines');
        if ($view === false) {
            $view = parent::finesAction();
            $view->profile = $this->getCatalogProfile();
            if (isset($patron['source'])) {
                $result = $this->handleOnlinePayment($patron, $view->fines, $view);
            }
        }
        return $view;
    }

    /**
     * Unsubscribe a scheduled alert for a saved search.
     *
     * @return mixed
     */
    public function unsubscribeAction()
    {
        $id = $this->params()->fromQuery('id', false);
        $key = $this->params()->fromQuery('key', false);
        $type = $this->params()->fromQuery('type', false);

        if ($id === false || $key === false || $type === false) {
            throw new \Exception('Missing parameters.');
        }

        $view = $this->createViewModel();

        if ($this->params()->fromQuery('confirm', false) == 1) {
            if ($type == 'alert') {
                $search
                    = $this->getTable('Search')->select(['id' => $id])->current();
                if (!$search) {
                    throw new \Exception('Invalid parameters.');
                }
                $user = $this->getTable('User')->getById($search->user_id);

                if ($key !== $search->getUnsubscribeSecret(
                    $this->getServiceLocator()->get('VuFind\HMAC'), $user
                )) {
                    throw new \Exception('Invalid parameters.');
                }
                $search->setSchedule(0);
            } else if ($type == 'reminder') {
                $user = $this->getTable('User')->select(['id' => $id])->current();
                if (!$user) {
                    throw new \Exception('Invalid parameters.');
                }
                $dueDateTable = $this->getTable('due-date-reminder');
                if ($key !== $dueDateTable->getUnsubscribeSecret(
                    $this->getServiceLocator()->get('VuFind\HMAC'), $user, $user->id
                )) {
                    throw new \Exception('Invalid parameters.');
                }
                $user->finna_due_date_reminder = 0;
                $user->save();
            }
            $view->success = true;
        } else {
            $view->unsubscribeUrl
                = $this->getRequest()->getRequestUri() . '&confirm=1';
        }
        return $view;
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
     * Check if current library card supports a function. If not supported, show
     * a message and a notice about the possibility to change library card.
     *
     * @param string  $function      Function to check
     * @param boolean $checkFunction Use checkFunction() if true,
     * checkCapability() otherwise
     *
     * @return mixed \Zend\View if the function is not supported, false otherwise
     */
    protected function createViewIfUnsupported($function, $checkFunction = false)
    {
        $params = ['patron' => $this->catalogLogin()];
        if ($checkFunction) {
            $supported = $this->getILS()->checkFunction($function, $params);
        } else {
            $supported = $this->getILS()->checkCapability($function, $params);
        }

        if (!$supported) {
            $view = $this->createViewModel();
            $view->noSupport = true;
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('no_ils_support_for_' . strtolower($function));
            return $view;
        }
        return false;
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
        if ($recordList === null) {
            return [];
        }

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
        $data = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'created' => $user->created,
        ];
        $token = new \VuFind\Crypt\HMAC('usersecret');
        return $token->generate(array_keys($data), $data);
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
        // Connect to the ILS:
        $catalog = $this->getILS();

        $validator = new \Zend\Validator\EmailAddress();
        if ($validator->isValid($values->profile_email)) {
            //Update Email
            $result = $catalog->updateEmail($profile, $values->profile_email);
        }
        // Update Phone
        $result = $result && $catalog->updatePhone($profile, $values->profile_tel);

        return $result;
    }

    /**
     * Delete user account for MyResearch module
     *
     * @return boolean
     */
    protected function processDeleteAccount()
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

    /**
     * Send a change request message (e.g. address change) to the library
     *
     * @param array  $patron    Patron
     * @param array  $profile   Patron profile
     * @param array  $data      Change data
     * @param array  $fields    Form fields for address change request
     * @param string $recipient Email recipient
     * @param string $subject   Email subject
     * @param string $template  Email template
     *
     * @return void
     */
    protected function sendChangeRequestEmail($patron, $profile, $data, $fields,
        $recipient, $subject, $template
    ) {
        list($library, $username) = explode('.', $patron['cat_username']);
        $library = $this->translate("source_$library", null, $library);
        $name = trim(
            (isset($patron['firstname']) ? $patron['firstname'] : '')
            . ' '
            . (isset($patron['lastname']) ? $patron['lastname'] : '')
        );
        $email = isset($patron['email']) ? $patron['email'] : '';
        if (!$email) {
            $user = $this->getUser();
            if (!empty($user['email'])) {
                $email = $user['email'];
            }
        }

        $params = [
            'library' => $library,
            'username' => $patron['cat_username'],
            'name' => $name,
            'email' => $email,
            'patron' => $patron,
            'profile' => $profile,
            'data' => $data,
            'fields' => $fields
        ];
        $renderer = $this->getViewRenderer();
        $message = $renderer->render("Email/$template.phtml", $params);
        $subject = $this->getConfig()->Site->title . ": $subject";
        $from = $this->getConfig()->Site->email;

        $this->getServiceLocator()->get('VuFind\Mailer')->send(
            $recipient, $from, $subject, $message
        );
    }

    /**
     * Get the current patron profile.
     *
     * @return mixed
     */
    protected function getCatalogProfile()
    {
        $patron = $this->catalogLogin();
        if (is_array($patron)) {
            $catalog = $this->getILS();
            $profile = $catalog->getMyProfile($patron);
            return $profile;
        }
        return null;
    }
}
