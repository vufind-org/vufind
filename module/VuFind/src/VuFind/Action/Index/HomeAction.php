<?php

/**
 * Default home page action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFind\Action\Index;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\Action\PluginManager as ActionPluginManager;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\ServiceManager\Factory\Autowire;

use function count;

/**
 * Default home page action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param AuthManager         $authManager         Authentication manager
     * @param ActionPluginManager $actionPluginManager Action plugin manager
     * @param array               $config              VuFind configuration
     * @param array               $appConfig           Application configuration
     */
    public function __construct(
        protected AuthManager $authManager,
        protected ActionPluginManager $actionPluginManager,
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(service: 'Config')]
        protected array $appConfig,
    ) {
        parent::__construct();
    }

    /**
     * Display configured default action.
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
        // Load different configurations depending on whether we're logged in or not:
        if ($this->authManager->getIdentity()) {
            $category = $this->config['Site']['defaultLoggedInModule'] ?? 'account';
            $actionConfig = 'defaultLoggedInAction';
        } else {
            $category = $this->config['Site']['defaultModule'] ?? 'search';
            $actionConfig = 'defaultAction';
        }
        $action = $this->config['Site'][$actionConfig] ?? 'home';

        // Apply any action redirect right away:
        $redirects = $this->appConfig['vufind']['action_redirects'] ?? [];
        $key = strtolower($category) . '/' . strtolower($action);
        if ($routeName = $redirects[$key] ?? null) {
            // This relies on being able to convert route name to action name, so check for that and fail quietly
            // if the action is not available:
            $parts = explode('-', $routeName, 2);
            if (count($parts) === 2) {
                if ($this->actionPluginManager->has(implode('/', $parts))) {
                    $category = $parts[0];
                    $action = $parts[1];
                }
            }
        }

        // Forward to the appropriate action:
        return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, "$category/$action");
    }
}
