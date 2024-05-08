<?php

/**
 * Unit tests for Socialstats controller.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014-2024.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Controller;

use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;

/**
 * Unit tests for Socialstats controller.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SocialstatsControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test language mappings.
     *
     * @return void
     */
    public function testHome()
    {
        // Create mocks to simulate database lookups:
        $container = new \VuFindTest\Container\MockContainer($this);
        $tables = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Db\Table\PluginManager::class, $tables);
        $dbServices = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Db\Service\PluginManager::class, $dbServices);
        $c = new \VuFindAdmin\Controller\SocialstatsController($container);

        $mockCommentsStats = ['users' => 5, 'resources' => 7, 'total' => 23];
        $commentsService = $this->createMock(CommentsServiceInterface::class);
        $commentsService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($mockCommentsStats));
        $dbServices->set(CommentsServiceInterface::class, $commentsService);

        $userresource = $this->getMockBuilder(\VuFind\Db\Table\UserResource::class)
            ->onlyMethods(['getStatistics'])->disableOriginalConstructor()->getMock();
        $userresource->expects($this->once())->method('getStatistics')->will($this->returnValue('userresource-data'));
        $tables->set('userresource', $userresource);

        $mockTagStats = ['users' => 31, 'resources' => 32, 'total' => 33];
        $tagService = $this->createMock(TagServiceInterface::class);
        $tagService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($mockTagStats));
        $dbServices->set(TagServiceInterface::class, $tagService);

        $mockRatingsStats = ['users' => 1, 'resources' => 2, 'total' => 3];
        $ratingsService = $this->createMock(RatingsServiceInterface::class);
        $ratingsService->expects($this->any())->method('getStatistics')
            ->will($this->returnValue($mockRatingsStats));
        $dbServices->set(RatingsServiceInterface::class, $ratingsService);

        $viewRenderer = $this->getMockBuilder(\Laminas\View\Renderer\RendererInterface::class)
            ->onlyMethods(['getEngine', 'setResolver', 'render'])->addMethods(['plugin'])->getMock();
        $viewRenderer->expects($this->once())->method('plugin')->withAnyParameters()
            ->will($this->returnValue(function ($input) {
                return 'url';
            }));
        $container->set('ViewRenderer', $viewRenderer);

        // Confirm properly-constructed view object:
        $view = $c->homeAction();
        $this->assertEquals('admin/socialstats/home', $view->getTemplate());
        $this->assertEquals($mockCommentsStats, $view->comments);
        $this->assertEquals('userresource-data', $view->favorites);
        $this->assertEquals($mockTagStats, $view->tags);
        $this->assertEquals($mockRatingsStats, $view->ratings);
    }
}
