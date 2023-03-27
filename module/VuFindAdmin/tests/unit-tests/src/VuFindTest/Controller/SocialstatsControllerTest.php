<?php

/**
 * Unit tests for Socialstats controller.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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

use VuFind\Db\Service\CommentsService;
use VuFind\Db\Service\RatingsService;
use VuFind\Db\Service\TagService;
use VuFind\Db\Service\UserResourceService;

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
    public function testHome(): void
    {
        // Create mock containers for fetching database-related services:
        $container = new \VuFindTest\Container\MockContainer($this);
        $dbServices = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Db\Service\PluginManager::class, $dbServices);

        $mockCommentsStats = ['users' => 5, 'resources' => 7, 'total' => 23];
        $commentsService = $this->getMockBuilder(CommentsService::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $commentsService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($mockCommentsStats));
        $dbServices->set(CommentsService::class, $commentsService);

        $userResourceStats = ['users' => 5,
            'lists' =>4,
            'resources' => 7,
            'total' => 23
        ];
        $userResourceService = $this->getMockBuilder(UserResourceService::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $userResourceService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($userResourceStats));
        $dbServices->set(UserResourceService::class, $userResourceService);

        $mockRatingsStats = ['users' => 1, 'resources' => 2, 'total' => 3];
        $ratingsService = $this->getMockBuilder(RatingsService::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $ratingsService->expects($this->any())->method('getStatistics')
            ->will($this->returnValue($mockRatingsStats));
        $dbServices->set(RatingsService::class, $ratingsService);

        $mockTagStats = ['users' => 31, 'resources' => 32, 'total' => 33];
        $tagService = $this->getMockBuilder(TagService::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $tagService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($mockTagStats));
        $dbServices->set(TagService::class, $tagService);

        // Build the controller to test:
        $c = new \VuFindAdmin\Controller\SocialstatsController($container);

        // Confirm properly-constructed view object:
        $view = $c->homeAction();
        $this->assertEquals('admin/socialstats/home', $view->getTemplate());
        $this->assertEquals($mockCommentsStats, $view->comments);
        $this->assertEquals($userResourceStats, $view->favorites);
        $this->assertEquals($mockTagStats, $view->tags);
        $this->assertEquals($mockRatingsStats, $view->ratings);
    }
}
