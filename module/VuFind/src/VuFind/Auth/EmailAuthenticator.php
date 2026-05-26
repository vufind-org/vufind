<?php

/**
 * Class for managing email-based authentication.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use Laminas\Http\Request;
use Laminas\View\Renderer\PhpRenderer;
use OTPHP\HOTP;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Service\AuthHashServiceInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\Net\UserIpReader;
use VuFind\Validator\CsrfInterface;

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
    use EmailSettingsTrait;

    /**
     * How long a login request is considered to be valid (seconds).
     *
     * @var int
     */
    protected $loginRequestValidTime = 600;

    /**
     * Constructor.
     *
     * @param \Laminas\Session\SessionManager $sessionManager  Session Manager
     * @param CsrfInterface                   $csrf            CSRF Validator
     * @param \VuFind\Mailer\Mailer           $mailer          Mailer
     * @param PhpRenderer                     $viewRenderer    View Renderer
     * @param UserIpReader                    $userIpReader    User IP address reader
     * @param \VuFind\Config\Config           $config          Configuration
     * @param AuthHashServiceInterface        $authHashService AuthHash database service
     */
    public function __construct(
        protected \Laminas\Session\SessionManager $sessionManager,
        protected CsrfInterface $csrf,
        protected \VuFind\Mailer\Mailer $mailer,
        protected PhpRenderer $viewRenderer,
        protected UserIpReader $userIpReader,
        protected \VuFind\Config\Config $config,
        protected AuthHashServiceInterface $authHashService
    ) {
    }

    /**
     * Send an email authentication link to the specified email address.
     *
     * Stores the required information in the session.
     *
     * @param string $email          Email address to send the link to
     * @param array  $data           Information from the authentication request (such as user details)
     * @param array  $urlParams      Default parameters for the generated URL
     * @param string $linkRoute      The route to use as the base url for the login link
     * @param array  $routeParams    Route parameters
     * @param string $subject        Email subject
     * @param string $template       Email message template
     * @param array  $templateParams Extra params for rendering the email message
     *
     * @return void
     *
     * @deprecated Use code-based authentication instead
     */
    public function sendAuthenticationLink(
        $email,
        $data,
        $urlParams,
        $linkRoute = 'myresearch-home',
        $routeParams = [],
        $subject = 'email_login_subject',
        $template = 'Email/login-link.phtml',
        $templateParams = []
    ) {
        // Make sure we've waited long enough
        $recoveryInterval = $this->config->Authentication->recover_interval ?? 60;
        $sessionId = $this->sessionManager->getId();

        if (
            ($row = $this->authHashService->getLatestBySessionId($sessionId))
            && time() - $row->getCreated()->getTimestamp() < $recoveryInterval
        ) {
            throw new AuthException('authentication_error_in_progress');
        }

        $this->csrf->trimTokenList(5);
        $linkData = [
            'timestamp' => time(),
            'data' => $data,
            'email' => $email,
            'ip' => $this->userIpReader->getUserIp(),
        ];
        $hash = $this->csrf->getHash(true);

        $row = $this->authHashService->getByHashAndType($hash, AuthHashServiceInterface::TYPE_EMAIL);

        $row->setSessionId($sessionId)
            ->setData(json_encode($linkData));
        $this->authHashService->persistEntity($row);

        $serverHelper = $this->viewRenderer->plugin('serverurl');
        $urlHelper = $this->viewRenderer->plugin('url');
        $urlParams['hash'] = $hash;
        $viewParams = $linkData + $templateParams;
        $viewParams['url'] = $serverHelper(
            $urlHelper($linkRoute, $routeParams, ['query' => $urlParams])
        );
        $viewParams['title'] = $this->config->Site->title;

        $message = $this->viewRenderer->render($template, $viewParams);
        $from = $this->getEmailSenderAddress($this->config, $email);
        $subject = $this->translator->translate($subject);
        $subject = str_replace('%%title%%', $viewParams['title'], $subject);

        $this->mailer->send($email, $from, $subject, $message);
    }

    /**
     * Authenticate using a hash.
     *
     * @param string $hash Hash
     *
     * @return array
     * @throws AuthException
     *
     * @deprecated Use code-based authentication instead
     */
    public function authenticate($hash)
    {
        $row = $this->authHashService->getByHashAndType($hash, AuthHashServiceInterface::TYPE_EMAIL, false);
        if (!$row) {
            // Assume the hash has already been used or has expired
            throw new AuthException('authentication_error_expired');
        }
        $linkData = json_decode($row->getData(), true);

        // Require same session id or IP address:
        $sessionId = $this->sessionManager->getId();
        if (
            $row->getSessionId() !== $sessionId
            && $linkData['ip'] !== $this->userIpReader->getUserIp()
        ) {
            throw new AuthException('authentication_error_session_ip_mismatch');
        }

        // Only delete the token now that we know the requester is correct. Otherwise
        // it may end up deleted due to e.g. safe link check by the email server.
        $this->authHashService->deleteAuthHash($row);

        if (time() - $row->getCreated()->getTimestamp() > $this->loginRequestValidTime) {
            throw new AuthException('authentication_error_expired');
        }

        return $linkData['data'];
    }

    /**
     * Check if the given request is a valid login request.
     *
     * @param Request $request Request object.
     *
     * @return bool
     *
     * @deprecated Use code-based authentication instead
     */
    public function isValidLoginRequest(Request $request)
    {
        $hash = $request->getPost()->get(
            'hash',
            $request->getQuery()->get('hash', '')
        );
        if ($hash) {
            $row = $this->authHashService->getByHashAndType($hash, AuthHashServiceInterface::TYPE_EMAIL, false);
            return !empty($row);
        }
        return false;
    }

    /**
     * Send an email authentication code to the specified email address.
     *
     * @param string $email          Email address to send the link to
     * @param array  $data           Information from the authentication request (such as user details)
     * @param string $subject        Email subject
     * @param string $template       Email message template
     * @param array  $templateParams Extra params for rendering the email message
     *
     * @return int Authentication code ID for subsequent call to verifyAuthenticationCode (not to be exposed to the
     * user!)
     */
    public function sendAuthenticationCode(
        string $email,
        array $data,
        string $subject = 'email_login_subject',
        string $template = 'Email/login-code.phtml',
        $templateParams = []
    ): int {
        // Make sure we've waited long enough
        $recoveryInterval = $this->config->Authentication->recover_interval ?? 60;
        $sessionId = $this->sessionManager->getId();

        if (
            ($row = $this->authHashService->getLatestBySessionId($sessionId))
            && time() - $row->getCreated()->getTimestamp() < $recoveryInterval
        ) {
            throw new AuthException('authentication_error_in_progress');
        }

        $otpObject = HOTP::create();
        $otpObject->setLabel($email);
        $otp = $otpObject->at($otpObject->getCounter());

        // Random bytes just ensure that the hash is unique:
        $hash = $otp . '||' . md5(random_bytes(32)) . '||0';
        $row = $this->authHashService->getByHashAndType($hash, AuthHashServiceInterface::TYPE_OTP);

        $row->setSessionId($sessionId)
            ->setData(json_encode($data));
        $this->authHashService->persistEntity($row);

        $viewParams = $templateParams;
        $viewParams['code'] = $otp;
        $viewParams['title'] = $this->config->Site->title ?? '';

        $message = $this->viewRenderer->render($template, $viewParams);
        $from = $this->getEmailSenderAddress($this->config, $email);
        $subject = $this->translator->translate($subject);
        $subject = str_replace('%%title%%', $viewParams['title'], $subject);

        $this->mailer->send($email, $from, $subject, $message);

        return $row->getId();
    }

    /**
     * Verify an authentication code sent by email.
     *
     * @param int    $id  Authentication code ID (from sendAuthenticationCode method)
     * @param string $otp User-entered one-time password
     *
     * @return ?array Authentication information passed to sendAuthenticationCode on success; null on failure
     */
    public function verifyAuthenticationCode(
        int $id,
        string $otp
    ): ?array {
        // Use a transaction to avoid any concurrency issues:
        $this->authHashService->beginTransaction();
        try {
            if (!($row = $this->authHashService->getById($id))) {
                // Assume the hash has expired
                throw new AuthException('authentication_error_expired');
            }

            if (time() - $row->getCreated()->getTimestamp() > $this->loginRequestValidTime) {
                throw new AuthException('authentication_error_expired');
            }

            // Update attempt count:
            $hashParts = explode('||', $row->getHash());
            $storedOtp = $hashParts[0];
            // Account for existing hashes that don't contain the attempt counter and increase the counter:
            $attempts = ($hashParts[2] ?? 0) + 1;
            $hashParts[2] = $attempts;
            $row->setHash(implode('||', $hashParts));
            $this->authHashService->persistEntity($row);
            $this->authHashService->commitTransaction();
        } catch (\Exception $e) {
            $this->authHashService->rollBackTransaction();
            throw $e;
        }
        // Check the maximum attempt limit:
        $maxAttempts = max($this->config->Authentication->otp_max_attempts ?? 3, 1);
        if ($attempts > $maxAttempts) {
            throw new AuthException('authentication_error_expired');
        }
        // Verify password:
        if ($otp === $storedOtp) {
            // Success; extract data and clean up:
            $authData = json_decode($row->getData(), true);
            $this->authHashService->deleteAuthHash($row);
            return $authData;
        }

        // Failure; keep the hash for retries.
        return null;
    }
}
