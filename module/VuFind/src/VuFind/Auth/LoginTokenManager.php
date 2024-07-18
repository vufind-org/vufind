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
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\SessionManager;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\LoginTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\LoginToken as LoginTokenException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Log\LoggerAwareTrait;
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
class LoginTokenManager implements LoggerAwareInterface, TranslatorAwareInterface
{
    use LoggerAwareTrait;
    use TranslatorAwareTrait;

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
     * @var ?UserEntityInterface
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
     * @param Config                     $config            Configuration
     * @param UserServiceInterface       $userService       User database service
     * @param LoginTokenServiceInterface $loginTokenService Login Token database service
     * @param CookieManager              $cookieManager     Cookie manager
     * @param SessionManager             $sessionManager    Session manager
     * @param Mailer                     $mailer            Mailer
     * @param RendererInterface          $viewRenderer      View Renderer
     * @param callable                   $browscapCB        Callback for creating Browscap
     */
    public function __construct(
        protected Config $config,
        protected UserServiceInterface $userService,
        protected LoginTokenServiceInterface $loginTokenService,
        protected CookieManager $cookieManager,
        protected SessionManager $sessionManager,
        protected Mailer $mailer,
        protected RendererInterface $viewRenderer,
        callable $browscapCB
    ) {
        $this->browscapCallback = $browscapCB;
    }

