<?php

/**
 * Base class for Collections action tests.
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

namespace VuFindTest\Action\Collections;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\HelperInterface;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Http\RouteHelper;
use VuFind\I18n\Sorter;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results as SearchResults;
use VuFind\Search\Results\PluginManager as SearchResultsPluginManager;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindSearch\Command\AbstractBase as AbstractCommand;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Service as SearchService;

/**
 * Base class for Collections action tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractCollectionsActionTestCase extends TestCase
{
    /**
     * Template parameters captured from the renderTemplate() call.
     *
     * @var array
     */
    protected array $capturedTemplateParams = [];

    /**
     * Command captured from the search service invoke() call.
     *
     * @var ?CommandInterface
     */
    protected ?CommandInterface $capturedCommand = null;

    /**
     * Build a Collections action and wire up its setter-injected dependencies.
     *
     * @param class-string      $class         Action class to build
     * @param array             $config        VuFind configuration
     * @param SearchService     $searchService Search service
     * @param HelperInterface[] $helpers       Extra action helpers
     * @param ?SearchResults    $searchResults Search results returned by the results plugin manager (index browse)
     * @param ?Sorter           $sorter        Sorter (defaults to a stub; pass a real one when sorting matters)
     *
     * @return AbstractAction
     */
    protected function buildAction(
        string $class,
        array $config,
        SearchService $searchService,
        array $helpers = [],
        ?SearchResults $searchResults = null,
        ?Sorter $sorter = null
    ): AbstractAction {
        if (null !== $searchResults) {
            $resultsManager = $this->createMock(SearchResultsPluginManager::class);
            $resultsManager->method('get')->willReturn($searchResults);
        } else {
            $resultsManager = $this->createStub(SearchResultsPluginManager::class);
        }
        $action = new $class(
            $config,
            $searchService,
            $resultsManager,
            $sorter ?? $this->createStub(Sorter::class),
        );
        $action->setHelperPluginManager($this->getHelperPluginManager($helpers));
        $action->setRouteHelper($this->createStub(RouteHelper::class));
        $action->setSessionSettings($this->createStub(SessionSettings::class));
        if ($action instanceof AbstractTemplateRenderingAction) {
            $action->setTemplateRenderer($this->getTemplateRenderer());
        }
        return $action;
    }

    /**
     * Get a helper plugin manager returning the provided helpers, plus a PermissionHelper for access.
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
     * Get a template renderer and capture the parameters passed to renderTemplate().
     *
     * @return TemplateRendererInterface
     */
    protected function getTemplateRenderer(): TemplateRendererInterface
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('renderTemplate')->willReturnCallback(
            function (
                ServerRequestInterface $request,
                ResponseInterface $response,
                ?string $template = null,
                array $params = [],
            ): ResponseInterface {
                $this->capturedTemplateParams = $params;
                return $response;
            }
        );
        return $renderer;
    }

    /**
     * Get a mock search service that returns a command with the given result when invoked. The command passed to
     * invoke() is captured so tests can assert on the query that was built.
     *
     * @param mixed $result Result to return from the executed command
     *
     * @return SearchService
     */
    protected function getMockSearchService(mixed $result): SearchService
    {
        $executedCommand = $this->createMock(AbstractCommand::class);
        $executedCommand->method('getResult')->willReturn($result);
        $searchService = $this->createMock(SearchService::class);
        $searchService->method('invoke')->willReturnCallback(
            function (CommandInterface $command) use ($executedCommand): CommandInterface {
                $this->capturedCommand = $command;
                return $executedCommand;
            }
        );
        return $searchService;
    }

    /**
     * Get a mock search results object that returns the hierarchy_browse facet values, for the index browse path.
     *
     * @param array $facetList  Facet entries to return from getFullFieldFacets()
     * @param array $filterList Filter list to return from getParams()->getFilterList()
     *
     * @return SearchResults
     */
    protected function getMockSearchResults(array $facetList, array $filterList = []): SearchResults
    {
        $params = $this->createMock(Params::class);
        $params->method('getFilterList')->willReturn($filterList);
        $results = $this->createMock(SearchResults::class);
        $results->method('getParams')->willReturn($params);
        $results->method('getFullFieldFacets')->willReturn(
            ['hierarchy_browse' => ['data' => ['list' => $facetList]]]
        );
        return $results;
    }

    /**
     * Invoke an action with the given query parameters.
     *
     * @param AbstractAction $action      Action to invoke
     * @param array          $queryParams Query parameters for the request
     *
     * @return ResponseInterface
     */
    protected function invokeAction(AbstractAction $action, array $queryParams = []): ResponseInterface
    {
        $request = (new ServerRequest())->withQueryParams($queryParams);
        return $action($request, new Response());
    }
}
