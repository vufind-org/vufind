<?php

/**
 * Persistent login token manager
 *
 * PHP version 8
 *
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
 * @package  VuFind\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFind\Auth;

use VuFind\Exception\LoginToken as LoginTokenException;
use Laminas\Session\SessionManager;

/**
 * Class LoginToken
 *
 * @category VuFind
 * @package  VuFind\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoginToken implements \VuFind\I18n\Translator\TranslatorAwareInterface
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
     * @var LoginToken
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
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * LoginToken constructor.
     *
     * @param Config                $config          Configuration
     * @param UserTable             $userTable       User table gateway
     * @param LoginTokenTable       $loginTokenTable Login Token table gateway
     * @param CookieManager         $cookieManager   Cookie manager
     * @param SessionManager        $sessionManager  Session manager
     * @param \VuFind\Mailer\Mailer $mailer          Mailer
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \VuFind\Db\Table\User $userTable,
        \VuFind\Db\Table\LoginToken $loginTokenTable,
        \VuFind\Cookie\CookieManager $cookieManager,
        SessionManager $sessionManager,
        \VuFind\Mailer\Mailer $mailer,
    ) {
        $this->config = $config;
        $this->userTable = $userTable;
        $this->loginTokenTable = $loginTokenTable;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->mailer = $mailer;
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
                    $this->loginTokenTable->deleteBySeries($token->series, $token->user_id);
                    $user = $this->userTable->getById($token->user_id);
                    $this->createToken($user, $token->series, $sessionId, $token->expires);
                }
            } catch (LoginTokenException $e) {
                // Delete all login tokens for the user and all sessions
                // associated with the tokens and send a warning email to user
                $user = $this->userTable->getById($cookie['user_id']);
                $userTokens = $this->loginTokenTable->getByUserId($cookie['user_id']);
                $handler = $this->sessionManager->getSaveHandler();     
                foreach ($userTokens as $t) {
                    $handler->destroy($t->last_session_id);
                }
                $this->loginTokenTable->deleteByUserId($cookie['user_id']);
                $this->sendLoginTokenWarningEmail($user);
                return null;
            }
        }
        return $user;
    }

    /**
     * Create a new login token
     *
     * @param \VuFind\Db\Row\User $user      user
     * @param string              $series    login token series
     * @param string              $sessionId Session identifier
     * @param int                 $expires   Token expiration date
     *
     * @return void 
     */
    public function createToken(\VuFind\Db\Row\User $user, string $series = '', string $sessionId = '', $expires = 0)
    {
        $token = bin2hex(random_bytes(32));
        $series = $series ? $series : bin2hex(random_bytes(32));
        $browser = '';
        $platform = '';
        try {
            $userInfo = get_browser(null, true) ?? [];
            $browser = $userInfo['browser'];
            $platform = $userInfo['platform'];
        } catch (\Exception $e) {
            // Problem with browscap.ini, continue without
            // browser information
        }
        if ($expires === 0) {
            $lifetime = $this->config->Authentication->persistent_login_lifetime ?? 0;
            $expires = time() + $lifetime * 60 * 60 * 24;
        }
        $this->setLoginTokenCookie($user->id, $token, $series, $expires);
        
        $this->loginTokenTable->saveToken($user->id, $token, $series, $browser, $platform, $expires, $sessionId);
    }

    /**
     * Delete a login token by series. Also destroys
     * sessions associated with the login token
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
            $this->cookieManager->clear('loginToken');
        }
        if ($token = $this->loginTokenTable->getBySeries($series, $cookie['user_id'])) {
            $handler = $this->sessionManager->getSaveHandler();
            $handler->destroy($token->last_session_id);
        }
        $this->loginTokenTable->deleteBySeries($series, $cookie['user_id']);
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
        $this->cookieManager->clear('loginToken');
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
        if (!empty($user->email)) {
            $this->mailer->send(
                $user->email,
                $this->config->Site->email,
                $this->translate('login_warning_email_subject'),
                $this->translate('login_warning_email_message')
            );
        }
    }


    /**
     * Set login token cookie
     *
     * @param int    $userId  User identifier
     * @param string $token   Login token
     * @param string $series  Series the token belongs to
     * @param int    $expires Token expiration date
     *
     * @return void
     */
    public function setLoginTokenCookie(int $userId, string $token, string $series, int $expires)
    {
        $token = implode(';', [$series, $userId, $token]);
        $this->cookieManager->set(
            'loginToken',
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
        if ($cookie = $this->cookieManager->get('loginToken')) {
            $parts = explode(';', $cookie);
            $result = [
                'series' => $parts[0] ?? '',
                'user_id' => (int)($parts[1] ?? -1),
                'token' => $parts[2] ?? ''
            ];
        }
        return $result;
    }
}
