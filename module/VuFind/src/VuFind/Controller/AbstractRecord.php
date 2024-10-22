<?php

/**
 * VuFind Record Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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

use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Ratings\RatingsService;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;
use VuFind\Tags\TagsService;
use VuFindSearch\ParamBag;

use function in_array;
use function intval;
use function is_array;
use function is_object;

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
     * Array of background tabs
     *
     * @var array
     */
    protected $backgroundTabs = null;

    /**
     * Array of extra scripts for tabs
     *
     * @var array
     */
    protected $tabsExtraScripts = null;

    /**
     * Type of record to display
     *
     * @var string
     */
    protected $sourceId = 'Solr';

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
     * @return \Laminas\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $view->driver = $this->loadRecord();
        $this->layout()->searchClassId = $view->searchClassId
            = $view->driver->getSearchBackendIdentifier();
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

        $captchaActive = $this->captcha()->active('userComments');

        // Force login:
        if (!($user = $this->getUser())) {
            // Validate CAPTCHA before redirecting to login:
            if (!$this->formWasSubmitted('comment', $captchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }

            // Remember comment since POST data will be lost:
            return $this->forceLogin(
                null,
                ['comment' => $this->params()->fromPost('comment')]
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
            if (!$this->formWasSubmitted('comment', $captchaActive)) {
                return $this->redirectToRecord('', 'UserComments');
            }
        }

        // At this point, we should have a comment to save; if we do not,
        // something has gone wrong (or user submitted blank form) and we
        // should do nothing:
        if (!empty($comment)) {
            $populator = $this->getService(ResourcePopulator::class);
            $resource = $populator->getOrCreateResourceForDriver($driver);
            $commentsService = $this->getDbService(
                \VuFind\Db\Service\CommentsServiceInterface::class
            );
            $commentsService->addComment($comment, $user, $resource);

            // Save rating if allowed:
            if (
                $driver->isRatingAllowed()
                && '0' !== ($rating = $this->params()->fromPost('rating', '0'))
            ) {
                $ratingsService = $this->getService(RatingsService::class);
                $ratingsService->saveRating($driver, $user->getId(), intval($rating));
            }

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
        $commentsService = $this->getDbService(
            \VuFind\Db\Service\CommentsServiceInterface::class
        );
        if (null !== $id && $commentsService->deleteIfOwnedByUser($id, $user)) {
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
            $this->getService(TagsService::class)->linkTagsToRecord($driver, $user, $tags);
            $this->flashMessenger()->addMessage(['msg' => 'add_tag_success'], 'success');
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

        // Delete tags, if any:
        if ($tag = $this->params()->fromPost('tag')) {
            $this->getService(TagsService::class)->unlinkTagsFromRecord(
                $driver,
                $user,
                [$tag]
            );
            $this->flashMessenger()->addMessage(
                [
                    'msg' => 'tags_deleted',
                    'tokens' => ['%count%' => 1],
                ],
                'success'
            );
        }

        return $this->redirectToRecord();
    }

    /**
     * Display and add ratings
     *
     * @return mixed
     */
    public function ratingAction()
    {
        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Make sure ratings are allowed for the record:
        if (!$driver->isRatingAllowed()) {
            throw new ForbiddenException('rating_disabled');
        }

        // Save rating, if any, and user has logged in:
        $user = $this->getUser();
        if ($user && null !== ($rating = $this->params()->fromPost('rating'))) {
            if (
                '' === $rating
                && !($this->getConfig()->Social->remove_rating ?? true)
            ) {
                throw new BadRequestException('error_inconsistent_parameters');
            }
            $ratingsService = $this->getService(RatingsService::class);
            $ratingsService->saveRating(
                $driver,
                $user->getId(),
                '' === $rating ? null : intval($rating)
            );
            $this->flashMessenger()->addSuccessMessage('rating_add_success');
            if ($this->inLightbox()) {
                return $this->getRefreshResponse();
            }
            return $this->redirectToRecord();
        }

        // Display the "add rating" form:
        $currentRating = $user
            ? $this->getService(RatingsService::class)->getRatingData($driver, $user->getId())
            : null;
        return $this->createViewModel(compact('currentRating'));
    }

    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // If collections are active, we may need to check if the driver is actually
        // a collection; if so, we should redirect to the collection controller.
        $checkRoute = $this->params()->fromPost('checkRoute')
            ?? $this->params()->fromQuery('checkRoute')
            ?? false;
        $config = $this->getConfig();
        if ($checkRoute && $config->Collections->collections ?? false) {
            $routeConfig = isset($config->Collections->route)
                ? $config->Collections->route->toArray() : [];
            $collectionRoutes
                = array_merge(['record' => 'collection'], $routeConfig);
            $routeName = $this->event->getRouteMatch()->getMatchedRouteName() ?? '';
            if ($collectionRoute = ($collectionRoutes[$routeName] ?? null)) {
                $driver = $this->loadRecord();
                if (true === $driver->tryMethod('isCollection')) {
                    $params = $this->params()->fromQuery()
                        + $this->params()->fromRoute();
                    // Disable path normalization since it can unencode e.g. encoded
                    // slashes in record id's
                    $options = [
                        'normalize_path' => false,
                    ];
                    if ($sid = $this->getSearchMemory()->getCurrentSearchId()) {
                        $options['query'] = compact('sid');
                    }
                    $collectionUrl = $this->url()
                        ->fromRoute($collectionRoute, $params, $options);
                    return $this->redirect()->toUrl($collectionUrl);
                }
            }
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
        $this->disableSessionWrites();
        $this->loadRecord();
        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');
        $this->layout()->setVariable('layoutContext', 'tabs');
        return $this->showTab(
            $this->params()->fromPost('tab', $this->getDefaultTab()),
            true
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
        $tagsService = $this->getService(TagsService::class);
        $post['mytags'] = $tagsService->parse($post['mytags'] ?? '');
        $favorites = $this->getService(\VuFind\Favorites\FavoritesService::class);
        $results = $favorites->saveRecordToFavorites($post, $user, $driver);

        // Display a success status message:
        $listUrl = $this->url()->fromRoute('userList', ['id' => $results['listId']]);
        $message = [
            'html' => true,
            'msg' => $this->translate('bulk_save_success') . '. '
                . '<a href="' . $listUrl . '" class="gotolist">'
                . $this->translate('go_to_list') . '</a>.',
        ];
        $this->flashMessenger()->addMessage($message, 'success');

        // redirect to followup url saved in saveAction
        if ($url = $this->getAndClearFollowupUrl()) {
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

        // Check permission:
        $response = $this->permission()->check('feature.Favorites', false);
        if (is_object($response)) {
            return $response;
        }

        if ($this->formWasSubmitted('newList')) {
            // Remove submit now from parameters
            $this->getRequest()->getPost()->set('newList', null)->set('submitButton', null);
            return $this->forwardTo('MyResearch', 'editList', ['id' => 'NEW']);
        }

        // Process form submission:
        if ($this->formWasSubmitted()) {
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
        if (
            !empty($referer)
            && !str_ends_with($referer, '/Save')
            && stripos($referer, 'MyResearch/EditList/NEW') === false
            && $this->isLocalUrl($referer)
        ) {
            $this->setFollowupUrlToReferer();
        } else {
            $this->clearFollowupUrl();
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Find out if the item is already part of any lists; save list info/IDs
        $listIds = [];
        $resources = $this->getDbService(UserResourceServiceInterface::class)->getFavoritesForRecord(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier(),
            null,
            $user
        );
        foreach ($resources as $userResource) {
            if ($currentList = $userResource->getUserList()) {
                $listIds[] = $currentList->getId();
            }
        }

        // Loop through all user lists and sort out containing/non-containing lists
        $containingLists = $nonContainingLists = [];
        foreach ($this->getDbService(UserListServiceInterface::class)->getUserListsByUser($user) as $list) {
            // Assign list to appropriate array based on whether or not we found
            // it earlier in the list of lists containing the selected record.
            if (in_array($list->getId(), $listIds)) {
                $containingLists[] = $list;
            } else {
                $nonContainingLists[] = $list;
            }
        }

        $view = $this->createViewModel(
            [
                'containingLists' => $containingLists,
                'nonContainingLists' => $nonContainingLists,
            ]
        );
        $view->setTemplate('record/save');
        return $view;
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function emailAction()
    {
        $emailActionSettings = $this->getService(\VuFind\Config\AccountCapabilities::class)->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->getUser()
        ) {
            return $this->forceLogin();
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Create view
        $mailer = $this->getService(\VuFind\Mailer\Mailer::class);
        $view = $this->createEmailViewModel(
            null,
            $mailer->getDefaultRecordSubject($driver)
        );
        $mailer->setMaxRecipients($view->maxRecipients);

        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('email');
        // Process form submission:
        if ($this->formWasSubmitted(useCaptcha: $view->useCaptcha)) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendRecord(
                    $view->to,
                    $view->from,
                    $view->message,
                    $driver,
                    $this->getViewRenderer(),
                    $view->subject,
                    $cc
                );
                $this->flashMessenger()->addMessage('email_success', 'success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getDisplayMessage(), 'error');
            }
        }

        // Display the template:
        $view->setTemplate('record/email');
        return $view;
    }

    /**
     * Is SMS enabled?
     *
     * @return bool
     */
    protected function smsEnabled()
    {
        $check = $this->getService(\VuFind\Config\AccountCapabilities::class);
        return $check->getSmsSetting() !== 'disabled';
    }

    /**
     * SMS action - Allows the SMS form to appear.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function smsAction()
    {
        // Make sure comments are enabled:
        if (!$this->smsEnabled()) {
            throw new ForbiddenException('SMS disabled');
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Load the SMS carrier list:
        $sms = $this->getService(\VuFind\SMS\SMSInterface::class);
        $view = $this->createViewModel();
        $view->carriers = $sms->getCarriers();
        $view->validation = $sms->getValidationType();
        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('sms');
        // Send parameters back to view so form can be re-populated:
        $view->to = $this->params()->fromPost('to');
        $view->provider = $this->params()->fromPost('provider');
        // Process form submission:
        if ($this->formWasSubmitted(useCaptcha: $view->useCaptcha)) {
            // Do CSRF check
            $csrf = $this->getService(\VuFind\Validator\SessionCsrf::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }

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
                $this->flashMessenger()->addMessage($e->getDisplayMessage(), 'error');
            }
        }

        // Display the template:
        $view->setTemplate('record/sms');
        return $view;
    }

    /**
     * Show citations for the current record.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function citeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('record/cite');
        return $view;
    }

    /**
     * Show permanent link for the current record.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function permalinkAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('record/permalink');
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
        $export = $this->getService(\VuFind\Export::class);
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
        if (
            $export->needsRedirect($format)
            && !$this->params()->fromQuery('callback')
        ) {
            // Build callback URL:
            $parts = explode('?', $this->getServerUrl(true));
            $callback = $parts[0] . '?callback=1&style=' . urlencode($format);

            return $this->redirect()
                ->toUrl($export->getRedirectUrl($format, $callback));
        }

        $recordHelper = $this->getViewRenderer()->plugin('record');
        try {
            $exportedRecord = $recordHelper($driver)->getExport($format);
        } catch (\VuFind\Exception\FormatUnavailable $e) {
            $this->flashMessenger()->addErrorMessage('export_unsupported_format');
            return $this->redirectToRecord();
        }

        $exportType = $export->getBulkExportType($format);
        if ('post' === $exportType) {
            $params = [
                'exportType' => 'post',
                'postField' => $export->getPostField($format),
                'postData' => $exportedRecord,
                'targetWindow' => $export->getTargetWindow($format),
                'url' => $export->getRedirectUrl($format, ''),
                'format' => $format,
            ];
            $msg = [
                'translate' => false, 'html' => true,
                'msg' => $this->getViewRenderer()->render(
                    'cart/export-success.phtml',
                    $params
                ),
            ];
            $this->flashMessenger()->addSuccessMessage($msg);
            return $this->redirectToRecord();
        }

        // Send appropriate HTTP headers for requested format:
        $response = $this->getResponse();
        $response->getHeaders()->addHeaders($export->getHeaders($format));

        // Actually export the record
        $response->setContent($exportedRecord);
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
     * Show explanation for why a record was found and how its relevancy is computed
     *
     * @return mixed
     */
    public function explainAction()
    {
        $record = $this->loadRecord();

        $view = $this->createViewModel();
        $view->setTemplate('record/explain');
        if (!$record->tryMethod('explainEnabled')) {
            $view->disabled = true;
            return $view;
        }

        $explanation = $this->getService(\VuFind\Search\Explanation\PluginManager::class)
            ->get($record->getSourceIdentifier());

        $params = $explanation->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());
        $explanation->performRequest($record->getUniqueID());

        $view->explanation = $explanation;
        return $view;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @param ParamBag $params Search backend parameters
     * @param bool     $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(ParamBag $params = null, bool $force = false)
    {
        // Only load the record if it has not already been loaded. Note that
        // when determining record ID, we check both the route match (the most
        // common scenario) and the GET parameters (a fallback used by some
        // legacy routes).
        if ($force || !is_object($this->driver)) {
            $recordLoader = $this->getRecordLoader();
            $cacheContext = $this->getRequest()->getQuery()->get('cacheContext');
            if (isset($cacheContext)) {
                $recordLoader->setCacheContext($cacheContext);
            }
            $this->driver = $recordLoader->load(
                $this->params()->fromRoute('id', $this->params()->fromQuery('id')),
                $this->sourceId,
                false,
                $params
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
     * Support method to load tab information from the RecordTab PluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        $driver = $this->loadRecord();
        $request = $this->getRequest();
        $manager = $this->getRecordTabManager();
        $details = $manager
            ->getTabDetailsForRecord($driver, $request, $this->fallbackDefaultTab);
        $this->allTabs = $details['tabs'];
        $this->defaultTab = $details['default'] ? $details['default'] : false;
        $this->backgroundTabs = $manager->getBackgroundTabNames($driver);
        $this->tabsExtraScripts = $manager->getExtraScripts();
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
     * Get extra scripts required by tabs.
     *
     * @param array $tabs Tab names to consider
     *
     * @return array
     */
    protected function getTabsExtraScripts($tabs)
    {
        if (null === $this->tabsExtraScripts) {
            $this->loadTabDetails();
        }
        $allScripts = [];
        foreach (array_keys($tabs) as $tab) {
            if (!empty($this->tabsExtraScripts[$tab])) {
                $allScripts
                    = array_merge($allScripts, $this->tabsExtraScripts[$tab]);
            }
        }
        return array_unique($allScripts);
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
        if (
            $this->params()->fromQuery('login', 'false') == 'true'
            && !$this->getUser()
        ) {
            return $this->forceLogin(null);
        } elseif (
            $this->params()->fromQuery('catalogLogin', 'false') == 'true'
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
        $view->tabsExtraScripts = $this->getTabsExtraScripts($view->tabs);
        $view->loadInitialTabWithAjax
            = isset($config->Site->loadInitialTabWithAjax)
            ? (bool)$config->Site->loadInitialTabWithAjax : false;

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        $view->callnumberHandler = $config->Item_Status->callnumber_handler ?? false;

        $view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');
        return $view;
    }
}
