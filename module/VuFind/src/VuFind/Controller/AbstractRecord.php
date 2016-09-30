<?php
/**
 * VuFind Record Controller
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;
use VuFind\Exception\Forbidden as ForbiddenException,
    VuFind\Exception\Mail as MailException,
    VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

/**
 * VuFind Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AbstractRecord extends AbstractBase
{
    /**
     * Array of available tab options
     *
     * @var array
     */
    protected $allTabs = null;

    /**
     * Default tab to display (configured at record driver level)
     *
     * @var string
     */
    protected $defaultTab = null;

    /**
     * Default tab to display (fallback used if no record driver configuration)
     *
     * @var string
     */
    protected $fallbackDefaultTab = 'Holdings';

    /**
     * Type of record to display
     *
     * @var string
     */
    protected $searchClassId = 'Solr';

    /**
     * Should we log statistics?
     *
     * @var bool
     */
    protected $logStatistics = true;

    /**
     * Record driver
     *
     * @var AbstractRecordDriver
     */
    protected $driver = null;

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Zend\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $this->layout()->searchClassId = $view->searchClassId = $this->searchClassId;
        $view->driver = $this->loadRecord();
        return $view;
    }

    /**
     * Add a comment
     *
     * @return mixed
     */
    public function addcommentAction()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled');
        }

        $recaptchaActive = $this->recaptcha()->active('userComments');

        // Force login:
        if (!($user = $this->getUser())) {
            // Validate CAPTCHA before redirecting to login:
            if (!$this->formWasSubmitted('comment', $recaptchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }

            // Remember comment since POST data will be lost:
            return $this->forceLogin(
                null, ['comment' => $this->params()->fromPost('comment')]
            );
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save comment:
        $comment = $this->params()->fromPost('comment');
        if (empty($comment)) {
            $comment = $this->followup()->retrieveAndClear('comment');
        } else {
            // Validate CAPTCHA now only if we're not coming back post-login:
            if (!$this->formWasSubmitted('comment', $recaptchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }
        }

        // At this point, we should have a comment to save; if we do not,
        // something has gone wrong (or user submitted blank form) and we
        // should do nothing:
        if (!empty($comment)) {
            $table = $this->getTable('Resource');
            $resource = $table->findResource(
                $driver->getUniqueId(), $driver->getSourceIdentifier(), true, $driver
            );
            $resource->addComment($comment, $user);
            $this->flashMessenger()->addMessage('add_comment_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('add_comment_fail_blank', 'error');
        }

        return $this->redirectToRecord('', 'UserComments');
    }

    /**
     * Delete a comment
     *
     * @return mixed
     */
    public function deletecommentAction()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled');
        }

        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $id = $this->params()->fromQuery('delete');
        $table = $this->getTable('Comments');
        if (!is_null($id) && $table->deleteIfOwnedByUser($id, $user)) {
            $this->flashMessenger()->addMessage('delete_comment_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('delete_comment_failure', 'error');
        }
        return $this->redirectToRecord('', 'UserComments');
    }

    /**
     * Add a tag
     *
     * @return mixed
     */
    public function addtagAction()
    {
        // Make sure tags are enabled:
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled');
        }

        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save tags, if any:
        if ($tags = $this->params()->fromPost('tag')) {
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            $driver->addTags($user, $tagParser->parse($tags));
            $this->flashMessenger()
                ->addMessage(['msg' => 'add_tag_success'], 'success');
            return $this->redirectToRecord();
        }

        // Display the "add tag" form:
        $view = $this->createViewModel();
        $view->setTemplate('record/addtag');
        return $view;
    }

    /**
     * Delete a tag
     *
     * @return mixed
     */
    public function deletetagAction()
    {
        // Make sure tags are enabled:
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled');
        }

        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save tags, if any:
        if ($tag = $this->params()->fromPost('tag')) {
            $driver->deleteTags($user, [$tag]);
            $this->flashMessenger()->addMessage(
                [
                    'msg' => 'tags_deleted',
                    'tokens' => ['%count%' => 1]
                ], 'success'
            );
        }

        return $this->redirectToRecord();
    }

    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Save statistics:
        if ($this->logStatistics) {
            $this->getServiceLocator()->get('VuFind\RecordStats')
                ->log($this->loadRecord(), $this->getRequest());
        }

        return $this->showTab(
            $this->params()->fromRoute('tab', $this->getDefaultTab())
        );
    }

    /**
     * AJAX tab action -- render a tab without surrounding context.
     *
     * @return mixed
     */
    public function ajaxtabAction()
    {
        $this->loadRecord();
        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');
        return $this->showTab(
            $this->params()->fromPost('tab', $this->getDefaultTab()), true
        );
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processSave()
    {
        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Perform the save operation:
        $driver = $this->loadRecord();
        $post = $this->getRequest()->getPost()->toArray();
        $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
        $post['mytags']
            = $tagParser->parse(isset($post['mytags']) ? $post['mytags'] : '');
        $results = $driver->saveToFavorites($post, $user);

        // Display a success status message:
        $listUrl = $this->url()->fromRoute('userList', ['id' => $results['listId']]);
        $message = [
            'html' => true,
            'msg' => $this->translate('bulk_save_success') . '. '
            . '<a href="' . $listUrl . '" class="gotolist">'
            . $this->translate('go_to_list') . '</a>.'
        ];
        $this->flashMessenger()->addMessage($message, 'success');

        // redirect to followup url saved in saveAction
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();
            return $this->redirect()->toUrl($url);
        }

        // No followup info found?  Send back to record view:
        return $this->redirectToRecord();
    }

    /**
     * Save action - Allows the save template to appear,
     *   passes containingLists & nonContainingLists
     *
     * @return mixed
     */
    public function saveAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            return $this->processSave();
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // If we got this far, we should save the referer for later use by the
        // ProcessSave action (to get back to where we came from after saving).
        // We shouldn't save follow-up information if it points to the Save
        // screen or the "create list" screen, as this causes confusing workflows;
        // in these cases, we will simply push the user to record view
        // by unsetting the followup and relying on default behavior in processSave.
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
        if (substr($referer, -5) != '/Save'
            && stripos($referer, 'MyResearch/EditList/NEW') === false
        ) {
            $this->setFollowupUrlToReferer();
        } else {
            $this->clearFollowupUrl();
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Find out if the item is already part of any lists; save list info/IDs
        $listIds = [];
        $resources = $user->getSavedData(
            $driver->getUniqueId(), null, $driver->getSourceIdentifier()
        );
        foreach ($resources as $userResource) {
            $listIds[] = $userResource->list_id;
        }

        // Loop through all user lists and sort out containing/non-containing lists
        $containingLists = $nonContainingLists = [];
        foreach ($user->getLists() as $list) {
            // Assign list to appropriate array based on whether or not we found
            // it earlier in the list of lists containing the selected record.
            if (in_array($list->id, $listIds)) {
                $containingLists[] = [
                    'id' => $list->id, 'title' => $list->title
                ];
            } else {
                $nonContainingLists[] = [
                    'id' => $list->id, 'title' => $list->title
                ];
            }
        }

        $view = $this->createViewModel(
            [
                'containingLists' => $containingLists,
                'nonContainingLists' => $nonContainingLists
            ]
        );
        $view->setTemplate('record/save');
        return $view;
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function emailAction()
    {
        // Force login if necessary:
        $config = $this->getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
            && !$this->getUser()
        ) {
            return $this->forceLogin();
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Create view
        $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
        $view = $this->createEmailViewModel(
            null, $mailer->getDefaultRecordSubject($driver)
        );
        $mailer->setMaxRecipients($view->maxRecipients);

        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('email');
        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendRecord(
                    $view->to, $view->from, $view->message, $driver,
                    $this->getViewRenderer(), $view->subject, $cc
                );
                $this->flashMessenger()->addMessage('email_success', 'success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }

        // Display the template:
        $view->setTemplate('record/email');
        return $view;
    }

    /**
     * SMS action - Allows the SMS form to appear.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function smsAction()
    {
        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Load the SMS carrier list:
        $sms = $this->getServiceLocator()->get('VuFind\SMS');
        $view = $this->createViewModel();
        $view->carriers = $sms->getCarriers();
        $view->validation = $sms->getValidationType();
        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('sms');
        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            // Send parameters back to view so form can be re-populated:
            $view->to = $this->params()->fromPost('to');
            $view->provider = $this->params()->fromPost('provider');

            // Attempt to send the email and show an appropriate flash message:
            try {
                $body = $this->getViewRenderer()->partial(
                    'Email/record-sms.phtml',
                    ['driver' => $driver, 'to' => $view->to]
                );
                $sms->text($view->provider, $view->to, null, $body);
                $this->flashMessenger()->addMessage('sms_success', 'success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }

        // Display the template:
        $view->setTemplate('record/sms');
        return $view;
    }

    /**
     * Show citations for the current record.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function citeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('record/cite');
        return $view;
    }

    /**
     * Export the record
     *
     * @return mixed
     */
    public function exportAction()
    {
        $driver = $this->loadRecord();
        $view = $this->createViewModel();
        $format = $this->params()->fromQuery('style');

        // Display export menu if missing/invalid option
        $export = $this->getServiceLocator()->get('VuFind\Export');
        if (empty($format) || !$export->recordSupportsFormat($driver, $format)) {
            if (!empty($format)) {
                $this->flashMessenger()
                    ->addMessage('export_invalid_format', 'error');
            }
            $view->setTemplate('record/export-menu');
            return $view;
        }

        // If this is an export format that redirects to an external site, perform
        // the redirect now (unless we're being called back from that service!):
        if ($export->needsRedirect($format)
            && !$this->params()->fromQuery('callback')
        ) {
            // Build callback URL:
            $parts = explode('?', $this->getServerUrl(true));
            $callback = $parts[0] . '?callback=1&style=' . urlencode($format);

            return $this->redirect()
                ->toUrl($export->getRedirectUrl($format, $callback));
        }

        // Send appropriate HTTP headers for requested format:
        $response = $this->getResponse();
        $response->getHeaders()->addHeaders($export->getHeaders($format));

        // Actually export the record
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $response->setContent($recordHelper($driver)->getExport($format));
        return $response;
    }

    /**
     * Special action for RDF export
     *
     * @return mixed
     */
    public function rdfAction()
    {
        $this->getRequest()->getQuery()->set('style', 'RDF');
        return $this->exportAction();
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord()
    {
        // Only load the record if it has not already been loaded.  Note that
        // when determining record ID, we check both the route match (the most
        // common scenario) and the GET parameters (a fallback used by some
        // legacy routes).
        if (!is_object($this->driver)) {
            $recordLoader = $this->getRecordLoader();
            $cacheContext = $this->getRequest()->getQuery()->get('cacheContext');
            if (isset($cacheContext)) {
                $recordLoader->setCacheContext($cacheContext);
            }
            $this->driver = $recordLoader->load(
                $this->params()->fromRoute('id', $this->params()->fromQuery('id')),
                $this->searchClassId,
                false
            );
        }
        return $this->driver;
    }

    /**
     * Redirect the user to the main record view.
     *
     * @param string $params Parameters to append to record URL.
     * @param string $tab    Record tab to display (null for default).
     *
     * @return mixed
     */
    protected function redirectToRecord($params = '', $tab = null)
    {
        $details = $this->getRecordRouter()
            ->getTabRouteDetails($this->loadRecord(), $tab);
        $target = $this->url()->fromRoute($details['route'], $details['params']);

        // Special case: don't use anchors in jquerymobile theme, since they
        // mess things up!
        if (strlen($params) && substr($params, 0, 1) == '#') {
            $themeInfo = $this->getServiceLocator()->get('VuFindTheme\ThemeInfo');
            if ($themeInfo->getTheme() == 'jquerymobile') {
                $params = '';
            }
        }

        return $this->redirect()->toUrl($target . $params);
    }

    /**
     * Alias to getRecordTabConfig for backward compatibility.
     *
     * @deprecated use getRecordTabConfig instead
     *
     * @return array
     */
    protected function getTabConfiguration()
    {
        return $this->getRecordTabConfig();
    }

    /**
     * Support method to load tab information from the RecordTabPluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        $driver = $this->loadRecord();
        $request = $this->getRequest();
        $rtpm = $this->getServiceLocator()->get('VuFind\RecordTabPluginManager');
        $details = $rtpm->getTabDetailsForRecord(
            $driver, $this->getRecordTabConfig(), $request,
            $this->fallbackDefaultTab
        );
        $this->allTabs = $details['tabs'];
        $this->defaultTab = $details['default'] ? $details['default'] : false;
        $this->backgroundTabs = $rtpm->getBackgroundTabNames(
            $driver, $this->getRecordTabConfig()
        );
    }

    /**
     * Get default tab for a given driver
     *
     * @return string
     */
    protected function getDefaultTab()
    {
        // Load default tab if not already retrieved:
        if (null === $this->defaultTab) {
            $this->loadTabDetails();
        }
        return $this->defaultTab;
    }

    /**
     * Get all tab information for a given driver.
     *
     * @return array
     */
    protected function getAllTabs()
    {
        if (null === $this->allTabs) {
            $this->loadTabDetails();
        }
        return $this->allTabs;
    }

    /**
     * Get names of tabs to be loaded in the background.
     *
     * @return array
     */
    protected function getBackgroundTabs()
    {
        if (null === $this->backgroundTabs) {
            $this->loadTabDetails();
        }
        return $this->backgroundTabs;
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        // Disabled by default:
        return false;
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Special case -- handle login request (currently needed for holdings
        // tab when driver-based holds mode is enabled, but may also be useful
        // in other circumstances):
        if ($this->params()->fromQuery('login', 'false') == 'true'
            && !$this->getUser()
        ) {
            return $this->forceLogin(null);
        } else if ($this->params()->fromQuery('catalogLogin', 'false') == 'true'
            && !is_array($patron = $this->catalogLogin())
        ) {
            return $patron;
        }

        $config = $this->getConfig();

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());
        $view->backgroundTabs = $this->getBackgroundTabs();
        $view->loadInitialTabWithAjax
            = isset($config->Site->loadInitialTabWithAjax)
            ? (bool) $config->Site->loadInitialTabWithAjax : false;

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        $view->callnumberHandler = isset($config->Item_Status->callnumber_handler)
            ? $config->Item_Status->callnumber_handler
            : false;

        $view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');
        return $view;
    }
}
