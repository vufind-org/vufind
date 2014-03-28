<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

use VuFind\Exception\Auth as AuthException,
    VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Exception\RecordMissing as RecordMissingException,
    Zend\Stdlib\Parameters;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends AbstractBase
{
    /**
     * Process an authentication error.
     *
     * @param AuthException $e Exception to process.
     *
     * @return void
     */
    protected function processAuthenticationException(AuthException $e)
    {
        $msg = $e->getMessage();
        // If a Shibboleth-style login has failed and the user just logged
        // out, we need to override the error message with a more relevant
        // one:
        if ($msg == 'authentication_error_admin'
            && $this->getAuthManager()->userHasLoggedOut()
            && $this->getSessionInitiator()
        ) {
            $msg = 'authentication_error_loggedout';
        }
        $this->flashMessenger()->setNamespace('error')->addMessage($msg);
    }

    /**
     * Store a referer (if appropriate) to keep post-login redirect pointing
     * to an appropriate location.
     *
     * @return void
     */
    protected function storeRefererForPostLoginRedirect()
    {
        // Get the referer -- if it's empty, there's nothing to store!
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
        if (empty($referer)) {
            return;
        }

        // Normalize the referer URL so that inconsistencies in protocol
        // and trailing slashes do not break comparisons; this same normalization
        // is applied to all URLs examined below.
        $refererNorm = trim(end(explode('://', $referer, 2)), '/');

        // If the referer lives outside of VuFind, don't store it! We only
        // want internal post-login redirects.
        $baseUrl = $this->url()->fromRoute('home');
        $baseUrlNorm = trim(end(explode('://', $baseUrl, 2)), '/');
        if (0 !== strpos($refererNorm, $baseUrlNorm)) {
            return;
        }

        // If the referer is the MyResearch/Home action, it probably means
        // that the user is repeatedly mistyping their password. We should
        // ignore this and instead rely on any previously stored referer.
        $myResearchHomeUrl = $this->url()->fromRoute('myresearch-home');
        $mrhuNorm = trim(end(explode('://', $myResearchHomeUrl, 2)), '/');
        if ($mrhuNorm === $refererNorm) {
            return;
        }

        // If we got this far, we want to store the referer:
        $this->followup()->store(array(), $referer);
    }

    /**
     * Prepare and direct the home page where it needs to go
     *
     * @return mixed
     */
    public function homeAction()
    {
        // if the current auth class proxies others, we'll get the proxied
        //   auth method as a querystring or post parameter.
        //   Force to post.
        if ($method = trim($this->params()->fromQuery('auth_method'))) {
            $this->getRequest()->getPost()->set('auth_method', $method);
        }

        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if ($this->params()->fromPost('processLogin')
            || $this->getSessionInitiator()
            || $this->params()->fromPost('auth_method')
        ) {
            try {
                $this->getAuthManager()->login($this->getRequest());
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            $this->storeRefererForPostLoginRedirect();
            return $this->forwardTo('MyResearch', 'Login');
        }

        // Logged in?  Forward user to followup action (if set) or default action
        // (if no followup provided):
        $followup = $this->followup()->retrieve();
        if (isset($followup->url)) {
            $url = $followup->url;
            unset($followup->url);
            return $this->redirect()->toUrl($url);
        }

        $config = $this->getConfig();
        $page = isset($config->Site->defaultAccountPage)
            ? $config->Site->defaultAccountPage : 'Favorites';
        return $this->forwardTo('MyResearch', $page);
    }

    /**
     * "Create account" action
     *
     * @return mixed
     */
    public function accountAction()
    {
        // if the current auth class proxies others, we'll get the proxied
        //   auth method as a querystring parameter.
        $method = trim($this->params()->fromQuery('auth_method'));
        // If authentication mechanism does not support account creation, send
        // the user away!
        if (!$this->getAuthManager()->supportsCreation($method)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // We may have come in from a lightbox.  In this case, a prior module
        // will not have set the followup information.  We should grab the referer
        // so the user doesn't get lost.
        $followup = $this->followup()->retrieve();
        if (!isset($followup->url)) {
            $followup->url = $this->getRequest()->getServer()->get('HTTP_REFERER');
        }

        // Process request, if necessary:
        if (!is_null($this->params()->fromPost('submit', null))) {
            try {
                $this->getAuthManager()->create($this->getRequest());
                return $this->forwardTo('MyResearch', 'Home');
            } catch (AuthException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        // Pass request to view so we can repopulate user parameters in form:
        $view = $this->createViewModel();
        $view->request = $this->getRequest()->getPost();
        return $view;
    }

    /**
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        // If this authentication method doesn't use a VuFind-generated login
        // form, force it through:
        if ($this->getSessionInitiator()) {
            // Don't get stuck in an infinite loop -- if processLogin is already
            // set, it probably means Home action is forwarding back here to
            // report an error!
            //
            // Also don't attempt to process a login that hasn't happened yet;
            // if we've just been forced here from another page, we need the user
            // to click the session initiator link before anything can happen.
            //
            // Finally, we don't want to auto-forward if we're in a lightbox, since
            // it may cause weird behavior -- better to display an error there!
            if (!$this->params()->fromPost('processLogin', false)
                && !$this->params()->fromPost('forcingLogin', false)
                && !$this->inLightbox()
            ) {
                $this->getRequest()->getPost()->set('processLogin', true);
                return $this->forwardTo('MyResearch', 'Home');
            }
        }

        // Make request available to view for form updating:
        $view = $this->createViewModel();
        $view->request = $this->getRequest()->getPost();
        return $view;
    }

    /**
     * Logout Action
     *
     * @return mixed
     */
    public function logoutAction()
    {
        $logoutTarget = $this->getRequest()->getServer()->get('HTTP_REFERER');
        if (empty($logoutTarget)) {
            $logoutTarget = $this->getServerUrl('home');
        }

        // clear querystring parameters
        $logoutTarget = preg_replace('/\?.*/', '', $logoutTarget);

        return $this->redirect()
            ->toUrl($this->getAuthManager()->logout($logoutTarget));
    }

    /**
     * Handle 'save/unsave search' request
     *
     * @return mixed
     */
    public function savesearchAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Check for the save / delete parameters and process them appropriately:
        $search = $this->getTable('Search');
        if (($id = $this->params()->fromQuery('save', false)) !== false) {
            $search->setSavedFlag($id, true, $user->id);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('search_save_success');
        } else if (($id = $this->params()->fromQuery('delete', false)) !== false) {
            $search->setSavedFlag($id, false);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('search_unsave_success');
        } else {
            throw new \Exception('Missing save and delete parameters.');
        }

        // Forward to the appropriate place:
        if ($this->params()->fromQuery('mode') == 'history') {
            return $this->redirect()->toRoute('search-history');
        } else {
            // Forward to the Search/Results action with the "saved" parameter set;
            // this will in turn redirect the user to the appropriate results screen.
            $this->getRequest()->getQuery()->set('saved', $id);
            return $this->forwardTo('Search', 'Results');
        }
    }

    /**
     * Gather user profile data
     *
     * @return mixed
     */
    public function profileAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // User must be logged in at this point, so we can assume this is non-false:
        $user = $this->getUser();

        // Process home library parameter (if present):
        $homeLibrary = $this->params()->fromPost('home_library', false);
        if (!empty($homeLibrary)) {
            $user->changeHomeLibrary($homeLibrary);
            $this->getAuthManager()->updateSession($user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('profile_update');
        }

        // Begin building view object:
        $view = $this->createViewModel();

        // Obtain user information from ILS:
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);
        $profile['home_library'] = $user->home_library;
        $view->profile = $profile;
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
            $view->defaultPickupLocation
                = $catalog->getDefaultPickUpLocation($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }

        return $view;
    }

    /**
     * Catalog Login Action
     *
     * @return mixed
     */
    public function catalogloginAction()
    {
        // No special action needed -- just display form
        return $this->createViewModel();
    }

    /**
     * Action for sending all of a user's saved favorites to the view
     *
     * @return mixed
     */
    public function favoritesAction()
    {
        // Favorites is the same as MyList, but without the list ID parameter.
        return $this->forwardTo('MyResearch', 'MyList');
    }

    /**
     * Delete group of records from favorites.
     *
     * @return mixed
     */
    public function deleteAction()
    {
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Get target URL for after deletion:
        $listID = $this->params()->fromPost('listID');
        $newUrl = empty($listID)
            ? $this->url()->fromRoute('myresearch-favorites')
            : $this->url()->fromRoute('userList', array('id' => $listID));

        // Fail if we have nothing to delete:
        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('bulk_noitems_advice');
            return $this->redirect()->toUrl($newUrl);
        }

        // Process the deletes if necessary:
        if (!is_null($this->params()->fromPost('submit'))) {
            $this->favorites()->delete($ids, $listID, $user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('fav_delete_success');
            return $this->redirect()->toUrl($newUrl);
        }

        // If we got this far, the operation has not been confirmed yet; show
        // the necessary dialog box:
        if (empty($listID)) {
            $list = false;
        } else {
            $table = $this->getTable('UserList');
            $list = $table->getExisting($listID);
        }
        return $this->createViewModel(
            array(
                'list' => $list, 'deleteIDS' => $ids,
                'records' => $this->getRecordLoader()->loadBatch($ids)
            )
        );
    }

    /**
     * Delete record
     *
     * @param string $id     ID of record to delete
     * @param string $source Source of record to delete
     *
     * @return mixed         True on success; otherwise returns a value that can
     * be returned by the controller to forward to another action (i.e. force login)
     */
    public function performDeleteFavorite($id, $source)
    {
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Load/check incoming parameters:
        $listID = $this->params()->fromRoute('id');
        $listID = empty($listID) ? null : $listID;
        if (empty($id)) {
            throw new \Exception('Cannot delete empty ID!');
        }

        // Perform delete and send appropriate flash message:
        if (!is_null($listID)) {
            // ...Specific List
            $table = $this->getTable('UserList');
            $list = $table->getExisting($listID);
            $list->removeResourcesById($user, array($id), $source);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Item removed from list');
        } else {
            // ...My Favorites
            $user->removeResourcesById(array($id), $source);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Item removed from favorites');
        }

        // All done -- return true to indicate success.
        return true;
    }

    /**
     * Process the submission of the edit favorite form.
     *
     * @param \VuFind\Db\Row\User               $user   Logged-in user
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver for favorite
     * @param int                               $listID List being edited (null
     * if editing all favorites)
     *
     * @return object
     */
    protected function processEditSubmit($user, $driver, $listID)
    {
        $lists = $this->params()->fromPost('lists');
        $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
        foreach ($lists as $list) {
            $tags = $this->params()->fromPost('tags'.$list);
            $driver->saveToFavorites(
                array(
                    'list'  => $list,
                    'mytags'  => $tagParser->parse($tags),
                    'notes' => $this->params()->fromPost('notes'.$list)
                ),
                $user
            );
        }
        // add to a new list?
        $addToList = $this->params()->fromPost('addToList');
        if ($addToList > -1) {
            $driver->saveToFavorites(array('list' => $addToList), $user);
        }
        $this->flashMessenger()->setNamespace('info')
            ->addMessage('edit_list_success');

        $newUrl = is_null($listID)
            ? $this->url()->fromRoute('myresearch-favorites')
            : $this->url()->fromRoute('userList', array('id' => $listID));
        return $this->redirect()->toUrl($newUrl);
    }

    /**
     * Edit record
     *
     * @return mixed
     */
    public function editAction()
    {
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Get current record (and, if applicable, selected list ID) for convenience:
        $id = $this->params()->fromPost('id', $this->params()->fromQuery('id'));
        $source = $this->params()->fromPost(
            'source', $this->params()->fromQuery('source', 'VuFind')
        );
        $driver = $this->getRecordLoader()->load($id, $source, true);
        $listID = $this->params()->fromPost(
            'list_id', $this->params()->fromQuery('list_id', null)
        );

        // Process save action if necessary:
        if ($this->params()->fromPost('submit')) {
            return $this->processEditSubmit($user, $driver, $listID);
        }

        // Get saved favorites for selected list (or all lists if $listID is null)
        $userResources = $user->getSavedData($id, $listID, $source);
        $savedData = array();
        foreach ($userResources as $current) {
            $savedData[] = array(
                'listId' => $current->list_id,
                'listTitle' => $current->list_title,
                'notes' => $current->notes,
                'tags' => $user->getTagString($id, $current->list_id, $source)
            );
        }

        // In order to determine which lists contain the requested item, we may
        // need to do an extra database lookup if the previous lookup was limited
        // to a particular list ID:
        $containingLists = array();
        if (!empty($listID)) {
            $userResources = $user->getSavedData($id, null, $source);
        }
        foreach ($userResources as $current) {
            $containingLists[] = $current->list_id;
        }

        // Send non-containing lists to the view for user selection:
        $userLists = $user->getLists();
        $lists = array();
        foreach ($userLists as $userList) {
            if (!in_array($userList->id, $containingLists)) {
                $lists[$userList->id] = $userList->title;
            }
        }

        return $this->createViewModel(
            array(
                'driver' => $driver, 'lists' => $lists, 'savedData' => $savedData
            )
        );
    }

    /**
     * Confirm a request to delete a favorite item.
     *
     * @param string $id     ID of record to delete
     * @param string $source Source of record to delete
     *
     * @return mixed
     */
    protected function confirmDeleteFavorite($id, $source)
    {
        // Normally list ID is found in the route match, but in lightbox context it
        // may sometimes be a GET parameter.  We must cover both cases.
        $listID = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (empty($listID)) {
            $url = $this->url()->fromRoute('myresearch-favorites');
        } else {
            $url = $this->url()->fromRoute('userList', array('id' => $listID));
        }
        return $this->confirm(
            'confirm_delete_brief', $url, $url, 'confirm_delete',
            array('delete' => $id, 'source' => $source)
        );
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        // Check for "delete item" request; parameter may be in GET or POST depending
        // on calling context.
        $deleteId = $this->params()->fromPost(
            'delete', $this->params()->fromQuery('delete')
        );
        if ($deleteId) {
            $deleteSource = $this->params()->fromPost(
                'source', $this->params()->fromQuery('source', 'VuFind')
            );
            // If the user already confirmed the operation, perform the delete now;
            // otherwise prompt for confirmation:
            $confirm = $this->params()->fromPost(
                'confirm', $this->params()->fromQuery('confirm')
            );
            if ($confirm) {
                $success = $this->performDeleteFavorite($deleteId, $deleteSource);
                if ($success !== true) {
                    return $success;
                }
            } else {
                return $this->confirmDeleteFavorite($deleteId, $deleteSource);
            }
        }

        // If we got this far, we just need to display the favorites:
        try {
            $results = $this->getServiceLocator()
                ->get('VuFind\SearchResultsPluginManager')->get('Favorites');
            $params = $results->getParams();
            $params->setAuthManager($this->getAuthManager());

            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $params->initFromRequest(
                new Parameters(
                    $this->getRequest()->getQuery()->toArray()
                    + $this->getRequest()->getPost()->toArray()
                    + array('id' => $this->params()->fromRoute('id'))
                )
            );

            $results->performAndProcessSearch();
            return $this->createViewModel(
                array('params' => $params, 'results' => $results)
            );
        } catch (ListPermissionException $e) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw $e;
        }
    }

    /**
     * Process the "edit list" submission.
     *
     * @param \VuFind\Db\Row\User     $user Logged in user
     * @param \VuFind\Db\Row\UserList $list List being created/edited
     *
     * @return object|bool                  Response object if redirect is
     * needed, false if form needs to be redisplayed.
     */
    protected function processEditList($user, $list)
    {
        // Process form within a try..catch so we can handle errors appropriately:
        try {
            $finalId
                = $list->updateFromRequest($user, $this->getRequest()->getPost());

            // If the user is in the process of saving a record, send them back
            // to the save screen; otherwise, send them back to the list they
            // just edited.
            $recordId = $this->params()->fromQuery('recordId');
            $recordSource = $this->params()->fromQuery('recordSource', 'VuFind');
            if (!empty($recordId)) {
                $details = $this->getRecordRouter()->getActionRouteDetails(
                    $recordSource . '|' . $recordId, 'Save'
                );
                return $this
                    ->lightboxAwareRedirect($details['route'], $details['params']);
            }

            // Similarly, if the user is in the process of bulk-saving records,
            // send them back to the appropriate place in the cart.
            $bulkIds = $this->params()->fromPost(
                'ids', $this->params()->fromQuery('ids', array())
            );
            if (!empty($bulkIds)) {
                $params = array();
                foreach ($bulkIds as $id) {
                    $params[] = urlencode('ids[]') . '=' . urlencode($id);
                }
                $saveUrl = $this->getLightboxAwareUrl('cart-save');
                $saveUrl .= (strpos($saveUrl, '?') === false) ? '?' : '&';
                return $this->redirect()
                    ->toUrl($saveUrl . implode('&', $params));
            }

            return $this->lightboxAwareRedirect('userList', array('id' => $finalId));
        } catch (\Exception $e) {
            switch(get_class($e)) {
            case 'VuFind\Exception\ListPermission':
            case 'VuFind\Exception\MissingField':
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
                return false;
            case 'VuFind\Exception\LoginRequired':
                return $this->forceLogin();
            default:
                throw $e;
            }
        }
    }

    /**
     * Send user's saved favorites from a particular list to the edit view
     *
     * @return mixed
     */
    public function editlistAction()
    {
        // User must be logged in to edit list:
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $table = $this->getTable('UserList');
        $newList = ($id == 'NEW');
        $list = $newList ? $table->getNew($user) : $table->getExisting($id);

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            if ($redirect = $this->processEditList($user, $list)) {
                return $redirect;
            }
        }

        // Send the list to the view:
        return $this->createViewModel(array('list' => $list, 'newList' => $newList));
    }

    /**
     * Creates a confirmation box to delete or not delete the current list
     *
     * @return mixed
     */
    public function deletelistAction()
    {
        // Get requested list ID:
        $listID = $this->params()
            ->fromPost('listID', $this->params()->fromQuery('listID'));

        // Have we confirmed this?
        $confirm = $this->params()->fromPost(
            'confirm', $this->params()->fromQuery('confirm')
        );
        if ($confirm) {
            try {
                $table = $this->getTable('UserList');
                $list = $table->getExisting($listID);
                $list->delete($this->getUser());

                // Success Message
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('fav_list_delete');
            } catch (\Exception $e) {
                switch(get_class($e)) {
                case 'VuFind\Exception\LoginRequired':
                case 'VuFind\Exception\ListPermission':
                    $user = $this->getUser();
                    if ($user == false) {
                        return $this->forceLogin();
                    }
                    // Logged in? Fall through to default case!
                default:
                    throw $e;
                }
            }
            // Redirect to MyResearch home
            return $this->redirect()->toRoute('myresearch-favorites');
        }

        // If we got this far, we must display a confirmation message:
        return $this->confirm(
            'confirm_delete_list_brief',
            $this->url()->fromRoute('myresearch-deletelist'),
            $this->url()->fromRoute('userList', array('id' => $listID)),
            'confirm_delete_list_text', array('listID' => $listID)
        );
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
        $id = isset($current['id']) ? $current['id'] : null;
        $record = $this->getServiceLocator()->get('VuFind\RecordLoader')
            ->load($id, 'VuFind', true);
        $record->setExtraDetail('ils_details', $current);
        return $record;
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

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelHolds');
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus
            ? $this->holds()->cancelHolds($catalog, $patron) : array();
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get held item details:
        $result = $catalog->getMyHolds($patron);
        $recordList = array();
        $this->holds()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->holds()->addCancelDetails(
                $catalog, $current, $cancelStatus
            );
            if ($cancelStatus && $cancelStatus['function'] != "getCancelHoldLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            // Build record driver:
            $recordList[] = $this->getDriverForILSRecord($current);
        }

        // Get List of PickUp Libraries based on patron's home library
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }
        $view->recordList = $recordList;
        return $view;
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

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelSRR = $catalog->checkFunction('cancelStorageRetrievalRequests');
        $view = $this->createViewModel();
        $view->cancelResults = $cancelSRR
            ? $this->storageRetrievalRequests()->cancelStorageRetrievalRequests(
                $catalog, $patron
            )
            : array();
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get request details:
        $result = $catalog->getMyStorageRetrievalRequests($patron);
        $recordList = array();
        $this->storageRetrievalRequests()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->storageRetrievalRequests()->addCancelDetails(
                $catalog, $current, $cancelSRR, $patron
            );
            if ($cancelSRR
                && $cancelSRR['function'] != "getCancelStorageRetrievalRequestLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            // Build record driver:
            $recordList[] = $this->getDriverForILSRecord($current);
        }

        // Get List of PickUp Libraries based on patron's home library
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }
        $view->recordList = $recordList;
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

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelILLRequests');
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus
            ? $this->ILLRequests()->cancelILLRequests(
                $catalog, $patron
            )
            : array();
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get request details:
        $result = $catalog->getMyILLRequests($patron);
        $recordList = array();
        $this->ILLRequests()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->ILLRequests()->addCancelDetails(
                $catalog, $current, $cancelStatus, $patron
            );
            if ($cancelStatus 
                && $cancelStatus['function'] != "getCancelILLRequestLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            // Build record driver:
            $recordList[] = $this->getDriverForILSRecord($current);
        }

        $view->recordList = $recordList;
        return $view;
    }

    /**
     * Send list of checked out books to view
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Get the current renewal status and process renewal form, if necessary:
        $renewStatus = $catalog->checkFunction('Renewals');
        $renewResult = $renewStatus
            ? $this->renewals()->processRenewals(
                $this->getRequest()->getPost(), $catalog, $patron
            )
            : array();

        // By default, assume we will not need to display a renewal form:
        $renewForm = false;

        // Get checked out item details:
        $result = $catalog->getMyTransactions($patron);
        $transactions = array();
        foreach ($result as $current) {
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

            // Build record driver:
            $transactions[] = $this->getDriverForILSRecord($current);
        }

        return $this->createViewModel(
            array(
                'transactions' => $transactions, 'renewForm' => $renewForm,
                'renewResult' => $renewResult
            )
        );
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

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Get fine details:
        $result = $catalog->getMyFines($patron);
        $fines = array();
        foreach ($result as $row) {
            // Attempt to look up and inject title:
            try {
                if (!isset($row['id']) || empty($row['id'])) {
                    throw new \Exception();
                }
                $record = $this->getServiceLocator()->get('VuFind\RecordLoader')
                    ->load($row['id']);
                $row['title'] = $record->getShortTitle();
            } catch (\Exception $e) {
                if (!isset($row['title'])) {
                    $row['title'] = null;
                }
            }
            $fines[] = $row;
        }

        return $this->createViewModel(array('fines' => $fines));
    }

    /**
     * Convenience method to get a session initiator URL. Returns false if not
     * applicable.
     *
     * @return string|bool
     */
    protected function getSessionInitiator()
    {
        $url = $this->getServerUrl('myresearch-home');
        return $this->getAuthManager()->getSessionInitiator($url);
    }
}
