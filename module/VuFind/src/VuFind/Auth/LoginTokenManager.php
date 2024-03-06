<?php

/**
 * Persistent login token manager
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  VuFind\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFind\Auth;

use BrowscapPHP\BrowscapInterface;
use Laminas\Config\Config;
use Laminas\Session\SessionManager;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Row\User;
use VuFind\Db\Table\LoginToken as LoginTokenTable;
use VuFind\Db\Table\User as UserTable;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\LoginToken as LoginTokenException;
use VuFind\Mailer\Mailer;

/**
 * Class LoginTokenManager
 *
 * @category VuFind
 * @package  VuFind\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoginTokenManager implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * User table gateway
     *
     * @var UserTable
     */
    protected $userTable;

    /**
     * Login token table gateway
     *
     * @var LoginTokenTable
     */
    protected $loginTokenTable;

    /**
     * Cookie Manager
     *
     * @var CookieManager
     */
    protected $cookieManager;

    /**
     * Mailer
     *
     * @var Mailer
     */
    protected $mailer;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * View Renderer
     *
     * @var RendererInterface
     */
    protected $viewRenderer = null;

    /**
     * Callback for creating Browscap so that we can defer the cache access to when
     * it's actually needed.
     *
     * @var callable
     */
    protected $browscapCallback;

    /**
     * Browscap
     *
     * @var BrowscapInterface
     */
    protected $browscap = null;

    /**
     * Has the theme been initialized yet?
     *
     * @var bool
     */
    protected $themeInitialized = false;

    /**
     * User that needs to receive a warning (or null for no warning needed)
     *
     * @var ?User
     */
    protected $userToWarn = null;

    /**
     * Token data for deferred token update
     *
     * @var ?array
     */
    protected $tokenToUpdate = null;

    /**
     * LoginToken constructor.
     *
     * @param Config            $config          Configuration
     * @param UserTable         $userTable       User table gateway
     * @param LoginTokenTable   $loginTokenTable Login Token table gateway
     * @param CookieManager     $cookieManager   Cookie manager
     * @param SessionManager    $sessionManager  Session manager
     * @param Mailer            $mailer          Mailer
     * @param RendererInterface $viewRenderer    View Renderer
     * @param callable          $browscapCB      Callback for creating Browscap
     */
    public function __construct(
        Config $config,
        UserTable $userTable,
        LoginTokenTable $loginTokenTable,
        CookieManager $cookieManager,
        SessionManager $sessionManager,
        Mailer $mailer,
        RendererInterface $viewRenderer,
        callable $browscapCB
    ) {
        $this->config = $config;
        $this->userTable = $userTable;
        $this->loginTokenTable = $loginTokenTable;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->mailer = $mailer;
        $this->viewRenderer = $viewRenderer;
        $this->browscapCallback = $browscapCB;
    }

    /**
     * Authenticate user using a login token cookie
     *
     * @param string $sessionId Session identifier
     *
     * @return \VuFind\Db\Row\UserRow Object representing logged-in user.
     */
    public function tokenLogin(string $sessionId): ?\VuFind\Db\Row\User
    {
        $cookie = $this->getLoginTokenCookie();
        $user = null;
        if ($cookie) {
            try {
                if ($token = $this->loginTokenTable->matchToken($cookie)) {
                    // Queue token update to be done after everything else is
                    // successfully processed:
                    $user = $this->userTable->getById($token->user_id);
                    $this->tokenToUpdate = compact('user', 'token', 'sessionId');
                }
            } catch (LoginTokenException $e) {
                // Delete all login tokens for the user and all sessions
                // associated with the tokens and send a warning email to user
                $user = $this->userTable->getById($cookie['user_id']);
                $this->deleteUserLoginTokens($user->id);
                // We can't send an email until after the theme has initialized;
                // if it's not ready yet, save the user for later.
                if ($this->themeInitialized) {
                    $this->sendLoginTokenWarningEmail($user);
                } else {
                    $this->userToWarn = $user;
                }
                return null;
            }
        }
        return $user;
    }

    /**
     * Event hook -- called after the theme has initialized.
     *
     * @return void
     */
    public function themeIsReady(): void
    {
        $this->themeInitialized = true;
        // If we have queued a user warning, we can send it now!
        if ($this->userToWarn) {
            $this->sendLoginTokenWarningEmail($this->userToWarn);
            $this->userToWarn = null;
        }
    }

    /**
     * Event hook -- called after the request has been processed.
     *
     * @return void
     */
    public function requestIsFinished(): void
    {
        // If we have queued a login token update, we can process it now!
        if ($this->tokenToUpdate) {
            $token = $this->tokenToUpdate['token'];
            $this->loginTokenTable->deleteBySeries($token->series, $token->user_id);
            $this->createToken(
                $this->tokenToUpdate['user'],
                $token->series,
                $this->tokenToUpdate['sessionId'],
                $token->expires
            );
            $this->tokenToUpdate = null;
        }
    }

    /**
     * Create a new login token
     *
     * @param \VuFind\Db\Row\User $user      user
     * @param string              $series    login token series
     * @param string              $sessionId Session identifier
     * @param int                 $expires   Token expiration timestamp
     *
     * @throws AuthException
     * @return void
     */
    public function createToken(\VuFind\Db\Row\User $user, string $series = '', string $sessionId = '', $expires = 0)
    {
        $token = bin2hex(random_bytes(32));
        $series = $series ?: bin2hex(random_bytes(32));
        try {
            $browser = $this->getBrowscap()->getBrowser();
        } catch (\Exception $e) {
            throw new AuthException('Problem with browscap: ' . (string)$e);
        }
        if ($expires === 0) {
            $lifetime = $this->getCookieLifetime();
            $expires = time() + $lifetime * 60 * 60 * 24;
        }
        try {
            $this->loginTokenTable->saveToken(
                $user->id,
                $token,
                $series,
                $browser->browser,
                $browser->platform,
                $expires,
                $sessionId
            );
            $this->setLoginTokenCookie($user->id, $token, $series, $expires);
        } catch (\Exception $e) {
            throw new AuthException('Failed to save token');
        }
    }

    /**
     * Delete a login token by series. Also destroys
     * sessions associated with the login token.
     *
     * @param string $series Series to identify the token
     * @param string $userId User identifier
     *
     * @return void
     */
    public function deleteTokenSeries(string $series, int $userId)
    {
        $cookie = $this->getLoginTokenCookie();
        if (!empty($cookie) && $cookie['series'] === $series) {
            $this->cookieManager->clear($this->getCookieName());
        }
        if ($token = $this->loginTokenTable->getBySeries($series, $cookie['user_id'])) {
            $handler = $this->sessionManager->getSaveHandler();
            $handler->destroy($token->last_session_id);
        }
        $this->loginTokenTable->deleteBySeries($series, $cookie['user_id']);
    }

    /**
     * Delete all login tokens for a user. Also destroys
     * sessions associated with the tokens.
     *
     * @param int $userId User identifier
     *
     * @return void
     */
    public function deleteUserLoginTokens($userId)
    {
        $userTokens = $this->loginTokenTable->getByUserId($userId);
        $handler = $this->sessionManager->getSaveHandler();
        foreach ($userTokens as $t) {
            $handler->destroy($t->last_session_id);
        }
        $this->loginTokenTable->deleteByUserId($userId);
    }

    /**
     * Delete a login token from cookies and database
     *
     * @return void
     */
    public function deleteActiveToken()
    {
        $cookie = $this->getLoginTokenCookie();
        if (!empty($cookie) && $cookie['series'] && $cookie['user_id']) {
            $this->loginTokenTable->deleteBySeries($cookie['series'], $cookie['user_id']);
        }
        $this->cookieManager->clear($this->getCookieName());
    }

    /**
     * Send email warning to user
     *
     * @param User $user User
     *
     * @return void
     */
    public function sendLoginTokenWarningEmail(\VuFind\Db\Row\User $user)
    {
        if (!($this->config->Authentication->send_login_warnings ?? true)) {
            return;
        }
        $title = $this->config->Site->title ?? '';
        if (!empty($user->email)) {
            $message = $this->viewRenderer->render(
                'Email/login-warning.phtml',
                compact('title')
            );
            $subject = $this->config->Authentication->persistent_login_warning_email_subject
                ?? 'persistent_login_warning_email_subject';

            $this->mailer->send(
                $user->email,
                $this->config->Mail->default_from ?? $this->config->Site->email,
                $this->translate($subject, ['%%title%%' => $title]),
                $message
            );
        }
    }

    /**
     * Set login token cookie
     *
     * @param int    $userId  User identifier
     * @param string $token   Login token
     * @param string $series  Series the token belongs to
     * @param int    $expires Token expiration timestamp
     *
     * @return void
     */
    public function setLoginTokenCookie(int $userId, string $token, string $series, int $expires)
    {
        $token = implode(';', [$series, $userId, $token]);
        $this->cookieManager->set(
            $this->getCookieName(),
            $token,
            $expires,
            true
        );
    }

    /**
     * Get login token cookie in array format
     *
     * @return array
     */
    public function getLoginTokenCookie(): array
    {
        $result = [];
        if ($cookie = $this->cookieManager->get($this->getCookieName())) {
            $parts = explode(';', $cookie);
            $result = [
                'series' => $parts[0] ?? '',
                'user_id' => (int)($parts[1] ?? -1),
                'token' => $parts[2] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Get login token cookie name
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return 'loginToken';
    }

    /**
     * Get login token cookie lifetime (days)
     *
     * @return int
     */
    public function getCookieLifetime(): int
    {
        return (int)($this->config->Authentication->persistent_login_lifetime ?? 14);
    }

    /**
     * Get Browscap
     *
     * @return BrowscapInterface
     */
    protected function getBrowscap(): BrowscapInterface
    {
        if (null === $this->browscap) {
            $this->browscap = ($this->browscapCallback)();
        }
        return $this->browscap;
    }
}
