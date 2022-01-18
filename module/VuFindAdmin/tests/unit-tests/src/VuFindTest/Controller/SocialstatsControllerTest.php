<?php

/**
 * Unit tests for Socialstats controller.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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

use VuFind\Db\Service\TagService;

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
        $tables = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Db\Table\PluginManager::class, $tables);
        $dbServices = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Db\Service\PluginManager::class, $dbServices);

        // Create and register mock table objects:
        $comments = $this->getMockBuilder(\VuFind\Db\Table\Comments::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $comments->expects($this->once())->method('getStatistics')
            ->will($this->returnValue('comments-data'));
        $tables->set('comments', $comments);
        $userresource = $this->getMockBuilder(\VuFind\Db\Table\UserResource::class)
            ->onlyMethods(['getStatistics'])->disableOriginalConstructor()
            ->getMock();
        $userresource->expects($this->once())->method('getStatistics')
            ->will($this->returnValue('userresource-data'));
        $tables->set('userresource', $userresource);

        // Create and register mock tag service
        $tagService = $this->getMockBuilder(TagService::class)
            ->disableOriginalConstructor()->onlyMethods(['getStatistics'])
            ->getMock();
        $mockTagStats = ['users' => 5, 'resources' => 7, 'total' => 23];
        $tagService->expects($this->once())->method('getStatistics')
            ->will($this->returnValue($mockTagStats));
        $dbServices->set(TagService::class, $tagService);

        // Build the controller to test:
        $c = new \VuFindAdmin\Controller\SocialstatsController($container);

        // Confirm properly-constructed view object:
        $view = $c->homeAction();

        $this->assertEquals('admin/socialstats/home', $view->getTemplate());
        $this->assertEquals('comments-data', $view->comments);
        $this->assertEquals('userresource-data', $view->favorites);
        $this->assertEquals($mockTagStats, $view->tags);
    }
}
