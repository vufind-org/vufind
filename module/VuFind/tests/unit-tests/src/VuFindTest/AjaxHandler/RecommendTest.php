<?php
/**
 * Recommend test class.
 *
 * PHP version 7
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

use Laminas\View\Renderer\PhpRenderer;
use VuFind\AjaxHandler\Recommend;
use VuFind\AjaxHandler\RecommendFactory;
use VuFind\Recommend\PluginManager;
use VuFind\Recommend\RecommendInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings;
use VuFind\View\Helper\Root\Recommend as RecommendHelper;

/**
 * Recommend test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecommendTest extends \VuFindTest\Unit\AjaxHandlerTest
{
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

        // Set up results plugin manager:
        $resultsManager = $this->container
            ->createMock(ResultsManager::class, ['get']);
        $resultsManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->will($this->returnValue($this->container->createMock(Results::class)));
        $this->container->set(ResultsManager::class, $resultsManager);

        // Set up view helper and renderer:
        $viewHelper = $this->container->createMock(RecommendHelper::class);
        $view = $this->container->createMock(PhpRenderer::class, ['plugin']);
        $view->expects($this->once())->method('plugin')
            ->with($this->equalTo('recommend'))
            ->will($this->returnValue($viewHelper));
        $this->container->set('ViewRenderer', $view);

        // Build and test the ajax handler:
        $factory = new RecommendFactory();
        $handler = $factory($this->container, Recommend::class);
        $params = $this->getParamsHelper(['mod' => 'foo']);
        $this->assertEquals([null], $handler->handleRequest($params));
    }
}
