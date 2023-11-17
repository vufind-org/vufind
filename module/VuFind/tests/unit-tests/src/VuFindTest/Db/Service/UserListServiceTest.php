<?php

/**
 * UserListService Test Class
 *
 * PHP version 8
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
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserList;

/**
 * UserListService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserListServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that a new list contains the appropriate user ID.
     *
     * @return void
     */
    public function testNewListContainsCreatorUserId()
    {
        $user = new User();
        $user->setUsername('sud');
        $listService = $this->getMockListService();
        $list = $listService->getNew($user);

        $this->assertEquals($user->getUsername(), $list->getUser()->getUsername());
    }

    /**
     * Test that an exception is thrown if a non-logged-in user tries to create a new list.
     *
     * @return void
     */
    public function testLoginRequiredToCreateList()
    {
        $this->expectException(\VuFind\Exception\LoginRequired::class);

        $listService = $this->getMockListService();
        $listService->getNew(false);
    }

    /**
     * Test that new lists are distinct (not references to same object).
     *
     * @return void
     */
    public function testNewListsAreDistinct()
    {
        $listService = $this->getMockListService();
        $list1 = $listService->getNew(new User());
        $list2 = $listService->getNew(new User());
        $this->assertEquals('Title1', $list1->getTitle());
        $this->assertEquals('Title2', $list2->getTitle());
    }

    /**
     * Get a mock userList service.
     *
     * @return MockObject
     */
    public function getMockListService()
    {
        $mockContainer = new \VuFindTest\Container\MockContainer($this);
        $entityManager = $mockContainer->get(\Doctrine\ORM\EntityManager::class);
        $pluginManager = $mockContainer->get(\VuFind\Db\Entity\PluginManager::class);
        $tags = $mockContainer->get(\VuFind\Tags::class);
        $session = $mockContainer->get(\Laminas\Session\Container::class);
        $listService = $this->getMockBuilder(\VuFind\Db\Service\UserListService::class)
            ->setConstructorArgs([$entityManager, $pluginManager, $tags, $session])
            ->onlyMethods(['createUserList'])
            ->getMock();
        $callback = function () {
            static $i = 0;
            $i++;
            $list = new UserList();
            $list->setTitle("Title$i");
            return $list;
        };
        $listService->expects($this->atMost(3))->method('createUserList')
            ->willReturnCallback($callback);
        return $listService;
    }
}
