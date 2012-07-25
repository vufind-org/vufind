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

use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Db\Table\Search as SearchTable,
    VuFind\Exception\Auth as AuthException,
    VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Search\Solr\Results as SolrResults,
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
    protected $account;

    /**
     * Prepare and direct the home page where it needs to go
     *
     * @return string
     */
    public function homeAction()
    {
        // Process login request, if necessary:
        if ($this->params()->fromPost('processLogin')) {
            try {
                $this->getAuthManager()->login($this->getRequest());
            } catch (AuthException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            return $this->forward()
                ->dispatch('MyResearch', array('action' => 'Login'));
        }

        // Logged in?  Forward user to followup action (if set) or default action
        // (if no followup provided):
        $followup = $this->followup()->retrieve();
        if (isset($followup->url)) {
            $url = $followup->url;
            unset($followup->url);
            return $this->redirect()->toUrl($url);
        }

        $config = ConfigReader::getConfig();
        $page = isset($configArray->Site->defaultAccountPage)
            ? $configArray->Site->defaultAccountPage : 'Favorites';
        return $this->forward()->dispatch('MyResearch', array('action' => $page));
    }

    /**
     * "Create account" action
     *
     * @return void
     */
    public function accountAction()
    {
        // If authentication mechanism does not support account creation, send
        // the user away!
        if (!$this->getAuthManager()->supportsCreation()) {
            return $this->forward()
                ->dispatch('MyResearch', array('action' => 'Home'));
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
                return $this->forward()
                    ->dispatch('MyResearch', array('action' => 'Home'));
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
     * @return ViewModel
     */
    public function loginAction()
    {
        // If this authentication method doesn't use a VuFind-generated login
        // form, force it through:
        if ($this->getAuthManager()->getSessionInitiator()) {
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
            if (!$this->params()->getPost('processLogin', false)
                && !$this->params()->getPost('forcingLogin', false)
                && !$this->inLightbox()
            ) {
                $this->getRequest()->getPost()->set('processLogin', true);
                return $this->forward()
                    ->dispatch('MyResearch', array('action' => 'Home'));
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
     * @return string
     */
    public function logoutAction()
    {
        $serverHelper = new \Zend\View\Helper\ServerUrl();
        $url = $serverHelper->__invoke($this->url()->fromRoute('home'));
        return $this->redirect()->toUrl($this->getAuthManager()->logout($url));
    }

    /**
     * Handle 'save/unsave search' request
     *
     * @return void (forward)
     */
    public function savesearchAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Check for the save / delete parameters and process them appropriately:
        $search = new SearchTable();
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
            return $this->forward()
                ->dispatch('Search', array('action' => 'Results'));
        }
    }

    /**
     * Gather user profile data
     *
     * @return void
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
        $catalog = ConnectionManager::connectToCatalog();
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
     * @return void
     */
    public function catalogloginAction()
    {
        // No special action needed -- just display form
        return $this->createViewModel();
    }

    /**
     * Action for sending all of a user's saved favorites to the view
     *
     * @return void (forward)
     */
    public function favoritesAction()
    {
        // Favorites is the same as MyList, but without the list ID parameter.
        return $this->forward()->dispatch('MyResearch', array('action' => 'MyList'));
    }

    /**
     * Delete group of records from favorites.
     *
     * @return void
     */
    public function deleteAction()
    {
        /* TODO:
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Get target URL for after deletion:
        $listID = $this->_request->getParam('listID');
        $newUrl = empty($listID)
            ? '/MyResearch/Favorites' : '/MyResearch/MyList/' . $listID;

        // Fail if we have nothing to delete:
        $ids = is_null($this->_request->getParam('selectAll'))
            ? $this->_request->getParam('ids')
            : $this->_request->getParam('idsAll');
        if (!is_array($ids) || empty($ids)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('bulk_noitems_advice');
            return $this->_redirect($newUrl);
        }

        // Process the deletes if necessary:
        if (!is_null($this->_request->getParam('submit'))) {
            $this->_helper->favorites->delete($ids, $listID, $user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('fav_delete_success');
            return $this->_redirect($newUrl);
        }

        // If we got this far, the operation has not been confirmed yet; show
        // the necessary dialog box:
        $this->view->list = empty($listID)
            ? false : VuFind_Model_Db_UserList::getExisting($listID);
        $this->view->deleteIDS = $ids;
        $this->view->records = VF_Record::loadBatch($ids);
         */
    }

    /**
     * Delete record
     *
     * PARAMS: id = list ID, delete = record ID
     *
     * @return void (forward)
     */
    public function deletefavoriteAction()
    {
        /* TODO:
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Load/check incoming parameters:
        $listID = $this->_request->getParam('id');
        $listID = empty($listID) ? null : $listID;
        $idToDelete = $this->_request->getParam('delete');
        $idSource = $this->_request->getParam('source', 'VuFind');
        if (empty($idToDelete)) {
            throw new \Exception('Cannot delete empty ID!');
        }

        // Perform delete and send appropriate flash message:
        if (!is_null($listID)) {
            // ...Specific List
            $list = VuFind_Model_Db_UserList::getExisting($listID);
            $list->removeResourcesById($user, array($idToDelete), $idSource);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Item removed from list');
        } else {
            // ...My Favorites
            $user->removeResourcesById(array($idToDelete), $idSource);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Item removed from favorites');
        }

        // All done -- show the appropriate action:
        $this->_request->setParam('delete', false);
        return $this->_forward('MyList');
         */
    }

    /**
     * Edit record
     *
     * @return void (forward)
     */
    public function editAction()
    {
        /* TODO:
        // Force login:
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        // Get current record (and, if applicable, selected list ID) for convenience:
        $id = $this->_request->getParam('id');
        $source = $this->_request->getParam('source', 'VuFind');
        $this->view->driver = VF_Record::load($id, $source);
        $listID = $this->_request->getParam('list_id', null);

        // SAVE
        if ($this->_request->getParam('submit')) {
            $lists = $this->_request->getParam('lists');
            foreach ($lists as $list) {
                $this->view->driver->saveToFavorites(
                    array(
                        'list'  => $list,
                        'mytags'  => $this->_request->getParam('tags'.$list),
                        'notes' => $this->_request->getParam('notes'.$list)
                    ),
                    $user
                );
            }
            // add to a new list?
            if ($this->_request->getParam('addToList') > -1) {
                $this->view->driver->saveToFavorites(
                    array('list' => $this->_request->getParam('addToList')),
                    $user
                );
            }
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('edit_list_success');

            $newUrl = is_null($listID)
                ? '/MyResearch/Favorites' : '/MyResearch/MyList/' . $listID;
            return $this->_redirect($newUrl);
        }

        $userResources = $user->getSavedData(
            $id,
            $listID, // if null, returns from My Favorites
            $source
        );

        $this->view->savedData = array();
        foreach ($userResources as $current) {
            $this->view->savedData[] = array(
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
        $this->view->lists = array();
        foreach ($userLists as $userList) {
            if (!in_array($userList->id, $containingLists)) {
                $this->view->lists[$userList->id] = $userList->title;
            }
        }
         */
    }

    /**
     * Process a request to delete a favorite item.
     *
     * @param string $id     ID of record to delete
     * @param string $source Source of record to delete
     *
     * @return object
     */
    protected function processDeleteFavorite($id, $source)
    {
        // If the user already confirmed the operation, perform the delete now:
        if ($this->params()->fromPost('confirm')) {
            return $this->forward()
                ->dispatch('MyResearch', array('action' => 'DeleteFavorite'));
        }

        // If we got this far, we must display a confirmation message...

        // Normally list ID is found in the route match, but in lightbox context it
        // may sometimes be a GET parameter.  We must cover both cases.
        $listID = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (empty($listID)) {
            $url = $this->url()->fromRoute('myresearch-favorites');
        } else {
            $url = $this->url()->fromRoute('userList', array('id' => $listID));
        }
        $this->getRequest()->getQuery()->set('confirmAction', $url);
        $this->getRequest()->getQuery()->set('cancelAction', $url);
        $this->getRequest()->getQuery()->set(
            'extraFields', array('delete' => $id, 'source' => $source)
        );
        $this->getRequest()->getQuery()
            ->set('confirmTitle', 'confirm_delete_brief');
        $this->getRequest()->getQuery()->set('confirmMessage', "confirm_delete");
        return $this->forward()
            ->dispatch('MyResearch', array('action' => 'Confirm'));
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return void (forward)
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
                'source', $this->params()->fromQuery('source')
            );
            return $this->processDeleteFavorite($deleteId, $deleteSource);
        }

        // If we got this far, we just need to display the favorites:
        try {
            $params = new \VuFind\Search\Favorites\Params();
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

            $results = new \VuFind\Search\Favorites\Results($params);
            $results->performAndProcessSearch();
            return $this->createViewModel(array('results' => $results));
        } catch (ListPermissionException $e) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw $e;
        }
    }

    /**
     * Send user's saved favorites from a particular list to the edit view
     *
     * @return void (forward)
     */
    public function editlistAction()
    {
        /* TODO:
        // User must be logged in to edit list:
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $id = $this->_request->getParam('id');
        $list = ($id == 'NEW')
            ? VuFind_Model_Db_UserList::getNew($user)
            : VuFind_Model_Db_UserList::getExisting($id);

        // Send the list to the view:
        $this->view->list = $list;

        // If we're processing a form submission, do it within a try..catch so we can
        // handle errors appropriately:
        if ($this->_request->getParam('submit')) {
            try {
                $finalId = $list->updateFromRequest($user, $this->_request);

                // If the user is in the process of saving a record, send them back
                // to the save screen; otherwise, send them back to the list they
                // just edited.
                $recordId = $this->_request->getParam('recordId');
                $recordController
                    = $this->_request->getParam('recordController', 'Record');
                if (!empty($recordId)) {
                    return $this->_redirect(
                        '/' . $recordController . 
                        '/' . urlencode($recordId) . '/Save'
                    );
                }

                // Similarly, if the user is in the process of bulk-saving records,
                // send them back to the appropriate place in the cart.
                $bulkIds = $this->_request->getParam('ids', array());
                if (!empty($bulkIds)) {
                    $params = array();
                    foreach ($bulkIds as $id) {
                        $params[] = urlencode('ids[]') . '=' . urlencode($id);
                    }
                    return $this->_redirect('/Cart/Save?' . implode('&', $params));
                }

                return $this->_redirect('/MyResearch/MyList/' . $finalId);
            } catch (\Exception $e) {
                switch(get_class($e)) {
                case 'VF_Exception_ListPermission':
                case 'VF_Exception_MissingField':
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage($e->getMessage());
                    break;
                case 'VF_Exception_LoginRequired':
                    return $this->forceLogin();
                default:
                    throw $e;
                }
            }
        }
         */
    }

    /**
     * Takes params from the request and uses them to display a confirmation box
     *
     * @return void
     */
    public function confirmAction()
    {
        return $this->createViewModel(
            array(
                'title' => $this->params()->fromQuery('confirmTitle'),
                'message' => $this->params()->fromQuery('confirmMessage'),
                'confirm' => $this->params()->fromQuery('confirmAction'),
                'cancel' => $this->params()->fromQuery('cancelAction'),
                'extras' => $this->params()->fromQuery('extraFields')
            )
        );
    }

    /**
     * Creates a confirmation box to delete or not delete the current list
     *
     * @return void
     */
    public function deletelistAction()
    {
        /* TODO:
        // Have we confirmed this?
        if ($this->_request->getParam('confirm')) {
            try {
                $list = VuFind_Model_Db_UserList::getExisting(
                    $this->_request->getParam('listID')
                );
                $list->delete($this->getUser());

                // Success Message
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('fav_list_delete');
            } catch(Exception $e) {
                switch(get_class($e)) {
                case 'VF_Exception_LoginRequired':
                case 'VF_Exception_ListPermission':
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
            return $this->_redirect('/MyResearch/Favorites');
        }

        // If we got this far, we must display a confirmation message:
        $router = Zend_Controller_Front::getInstance()->getRouter();
        $this->_request->setParam(
            'confirmAction',
            $router->assemble(
                array(
                    'controller'=>'MyResearch',
                    'action'    =>'DeleteList'
                ), 'default', true
            )
        );
        $this->_request->setParam(
            'cancelAction',
            $router->assemble(
                array('id' => $this->_request->getParam('listID')), 'userList', true
            )
        );
        $this->_request->setParam(
            'extraFields',
            array('listID' => $this->_request->getParam('listID'))
        );
        $this->_request->setParam('confirmTitle', 'confirm_delete_list_brief');
        $this->_request->setParam('confirmMessage', 'confirm_delete_list_text');
        return $this->_forward('Confirm');
         */
    }

    /**
     * Get a record driver object corresponding to an array returned by an ILS
     * driver's getMyHolds / getMyTransactions method.
     *
     * @param array $current Record information
     *
     * @return VF_RecordDriver_Base
     */
    protected function getDriverForILSRecord($current)
    {
        /* TODO:
        try {
            if (!isset($current['id'])) {
                throw new VF_Exception_RecordMissing();
            }
            $record = SolrResults::getRecord($current['id']);
        } catch (VF_Exception_RecordMissing $e) {
            $record = new VF_RecordDriver_Missing(
                array('id' => isset($current['id']) ? $current['id'] : null)
            );
        }
        $record->setExtraDetail('ils_details', $current);
        return $record;
         */
    }

    /**
     * Send list of holds to view
     *
     * @return void
     */
    public function holdsAction()
    {
        /* TODO:
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = ConnectionManager::connectToCatalog();

        // Process cancel requests if necessary:
        $cancelStatus = $catalog->checkFunction('cancelHolds');
        $this->view->cancelResults = $cancelStatus
            ? $this->_helper->holds->cancelHolds(
                $this->_request, $catalog, $patron
            )
            : array();

        // By default, assume we will not need to display a cancel form:
        $this->view->cancelForm = false;

        // Get held item details:
        $result = $catalog->getMyHolds($patron);
        $recordList = array();
        $this->_helper->holds->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->_helper->holds->addCancelDetails(
                $catalog, $current, $cancelStatus
            );
            if ($cancelStatus && $cancelStatus['function'] != "getCancelHoldLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $this->view->cancelForm = true;
            }

            // Build record driver:
            $recordList[] = $this->getDriverForILSRecord($current);
        }

        // Get List of PickUp Libraries based on patron's home library
        $this->view->pickup = $catalog->getPickUpLocations($patron);
        $this->view->recordList = $recordList;
         */
    }

    /**
     * Send list of checked out books to view
     *
     * @return void
     */
    public function checkedoutAction()
    {
        /* TODO:
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = ConnectionManager::connectToCatalog();

        // Get the current renewal status and process renewal form, if necessary:
        $renewStatus = $catalog->checkFunction('Renewals');
        $this->view->renewResult = $renewStatus
            ? $this->_helper->renewals->processRenewals(
                $this->_request, $catalog, $patron
            )
            : array();

        // By default, assume we will not need to display a renewal form:
        $this->view->renewForm = false;

        // Get checked out item details:
        $result = $catalog->getMyTransactions($patron);
        $transactions = array();
        foreach ($result as $current) {
            // Add renewal details if appropriate:
            $current = $this->_helper->renewals->addRenewDetails(
                $catalog, $current, $renewStatus
            );
            if ($renewStatus && !isset($current['renew_link'])
                && $current['renewable']
            ) {
                // Enable renewal form if necessary:
                $this->view->renewForm = true;
            }

            // Build record driver:
            $transactions[] = $this->getDriverForILSRecord($current);
        }

        $this->view->transactions = $transactions;
         */
    }

    /**
     * Send list of fines to view
     *
     * @return void
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = ConnectionManager::connectToCatalog();

        // Get fine details:
        $result = $catalog->getMyFines($patron);
        $fines = array();
        foreach ($result as $row) {
            // Attempt to look up and inject title:
            try {
                if (!isset($row['id']) || empty($row['id'])) {
                    throw new \Exception();
                }
                $record = SolrResults::getRecord($row['id']);
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
}
