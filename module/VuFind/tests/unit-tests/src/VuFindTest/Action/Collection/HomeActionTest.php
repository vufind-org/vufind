<?php

/**
 * Collection HomeAction test class.
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

namespace VuFindTest\Action\Collection;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\Collection\HomeAction;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Http\RouteHelper;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Collection HomeAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomeActionTest extends TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Build a Collection HomeAction with stubbed record dependencies, overriding only the collaborators a test cares
     * about.
     *
     * @param array           $config         VuFind configuration
     * @param ?SearchMemory   $searchMemory   Search memory (defaults to a stub)
     * @param ?RecordRouter   $recordRouter   Record router (defaults to a stub)
     * @param ?RouteHelper    $routeHelper    Route helper (defaults to a stub)
     * @param ?RedirectHelper $redirectHelper Redirect helper for HelperPluginManager to return
     * @param ?RecordDriver   $driver         Record returned by RecordLoader's load()
     *
     * @return HomeAction
     */
    protected function buildAction(
        array $config = [],
        ?SearchMemory $searchMemory = null,
        ?RecordRouter $recordRouter = null,
        ?RouteHelper $routeHelper = null,
        ?RedirectHelper $redirectHelper = null,
        ?RecordDriver $driver = null
    ): HomeAction {
        if (null !== $driver) {
            $recordLoader = $this->createMock(RecordLoader::class);
            $recordLoader->method('load')->willReturn($driver);
        } else {
            $recordLoader = $this->createStub(RecordLoader::class);
        }

        $action = new HomeAction(
            $searchMemory ?? $this->createStub(SearchMemory::class),
            $this->createStub(TabManager::class),
            $this->createStub(AuthManager::class),
            $recordLoader,
            $recordRouter ?? $this->createStub(RecordRouter::class),
            $this->createStub(ResultScroller::class),
            $config
        );
        $action->setBackendId('Solr');

        $permissionHelper = $this->createMock(PermissionHelper::class);
        $permissionHelper->method('getPermissionBehaviorConfig')->willReturn([]);
        $redirectHelper ??= $this->createStub(RedirectHelper::class);
        $manager = $this->createMock(HelperPluginManager::class);
        $manager->method('get')->willReturnCallback(
            fn ($name) => match ($name) {
                PermissionHelper::class => $permissionHelper,
                RedirectHelper::class => $redirectHelper,
                default => throw new \Exception("Unexpected helper requested: $name"),
            }
        );
        $action->setHelperPluginManager($manager);
        $action->setRouteHelper($routeHelper ?? $this->createStub(RouteHelper::class));
        $action->setSessionSettings($this->createStub(SessionSettings::class));
        $action->setTemplateRenderer($this->createStub(TemplateRendererInterface::class));
        return $action;
    }

    /**
     * Test that a configured default tab is applied as the fallback default tab during initialization.
     *
     * @return void
     */
    public function testInitAppliesConfiguredDefaultTab(): void
    {
        $action = $this->buildAction(['Collections' => ['defaultTab' => 'holdings']]);
        $this->callMethod($action, 'init');
        $this->assertSame('holdings', $this->getProperty($action, 'fallbackDefaultTab'));
    }

    /**
     * Test that requesting a tab when collections are disabled redirects to the plain record view.
     *
     * @return void
     */
    public function testShowTabRedirectsToRecordWhenCollectionsDisabled(): void
    {
        $recordRouter = $this->createMock(RecordRouter::class);
        $recordRouter->method('getTabRouteDetails')
            ->willReturn(['route' => 'record', 'params' => ['id' => 'coll1']]);
        $routeHelper = $this->createMock(RouteHelper::class);
        $routeHelper->method('getUrlFromRoute')->with('record', ['id' => 'coll1'])->willReturn('/Record/coll1');
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToUrl')
            ->with($this->isInstanceOf(ResponseInterface::class), '/Record/coll1')->willReturn($expectedResponse);

        $action = $this->buildAction(
            recordRouter: $recordRouter,
            routeHelper: $routeHelper,
            redirectHelper: $redirectHelper,
            driver: $this->createStub(RecordDriver::class)
        );

        $routeMatch = new RouteMatch(['tab' => 'description']);
        $request = (new ServerRequest())->withParsedBody([])->withAttribute('route-match', $routeMatch);

        $this->assertSame($expectedResponse, $action($request, new Response()));
    }

    /**
     * Data provider for testActionRedirectsToCollectionRoute().
     *
     * @return \Iterator
     */
    public static function collectionRedirectProvider(): \Iterator
    {
        yield 'no active search' => [null, []];

        yield 'active search passes sid' => [42, ['sid' => 42]];
    }

    /**
     * Test that when collections are active and the loaded record is a collection, the action redirects to the
     * collection route, carrying the current search id through as a "sid" query parameter when one is present.
     *
     * @param ?int  $currentSearchId     Current search id reported by search memory
     * @param array $expectedQueryParams Query parameters expected on the redirect
     *
     * @return void
     */
    #[DataProvider('collectionRedirectProvider')]
    public function testActionRedirectsToCollectionRoute(?int $currentSearchId, array $expectedQueryParams): void
    {
        $searchMemory = $this->createMock(SearchMemory::class);
        $searchMemory->method('getCurrentSearchId')->willReturn($currentSearchId);
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'collection', $this->anything(), $expectedQueryParams)
            ->willReturn($expectedResponse);

        $driver = $this->createMock(RecordDriver::class);
        $driver->method('tryMethod')->with('isCollection')->willReturn(true);

        $action = $this->buildAction(
            ['Collections' => ['collections' => true]],
            searchMemory: $searchMemory,
            redirectHelper: $redirectHelper,
            driver: $driver
        );

        $routeMatch = new RouteMatch(['id' => 'coll1']);
        $routeMatch->setMatchedRouteName('record');
        $request = (new ServerRequest())
            ->withQueryParams(['checkRoute' => '1'])
            ->withParsedBody([])
            ->withAttribute('route-match', $routeMatch);

        $this->assertSame($expectedResponse, $action($request, new Response()));
    }
}
