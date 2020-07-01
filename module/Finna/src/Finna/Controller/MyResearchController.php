<?php
/**
 * MyResearch Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\ListPermission as ListPermissionException;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{
    use FinnaOnlinePaymentControllerTrait;
    use CatalogLoginTrait;

    /**
     * Catalog Login Action
     *
     * @return mixed
     */
    public function catalogloginAction()
    {
        $result = parent::catalogloginAction();

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
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        $view = parent::loginAction();
        if ($view instanceof \Laminas\View\Model\ViewModel) {
            if ($defaultTarget = $this->params()->fromQuery('target')) {
                $view->defaultTarget = $defaultTarget;
            }
        }
        return $view;
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

        if ($view = $this->createViewIfUnsupported('getMyTransactions')) {
            return $view;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Display account blocks, if any:
        $this->addAccountBlocksToFlashMessenger($catalog, $patron);

        // Get the current renewal status and process renewal form, if necessary:
        $renewStatus = $catalog->checkFunction('Renewals', compact('patron'));
        $renewResult = $renewStatus
            ? $this->renewals()->processRenewals(
                $this->getRequest()->getPost(), $catalog, $patron
            )
            : [];

        // By default, assume we will not need to display a renewal form:
        $renewForm = false;

        // Get paging setup:
        $config = $this->getConfig();
        $pageOptions = $this->getPaginationHelper()->getOptions(
            (int)$this->params()->fromQuery('page', 1),
            $this->params()->fromQuery('sort'),
            isset($config->Catalog->checked_out_page_size)
                ? $config->Catalog->checked_out_page_size : 50,
            $catalog->checkFunction('getMyTransactions', compact('patron'))
        );

        // Get checked out item details:
        $result = $catalog->getMyTransactions($patron, $pageOptions['ilsParams']);

        // Support also older driver return value:
        if (!isset($result['count'])) {
            $result = [
                'count' => count($result),
                'records' => $result
            ];
        }

        // Build paginator if needed:
        $paginator = $this->getPaginationHelper()->getPaginator(
            $pageOptions, $result['count'], $result['records']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        if (!$pageOptions['ilsPaging']) {
            // Handle sorting
            $currentSort = $this->getRequest()->getQuery('sort', 'duedate');
            if (!in_array($currentSort, ['duedate', 'title'])) {
                $currentSort = 'duedate';
            }
            $pageOptions['ilsParams']['sort'] = $currentSort;
            $sortList = [
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

            $date = $this->serviceLocator->get(\VuFind\Date\Converter::class);
            $sortFunc = function ($a, $b) use ($currentSort, $date) {
                if ($currentSort == 'title') {
                    $aTitle = $a['title'] ?? '';
                    $bTitle = $b['title'] ?? '';
                    $result = strcmp($aTitle, $bTitle);
                    if ($result != 0) {
                        return $result;
                    }
                }

                try {
                    $aDate = isset($a['duedate'])
                        ? $date->convertFromDisplayDate('U', $a['duedate'])
                        : 0;
                    $bDate = isset($b['duedate'])
                        ? $date->convertFromDisplayDate('U', $b['duedate'])
                        : 0;
                } catch (\Exception $e) {
                    return 0;
                }

                return $aDate - $bDate;
            };

            usort($result['records'], $sortFunc);
        } else {
            $sortList = $pageOptions['sortList'];
        }

        $transactions = $hiddenTransactions = [];
        foreach ($result['records'] as $i => $current) {
            // Add renewal details if appropriate:
            $current = $this->renewals()->addRenewDetails(
                $catalog, $current, $renewStatus
            );
            if ($renewStatus && !isset($current['renew_link'])
                && $current['renewable']
            ) {
                // Enable renewal form if necessary:
                $renewForm = true;
            }

            // Build record driver (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $transactions[] = $this->getDriverForILSRecord($current);
            } else {
                $hiddenTransactions[] = $current;
            }
        }

        $displayItemBarcode
            = !empty($config->Catalog->display_checked_out_item_barcode);

        // Display renewal information
        $renewedCount = 0;
        $renewErrorCount = 0;
        foreach ($renewResult as $renew) {
            if ($renew['success']) {
                $renewedCount++;
            } else {
                $renewErrorCount++;
            }
        }
        if ($renewedCount > 0) {
            $msg = $this->translate(
                'renew_ok', ['%%count%%' => $renewedCount,
                '%%transactionscount%%' => $result['count']]
            );
            $this->flashMessenger()->addInfoMessage($msg);
        }
        if ($renewErrorCount > 0) {
            $msg = $this->translate(
                'renew_failed',
                ['%%count%%' => $renewErrorCount]
            );
            $this->flashMessenger()->addErrorMessage($msg);
        }

        $params = $pageOptions['ilsParams'];
        $ilsPaging = $pageOptions['ilsPaging'];
        $view = $this->createViewModel(
            compact(
                'transactions', 'renewForm', 'renewResult', 'paginator', 'params',
                'hiddenTransactions', 'displayItemBarcode', 'sortList', 'ilsPaging'
            )
        );

        $view->blocks = $this->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Purge historic loans action.
     *
     * @return mixed
     */
    public function purgeHistoricLoansAction()
    {
        if ($this->formWasSubmitted('cancel', false)) {
            return $this->redirect()->toRoute('myresearch-historicloans');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('purgeTransactionHistory')) {
            return $view;
        }

        // Set up CSRF:
        $csrfValidator = $this->serviceLocator->get(\VuFind\Validator\Csrf::class);

        if ($this->formWasSubmitted('submit', false)) {
            $csrf = $this->getRequest()->getPost()->get('csrf');
            if (!$csrfValidator->isValid($csrf)) {
                throw new \Exception('An error has occurred');
            }
            // After successful token verification, clear list to shrink session:
            $csrfValidator->trimTokenList(0);
            $catalog = $this->getILS();
            $result = $catalog->purgeTransactionHistory($patron);
            $this->flashMessenger()->addMessage(
                $result['status'], $result['success'] ? 'error' : 'info'
            );
            return $this->redirect()->toRoute('myresearch-historicloans');
        }

        $view = $this->createViewModel();
        $view->csrf = $csrfValidator->getHash(true);

        return $view;
    }

    /**
     * Send user's saved favorites from a particular list to the edit view
     *
     * @return mixed
     */
    public function editlistAction()
    {
        $view = parent::editlistAction();
        if ($view instanceof \Laminas\Http\PhpEnvironment\Response
            && !empty($url = $this->getFollowupUrl())
        ) {
            return $this->redirect()->toUrl($url);
        }
        $this->setFollowupUrlToReferer();
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
            if ($list) {
                $this->rememberCurrentSearchUrl();
            } else {
                $memory  = $this->serviceLocator->get(\VuFind\Search\Memory::class);
                $memory->rememberSearch(
                    $this->url()->fromRoute('myresearch-favorites')
                );
            }
        }

        if (!$user) {
            return $view;
        }

        $view->sortList = $this->createSortList($results->getListObject());

        return $view;
    }

    /**
     * Show user's own favorite list (max. 1000) to the view
     *
     * @return mixed
     */
    public function sortListAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        $listId = $this->params()->fromRoute('id');
        if (null === $listId) {
            throw new ListPermissionException('Cannot sort all favorites list');
        }

        if ($this->formWasSubmitted('cancelOrdering')) {
            return $this->redirect()->toRoute('userList', ['id' => $listId]);
        }
        if ($this->formWasSubmitted('saveOrdering')) {
            $orderedList = json_decode(
                $this->params()->fromPost('orderedList'), true
            );
            $table = $this->getTable('UserResource');
            $listID = $this->params()->fromPost('list_id');
            if (empty($listID) || empty($orderedList)
                || !$table->saveCustomFavoriteOrder($user->id, $listID, $orderedList)
            ) {
                $this->flashMessenger()->addErrorMessage('An error has occurred');
            } else {
                $this->flashMessenger()->addMessage('list_order_saved', 'success');
            }
        }

        // If we got this far, we just need to display the favorites:
        try {
            $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);

            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $request = $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray()
                + ['id' => $listId];

            $setupCallback = function ($runner, $params, $searchId) {
                $params->setLimit(1000);
            };
            $results = $runner->run($request, 'Favorites', $setupCallback);

            $url = $this->getViewRenderer()->plugin('url');
            $listUrl = $url('home') . 'MyResearch/MyList/' . $listId;

            return $this->createViewModel(
                ['params' => $results->getParams(),
                 'results' => $results,
                 'listUrl' => $listUrl
                ]
            );
        } catch (ListPermissionException $e) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw $e;
        }
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
        if (isset($values->due_date_reminder)) {
            $user->setFinnaDueDateReminder($values->due_date_reminder);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('profile_update');
        }

        if ($this->formWasSubmitted('saveUserProfile')) {
            $validator = new \Laminas\Validator\EmailAddress();
            $showSuccess = $showError = false;
            if ('' === $values->email || $validator->isValid($values->email)) {
                $user->email = $values->email;
                $user->save();
                $showSuccess = true;
            } else {
                $showError = true;
            }

            $nicknameAvailable = $this->checkIfAvailableNickname(
                $values->finna_nickname
            );
            $nicknameValid = $this->checkIfValidNickname($values->finna_nickname);
            if (empty($values->finna_nickname)) {
                $user->finna_nickname = null;
                $user->save();
                $showSuccess = true;
            } elseif (!$nicknameValid) {
                $showError = true;
            } elseif ($nicknameAvailable) {
                $user->finna_nickname = $values->finna_nickname;
                $user->save();
                $showSuccess = true;
            } elseif ($user->finna_nickname === $values->finna_nickname) {
                $showSuccess = true;
            } else {
                $showSuccess = $showError = false;
                $this->flashMessenger()->setNamespace('error')
                    ->addErrorMessage('profile_update_nickname_taken');
            }
            if ($showError) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('profile_update_failed');
            } elseif ($showSuccess) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            }
        }

        $view = parent::profileAction();
        $patron = $this->catalogLogin();

        if (is_array($patron) && $this->formWasSubmitted('saveLibraryProfile')) {
            if ($this->processLibraryDataUpdate($patron, $values, $user)) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            }
            $view = parent::profileAction();
        }

        // Check if due date reminder settings should be displayed
        $config = $this->getConfig();
        $view->hideDueDateReminder = $user->finna_due_date_reminder == 0
            && isset($config->Site->hideDueDateReminder)
            && $config->Site->hideDueDateReminder;
        if (!$view->hideDueDateReminder && is_array($patron)) {
            $catalog = $this->getILS();
            $ddrConfig = $catalog->getConfig('dueDateReminder', $patron);
            if (isset($ddrConfig['enabled']) && !$ddrConfig['enabled']) {
                $view->hideDueDateReminder = true;
            }
        }

        // Check whether to hide email address in profile
        $view->hideProfileEmailAddress
            = isset($config->Site->hideProfileEmailAddress)
            && $config->Site->hideProfileEmailAddress;

        if (is_array($patron)) {
            $view->blocks = $this->getAccountBlocks($patron);
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
        if ($view = $this->createViewIfUnsupported('updateAddress', true)) {
            return $view;
        }

        $catalog = $this->getILS();
        $updateConfig = $catalog->checkFunction('updateAddress', compact('patron'));
        $profile = $catalog->getMyProfile($patron);
        $fields = [];
        if (!empty($updateConfig['fields'])) {
            foreach ($updateConfig['fields'] as $fieldConfig) {
                if (is_array($fieldConfig)) {
                    $fields[$fieldConfig['field']] = $fieldConfig;
                    if (!isset($fields[$fieldConfig['field']]['required'])) {
                        $fields[$fieldConfig['field']]['required'] = false;
                    }
                } else {
                    $parts = explode(':', $fieldConfig);
                    $field = $parts[1] ?? '';
                    if (!$field) {
                        continue;
                    }
                    $fields[$field] = [
                        'label' => $parts[0],
                        'type' => $parts[2] ?? 'text',
                        'required' => ($parts[3] ?? '') === 'required'
                    ];
                }
            }
        }
        if (empty($fields)) {
            $fields = [
                'address1'
                    => ['label' => 'Address', 'type' => 'text', 'required' => true],
                'zip' => ['label' => 'Zip', 'type' => 'text', 'required' => true],
                'city' => ['label' => 'City', 'type' => 'text', 'required' => true],
                'country'
                    => ['label' => 'Country', 'type' => 'text', 'required' => true]
            ];

            if (false === $catalog->checkFunction('updateEmail', compact('patron'))
            ) {
                $fields['email']
                    = ['label' => 'Email', 'type' => 'email', 'required' => true];
            }
            if (false === $catalog->checkFunction('updatePhone', compact('patron'))
            ) {
                $fields['phone']
                    = ['label' => 'Phone', 'type' => 'tel', 'required' => true];
            }
            $updateSms
                = $catalog->checkFunction('updateSmsNumber', compact('patron'));
            if (false === $updateSms) {
                $fields['sms_number'] = [
                    'label' => 'SMS Number', 'type' => 'tel', 'required' => false
                ];
            }
        }

        $view = $this->createViewModel(
            [
                'fields' => $fields,
                'profile' => $profile,
                'config' => $updateConfig,
            ]
        );
        $view->setTemplate('myresearch/change-address-settings');

        if ($this->formWasSubmitted('address_change_request')) {
            $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

            if (isset($updateConfig['method'])
                && 'driver' === $updateConfig['method']
            ) {
                if (false === $updateConfig) {
                    throw new \Exception(
                        'ILS driver does not support updating profile information'
                    );
                }

                foreach ($fields as $fieldName => $fieldConfig) {
                    if ($fieldConfig['required']
                        && (!isset($data[$fieldName]) || '' === $data[$fieldName])
                    ) {
                        $this->flashMessenger()->addErrorMessage(
                            $this->translate('This field is required') . ': '
                            . $this->translate($fieldConfig['label'])
                        );
                        return $view;
                    }
                    if ('pin4' === $fieldConfig['type'] && !empty($data[$fieldName])
                        && !preg_match('/^[0-9]{4}$/', $data[$fieldName])
                    ) {
                        $this->flashMessenger()->addErrorMessage(
                            $this->translate('password_error_invalid') . ': '
                            . $this->translate($fieldConfig['label'])
                        );
                        return $view;
                    }
                }

                try {
                    $result = $catalog->updateAddress($patron, $data);
                    if ($result['success']) {
                        $view->requestCompleted = true;
                        $this->flashMessenger()
                            ->addSuccessMessage($result['status']);
                    } else {
                        $this->flashMessenger()->addErrorMessage($result['status']);
                    }
                } catch (ILSException $e) {
                    $this->flashMessenger()->addErrorMessage($e->getMessage());
                }
            } else {
                $result = $this->saveChangeRequestFeedback(
                    $patron, $profile, $data, $fields,
                    'finna_UpdatePersonalInformation'
                );
                if ($result) {
                    $this->flashMessenger()
                        ->addSuccessMessage('request_change_done');
                }
                $view->requestCompleted = true;
            }
        }

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
        $config = $catalog->getConfig('updateMessagingSettings', $patron);

        if ($this->formWasSubmitted('messaging_update_request')) {
            if (isset($config['method']) && 'driver' === $config['method']) {
                $data = $profile['messagingServices'];
                $request = $this->getRequest();
                // Collect results from the POST request and update settings
                foreach ($data as $serviceId => &$service) {
                    foreach ($service['settings'] as $settingId => &$setting) {
                        if (!empty($setting['readonly'])) {
                            continue;
                        }
                        if ('boolean' == $setting['type']) {
                            $setting['active'] = (bool)$request->getPost(
                                $serviceId . '_' . $settingId, false
                            );
                        } elseif ('select' == $setting['type']) {
                            $setting['value'] = $request->getPost(
                                $serviceId . '_' . $settingId, ''
                            );
                        } elseif ('multiselect' == $setting['type']) {
                            foreach ($setting['options'] as $optionId
                                => &$option
                            ) {
                                $option['active'] = (bool)$request->getPost(
                                    $serviceId . '_' . $settingId . '_' . $optionId,
                                    false
                                );
                            }
                        }
                    }
                }
                $result = $catalog->updateMessagingSettings($patron, $data);
                if ($result['success']) {
                    $this->flashMessenger()->addSuccessMessage($result['status']);
                    $view->requestCompleted = true;
                } else {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                }
            } else {
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

                $result = $this->saveChangeRequestFeedback(
                    $patron, $profile, $data, [],
                    'finna_UpdateMessagingSettings'
                );
                if ($result) {
                    $this->flashMessenger()
                        ->addSuccessMessage('request_change_done');
                }
                $view->requestCompleted = true;
            }
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
        if (isset($config['method']) && 'driver' === $config['method']) {
            $view->setTemplate('myresearch/change-messaging-settings-driver');
            $view->approvalRequired = !empty($config['approvalRequired']);
        } else {
            $view->setTemplate('myresearch/change-messaging-settings');
        }
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
            'custom_order' => 'sort_custom_order',
            'id desc' => 'sort_saved',
            'id' => 'sort_saved asc',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'year desc' => 'sort_year',
            'year' => 'sort_year asc',
            'format' => 'sort_format'
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

        if ($view = $this->createViewIfUnsupported('getMyHolds')) {
            return $view;
        }

        $view = parent::holdsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Save favorite custom order into DB
     *
     * @return mixed
     */
    public function saveCustomOrderAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        if ($this->formWasSubmitted('opcode')
            && $this->params()->fromPost('opcode') == 'save_order'
        ) {
            $listID = $this->params()->fromPost('list_id');
            $this->session->url = empty($listID)
                ? $this->url()->fromRoute('myresearch-favorites')
                : $this->url()->fromRoute('userList', ['id' => $listID]);

            $orderedList = $this->params()->fromPost('orderedList');
            $table = $this->getTable('UserResource');
            if (empty($listID) || empty($orderedList)
                || !$table->saveCustomFavoriteOrder($user->id, $listID, $orderedList)
            ) {
                $this->flashMessenger()->addErrorMessage('An error has occurred');
            }
            return $this->redirect()->toRoute('userList', ['id' => $listID]);
        } else {
            return $this->redirect()->toRoute('userList', ['id' => $listID]);
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

        if ($view = $this->createViewIfUnsupported('StorageRetrievalRequests', true)
        ) {
            return $view;
        }

        $view = parent::storageRetrievalRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getAccountBlocks($patron);
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

        if ($view = $this->createViewIfUnsupported('ILLRequests', true)) {
            return $view;
        }

        $view = parent::illRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getAccountBlocks($patron);
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

        if ($view = $this->createViewIfUnsupported('getMyFines')) {
            return $view;
        }

        $view = parent::finesAction();
        $view->blocks = $this->getAccountBlocks($patron);
        if (isset($patron['source'])) {
            $this->handleOnlinePayment($patron, $view->fines, $view);
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
        $type = $this->params()->fromQuery('type', 'alert');
        if ('alert' === $type) {
            return parent::unsubscribeAction();
        }

        $id = $this->params()->fromQuery('id', false);
        $key = $this->params()->fromQuery('key', false);

        if ($id === false || $key === false) {
            throw new \Exception('Missing parameters.');
        }

        $view = $this->createViewModel();

        if ($this->params()->fromQuery('confirm', false) == 1) {
            if ($type == 'reminder') {
                $user = $this->getTable('User')->select(['id' => $id])->current();
                if (!$user) {
                    throw new \Exception('Invalid parameters.');
                }
                $dueDateTable = $this->getTable('duedatereminder');
                $secret = $dueDateTable->getUnsubscribeSecret(
                    $this->serviceLocator->get(\VuFind\Crypt\HMAC::class),
                    $user,
                    $user->id
                );
                if ($key !== $secret) {
                    throw new \Exception('Invalid parameters.');
                }
                $user->setFinnaDueDateReminder(0);
                // Remove due date reminder from all cards too
                foreach ($user->getLibraryCards() as $card) {
                    if ($card->finna_due_date_reminder != 0) {
                        $card = $user->getLibraryCard($card->id);
                        $card->finna_due_date_reminder = 0;
                        $card->save();
                    }
                }
                $view->success = true;
            }
        } else {
            $view->unsubscribeUrl
                = $this->getRequest()->getRequestUri() . '&confirm=1';
        }
        return $view;
    }

    /**
     * Creates a JSON file of logged in user's saved searches and lists and sends
     * the file to the browser.
     *
     * @return mixed
     */
    public function exportAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirect()->toRoute(
                'default', ['controller' => 'MyResearch', 'action' => 'Login']
            );
        }

        $exportData = [
            'searches' => $this->exportSavedSearches($user->id),
            'lists' => $this->exportUserLists($user->id)
        ];
        $json = json_encode($exportData);
        $timestamp = strftime('%Y-%m-%d-%H%M');
        $filename = "finna-export-$timestamp.json";
        $response = $this->getResponse();
        $response->setContent($json);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/json')
            ->addHeaderLine(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            )
            ->addHeaderLine('Content-Length', strlen($json));

        return $this->response;
    }

    /**
     * Display dialog for importing favorites.
     *
     * @return mixed
     */
    public function importAction()
    {
    }

    /**
     * Add account blocks to the flash messenger as errors.
     *
     * @param \VuFind\ILS\Connection $catalog Catalog connection
     * @param array                  $patron  Patron details
     *
     * @return void
     */
    public function addAccountBlocksToFlashMessenger($catalog, $patron)
    {
        // We don't use the flash messenger for blocks.
    }

    /**
     * Create sort list.
     * If no sort option selected, set first one from the list to default.
     *
     * @param list $list List object
     *
     * @return array
     */
    protected function createSortList($list)
    {
        $table = $this->getTable('UserResource');

        $sortOptions = self::getFavoritesSortList();
        $sort = $_GET['sort'] ?? false;
        if (!$sort) {
            reset($sortOptions);
            $sort = key($sortOptions);
        }
        $sortList = [];

        if (empty($list) || !$table->isCustomOrderAvailable($list->id)) {
            array_shift($sortOptions);
            if ($sort == 'custom_order') {
                $sort = 'id desc';
            }
        }

        foreach ($sortOptions as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'selected' => $key === $sort
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
     * @return mixed \Laminas\View if the function is not supported, false otherwise
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
     * Append current URL to search memory so that return links on
     * record pages opened from a list point back to the list page.
     *
     * @return void
     */
    protected function rememberCurrentSearchUrl()
    {
        $memory  = $this->serviceLocator->get(\VuFind\Search\Memory::class);
        $listUrl = $this->getRequest()->getRequestUri();
        $memory->rememberSearch($listUrl);
    }

    /**
     * Change phone number, email and checkout history state from library info.
     *
     * @param array  $patron patron data
     * @param object $values form values
     *
     * @return bool
     */
    protected function processLibraryDataUpdate($patron, $values)
    {
        // Connect to the ILS:
        $catalog = $this->getILS();

        $success = true;
        if (isset($values->profile_email)) {
            $validator = new \Laminas\Validator\EmailAddress();
            if ($validator->isValid($values->profile_email)
                && $catalog->checkFunction('updateEmail', compact('patron'))
            ) {
                // Update email
                $result = $catalog->updateEmail($patron, $values->profile_email);
                if (!$result['success']) {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                    $success = false;
                }
            }
        }
        // Update phone
        if (isset($values->profile_tel)
            && $catalog->checkFunction('updatePhone', compact('patron'))
        ) {
            $result = $catalog->updatePhone($patron, $values->profile_tel);
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update SMS Number
        if (isset($values->profile_sms_number)
            && $catalog->checkFunction('updateSmsNumber', compact('patron'))
        ) {
            $result = $catalog->updateSmsNumber(
                $patron, $values->profile_sms_number
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update checkout history state
        $updateState = $catalog
            ->checkFunction('updateTransactionHistoryState', compact('patron'));
        if (isset($values->loan_history) && $updateState) {
            $result = $catalog->updateTransactionHistoryState(
                $patron, $values->loan_history
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Save a feedback to database for library
     *
     * @param array  $patron  Patron
     * @param array  $profile Patron profile
     * @param array  $data    Change data
     * @param array  $fields  Form fields for address change request
     * @param string $subject Email subject
     *
     * @return bool
     */
    protected function saveChangeRequestFeedback($patron, $profile, $data,
        $fields, $subject
    ) {
        list($library, $username) = explode('.', $patron['cat_username']);
        $catalog = $this->getILS();
        $config = $catalog->getConfig('Feedback', $patron);

        if (!isset($config['domain'])) {
            $this->flashMessenger()->addErrorMessage('An error has occurred');
            return false;
        }

        $urlParsed = parse_url($this->getRequest()->getUri());
        $host = $urlParsed['host'];
        $url = $config['domain'];
        $url .= (substr_count($host, '.') > 1) ? strstr($host, '.') : ".$host";

        $name = trim(
            ($patron['firstname'] ?? '')
            . ' '
            . ($patron['lastname'] ?? '')
        );
        $user = $this->getUser();
        $email = $profile['email'] ?? '';
        $userId = $user->id;
        $homeLibrary = $user->home_library ?? '';
        $formId = $subject;

        $userData = [
            'Library' => $library,
            'Username' => $username,
            'Name' => $name,
            'Email' => $email
        ];

        $message = [];
        $oldMessage = [];
        $messageString = '';

        $ignoredTypes = [
            'layout',
            'messaging_update_request'
        ];
        if (!empty($fields)) {
            foreach ($fields as $field => $fieldData) {
                $key = $this->translate($fieldData['label']);
                $value = $data[$field] ?? '';
                $message[$key] = $value;

                if (isset($profile[$field])) {
                    $oldMessage[$key] = $profile[$field];
                }
            }
        } else {
            foreach ($data as $type => $sendMethod) {
                if (!in_array(strtolower($type), $ignoredTypes)) {
                    $key = $this->translate("messaging_settings_type_$type");
                    $message[$key] = $sendMethod;
                }
            }
        }

        $mergedArrays = array_merge($userData, $message);
        $messageJson = json_encode($mergedArrays);

        $messageString = $this->getMessageString($userData, $message, $oldMessage);
        $feedback = $this->getTable('feedback');
        $feedback->saveFeedback(
            $url, $formId, $userId, $messageString, $messageJson
        );

        return true;
    }

    /**
     * Function to get feedback message string from arrays
     *
     * @param array $userData   containing personal information
     * @param array $message    containing data about new values
     * @param array $oldMessage containing data about old values
     *
     * @return string
     */
    protected function getMessageString($userData, $message, $oldMessage = [])
    {
        $messageString = 'User information:' . PHP_EOL
            . '--------------' . PHP_EOL;
        foreach ($userData as $key => $value) {
            $messageString .= $key . ': ' . $value . PHP_EOL;
        }

        $messageString .= PHP_EOL;
        $messageString .= 'New information:' . PHP_EOL
            . '--------------' . PHP_EOL;
        foreach ($message as $key => $value) {
            $messageString .= $key . ': ' . $value . PHP_EOL;
        }
        $messageString .= PHP_EOL;
        if (!empty($oldMessage)) {
            $messageString .= 'Old information:' . PHP_EOL
            . '--------------' . PHP_EOL;
            foreach ($oldMessage as $key => $value) {
                $messageString .= $key . ': ' . $value . PHP_EOL;
            }
        }

        return $messageString;
    }

    /**
     * Exports user's saved searches into an array.
     *
     * @param int $userId User id
     *
     * @return array Saved searches
     */
    protected function exportSavedSearches($userId)
    {
        $savedSearches = $this->getTable('Search')->getSavedSearches($userId);
        $getSearchObject = function ($search) {
            return $search['search_object'];
        };
        return array_map($getSearchObject, $savedSearches->toArray());
    }

    /**
     * Exports user's saved lists into an array.
     *
     * @param int $userId User id
     *
     * @return array Saved user lists
     */
    protected function exportUserLists($userId)
    {
        $user = $this->getTable('User')->getById($userId);
        $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);

        $getTag = function ($tag) {
            return $tag['tag'];
        };

        $setupCallback = function ($searchRunner, $params, $runningSearchId) {
            $params->setLimit(1000);
        };

        $userLists = [];
        foreach ($user->getLists() as $list) {
            $listRecords = $runner->run(
                ['id' => $list->id], 'Favorites', $setupCallback
            );
            $outputList = [
                'title' => $list->title,
                'description' => $list->description,
                'public' => $list->public,
                'records' => []
            ];

            foreach ($listRecords->getResults() as $record) {
                $userResource = $user->getSavedData(
                    $record->getUniqueID(),
                    $list->id,
                    $record->getSourceIdentifier()
                )->current();

                $notes = $record->getListNotes($list->id, $user->id);
                $tags = $record->getTags($list->id, $user->id);
                $outputList['records'][] = [
                    'id' => $record->getUniqueID(),
                    'source' => $record->getSourceIdentifier(),
                    'notes' => !empty($notes) ? $notes[0] : null,
                    'tags' => array_map($getTag, $tags->toArray()),
                    'order' => $userResource
                        ? $userResource->finna_custom_order_index
                        : null
                ];
            }

            $userLists[] = $outputList;
        }

        return $userLists;
    }

    /**
     * Get a record driver object corresponding to an array returned by an ILS
     * driver's getMyHolds / getMyTransactions method.
     *
     * @param array $current Record information
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getDriverForILSRecord($current)
    {
        try {
            return parent::getDriverForILSRecord($current);
        } catch (\Exception $e) {
            $id = $current['id'] ?? null;
            $source = $current['source'] ?? DEFAULT_SEARCH_BACKEND;
            $recordFactory = $this->serviceLocator
                ->get(\VuFind\RecordDriver\PluginManager::class);
            $record = $recordFactory->get('Missing');
            $record->setRawData(['id' => $id]);
            $record->setSourceIdentifier($source);
            $record->setExtraDetail('ils_details', $current);
            return $record;
        }
    }

    /**
     * Get account blocks if supported by the ILS
     *
     * @param array $patron Patron
     *
     * @return array
     */
    protected function getAccountBlocks($patron)
    {
        $catalog = $this->getILS();
        if ($catalog->checkCapability('getAccountBlocks', compact('patron'))
            && $blocks = $catalog->getAccountBlocks($patron)
        ) {
            return $blocks;
        }
        return [];
    }

    /**
     * Check if nickname is available
     *
     * @param string $nickname User nickname
     *
     * @return bool Return username or false if not valid
     */
    protected function checkIfAvailableNickname($nickname): bool
    {
        return ! $this->getTable('User')->nicknameIsTaken($nickname);
    }

    /**
     * Validate user's nickname.
     *
     * @param string $nickname User nickname
     *
     * @return bool Return username or false if not valid
     */
    protected function checkIfValidNickname($nickname): bool
    {
        return preg_match(
            '/^(?!.*[._\-\s]{2})[A-ZÅÄÖa-zåäö0-9._\-\s]{3,50}$/',
            $nickname
        );
    }
}
