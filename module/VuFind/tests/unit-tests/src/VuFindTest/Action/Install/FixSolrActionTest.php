<?php

/**
 * Install FixSolrAction test class.
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

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\Install\FixSolrAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\PathResolver;
use VuFindSearch\Command\AbstractBase as AbstractCommand;
use VuFindSearch\Service as SearchService;

/**
 * Install FixSolrAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixSolrActionTest extends AbstractInstallActionTestCase
{
    /**
     * Get a search service whose command either returns a result or throws when executed.
     *
     * @param ?\Throwable $throws Exception thrown when the command is invoked (null = success)
     *
     * @return SearchService
     */
    protected function getSearchService(?\Throwable $throws = null): SearchService
    {
        $searchService = $this->createMock(SearchService::class);
        if (null !== $throws) {
            $searchService->method('invoke')->willThrowException($throws);
        } else {
            $command = $this->createMock(AbstractCommand::class);
            $command->method('getResult')->willReturn([]);
            $searchService->method('invoke')->willReturn($command);
        }
        return $searchService;
    }

    /**
     * Test that a reachable Solr redirects back to the install home page.
     *
     * @return void
     */
    public function testRedirectsHomeWhenSolrIsReachable(): void
    {
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'install-home')->willReturn($expectedResponse);

        $action = $this->buildAction(
            FixSolrAction::class,
            [SearchService::class => $this->getSearchService()],
            ['System' => ['autoConfigure' => true]],
            [RedirectHelper::class => $redirectHelper]
        );
        $this->assertSame($expectedResponse, $action($this->getServerRequest(), new Response()));
    }

    /**
     * Test that an unreachable Solr renders troubleshooting details, rewriting loopback hosts to the request host.
     *
     * @return void
     */
    public function testRendersTroubleshootingWhenSolrIsUnreachable(): void
    {
        $configFile = '/usr/local/vufind/local/config/vufind/config.ini';
        $location = $this->createMock(ConfigLocationInterface::class);
        $location->method('getPath')->willReturn($configFile);
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('getForcedLocalConfigLocation')->willReturn($location);

        $action = $this->buildAction(
            FixSolrAction::class,
            [
                SearchService::class => $this->getSearchService(new \Exception('Solr down')),
                PathResolver::class => $pathResolver,
            ],
            [
                'System' => ['autoConfigure' => true],
                'Index' => ['url' => 'http://localhost:8983/solr', 'default_core' => 'biblio'],
            ]
        );
        $request = $this->getServerRequest(serverParams: ['HTTP_HOST' => 'discovery.example.edu']);
        $action($request, new Response());

        $this->assertSame('http://localhost:8983/solr', $this->capturedTemplateParams['rawUrl']);
        $this->assertSame('http://discovery.example.edu:8983/solr', $this->capturedTemplateParams['userUrl']);
        $this->assertSame('biblio', $this->capturedTemplateParams['core']);
        $this->assertSame($configFile, $this->capturedTemplateParams['configFile']);
    }
}
