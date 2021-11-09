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

use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Table\AuthHash as AuthHashTable;
use VuFind\Exception\Auth as AuthException;
use VuFind\Validator\TokenCountingCsrfInterface;

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
     * @var \Laminas\Session\SessionManager
     */
    protected $sessionManager = null;

    /**
     * CSRF Validator
     *
     * @var TokenCountingCsrfInterface $csrf CSRF validator
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
     * @var \Laminas\View\Renderer\RendererInterface
     */
    protected $viewRenderer = null;

    /**
     * Remote address
     *
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * Configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * How long a login request is considered to be valid (seconds)
     *
     * @var int
     */
    protected $loginRequestValidTime = 600;

    /**
     * Database table for authentication hashes
     *
     * @var AuthHashTable
     */
    protected $authHashTable;

    /**
     * Constructor
     *
     * @param \Laminas\Session\SessionManager          $session      Session Manager
     * @param TokenCountingCsrfInterface               $csrf         CSRF Validator
     * @param \VuFind\Mailer\Mailer                    $mailer       Mailer
     * @param \Laminas\View\Renderer\RendererInterface $viewRenderer View Renderer
     * @param RemoteAddress                            $remoteAddr   Remote address
     * @param \Laminas\Config\Config                   $config       Configuration
     * @param AuthHashTable                            $authHash     AuthHash Table
     */
    public function __construct(
        \Laminas\Session\SessionManager $session,
        TokenCountingCsrfInterface $csrf,
        \VuFind\Mailer\Mailer $mailer,
        \Laminas\View\Renderer\RendererInterface $viewRenderer,
        RemoteAddress $remoteAddr,
        \Laminas\Config\Config $config,
        AuthHashTable $authHash
    ) {
        $this->sessionManager = $session;
        $this->csrf = $csrf;
        $this->mailer = $mailer;
        $this->viewRenderer = $viewRenderer;
        $this->remoteAddress = $remoteAddr;
        $this->config = $config;
        $this->authHashTable = $authHash;
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
    public function sendAuthenticationLink(
        $email,
        $data,
        $urlParams,
        $linkRoute = 'myresearch-home',
        $subject = 'email_login_subject',
        $template = 'Email/login-link.phtml'
    ) {
        // Make sure we've waited long enough
        $recoveryInterval = $this->config->Authentication->recover_interval ?? 60;
        $sessionId = $this->sessionManager->getId();

        if (($row = $this->authHashTable->getLatestBySessionId($sessionId))
            && time() - strtotime($row['created']) < $recoveryInterval
        ) {
            throw new AuthException('authentication_error_in_progress');
        }

        $this->csrf->trimTokenList(5);
        $linkData = [
            'timestamp' => time(),
            'data' => $data,
            'email' => $email,
            'ip' => $this->remoteAddress->getIpAddress()
        ];
        $hash = $this->csrf->getHash(true);

        $row = $this->authHashTable
            ->getByHashAndType($hash, AuthHashTable::TYPE_EMAIL);

        $row['session_id'] = $sessionId;
        $row['data'] = json_encode($linkData);
        $row->save();

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
        $row = $this->authHashTable
            ->getByHashAndType($hash, AuthHashTable::TYPE_EMAIL, false);
        if (!$row) {
            // Assume the hash has already been used or has expired
            throw new AuthException('authentication_error_expired');
        }
        $linkData = json_decode($row['data'], true);

        // Require same session id or IP address:
        $sessionId = $this->sessionManager->getId();
        if ($row['session_id'] !== $sessionId
            && $linkData['ip'] !== $this->remoteAddress->getIpAddress()
        ) {
            throw new AuthException('authentication_error_session_ip_mismatch');
        }

        // Only delete the token now that we know the requester is correct. Otherwise
        // it may end up deleted due to e.g. safe link check by the email server.
        $row->delete();

        if (time() - strtotime($row['created']) > $this->loginRequestValidTime) {
            throw new AuthException('authentication_error_expired');
        }

        return $linkData['data'];
    }

    /**
     * Check if the given request is a valid login request
     *
     * @param Request $request Request object.
     *
     * @return bool
     */
    public function isValidLoginRequest(Request $request)
    {
        $hash = $request->getPost()->get(
            'hash',
            $request->getQuery()->get('hash', '')
        );
        if ($hash) {
            $row = $this->authHashTable
                ->getByHashAndType($hash, AuthHashTable::TYPE_EMAIL, false);
            return !empty($row);
        }
        return false;
    }
}
