<?php
/**
 * Class for managing email-based authentication.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace VuFind\Auth;

use VuFind\Exception\Auth as AuthException;

/**
 * Class for managing email-based authentication.
 *
 * This class provides functionality for authentication based on a known-valid email
 * address.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class EmailAuthenticator implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Session Manager
     *
     * @var \Zend\Session\SessionManager
     */
    protected $sessionManager = null;

    /**
     * CSRF Validator
     *
     * @var \VuFind\Validator\Csrf $csrf CSRF validator
     */
    protected $csrf = null;

    /**
     * Mailer
     *
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer = null;

    /**
     * View Renderer
     *
     * @var \Zend\View\Renderer\RendererInterface
     */
    protected $viewRenderer = null;

    /**
     * Request
     *
     * @var \Zend\Stdlib\RequestInterface
     */
    protected $request;

    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * How long a login request is considered to be valid (seconds)
     *
     * @var int
     */
    protected $loginRequestValidTime = 600;

    /**
     * Constructor
     *
     * @param \Zend\Session\SessionManager          $session      Session Manager
     * @param \VuFind\Validator\Csrf                $csrf         CSRF Validator
     * @param \VuFind\Mailer\Mailer                 $mailer       Mailer
     * @param \Zend\View\Renderer\RendererInterface $viewRenderer View Renderer
     * @param \Zend\Stdlib\RequestInterface         $request      Request
     * @param \Zend\Config\Config                   $config       Configuration
     */
    public function __construct(\Zend\Session\SessionManager $session,
        \VuFind\Validator\Csrf $csrf, \VuFind\Mailer\Mailer $mailer,
        \Zend\View\Renderer\RendererInterface $viewRenderer,
        \Zend\Stdlib\RequestInterface $request,
        \Zend\Config\Config $config
    ) {
        $this->sessionManager = $session;
        $this->csrf = $csrf;
        $this->mailer = $mailer;
        $this->viewRenderer = $viewRenderer;
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * Send an email authentication link to the specified email address.
     *
     * Stores the required information in the session.
     *
     * @param string $email     Email address to send the link to
     * @param array  $data      Information from the authentication request (such as
     * user details)
     * @param array  $urlParams Default parameters for the generated URL
     * @param string $linkRoute The route to use as the base url for the login link
     * @param string $subject   Email subject
     * @param string $template  Email message template
     *
     * @return void
     */
    public function sendAuthenticationLink($email, $data,
        $urlParams, $linkRoute = 'myresearch-home',
        $subject = 'email_login_subject',
        $template = 'Email/login-link.phtml'
    ) {
        $sessionContainer = $this->getSessionContainer();

        // Make sure we've waited long enough
        $recoveryInterval = isset($this->config->Authentication->recover_interval)
            ? $this->config->Authentication->recover_interval
            : 60;
        if (null !== $sessionContainer->timestamp
            && time() - $sessionContainer->timestamp < $recoveryInterval
        ) {
            throw new AuthException('authentication_error_in_progress');
        }

        $this->csrf->trimTokenList(5);
        $linkData = [
            'timestamp' => time(),
            'data' => $data,
            'email' => $email
        ];
        $hash = $this->csrf->getHash(true);

        if (!isset($sessionContainer->requests)) {
            $sessionContainer->requests = [];
        }
        $sessionContainer->requests[$hash] = $linkData;

        $serverHelper = $this->viewRenderer->plugin('serverurl');
        $urlHelper = $this->viewRenderer->plugin('url');
        $urlParams['hash'] = $hash;
        $viewParams = $linkData;
        $viewParams['url'] = $serverHelper(
            $urlHelper($linkRoute, [], ['query' => $urlParams])
        );
        $viewParams['title'] = $this->config->Site->title;

        $message = $this->viewRenderer->render($template, $viewParams);
        $from = !empty($this->config->Mail->user_email_in_from)
            ? $email
            : ($this->config->Mail->default_from ?? $this->config->Site->email);
        $subject = $this->translator->translate($subject);
        $subject = str_replace('%%title%%', $viewParams['title'], $subject);

        $this->mailer->send($email, $from, $subject, $message);
    }

    /**
     * Authenticate using a hash
     *
     * @param string $hash Hash
     *
     * @return array
     * @throws AuthException
     */
    public function authenticate($hash)
    {
        $sessionContainer = $this->getSessionContainer();

        if (!isset($sessionContainer->requests[$hash])) {
            throw new AuthException('authentication_error_denied');
        }
        $linkData = $sessionContainer->requests[$hash];
        unset($sessionContainer->requests[$hash]);
        if (time() - $linkData['timestamp'] > $this->loginRequestValidTime) {
            throw new AuthException('authentication_error_denied');
        }

        return $linkData['data'];
    }

    /**
     * Check if the given request is a valid login request
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object.
     *
     * @return bool
     */
    public function isValidLoginRequest(\Zend\Http\PhpEnvironment\Request $request)
    {
        $hash = $request->getPost()->get(
            'hash',
            $request->getQuery()->get('hash', '')
        );
        if ($hash) {
            $sessionContainer = $this->getSessionContainer();
            return isset($sessionContainer->requests[$hash]);
        }
        return false;
    }

    /**
     * Get the session container
     *
     * @return \Zend\Session\Container
     */
    protected function getSessionContainer()
    {
        return new \Zend\Session\Container('EmailAuth', $this->sessionManager);
    }
}
