<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * PHP version 7
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

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Http\PhpEnvironment\Request as HttpRequest;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 *
 * @method Plugin\Captcha captcha() Captcha plugin
 * @method Plugin\DbUpgrade dbUpgrade() DbUpgrade plugin
 * @method Plugin\Favorites favorites() Favorites plugin
 * @method FlashMessenger flashMessenger() FlashMessenger plugin
 * @method Plugin\Followup followup() Followup plugin
 * @method Plugin\Holds holds() Holds plugin
 * @method Plugin\ILLRequests ILLRequests() ILLRequests plugin
 * @method Plugin\IlsRecords ilsRecords() IlsRecords plugin
 * @method Plugin\NewItems newItems() NewItems plugin
 * @method Plugin\Permission permission() Permission plugin
 * @method Plugin\Renewals renewals() Renewals plugin
 * @method Plugin\Reserves reserves() Reserves plugin
 * @method Plugin\ResultScroller resultScroller() ResultScroller plugin
 * @method Plugin\StorageRetrievalRequests storageRetrievalRequests()
 * StorageRetrievalRequests plugin
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class AbstractBase extends AbstractActionController
    implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Permission that must be granted to access this module (false for no
     * restriction, null to use configured default (which is usually the same
     * as false)).
     *
     * @var string|bool
     */
    protected $accessPermission = null;

    /**
     * Behavior when access is denied (used unless overridden through
     * permissionBehavior.ini). Valid values are 'promptLogin' and 'exception'.
     * Leave at null to use the defaultDeniedControllerBehavior set in
     * permissionBehavior.ini (normally 'promptLogin' unless changed).
     *
     * @var string
     */
    protected $accessDeniedBehavior = null;

    /**
     * Service manager
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->serviceLocator = $sm;
    }

    /**
     * Use preDispatch event to block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function validateAccessPermission(MvcEvent $e)
    {
        // If there is an access permission set for this controller, pass it
        // through the permission helper, and if the helper returns a custom
        // response, use that instead of the normal behavior.
        if ($this->accessPermission) {
            $response = $this->permission()
                ->check($this->accessPermission, $this->accessDeniedBehavior);
            if (is_object($response)) {
                $e->setResponse($response);
            }
        }
    }

    /**
     * Getter for access permission.
     *
     * @return string|bool
     */
    public function getAccessPermission()
    {
        return $this->accessPermission;
    }

    /**
     * Getter for access permission.
     *
     * @param string $ap Permission to require for access to the controller (false
     * for no requirement)
     *
     * @return void
     */
    public function setAccessPermission($ap)
    {
        $this->accessPermission = empty($ap) ? false : $ap;
    }

    /**
     * Get request object
     *
     * @return HttpRequest
     */
    public function getRequest()
    {
        if (!$this->request) {
            $this->request = new HttpRequest();
        }

        return $this->request;
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();

        // Attach preDispatch event if we need to check permissions.
        if ($this->accessPermission) {
            $events = $this->getEventManager();
            $events->attach(
                MvcEvent::EVENT_DISPATCH,
                [$this, 'validateAccessPermission'],
                1000
            );
        }
    }

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    protected function createViewModel($params = null)
    {
        if ($this->inLightbox()) {
            $this->layout()->setTemplate('layout/lightbox');
            $params['inLightbox'] = true;
        }
        return new ViewModel($params);
    }

    /**
     * Create a new ViewModel to use as an email form.
     *
     * @param array  $params         Parameters to pass to ViewModel constructor.
     * @param string $defaultSubject Default subject line to use.
     *
     * @return ViewModel
     */
    protected function createEmailViewModel($params = null, $defaultSubject = null)
    {
        // Build view:
        $view = $this->createViewModel($params);

        // Load configuration and current user for convenience:
        $config = $this->getConfig();
        $view->disableFrom
            = (isset($config->Mail->disable_from) && $config->Mail->disable_from);
        $view->editableSubject = isset($config->Mail->user_editable_subjects)
            && $config->Mail->user_editable_subjects;
        $view->maxRecipients = isset($config->Mail->maximum_recipients)
            ? intval($config->Mail->maximum_recipients) : 1;
        $user = $this->getUser();

        // Send parameters back to view so form can be re-populated:
        if ($this->getRequest()->isPost()) {
            $view->to = $this->params()->fromPost('to');
            if (!$view->disableFrom) {
                $view->from = $this->params()->fromPost('from');
            }
            if ($view->editableSubject) {
                $view->subject = $this->params()->fromPost('subject');
            }
            $view->message = $this->params()->fromPost('message');
        }

        // Set default values if applicable:
        if ((!isset($view->to) || empty($view->to)) && $user
            && isset($config->Mail->user_email_in_to)
            && $config->Mail->user_email_in_to
        ) {
            $view->to = $user->email;
        }
        if (!isset($view->from) || empty($view->from)) {
            if ($user && isset($config->Mail->user_email_in_from)
                && $config->Mail->user_email_in_from
            ) {
                $view->userEmailInFrom = true;
                $view->from = $user->email;
            } elseif (isset($config->Mail->default_from)
                && $config->Mail->default_from
            ) {
                $view->from = $config->Mail->default_from;
            }
        }
        if (!isset($view->subject) || empty($view->subject)) {
            $view->subject = $defaultSubject;
        }

        // Fail if we're missing a from and the form element is disabled:
        if ($view->disableFrom) {
            if (empty($view->from)) {
                $view->from = $config->Site->email;
            }
            if (empty($view->from)) {
                throw new \Exception('Unable to determine email from address');
            }
        }

        return $view;
    }

    /**
     * Get the account manager object.
     *
     * @return \VuFind\Auth\Manager
     */
    protected function getAuthManager()
    {
        return $this->serviceLocator->get(\VuFind\Auth\Manager::class);
    }

    /**
     * Get the authorization service (note that we're doing this on-demand
     * rather than through injection with the AuthorizationServiceAwareInterface
     * to minimize expensive initialization when authorization is not needed.
     *
     * @return \LmcRbacMvc\Service\AuthorizationService
     */
    protected function getAuthorizationService()
    {
        return $this->serviceLocator
            ->get(\LmcRbacMvc\Service\AuthorizationService::class);
    }

    /**
     * Get the ILS authenticator.
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getILSAuthenticator()
    {
        return $this->serviceLocator->get(\VuFind\Auth\ILSAuthenticator::class);
    }

    /**
     * Get the user object if logged in, false otherwise.
     *
     * @return \VuFind\Db\Row\User|bool
     */
    protected function getUser()
    {
        return $this->getAuthManager()->isLoggedIn();
    }

    /**
     * Get the view renderer
     *
     * @return \Laminas\View\Renderer\RendererInterface
     */
    protected function getViewRenderer()
    {
        return $this->serviceLocator->get('ViewRenderer');
    }

    /**
     * Redirect the user to the login screen.
     *
     * @param string $msg     Flash message to display on login screen
     * @param array  $extras  Associative array of extra fields to store
     * @param bool   $forward True to forward, false to redirect
     *
     * @return mixed
     */
    public function forceLogin($msg = null, $extras = [], $forward = true)
    {
        // Set default message if necessary.
        if (null === $msg) {
            $msg = 'You must be logged in first';
        }

        // We don't want to return to the lightbox
        $serverUrl = $this->getServerUrl();
        $serverUrl = str_replace(
            ['?layout=lightbox', '&layout=lightbox'],
            ['?', '&'],
            $serverUrl
        );

        // Store the current URL as a login followup action
        $this->followup()->store($extras, $serverUrl);
        if (!empty($msg)) {
            $this->flashMessenger()->addMessage($msg, 'error');
        }

        // Set a flag indicating that we are forcing login:
        $this->getRequest()->getPost()->set('forcingLogin', true);

        if ($forward) {
            return $this->forwardTo('MyResearch', 'Login');
        }
        return $this->redirect()->toRoute('myresearch-home');
    }

    /**
     * Does the user have catalog credentials available?  Returns associative array
     * of patron data if so, otherwise forwards to appropriate login prompt and
     * returns false. If there is an ILS exception, a flash message is added and
     * a newly created ViewModel is returned.
     *
     * @return bool|array|ViewModel
     */
    protected function catalogLogin()
    {
        // First make sure user is logged in to VuFind:
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            return $this->forceLogin();
        }

        // Now check if the user has provided credentials with which to log in:
        $ilsAuth = $this->getILSAuthenticator();
        $patron = null;
        if (($username = $this->params()->fromPost('cat_username', false))
            && ($password = $this->params()->fromPost('cat_password', false))
        ) {
            // If somebody is POSTing credentials but that logic is disabled, we
            // should throw an exception!
            if (!$account->allowsUserIlsLogin()) {
                throw new \Exception('Unexpected ILS credential submission.');
            }
            // Check for multiple ILS target selection
            $target = $this->params()->fromPost('target', false);
            if ($target) {
                $username = "$target.$username";
            }
            try {
                if ('email' === $this->getILSLoginMethod($target)) {
                    $routeMatch = $this->getEvent()->getRouteMatch();
                    $routeName = $routeMatch ? $routeMatch->getMatchedRouteName()
                        : 'myresearch-profile';
                    $ilsAuth->sendEmailLoginLink($username, $routeName);
                    $this->flashMessenger()
                        ->addSuccessMessage('email_login_link_sent');
                } else {
                    $patron = $ilsAuth->newCatalogLogin($username, $password);

                    // If login failed, store a warning message:
                    if (!$patron) {
                        $this->flashMessenger()
                            ->addErrorMessage('Invalid Patron Login');
                    }
                }
            } catch (ILSException $e) {
                $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            }
        } elseif ('ILS' === $this->params()->fromQuery('auth_method', false)
            && ($hash = $this->params()->fromQuery('hash', false))
        ) {
            try {
                $patron = $ilsAuth->processEmailLoginHash($hash);
            } catch (AuthException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }
        } else {
            try {
                // If no credentials were provided, try the stored values:
                $patron = $ilsAuth->storedCatalogLogin();
            } catch (ILSException $e) {
                $this->flashMessenger()->addErrorMessage('ils_connection_failed');
                return $this->createViewModel();
            }
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            return $this->forwardTo('MyResearch', 'CatalogLogin');
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
    }

    /**
     * Get a VuFind configuration.
     *
     * @param string $id Configuration identifier (default = main VuFind config)
     *
     * @return \Laminas\Config\Config
     */
    public function getConfig($id = 'config')
    {
        return $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get($id);
    }

    /**
     * Get the ILS connection.
     *
     * @return \VuFind\ILS\Connection
     */
    public function getILS()
    {
        return $this->serviceLocator->get(\VuFind\ILS\Connection::class);
    }

    /**
     * Get the record loader
     *
     * @return \VuFind\Record\Loader
     */
    public function getRecordLoader()
    {
        return $this->serviceLocator->get(\VuFind\Record\Loader::class);
    }

    /**
     * Get the record cache
     *
     * @return \VuFind\Record\Cache
     */
    public function getRecordCache()
    {
        return $this->serviceLocator->get(\VuFind\Record\Cache::class);
    }

    /**
     * Get the record router.
     *
     * @return \VuFind\Record\Router
     */
    public function getRecordRouter()
    {
        return $this->serviceLocator->get(\VuFind\Record\Router::class);
    }

    /**
     * Get a database table object.
     *
     * @param string $table Name of table to retrieve
     *
     * @return \VuFind\Db\Table\Gateway
     */
    public function getTable($table)
    {
        return $this->serviceLocator->get(\VuFind\Db\Table\PluginManager::class)
            ->get($table);
    }

    /**
     * Get a database service object.
     *
     * @param string $name Name of service to retrieve
     *
     * @return \VuFind\Db\Service\AbstractService
     */
    public function getDbService(string $name): \VuFind\Db\Service\AbstractService
    {
        return $this->serviceLocator->get(\VuFind\Db\Service\PluginManager::class)
            ->get($name);
    }

    /**
     * Get the full URL to one of VuFind's routes.
     *
     * @param bool|string $route Boolean true for current URL, otherwise name of
     * route to render as URL
     *
     * @return string
     */
    public function getServerUrl($route = true)
    {
        $serverHelper = $this->getViewRenderer()->plugin('serverurl');
        return $serverHelper(
            $route === true ? true : $this->url()->fromRoute($route)
        );
    }

    /**
     * Convenience method to make invocation of forward() helper less verbose.
     *
     * @param string $controller Controller to invoke
     * @param string $action     Action to invoke
     * @param array  $params     Extra parameters for the RouteMatch object (no
     * need to provide action here, since $action takes care of that)
     *
     * @return mixed
     */
    public function forwardTo($controller, $action, $params = [])
    {
        // Inject action into the RouteMatch parameters
        $params['action'] = $action;

        // Dispatch the requested controller/action:
        return $this->forward()->dispatch($controller, $params);
    }

    /**
     * Check to see if a form was submitted from its post value
     * Also validate the Captcha, if it's activated
     *
     * @param string $submitElement Name of the post field of the submit button
     * @param bool   $useCaptcha    Are we using captcha in this situation?
     *
     * @return bool
     */
    protected function formWasSubmitted(
        $submitElement = 'submit',
        $useCaptcha = false
    ) {
        // Fail if the expected submission element was missing from the POST:
        // Form was submitted; if CAPTCHA is expected, validate it now.
        return $this->params()->fromPost($submitElement, false)
            && (!$useCaptcha || $this->captcha()->verify());
    }

    /**
     * Confirm an action.
     *
     * @param string       $title     Title of confirm dialog
     * @param string       $yesTarget Form target for "confirm" action
     * @param string       $noTarget  Form target for "cancel" action
     * @param string|array $messages  Info messages for confirm dialog
     * @param array        $extras    Extra details to include in form
     *
     * @return mixed
     */
    public function confirm(
        $title,
        $yesTarget,
        $noTarget,
        $messages = [],
        $extras = []
    ) {
        return $this->forwardTo(
            'Confirm',
            'Confirm',
            [
                'data' => [
                    'title' => $title,
                    'confirm' => $yesTarget,
                    'cancel' => $noTarget,
                    'messages' => (array)$messages,
                    'extras' => $extras
                ]
            ]
        );
    }

    /**
     * Prevent session writes -- this is designed to be called prior to time-
     * consuming AJAX operations to help reduce the odds of a timing-related bug
     * that causes the wrong version of session data to be written to disk (see
     * VUFIND-716 for more details).
     *
     * @return void
     */
    protected function disableSessionWrites()
    {
        $this->serviceLocator->get(\VuFind\Session\Settings::class)->disableWrite();
    }

    /**
     * Get the search memory
     *
     * @return \VuFind\Search\Memory
     */
    public function getSearchMemory()
    {
        return $this->serviceLocator->get(\VuFind\Search\Memory::class);
    }

    /**
     * Are comments enabled?
     *
     * @return bool
     */
    protected function commentsEnabled()
    {
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        return $check->getCommentSetting() !== 'disabled';
    }

    /**
     * Are lists enabled?
     *
     * @return bool
     */
    protected function listsEnabled()
    {
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        return $check->getListSetting() !== 'disabled';
    }

    /**
     * Are tags enabled?
     *
     * @return bool
     */
    protected function tagsEnabled()
    {
        $check = $this->serviceLocator
            ->get(\VuFind\Config\AccountCapabilities::class);
        return $check->getTagSetting() !== 'disabled';
    }

    /**
     * Store a referer (if appropriate) to keep post-login redirect pointing
     * to an appropriate location. This is used when the user clicks the
     * log in link from an arbitrary page or when a password is mistyped;
     * separate logic is used for storing followup information when VuFind
     * forces the user to log in from another context.
     *
     * @return void
     */
    protected function setFollowupUrlToReferer()
    {
        // lbreferer is the stored current url of the lightbox
        // which overrides the url from the server request when present
        $referer = $this->getRequest()->getQuery()->get(
            'lbreferer',
            $this->getRequest()->getServer()->get('HTTP_REFERER', null)
        );
        // Get the referer -- if it's empty, there's nothing to store!
        if (empty($referer)) {
            return;
        }
        $refererNorm = $this->normalizeUrlForComparison($referer);

        // If the referer lives outside of VuFind, don't store it! We only
        // want internal post-login redirects.
        $baseUrl = $this->getServerUrl('home');
        $baseUrlNorm = $this->normalizeUrlForComparison($baseUrl);
        if (0 !== strpos($refererNorm, $baseUrlNorm)) {
            return;
        }

        // If the referer is the MyResearch/Home action, it probably means
        // that the user is repeatedly mistyping their password. We should
        // ignore this and instead rely on any previously stored referer.
        $myResearchHomeUrl = $this->getServerUrl('myresearch-home');
        $mrhuNorm = $this->normalizeUrlForComparison($myResearchHomeUrl);
        if ($mrhuNorm === $refererNorm) {
            return;
        }

        // If the referer is the MyResearch/UserLogin action, it probably means
        // that the user is repeatedly mistyping their password. We should
        // ignore this and instead rely on any previously stored referer.
        $myUserLogin = $this->getServerUrl('myresearch-userlogin');
        $mulNorm = $this->normalizeUrlForComparison($myUserLogin);
        if (0 === strpos($refererNorm, $mulNorm)) {
            return;
        }

        // If we got this far, we want to store the referer:
        $this->followup()->store([], $referer);
    }

    /**
     * Normalize the referer URL so that inconsistencies in protocol and trailing
     * slashes do not break comparisons.
     *
     * @param string $url URL to normalize
     *
     * @return string
     */
    protected function normalizeUrlForComparison($url)
    {
        $parts = explode('://', $url, 2);
        return trim(end($parts), '/');
    }

    /**
     * Retrieve a referer to keep post-login redirect pointing
     * to an appropriate location.
     * Unset the followup before returning.
     *
     * @return string
     */
    protected function getFollowupUrl()
    {
        return $this->followup()->retrieve('url', '');
    }

    /**
     * Sometimes we need to unset the followup to trigger default behaviors
     *
     * @return void
     */
    protected function clearFollowupUrl()
    {
        $this->followup()->clear('url');
    }

    /**
     * Get the tab configuration for this controller.
     *
     * @return \VuFind\RecordTab\TabManager
     */
    protected function getRecordTabManager()
    {
        return $this->serviceLocator->get(\VuFind\RecordTab\TabManager::class);
    }

    /**
     * Are we currently in a lightbox context?
     *
     * @return bool
     */
    protected function inLightbox()
    {
        return
            $this->params()->fromPost(
                'layout',
                $this->params()->fromQuery('layout', false)
            ) === 'lightbox'
            || 'layout/lightbox' == $this->layout()->getTemplate();
    }

    /**
     * What login method does the ILS use (password, email, vufind)
     *
     * @param string $target Login target (MultiILS only)
     *
     * @return string
     */
    protected function getILSLoginMethod($target = '')
    {
        $config = $this->getILS()->checkFunction(
            'patronLogin',
            ['patron' => ['cat_username' => "$target.login"]]
        );
        return $config['loginMethod'] ?? 'password';
    }

    /**
     * Get settings required for displaying the catalog login form
     *
     * @return array
     */
    protected function getILSLoginSettings()
    {
        $targets = null;
        $defaultTarget = null;
        $loginMethod = null;
        $loginMethods = [];
        // Connect to the ILS and check if multiple target support is available:
        $catalog = $this->getILS();
        if ($catalog->checkCapability('getLoginDrivers')) {
            $targets = $catalog->getLoginDrivers();
            $defaultTarget = $catalog->getDefaultLoginDriver();
            foreach ($targets as $t) {
                $loginMethods[$t] = $this->getILSLoginMethod($t);
            }
        } else {
            $loginMethod = $this->getILSLoginMethod();
        }
        return compact('targets', 'defaultTarget', 'loginMethod', 'loginMethods');
    }

    /**
     * Construct an HTTP 205 (refresh) response. Useful for reporting success
     * in the lightbox without actually rendering content.
     *
     * @return \Laminas\Http\Response
     */
    protected function getRefreshResponse()
    {
        $response = $this->getResponse();
        $response->setStatusCode(205);
        return $response;
    }
}
