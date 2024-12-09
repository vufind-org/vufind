<?php

/**
 * FavoritesService Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Favorites;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsService;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFind\Tags\TagsService;

/**
 * FavoritesService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FavoritesServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a FavoritesService object.
     *
     * @param UserListServiceInterface $listService Mock list service (optional)
     *
     * @return FavoritesService
     */
    protected function getFavoritesService(?UserListServiceInterface $listService = null): FavoritesService
    {
        return new FavoritesService(
            $this->createMock(ResourceServiceInterface::class),
            $this->createMock(ResourceTagsService::class),
            $listService ?? $this->createMock(UserListServiceInterface::class),
            $this->createMock(UserResourceServiceInterface::class),
            $this->createMock(UserServiceInterface::class),
            $this->createMock(ResourcePopulator::class),
            $this->createMock(TagsService::class),
            $this->createMock(Loader::class)
        );
    }

    /**
     * Create a mock user object.
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUser(): MockObject&UserEntityInterface
    {
        $user = $this->createMock(UserEntityInterface::class);
        $user->method('getId')->willReturn(1234);
        return $user;
    }

    /**
     * Test that an exception is thrown if a non-logged-in user tries to create a new
     * list.
     *
     * @return void
     */
    public function testLoginRequiredToCreateList(): void
    {
        $this->expectException(\VuFind\Exception\LoginRequired::class);

        $service = $this->getFavoritesService();
        $service->createListForUser(null);
    }

    /**
     * Test that a new list is populated appropriately.
     *
     * @return void
     */
    public function testNewListIsPopulatedCorrectly()
    {
        $user = $this->getMockUser();
        $newList = $this->createMock(UserListEntityInterface::class);
        $newList->expects($this->once())->method('setCreated')->willReturn($newList);
        $newList->expects($this->once())->method('setUser')->with($user)->willReturn($newList);
        $listService = $this->createMock(UserListServiceInterface::class);
        $listService->expects($this->once())->method('createEntity')->willReturn($newList);
        $service = $this->getFavoritesService($listService);
        $service->createListForUser($user);
    }
}
