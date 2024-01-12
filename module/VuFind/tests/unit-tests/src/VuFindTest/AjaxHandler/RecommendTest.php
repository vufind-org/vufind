<?php

/**
 * Recommend test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\Recommend;
use VuFind\AjaxHandler\RecommendFactory;
use VuFind\Recommend\PluginManager;
use VuFind\Recommend\RecommendInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings;
use VuFind\View\Helper\Root\Recommend as RecommendHelper;

use function count;

/**
 * Recommend test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecommendTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    /**
     * Get a mock params object.
     *
     * @param \VuFindSearch\Query\Query $query Query to include in container.
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = null)
    {
        if (null === $query) {
            $query = new \VuFindSearch\Query\Query('foo', 'bar');
        }
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        return $params;
    }

    /**
     * Get a mock results object.
     *
     * @param \VuFind\Search\Solr\Params $params Params to include in container.
     *
     * @return Results
     */
    protected function getMockResults($params = null): Results
    {
        if (null === $params) {
            $params = $this->getMockParams();
        }
        $results = $this->getMockBuilder(Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Test the AJAX handler's basic response.
     *
     * @return void
     */
    public function testResponse()
    {
        // Set up session settings:
        $ss = $this->container->createMock(Settings::class, ['disableWrite']);
        $ss->expects($this->once())->method('disableWrite');
        $this->container->set(Settings::class, $ss);

        // Set up recommend plugin manager:
        $mockPlugin = $this->container->createMock(RecommendInterface::class);
        $rm = $this->container->createMock(PluginManager::class, ['get']);
        $rm->expects($this->once())->method('get')->with($this->equalTo('foo'))
            ->will($this->returnValue($mockPlugin));
        $this->container->set(PluginManager::class, $rm);

        // Set up results object, including expectation to confirm that
        // Params is initialized with the correct request.
        $results = $this->getMockResults();
        $testRequestInitialization = function ($request) {
            // exactly one parameter: mod = foo
            return $request->get('mod') === 'foo'
                && count($request) === 1;
        };
        $results->getParams()->expects($this->once())
            ->method('initFromRequest')
            ->with($this->callback($testRequestInitialization));

        // Set up results manager:
        $resultsManager = $this->container
            ->createMock(ResultsManager::class, ['get']);
        $resultsManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->will($this->returnValue($results));
        $this->container->set(ResultsManager::class, $resultsManager);

        // Set up view helper and renderer:
        $view = new \Laminas\View\Renderer\PhpRenderer();
        $plugins = new \VuFindTest\Container\MockViewHelperContainer($this);
        $plugins->set('recommend', $plugins->get(RecommendHelper::class));
        $view->setHelperPluginManager($plugins);
        $this->container->set('ViewRenderer', $view);

        // Build and test the ajax handler:
        $factory = new RecommendFactory();
        $handler = $factory($this->container, Recommend::class);
        $params = $this->getParamsHelper(['mod' => 'foo']);
        $this->assertEquals([null], $handler->handleRequest($params));
    }
}
