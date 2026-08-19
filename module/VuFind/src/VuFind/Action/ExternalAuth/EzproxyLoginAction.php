<?php

/**
 * EZproxy login action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\ExternalAuth;

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * EZproxy login action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EzproxyLoginAction extends AbstractTemplateRenderingAction
{
    /**
     * Permission from permissions.ini required for EZProxy authorization.
     *
     * @var string
     */
    protected string $ezproxyRequiredPermission = 'ezproxy.authorized';

    /**
     * Constructor.
     *
     * @param AuthManager          $authManager          Authentication manager
     * @param AuthorizationService $authorizationService Authorization service
     * @param array                $config               VuFind configuration
     */
    public function __construct(
        protected AuthManager $authManager,
        protected AuthorizationService $authorizationService,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Login to EZproxy using an authorization ticket.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $ezproxyConfig = $this->config['EZproxy'] ?? [];
        if (!($ezproxyHost = $ezproxyConfig['host'] ?? null)) {
            throw new \Exception('EZproxy host not defined in configuration');
        }

        $user = $this->authManager->getUserObject();

        if ($this->authorizationService->isGranted($this->ezproxyRequiredPermission)) {
            // Access granted, redirect to EZproxy
            if (!($ezproxyConfig['disable_ticket_auth_logging'] ?? false)) {
                $this->log(
                    LogLevel::INFO,
                    "EZproxy login to '" . $ezproxyHost . "' for '" . ($user ? $user->getUsername() : 'anonymous')
                    . "' from IP address " . $request->getServerParams()['REMOTE_ADDR'] ?? '<unknown>',
                    prependClass: true
                );
            }
            $url = $this->getPostOrQueryParam('url');
            $username = (($ezproxyConfig['anonymous_ticket'] ?? false) || !$user)
                ? 'anonymous'
                : $user->getUsername();
            return $this->getHelper(RedirectHelper::class)
                ->redirectToUrl($response, $this->createEzproxyTicketUrl($username, $url));
        }

        if ($user) {
            // User already logged in; inform that the current login does not allow access.
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('external_auth_unauthorized');
            return $this->renderTemplate($request, $response, ['unauthorized' => true]);
        }
        return $this->getHelper(LoginHelper::class)->forceLogin($request, $response, 'external_auth_login_message');
    }

    /**
     * Create a ticket login URL for EZproxy.
     *
     * @param string $user User name to pass on to EZproxy
     * @param string $url  The original URL
     *
     * @return string EZproxy URL
     *
     * @throws \Exception
     * @see    https://www.oclc.org/support/services/ezproxy/documentation/usr
     * /ticket/php.en.html
     */
    protected function createEzproxyTicketUrl($user, $url)
    {
        $ezproxyConfig = $this->config['EZproxy'] ?? [];
        if (!($secret = $ezproxyConfig['secret'] ?? null)) {
            throw new \Exception('EZproxy secret not defined in configuration');
        }

        $packet = '$u' . time() . '$e';
        $algorithm = ($ezproxyConfig['secret_hash_method'] ?? null) ?: 'SHA512';
        $ticket = $secret . $user . $packet;
        $ticket = hash($algorithm, $ticket);
        $ticket .= $packet;
        $params = http_build_query(
            ['user' => $user, 'ticket' => $ticket, 'url' => $url]
        );
        return $ezproxyConfig['host'] . "/login?$params";
    }
}
