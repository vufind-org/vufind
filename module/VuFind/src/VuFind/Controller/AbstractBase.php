<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
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
use Zend\Mvc\Controller\AbstractActionController, Zend\View\Model\ViewModel;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class AbstractBase extends AbstractActionController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Placeholder so child classes can call parent::__construct() in case
        // of future global behavior.
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
        return new ViewModel($params);
    }

    /**
     * Create a new ViewModel to use as an email form.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    protected function createEmailViewModel($params = null)
    {
        // Build view:
        $view = $this->createViewModel($params);

        // Load configuration and current user for convenience:
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $view->disableFrom
            = (isset($config->Mail->disable_from) && $config->Mail->disable_from);
        $user = $this->getUser();

        // Send parameters back to view so form can be re-populated:
        if ($this->getRequest()->isPost()) {
            $view->to = $this->params()->fromPost('to');
            if (!$view->disableFrom) {
                $view->from = $this->params()->fromPost('from');
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
            } else if (isset($config->Mail->default_from)
                && $config->Mail->default_from
            ) {
                $view->from = $config->Mail->default_from;
            }
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
        return $this->getServiceLocator()->get('VuFind\AuthManager');
    }

    /**
     * Get the user object if logged in, false otherwise.
     *
     * @return object|bool
     */
    protected function getUser()
    {
        return $this->getAuthManager()->isLoggedIn();
    }

    /**
     * Get the view renderer
     *
     * @return \Zend\View\Renderer\RendererInterface
     */
    protected function getViewRenderer()
    {
        return $this->getServiceLocator()->get('viewmanager')->getRenderer();
    }

    /**
     * Are we running in a lightbox?
     *
     * @return bool
     */
    public function inLightbox()
    {
        return ($this->layout()->getTemplate() == 'layout/lightbox');
    }

    /**
     * Get a URL for a route with lightbox awareness.
     *
     * @param string $route              Route name
     * @param array  $params             Route parameters
     * @param array  $options            RouteInterface-specific options to use in
     * url generation, if any
     * @param bool   $reuseMatchedParams Whether to reuse matched parameters
     *
     * @return string
     */
    public function getLightboxAwareUrl($route, $params = array(),
        $options = array(), $reuseMatchedParams = false
    ) {
        // Rearrange the parameters if we're in a lightbox:
        if ($this->inLightbox()) {
            // Make sure we have a query:
            $options['query'] = isset($options['query'])
                ? $options['query'] : array();

            // Map ID route parameter into a GET parameter if necessary:
            if (isset($params['id'])) {
                $options['query']['id'] = $params['id'];
            }

            // Change the current route into submodule/subaction lightbox params:
            $parts = explode('-', $route);
            $options['query']['submodule'] = $parts[0];
            $options['query']['subaction'] = isset($parts[1]) ? $parts[1] : 'home';
            $options['query']['method'] = 'getLightbox';

            // Override the current route with the lightbox action:
            $route = 'default';
            $params['controller'] = 'AJAX';
            $params['action'] = 'JSON';
        }

        // Build the URL:
        return $this->url()
            ->fromRoute($route, $params, $options, $reuseMatchedParams);
    }

    /**
     * Lightbox-aware redirect -- if we're in a lightbox, go to a route that
     * keeps us there; otherwise, go to the normal route.
     *
     * @param string $route              Route name
     * @param array  $params             Route parameters
     * @param array  $options            RouteInterface-specific options to use in
     * url generation, if any
     * @param bool   $reuseMatchedParams Whether to reuse matched parameters
     *
     * @return \Zend\Http\Response
     */
    public function lightboxAwareRedirect($route, $params = array(),
        $options = array(), $reuseMatchedParams = false
    ) {
        return $this->redirect()->toUrl(
            $this->getLightboxAwareUrl(
                $route, $params, $options, $reuseMatchedParams
            )
        );
    }

    /**
     * Support method for forceLogin() -- convert a lightbox URL to a non-lightbox
     * URL.
     *
     * @param string $url URL to convert
     *
     * @return string
     */
    protected function delightboxURL($url)
    {
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        if (false === strpos($parts['path'], '/AJAX/JSON')) {
            return $url;
        }
        $controller = strtolower($query['submodule']);
        $action     = strtolower($query['subaction']);
        unset($query['method'], $query['subaction'], $query['submodule']);
        return $this->url()->fromRoute($controller.'-'.$action, $query);
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
    protected function forceLogin($msg = null, $extras = array(), $forward = true)
    {
        // Set default message if necessary.
        if (is_null($msg)) {
            $msg = 'You must be logged in first';
        }

        // Store the current URL as a login followup action unless we are in a
        // lightbox (since lightboxes use a different followup mechanism).
        if (!$this->inLightbox()) {
            $this->followup()->store($extras);
        } else {
            // If we're in a lightbox and using an authentication method
            // with a session initiator, the user will be redirected outside
            // of VuFind and then redirected back. Thus, we need to store a
            // followup URL to avoid losing context, but we don't want to
            // store the AJAX request URL that populated the lightbox. The
            // delightboxURL() routine will remap the URL appropriately.
            // We can set this whether or not there's a session initiator
            // because it will be cleared when needed.
            $url = $this->delightboxURL($this->getServerUrl());
            $this->followup()->store($extras, $url);
        }
        if (!empty($msg)) {
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
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
     * returns false.
     *
     * @return bool|array
     */
    protected function catalogLogin()
    {
        // First make sure user is logged in to VuFind:
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            return $this->forceLogin();
        }

        // Now check if the user has provided credentials with which to log in:
        if (($username = $this->params()->fromPost('cat_username', false))
            && ($password = $this->params()->fromPost('cat_password', false))
        ) {
            $patron = $account->newCatalogLogin($username, $password);

            // If login failed, store a warning message:
            if (!$patron) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Invalid Patron Login');
            }
        } else {
            // If no credentials were provided, try the stored values:
            $patron = $account->storedCatalogLogin();
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
     * @return \Zend\Config\Config
     */
    public function getConfig($id = 'config')
    {
        return $this->getServiceLocator()->get('VuFind\Config')->get($id);
    }

    /**
     * Get the ILS connection.
     *
     * @return \VuFind\ILS\Connection
     */
    public function getILS()
    {
        return $this->getServiceLocator()->get('VuFind\ILSConnection');
    }

    /**
     * Get the record loader
     *
     * @return \VuFind\Record\Loader
     */
    public function getRecordLoader()
    {
        return $this->getServiceLocator()->get('VuFind\RecordLoader');
    }

    /**
     * Get the record router.
     *
     * @return \VuFind\Record\Router
     */
    public function getRecordRouter()
    {
        return $this->getServiceLocator()->get('VuFind\RecordRouter');
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
        return $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get($table);
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
     * Translate a string if a translator is available.
     *
     * @param string $msg     Message to translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found (null
     * for no default).
     *
     * @return string
     */
    public function translate($msg, $tokens = array(), $default = null)
    {
        return $this->getViewRenderer()->plugin('translate')
            ->__invoke($msg, $tokens, $default);
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
    public function forwardTo($controller, $action, $params = array())
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
     * @param string  $submitElement Name of the post field of the submit button
     * @param boolean $useRecaptcha  Are we using captcha in this situation?
     *
     * @return boolean
     */
    protected function formWasSubmitted($submitElement = 'submit',
        $useRecaptcha = false
    ) {
        // Fail if the expected submission element was missing from the POST:
        // Form was submitted; if CAPTCHA is expected, validate it now.
        return $this->params()->fromPost($submitElement, false)
            && (!$useRecaptcha || $this->recaptcha()->validate());
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
    public function confirm($title, $yesTarget, $noTarget, $messages = array(),
        $extras = array()
    ) {
        return $this->forwardTo(
            'Confirm', 'Confirm',
            array(
                'data' => array(
                    'title' => $title,
                    'confirm' => $yesTarget,
                    'cancel' => $noTarget,
                    'messages' => (array)$messages,
                    'extras' => $extras
                )
            )
        );
    }

    /**
     * Write the session -- this is designed to be called prior to time-consuming
     * AJAX operations.  This should help reduce the odds of a timing-related bug
     * that causes the wrong version of session data to be written to disk (see
     * VUFIND-716 for more details).
     *
     * @return void
     */
    protected function writeSession()
    {
        $this->getServiceLocator()->get('VuFind\SessionManager')->writeClose();
    }

    /**
     * Get the search memory
     *
     * @return \VuFind\Search\Memory
     */
    public function getSearchMemory()
    {
        return $this->getServiceLocator()->get('VuFind\Search\Memory');
    }

    /**
     * Are lists enabled?
     *
     * @return bool
     */
    protected function listsEnabled()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $tagSetting = isset($config->Social->lists) ? $config->Social->lists : true;
        return $tagSetting && $tagSetting !== 'disabled';
    }

    /**
     * Are tags enabled?
     *
     * @return bool
     */
    protected function tagsEnabled()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $tagSetting = isset($config->Social->tags) ? $config->Social->tags : true;
        return $tagSetting && $tagSetting !== 'disabled';
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
        // Get the referer -- if it's empty, there's nothing to store!
        $referer = $this->getRequest()->getServer()->get('HTTP_REFERER');
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

        // If we got this far, we want to store the referer:
        $this->followup()->store(array(), $referer);
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
        $followup = $this->followup()->retrieve();
        // followups aren't used in lightboxes.
        if (isset($followup->url) && !$this->inLightbox()) {
            return $followup->url;
        }
        return '';
    }

    /**
     * Sometimes we need to unset the followup to trigger default behaviors
     *
     * @return void
     */
    protected function clearFollowupUrl()
    {
        $followup = $this->followup()->retrieve();
        if (isset($followup->url)) {
            unset($followup->url);
        }
    }
}
