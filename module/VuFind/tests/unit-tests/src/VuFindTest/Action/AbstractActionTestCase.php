<?php

/**
 * Base class for Action test cases.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Action;

use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\HelperInterface;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Http\RouteHelper;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Base class for Action test cases.
 *
 * Provides the setter injection an action normally receives from ActionInitializer.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractActionTestCase extends TestCase
{
    /**
     * Wire the setter-injected dependencies onto an action.
     *
     * @param AbstractAction             $action      Action to initialize
     * @param HelperInterface[]          $helpers     Extra action helpers keyed by class name
     * @param ?RouteHelper               $routeHelper Route helper (defaults to a stub)
     * @param ?TemplateRendererInterface $renderer    Template renderer for template-rendering actions (defaults to a
     * stub)
     *
     * @return AbstractAction
     */
    protected function initializeAction(
        AbstractAction $action,
        array $helpers = [],
        ?RouteHelper $routeHelper = null,
        ?TemplateRendererInterface $renderer = null
    ): AbstractAction {
        $action->setHelperPluginManager($this->getHelperPluginManager($helpers));
        $action->setRouteHelper($routeHelper ?? $this->createStub(RouteHelper::class));
        $action->setSessionSettings($this->createStub(SessionSettings::class));
        if ($action instanceof AbstractTemplateRenderingAction) {
            $action->setTemplateRenderer($renderer ?? $this->createStub(TemplateRendererInterface::class));
        }
        return $action;
    }

    /**
     * Get a helper plugin manager returning the provided helpers, plus a permissive access-granting PermissionHelper.
     *
     * @param HelperInterface[] $helpers Extra helpers keyed by class name
     *
     * @return HelperPluginManager
     */
    protected function getHelperPluginManager(array $helpers = []): HelperPluginManager
    {
        if (!isset($helpers[PermissionHelper::class])) {
            $permissionHelper = $this->createMock(PermissionHelper::class);
            $permissionHelper->method('getPermissionBehaviorConfig')->willReturn([]);
            $helpers[PermissionHelper::class] = $permissionHelper;
        }
        $manager = $this->createMock(HelperPluginManager::class);
        $manager->method('get')->willReturnCallback(
            fn ($name) => $helpers[$name] ?? throw new \Exception("Unexpected helper requested: $name")
        );
        return $manager;
    }

    /**
     * Build a PSR-7 server request with the given route match parameters, query parameters and parsed body.
     *
     * @param array   $routeParams      Route match parameters
     * @param array   $queryParams      Query parameters
     * @param array   $parsedBody       Parsed request body
     * @param ?string $matchedRouteName Matched route name to set on the route match
     *
     * @return ServerRequestInterface
     */
    protected function getServerRequest(
        array $routeParams = [],
        array $queryParams = [],
        array $parsedBody = [],
        ?string $matchedRouteName = null
    ): ServerRequestInterface {
        $routeMatch = new RouteMatch($routeParams);
        if (null !== $matchedRouteName) {
            $routeMatch->setMatchedRouteName($matchedRouteName);
        }
        return (new ServerRequest())
            ->withQueryParams($queryParams)
            ->withParsedBody($parsedBody)
            ->withAttribute('route-match', $routeMatch);
    }
}
