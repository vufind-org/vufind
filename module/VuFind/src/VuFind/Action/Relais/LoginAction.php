<?php

/**
 * Relais login action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Relais;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ServiceManager\Factory\Autowire;

use function is_array;

/**
 * Relais login action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LoginAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param array $config VuFind configuration
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Redirect to Relais login.
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
        // Fatal error if not configured correctly:
        $baseUrl = $this->config['Relais']['loginUrl'] ?? null;
        if (!$baseUrl) {
            throw new \Exception('Relais login URL not set.');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        // Send user credentials through to Relais:
        $symbol = $this->config['Relais']['symbol'] ?? '';
        $q = $this->getQueryParam('query');
        $url = $baseUrl . '?LS=' . urlencode($symbol) . '&dest=discovery&group=patron&PI='
            . urlencode($this->getRelaisUserIdentifier($patron));
        if ($q) {
            $url .= '&query=' . rawurlencode($q);
        }
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
    }

    /**
     * Given patron data from the catalogLogin() method, return the appropriate identifier for use with Relais.
     *
     * @param array $patron Patron details
     *
     * @return string
     */
    protected function getRelaisUserIdentifier(array $patron): string
    {
        // By default we assume the cat_username field provides the appropriate username... but if you have a more
        // complex situation at your local institution, you can extend the action and override this method.
        return $patron['cat_username'];
    }
}
