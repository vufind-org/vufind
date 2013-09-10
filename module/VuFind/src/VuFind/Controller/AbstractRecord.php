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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFind\Controller;
use VuFind\Exception\Mail as MailException,
    VuFind\RecordDriver\AbstractBase as AbstractRecordDriver,
    Zend\Session\Container as SessionContainer;

/**
 * VuFind Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
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
        if (!is_null($this->driver)) {
            $view->driver = $this->driver;
        }
        return $view;
    }

    /**
     * Add a comment
     *
     * @return mixed
     */
    public function addcommentAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            // Remember comment since POST data will be lost:
            return $this->forceLogin(
                null, array('comment' => $this->params()->fromPost('comment'))
            );
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save comment:
        $comment = $this->params()->fromPost('comment');
        if (empty($comment)) {
            // No comment?  Try to restore from session:
            $session = $this->followup()->retrieve();
            if (isset($session->comment)) {
                $comment = $session->comment;
                unset($session->comment);
            }
        }

        // At this point, we should have a comment to save; if we do not,
        // something has gone wrong (or user submitted blank form) and we
        // should do nothing:
        if (!empty($comment)) {
            $table = $this->getTable('Resource');
            $resource = $table->findResource(
                $driver->getUniqueId(), $driver->getResourceSource(), true, $driver
            );
            $resource->addComment($comment, $user);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('add_comment_success');
        } else {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('add_comment_fail_blank');
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
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $id = $this->params()->fromQuery('delete');
        $table = $this->getTable('Comments');
        if (!is_null($id) && $table->deleteIfOwnedByUser($id, $user)) {
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('delete_comment_success');
        } else {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('delete_comment_failure');
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
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save tags, if any:
        if ($this->params()->fromPost('submit')) {
            $tags = $this->params()->fromPost('tag');
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            $driver->addTags($user, $tagParser->parse($tags));
            return $this->redirectToRecord();
        }

        // Display the "add tag" form:
        $view = $this->createViewModel();
        $view->setTemplate('record/addtag');
        return $view;
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
        $post['mytags'] = $tagParser->parse($post['mytags']);
        $driver->saveToFavorites($post, $user);

        // Display a success status message:
        $this->flashMessenger()->setNamespace('info')
            ->addMessage('bulk_save_success');

        // Grab the followup namespace so we know where to send the user next:
        $followup = new SessionContainer($this->searchClassId . 'SaveFollowup');
        $url = isset($followup->url) ? (string)$followup->url : false;
        if (!empty($url)) {
            // Clear followup URL in session -- we're done with it now:
            unset($followup->url);

            // Redirect!
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
        // Process form submission:
        if ($this->params()->fromPost('submit')) {
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
        // in these cases, we will simply default to pushing the user to record view.
        $followup = new SessionContainer($this->searchClassId . 'SaveFollowup');
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
        if (substr($referer, -5) != '/Save'
            && stripos($referer, 'MyResearch/EditList/NEW') === false
        ) {
            $followup->url = $referer;
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Find out if the item is already part of any lists; save list info/IDs
        $listIds = array();
        $resources = $user->getSavedData(
            $driver->getUniqueId(), null, $driver->getResourceSource()
        );
        foreach ($resources as $userResource) {
            $listIds[] = $userResource->list_id;
        }

        // Loop through all user lists and sort out containing/non-containing lists
        $containingLists = $nonContainingLists = array();
        foreach ($user->getLists() as $list) {
            // Assign list to appropriate array based on whether or not we found
            // it earlier in the list of lists containing the selected record.
            if (in_array($list->id, $listIds)) {
                $containingLists[] = array(
                    'id' => $list->id, 'title' => $list->title
                );
            } else {
                $nonContainingLists[] = array(
                    'id' => $list->id, 'title' => $list->title
                );
            }
        }

        $view = $this->createViewModel(
            array(
                'containingLists' => $containingLists,
                'nonContainingLists' => $nonContainingLists
            )
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

        // Process form submission:
        $view = $this->createEmailViewModel();
        if ($this->params()->fromPost('submit')) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $this->getServiceLocator()->get('VuFind\Mailer')->sendRecord(
                    $view->to, $view->from, $view->message, $driver,
                    $this->getViewRenderer()
                );
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('email_success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
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

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            // Send parameters back to view so form can be re-populated:
            $view->to = $this->params()->fromPost('to');
            $view->provider = $this->params()->fromPost('provider');

            // Attempt to send the email and show an appropriate flash message:
            try {
                $body = $this->getViewRenderer()->partial(
                    'Email/record-sms.phtml',
                    array('driver' => $driver, 'to' => $view->to)
                );
                $sms->text($view->provider, $view->to, null, $body);
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('sms_success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
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
        $this->loadRecord();
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
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('export_invalid_format');
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
            $this->driver = $this->getRecordLoader()->load(
                $this->params()->fromRoute('id', $this->params()->fromQuery('id')),
                $this->searchClassId
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
        return $this->redirect()->toUrl($target . $params);
    }

    /**
     * Get the tab configuration for this controller.
     *
     * @return array
     */
    protected function getTabConfiguration()
    {
        $cfg = $this->getServiceLocator()->get('Config');
        return $cfg['vufind']['recorddriver_tabs'];
    }

    /**
     * Get a default tab by looking up the provided record driver in the tab
     * configuration array.
     *
     * @param AbstractRecordDriver $driver Record driver
     *
     * @return string
     */
    protected function getDefaultTabForRecord(AbstractRecordDriver $driver)
    {
        // Load configuration:
        $config = $this->getTabConfiguration();

        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // setting.
        $className = get_class($driver);
        while (true) {
            if (isset($config[$className]['defaultTab'])) {
                return $config[$className]['defaultTab'];
            }
            $className = get_parent_class($className);
            if (empty($className)) {
                // No setting found...
                return null;
            }
        }
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
            // Load record driver tab configuration:
            $driver = $this->loadRecord();
            $this->defaultTab = $this->getDefaultTabForRecord($driver);

            // Missing/invalid record driver configuration? Fall back to configured
            // default:
            $tabs = $this->getAllTabs();
            if (empty($this->defaultTab) || !isset($tabs[$this->defaultTab])) {
                $this->defaultTab = $this->fallbackDefaultTab;
            }

            // Is configured tab also invalid? If so, pick first existing tab:
            if (empty($this->defaultTab) || !isset($tabs[$this->defaultTab])) {
                $keys = array_keys($tabs);
                $this->defaultTab = isset($keys[0]) ? $keys[0] : '';
            }
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
            $driver = $this->loadRecord();
            $request = $this->getRequest();
            $this->allTabs = $this->getServiceLocator()
                ->get('VuFind\RecordTabPluginManager')
                ->getTabsForRecord($driver, $this->getTabConfiguration(), $request);
        }
        return $this->allTabs;
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

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        $view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');
        return $view;
    }
}