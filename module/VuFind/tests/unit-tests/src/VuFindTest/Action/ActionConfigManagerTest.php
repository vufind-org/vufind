<?php

/**
 * ActionConfigManager test class.
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

use Laminas\Router\RouteMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VuFind\Action\AccessPermissionInterface;
use VuFind\Action\ActionConfigInterface;
use VuFind\Action\ActionConfigManager;
use VuFind\Action\ActionInterface;
use VuFind\Action\BackendIdInterface;
use VuFind\Action\CheckEnabledInterface;
use VuFind\Action\DefaultTabInterface;
use VuFind\Exception\ConfigException;
use VuFind\View\GlobalsContainer;

/**
 * ActionConfigManager test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ActionConfigManagerTest extends TestCase
{
    /**
     * Build an ActionConfigManager with the given action_config entries and VuFind configuration.
     *
     * @param array             $actionConfig Entries for appConfig['vufind']['action_config']
     * @param array             $config       VuFind configuration
     * @param ?GlobalsContainer $globals      Globals container (defaults to a stub)
     *
     * @return ActionConfigManager
     */
    protected function buildManager(
        array $actionConfig = [],
        array $config = [],
        ?GlobalsContainer $globals = null
    ): ActionConfigManager {
        return new ActionConfigManager(
            $globals ?? $this->createStub(GlobalsContainer::class),
            $config,
            ['vufind' => ['action_config' => $actionConfig]]
        );
    }

    /**
     * Get a mock action implementing ActionInterface plus the given configuration interface(s).
     *
     * @param string ...$interfaces Additional interfaces to implement
     *
     * @return ActionInterface&MockObject
     */
    protected function getActionMock(string ...$interfaces): MockObject
    {
        return $this->createMockForIntersectionOfInterfaces([ActionInterface::class, ...$interfaces]);
    }

    /**
     * Test that nothing happens when neither a route match nor an explicit identifier is supplied.
     *
     * @return void
     */
    public function testDoesNothingWithoutRouteMatchOrIdentifier(): void
    {
        $action = $this->getActionMock(BackendIdInterface::class);
        $action->expects($this->never())->method('setBackendId');
        $manager = $this->buildManager([['actionIds' => ['x'], 'backendId' => 'Solr']]);
        $manager->applyActionConfig($action);
    }

    /**
     * Test that nothing is applied when the action is not configurable (does not implement ActionConfigInterface).
     *
     * @return void
     */
    public function testDoesNothingWhenActionNotConfigurable(): void
    {
        $globals = new GlobalsContainer();
        $action = $this->createMock(ActionInterface::class);
        $manager = $this->buildManager([['actionIds' => ['x'], 'poweredBy' => 'EDS']], globals: $globals);
        $manager->applyActionConfig($action, null, 'x');
        $this->assertNull($globals['poweredBy']);
    }

    /**
     * Data provider for testResolvesActionIdentifierFromRouteMatch().
     *
     * @return \Iterator
     */
    public static function identifierResolutionProvider(): \Iterator
    {
        yield 'category and action' => [['action' => 'bar', 'category' => 'foo'], null, 'foo/bar'];

        yield 'controller and action' => [['action' => 'bar', 'controller' => 'foo'], null, 'foo/bar'];

        yield 'action only' => [['action' => 'bar'], null, 'bar'];

        yield 'route name fallback' => [[], 'home', 'home'];
    }

    /**
     * Test that the action identifier is derived from the route match when no explicit identifier is given.
     *
     * @param array   $routeParams Route match parameters
     * @param ?string $routeName   Matched route name (or null)
     * @param string  $expectedId  Action identifier the config should match against
     *
     * @return void
     */
    #[DataProvider('identifierResolutionProvider')]
    public function testResolvesActionIdentifierFromRouteMatch(
        array $routeParams,
        ?string $routeName,
        string $expectedId
    ): void {
        $globals = new GlobalsContainer();
        $action = $this->getActionMock(ActionConfigInterface::class);
        $routeMatch = new RouteMatch($routeParams);
        if (null !== $routeName) {
            $routeMatch->setMatchedRouteName($routeName);
        }
        $manager = $this->buildManager([['actionIds' => [$expectedId], 'poweredBy' => 'EDS']], globals: $globals);

        $manager->applyActionConfig($action, $routeMatch);

        $this->assertSame('EDS', $globals['poweredBy']);
    }

    /**
     * Data provider for testAppliesConfigKeyToSetter().
     *
     * @return \Iterator
     */
    public static function configKeySetterProvider(): \Iterator
    {
        yield 'accessPermission'
            => [AccessPermissionInterface::class, 'accessPermission', 'staff', 'setAccessPermission'];

        yield 'accessDeniedBehavior'
            => [AccessPermissionInterface::class, 'accessDeniedBehavior', 'exception', 'setAccessDeniedBehavior'];

        yield 'backendId' => [BackendIdInterface::class, 'backendId', 'Solr', 'setBackendId'];

        yield 'checkEnabled' => [CheckEnabledInterface::class, 'checkEnabled', true, 'setCheckEnabled'];

        yield 'defaultTab' => [DefaultTabInterface::class, 'defaultTab', 'holdings', 'setDefaultTab'];

        yield 'fallbackDefaultTab'
            => [DefaultTabInterface::class, 'fallbackDefaultTab', 'desc', 'setFallbackDefaultTab'];
    }

    /**
     * Test that each configuration key is applied to the matching action setter.
     *
     * @param string $interface Interface the action must implement
     * @param string $key       Configuration key
     * @param mixed  $value     Configured value
     * @param string $setter    Setter expected to be called with the value
     *
     * @return void
     */
    #[DataProvider('configKeySetterProvider')]
    public function testAppliesConfigKeyToSetter(string $interface, string $key, mixed $value, string $setter): void
    {
        $action = $this->getActionMock($interface);
        $action->expects($this->once())->method($setter)->with($value);
        $manager = $this->buildManager([['actionIds' => ['x'], $key => $value]]);
        $manager->applyActionConfig($action, null, 'x');
    }

    /**
     * Test that an empty fallbackDefaultTab falls back to the site's default record tab setting.
     *
     * @return void
     */
    public function testEmptyFallbackDefaultTabUsesSiteDefault(): void
    {
        $action = $this->getActionMock(DefaultTabInterface::class);
        $action->expects($this->once())->method('setFallbackDefaultTab')->with('holdings');
        $manager = $this->buildManager(
            [['actionIds' => ['x'], 'fallbackDefaultTab' => '']],
            config: ['Site' => ['defaultRecordTab' => 'holdings']]
        );
        $manager->applyActionConfig($action, null, 'x');
    }

    /**
     * Test that an empty fallbackDefaultTab with no site default leaves the fallback tab unset.
     *
     * @return void
     */
    public function testEmptyFallbackDefaultTabWithoutSiteDefaultDoesNothing(): void
    {
        $action = $this->getActionMock(DefaultTabInterface::class);
        $action->expects($this->never())->method('setFallbackDefaultTab');
        $manager = $this->buildManager([['actionIds' => ['x'], 'fallbackDefaultTab' => '']]);
        $manager->applyActionConfig($action, null, 'x');
    }

    /**
     * Test that the poweredBy value is stored in the globals container.
     *
     * @return void
     */
    public function testPoweredByStoredInGlobalsContainer(): void
    {
        $globals = new GlobalsContainer();
        $action = $this->getActionMock(ActionConfigInterface::class);
        $manager = $this->buildManager([['actionIds' => ['x'], 'poweredBy' => 'EDS']], globals: $globals);
        $manager->applyActionConfig($action, null, 'x');
        $this->assertSame('EDS', $globals['poweredBy']);
    }

    /**
     * Data provider for testThrowsWhenActionMissingRequiredInterface().
     *
     * @return \Iterator
     */
    public static function missingInterfaceProvider(): \Iterator
    {
        yield 'accessPermission' => ['accessPermission', 'staff', BackendIdInterface::class];

        yield 'accessDeniedBehavior' => ['accessDeniedBehavior', 'exception', BackendIdInterface::class];

        yield 'backendId' => ['backendId', 'Solr', AccessPermissionInterface::class];

        yield 'checkEnabled' => ['checkEnabled', true, BackendIdInterface::class];

        yield 'defaultTab' => ['defaultTab', 'holdings', BackendIdInterface::class];

        yield 'fallbackDefaultTab' => ['fallbackDefaultTab', 'desc', BackendIdInterface::class];
    }

    /**
     * Test that applying a key throws when the action does not implement the required interface.
     *
     * @param string $key            Configuration key
     * @param mixed  $value          Configured value
     * @param string $otherInterface A configuration interface the action implements instead of the required one
     *
     * @return void
     */
    #[DataProvider('missingInterfaceProvider')]
    public function testThrowsWhenActionMissingRequiredInterface(
        string $key,
        mixed $value,
        string $otherInterface
    ): void {
        $action = $this->getActionMock($otherInterface);
        $manager = $this->buildManager([['actionIds' => ['x'], $key => $value]]);
        $this->expectException(ConfigException::class);
        $manager->applyActionConfig($action, null, 'x');
    }

    /**
     * Test that an unknown configuration key throws a ConfigException.
     *
     * @return void
     */
    public function testInvalidConfigKeyThrows(): void
    {
        $action = $this->getActionMock(ActionConfigInterface::class);
        $manager = $this->buildManager([['actionIds' => ['x'], 'unknownKey' => 'value']]);
        $this->expectException(ConfigException::class);
        $manager->applyActionConfig($action, null, 'x');
    }

    /**
     * Test that a prefix-type actionIds entry matches identifiers sharing the prefix.
     *
     * @return void
     */
    public function testPrefixActionIdMatches(): void
    {
        $globals = new GlobalsContainer();
        $action = $this->getActionMock(ActionConfigInterface::class);
        $manager = $this->buildManager(
            [['actionIds' => [['type' => 'prefix', 'prefix' => 'admin/']], 'poweredBy' => 'EDS']],
            globals: $globals
        );
        $manager->applyActionConfig($action, null, 'admin/users');
        $this->assertSame('EDS', $globals['poweredBy']);
    }

    /**
     * Test that no configuration is applied when neither a string nor a prefix actionId matches the identifier.
     *
     * @return void
     */
    public function testNoMatchingConfigLeavesActionUntouched(): void
    {
        $globals = new GlobalsContainer();
        $action = $this->getActionMock(ActionConfigInterface::class);
        $manager = $this->buildManager(
            [['actionIds' => [['type' => 'prefix', 'prefix' => 'admin/'], 'other'], 'poweredBy' => 'EDS']],
            globals: $globals
        );
        $manager->applyActionConfig($action, null, 'x');
        $this->assertNull($globals['poweredBy']);
    }

    /**
     * Test that an unknown actionIds entry type throws a ConfigException.
     *
     * @return void
     */
    public function testInvalidActionIdTypeThrows(): void
    {
        $action = $this->getActionMock(ActionConfigInterface::class);
        $manager = $this->buildManager([['actionIds' => [['type' => 'unknown']]]]);
        $this->expectException(ConfigException::class);
        $manager->applyActionConfig($action, null, 'x');
    }
}
