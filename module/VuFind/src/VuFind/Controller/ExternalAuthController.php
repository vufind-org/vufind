<?php

/**
 * External Authentication/Authorization Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;

/**
 * External Authentication/Authorization Controller
 *
 * Provides authorization support for external systems, e.g. EZproxy
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ExternalAuthController extends AbstractBase implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Permission from permissions.ini required for EZProxy authorization.
     *
     * @var string
     */
    protected $ezproxyRequiredPermission = 'ezproxy.authorized';

    /**
     * Provides an EZproxy session to an authorized user
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function ezproxyLoginAction()
    {
        $config = $this->getConfig();
        if (empty($config->EZproxy->host)) {
            throw new \Exception('EZproxy host not defined in configuration');
        }

        $user = $this->getUser();

        $authService = $this->getService(\LmcRbacMvc\Service\AuthorizationService::class);
        if ($authService->isGranted($this->ezproxyRequiredPermission)) {
            // Access granted, redirect to EZproxy
            if (empty($config->EZproxy->disable_ticket_auth_logging)) {
                $logger = $this->getService(\VuFind\Log\Logger::class);
                $logger->info(
                    "EZproxy login to '" . $config->EZproxy->host
                    . "' for '" . ($user ? $user->getUsername() : 'anonymous')
                    . "' from IP address "
                    . $this->request->getServer()->get('REMOTE_ADDR')
                );
            }
            $url = $this->params()->fromPost(
                'url',
                $this->params()->fromQuery('url')
            );
            $username = (!empty($config->EZproxy->anonymous_ticket) || !$user)
                ? 'anonymous' : $user->getUsername();
            return $this->redirect()->toUrl(
                $this->createEzproxyTicketUrl($username, $url)
            );
        }

        if ($user) {
            // User already logged in, inform that the current login does not
            // allow access.
            $view = $this->createViewModel();
            $view->unauthorized = true;
            $this->flashMessenger()->addErrorMessage(
                'external_auth_unauthorized'
            );
            return $view;
        }
        return $this->forceLogin('external_auth_login_message');
    }

    /**
     * Create a ticket login URL for EZproxy
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
        $config = $this->getConfig();
        if (empty($config->EZproxy->secret)) {
            throw new \Exception('EZproxy secret not defined in configuration');
        }

        $packet = '$u' . time() . '$e';
        $hash = new \Laminas\Crypt\Hash();
        $algorithm = !empty($config->EZproxy->secret_hash_method)
            ? $config->EZproxy->secret_hash_method : 'SHA512';
        $ticket = $config->EZproxy->secret . $user . $packet;
        $ticket = $hash->compute($algorithm, $ticket);
        $ticket .= $packet;
        $params = http_build_query(
            ['user' => $user, 'ticket' => $ticket, 'url' => $url]
        );
        return $config->EZproxy->host . "/login?$params";
    }
}
