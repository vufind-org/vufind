<?php

/**
 * MyResearch Controller
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\View\Model\ViewModel;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\AuthEmailNotVerified as AuthEmailNotVerifiedException;
use VuFind\Exception\AuthInProgress as AuthInProgressException;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\Mail as MailException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\ILS\PaginationHelper;
use VuFind\Mailer\Mailer;
use VuFind\Search\RecommendListener;
use VuFind\Validator\CsrfInterface;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MyResearchController extends AbstractBase
{
    use Feature\CatchIlsExceptionsTrait;
    use \VuFind\ILS\Logic\SummaryTrait;

    /**
     * Permission that must be granted to access this module (false for no
     * restriction, null to use configured default (which is usually the same
     * as false)).
     *
     * For this controller, we default to false rather than null because
     * we don't want a default setting to override the controller's accessibility
     * and break the login process!
     *
     * @var string|bool
     */
    protected $accessPermission = false;

    /**
     * ILS Pagination Helper
     *
     * @var PaginationHelper
     */
    protected $paginationHelper = null;

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
        if ($e instanceof AuthInProgressException) {
            $this->flashMessenger()->addSuccessMessage($msg);
            return;
        }
        if ($e instanceof AuthEmailNotVerifiedException) {
            $this->sendFirstVerificationEmail($e->user);
            if ($msg == 'authentication_error_email_not_verified_html') {
                $this->getUserVerificationContainer()->user = $e->user->username;
                $url = $this->url()->fromRoute('myresearch-emailnotverified')
                    . '?reverify=true';
                $msg = [
                    'html' => true,
                    'msg' => $msg,
                    'tokens' => ['%%url%%' => $url],
                ];
            }
        }
        // If a Shibboleth-style login has failed and the user just logged
        // out, we need to override the error message with a more relevant
        // one:
        if (
            $msg == 'authentication_error_admin'
            && $this->getAuthManager()->userHasLoggedOut()
            && $this->getSessionInitiator()
        ) {
            $msg = 'authentication_error_loggedout';
        }
        $this->flashMessenger()->addMessage($msg, 'error');
    }

    /**
     * Maintaining this method for backwards compatibility;
     * logic moved to parent and method re-named
     *
     * @return void
     */
    protected function storeRefererForPostLoginRedirect()
    {
        $this->setFollowupUrlToReferer();
    }

    /**
     * Prepare and direct the home page where it needs to go
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if (
            $this->params()->fromPost('processLogin')
            || $this->getSessionInitiator()
            || $this->params()->fromPost('auth_method')
            || $this->params()->fromQuery('auth_method')
        ) {
            try {
                if (!$this->getAuthManager()->isLoggedIn()) {
                    $this->getAuthManager()->login($this->getRequest());
                    // Return early to avoid unnecessary processing if we are being
                    // called from login lightbox and don't have a followup action.
                    if (
                        $this->params()->fromPost('processLogin')
                        && $this->inLightbox()
                        && empty($this->getFollowupUrl())
                    ) {
                        return $this->getRefreshResponse();
                    }
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            // Allow bypassing of post-login redirect
            if ($this->params()->fromQuery('redirect', true)) {
                $this->setFollowupUrlToReferer();
            }
            return $this->forwardTo('MyResearch', 'Login');
        }
        // Logged in?  Forward user to followup action
        // or default action (if no followup provided):
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();
            // If a user clicks on the "Your Account" link, we want to be sure
            // they get to their account rather than being redirected to an old
            // followup URL. We'll use a redirect=0 GET flag to indicate this:
            if ($this->params()->fromQuery('redirect', true)) {
                return $this->redirect()->toUrl($url);
            }
        }

        $config = $this->getConfig();
        $page = $config->Site->defaultAccountPage ?? 'Favorites';

        // Default to search history if favorites are disabled:
        if ($page == 'Favorites' && !$this->listsEnabled()) {
            return $this->forwardTo('Search', 'History');
        }
        return $this->forwardTo('MyResearch', $page);
    }

    /**
     * "Create account" action
     *
     * @return mixed
     */
    public function accountAction()
    {
        // If the user is already logged in, don't let them create an account:
        if ($this->getAuthManager()->isLoggedIn()) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        // If authentication mechanism does not support account creation, send
        // the user away!
        $method = trim($this->params()->fromQuery('auth_method'));
        if (!$this->getAuthManager()->supportsCreation($method)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // If there's already a followup url, keep it; otherwise set one.
        if (!$this->getFollowupUrl()) {
            $this->setFollowupUrlToReferer();
        }

        // Make view
        $view = $this->createViewModel();
        // Username policy
        $view->usernamePolicy = $this->getAuthManager()->getUsernamePolicy($method);
        // Password policy
        $view->passwordPolicy = $this->getAuthManager()->getPasswordPolicy($method);
        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('newAccount');
        // Pass request to view so we can repopulate user parameters in form:
        $view->request = $this->getRequest()->getPost();
        // Process request, if necessary:
        if ($this->formWasSubmitted('submit', $view->useCaptcha)) {
            try {
                $this->getAuthManager()->create($this->getRequest());
                return $this->forwardTo('MyResearch', 'Home');
            } catch (AuthEmailNotVerifiedException $e) {
                $this->sendFirstVerificationEmail($e->user);
                return $this->redirect()->toRoute('myresearch-emailnotverified');
            } catch (AuthException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        } else {
            // If we are not processing a submission, we need to simply display
            // an empty form. In case ChoiceAuth is being used, we may need to
            // override the active authentication method based on request
            // parameters to ensure display of the appropriate template.
            $this->setUpAuthenticationFromRequest();
        }
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
            if (
                !$this->params()->fromPost('processLogin', false)
                && !$this->params()->fromPost('forcingLogin', false)
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
     * User login action -- clear any previous follow-up information prior to
     * triggering a login process. This is used for explicit login links within
     * the UI to differentiate them from contextual login links that are triggered
     * by attempting to access protected actions.
     *
     * @return mixed
     */
    public function userloginAction()
    {
        // Don't log in if already logged in!
        if ($this->getAuthManager()->isLoggedIn()) {
            return $this->inLightbox()  // different behavior for lightbox context
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('home');
        }
        $this->clearFollowupUrl();
        // Set followup only if we're not in lightbox since it has the short-circuit
        // for reloading current page:
        if (!$this->inLightbox()) {
            $this->setFollowupUrlToReferer();
        }
        if ($si = $this->getSessionInitiator()) {
            return $this->redirect()->toUrl($si);
        }
        return $this->forwardTo('MyResearch', 'Login');
    }

    /**
     * Logout Action
     *
     * @return mixed
     */
    public function logoutAction()
    {
        $config = $this->getConfig();
        if (!empty($config->Site->logOutRoute)) {
            $logoutTarget = $this->getServerUrl($config->Site->logOutRoute);
        } else {
            $logoutTarget = $this->getRequest()->getServer()->get('HTTP_REFERER');
            if (empty($logoutTarget)) {
                $logoutTarget = $this->getServerUrl('home');
            }

            // If there is an auth_method parameter in the query, we should strip
            // it out. Otherwise, the user may get stuck in an infinite loop of
            // logging out and getting logged back in when using environment-based
            // authentication methods like Shibboleth.
            $logoutTarget = preg_replace(
                '/([?&])auth_method=[^&]*&?/',
                '$1',
                $logoutTarget
            );
            $logoutTarget = rtrim($logoutTarget, '?');

            // Another special case: if logging out will send the user back to
            // the MyResearch home action, instead send them all the way to
            // VuFind home. Otherwise, they might get logged back in again,
            // which is confusing. Even in the best scenario, they'll just end
            // up on a login screen, which is not helpful.
            if ($logoutTarget == $this->getServerUrl('myresearch-home')) {
                $logoutTarget = $this->getServerUrl('home');
            }
        }

        return $this->redirect()
            ->toUrl($this->getAuthManager()->logout($logoutTarget));
    }

    /**
     * Get a search row, but throw an exception if it is not owned by the specified
     * user or current active session.
     *
     * @param int $searchId ID of search row
     * @param int $userId   ID of active user
     *
     * @throws ForbiddenException
     * @return \VuFind\Db\Row\Search
     */
    protected function getSearchRowSecurely($searchId, $userId)
    {
        $searchTable = $this->getTable('Search');
        $sessId = $this->serviceLocator
            ->get(\Laminas\Session\SessionManager::class)->getId();
        $search = $searchTable->getOwnedRowById($searchId, $sessId, $userId);
        if (empty($search)) {
            throw new ForbiddenException('Access denied.');
        }
        return $search;
    }

    /**
     * Support method for savesearchAction(): set the saved flag in a secure
     * fashion, throwing an exception if somebody attempts something invalid.
     *
     * @param int  $searchId The search ID to save/unsave
     * @param bool $saved    The new desired state of the saved flag
     * @param int  $userId   The user ID requesting the change
     *
     * @throws \Exception
     * @return void
     */
    protected function setSavedFlagSecurely($searchId, $saved, $userId)
    {
        $row = $this->getSearchRowSecurely($searchId, $userId);
        $row->saved = $saved ? 1 : 0;
        if (!$saved) {
            $row->notification_frequency = 0;
        }
        $row->user_id = $userId;
        $row->save();
    }

    /**
     * Return a session container for use in user email verification.
     *
     * @return \Laminas\Session\Container
     */
    protected function getUserVerificationContainer()
    {
        return new \Laminas\Session\Container(
            'user_verification',
            $this->serviceLocator->get(\Laminas\Session\SessionManager::class)
        );
    }

    /**
     * Support method for savesearchAction() -- schedule a search.
     *
     * @param \VuFind\Db\Row\User $user     Logged-in user object
     * @param int                 $schedule Requested schedule setting
     * @param int                 $sid      Search ID to schedule
     *
     * @return mixed
     */
    protected function scheduleSearch($user, $schedule, $sid)
    {
        // Fail if scheduled searches are disabled.
        $scheduleOptions = $this->serviceLocator
            ->get(\VuFind\Search\History::class)
            ->getScheduleOptions();
        if (!isset($scheduleOptions[$schedule])) {
            throw new ForbiddenException('Illegal schedule option: ' . $schedule);
        }
        $search = $this->getTable('Search');
        $baseurl = rtrim($this->getServerUrl('home'), '/');
        $savedRow = $this->getSearchRowSecurely($sid, $user->id);

        // In case the user has just logged in, let's deduplicate...
        $sessId = $this->serviceLocator
            ->get(\Laminas\Session\SessionManager::class)->getId();
        $duplicateId = $this->isDuplicateOfSavedSearch(
            $search,
            $savedRow,
            $sessId,
            $user->id
        );
        if ($duplicateId) {
            $savedRow->delete();
            $sid = $duplicateId;
            $savedRow = $this->getSearchRowSecurely($sid, $user->id);
        }

        // If we didn't find an already-saved row, let's save and retry:
        if (!($savedRow->saved ?? false)) {
            $this->setSavedFlagSecurely($sid, true, $user->id);
            $savedRow = $this->getSearchRowSecurely($sid, $user->id);
        }
        if (!($this->getConfig()->Account->force_first_scheduled_email ?? false)) {
            // By default, a first scheduled email will be sent because the database
            // last notification date will be initialized to a past date. If we don't
            // want that to happen, we need to set it to a more appropriate date:
            $savedRow->last_notification_sent = date('Y-m-d H:i:s');
        }
        $savedRow->setSchedule($schedule, $baseurl);
        return $this->redirect()->toRoute('search-history');
    }

    /**
     * Handle search subscription request
     *
     * @return mixed
     */
    public function schedulesearchAction()
    {
        // Fail if saved searches or subscriptions are disabled.
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        if ($check->getSavedSearchSetting() === 'disabled') {
            throw new ForbiddenException('Saved searches disabled.');
        }
        $scheduleOptions = $this->serviceLocator
            ->get(\VuFind\Search\History::class)
            ->getScheduleOptions();
        if (empty($scheduleOptions)) {
            throw new ForbiddenException('Scheduled searches disabled.');
        }
        // Fail if search ID is missing.
        $searchId = $this->params()->fromQuery('searchid', false);
        if (!$searchId) {
            throw new BadRequestException('searchid missing');
        }
        // Require login.
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        // Get the row, and fail if the current user doesn't own it.
        $search = $this->getSearchRowSecurely($searchId, $user->id);

        // If the user has just logged in, the search might be a duplicate; if
        // so, let's switch to the pre-existing version instead.
        $searchTable = $this->getTable('search');
        $sessId = $this->serviceLocator
            ->get(\Laminas\Session\SessionManager::class)->getId();
        $duplicateId = $this->isDuplicateOfSavedSearch(
            $searchTable,
            $search,
            $sessId,
            $user->id
        );
        if ($duplicateId) {
            $search->delete();
            $this->redirect()->toRoute(
                'myresearch-schedulesearch',
                [],
                ['query' => ['searchid' => $duplicateId]]
            );
        }

        // Now fetch all the results:
        $resultsManager = $this->serviceLocator
            ->get(\VuFind\Search\Results\PluginManager::class);
        $results = $search->getSearchObject()->deminify($resultsManager);

        // Build the form.
        return $this->createViewModel(
            compact('scheduleOptions', 'search', 'results')
        );
    }

    /**
     * Is the provided search row a duplicate of a search that is already saved?
     *
     * @param \VuFind\Db\Table\Search $searchTable Search table
     * @param ?\VuFind\Db\Row\Search  $rowToCheck  Search row to check (if any)
     * @param string                  $sessId      Current session ID
     * @param int                     $userId      Current user ID
     *
     * @return ?int
     */
    protected function isDuplicateOfSavedSearch(
        \VuFind\Db\Table\Search $searchTable,
        ?\VuFind\Db\Row\Search $rowToCheck,
        string $sessId,
        int $userId
    ): ?int {
        if (!$rowToCheck) {
            return null;
        }
        $normalizer = $this->serviceLocator
            ->get(\VuFind\Search\SearchNormalizer::class);
        $normalized = $normalizer
            ->normalizeMinifiedSearch($rowToCheck->getSearchObject());
        $matches = $searchTable->getSearchRowsMatchingNormalizedSearch(
            $normalized,
            $sessId,
            $userId
        );
        foreach ($matches as $current) {
            // $current->saved may be 1 (MySQL) or true (PostgreSQL), so we should
            // avoid a strict === comparison here:
            if ($current->saved == 1 && $current->id !== $rowToCheck->id) {
                return $current->id;
            }
        }
        return null;
    }

    /**
     * Handle 'save/unsave search' request
     *
     * @return mixed
     */
    public function savesearchAction()
    {
        // Fail if saved searches are disabled.
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        if ($check->getSavedSearchSetting() === 'disabled') {
            throw new ForbiddenException('Saved searches disabled.');
        }

        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Check for schedule-related parameters and process them first:
        $schedule = $this->params()->fromQuery('schedule', false);
        $sid = $this->params()->fromQuery('searchid', false);
        if ($schedule !== false && $sid !== false) {
            return $this->scheduleSearch($user, $schedule, $sid);
        }

        // Check for the save / delete parameters and process them appropriately:
        if (($id = $this->params()->fromQuery('save', false)) !== false) {
            // If the row the user is trying to save is a duplicate of an already-
            // saved row, we should just delete the duplicate. (This can happen if
            // the user clicks "save" before logging in, then logs in during the
            // save process, but has the same search already saved in their account).
            $searchTable = $this->getTable('search');
            $sessId = $this->serviceLocator
                ->get(\Laminas\Session\SessionManager::class)->getId();
            $rowToCheck = $searchTable->getOwnedRowById($id, $sessId, $user->id);
            $duplicateId = $this->isDuplicateOfSavedSearch(
                $searchTable,
                $rowToCheck,
                $sessId,
                $user->id
            );
            if ($duplicateId) {
                $rowToCheck->delete();
                $id = $duplicateId;
            } else {
                $this->setSavedFlagSecurely($id, true, $user->id);
            }
            $this->flashMessenger()->addMessage('search_save_success', 'success');
        } elseif (($id = $this->params()->fromQuery('delete', false)) !== false) {
            $this->setSavedFlagSecurely($id, false, $user->id);
            $this->flashMessenger()->addMessage('search_unsave_success', 'success');
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
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Begin building view object:
        $view = $this->createViewModel(['user' => $user]);

        $config = $this->getConfig();
        $allowHomeLibrary = $config->Account->set_home_library ?? true;

        $patron = $this->catalogLogin();
        if (is_array($patron)) {
            // Process home library parameter (if present and allowed):
            $homeLibrary = $this->params()->fromPost('home_library');
            if ($allowHomeLibrary && null !== $homeLibrary) {
                // Note: for backward compatibility user's home library defaults to
                // empty string indicating system default. We also allow null for
                // "Always ask me", and the choice is encoded as ' ** ' on the form:
                if (' ** ' === $homeLibrary) {
                    $homeLibrary = null;
                }
                $user->changeHomeLibrary($homeLibrary);
                $this->getAuthManager()->updateSession($user);
                $this->flashMessenger()->addMessage('profile_update', 'success');
            }

            // Obtain user information from ILS:
            $catalog = $this->getILS();
            $this->addAccountBlocksToFlashMessenger($catalog, $patron);
            $profile = $catalog->getMyProfile($patron);
            $profile['home_library'] = $allowHomeLibrary
                ? $user->home_library
                : ($profile['home_library'] ?? '');
            $view->profile = $profile;
            $pickup = $defaultPickupLocation = null;
            try {
                $pickup = $catalog->getPickUpLocations($patron);
                $defaultPickupLocation = $catalog->getDefaultPickUpLocation($patron);
            } catch (\Exception $e) {
                // Do nothing; if we're unable to load information about pickup
                // locations, they are not supported and we should ignore them.
            }

            // Set things up differently depending on whether or not the user is
            // allowed to set a home library.
            if ($allowHomeLibrary) {
                $view->pickup = $pickup;
                $view->defaultPickupLocation = $defaultPickupLocation;
            } elseif (!empty($pickup)) {
                foreach ($pickup as $lib) {
                    if ($defaultPickupLocation == $lib['locationID']) {
                        $view->preferredLibraryDisplay = $lib['locationDisplay'];
                        break;
                    }
                }
            }
        } else {
            $view->patronLoginView = $patron;
        }

        $view->accountDeletion
            = !empty($config->Authentication->account_deletion);

        $this->addPendingEmailChangeMessage($user);

        return $view;
    }

    /**
     * Add account blocks to the flash messenger as errors.
     * These messages are lightbox ignored.
     *
     * @param \VuFind\ILS\Connection $catalog Catalog connection
     * @param array                  $patron  Patron details
     *
     * @return void
     */
    public function addAccountBlocksToFlashMessenger($catalog, $patron)
    {
        if (
            $catalog->checkCapability('getAccountBlocks', compact('patron'))
            && $blocks = $catalog->getAccountBlocks($patron)
        ) {
            foreach ($blocks as $block) {
                $this->flashMessenger()->addMessage(
                    [ 'msg' => $block, 'dataset' => [ 'lightbox-ignore' => '1' ] ],
                    'error'
                );
            }
        }
    }

    /**
     * Catalog Login Action
     *
     * @return mixed
     */
    public function catalogloginAction()
    {
        $loginSettings = $this->getILSLoginSettings();
        return $this->createViewModel($loginSettings);
    }

    /**
     * Action for sending all of a user's saved favorites to the view
     *
     * @return mixed
     */
    public function favoritesAction()
    {
        // Check permission:
        $response = $this->permission()->check('feature.Favorites', false);
        if (is_object($response)) {
            return $response;
        }

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
            : $this->url()->fromRoute('userList', ['id' => $listID]);

        // Fail if we have nothing to delete:
        $ids = null === $this->params()->fromPost('selectAll')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            $this->flashMessenger()->addMessage('bulk_noitems_advice', 'error');
            return $this->redirect()->toUrl($newUrl);
        }

        // Process the deletes if necessary:
        if ($this->formWasSubmitted('submit')) {
            $this->favorites()->delete($ids, $listID, $user);
            $this->flashMessenger()->addMessage('fav_delete_success', 'success');
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
            [
                'list' => $list, 'deleteIDS' => $ids,
                'records' => $this->getRecordLoader()->loadBatch($ids),
            ]
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
        if (null !== $listID) {
            // ...Specific List
            $table = $this->getTable('UserList');
            $list = $table->getExisting($listID);
            $list->removeResourcesById($user, [$id], $source);
            $this->flashMessenger()->addMessage('Item removed from list', 'success');
        } else {
            // ...My Favorites
            $user->removeResourcesById([$id], $source);
            $this->flashMessenger()
                ->addMessage('Item removed from favorites', 'success');
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
        $lists = $this->params()->fromPost('lists', []);
        $tagParser = $this->serviceLocator->get(\VuFind\Tags::class);
        $favorites = $this->serviceLocator
            ->get(\VuFind\Favorites\FavoritesService::class);
        $didSomething = false;
        foreach ($lists as $list) {
            $tags = $this->params()->fromPost('tags' . $list);
            $favorites->save(
                [
                    'list'  => $list,
                    'mytags'  => $tagParser->parse($tags),
                    'notes' => $this->params()->fromPost('notes' . $list),
                ],
                $user,
                $driver
            );
            $didSomething = true;
        }
        // add to a new list?
        $addToList = $this->params()->fromPost('addToList');
        if ($addToList > -1) {
            $didSomething = true;
            $favorites->save(['list' => $addToList], $user, $driver);
        }
        if ($didSomething) {
            $this->flashMessenger()->addMessage('edit_list_success', 'success');
        }

        $newUrl = null === $listID
            ? $this->url()->fromRoute('myresearch-favorites')
            : $this->url()->fromRoute('userList', ['id' => $listID]);
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
            'source',
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND)
        );
        $driver = $this->getRecordLoader()->load($id, $source, true);
        $listID = $this->params()->fromPost(
            'list_id',
            $this->params()->fromQuery('list_id', null)
        );

        // Process save action if necessary:
        if ($this->formWasSubmitted('submit')) {
            return $this->processEditSubmit($user, $driver, $listID);
        }

        // Get saved favorites for selected list (or all lists if $listID is null)
        $userResources = $user->getSavedData($id, $listID, $source);
        $savedData = [];
        foreach ($userResources as $current) {
            $savedData[] = [
                'listId' => $current->list_id,
                'listTitle' => $current->list_title,
                'notes' => $current->notes,
                'tags' => $user->getTagString($id, $current->list_id, $source),
            ];
        }

        // In order to determine which lists contain the requested item, we may
        // need to do an extra database lookup if the previous lookup was limited
        // to a particular list ID:
        $containingLists = [];
        if (!empty($listID)) {
            $userResources = $user->getSavedData($id, null, $source);
        }
        foreach ($userResources as $current) {
            $containingLists[] = $current->list_id;
        }

        // Send non-containing lists to the view for user selection:
        $userLists = $user->getLists();
        $lists = [];
        foreach ($userLists as $userList) {
            if (!in_array($userList->id, $containingLists)) {
                $lists[$userList->id] = $userList->title;
            }
        }

        return $this->createViewModel(
            compact('driver', 'lists', 'savedData', 'listID')
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
            $url = $this->url()->fromRoute('userList', ['id' => $listID]);
        }
        return $this->confirm(
            'confirm_delete_brief',
            $url,
            $url,
            'confirm_delete',
            ['delete' => $id, 'source' => $source]
        );
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Check for "delete item" request; parameter may be in GET or POST depending
        // on calling context.
        $deleteId = $this->params()->fromPost(
            'delete',
            $this->params()->fromQuery('delete')
        );
        if ($deleteId) {
            $deleteSource = $this->params()->fromPost(
                'source',
                $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND)
            );
            // If the user already confirmed the operation, perform the delete now;
            // otherwise prompt for confirmation:
            $confirm = $this->params()->fromPost(
                'confirm',
                $this->params()->fromQuery('confirm')
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
            $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);

            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $request = $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray()
                + ['id' => $this->params()->fromRoute('id')];

            // Set up listener for recommendations:
            $rManager = $this->serviceLocator
                ->get(\VuFind\Recommend\PluginManager::class);
            $setupCallback = function ($runner, $params, $searchId) use ($rManager) {
                $listener = new RecommendListener($rManager, $searchId);
                $listener->setConfig(
                    $params->getOptions()->getRecommendationSettings()
                );
                $listener->attach($runner->getEventManager()->getSharedManager());
            };

            $results = $runner->run($request, 'Favorites', $setupCallback);
            $listTags = [];

            if ($this->listTagsEnabled()) {
                if ($list = $results->getListObject()) {
                    foreach ($list->getListTags() as $tag) {
                        $listTags[$tag->id] = $tag->tag;
                    }
                }
            }
            return $this->createViewModel(
                [
                    'params' => $results->getParams(), 'results' => $results,
                    'listTags' => $listTags,
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
            $recordSource
                = $this->params()->fromQuery('recordSource', DEFAULT_SEARCH_BACKEND);
            if (!empty($recordId)) {
                $details = $this->getRecordRouter()->getActionRouteDetails(
                    $recordSource . '|' . $recordId,
                    'Save'
                );
                return $this->redirect()->toRoute(
                    $details['route'],
                    $details['params']
                );
            }

            // Similarly, if the user is in the process of bulk-saving records,
            // send them back to the appropriate place in the cart.
            $bulkIds = $this->params()->fromPost(
                'ids',
                $this->params()->fromQuery('ids', [])
            );
            if (!empty($bulkIds)) {
                $params = [];
                foreach ($bulkIds as $id) {
                    $params[] = urlencode('ids[]') . '=' . urlencode($id);
                }
                $saveUrl = $this->url()->fromRoute('cart-save');
                $saveUrl .= (strpos($saveUrl, '?') === false) ? '?' : '&';
                return $this->redirect()
                    ->toUrl($saveUrl . implode('&', $params));
            }

            return $this->redirect()->toRoute('userList', ['id' => $finalId]);
        } catch (ListPermissionException | MissingFieldException $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            return false;
        } catch (LoginRequiredException $e) {
            return $this->forceLogin();
        }
    }

    /**
     * Send user's saved favorites from a particular list to the edit view
     *
     * @return mixed
     */
    public function editlistAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

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

        // Make sure the user isn't fishing for other people's lists:
        if (!$newList && !$list->editAllowed($user)) {
            throw new ListPermissionException('Access denied.');
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            if ($redirect = $this->processEditList($user, $list)) {
                return $redirect;
            }
        }

        $listTags = null;
        if ($this->listTagsEnabled() && !$newList) {
            $listTags = $user->formatTagString($list->getListTags());
        }
        // Send the list to the view:
        return $this->createViewModel(
            [
                'list' => $list,
                'newList' => $newList,
                'listTags' => $listTags,
            ]
        );
    }

    /**
     * Creates a message that the verification email has been sent to the user's
     * mail address.
     *
     * @return mixed
     */
    public function emailNotVerifiedAction()
    {
        if ($this->params()->fromQuery('reverify')) {
            $change = false;
            $table = $this->getTable('User');
            // Case 1: new user:
            $user = $table
                ->getByUsername($this->getUserVerificationContainer()->user, false);
            // Case 2: pending email change:
            if (!$user) {
                $user = $this->getUser();
                if (!empty($user->pending_email)) {
                    $change = true;
                }
            }
            $this->sendVerificationEmail($user, $change);
        } else {
            $this->flashMessenger()->addMessage('verification_email_sent', 'info');
        }
        return $this->createViewModel();
    }

    /**
     * Creates a confirmation box to delete or not delete the current list
     *
     * @return mixed
     */
    public function deletelistAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Get requested list ID:
        $listID = $this->params()
            ->fromPost('listID', $this->params()->fromQuery('listID'));

        // Have we confirmed this?
        $confirm = $this->params()->fromPost(
            'confirm',
            $this->params()->fromQuery('confirm')
        );
        if ($confirm) {
            try {
                $table = $this->getTable('UserList');
                $list = $table->getExisting($listID);
                $list->delete($this->getUser());

                // Success Message
                $this->flashMessenger()->addMessage('fav_list_delete', 'success');
            } catch (LoginRequiredException | ListPermissionException $e) {
                $user = $this->getUser();
                if ($user == false) {
                    return $this->forceLogin();
                }
                // Logged in? Then we have to rethrow the exception!
                throw $e;
            }
            // Redirect to MyResearch home
            return $this->redirect()->toRoute('myresearch-favorites');
        }

        // If we got this far, we must display a confirmation message:
        return $this->confirm(
            'confirm_delete_list_brief',
            $this->url()->fromRoute('myresearch-deletelist'),
            $this->url()->fromRoute('userList', ['id' => $listID]),
            'confirm_delete_list_text',
            ['listID' => $listID]
        );
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     *
     * @deprecated
     */
    public function holdsAction()
    {
        return $this->redirect()->toRoute('holds-list');
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
        $cancelSRR = $catalog->checkFunction(
            'cancelStorageRetrievalRequests',
            compact('patron')
        );
        $view = $this->createViewModel();
        $view->cancelResults = $cancelSRR
            ? $this->storageRetrievalRequests()->cancelStorageRetrievalRequests(
                $catalog,
                $patron
            )
            : [];
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get request details:
        $result = $catalog->getMyStorageRetrievalRequests($patron);
        $driversNeeded = [];
        $this->storageRetrievalRequests()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->storageRetrievalRequests()->addCancelDetails(
                $catalog,
                $current,
                $cancelSRR,
                $patron
            );
            if (
                $cancelSRR
                && $cancelSRR['function'] != "getCancelStorageRetrievalRequestLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            $driversNeeded[] = $current;
        }

        // Get List of PickUp Libraries based on patron's home library
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }

        $view->recordList = $this->ilsRecords()->getDrivers($driversNeeded);
        $view->accountStatus = $this->ilsRecords()
            ->collectRequestStats($view->recordList);
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
        $cancelStatus = $catalog->checkFunction(
            'cancelILLRequests',
            compact('patron')
        );
        $view = $this->createViewModel();
        $view->cancelResults = $cancelStatus
            ? $this->ILLRequests()->cancelILLRequests(
                $catalog,
                $patron
            )
            : [];
        // If we need to confirm
        if (!is_array($view->cancelResults)) {
            return $view->cancelResults;
        }

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        // Get request details:
        $result = $catalog->getMyILLRequests($patron);
        $driversNeeded = [];
        $this->ILLRequests()->resetValidation();
        foreach ($result as $current) {
            // Add cancel details if appropriate:
            $current = $this->ILLRequests()->addCancelDetails(
                $catalog,
                $current,
                $cancelStatus,
                $patron
            );
            if (
                $cancelStatus
                && $cancelStatus['function'] != "getCancelILLRequestLink"
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $view->cancelForm = true;
            }

            $driversNeeded[] = $current;
        }

        $view->recordList = $this->ilsRecords()->getDrivers($driversNeeded);
        $view->accountStatus = $this->ilsRecords()
            ->collectRequestStats($view->recordList);
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

        // Display account blocks, if any:
        $this->addAccountBlocksToFlashMessenger($catalog, $patron);

        // Get the current renewal status and process renewal form, if necessary:
        $renewStatus = $catalog->checkFunction('Renewals', compact('patron'));
        $renewResult = $renewStatus
            ? $this->renewals()->processRenewals(
                $this->getRequest()->getPost(),
                $catalog,
                $patron,
                $this->serviceLocator->get(CsrfInterface::class)
            )
            : [];

        // By default, assume we will not need to display a renewal form:
        $renewForm = false;

        // Get paging setup:
        $config = $this->getConfig();
        $pageSize = $config->Catalog->checked_out_page_size ?? 50;
        $pageOptions = $this->getPaginationHelper()->getOptions(
            (int)$this->params()->fromQuery('page', 1),
            $this->params()->fromQuery('sort'),
            $pageSize,
            $catalog->checkFunction('getMyTransactions', $patron)
        );

        // Get checked out item details:
        $result = $catalog->getMyTransactions($patron, $pageOptions['ilsParams']);

        // Build paginator if needed:
        $paginator = $this->getPaginationHelper()->getPaginator(
            $pageOptions,
            $result['count'],
            $result['records']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        // If the results are not paged in the ILS, collect up to date stats for ajax
        // account notifications:
        if (
            !empty($config->Authentication->enableAjax)
            && (!$pageOptions['ilsPaging'] || !$paginator
            || $result['count'] <= $pageSize)
        ) {
            $accountStatus = $this->getTransactionSummary($result['records']);
        } else {
            $accountStatus = null;
        }

        $driversNeeded = $hiddenTransactions = [];
        foreach ($result['records'] as $i => $current) {
            // Add renewal details if appropriate:
            $current = $this->renewals()->addRenewDetails(
                $catalog,
                $current,
                $renewStatus
            );
            if (
                $renewStatus && !isset($current['renew_link'])
                && $current['renewable']
            ) {
                // Enable renewal form if necessary:
                $renewForm = true;
            }

            // Build record drivers (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $driversNeeded[] = $current;
            } else {
                $hiddenTransactions[] = $current;
            }
        }

        $transactions = $this->ilsRecords()->getDrivers($driversNeeded);

        $displayItemBarcode
            = !empty($config->Catalog->display_checked_out_item_barcode);

        $ilsPaging = $pageOptions['ilsPaging'];
        $sortList = $pageOptions['sortList'];
        $params = $pageOptions['ilsParams'];
        return $this->createViewModel(
            compact(
                'transactions',
                'renewForm',
                'renewResult',
                'paginator',
                'ilsPaging',
                'hiddenTransactions',
                'displayItemBarcode',
                'sortList',
                'params',
                'accountStatus'
            )
        );
    }

    /**
     * Send list of historic loans to view
     *
     * @return mixed
     */
    public function historicloansAction()
    {
        return $this->redirect()->toRoute('checkouts-listhistory');
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
        $fines = [];
        $driversNeeded = [];
        foreach ($result as $i => $row) {
            // If we have an id, add it to list of record drivers to load:
            if ($row['id'] ?? false) {
                $driversNeeded[$i] = [
                    'id' => $row['id'],
                    'source' => $row['source'] ?? DEFAULT_SEARCH_BACKEND,
                ];
            }
            // Store by original index so that we can access it when loading record
            // drivers:
            $fines[$i] = $row;
        }

        if ($driversNeeded) {
            $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
            $drivers = $recordLoader->loadBatch($driversNeeded, true);
            foreach ($drivers as $i => $driver) {
                $fines[$i]['driver'] = $driver;
                if (empty($fines[$i]['title'])) {
                    $fines[$i]['title'] = $driver->getShortTitle();
                }
            }
        }

        // Clean up array keys:
        $fines = array_values($fines);

        // Collect up to date stats for ajax account notifications:
        if (!empty($this->getConfig()->Authentication->enableAjax)) {
            $accountStatus = $this->getFineSummary(
                $fines,
                $this->serviceLocator->get(\VuFind\Service\CurrencyFormatter::class)
            );
        } else {
            $accountStatus = null;
        }

        return $this->createViewModel(compact('fines', 'accountStatus'));
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

    /**
     * Send account recovery email
     *
     * @return mixed
     */
    public function recoverAction()
    {
        // Make sure we're configured to do this
        $this->setUpAuthenticationFromRequest();
        if (!$this->getAuthManager()->supportsRecovery()) {
            $this->flashMessenger()->addMessage('recovery_disabled', 'error');
            return $this->redirect()->toRoute('myresearch-home');
        }
        if ($this->getUser()) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        // Database
        $table = $this->getTable('User');
        $user = false;
        // Check if we have a submitted form, and use the information
        // to get the user's information
        if ($email = $this->params()->fromPost('email')) {
            $user = $table->getByEmail($email);
        } elseif ($username = $this->params()->fromPost('username')) {
            $user = $table->getByUsername($username, false);
        }
        $view = $this->createViewModel();
        $view->useCaptcha = $this->captcha()->active('passwordRecovery');
        // If we have a submitted form
        if ($this->formWasSubmitted('submit', $view->useCaptcha)) {
            if ($user) {
                $this->sendRecoveryEmail($user, $this->getConfig());
            } else {
                $this->flashMessenger()
                    ->addMessage('recovery_user_not_found', 'error');
            }
        }
        return $view;
    }

    /**
     * Helper function for recoverAction
     *
     * @param \VuFind\Db\Row\User $user   User object we're recovering
     * @param \VuFind\Config      $config Configuration object
     *
     * @return void (sends email or adds error message)
     */
    protected function sendRecoveryEmail($user, $config)
    {
        // If we can't find a user
        if (null == $user) {
            $this->flashMessenger()->addMessage('recovery_user_not_found', 'error');
        } else {
            // Make sure we've waited long enough
            $hashtime = $this->getHashAge($user->verify_hash);
            $recoveryInterval = $config->Authentication->recover_interval ?? 60;
            if (time() - $hashtime < $recoveryInterval) {
                $this->flashMessenger()->addMessage('recovery_too_soon', 'error');
            } else {
                // Attempt to send the email
                try {
                    // Create a fresh hash
                    $user->updateHash();
                    $config = $this->getConfig();
                    $renderer = $this->getViewRenderer();
                    $method = $this->getAuthManager()->getAuthMethod();
                    // Custom template for emails (text-only)
                    $message = $renderer->render(
                        'Email/recover-password.phtml',
                        [
                            'library' => $config->Site->title,
                            'url' => $this->getServerUrl('myresearch-verify')
                                . '?hash='
                                . $user->verify_hash . '&auth_method=' . $method,
                        ]
                    );
                    $this->serviceLocator->get(Mailer::class)->send(
                        $user->email,
                        $config->Site->email,
                        $this->translate('recovery_email_subject'),
                        $message
                    );
                    $this->flashMessenger()
                        ->addMessage('recovery_email_sent', 'success');
                } catch (MailException $e) {
                    $this->flashMessenger()->addMessage($e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Send a verify email message for the first time (only if the user does not
     * already have a hash).
     *
     * @param \VuFind\Db\Row\User $user User object we're recovering
     *
     * @return void (sends email or adds error message)
     */
    protected function sendFirstVerificationEmail($user)
    {
        if (empty($user->verify_hash)) {
            $this->sendVerificationEmail($user);
        }
    }

    /**
     * When a request to change a user's email address has been received, we should
     * send a notification to the old email address for the user's information.
     *
     * @param \VuFind\Db\Row\User $user     User whose email address is being changed
     * @param string              $newEmail New email address
     *
     * @return void (sends email or adds error message)
     */
    protected function sendChangeNotificationEmail($user, $newEmail)
    {
        // Don't send the notification if the existing email is not valid:
        $validator = new \Laminas\Validator\EmailAddress();
        if (!$validator->isValid($user->email)) {
            return;
        }

        $config = $this->getConfig();
        $renderer = $this->getViewRenderer();
        // Custom template for emails (text-only)
        $message = $renderer->render(
            'Email/notify-email-change.phtml',
            [
                'library' => $config->Site->title,
                'url' => $this->getServerUrl('home'),
                'email' => $config->Site->email,
                'newEmail' => $newEmail,
            ]
        );
        // If the user is setting up a new account, use the main email
        // address; if they have a pending address change, use that.
        $this->serviceLocator->get(Mailer::class)->send(
            $user->email,
            $config->Site->email,
            $this->translate('change_notification_email_subject'),
            $message
        );
    }

    /**
     * Send a verify email message.
     *
     * @param \VuFind\Db\Row\User $user   User object we're recovering
     * @param bool                $change Is the user changing their email (true)
     * or setting up a new account (false).
     *
     * @return void (sends email or adds error message)
     */
    protected function sendVerificationEmail($user, $change = false)
    {
        // If we can't find a user
        if (null == $user) {
            $this->flashMessenger()
                ->addMessage('verification_user_not_found', 'error');
        } else {
            // Make sure we've waited long enough
            $hashtime = $this->getHashAge($user->verify_hash);
            $recoveryInterval = $this->getConfig()->Authentication->recover_interval
                ?? 60;
            if (time() - $hashtime < $recoveryInterval && !$change) {
                $this->flashMessenger()
                    ->addMessage('verification_too_soon', 'error');
            } else {
                // Attempt to send the email
                try {
                    // Create a fresh hash
                    $user->updateHash();
                    $config = $this->getConfig();
                    $renderer = $this->getViewRenderer();
                    // Custom template for emails (text-only)
                    $message = $renderer->render(
                        'Email/verify-email.phtml',
                        [
                            'library' => $config->Site->title,
                            'url' => $this->getServerUrl('myresearch-verifyemail')
                                . '?hash=' . urlencode($user->verify_hash),
                        ]
                    );
                    // If the user is setting up a new account, use the main email
                    // address; if they have a pending address change, use that.
                    $to = empty($user->pending_email)
                        ? $user->email : $user->pending_email;
                    $this->serviceLocator->get(Mailer::class)->send(
                        $to,
                        $config->Site->email,
                        $this->translate('verification_email_subject'),
                        $message
                    );
                    $flashMessage = $change
                        ? 'verification_email_change_sent'
                        : 'verification_email_sent';
                    $this->flashMessenger()->addMessage($flashMessage, 'info');
                    // If this is an email change, send a notification to the old
                    // email address as well.
                    if ($change) {
                        $this->sendChangeNotificationEmail($user, $to);
                    }
                } catch (MailException $e) {
                    $this->flashMessenger()->addMessage($e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Receive a hash and display the new password form if it's valid
     *
     * @return mixed
     */
    public function verifyAction()
    {
        // If we have a submitted form
        if ($hash = $this->params()->fromQuery('hash')) {
            $hashtime = $this->getHashAge($hash);
            $config = $this->getConfig();
            // Check if hash is expired
            $hashLifetime = $config->Authentication->recover_hash_lifetime
                ?? 1209600; // Two weeks
            if (time() - $hashtime > $hashLifetime) {
                $this->flashMessenger()
                    ->addMessage('recovery_expired_hash', 'error');
                return $this->forwardTo('MyResearch', 'Login');
            } else {
                $table = $this->getTable('User');
                $user = $table->getByVerifyHash($hash);
                // If the hash is valid, forward user to create new password
                // Also treat email address as verified
                if (null != $user) {
                    $user->saveEmailVerified();
                    $this->setUpAuthenticationFromRequest();
                    $view = $this->createViewModel();
                    $view->auth_method = $this->getAuthManager()->getAuthMethod();
                    $view->hash = $hash;
                    $view->username = $user->username;
                    $view->useCaptcha = $this->captcha()->active('changePassword');
                    $view->passwordPolicy = $this->getAuthManager()
                        ->getPasswordPolicy();
                    $view->setTemplate('myresearch/newpassword');
                    return $view;
                }
            }
        }
        $this->flashMessenger()->addMessage('recovery_invalid_hash', 'error');
        return $this->forwardTo('MyResearch', 'Login');
    }

    /**
     * Receive a hash and display the new password form if it's valid
     *
     * @return mixed
     */
    public function verifyEmailAction()
    {
        // If we have a submitted form
        if ($hash = $this->params()->fromQuery('hash')) {
            $hashtime = $this->getHashAge($hash);
            $config = $this->getConfig();
            // Check if hash is expired
            $hashLifetime = $config->Authentication->recover_hash_lifetime
                ?? 1209600; // Two weeks
            if (time() - $hashtime > $hashLifetime) {
                $this->flashMessenger()
                    ->addMessage('recovery_expired_hash', 'error');
                return $this->forwardTo('MyResearch', 'Profile');
            } else {
                $table = $this->getTable('User');
                $user = $table->getByVerifyHash($hash);
                // If the hash is valid, store validation in DB and forward to login
                if (null != $user) {
                    // Apply pending email address change, if applicable:
                    if (!empty($user->pending_email)) {
                        $user->updateEmail($user->pending_email, true);
                        $user->pending_email = '';
                    }
                    $user->saveEmailVerified();

                    $this->flashMessenger()->addMessage('verification_done', 'info');
                    return $this->redirect()->toRoute('myresearch-profile');
                }
            }
        }
        $this->flashMessenger()->addMessage('recovery_invalid_hash', 'error');
        return $this->redirect()->toRoute('myresearch-profile');
    }

    /**
     * Reset the new password form and return the modified view. When a user has
     * already been loaded from an existing hash, this resets the hash and updates
     * the form so that the user can try again.
     *
     * @param mixed     $userFromHash User loaded from database, or false if none.
     * @param ViewModel $view         View object
     *
     * @return ViewModel
     */
    protected function resetNewPasswordForm($userFromHash, ViewModel $view)
    {
        if ($userFromHash) {
            $userFromHash->updateHash();
            $view->username = $userFromHash->username;
            $view->hash = $userFromHash->verify_hash;
        }
        return $view;
    }

    /**
     * Handling submission of a new password for a user.
     *
     * @return mixed
     */
    public function newPasswordAction()
    {
        // Have we submitted the form?
        if (!$this->formWasSubmitted('submit')) {
            return $this->redirect()->toRoute('home');
        }
        // Set up authentication so that we can retrieve the correct password policy:
        $this->setUpAuthenticationFromRequest();
        // Pull in from POST
        $request = $this->getRequest();
        $post = $request->getPost();
        // Verify hash
        $userFromHash = isset($post->hash)
            ? $this->getTable('User')->getByVerifyHash($post->hash)
            : false;
        // View, password policy and Captcha
        $view = $this->createViewModel($post);
        $view->passwordPolicy = $this->getAuthManager()->getPasswordPolicy();
        $view->useCaptcha = $this->captcha()->active('changePassword');
        // Check Captcha
        if (!$this->formWasSubmitted('submit', $view->useCaptcha)) {
            return $this->resetNewPasswordForm($userFromHash, $view);
        }
        // Missing or invalid hash
        if (false == $userFromHash) {
            $this->flashMessenger()->addMessage('recovery_user_not_found', 'error');
            // Force login or restore hash
            $post->username = false;
            return $this->forwardTo('MyResearch', 'Recover');
        } elseif ($userFromHash->username !== $post->username) {
            $this->flashMessenger()
                ->addMessage('authentication_error_invalid', 'error');
            return $this->resetNewPasswordForm($userFromHash, $view);
        }
        // Verify old password if we're logged in
        if ($this->getUser()) {
            if (isset($post->oldpwd)) {
                // Reassign oldpwd to password in the request so login works
                $tempPassword = $post->password;
                $post->password = $post->oldpwd;
                $valid = $this->getAuthManager()->validateCredentials($request);
                $post->password = $tempPassword;
            } else {
                $valid = false;
            }
            if (!$valid) {
                $this->flashMessenger()
                    ->addMessage('authentication_error_invalid', 'error');
                $view->verifyold = true;
                return $view;
            }
        }
        // Update password
        try {
            $user = $this->getAuthManager()->updatePassword($this->getRequest());
        } catch (AuthException $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            return $view;
        }
        // Update hash to prevent reusing hash
        $user->updateHash();
        // Login
        $this->getAuthManager()->login($this->request);
        // Return to account home
        $this->flashMessenger()->addMessage('new_password_success', 'success');
        return $this->redirect()->toRoute('myresearch-home');
    }

    /**
     * Handling submission of a new email for a user.
     *
     * @return mixed
     */
    public function changeEmailAction()
    {
        // Always check that we are logged in and function is enabled first:
        if (!$this->getAuthManager()->isLoggedIn()) {
            return $this->forceLogin();
        }
        if (!$this->getAuthManager()->supportsEmailChange()) {
            $this->flashMessenger()->addMessage('change_email_disabled', 'error');
            return $this->redirect()->toRoute('home');
        }
        $view = $this->createViewModel($this->params()->fromPost());
        // Display email
        $user = $this->getUser();
        $view->email = $user->email;
        // Identification
        $view->useCaptcha = $this->captcha()->active('changeEmail');
        // Special case: form was submitted:
        if ($this->formWasSubmitted('submit', $view->useCaptcha)) {
            // Do CSRF check
            $csrf = $this->serviceLocator->get(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }
            // Update email
            $validator = new \Laminas\Validator\EmailAddress();
            $email = $this->params()->fromPost('email', '');
            try {
                if (!$validator->isValid($email)) {
                    throw new AuthException('Email address is invalid');
                }
                $this->getAuthManager()->updateEmail($user, $email);
                // If we have a pending change, we need to send a verification email:
                if (!empty($user->pending_email)) {
                    $this->sendVerificationEmail($user, true);
                } else {
                    $this->flashMessenger()
                        ->addMessage('new_email_success', 'success');
                }
            } catch (AuthException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
                return $view;
            }
            // Return to account home
            return $this->redirect()->toRoute('myresearch-home');
        } elseif ($this->getConfig()->Authentication->verify_email ?? false) {
            $this->flashMessenger()
                ->addMessage('change_email_verification_reminder', 'info');
        }
        $this->addPendingEmailChangeMessage($user);
        return $view;
    }

    /**
     * Handling submission of a new password for a user.
     *
     * @return mixed
     */
    public function changePasswordAction()
    {
        if (!$this->getAuthManager()->isLoggedIn()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->getAuthManager()->supportsPasswordChange()) {
            $this->flashMessenger()->addMessage('recovery_new_disabled', 'error');
            return $this->redirect()->toRoute('home');
        }
        $view = $this->createViewModel($this->params()->fromPost());
        // Verify user password
        $view->verifyold = true;
        // Display username
        $user = $this->getUser();
        $view->username = $user->username;
        // Password policy
        $view->passwordPolicy = $this->getAuthManager()
            ->getPasswordPolicy();
        // Identification
        $user->updateHash();
        $view->hash = $user->verify_hash;
        $view->setTemplate('myresearch/newpassword');
        $view->useCaptcha = $this->captcha()->active('changePassword');
        return $view;
    }

    /**
     * Helper function for verification hashes
     *
     * @param string $hash User-unique hash string from request
     *
     * @return int age in seconds
     */
    protected function getHashAge($hash)
    {
        return intval(substr($hash, -10));
    }

    /**
     * Configure the authentication manager to use a user-specified method.
     *
     * @return void
     */
    protected function setUpAuthenticationFromRequest()
    {
        $method = trim(
            $this->params()->fromQuery(
                'auth_method',
                $this->params()->fromPost('auth_method')
            )
        );
        if (!empty($method)) {
            $this->getAuthManager()->setAuthMethod($method);
        }
    }

    /**
     * Account deletion
     *
     * @return mixed
     */
    public function deleteAccountAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        $config = $this->getConfig();
        if (empty($config->Authentication->account_deletion)) {
            throw new \VuFind\Exception\BadRequest();
        }

        $view = $this->createViewModel(['accountDeleted' => false]);
        if ($this->formWasSubmitted('submit')) {
            $csrf = $this->serviceLocator->get(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            } else {
                // After successful token verification, clear list to shrink session:
                $csrf->trimTokenList(0);
            }
            $user->delete(
                $config->Authentication->delete_comments_with_user ?? true,
                $config->Authentication->delete_ratings_with_user ?? true
            );
            $view->accountDeleted = true;
            $view->redirectUrl = $this->getAuthManager()->logout(
                $this->getServerUrl('home')
            );
        } elseif ($this->formWasSubmitted('reset')) {
            return $this->redirect()->toRoute('myresearch-profile');
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
        $type = $this->params()->fromQuery('type', 'alert');
        if ($id === false || $key === false) {
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
                $secret = $search->getUnsubscribeSecret(
                    $this->serviceLocator->get(\VuFind\Crypt\HMAC::class),
                    $user
                );
                if ($key !== $secret) {
                    throw new \Exception('Invalid parameters.');
                }
                $search->setSchedule(0);
                $view->success = true;
            }
        } else {
            $view->unsubscribeUrl
                = $this->getRequest()->getRequestUri() . '&confirm=1';
        }
        return $view;
    }

    /**
     * Get the ILS pagination helper
     *
     * @return PaginationHelper
     */
    protected function getPaginationHelper()
    {
        if (null === $this->paginationHelper) {
            $this->paginationHelper = new PaginationHelper();
        }
        return $this->paginationHelper;
    }

    /**
     * Are list tags enabled?
     *
     * @return bool
     */
    protected function listTagsEnabled()
    {
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        return $check->getListTagSetting() === 'enabled';
    }

    /**
     * Add a message about any pending email change to the flash messenger
     *
     * @param \VuFind\Db\Row\User $user User
     *
     * @return void
     */
    protected function addPendingEmailChangeMessage($user)
    {
        if (!empty($user->pending_email)) {
            $url = $this->url()->fromRoute(
                'myresearch-emailnotverified',
                [],
                ['query' => ['reverify' => 'true']]
            );
            $pendingEmailEsc = htmlspecialchars(
                $user->pending_email,
                ENT_COMPAT,
                'UTF-8'
            );
            $this->flashMessenger()->addInfoMessage(
                [
                    'html' => true,
                    'msg' => 'email_change_pending_html',
                    'tokens' => [
                        '%%pending%%' => $pendingEmailEsc,
                        '%%url%%' => $url,
                    ],
                ]
            );
        }
    }
}
