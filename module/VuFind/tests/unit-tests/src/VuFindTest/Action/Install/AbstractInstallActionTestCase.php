<?php

/**
 * Base class for Install action tests.
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

namespace VuFindTest\Action\Install;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Action\Install\AbstractInstallAction;
use VuFind\ActionHelper\HelperInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\PathResolver;
use VuFind\Http\RouteHelper;
use VuFindTest\Action\AbstractActionTestCase;
use VuFindTest\Feature\AutowireTrait;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Base class for Install action tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractInstallActionTestCase extends AbstractActionTestCase
{
    use AutowireTrait;
    use ConfigRelatedServicesTrait;
    use ReflectionTrait;

    /**
     * Build an Install action with autowired dependencies and optional service overrides.
     *
     * @param class-string      $class       Action class to build
     * @param array             $services    Constructor dependencies to override, keyed by class name
     * @param array             $config      VuFind configuration
     * @param HelperInterface[] $helpers     Extra action helpers keyed by class name
     * @param ?RouteHelper      $routeHelper Route helper (defaults to a stub)
     *
     * @return AbstractInstallAction
     */
    protected function buildAction(
        string $class,
        array $services = [],
        array $config = [],
        array $helpers = [],
        ?RouteHelper $routeHelper = null
    ): AbstractInstallAction {
        $services[ConfigManagerInterface::class] ??= $this->getMockConfigManager(compact('config'));
        $services[PathResolver::class] ??= $this->getDefaultPathResolver();
        $action = $this->getAutowiredObject($class, $services);
        $this->initializeAction($action, $helpers, $routeHelper, $this->getCapturingRenderer());
        return $action;
    }

    /**
     * Get a path resolver whose base and forced-local config location getters return empty-path locations, so config
     * path helpers resolve without touching the filesystem.
     *
     * @return PathResolver
     */
    protected function getDefaultPathResolver(): PathResolver
    {
        $location = $this->createMock(ConfigLocationInterface::class);
        $location->method('getPath')->willReturn('');
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('getBaseConfigLocation')->willReturn($location);
        $pathResolver->method('getForcedLocalConfigLocation')->willReturn($location);
        return $pathResolver;
    }

    /**
     * Build a partial Install action with stubbed methods and wired dependencies
     * for testing environment-dependent action() branches.
     *
     * @param class-string<AbstractInstallAction> $class       Action class to build
     * @param string[]                            $methods     Protected/public methods to stub on the action
     * @param array                               $config      VuFind configuration
     * @param HelperInterface[]                   $helpers     Extra action helpers keyed by class name
     * @param ?RouteHelper                        $routeHelper Route helper (defaults to a stub)
     *
     * @return AbstractInstallAction&MockObject
     */
    protected function buildActionMock(
        string $class,
        array $methods,
        array $config = [],
        array $helpers = [],
        ?RouteHelper $routeHelper = null
    ): MockObject {
        $action = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $this->setProperty($action, 'config', $config);
        $this->initializeAction($action, $helpers, $routeHelper, $this->getCapturingRenderer());
        return $action;
    }
}
