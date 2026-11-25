<?php

/**
 * DevTools permissions check action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindDevTools\Action\DevTools;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Role\PermissionManager;
use VuFind\Role\PermissionProvider\PluginManager as PermissionProviderPluginManager;
use VuFind\Role\PermissionProvider\SessionKey;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * DevTools permissions check action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PermissionsAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param PermissionProviderPluginManager $permissionProviderPluginManager Permission provider plugin manager
     * @param PermissionManager               $permissionManager               Permission manager
     */
    #[Autowire]
    public function __construct(
        protected PermissionProviderPluginManager $permissionProviderPluginManager,
        protected PermissionManager $permissionManager,
    ) {
        parent::__construct();
    }

    /**
     * Display permissions.
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
        // Handle demo session key setting/unsetting:
        $set = $this->getQueryParam('setSessionKey');
        $unset = $this->getQueryParam('unsetSessionKey');
        if ($set || $unset) {
            $provider = $this->permissionProviderPluginManager->get(SessionKey::class);
            $method = $set ? 'setSessionValue' : 'unsetSessionValue';
            $provider->$method('demo_key');
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'devtools-permissions');
        }

        // Retrieve full permission list:
        $permissions = [];
        foreach ($this->permissionManager->getAllConfiguredPermissions() as $permission) {
            $permissions[$permission] = $this->permissionManager->isAuthorized($permission);
        }
        ksort($permissions);
        return $this->renderTemplate($request, $response, compact('permissions'));
    }
}