    /**
     * Authenticate user using a login token cookie
     *
     * @param string $sessionId Session identifier
     *
     * @return ?UserEntityInterface Object representing logged-in user.
     */
    public function tokenLogin(string $sessionId): ?UserEntityInterface
    {
        $user = null;
        $cookie = $this->getLoginTokenCookie();
        if ($cookie) {
            try {
                if (
                    ($token = $this->loginTokenService->matchToken($cookie))
                    && ($user = $token->getUser())
                ) {
                    // Queue token update to be done after everything else is
                    // successfully processed:
                    $this->tokenToUpdate = compact('user', 'token', 'sessionId');
                    $this->debug(
                        "Token login successful for user {$user->getId()}"
                        . ", token {$token->getToken()} series {$token->getSeries()}"
                    );
                } else {
                    $this->cookieManager->clear($this->getCookieName());
                }
            } catch (LoginTokenException $e) {
                $this->logError(
                    'Token login failure for user ' . $e->getUserId()
                    . ", token {$cookie['token']} series {$cookie['series']}: " . (string)$e
                );
                // Delete all login tokens for the user and all sessions
                // associated with the tokens and send a warning email to user
                $user = $this->userService->getUserById($e->getUserId());
                if ($user) {
                    $this->deleteUserLoginTokens($user->getId());
                }
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
     * Create a new login token series
     *
     * @param UserEntityInterface $user      User
     * @param string              $sessionId Session identifier
     *
     * @throws AuthException
     * @return void
     */
    public function createToken(UserEntityInterface $user, string $sessionId = ''): void
    {
        $this->createOrRotateToken($user, $sessionId);
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
            $this->createOrRotateToken(
                $this->tokenToUpdate['user'],
                $this->tokenToUpdate['sessionId'],
                $token->getSeries(),
                $token->getExpires(),
                $token->getId()
            );
            $this->tokenToUpdate = null;
        }
    }

    /**
     * Delete a login token by series. Also destroys
     * sessions associated with the login token.
     *
     * @param string $series Series to identify the token
     *
     * @return void
     */
    public function deleteTokenSeries(string $series)
    {
        $cookie = $this->getLoginTokenCookie();
        if (!empty($cookie) && $cookie['series'] === $series) {
            $this->cookieManager->clear($this->getCookieName());
        }
        $handler = $this->sessionManager->getSaveHandler();
        foreach ($this->loginTokenService->getBySeries($series) as $token) {
            $handler->destroy($token->getLastSessionId());
        }
        $this->loginTokenService->deleteBySeries($series);
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
        $userTokens = $this->loginTokenService->getByUser($userId, false);
        $handler = $this->sessionManager->getSaveHandler();
        foreach ($userTokens as $t) {
            $handler->destroy($t->getLastSessionId());
        }
        $this->loginTokenService->deleteByUser($userId);
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
     * Get login token cookie name
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return 'loginToken';
    }

    /**
     * Delete a login token from cookies and database
     *
     * @return void
     */
    public function deleteActiveToken()
    {
        $cookie = $this->getLoginTokenCookie();
        if (!empty($cookie) && $cookie['series']) {
            $this->loginTokenService->deleteBySeries($cookie['series']);
        }
        $this->cookieManager->clear($this->getCookieName());
    }

    /**
     * Create a new login token series or rotate login token in given series
     *
     * @param UserEntityInterface $user           User
     * @param string              $sessionId      Session identifier
     * @param string              $series         Login token series
     * @param ?int                $expires        Token expiration timestamp or null for default
     * @param ?int                $currentTokenId ID of current token to keep intact
     *
     * @throws AuthException
     * @return void
     */
    protected function createOrRotateToken(
        UserEntityInterface $user,
        string $sessionId = '',
        string $series = '',
        ?int $expires = null,
        ?int $currentTokenId = null
    ): void {
        try {
            $browser = $this->getBrowscap()->getBrowser();
        } catch (\Exception $e) {
            throw new AuthException('Problem with browscap: ' . (string)$e);
        }
        if (null === $expires) {
            $lifetime = $this->getCookieLifetime();
            $expires = time() + $lifetime * 60 * 60 * 24;
        }
        $token = bin2hex(random_bytes(32));
        $userId = $user->getId();
        try {
            if ($series) {
                $lenient = ($this->config->Authentication->lenient_token_rotation ?? true);
                $this->loginTokenService->deleteBySeries($series, $lenient ? $currentTokenId : null);
                $this->debug("Updating login token $token series $series for user {$userId}");
            } else {
                $series = bin2hex(random_bytes(32));
                $this->debug("Creating login token $token series $series for user {$userId}");
            }
            $this->loginTokenService->createAndPersistToken(
                $user,
                $token,
                $series,
                $browser->browser,
                $browser->platform,
                $expires,
                $sessionId
            );
            $this->setLoginTokenCookie($token, $series, $expires);
        } catch (\Exception $e) {
            $this->logError("Failed to save login token $token series $series for user {$userId}: " . (string)$e);
            throw new AuthException('Failed to save token');
        }
    }

    /**
     * Send email warning to user
     *
     * @param UserEntityInterface $user User
     *
     * @return void
     */
    protected function sendLoginTokenWarningEmail(UserEntityInterface $user)
    {
        if (!($this->config->Authentication->send_login_warnings ?? true)) {
            return;
        }
        $title = $this->config->Site->title ?? '';
        if ($toAddr = $user->getEmail()) {
            $message = $this->viewRenderer->render(
                'Email/login-warning.phtml',
                compact('title')
            );
            $subject = $this->config->Authentication->persistent_login_warning_email_subject
                ?? 'persistent_login_warning_email_subject';

            try {
                $this->mailer->send(
                    $toAddr,
                    $this->config->Mail->default_from ?? $this->config->Site->email,
                    $this->translate($subject, ['%%title%%' => $title]),
                    $message
                );
            } catch (\Exception $e) {
                $this->logError('Failed to send login token warning email: ' . (string)$e);
            }
        }
    }

    /**
     * Set login token cookie
     *
     * @param string $token   Login token
     * @param string $series  Series the token belongs to
     * @param int    $expires Token expiration timestamp
     *
     * @return void
     */
    protected function setLoginTokenCookie(string $token, string $series, int $expires): void
    {
        $token = implode(';', [$series, $token]);
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
    protected function getLoginTokenCookie(): array
    {
        if ($cookie = $this->cookieManager->get($this->getCookieName())) {
            $parts = explode(';', $cookie);
            // Account for tokens that have extra content in the middle:
            if ($part2 = $parts[2] ?? null) {
                return [
                    'series' => $parts[0],
                    'token' => $part2,
                ];
            }
            return [
                'series' => $parts[0],
                'token' => $parts[1] ?? '',
            ];
        }
        return [];
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
